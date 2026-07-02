<?php
// conf inicial
session_start();
require(__DIR__ . "/conexion.php");

// eval usr
if (!isset($_SESSION['usuario'])) {
    header("Location: /getrest/login.php");
    exit();
}

// eval rol (Gerente y Chef)
$rol_actual = $_SESSION['rol'];
if ($rol_actual !== 'gerente' && $rol_actual !== 'chef') {
    header("Location: /getrest/inventario.php");
    exit();
}

$rol_usuario = ucfirst($_SESSION['rol'] ?? 'Usuario');
$iniciales = strtoupper(substr($rol_usuario, 0, 2));
$id_restaurante = $_SESSION['restaurante'];

$error = "";
$exito = "";

// log req
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

// ejecucion acciones db
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'eliminar') {
        $id_del = (int)$_POST['eliminar_id'];
        
        $n_del = "Receta";
        $q_n = $conexion->query("SELECT Nombre FROM Receta WHERE Id_Receta = $id_del");
        if($q_n && $r_n = $q_n->fetch_assoc()) $n_del = $r_n['Nombre'];

        if($conexion->query("DELETE FROM Receta WHERE Id_Receta = $id_del AND Id_Restaurante = $id_restaurante")){
            $txt = $conexion->real_escape_string("Usuario $rol_usuario eliminó la receta: $n_del");
            registrarBitacora($conexion, $id_restaurante, $txt);
        }
        header("Location: /getrest/recetas.php");
        exit();
    } 
    elseif ($accion === 'guardar') {
        $id_edit = (int)($_POST['id_receta'] ?? 0);
        $nombre = $conexion->real_escape_string($_POST['nombre']);
        $cat = $conexion->real_escape_string($_POST['categoria']);
        $tiempo = (int)$_POST['tiempo'];
        $porc = (int)$_POST['porciones'];
        $cal = (int)$_POST['calorias'];
        $costo = (float)$_POST['costo'];
        $precio = (float)$_POST['precio'];
        $preparacion = $conexion->real_escape_string($_POST['preparacion'] ?? '');
        $activa = isset($_POST['activa']) && $_POST['activa'] === 'on' ? 1 : 0;

        try {
            // escaneo tablas
            $cols_query = $conexion->query("SHOW COLUMNS FROM Receta");
            $columnas = [];
            while($c = $cols_query->fetch_assoc()) $columnas[strtolower($c['Field'])] = $c['Field'];

            $campos = [];
            if(isset($columnas['nombre'])) $campos[$columnas['nombre']] = "'$nombre'";
            if(isset($columnas['categoria'])) $campos[$columnas['categoria']] = "'$cat'";
            if(isset($columnas['preparacion'])) $campos[$columnas['preparacion']] = "'$preparacion'";
            if(isset($columnas['tiempo_preparacion'])) $campos[$columnas['tiempo_preparacion']] = $tiempo;
            elseif(isset($columnas['tiempo'])) $campos[$columnas['tiempo']] = $tiempo;
            if(isset($columnas['porciones'])) $campos[$columnas['porciones']] = $porc;
            if(isset($columnas['calorias'])) $campos[$columnas['calorias']] = $cal;
            if(isset($columnas['costo_total'])) $campos[$columnas['costo_total']] = $costo;
            elseif(isset($columnas['costo'])) $campos[$columnas['costo']] = $costo;
            if(isset($columnas['precio_venta'])) $campos[$columnas['precio_venta']] = $precio;
            elseif(isset($columnas['precio'])) $campos[$columnas['precio']] = $precio;
            if(isset($columnas['activa'])) $campos[$columnas['activa']] = $activa;
            elseif(isset($columnas['estado'])) $campos[$columnas['estado']] = $activa ? "'Activo'" : "'Inactivo'";

            if ($id_edit > 0) {
                $set_parts = [];
                foreach($campos as $k => $v) $set_parts[] = "$k = $v";
                $sql = "UPDATE Receta SET " . implode(", ", $set_parts) . " WHERE Id_Receta=$id_edit AND Id_Restaurante=$id_restaurante";
                $msg_success = "Receta actualizada con éxito.";
                $id_receta_final = $id_edit;
            } else {
                if(isset($columnas['id_restaurante'])) $campos[$columnas['id_restaurante']] = $id_restaurante;
                $keys = implode(", ", array_keys($campos));
                $vals = implode(", ", array_values($campos));
                $sql = "INSERT INTO Receta ($keys) VALUES ($vals)";
                $msg_success = "Receta creada con éxito.";
            }
            
            if ($conexion->query($sql)) {
                if ($id_edit === 0) $id_receta_final = $conexion->insert_id;

                // inyeccion rel
                $conexion->query("DELETE FROM Receta_Ingrediente WHERE Id_Receta = $id_receta_final");
                if (isset($_POST['ingredientes']) && is_array($_POST['ingredientes'])) {
                    $cantidades = $_POST['cantidades'];
                    foreach ($_POST['ingredientes'] as $index => $id_ingr) {
                        $id_ingr = (int)$id_ingr;
                        $cant = $conexion->real_escape_string($cantidades[$index]);
                        if ($id_ingr > 0 && !empty($cant)) {
                            $conexion->query("INSERT INTO Receta_Ingrediente (Id_Receta, Id_Ingrediente, Cantidad) VALUES ($id_receta_final, $id_ingr, '$cant')");
                        }
                    }
                }
                $exito = $msg_success;
                
                $acc = ($id_edit > 0) ? "actualizó" : "creó";
                $txt = $conexion->real_escape_string("Usuario $rol_usuario $acc receta: $nombre");
                registrarBitacora($conexion, $id_restaurante, $txt);
            } else {
                $error = "Error DB: " . $conexion->error;
            }
        } catch (Exception $e) {
            $error = "Fallo de ejecución: " . $e->getMessage();
        }
    }
}

// fetch list
$recetas_db = [];
try {
    $q = $conexion->query("SELECT * FROM Receta WHERE Id_Restaurante = $id_restaurante");
    if ($q) while ($r = $q->fetch_assoc()) $recetas_db[] = $r;
} catch (Exception $e) {}

// cat modificado para extraer el costo unitario
$cat_insumos = [];
try {
    $q = $conexion->query("SELECT * FROM Ingrediente WHERE Id_Restaurante = $id_restaurante ORDER BY Nombre ASC");
    if ($q) {
        while ($r = $q->fetch_assoc()) {
            $row = array_change_key_case($r, CASE_LOWER);
            $cat_insumos[] = [
                'Id_Ingrediente' => $row['id_ingrediente'],
                'Nombre' => $row['nombre'],
                'Costo' => (float)($row['costo_unitario'] ?? $row['costo'] ?? 0)
            ];
        }
    }
} catch (Exception $e) {}

// maps struct
$ing_view_map = [];
$ing_form_map = [];
try {
    $q = $conexion->query("
        SELECT ri.Id_Receta, ri.Id_Ingrediente, ri.Cantidad, i.Nombre 
        FROM Receta_Ingrediente ri 
        JOIN Ingrediente i ON ri.Id_Ingrediente = i.Id_Ingrediente 
        WHERE i.Id_Restaurante = $id_restaurante
    ");
    if ($q) {
        while ($r = $q->fetch_assoc()) {
            $id_r = $r['Id_Receta'];
            $ing_view_map[$id_r][] = $r['Cantidad'] . " " . $r['Nombre'];
            $ing_form_map[$id_r][] = ['id' => $r['Id_Ingrediente'], 'cant' => $r['Cantidad']];
        }
    }
} catch (Exception $e) {}

$json_recetas = json_encode($recetas_db);
$json_view_ing = json_encode($ing_view_map);
$json_form_ing = json_encode($ing_form_map);
$json_catalogo = json_encode($cat_insumos);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Getrest - Recetas</title>
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
        .grid-recetas { display: grid; grid-template-columns: 2fr 1.5fr 80px 90px 90px 90px 80px; align-items: center; }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #47474733; border-radius: 10px; }

        .modern-input { background-color: #F9FAFB; border: 1px solid #E5E7EB; color: var(--charcoal); outline: none; transition: all 0.25s ease; }
        .modern-input:focus { background-color: #FFFFFF; border-color: var(--tan); box-shadow: 0 0 0 4px rgba(161, 142, 94, 0.1); }
        .toggle-checkbox:checked { right: 0; border-color: var(--green); }
        .toggle-checkbox:checked + .toggle-label { background-color: var(--green); }
        
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
                    <span style="color: var(--cream);">Recetas</span>
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

            <div class="px-8 pt-7 pb-5 shrink-0 animate-fade-in">
                <div class="flex items-end justify-between mb-6">
                    <div>
                        <h1 style="font-family: 'Playfair Display', serif; font-size: 1.6rem; font-weight: 400; color: var(--charcoal);">
                            Recetas
                        </h1>
                        <p id="contador-recetas" class="text-sm mt-1" style="color: #47474777;">Cargando...</p>
                    </div>
                    <button onclick="abrirFormulario()" class="flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-[#7a0512] transition-colors shadow-sm" style="background: var(--crimson); color: var(--cream);">
                        <i data-lucide="plus" class="w-4 h-4"></i> Nueva receta
                    </button>
                </div>

                <?php if ($error): ?>
                    <div class="mb-4 p-4 rounded-xl bg-red-50 border border-red-100 text-red-700 text-sm flex items-center gap-3 shadow-sm">
                        <i data-lucide="alert-circle" class="w-5 h-5 text-red-500"></i><?= $error ?>
                    </div>
                <?php endif; ?>
                <?php if ($exito): ?>
                    <div class="mb-4 p-4 rounded-xl bg-green-50 border border-green-100 text-green-700 text-sm flex items-center gap-3 shadow-sm">
                        <i data-lucide="check-circle" class="w-5 h-5 text-green-500"></i><?= $exito ?>
                    </div>
                <?php endif; ?>

                <div class="flex items-center gap-3 flex-wrap">
                    <div class="relative flex-1 min-w-48 max-w-xs">
                        <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4" style="color: #47474755;"></i>
                        <input type="text" id="searchInput" placeholder="Buscar receta…" class="w-full pl-9 pr-4 py-2.5 rounded-lg text-sm outline-none" style="background: #ffffff; border: 1px solid #47474718; color: var(--charcoal);">
                    </div>
                    <div id="categoryPills" class="flex items-center gap-1.5 flex-wrap"></div>
                    <label class="flex items-center gap-2 cursor-pointer ml-auto">
                        <div id="toggleBg" class="w-9 h-5 rounded-full relative transition-colors duration-200" style="background: #47474730;">
                            <div id="toggleKnob" class="absolute top-0.5 w-4 h-4 rounded-full transition-all duration-200" style="background: #ffffff; left: 2px;"></div>
                        </div>
                        <span class="text-xs select-none" style="color: #47474777;">Solo activas</span>
                    </label>
                </div>
            </div>

            <div class="flex-1 overflow-y-auto px-8 pb-8 animate-fade-in delay-100">
                <div class="rounded-xl overflow-hidden bg-white shadow-[0_4px_20px_rgb(0,0,0,0.03)] border border-[#47474712]">
                    <div class="grid-recetas text-xs uppercase tracking-wide font-medium px-5 py-3.5" style="color: #47474766; border-bottom: 1px solid #47474710; background: #F1E9C655;">
                        <span>Nombre</span>
                        <span>Categoría</span>
                        <span class="text-center">Tiempo</span>
                        <span class="text-center">Porciones</span>
                        <span class="text-right">Costo</span>
                        <span class="text-right">Precio</span>
                        <span class="text-right">Estado</span>
                    </div>
                    <div id="tableBody" class="flex flex-col"></div>
                </div>
            </div>
        </div>

        <div id="drawerOverlay" class="fixed inset-0 bg-[#47474777] z-40 hidden opacity-0 transition-opacity duration-300" onclick="cerrarTodosPaneles()"></div>

        <aside id="detailDrawer" class="fixed right-0 top-0 h-full w-full max-w-md bg-white shadow-2xl z-50 flex flex-col translate-x-full transition-transform duration-300 ease-in-out">
            <div class="flex items-center justify-between px-6 py-5 shrink-0" style="border-bottom: 1px solid #47474710;">
                <div>
                    <p id="drawerCat" class="text-xs uppercase tracking-wide mb-1" style="color: #47474755;"></p>
                    <h2 id="drawerNombre" style="font-family: 'Playfair Display', serif; font-size: 1.25rem; font-weight: 400; color: var(--charcoal);"></h2>
                </div>
                <button onclick="cerrarTodosPaneles()" class="p-1.5 rounded text-[#47474755] hover:text-[#474747] transition-colors">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <div class="grid grid-cols-3 gap-px shrink-0 bg-[#47474710]">
                <div class="flex flex-col items-center gap-1 py-4 bg-white">
                    <span style="color: var(--tan);"><i data-lucide="clock" class="w-4 h-4"></i></span>
                    <span id="drawerTiempo" class="text-base font-medium" style="color: var(--charcoal);"></span>
                    <span class="text-xs" style="color: #47474755;">Tiempo</span>
                </div>
                <div class="flex flex-col items-center gap-1 py-4 bg-white">
                    <span style="color: var(--tan);"><i data-lucide="users" class="w-4 h-4"></i></span>
                    <span id="drawerPorciones" class="text-base font-medium" style="color: var(--charcoal);"></span>
                    <span class="text-xs" style="color: #47474755;">Porciones</span>
                </div>
                <div class="flex flex-col items-center gap-1 py-4 bg-white">
                    <span style="color: var(--tan);"><i data-lucide="flame" class="w-4 h-4"></i></span>
                    <span id="drawerCalorias" class="text-base font-medium" style="color: var(--charcoal);"></span>
                    <span class="text-xs" style="color: #47474755;">Calorías</span>
                </div>
            </div>

            <div class="px-6 py-5 shrink-0" style="border-bottom: 1px solid #47474708;">
                <p class="text-xs uppercase tracking-wide font-medium mb-4" style="color: #47474755;">Finanzas</p>
                <div class="grid grid-cols-3 gap-4">
                    <div class="rounded-lg p-3 text-center" style="background: var(--cream);">
                        <p id="drawerCosto" class="text-lg font-medium" style="color: var(--charcoal); font-family: 'Playfair Display', serif;"></p>
                        <p class="text-xs mt-0.5" style="color: #47474766;">Costo</p>
                    </div>
                    <div class="rounded-lg p-3 text-center" style="background: var(--cream);">
                        <p id="drawerPrecio" class="text-lg font-medium" style="color: var(--charcoal); font-family: 'Playfair Display', serif;"></p>
                        <p class="text-xs mt-0.5" style="color: #47474766;">Precio</p>
                    </div>
                    <div class="rounded-lg p-3 text-center" style="background: var(--cream);">
                        <p id="drawerMargen" class="text-lg font-medium" style="font-family: 'Playfair Display', serif;"></p>
                        <p class="text-xs mt-0.5" style="color: #47474766;">Margen</p>
                    </div>
                </div>
            </div>

            <div class="px-6 py-5 flex-1 overflow-y-auto flex flex-col gap-6">
                <div>
                    <p class="text-xs uppercase tracking-wide font-medium mb-3" style="color: #47474755;">Ingredientes</p>
                    <ul id="drawerIngredientes" class="flex flex-col gap-2"></ul>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide font-medium mb-3" style="color: #47474755;">Preparación</p>
                    <p id="drawerPreparacion" class="text-sm leading-relaxed whitespace-pre-wrap" style="color: var(--charcoal);"></p>
                </div>
            </div>

            <div class="px-6 py-5 flex gap-3 shrink-0" style="border-top: 1px solid #47474708;">
                <button onclick="abrirFormularioDesdeVista()" class="flex-1 flex items-center justify-center gap-2 py-2.5 rounded-lg text-sm font-medium text-white transition-colors hover:bg-[#7a0512]" style="background: var(--crimson);">
                    <i data-lucide="edit-2" class="w-4 h-4"></i> Editar receta
                </button>
                <button onclick="borrarReceta()" class="flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg text-sm border transition-colors border-[#47474715] text-[#47474777] hover:bg-red-50 hover:text-red-600">
                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                </button>
            </div>
        </aside>

        <aside id="formDrawer" class="fixed right-0 top-0 h-full w-full max-w-xl bg-[#FAFAFA] shadow-2xl z-50 flex flex-col translate-x-full transition-transform duration-300 ease-in-out">
            <form method="POST" class="flex flex-col h-full bg-white shadow-xl">
                <input type="hidden" name="accion" value="guardar">
                <input type="hidden" name="id_receta" id="frm_id" value="0">

                <div class="flex items-center justify-between px-8 py-6 shrink-0" style="border-bottom: 1px solid #47474708;">
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-wider text-[#A18E5E] mb-1">Gestión de Menú</p>
                        <h2 id="frmTitle" style="font-family: 'Playfair Display', serif; font-size: 1.4rem; color: var(--charcoal);">
                            Nueva Receta
                        </h2>
                    </div>
                    <button type="button" onclick="cerrarTodosPaneles()" class="p-2 rounded-xl text-[#47474755] hover:bg-gray-50 transition-colors">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto p-8 py-6 flex flex-col gap-8 bg-[#FAFAFA] bg-opacity-50">
                    
                    <div>
                        <h3 class="text-[11px] font-bold uppercase tracking-wider mb-4 flex items-center gap-2" style="color: var(--charcoal);">
                            <i data-lucide="utensils-crossed" class="w-4 h-4" style="color: var(--tan);"></i> Información Base
                        </h3>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="flex flex-col gap-2">
                                <label class="text-[10px] uppercase text-[#47474788]">Nombre</label>
                                <input type="text" name="nombre" id="frm_nombre" required placeholder="Ej. Pasta Carbonara" class="modern-input w-full px-4 py-2.5 rounded-xl text-sm">
                            </div>
                            <div class="flex flex-col gap-2">
                                <label class="text-[10px] uppercase text-[#47474788]">Categoría</label>
                                <div class="relative">
                                    <select name="categoria" id="frm_cat" class="modern-input w-full px-4 py-2.5 rounded-xl text-sm appearance-none cursor-pointer">
                                        <option value="Entradas">Entradas</option>
                                        <option value="Principales">Principales</option>
                                        <option value="Postres">Postres</option>
                                        <option value="Bebidas">Bebidas</option>
                                    </select>
                                    <i data-lucide="chevron-down" class="absolute right-4 top-1/2 -translate-y-1/2 w-4 h-4 text-[#47474755] pointer-events-none"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h3 class="text-[11px] font-bold uppercase tracking-wider mb-4 flex items-center gap-2" style="color: var(--charcoal);">
                            <i data-lucide="clipboard-list" class="w-4 h-4" style="color: var(--tan);"></i> Especificaciones
                        </h3>
                        <div class="grid grid-cols-3 gap-4">
                            <div class="relative">
                                <i data-lucide="clock" class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-[#47474744]"></i>
                                <input type="number" name="tiempo" id="frm_tiempo" required min="1" placeholder="Min" class="modern-input w-full pl-10 pr-4 py-2.5 rounded-xl text-sm" title="Tiempo (min)">
                            </div>
                            <div class="relative">
                                <i data-lucide="users" class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-[#47474744]"></i>
                                <input type="number" name="porciones" id="frm_porc" required min="1" placeholder="Porciones" class="modern-input w-full pl-10 pr-4 py-2.5 rounded-xl text-sm" title="Porciones">
                            </div>
                            <div class="relative">
                                <i data-lucide="flame" class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-[#47474744]"></i>
                                <input type="number" name="calorias" id="frm_cal" required min="0" placeholder="Kcal" class="modern-input w-full pl-10 pr-4 py-2.5 rounded-xl text-sm" title="Calorías">
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="flex flex-col">
                            <h3 class="text-[11px] font-bold uppercase tracking-wider mb-3 flex items-center gap-2" style="color: var(--charcoal);">
                                <i data-lucide="package" class="w-4 h-4" style="color: var(--tan);"></i> Insumos
                            </h3>
                            <div id="ingredientes-container" class="flex flex-col gap-2"></div>
                            <button type="button" onclick="addIngredientRow()" class="mt-3 flex items-center justify-center gap-2 w-full py-2.5 rounded-xl border border-dashed border-[#A18E5E] text-[#A18E5E] hover:bg-[#A18E5E10] transition-colors text-xs font-medium">
                                <i data-lucide="plus" class="w-3.5 h-3.5"></i> Añadir fila
                            </button>
                        </div>

                        <div class="flex flex-col">
                            <h3 class="text-[11px] font-bold uppercase tracking-wider mb-3 flex items-center gap-2" style="color: var(--charcoal);">
                                <i data-lucide="chef-hat" class="w-4 h-4" style="color: var(--tan);"></i> Pasos
                            </h3>
                            <textarea name="preparacion" id="frm_prep" rows="6" placeholder="Detalla la receta..." class="modern-input w-full px-4 py-3 rounded-xl text-sm resize-none h-full"></textarea>
                        </div>
                    </div>

                    <div>
                        <h3 class="text-[11px] font-bold uppercase tracking-wider mb-4 flex items-center gap-2" style="color: var(--charcoal);">
                            <i data-lucide="circle-dollar-sign" class="w-4 h-4" style="color: var(--tan);"></i> Finanzas
                        </h3>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-4 items-center">
                            <div class="flex flex-col gap-2">
                                <label class="text-[10px] uppercase text-[#47474788]">Costo Automático ($)</label>
                                <input type="number" name="costo" id="frm_costo" required step="0.01" min="0" class="w-full px-4 py-2.5 rounded-xl text-sm border border-[#E5E7EB] bg-gray-100 text-gray-500 cursor-not-allowed outline-none" readonly title="Se calcula sumando los insumos">
                            </div>
                            <div class="flex flex-col gap-2">
                                <label class="text-[10px] uppercase text-[#47474788]">Precio ($)</label>
                                <input type="number" name="precio" id="frm_precio" required step="0.01" min="0" class="modern-input w-full px-4 py-2.5 rounded-xl text-sm font-medium">
                            </div>
                            <div class="flex items-center justify-between p-3 rounded-xl border border-[#47474712] bg-white h-[62px] mt-6">
                                <p class="text-xs font-medium text-[#474747]">Activa</p>
                                <div class="relative inline-block w-10 align-middle select-none">
                                    <input type="checkbox" name="activa" id="frm_activa" class="toggle-checkbox absolute block w-5 h-5 rounded-full bg-white border-[3px] border-gray-300 appearance-none cursor-pointer transition-transform duration-200 z-10"/>
                                    <label for="frm_activa" class="toggle-label block overflow-hidden h-5 rounded-full bg-gray-300 cursor-pointer transition-colors duration-200"></label>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <div class="p-6 bg-white border-t border-[#47474708] flex justify-end gap-3 shrink-0">
                    <button type="button" onclick="cerrarTodosPaneles()" class="px-6 py-3 rounded-xl text-sm font-medium border border-[#47474722] text-[#474747] hover:bg-gray-50 transition-all shadow-sm">
                        Cancelar
                    </button>
                    <button type="submit" class="px-6 py-3 rounded-xl text-sm font-medium text-white transition-all shadow-md hover:shadow-lg transform hover:-translate-y-px" style="background: var(--crimson);">
                        Guardar Receta
                    </button>
                </div>
            </form>
        </aside>

    </div>

    <script>
        lucide.createIcons();

        // panel config
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

        // carga struct
        const recetasData = <?= $json_recetas ?>;
        const viewIngrMap = <?= $json_view_ing ?>;
        const formIngrMap = <?= $json_form_ing ?>;
        const catalogo = <?= $json_catalogo ?>;
        
        let busqueda = "";
        let categoriaActual = "Todos";
        let soloActivas = false;
        let recetaSelect = null;

        const categorias = ["Todos", "Entradas", "Principales", "Postres", "Bebidas"];
        const tableBody = document.getElementById('tableBody');
        const searchInput = document.getElementById('searchInput');
        const categoryPills = document.getElementById('categoryPills');
        const toggleBg = document.getElementById('toggleBg');
        const toggleKnob = document.getElementById('toggleKnob');
        const contadorText = document.getElementById('contador-recetas');

        // formato lista
        const recetasFormateadas = recetasData.map(raw => {
            const r = Object.keys(raw).reduce((acc, key) => { acc[key.toLowerCase()] = raw[key]; return acc; }, {});
            const esActiva = (r.estado || r.activa || 'activo').toString().toLowerCase() === '1' || (r.estado || r.activa || 'activo').toString().toLowerCase() === 'activo';
            
            return {
                id: r.id_receta,
                nombre: r.nombre || 'Sin nombre',
                categoria: r.categoria || 'Principales',
                tiempo: r.tiempo_preparacion || r.tiempo || 30,
                porciones: r.porciones || 2,
                calorias: r.calorias || 0,
                costo: parseFloat(r.costo_total || r.costo || 0),
                precio: parseFloat(r.precio_venta || r.precio || 0),
                preparacion: r.preparacion || '',
                activa: esActiva
            };
        });

        // visual pipeline
        function render() {
            const filtradas = recetasFormateadas.filter(r => {
                return (categoriaActual === "Todos" || r.categoria === categoriaActual) &&
                       r.nombre.toLowerCase().includes(busqueda.toLowerCase()) &&
                       (!soloActivas || r.activa);
            });

            contadorText.innerText = `${recetasFormateadas.length} recetas registradas · ${recetasFormateadas.filter(r => r.activa).length} activas`;

            categoryPills.innerHTML = categorias.map(cat => `
                <button onclick="setCategoria('${cat}')" class="px-3 py-1.5 rounded-full text-xs font-medium transition-all duration-150"
                    style="background: ${categoriaActual === cat ? 'var(--charcoal)' : 'var(--white)'}; color: ${categoriaActual === cat ? 'var(--cream)' : '#47474788'}; border: 1px solid #47474715;">
                    ${cat}
                </button>
            `).join('');

            toggleBg.style.background = soloActivas ? 'var(--green)' : '#47474730';
            toggleKnob.style.left = soloActivas ? 'calc(100% - 18px)' : '2px';

            tableBody.innerHTML = filtradas.length === 0 ? `
                <div class="flex flex-col items-center justify-center py-20 gap-3">
                    <i data-lucide="book-open" class="w-8 h-8" style="color: #47474730;"></i>
                    <p class="text-sm" style="color: #47474755;">No se encontraron recetas</p>
                </div>
            ` : filtradas.map((r, i) => `
                <div onclick="verDetalle(${r.id})" class="grid-recetas px-5 py-4 cursor-pointer transition-colors duration-100 hover:bg-[#FAFAFA]" 
                     style="border-bottom: ${i < filtradas.length - 1 ? '1px solid #47474707' : 'none'};">
                    <div class="flex items-center gap-3 min-w-0 pr-4">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0" style="background: #A18E5E18;">
                            <i data-lucide="flame" class="w-4 h-4" style="color: var(--tan);"></i>
                        </div>
                        <p class="text-sm font-medium truncate" style="color: var(--charcoal);">${r.nombre}</p>
                    </div>
                    <span class="text-xs" style="color: #47474777;">${r.categoria}</span>
                    <div class="flex items-center justify-center gap-1">
                        <i data-lucide="clock" class="w-3 h-3" style="color: #47474755;"></i><span class="text-xs" style="color: #47474788;">${r.tiempo}m</span>
                    </div>
                    <div class="flex items-center justify-center gap-1">
                        <i data-lucide="users" class="w-3 h-3" style="color: #47474755;"></i><span class="text-xs" style="color: #47474788;">${r.porciones}</span>
                    </div>
                    <span class="text-sm text-right" style="color: #47474788;">$${r.costo.toFixed(2)}</span>
                    <span class="text-sm font-medium text-right" style="color: var(--charcoal);">$${r.precio.toFixed(2)}</span>
                    <div class="flex justify-end">
                        <span class="text-[10px] uppercase font-bold px-2 py-0.5 rounded border" style="${r.activa ? 'background:#4F915010; color:var(--green); border-color:var(--green);' : 'background:#47474710; color:#47474777; border-color:#47474730;'}">
                            ${r.activa ? "Activa" : "Inactiva"}
                        </span>
                    </div>
                </div>
            `).join('');
            lucide.createIcons();
        }

        const detailDrawer = document.getElementById('detailDrawer');
        const formDrawer = document.getElementById('formDrawer');
        const drawerOverlay = document.getElementById('drawerOverlay');

        window.cerrarTodosPaneles = function() {
            detailDrawer.classList.add('translate-x-full');
            formDrawer.classList.add('translate-x-full');
            drawerOverlay.style.opacity = "0";
            setTimeout(() => { drawerOverlay.classList.add('hidden'); }, 300);
            recetaSelect = null;
        }

        window.verDetalle = function(id) {
            const r = recetasFormateadas.find(i => i.id == id);
            if (!r) return;
            recetaSelect = id;

            document.getElementById('drawerCat').innerText = r.categoria;
            document.getElementById('drawerNombre').innerText = r.nombre;
            document.getElementById('drawerTiempo').innerText = `${r.tiempo} min`;
            document.getElementById('drawerPorciones').innerText = r.porciones;
            document.getElementById('drawerCalorias').innerText = `${r.calorias} kcal`;
            document.getElementById('drawerCosto').innerText = `$${r.costo.toFixed(2)}`;
            document.getElementById('drawerPrecio').innerText = `$${r.precio.toFixed(2)}`;
            
            const m = r.precio > 0 ? (((r.precio - r.costo) / r.precio) * 100).toFixed(0) : 0;
            const bdg = document.getElementById('drawerMargen');
            bdg.innerText = `${m}%`;
            bdg.style.color = m >= 60 ? 'var(--green)' : 'var(--tan)';

            const insumosText = viewIngrMap[id] || [];
            document.getElementById('drawerIngredientes').innerHTML = insumosText.length === 0 
                ? `<li class="text-xs italic" style="color: #47474755;">Sin ingredientes registrados</li>`
                : insumosText.map(i => `<li class="flex items-center gap-3"><div class="w-1 h-1 rounded-full shrink-0" style="background: var(--tan);"></div><span class="text-sm" style="color: var(--charcoal);">${i}</span></li>`).join('');

            document.getElementById('drawerPreparacion').innerText = r.preparacion || 'Sin instrucciones.';

            drawerOverlay.classList.remove('hidden');
            setTimeout(() => { drawerOverlay.style.opacity = "1"; }, 10);
            detailDrawer.classList.remove('translate-x-full');
            lucide.createIcons();
        }

        // render inputs edit o nuevo
        window.abrirFormulario = function(id = null) {
            const container = document.getElementById('ingredientes-container');
            container.innerHTML = ''; 

            if(id) {
                const r = recetasFormateadas.find(i => i.id == id);
                document.getElementById('frmTitle').innerText = "Editar Receta";
                document.getElementById('frm_id').value = r.id;
                document.getElementById('frm_nombre').value = r.nombre;
                document.getElementById('frm_cat').value = r.categoria;
                document.getElementById('frm_tiempo').value = r.tiempo;
                document.getElementById('frm_porc').value = r.porciones;
                document.getElementById('frm_cal').value = r.calorias;
                // El costo se llenara dinamico pero lo guardamos visual
                document.getElementById('frm_costo').value = r.costo; 
                document.getElementById('frm_precio').value = r.precio;
                document.getElementById('frm_prep').value = r.preparacion;
                document.getElementById('frm_activa').checked = r.activa;

                const misIngr = formIngrMap[id] || [];
                if(misIngr.length > 0) {
                    misIngr.forEach(i => addIngredientRow(i.id, i.cant));
                } else {
                    addIngredientRow();
                }
            } else {
                document.getElementById('frmTitle').innerText = "Nueva Receta";
                document.getElementById('frm_id').value = "0";
                document.getElementById('frm_nombre').value = "";
                document.getElementById('frm_cat').value = "Principales";
                document.getElementById('frm_tiempo').value = "";
                document.getElementById('frm_porc').value = "";
                document.getElementById('frm_cal').value = "";
                document.getElementById('frm_costo').value = "0.00";
                document.getElementById('frm_precio').value = "";
                document.getElementById('frm_prep').value = "";
                document.getElementById('frm_activa').checked = true;
                addIngredientRow();
            }

            drawerOverlay.classList.remove('hidden');
            setTimeout(() => { drawerOverlay.style.opacity = "1"; }, 10);
            formDrawer.classList.remove('translate-x-full');
            lucide.createIcons();
        }

        window.abrirFormularioDesdeVista = function() {
            if(!recetaSelect) return;
            const id = recetaSelect;
            detailDrawer.classList.add('translate-x-full'); 
            setTimeout(() => { abrirFormulario(id); }, 300); 
        }

        // fx calculo en vivo de inventario
        window.addIngredientRow = function(id = '', cant = '') {
            const div = document.createElement('div');
            div.className = 'flex items-center gap-1.5 mb-1 fila-insumo';
            let opts = '<option value="">Sel...</option>';
            catalogo.forEach(ing => {
                const sel = ing.Id_Ingrediente == id ? 'selected' : '';
                // Inyectamos el costo como metadata en el HTML para js
                opts += `<option value="${ing.Id_Ingrediente}" data-costo="${ing.Costo}" ${sel}>${ing.Nombre}</option>`;
            });
            div.innerHTML = `
                <div class="flex-1 relative">
                    <select name="ingredientes[]" required onchange="calcCosto()" class="sel-ing modern-input w-full pl-2 pr-6 py-2 rounded-lg text-xs appearance-none text-ellipsis">
                        ${opts}
                    </select>
                </div>
                <div class="w-1/3">
                    <input type="text" name="cantidades[]" value="${cant}" onkeyup="calcCosto()" onchange="calcCosto()" required placeholder="Ej. 2 pza" class="cant-ing modern-input w-full px-2 py-2 rounded-lg text-xs text-center">
                </div>
                <button type="button" onclick="this.parentElement.remove(); calcCosto();" class="p-2 rounded-lg text-red-400 hover:bg-red-50 transition-colors">
                    <i data-lucide="x" class="w-3.5 h-3.5"></i>
                </button>
            `;
            document.getElementById('ingredientes-container').appendChild(div);
            lucide.createIcons();
            calcCosto();
        }

        // suma logica
        window.calcCosto = function() {
            let total = 0;
            document.querySelectorAll('.fila-insumo').forEach(fila => {
                const sel = fila.querySelector('.sel-ing');
                const cantInput = fila.querySelector('.cant-ing');
                if (sel && sel.value && cantInput && cantInput.value) {
                    const opt = sel.options[sel.selectedIndex];
                    const costoItem = parseFloat(opt.getAttribute('data-costo')) || 0;
                    const cant = parseFloat(cantInput.value) || 0;
                    total += (costoItem * cant);
                }
            });
            document.getElementById('frm_costo').value = total.toFixed(2);
        }

        window.borrarReceta = function() {
            if(!recetaSelect) return;
            if(confirm("¿Eliminar permanentemente esta receta?")) {
                document.getElementById('eliminar_id_input').value = recetaSelect;
                document.getElementById('formEliminar').submit();
            }
        }

        // listener basico
        searchInput.addEventListener('input', (e) => { busqueda = e.target.value; render(); });
        toggleBg.parentElement.addEventListener('click', (e) => { e.preventDefault(); soloActivas = !soloActivas; render(); });
        window.setCategoria = function(cat) { categoriaActual = cat; render(); }
        render();
    </script>
</body>
</html>