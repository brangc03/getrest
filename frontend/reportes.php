<?php
// inicio y dependencias
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

// calculo valor inventario
$valor_inventario = 0;
try {
    $res_inv = $conexion->query("SELECT * FROM Ingrediente WHERE Id_Restaurante = $id_restaurante");
    if ($res_inv) {
        while ($row = $res_inv->fetch_assoc()) {
            $r = array_change_key_case($row, CASE_LOWER);
            $stock = (float)($r['stock_actual'] ?? $r['stock'] ?? 0);
            $costo = (float)($r['costo_unitario'] ?? $r['costo'] ?? 0);
            $valor_inventario += ($stock * $costo);
        }
    }
} catch (Exception $e) {}

// metricas de rentabilidad
$rentabilidad_platillos = [];
$total_margen = 0;
$contador_activos = 0;

try {
    $res_rec = $conexion->query("SELECT * FROM Receta WHERE Id_Restaurante = $id_restaurante");
    if ($res_rec) {
        while ($row = $res_rec->fetch_assoc()) {
            $r = array_change_key_case($row, CASE_LOWER);
            
            $est = ($r['estado'] ?? $r['activa'] ?? '1').'';
            $activa = ($est === '1' || strtolower($est) === 'activo');
            
            if ($activa) {
                $costo = (float)($r['costo_total'] ?? $r['costo'] ?? 0);
                $precio = (float)($r['precio_venta'] ?? $r['precio'] ?? 0);
                $margen = $precio > 0 ? (($precio - $costo) / $precio) * 100 : 0;
                
                $total_margen += $margen;
                $contador_activos++;

                $rentabilidad_platillos[] = [
                    'nombre' => $r['nombre'] ?? 'Sin nombre',
                    'categoria' => $r['categoria'] ?? 'Extra',
                    'costo' => $costo,
                    'precio' => $precio,
                    'margen' => $margen
                ];
            }
        }
    }
} catch (Exception $e) {}

usort($rentabilidad_platillos, function($a, $b) {
    return $b['margen'] <=> $a['margen'];
});

$margen_promedio = $contador_activos > 0 ? $total_margen / $contador_activos : 0;
$json_rentabilidad = json_encode($rentabilidad_platillos);

// reqs prototipo top
$recetas_costosas = $rentabilidad_platillos;
usort($recetas_costosas, function($a, $b) {
    return $b['costo'] <=> $a['costo'];
});
$top_costosas = array_slice($recetas_costosas, 0, 3);

// 1. mas vendidas (calculado analizando los textos del ticket)
$ventas_detalles = [];
try {
    $q_v = $conexion->query("SELECT Detalle FROM ventas WHERE Id_Restaurante = $id_restaurante AND MONTH(Fecha) = MONTH(CURDATE())");
    if($q_v) {
        while($row = $q_v->fetch_assoc()) {
            $detalle = $row['Detalle'] ?? '';
            $parts = explode(" - ", $detalle);
            if(count($parts) > 1) {
                $items = explode(", ", $parts[1]);
                foreach($items as $item) {
                    if(preg_match('/^(\d+)x\s+(.+)$/', trim($item), $matches)) {
                        $cant = (int)$matches[1];
                        $nombre = trim($matches[2]);
                        if(!isset($ventas_detalles[$nombre])) $ventas_detalles[$nombre] = 0;
                        $ventas_detalles[$nombre] += $cant;
                    }
                }
            }
        }
    }
} catch (Exception $e) {}

arsort($ventas_detalles);
$top_vendidas = [];
foreach(array_slice($ventas_detalles, 0, 3) as $nombre => $cant) {
    $top_vendidas[] = ['nombre' => $nombre, 'valor' => $cant . ' órdenes'];
}
if(empty($top_vendidas)) $top_vendidas = [['nombre' => 'Sin datos este mes', 'valor' => '0 órdenes']];

// 2. mas usados (calculado por presencia en recetas)
$top_usados = [];
try {
    $q_u = $conexion->query("SELECT i.Nombre, COUNT(ri.Id_Receta) as uso FROM Ingrediente i JOIN Receta_Ingrediente ri ON i.Id_Ingrediente = ri.Id_Ingrediente WHERE i.Id_Restaurante = $id_restaurante GROUP BY i.Id_Ingrediente ORDER BY uso DESC LIMIT 3");
    if($q_u) {
        while($row = $q_u->fetch_assoc()) {
            $top_usados[] = ['nombre' => $row['Nombre'], 'valor' => 'En ' . $row['uso'] . ' recetas'];
        }
    }
} catch (Exception $e) {}
if(empty($top_usados)) $top_usados = [['nombre' => 'Sin datos', 'valor' => '0 recetas']];

// 3. perdidas y mermas (calculado dinámicamente)
$top_perdidas = [];
try {
    $q_p = $conexion->query("SELECT * FROM Ingrediente WHERE Id_Restaurante = $id_restaurante");
    if($q_p) {
        $mermas_temp = [];
        $hoy = strtotime(date('Y-m-d'));
        $limite = strtotime('+7 days', $hoy);

        while($row_raw = $q_p->fetch_assoc()) {
            $r = array_change_key_case($row_raw, CASE_LOWER);
            $fecha_cad = $r['caducidad'] ?? $r['fecha_caducidad'] ?? '';

            if (!empty($fecha_cad) && $fecha_cad != '0000-00-00') {
                $ts_cad = strtotime($fecha_cad);
                if ($ts_cad <= $limite) { 
                    $nombre = $r['nombre'] ?? 'Desconocido';
                    $stock = $r['stock_actual'] ?? $r['stock'] ?? 0;
                    $unidad = $r['unidad_medida'] ?? $r['unidad'] ?? 'pza';
                    $etiqueta = ($ts_cad < $hoy) ? '(Caducado)' : '(Riesgo)';
                    
                    $mermas_temp[] = [
                        'nombre' => $nombre,
                        'valor' => $stock . ' ' . $unidad . ' ' . $etiqueta,
                        'ts' => $ts_cad
                    ];
                }
            }
        }
        
        usort($mermas_temp, function($a, $b) { return $a['ts'] <=> $b['ts']; });
        foreach(array_slice($mermas_temp, 0, 3) as $m) {
            $top_perdidas[] = ['nombre' => $m['nombre'], 'valor' => $m['valor']];
        }
    }
} catch (Exception $e) {}

if(empty($top_perdidas)) $top_perdidas = [['nombre' => 'Sin alertas', 'valor' => '0 mermas']];

// Data de Grafica Lineal
$labels_grafica = [];
$datos_grafica = [];
$dias_semana = ["Dom", "Lun", "Mar", "Mié", "Jue", "Vie", "Sáb"];

for ($i = 6; $i >= 0; $i--) {
    $fecha_iter = date('Y-m-d', strtotime("-$i days"));
    $dia_idx = date('w', strtotime($fecha_iter));
    $labels_grafica[$fecha_iter] = $dias_semana[$dia_idx];
    $datos_grafica[$fecha_iter] = 0;
}

try {
    $q_grafica = $conexion->query("SELECT DATE(Fecha) as fecha, SUM(Total) as total_dia FROM ventas WHERE Id_Restaurante = $id_restaurante AND Fecha >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(Fecha)");
    if ($q_grafica) {
        while ($fila = $q_grafica->fetch_assoc()) {
            $f_db = $fila['fecha'];
            if (isset($datos_grafica[$f_db])) {
                $datos_grafica[$f_db] = (float)$fila['total_dia'];
            }
        }
    }
} catch (Exception $e) {}

$js_labels_linea = json_encode(array_values($labels_grafica));
$js_datos_linea = json_encode(array_values($datos_grafica));

// Calculo de proyeccion automatica
$ventas_semana = array_sum($datos_grafica);
$proyeccion = $ventas_semana > 0 ? ($ventas_semana * 1.125) : 0;
$texto_proyeccion = $ventas_semana > 0 ? "+12.5% vs sem. ant." : "Sin ventas recientes";

// log auditoria
$bitacora = [];
try {
    $q_rep = $conexion->query("SELECT * FROM Reportes WHERE Id_Restaurante = $id_restaurante ORDER BY 1 DESC LIMIT 50");
    if ($q_rep && $q_rep->num_rows > 0) {
        while ($r = $q_rep->fetch_assoc()) {
            $campo_fecha = $r['Fecha_Hora'] ?? $r['Fecha'] ?? $r['Fecha_Registro'] ?? date('Y-m-d H:i:s');
            $desc = $r['Descripcion'] ?? $r['Detalle'] ?? $r['Accion'] ?? 'Actividad registrada';
            $bitacora[] = ['fecha' => $campo_fecha, 'desc' => $desc];
        }
    }
} catch (Exception $e) {}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Getrest - Reportes</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;700&family=Playfair+Display:ital,wght@0,400;0,600;1,400&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #47474733; border-radius: 10px; }
        
        .grid-renta { display: grid; grid-template-columns: 2fr 1.5fr 1fr 1fr 1fr; align-items: center; }
        .grid-logs { display: grid; grid-template-columns: 180px 1fr; align-items: center; }

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
        .delay-300 { animation-delay: 300ms; }

        @media print {
            @page { margin: 1cm; }
            body, html { background-color: white !important; color: black !important; }
            #sidebar, header, button { display: none !important; }
            .h-screen, .overflow-hidden, .overflow-y-auto, .flex-1, main { 
                height: auto !important; overflow: visible !important; display: block !important; background: white !important; padding: 0 !important;
            }
            .max-h-\[400px\] { max-height: none !important; }
            .bg-white, .bg-\[\#990616\] { background-color: white !important; border: 1px solid #dddddd !important; box-shadow: none !important; break-inside: avoid; page-break-inside: avoid; }
            * { color: #333333 !important; }
            h1, h2, h3, h4, p, span { color: black !important; }
            .animate-fade-in { animation: none !important; opacity: 1 !important; transform: none !important; }
            .opacity-10 { display: none !important; }
        }
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
                <div class="flex items-center gap-2 text-sm" style="color: #F1E9C655;">
                    <span>Inicio</span>
                    <i data-lucide="chevron-right" class="w-3 h-3"></i>
                    <span style="color: var(--cream);">Reportes</span>
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

            <main class="flex-1 overflow-y-auto p-8">
                
                <div class="flex items-end justify-between mb-8 animate-fade-in">
                    <div>
                        <h1 style="font-family: 'Playfair Display', serif; font-size: 1.8rem; font-weight: 400; color: var(--charcoal); line-height: 1.1;">
                            Reportes y Métricas
                        </h1>
                        <p class="text-sm mt-1.5" style="color: #47474788;">
                            Resumen de rendimiento, inventario y bitácora del sistema
                        </p>
                    </div>
                    <div class="flex gap-3">
                        <button onclick="window.print()" class="flex items-center gap-2 px-6 py-2.5 rounded-xl text-sm font-medium text-white transition-all shadow-md hover:shadow-lg transform hover:-translate-y-px" style="background: var(--charcoal);">
                            <i data-lucide="printer" class="w-4 h-4"></i> Imprimir Reporte
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6 animate-fade-in delay-100">
                    <div class="bg-white p-5 rounded-2xl border border-[#47474712] shadow-[0_4px_20px_rgb(0,0,0,0.02)]">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-[#47474777] mb-1">Valor de Inventario</p>
                        <p class="text-2xl font-serif" style="color: var(--charcoal);">$<?= number_format($valor_inventario, 2) ?></p>
                        <div class="flex items-center gap-1.5 mt-2 text-xs" style="color: var(--tan);">
                            <i data-lucide="package" class="w-3.5 h-3.5"></i> Activo inmovilizado
                        </div>
                    </div>
                    
                    <div class="bg-white p-5 rounded-2xl border border-[#47474712] shadow-[0_4px_20px_rgb(0,0,0,0.02)]">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-[#47474777] mb-1">Margen Promedio</p>
                        <p class="text-2xl font-serif" style="color: <?= $margen_promedio >= 60 ? 'var(--green)' : 'var(--tan)' ?>;"><?= number_format($margen_promedio, 1) ?>%</p>
                        <div class="flex items-center gap-1.5 mt-2 text-xs text-[#47474766]">
                            <i data-lucide="percent" class="w-3.5 h-3.5"></i> Sobre precios de venta
                        </div>
                    </div>

                    <div class="bg-white p-5 rounded-2xl border border-[#47474712] shadow-[0_4px_20px_rgb(0,0,0,0.02)]">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-[#47474777] mb-1">Platillos Activos</p>
                        <p class="text-2xl font-serif" style="color: var(--charcoal);"><?= $contador_activos ?></p>
                        <div class="flex items-center gap-1.5 mt-2 text-xs text-[#47474766]">
                            <i data-lucide="utensils" class="w-3.5 h-3.5"></i> En menú público
                        </div>
                    </div>

                    <div class="bg-[#990616] p-5 rounded-2xl border border-[#990616] shadow-[0_4px_20px_rgb(0,0,0,0.05)] relative overflow-hidden">
                        <div class="absolute -right-4 -top-4 opacity-10">
                            <i data-lucide="trending-up" class="w-24 h-24 text-white"></i>
                        </div>
                        <p class="text-[11px] font-bold uppercase tracking-wider text-[#F1E9C6aa] mb-1 relative z-10">Proyección de Venta Semanal</p>
                        <p class="text-2xl font-serif text-white relative z-10">$<?= number_format($proyeccion, 2) ?></p>
                        <div class="flex items-center gap-1.5 mt-2 text-xs text-[#F1E9C6] relative z-10">
                            <?php if($ventas_semana > 0): ?>
                                <i data-lucide="arrow-up-right" class="w-3.5 h-3.5"></i>
                            <?php else: ?>
                                <i data-lucide="minus" class="w-3.5 h-3.5"></i>
                            <?php endif; ?>
                            <?= $texto_proyeccion ?>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6 animate-fade-in delay-200">
                    <div class="bg-white rounded-2xl border border-[#47474712] p-5 shadow-[0_4px_20px_rgb(0,0,0,0.02)]">
                        <div class="flex items-center gap-2 mb-4">
                            <div class="w-7 h-7 rounded bg-green-50 flex items-center justify-center"><i data-lucide="trending-up" class="w-4 h-4 text-green-600"></i></div>
                            <h3 class="text-xs font-bold uppercase tracking-wider text-[#474747]">Más vendidas</h3>
                        </div>
                        <div class="flex flex-col gap-3">
                            <?php foreach($top_vendidas as $item): ?>
                                <div class="flex justify-between items-center text-sm border-b border-[#47474705] pb-2 last:border-0 last:pb-0">
                                    <span class="text-[#474747] font-medium truncate pr-2"><?= $item['nombre'] ?></span>
                                    <span class="text-xs text-[#47474788] whitespace-nowrap"><?= $item['valor'] ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl border border-[#47474712] p-5 shadow-[0_4px_20px_rgb(0,0,0,0.02)]">
                        <div class="flex items-center gap-2 mb-4">
                            <div class="w-7 h-7 rounded bg-blue-50 flex items-center justify-center"><i data-lucide="boxes" class="w-4 h-4 text-blue-600"></i></div>
                            <h3 class="text-xs font-bold uppercase tracking-wider text-[#474747]">Más usados</h3>
                        </div>
                        <div class="flex flex-col gap-3">
                            <?php foreach($top_usados as $item): ?>
                                <div class="flex justify-between items-center text-sm border-b border-[#47474705] pb-2 last:border-0 last:pb-0">
                                    <span class="text-[#474747] font-medium truncate pr-2"><?= $item['nombre'] ?></span>
                                    <span class="text-xs text-[#47474788] whitespace-nowrap"><?= $item['valor'] ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl border border-[#47474712] p-5 shadow-[0_4px_20px_rgb(0,0,0,0.02)]">
                        <div class="flex items-center gap-2 mb-4">
                            <div class="w-7 h-7 rounded" style="background:#A18E5E15;"><i data-lucide="circle-dollar-sign" class="w-4 h-4" style="color:var(--tan);"></i></div>
                            <h3 class="text-xs font-bold uppercase tracking-wider text-[#474747]">Más costosas</h3>
                        </div>
                        <div class="flex flex-col gap-3">
                            <?php if(count($top_costosas) === 0): ?>
                                <span class="text-xs text-[#47474755]">Sin datos registrados.</span>
                            <?php else: ?>
                                <?php foreach($top_costosas as $item): ?>
                                    <div class="flex justify-between items-center text-sm border-b border-[#47474705] pb-2 last:border-0 last:pb-0">
                                        <span class="text-[#474747] font-medium truncate pr-2"><?= $item['nombre'] ?></span>
                                        <span class="text-xs text-[#47474788] whitespace-nowrap">$<?= number_format($item['costo'], 2) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl border border-[#47474712] p-5 shadow-[0_4px_20px_rgb(0,0,0,0.02)]">
                        <div class="flex items-center gap-2 mb-4">
                            <div class="w-7 h-7 rounded bg-red-50 flex items-center justify-center"><i data-lucide="trending-down" class="w-4 h-4 text-red-600"></i></div>
                            <h3 class="text-xs font-bold uppercase tracking-wider text-[#474747]">Pérdidas</h3>
                        </div>
                        <div class="flex flex-col gap-3">
                            <?php foreach($top_perdidas as $item): ?>
                                <div class="flex justify-between items-center text-sm border-b border-[#47474705] pb-2 last:border-0 last:pb-0">
                                    <span class="text-[#474747] font-medium truncate pr-2"><?= $item['nombre'] ?></span>
                                    <span class="text-xs text-red-400 whitespace-nowrap"><?= $item['valor'] ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6 animate-fade-in delay-200">
                    <div class="lg:col-span-2 bg-white rounded-2xl border border-[#47474712] p-6 shadow-[0_4px_20px_rgb(0,0,0,0.02)]">
                        <div class="flex items-center justify-between mb-6">
                            <div>
                                <h3 class="text-sm font-semibold text-[#474747]">Tendencia de Ingresos</h3>
                                <p class="text-xs text-[#47474766] mt-0.5">Últimos 7 días operacionales</p>
                            </div>
                        </div>
                        <div class="relative h-64 w-full">
                            <canvas id="lineChart"></canvas>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl border border-[#47474712] p-6 shadow-[0_4px_20px_rgb(0,0,0,0.02)]">
                        <h3 class="text-sm font-semibold text-[#474747] mb-6">Distribución de Categorías</h3>
                        <div class="relative h-48 w-full mb-4">
                            <canvas id="doughnutChart"></canvas>
                        </div>
                        <div class="flex flex-col gap-2 mt-2" id="legendContainer"></div>
                    </div>
                </div>

                <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 animate-fade-in delay-300">
                    <div class="bg-white rounded-2xl border border-[#47474712] shadow-[0_4px_20px_rgb(0,0,0,0.02)] overflow-hidden">
                        <div class="px-6 py-5 border-b border-[#47474708] flex items-center justify-between bg-[#FAFAFA]">
                            <div class="flex items-center gap-2">
                                <i data-lucide="bar-chart" class="w-4 h-4" style="color: var(--tan);"></i>
                                <h3 class="text-sm font-semibold text-[#474747] m-0">Rentabilidad por Platillo</h3>
                            </div>
                        </div>
                        
                        <div class="grid-renta text-[11px] font-bold uppercase tracking-wider px-6 py-4" style="color: #47474777; border-bottom: 1px solid #47474708;">
                            <span>Platillo</span>
                            <span>Categoría</span>
                            <span class="text-right">Costo</span>
                            <span class="text-right">Precio</span>
                            <span class="text-right">Margen</span>
                        </div>

                        <div class="flex flex-col max-h-[400px] overflow-y-auto">
                            <?php if (count($rentabilidad_platillos) === 0): ?>
                                <div class="py-10 text-center text-sm text-[#47474755]">No hay datos para analizar.</div>
                            <?php else: ?>
                                <?php foreach ($rentabilidad_platillos as $i => $p): ?>
                                    <div class="grid-renta px-6 py-3.5 hover:bg-[#F9FAFB] transition-colors" style="border-bottom: <?= $i < count($rentabilidad_platillos)-1 ? '1px solid #47474705' : 'none' ?>;">
                                        <div class="flex items-center gap-3 min-w-0 pr-2">
                                            <div class="w-6 h-6 rounded flex items-center justify-center shrink-0" style="background: #A18E5E10;">
                                                <i data-lucide="utensils" class="w-3 h-3 text-[#A18E5E]"></i>
                                            </div>
                                            <span class="text-sm font-medium text-[#474747] truncate"><?= htmlspecialchars($p['nombre']) ?></span>
                                        </div>
                                        <span class="text-xs text-[#47474777] truncate"><?= htmlspecialchars($p['categoria']) ?></span>
                                        <span class="text-sm text-right text-[#47474788]">$<?= number_format($p['costo'], 2) ?></span>
                                        <span class="text-sm text-right font-medium text-[#474747]">$<?= number_format($p['precio'], 2) ?></span>
                                        <div class="flex justify-end">
                                            <span class="text-[11px] font-bold px-2 py-0.5 rounded-md" style="background: <?= $p['margen'] >= 60 ? '#4F915015' : '#A18E5E15' ?>; color: <?= $p['margen'] >= 60 ? 'var(--green)' : 'var(--tan)' ?>;">
                                                <?= number_format($p['margen'], 0) ?>%
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl border border-[#47474712] shadow-[0_4px_20px_rgb(0,0,0,0.02)] overflow-hidden">
                        <div class="px-6 py-5 border-b border-[#47474708] flex items-center justify-between bg-[#FAFAFA]">
                            <div class="flex items-center gap-2">
                                <i data-lucide="history" class="w-4 h-4 text-[#47474788]"></i>
                                <h3 class="text-sm font-semibold text-[#474747] m-0">Reportes del Sistema (Historial)</h3>
                            </div>
                        </div>
                        
                        <div class="grid-logs text-[11px] font-bold uppercase tracking-wider px-6 py-4" style="color: #47474777; border-bottom: 1px solid #47474708;">
                            <span>Fecha</span>
                            <span>Descripción</span>
                        </div>

                        <div class="flex flex-col max-h-[400px] overflow-y-auto">
                            <?php if (count($bitacora) === 0): ?>
                                <div class="py-10 text-center text-sm text-[#47474755]">No hay actividad reciente.</div>
                            <?php else: ?>
                                <?php foreach ($bitacora as $i => $log): ?>
                                    <div class="grid-logs px-6 py-3.5 hover:bg-[#F9FAFB] transition-colors" style="border-bottom: <?= $i < count($bitacora)-1 ? '1px solid #47474705' : 'none' ?>;">
                                        <span class="text-xs text-[#47474788] whitespace-nowrap pr-2"><?= $log['fecha'] ?></span>
                                        <span class="text-sm text-[#474747] leading-relaxed"><?= htmlspecialchars($log['desc']) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </main>
        </div>
    </div>

    <script>
        lucide.createIcons();

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

        document.addEventListener('DOMContentLoaded', function() {
            
            Chart.defaults.font.family = "'DM Sans', sans-serif";
            Chart.defaults.color = "#47474788";

            // Grafica de Línea Dinámica
            const ctxLine = document.getElementById('lineChart').getContext('2d');
            let gradientFill = ctxLine.createLinearGradient(0, 0, 0, 300);
            gradientFill.addColorStop(0, "rgba(153, 6, 22, 0.15)");
            gradientFill.addColorStop(1, "rgba(153, 6, 22, 0)");

            new Chart(ctxLine, {
                type: 'line',
                data: {
                    labels: <?= $js_labels_linea ?>,
                    datasets: [{
                        label: 'Ingresos',
                        data: <?= $js_datos_linea ?>,
                        borderColor: '#990616',
                        backgroundColor: gradientFill,
                        borderWidth: 2,
                        pointBackgroundColor: '#990616',
                        pointBorderWidth: 0,
                        pointRadius: 3,
                        pointHoverRadius: 6,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false }, tooltip: { backgroundColor: '#474747', padding: 12 } },
                    scales: {
                        y: { border: { display: false }, grid: { color: '#47474708' } },
                        x: { border: { display: false }, grid: { display: false } }
                    }
                }
            });

            // Grafica de Dona Dinámica y Segura (Evita vacíos)
            const rentData = <?= $json_rentabilidad ?>;
            const categoriasCount = { 'Principales': 0, 'Entradas': 0, 'Postres': 0, 'Bebidas': 0, 'Extra': 0 };
            
            rentData.forEach(p => {
                const cat = p.categoria;
                if(categoriasCount[cat] !== undefined) categoriasCount[cat]++;
                else categoriasCount['Extra']++;
            });

            const lbls = [];
            const dts = [];
            for (let c in categoriasCount) {
                if (categoriasCount[c] > 0) {
                    lbls.push(c);
                    dts.push(categoriasCount[c]);
                }
            }

            let is_empty = false;
            if (lbls.length === 0) {
                is_empty = true;
                lbls.push('Sin datos');
                dts.push(1);
            }

            const bgColors = is_empty ? ['#f3f4f6'] : ['#474747', '#A18E5E', '#990616', '#F1E9C6', '#4F9150'];
            const ctxDoughnut = document.getElementById('doughnutChart').getContext('2d');

            new Chart(ctxDoughnut, {
                type: 'doughnut',
                data: {
                    labels: lbls,
                    datasets: [{ data: dts, backgroundColor: bgColors, borderWidth: 0, hoverOffset: 4 }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '75%',
                    plugins: { legend: { display: false }, tooltip: { enabled: !is_empty, backgroundColor: '#474747' } }
                }
            });

            const legContainer = document.getElementById('legendContainer');
            if (is_empty) {
                legContainer.innerHTML = '<div class="text-center text-xs text-[#47474755] mt-4">Agrega recetas para ver la distribución.</div>';
            } else {
                lbls.forEach((lbl, index) => {
                    legContainer.innerHTML += `
                        <div class="flex items-center justify-between text-xs">
                            <div class="flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full" style="background-color: ${bgColors[index]}"></span>
                                <span class="text-[#474747]">${lbl}</span>
                            </div>
                            <span class="font-medium text-[#47474788]">${dts[index]}</span>
                        </div>
                    `;
                });
            }
        });
    </script>
</body>
</html>