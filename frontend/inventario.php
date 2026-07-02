<?php
// init de entorno
session_start();
require(__DIR__ . "/conexion.php");

// eval usr
if (!isset($_SESSION['usuario'])) {
    header("Location: /getrest/login.php");
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

// procesador de db
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'eliminar') {
        $id_del = (int)$_POST['eliminar_id'];
        
        // nombre para bitacora
        $n_del = "Insumo";
        $q_n = $conexion->query("SELECT Nombre FROM Ingrediente WHERE Id_Ingrediente = $id_del");
        if($q_n && $r_n = $q_n->fetch_assoc()) $n_del = $r_n['Nombre'];

        if($conexion->query("DELETE FROM Ingrediente WHERE Id_Ingrediente = $id_del AND Id_Restaurante = $id_restaurante")){
            $txt = $conexion->real_escape_string("Usuario $rol_usuario eliminó el insumo: $n_del");
            registrarBitacora($conexion, $id_restaurante, $txt);
        }
        header("Location: /getrest/inventario.php");
        exit();
    } elseif ($accion === 'guardar') {
        $id_edit = (int)($_POST['id_ingrediente'] ?? 0);
        $nombre = $conexion->real_escape_string($_POST['nombre']);
        $unidad = $conexion->real_escape_string($_POST['unidad']);
        $stock = (float)$_POST['stock'];
        $minimo = (float)$_POST['minimo'];
        $costo = (float)$_POST['costo'];
        $caducidad = $conexion->real_escape_string($_POST['caducidad']);

        try {
            // checar columnas
            $cols_query = $conexion->query("SHOW COLUMNS FROM Ingrediente");
            $columnas = [];
            while($c = $cols_query->fetch_assoc()) $columnas[strtolower($c['Field'])] = $c['Field'];

            $campos = [];
            if(isset($columnas['nombre'])) $campos[$columnas['nombre']] = "'$nombre'";
            if(isset($columnas['unidad_medida'])) $campos[$columnas['unidad_medida']] = "'$unidad'";
            elseif(isset($columnas['unidad'])) $campos[$columnas['unidad']] = "'$unidad'";
            if(isset($columnas['stock_actual'])) $campos[$columnas['stock_actual']] = $stock;
            elseif(isset($columnas['stock'])) $campos[$columnas['stock']] = $stock;
            if(isset($columnas['stock_minimo'])) $campos[$columnas['stock_minimo']] = $minimo;
            elseif(isset($columnas['minimo'])) $campos[$columnas['minimo']] = $minimo;
            if(isset($columnas['costo_unitario'])) $campos[$columnas['costo_unitario']] = $costo;
            elseif(isset($columnas['costo'])) $campos[$columnas['costo']] = $costo;
            if(isset($columnas['caducidad'])) $campos[$columnas['caducidad']] = "'$caducidad'";
            elseif(isset($columnas['fecha_caducidad'])) $campos[$columnas['fecha_caducidad']] = "'$caducidad'";

            if ($id_edit > 0) {
                $set_parts = [];
                foreach($campos as $k => $v) $set_parts[] = "$k = $v";
                $sql = "UPDATE Ingrediente SET " . implode(", ", $set_parts) . " WHERE Id_Ingrediente = $id_edit AND Id_Restaurante = $id_restaurante";
                $msg = "Insumo actualizado con éxito.";
            } else {
                $campos['Id_Restaurante'] = $id_restaurante;
                $keys = implode(", ", array_keys($campos));
                $vals = implode(", ", array_values($campos));
                $sql = "INSERT INTO Ingrediente ($keys) VALUES ($vals)";
                $msg = "Insumo registrado con éxito.";
            }
            
            if ($conexion->query($sql)) {
                $exito = $msg;
                // guardar reporte
                $acc = ($id_edit > 0) ? "actualizó" : "registró";
                $txt = $conexion->real_escape_string("Usuario $rol_usuario $acc insumo: $nombre ($stock $unidad)");
                registrarBitacora($conexion, $id_restaurante, $txt);
            } else {
                $error = "Error en db: " . $conexion->error;
            }
        } catch (Exception $e) {
            $error = "Error fatal: " . $e->getMessage();
        }
    }
}
// extraccion gral
$insumos = [];
$total_inversion = 0;
$alertas = 0;

try {
    $res = $conexion->query("SELECT * FROM Ingrediente WHERE Id_Restaurante = $id_restaurante ORDER BY Nombre ASC");
    if ($res) {
        while ($row_raw = $res->fetch_assoc()) {
            $row = array_change_key_case($row_raw, CASE_LOWER);
            
            $item = [
                'id' => $row['id_ingrediente'],
                'nombre' => $row['nombre'] ?? 'Sin nombre',
                'unidad' => $row['unidad_medida'] ?? $row['unidad'] ?? 'pza',
                'stock' => (float)($row['stock_actual'] ?? $row['stock'] ?? 0),
                'minimo' => (float)($row['stock_minimo'] ?? $row['minimo'] ?? 0),
                'costo' => (float)($row['costo_unitario'] ?? $row['costo'] ?? 0),
                'caducidad' => $row['caducidad'] ?? $row['fecha_caducidad'] ?? ''
            ];
            
            $total_inversion += ($item['stock'] * $item['costo']);
            if ($item['stock'] <= $item['minimo']) $alertas++;
            
            $insumos[] = $item;
        }
    }
} catch (Exception $e) {}

$json_insumos = json_encode($insumos);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Getrest - Inventario</title>
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
        
        .grid-inventario { display: grid; grid-template-columns: 2fr 1fr 1fr 1fr 1fr 1fr 80px; align-items: center; }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #47474733; border-radius: 10px; }

        .modern-input {
            background-color: #F9FAFB;
            border: 1px solid #E5E7EB;
            color: var(--charcoal);
            outline: none;
            transition: all 0.25s ease;
        }
        .modern-input:focus {
            background-color: #FFFFFF;
            border-color: var(--tan);
            box-shadow: 0 0 0 4px rgba(161, 142, 94, 0.1);
        }

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
        .delay-200 { animation-delay: 200ms; }
    </style>
</head>
<body>
    
    <form id="formEliminar" method="POST" style="display: none;">
        <input type="hidden" name="accion" value="eliminar">
        <input type="hidden" name="eliminar_id" id="eliminar_id_input">
    </form>

    <div class="h-screen w-full flex overflow-hidden relative">

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
                <div class="flex items-center gap-2 text-sm" style="color: #F1E9C655;">
                    <span>Inicio</span>
                    <i data-lucide="chevron-right" class="w-3 h-3"></i>
                    <span style="color: var(--cream);">Inventario</span>
                </div>
                <div class="flex items-center gap-4">
                    <div class="flex items-center gap-2.5">
                        <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-medium" style="background: var(--tan); color: var(--cream);">
                            <?= htmlspecialchars($iniciales) ?>
                        </div>
                        <span class="text-xs" style="color: #F1E9C688;"><?= htmlspecialchars($rol_usuario) ?></span>
                    </div>
                </div>
            </header>

            <div class="flex-1 overflow-y-auto px-8 pt-7 pb-8">
                
                <div class="flex items-end justify-between mb-8 animate-fade-in">
                    <div>
                        <h1 style="font-family: 'Playfair Display', serif; font-size: 1.8rem; font-weight: 400; color: var(--charcoal); line-height: 1.1;">
                            Inventario de Insumos
                        </h1>
                        <p class="text-sm mt-1.5" style="color: #47474788;">
                            Gestiona el stock y caducidad de tus ingredientes
                        </p>
                    </div>
                    <button onclick="abrirDrawer()" class="flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-medium text-white transition-all shadow-md hover:shadow-lg transform hover:-translate-y-px" style="background: var(--crimson);">
                        <i data-lucide="plus" class="w-4 h-4"></i> Nuevo insumo
                    </button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8 animate-fade-in delay-100">
                    <div class="bg-white p-5 rounded-2xl border border-[#47474712] shadow-sm flex items-center gap-4">
                        <div class="w-12 h-12 rounded-xl flex items-center justify-center shrink-0" style="background: #A18E5E15;">
                            <i data-lucide="boxes" class="w-6 h-6" style="color: var(--tan);"></i>
                        </div>
                        <div>
                            <p class="text-[11px] font-bold uppercase tracking-wider text-[#47474788]">Total Insumos</p>
                            <p class="text-2xl font-serif mt-0.5" style="color: var(--charcoal);"><?= count($insumos) ?></p>
                        </div>
                    </div>
                    
                    <div class="bg-white p-5 rounded-2xl border border-[#47474712] shadow-sm flex items-center gap-4">
                        <div class="w-12 h-12 rounded-xl flex items-center justify-center shrink-0" style="background: <?= $alertas > 0 ? '#99061615' : '#4F915015' ?>;">
                            <i data-lucide="alert-triangle" class="w-6 h-6" style="color: <?= $alertas > 0 ? 'var(--crimson)' : 'var(--green)' ?>;"></i>
                        </div>
                        <div>
                            <p class="text-[11px] font-bold uppercase tracking-wider text-[#47474788]">Alertas de Stock</p>
                            <p class="text-2xl font-serif mt-0.5" style="color: var(--charcoal);"><?= $alertas ?></p>
                        </div>
                    </div>

                    <div class="bg-white p-5 rounded-2xl border border-[#47474712] shadow-sm flex items-center gap-4">
                        <div class="w-12 h-12 rounded-xl flex items-center justify-center shrink-0" style="background: #47474710;">
                            <i data-lucide="wallet" class="w-6 h-6" style="color: var(--charcoal);"></i>
                        </div>
                        <div>
                            <p class="text-[11px] font-bold uppercase tracking-wider text-[#47474788]">Valor del Inventario</p>
                            <p class="text-2xl font-serif mt-0.5" style="color: var(--charcoal);">$<?= number_format($total_inversion, 2) ?></p>
                        </div>
                    </div>
                </div>

                <?php if ($error): ?>
                    <div class="mb-6 p-4 rounded-xl bg-red-50 border border-red-100 text-red-700 text-sm flex items-center gap-3 shadow-sm animate-fade-in delay-200">
                        <i data-lucide="alert-circle" class="w-5 h-5 text-red-500"></i><?= $error ?>
                    </div>
                <?php endif; ?>
                <?php if ($exito): ?>
                    <div class="mb-6 p-4 rounded-xl bg-green-50 border border-green-100 text-green-700 text-sm flex items-center gap-3 shadow-sm animate-fade-in delay-200">
                        <i data-lucide="check-circle" class="w-5 h-5 text-green-500"></i><?= $exito ?>
                    </div>
                <?php endif; ?>

                <div class="relative max-w-sm mb-4 animate-fade-in delay-200">
                    <i data-lucide="search" class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-[#47474755]"></i>
                    <input type="text" id="searchInput" placeholder="Buscar por nombre..." class="modern-input w-full pl-11 pr-4 py-3 rounded-xl text-sm shadow-sm">
                </div>

                <div class="rounded-2xl overflow-hidden bg-white shadow-[0_4px_20px_rgb(0,0,0,0.03)] border border-[#47474712] animate-fade-in delay-200">
                    <div class="grid-inventario text-[11px] font-bold uppercase tracking-wider px-6 py-4" style="color: #47474777; border-bottom: 1px solid #47474710; background: #FAFAFA;">
                        <span>Insumo</span>
                        <span>Stock Actual</span>
                        <span>Stock Mínimo</span>
                        <span>Costo Unit.</span>
                        <span>Caducidad</span>
                        <span>Estado</span>
                        <span class="text-right">Acciones</span>
                    </div>

                    <div id="tableBody" class="flex flex-col">
                        </div>
                </div>

            </div>
        </div>

        <div id="drawerOverlay" class="fixed inset-0 bg-[#47474777] z-40 hidden opacity-0 transition-opacity duration-300" onclick="cerrarDrawer()"></div>

        <aside id="drawerForm" class="fixed right-0 top-0 h-full w-full max-w-md bg-white shadow-2xl z-50 flex flex-col translate-x-full transition-transform duration-300 ease-in-out">
            <form method="POST" class="flex flex-col h-full">
                <input type="hidden" name="accion" value="guardar">
                <input type="hidden" name="id_ingrediente" id="frm_id" value="0">

                <div class="flex items-center justify-between px-8 py-6 shrink-0" style="border-bottom: 1px solid #47474708;">
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-wider text-[#A18E5E] mb-1">Gestión</p>
                        <h2 id="drawerTitle" style="font-family: 'Playfair Display', serif; font-size: 1.4rem; color: var(--charcoal);">
                            Registrar insumo
                        </h2>
                    </div>
                    <button type="button" onclick="cerrarDrawer()" class="p-2 rounded-xl text-[#47474755] hover:bg-gray-50 hover:text-[#474747] transition-colors">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto px-8 py-6 flex flex-col gap-6 bg-[#FAFAFA] bg-opacity-50">
                    
                    <div class="flex flex-col gap-2">
                        <label class="text-[11px] font-bold uppercase tracking-wider text-[#47474788]">Nombre del insumo</label>
                        <input type="text" name="nombre" id="frm_nombre" required placeholder="Ej. Aguacate Hass" class="modern-input w-full px-4 py-3 rounded-xl text-sm">
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="flex flex-col gap-2">
                            <label class="text-[11px] font-bold uppercase tracking-wider text-[#47474788]">Stock Inicial</label>
                            <input type="number" name="stock" id="frm_stock" required step="0.01" min="0" placeholder="0" class="modern-input w-full px-4 py-3 rounded-xl text-sm">
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-[11px] font-bold uppercase tracking-wider text-[#47474788]">Unidad</label>
                            <div class="relative">
                                <select name="unidad" id="frm_unidad" class="modern-input w-full px-4 py-3 rounded-xl text-sm appearance-none pr-10 cursor-pointer">
                                    <option value="kg">Kilogramo (kg)</option>
                                    <option value="g">Gramo (g)</option>
                                    <option value="L">Litro (L)</option>
                                    <option value="ml">Mililitro (ml)</option>
                                    <option value="pza">Pieza (pza)</option>
                                </select>
                                <i data-lucide="chevron-down" class="absolute right-4 top-1/2 -translate-y-1/2 w-4 h-4 text-[#47474755] pointer-events-none"></i>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="flex flex-col gap-2">
                            <label class="text-[11px] font-bold uppercase tracking-wider text-[#47474788]">Stock Mínimo</label>
                            <input type="number" name="minimo" id="frm_minimo" required step="0.01" min="0" placeholder="0" class="modern-input w-full px-4 py-3 rounded-xl text-sm">
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-[11px] font-bold uppercase tracking-wider text-[#47474788]">Costo Unitario ($)</label>
                            <input type="number" name="costo" id="frm_costo" required step="0.01" min="0" placeholder="0.00" class="modern-input w-full px-4 py-3 rounded-xl text-sm">
                        </div>
                    </div>

                    <div class="flex flex-col gap-2">
                        <label class="text-[11px] font-bold uppercase tracking-wider text-[#47474788]">Fecha de Caducidad</label>
                        <div class="relative">
                            <i data-lucide="calendar" class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-[#47474755]"></i>
                            <input type="date" name="caducidad" id="frm_caducidad" required class="modern-input w-full pl-11 pr-4 py-3 rounded-xl text-sm">
                        </div>
                    </div>

                </div>

                <div class="p-6 bg-white border-t border-[#47474708] flex justify-end gap-3 shrink-0">
                    <button type="button" onclick="cerrarDrawer()" class="px-6 py-3 rounded-xl text-sm font-medium border border-[#47474722] text-[#474747] hover:bg-gray-50 transition-all shadow-sm">
                        Cancelar
                    </button>
                    <button type="submit" class="px-6 py-3 rounded-xl text-sm font-medium text-white transition-all shadow-md hover:shadow-lg transform hover:-translate-y-px" style="background: var(--crimson);">
                        Guardar Insumo
                    </button>
                </div>
            </form>
        </aside>

    </div>

    <script>
        lucide.createIcons();

        // toggle view 
        const sidebar = document.getElementById('sidebar');
        const sidebarTexts = document.querySelectorAll('.sidebar-text');
        function toggleSidebar() {
            if (sidebar.style.width === '220px') {
                sidebar.style.width = '64px';
                sidebarTexts.forEach(el => el.classList.add('hidden'));
            } else {
                sidebar.style.width = '220px';
                setTimeout(() => {
                    sidebarTexts.forEach(el => el.classList.remove('hidden'));
                }, 150);
            }
        }

        // render tabla de inv
        const insumosData = <?= $json_insumos ?>;
        const tableBody = document.getElementById('tableBody');
        const searchInput = document.getElementById('searchInput');

        function render() {
            const query = searchInput.value.toLowerCase();
            const filtrados = insumosData.filter(i => i.nombre.toLowerCase().includes(query));

            if (filtrados.length === 0) {
                tableBody.innerHTML = `
                    <div class="flex flex-col items-center justify-center py-16 gap-3">
                        <div class="w-12 h-12 rounded-full flex items-center justify-center bg-[#FAFAFA]">
                            <i data-lucide="package-search" class="w-6 h-6 text-[#47474730]"></i>
                        </div>
                        <p class="text-sm text-[#47474755]">No hay insumos que coincidan.</p>
                    </div>
                `;
            } else {
                tableBody.innerHTML = filtrados.map((item, i) => {
                    const isAlerta = item.stock <= item.minimo;
                    const badgeClass = isAlerta 
                        ? 'bg-red-50 text-red-600 border-red-100' 
                        : 'bg-green-50 text-green-600 border-green-100';
                    const badgeText = isAlerta ? 'Bajo Stock' : 'Adecuado';

                    return `
                    <div class="grid-inventario px-6 py-4 transition-colors duration-100 hover:bg-[#FAFAFA]" 
                         style="border-bottom: ${i < filtrados.length - 1 ? '1px solid #47474707' : 'none'};">
                        
                        <div class="flex items-center gap-3 min-w-0 pr-4">
                            <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0" style="background: #A18E5E15;">
                                <i data-lucide="package" class="w-4 h-4" style="color: var(--tan);"></i>
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-medium truncate" style="color: var(--charcoal);">${item.nombre}</p>
                                <p class="text-[10px] uppercase tracking-wider text-[#47474777] mt-0.5">${item.unidad}</p>
                            </div>
                        </div>

                        <span class="text-sm ${isAlerta ? 'font-bold text-red-600' : 'text-[#474747]'}">${item.stock}</span>
                        <span class="text-sm text-[#47474788]">${item.minimo}</span>
                        <span class="text-sm font-medium text-[#474747]">$${item.costo.toFixed(2)}</span>
                        
                        <div class="flex items-center gap-1.5 text-sm text-[#47474788]">
                            <i data-lucide="calendar" class="w-3.5 h-3.5"></i>
                            ${item.caducidad || 'N/A'}
                        </div>

                        <div>
                            <span class="text-[10px] font-bold uppercase tracking-wider px-2.5 py-1 rounded-md border ${badgeClass}">
                                ${badgeText}
                            </span>
                        </div>

                        <div class="flex justify-end gap-1">
                            <button onclick='abrirDrawer(${JSON.stringify(item).replace(/'/g, "&apos;")})' class="p-2 rounded-lg text-[#47474777] hover:bg-[#A18E5E15] hover:text-[#A18E5E] transition-colors" title="Editar">
                                <i data-lucide="edit-2" class="w-4 h-4"></i>
                            </button>
                            <button onclick="borrarInsumo(${item.id})" class="p-2 rounded-lg text-[#47474777] hover:bg-red-50 hover:text-red-600 transition-colors" title="Eliminar">
                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                            </button>
                        </div>
                    </div>
                `}).join('');
            }
            lucide.createIcons();
        }

        searchInput.addEventListener('input', render);
        render();

        // panel form config
        const drawerForm = document.getElementById('drawerForm');
        const drawerOverlay = document.getElementById('drawerOverlay');
        const drawerTitle = document.getElementById('drawerTitle');

        function abrirDrawer(data = null) {
            if (data) {
                drawerTitle.innerText = "Editar insumo";
                document.getElementById('frm_id').value = data.id;
                document.getElementById('frm_nombre').value = data.nombre;
                document.getElementById('frm_unidad').value = data.unidad;
                document.getElementById('frm_stock').value = data.stock;
                document.getElementById('frm_minimo').value = data.minimo;
                document.getElementById('frm_costo').value = data.costo;
                document.getElementById('frm_caducidad').value = data.caducidad;
            } else {
                drawerTitle.innerText = "Registrar insumo";
                document.getElementById('frm_id').value = "0";
                document.getElementById('frm_nombre').value = "";
                document.getElementById('frm_unidad').value = "kg";
                document.getElementById('frm_stock').value = "";
                document.getElementById('frm_minimo').value = "";
                document.getElementById('frm_costo').value = "";
                document.getElementById('frm_caducidad').value = "";
            }

            drawerOverlay.classList.remove('hidden');
            setTimeout(() => { drawerOverlay.style.opacity = "1"; }, 10);
            drawerForm.classList.remove('translate-x-full');
        }

        function cerrarDrawer() {
            drawerForm.classList.add('translate-x-full');
            drawerOverlay.style.opacity = "0";
            setTimeout(() => { drawerOverlay.classList.add('hidden'); }, 300);
        }

        function borrarInsumo(id) {
            if(confirm("¿Estás seguro de que deseas eliminar este insumo del inventario?")) {
                document.getElementById('eliminar_id_input').value = id;
                document.getElementById('formEliminar').submit();
            }
        }
    </script>
</body>
</html>