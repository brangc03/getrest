<?php
// dependencias
session_start();
require(__DIR__ . "/conexion.php");

// eval usr
if (!isset($_SESSION['usuario'])) {
    header("Location: /getrest/login.php");
    exit();
}

// eval rol (Solo Gerente)
if ($_SESSION['rol'] !== 'gerente') {
    header("Location: /getrest/inventario.php");
    exit();
}

$rol_usuario = ucfirst($_SESSION['rol'] ?? 'Usuario');
$iniciales = strtoupper(substr($rol_usuario, 0, 2));
$id_restaurante = $_SESSION['restaurante'];

$error = "";
$exito = "";

// fn bitacora
function registrarBitacora($con, $id_rest, $texto) {
    $c_f = 'Fecha_Hora'; $c_d = 'Descripcion';
    $q = $con->query("SHOW COLUMNS FROM Reportes");
    if($q) while($c = $q->fetch_assoc()){
        $f = strtolower($c['Field']);
        if($f=='fecha' || $f=='fecha_registro') $c_f = $c['Field'];
        if($f=='detalle' || $f=='accion') $c_d = $c['Field'];
    }
    $con->query("INSERT INTO Reportes (Id_Restaurante, $c_f, $c_d) VALUES ($id_rest, NOW(), '$texto')");
}

// procesar transaccion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['carrito_json'])) {
    $carrito = json_decode($_POST['carrito_json'], true);
    $metodo = $_POST['metodo_pago'] ?? 'Efectivo';
    
    $total = 0;
    $detalles_arr = [];
    
    if (is_array($carrito)) {
        foreach($carrito as $item) {
            $total += ($item['precio'] * $item['cantidad']);
            $detalles_arr[] = $item['cantidad'] . "x " . $item['nombre'];
        }
    }

    if (is_array($carrito) && count($carrito) > 0) {
        $texto_detalle = "Venta en $metodo - " . implode(", ", $detalles_arr);
        $texto_detalle_seguro = $conexion->real_escape_string($texto_detalle);

        try {
            $conexion->begin_transaction();

            $sql_v = "INSERT INTO ventas (Id_Restaurante, Fecha, Total, Detalle) VALUES ($id_restaurante, NOW(), $total, '$texto_detalle_seguro')";
            if ($conexion->query($sql_v)) {
                
                // buscar columna stock
                $col_stock = 'Stock_Actual';
                $q_cols = $conexion->query("SHOW COLUMNS FROM Ingrediente");
                if ($q_cols) {
                    while($c = $q_cols->fetch_assoc()){
                        if(strtolower($c['Field']) == 'stock') $col_stock = $c['Field'];
                        if(strtolower($c['Field']) == 'stock_actual') $col_stock = $c['Field'];
                    }
                }

                // restar inventario
                foreach ($carrito as $item) {
                    $id_receta = (int)$item['id'];
                    $cant_vendida = (int)$item['cantidad'];

                    $q_ingr = $conexion->query("SELECT Id_Ingrediente, Cantidad FROM Receta_Ingrediente WHERE Id_Receta = $id_receta");
                    if ($q_ingr) {
                        while ($ingr = $q_ingr->fetch_assoc()) {
                            $id_insumo = (int)$ingr['Id_Ingrediente'];
                            $cant_por_receta = floatval($ingr['Cantidad']);
                            $total_descontar = $cant_por_receta * $cant_vendida;

                            if ($total_descontar > 0) {
                                $sql_upd = "UPDATE Ingrediente SET $col_stock = GREATEST(0, $col_stock - $total_descontar) WHERE Id_Ingrediente = $id_insumo AND Id_Restaurante = $id_restaurante";
                                $conexion->query($sql_upd);
                            }
                        }
                    }
                }

                $conexion->commit();
                $exito = "Venta registrada exitosamente por $" . number_format($total, 2);
                
                // log bitacora
                $txt = $conexion->real_escape_string("Venta procesada por $rol_usuario. Monto: $" . number_format($total, 2) . " ($metodo)");
                registrarBitacora($conexion, $id_restaurante, $txt);

            } else {
                $conexion->rollback();
                $error = "Error al registrar la transacción: " . $conexion->error;
            }
        } catch (Exception $e) {
            $conexion->rollback();
            $error = "Fallo en base de datos: " . $e->getMessage();
        }
    } else {
        $error = "El ticket está vacío.";
    }
}
// extraccion pos
$recetas_pos = [];
try {
    $res = $conexion->query("SELECT * FROM Receta WHERE Id_Restaurante = $id_restaurante");
    if($res) {
        while($r = $res->fetch_assoc()){
            $r_lower = array_change_key_case($r, CASE_LOWER);
            $est = ($r_lower['estado'] ?? $r_lower['activa'] ?? '1').'';
            
            if($est === '1' || strtolower($est) === 'activo') {
                $recetas_pos[] = [
                    'id' => $r_lower['id_receta'],
                    'nombre' => $r_lower['nombre'],
                    'categoria' => $r_lower['categoria'] ?? 'Extra',
                    'precio' => (float)($r_lower['precio_venta'] ?? $r_lower['precio'] ?? 0)
                ];
            }
        }
    }
} catch (Exception $e) {}

// metricas locales
$ventas_hoy = [];
$total_hoy = 0;
try {
    $q_v = $conexion->query("SELECT * FROM ventas WHERE Id_Restaurante = $id_restaurante AND DATE(Fecha) = CURDATE() ORDER BY Fecha DESC");
    if($q_v) {
        while($v = $q_v->fetch_assoc()){
            $ventas_hoy[] = $v;
            $total_hoy += (float)$v['Total'];
        }
    }
} catch(Exception $e) {}

$json_recetas = json_encode($recetas_pos);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Getrest - Punto de Venta</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;700&family=Playfair+Display:ital,wght@0,400;0,600;1,400&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root {
            --crimson: #990616;
            --cream: #F1E9C6;
            --tan: #A18E5E;
            --charcoal: #474747;
            --green: #4F9150;
        }
        body { font-family: 'DM Sans', sans-serif; background-color: var(--cream); margin: 0; }
        .sidebar-item { background: transparent; color: #F1E9C688; }
        .sidebar-item:hover { background: #ffffff08; }
        .sidebar-item.active { background: var(--crimson); color: var(--cream); }
        ::-webkit-scrollbar { width: 4px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #47474722; border-radius: 10px; }
        
        .pos-item-card { transition: all 0.2s ease; cursor: pointer; }
        .pos-item-card:active { transform: scale(0.97); }
        .payment-btn { transition: all 0.2s ease; border: 2px solid transparent; }
        .payment-btn.selected { border-color: var(--tan); background: #A18E5E10; }

        /* FX */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in {
            opacity: 0;
            animation: fadeInUp 0.7s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }
        .delay-100 { animation-delay: 100ms; }
    </style>
</head>
<body>
    
    <div class="h-screen w-full flex overflow-hidden">

        <aside id="sidebar" class="flex flex-col h-full shrink-0 transition-all duration-300 z-30" style="width: 220px; background: var(--charcoal); border-right: 1px solid #5a5a5a44;">
            <div class="flex items-center gap-3 px-5 py-5 shrink-0 cursor-pointer" onclick="toggleSidebar()" title="Ocultar/Mostrar Menú" style="border-bottom: 1px solid #ffffff10;">
                <div class="w-7 h-7 rounded flex items-center justify-center shrink-0" style="background: var(--crimson);">
                    <span style="font-family: 'Playfair Display', serif; color: var(--cream); font-weight: 600; font-size: 12px;">G</span>
                </div>
                <span class="sidebar-text text-sm tracking-widest uppercase font-light" style="color: #F1E9C6cc;">GetRest</span>
            </div>

            <nav class="flex-1 flex flex-col gap-1 px-2 py-4">
                <?php if ($_SESSION['rol'] === 'gerente'): ?>
                    <a href="/getrest/dashboard.php" class="sidebar-item <?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '' ?> flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition-all duration-150">
                        <i data-lucide="layout-dashboard" class="w-4 h-4 shrink-0"></i>
                        <span class="sidebar-text">Inicio</span>
                    </a>
                <?php endif; ?>

                <?php if ($_SESSION['rol'] === 'gerente' || $_SESSION['rol'] === 'chef'): ?>
                    <a href="/getrest/recetas.php" class="sidebar-item <?= basename($_SERVER['PHP_SELF']) == 'recetas.php' ? 'active' : '' ?> flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition-all duration-150">
                        <i data-lucide="book-open" class="w-4 h-4 shrink-0"></i>
                        <span class="sidebar-text">Recetas</span>
                    </a>
                <?php endif; ?>

                <a href="/getrest/inventario.php" class="sidebar-item <?= basename($_SERVER['PHP_SELF']) == 'inventario.php' ? 'active' : '' ?> flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition-all duration-150">
                    <i data-lucide="package" class="w-4 h-4 shrink-0"></i>
                    <span class="sidebar-text">Inventario</span>
                </a>

                <?php if ($_SESSION['rol'] === 'gerente'): ?>
                    <a href="/getrest/ventas.php" class="sidebar-item <?= basename($_SERVER['PHP_SELF']) == 'ventas.php' ? 'active' : '' ?> flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition-all duration-150">
                        <i data-lucide="shopping-cart" class="w-4 h-4 shrink-0"></i>
                        <span class="sidebar-text">Ventas</span>
                    </a>
                    <a href="/getrest/reportes.php" class="sidebar-item <?= basename($_SERVER['PHP_SELF']) == 'reportes.php' ? 'active' : '' ?> flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition-all duration-150">
                        <i data-lucide="bar-chart-2" class="w-4 h-4 shrink-0"></i>
                        <span class="sidebar-text">Reportes</span>
                    </a>
                    <a href="/getrest/equipo.php" class="sidebar-item <?= basename($_SERVER['PHP_SELF']) == 'equipo.php' ? 'active' : '' ?> flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition-all duration-150">
                        <i data-lucide="users" class="w-4 h-4 shrink-0"></i>
                        <span class="sidebar-text">Mi Equipo</span>
                    </a>
                <?php endif; ?>
            </nav>

            <div class="px-2 pb-4 shrink-0" style="border-top: 1px solid #ffffff10;">
                <a href="/getrest/logout.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm w-full mt-4 transition-all duration-150 hover:text-[#990616]" style="color: #F1E9C655;">
                    <i data-lucide="log-out" class="w-4 h-4 shrink-0"></i>
                    <span class="sidebar-text">Cerrar sesión</span>
                </a>
            </div>
        </aside>

        <div class="flex-1 flex flex-col min-w-0 overflow-hidden" style="background: var(--cream);">
            
            <header class="flex items-center justify-between px-8 py-4 shrink-0" style="background: var(--charcoal); border-bottom: 1px solid #ffffff10;">
                <div class="flex items-center gap-3">
                    <button onclick="toggleSidebar()" class="p-1.5 rounded transition-colors hover:text-[#F1E9C6]" style="color: #F1E9C666;">
                        <i data-lucide="layout-dashboard" class="w-4 h-4"></i>
                    </button>
                    <div class="flex items-center gap-2 text-sm" style="color: #F1E9C655;">
                        <span>Inicio</span>
                        <i data-lucide="chevron-right" class="w-3 h-3"></i>
                        <span style="color: var(--cream);">Punto de Venta</span>
                    </div>
                </div>
                <div class="flex items-center gap-4">
                    <button onclick="abrirHistorial()" class="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-white bg-opacity-10 text-[#F1E9C6] hover:bg-opacity-20 transition-colors text-xs font-medium">
                        <i data-lucide="history" class="w-3.5 h-3.5"></i> Ventas de Hoy
                    </button>
                    <div class="flex items-center gap-2.5 ml-2">
                        <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-medium" style="background: var(--tan); color: var(--cream);">
                            <?= htmlspecialchars($iniciales) ?>
                        </div>
                        <span class="text-xs" style="color: #F1E9C688;"><?= htmlspecialchars($rol_usuario) ?></span>
                    </div>
                </div>
            </header>

            <div class="flex-1 overflow-hidden flex flex-col lg:flex-row px-8 py-6 gap-8">
                
                <div class="flex-1 flex flex-col min-h-0 animate-fade-in">
                    
                    <?php if ($error): ?>
                        <div class="mb-4 p-4 rounded-xl bg-red-50 border border-red-100 text-red-700 text-sm flex items-center gap-3 shadow-sm shrink-0">
                            <i data-lucide="alert-circle" class="w-5 h-5 text-red-500"></i><?= $error ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($exito): ?>
                        <div class="mb-4 p-4 rounded-xl bg-green-50 border border-green-100 text-green-700 text-sm flex items-center gap-3 shadow-sm shrink-0">
                            <i data-lucide="check-circle" class="w-5 h-5 text-green-500"></i><?= $exito ?>
                        </div>
                    <?php endif; ?>

                    <div id="js-alerts-container" class="shrink-0 transition-all duration-300"></div>

                    <div class="flex items-center justify-between mb-4 shrink-0 mt-2">
                        <h2 style="font-family: 'Playfair Display', serif; font-size: 1.6rem; color: var(--charcoal);">
                            Menú
                        </h2>
                        
                        <div class="flex items-center gap-1.5 p-1 bg-white rounded-lg shadow-sm border border-[#47474710]">
                            <button onclick="filtrarPOS('Todos', this)" class="cat-btn bg-[#474747] text-white px-3 py-1.5 rounded-md text-xs font-medium transition-colors">Todos</button>
                            <button onclick="filtrarPOS('Entradas', this)" class="cat-btn text-[#47474788] hover:bg-gray-50 px-3 py-1.5 rounded-md text-xs font-medium transition-colors">Entradas</button>
                            <button onclick="filtrarPOS('Principales', this)" class="cat-btn text-[#47474788] hover:bg-gray-50 px-3 py-1.5 rounded-md text-xs font-medium transition-colors">Fuertes</button>
                            <button onclick="filtrarPOS('Bebidas', this)" class="cat-btn text-[#47474788] hover:bg-gray-50 px-3 py-1.5 rounded-md text-xs font-medium transition-colors">Bebidas</button>
                            <button onclick="filtrarPOS('Postres', this)" class="cat-btn text-[#47474788] hover:bg-gray-50 px-3 py-1.5 rounded-md text-xs font-medium transition-colors">Postres</button>
                        </div>
                    </div>

                    <div id="posGrid" class="flex-1 overflow-y-auto grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4 pb-4 pr-2 content-start">
                    </div>
                </div>

                <div class="w-full lg:w-96 flex flex-col bg-white rounded-2xl shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-[#47474712] shrink-0 h-full overflow-hidden animate-fade-in delay-100">
                    <div class="p-5 border-b border-[#47474708] bg-[#FAFAFA] flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <i data-lucide="receipt" class="w-5 h-5" style="color: var(--tan);"></i>
                            <h3 class="text-sm font-bold uppercase tracking-wider text-[#474747]">Ticket Actual</h3>
                        </div>
                        <button onclick="limpiarCarrito()" class="text-xs text-red-400 hover:text-red-600 transition-colors font-medium">Limpiar</button>
                    </div>

                    <div id="cartItems" class="flex-1 overflow-y-auto p-2 flex flex-col gap-1">
                        <div class="h-full flex flex-col items-center justify-center opacity-50">
                            <i data-lucide="shopping-cart" class="w-10 h-10 mb-2 text-[#474747]"></i>
                            <p class="text-xs">Selecciona productos del menú</p>
                        </div>
                    </div>

                    <div class="p-5 border-t border-[#47474708] bg-[#FAFAFA]">
                        <div class="flex justify-between items-center mb-4">
                            <span class="text-sm font-bold uppercase tracking-wider text-[#47474788]">Total</span>
                            <span id="cartTotal" class="text-3xl font-serif text-[#474747]">$0.00</span>
                        </div>

                        <div class="grid grid-cols-3 gap-2 mb-4">
                            <button type="button" onclick="setMetodo('Efectivo', this)" class="payment-btn bg-white border border-[#47474715] rounded-xl py-3 flex flex-col items-center gap-1">
                                <i data-lucide="coins" class="w-4 h-4 text-[#474747]"></i>
                                <span class="text-[10px] font-medium text-[#474747]">Efectivo</span>
                            </button>
                            <button type="button" onclick="setMetodo('Tarjeta', this)" class="payment-btn bg-white border border-[#47474715] rounded-xl py-3 flex flex-col items-center gap-1">
                                <i data-lucide="credit-card" class="w-4 h-4 text-[#474747]"></i>
                                <span class="text-[10px] font-medium text-[#474747]">Tarjeta</span>
                            </button>
                            <button type="button" onclick="setMetodo('Transferencia', this)" class="payment-btn bg-white border border-[#47474715] rounded-xl py-3 flex flex-col items-center gap-1">
                                <i data-lucide="smartphone" class="w-4 h-4 text-[#474747]"></i>
                                <span class="text-[10px] font-medium text-[#474747]">Transf.</span>
                            </button>
                        </div>

                        <form method="POST" id="formVenta" class="m-0">
                            <input type="hidden" name="carrito_json" id="carrito_json">
                            <input type="hidden" name="metodo_pago" id="metodo_pago" value="">
                            <button type="button" onclick="procesarVenta()" class="w-full py-3.5 rounded-xl text-sm font-medium text-white transition-all shadow-md hover:shadow-lg transform hover:-translate-y-px flex items-center justify-center gap-2" style="background: var(--crimson);">
                                <i data-lucide="check-circle" class="w-4 h-4"></i> Cobrar Ticket
                            </button>
                        </form>
                    </div>
                </div>

            </div>
        </div>

        <div id="drawerOverlay" class="fixed inset-0 bg-[#47474777] z-40 hidden opacity-0 transition-opacity duration-300" onclick="cerrarHistorial()"></div>

        <aside id="historyDrawer" class="fixed right-0 top-0 h-full w-full max-w-sm bg-white shadow-2xl z-50 flex flex-col translate-x-full transition-transform duration-300 ease-in-out">
            <div class="flex items-center justify-between px-6 py-5 shrink-0" style="border-bottom: 1px solid #47474710;">
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-wider text-[#A18E5E] mb-1">Corte de Caja</p>
                    <h2 style="font-family: 'Playfair Display', serif; font-size: 1.4rem; color: var(--charcoal);">
                        Ventas de Hoy
                    </h2>
                </div>
                <button onclick="cerrarHistorial()" class="p-1.5 rounded text-[#47474755] hover:text-[#474747] transition-colors hover:bg-gray-50">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <div class="p-6 bg-[#FAFAFA] border-b border-[#47474708] shrink-0">
                <p class="text-xs uppercase tracking-wide font-bold text-[#47474788] mb-1">Ingresos del Día</p>
                <p class="text-3xl font-serif text-[#4F9150]">$<?= number_format($total_hoy, 2) ?></p>
                <p class="text-xs text-[#47474766] mt-1"><?= count($ventas_hoy) ?> tickets procesados hoy</p>
            </div>

            <div class="flex-1 overflow-y-auto p-6">
                <p class="text-[10px] font-bold uppercase tracking-wider text-[#47474755] mb-4">Historial de Tickets</p>
                
                <div class="flex flex-col gap-3">
                    <?php if (count($ventas_hoy) === 0): ?>
                        <div class="text-center py-10 text-sm text-[#47474755]">No hay ventas registradas hoy.</div>
                    <?php else: ?>
                        <?php foreach($ventas_hoy as $v): ?>
                            <div class="p-4 rounded-xl border border-[#47474712] flex items-center justify-between hover:border-[#A18E5E55] transition-colors">
                                <div class="min-w-0 pr-3">
                                    <p class="text-xs font-medium text-[#474747]">Ticket #<?= str_pad($v['Id_Venta'] ?? '0', 4, '0', STR_PAD_LEFT) ?></p>
                                    <p class="text-[10px] text-[#47474777] mt-0.5 truncate max-w-[200px]" title="<?= htmlspecialchars($v['Detalle'] ?? '') ?>">
                                        <?= date('h:i A', strtotime($v['Fecha'])) ?> · <?= htmlspecialchars($v['Detalle'] ?? 'Sin detalle') ?>
                                    </p>
                                </div>
                                <span class="text-sm font-bold text-[#474747] shrink-0">$<?= number_format($v['Total'], 2) ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </aside>

    </div>

    <script>
        lucide.createIcons();

        // fx inyeccion
        window.mostrarErrorJS = function(mensaje) {
            const contenedor = document.getElementById('js-alerts-container');
            contenedor.innerHTML = `
                <div class="mb-4 p-4 rounded-xl bg-red-50 border border-red-100 text-red-700 text-sm flex items-center justify-between shadow-sm shrink-0">
                    <div class="flex items-center gap-3">
                        <i data-lucide="alert-circle" class="w-5 h-5 text-red-500"></i>
                        <span>${mensaje}</span>
                    </div>
                    <button type="button" onclick="document.getElementById('js-alerts-container').innerHTML=''" class="text-red-400 hover:text-red-700 transition-colors">
                        <i data-lucide="x" class="w-4 h-4"></i>
                    </button>
                </div>
            `;
            lucide.createIcons();
            
            setTimeout(() => { contenedor.innerHTML = ''; }, 4000);
        }

        // panel general
        const sidebar = document.getElementById('sidebar');
        const sidebarTexts = document.querySelectorAll('.sidebar-text');
        function toggleSidebar() {
            if (sidebar.style.width === '220px') {
                sidebar.style.width = '64px';
                sidebarTexts.forEach(el => el.classList.add('hidden'));
            } else {
                sidebar.style.width = '220px';
                setTimeout(() => { sidebarTexts.forEach(el => el.classList.remove('hidden')); }, 150);
            }
        }

        // map vars
        const catalogo = <?= $json_recetas ?>;
        let carrito = [];

        function renderGrid(catFiltro = 'Todos') {
            const grid = document.getElementById('posGrid');
            const items = catalogo.filter(item => catFiltro === 'Todos' || item.categoria === catFiltro);
            
            if (items.length === 0) {
                grid.innerHTML = `<div class="col-span-full py-10 text-center text-sm text-[#47474755]">No hay platillos en esta categoría.</div>`;
                return;
            }

            grid.innerHTML = items.map(i => `
                <div onclick="addCarrito(${i.id}, '${i.nombre.replace(/'/g, "\\'")}', ${i.precio})" class="pos-item-card bg-white p-4 rounded-2xl border border-[#47474712] shadow-sm hover:shadow-md hover:border-[#A18E5E44] flex flex-col justify-between h-32">
                    <div>
                        <span class="text-[9px] font-bold uppercase tracking-wider text-[#A18E5E] bg-[#A18E5E10] px-2 py-0.5 rounded">${i.categoria}</span>
                        <p class="text-sm font-medium text-[#474747] mt-2 leading-tight line-clamp-2">${i.nombre}</p>
                    </div>
                    <p class="text-base font-serif text-[#990616] mt-auto">$${i.precio.toFixed(2)}</p>
                </div>
            `).join('');
        }

        window.filtrarPOS = function(cat, btnElement) {
            document.querySelectorAll('.cat-btn').forEach(b => {
                b.classList.remove('bg-[#474747]', 'text-white');
                b.classList.add('text-[#47474788]', 'hover:bg-gray-50');
            });
            btnElement.classList.remove('text-[#47474788]', 'hover:bg-gray-50');
            btnElement.classList.add('bg-[#474747]', 'text-white');
            renderGrid(cat);
        }

        // handler ui transaccion
        window.addCarrito = function(id, nombre, precio) {
            const index = carrito.findIndex(i => i.id === id);
            if (index > -1) {
                carrito[index].cantidad++;
            } else {
                carrito.push({ id, nombre, precio, cantidad: 1 });
            }
            renderCarrito();
        }

        window.updateCant = function(id, delta) {
            const index = carrito.findIndex(i => i.id === id);
            if (index > -1) {
                carrito[index].cantidad += delta;
                if (carrito[index].cantidad <= 0) carrito.splice(index, 1);
            }
            renderCarrito();
        }

        window.limpiarCarrito = function() {
            carrito = [];
            renderCarrito();
        }

        function renderCarrito() {
            const cartDiv = document.getElementById('cartItems');
            const totalDiv = document.getElementById('cartTotal');
            
            if (carrito.length === 0) {
                cartDiv.innerHTML = `
                    <div class="h-full flex flex-col items-center justify-center opacity-50">
                        <i data-lucide="shopping-cart" class="w-10 h-10 mb-2 text-[#474747]"></i>
                        <p class="text-xs">Selecciona productos del menú</p>
                    </div>
                `;
                totalDiv.innerText = "$0.00";
                lucide.createIcons();
                return;
            }

            let total = 0;
            cartDiv.innerHTML = carrito.map(item => {
                const subtotal = item.precio * item.cantidad;
                total += subtotal;
                return `
                    <div class="flex items-center justify-between p-3 rounded-xl bg-white border border-[#47474708] shadow-sm">
                        <div class="flex-1 min-w-0 pr-2">
                            <p class="text-xs font-medium text-[#474747] truncate">${item.nombre}</p>
                            <p class="text-[10px] text-[#47474777] mt-0.5">$${item.precio.toFixed(2)} c/u</p>
                        </div>
                        <div class="flex flex-col items-end gap-1.5 shrink-0">
                            <span class="text-sm font-bold text-[#474747]">$${subtotal.toFixed(2)}</span>
                            <div class="flex items-center gap-2 bg-[#FAFAFA] rounded-lg p-0.5 border border-[#47474710]">
                                <button onclick="updateCant(${item.id}, -1)" class="w-6 h-6 flex items-center justify-center rounded bg-white shadow-sm text-[#474747] hover:text-red-500"><i data-lucide="minus" class="w-3 h-3"></i></button>
                                <span class="text-xs font-medium w-3 text-center">${item.cantidad}</span>
                                <button onclick="updateCant(${item.id}, 1)" class="w-6 h-6 flex items-center justify-center rounded bg-white shadow-sm text-[#474747] hover:text-[#4F9150]"><i data-lucide="plus" class="w-3 h-3"></i></button>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
            
            totalDiv.innerText = "$" + total.toFixed(2);
            lucide.createIcons();
        }

        // val op
        window.setMetodo = function(metodo, btn) {
            document.getElementById('metodo_pago').value = metodo;
            document.querySelectorAll('.payment-btn').forEach(b => b.classList.remove('selected'));
            btn.classList.add('selected');
        }

        window.procesarVenta = function() {
            if(carrito.length === 0) {
                mostrarErrorJS("El ticket está vacío. Agrega productos para cobrar.");
                return;
            }
            
            const metodoElegido = document.getElementById('metodo_pago').value;
            if(metodoElegido === "") {
                mostrarErrorJS("Por favor, selecciona un método de pago (Efectivo, Tarjeta o Transferencia).");
                return;
            }

            document.getElementById('carrito_json').value = JSON.stringify(carrito);
            document.getElementById('formVenta').submit();
        }

        // modal
        const drawerHistorial = document.getElementById('historyDrawer');
        const drawerOverlay = document.getElementById('drawerOverlay');

        window.abrirHistorial = function() {
            drawerOverlay.classList.remove('hidden');
            setTimeout(() => { drawerOverlay.style.opacity = "1"; }, 10);
            drawerHistorial.classList.remove('translate-x-full');
        }

        window.cerrarHistorial = function() {
            drawerHistorial.classList.add('translate-x-full');
            drawerOverlay.style.opacity = "0";
            setTimeout(() => { drawerOverlay.classList.add('hidden'); }, 300);
        }

        // init
        renderGrid();
    </script>
</body>
</html>