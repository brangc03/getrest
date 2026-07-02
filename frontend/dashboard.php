<?php
// conf init
session_start();
require(__DIR__ . "/conexion.php");

// auth check
if (!isset($_SESSION['usuario'])) {
    header("Location: /getrest/login.php");
    exit();
}

// eval permisos
if ($_SESSION['rol'] !== 'gerente') {
    header("Location: /getrest/inventario.php");
    exit();
}

$rol_usr = ucfirst($_SESSION['rol'] ?? 'Usuario');
$ini_usr = strtoupper(substr($rol_usr, 0, 2));
$id_rest = $_SESSION['restaurante'];

// kpis base
$v_hoy = 0;
$p_hoy = 0;
$i_mes = 0;
$a_stock = 0;

// qry ventas hoy
try {
    $q_hoy = $conexion->query("SELECT SUM(Total) as t, COUNT(Id_Venta) as c FROM Ventas WHERE Id_Restaurante = $id_rest AND DATE(Fecha) = CURDATE()");
    if ($q_hoy && $r = $q_hoy->fetch_assoc()) {
        $v_hoy = (float)$r['t'];
        $p_hoy = (int)$r['c'];
    }
} catch (Exception $e) {}

// qry mes
try {
    $q_mes = $conexion->query("SELECT SUM(Total) as t FROM Ventas WHERE Id_Restaurante = $id_rest AND MONTH(Fecha) = MONTH(CURDATE()) AND YEAR(Fecha) = YEAR(CURDATE())");
    if ($q_mes && $r = $q_mes->fetch_assoc()) {
        $i_mes = (float)$r['t'];
    }
} catch (Exception $e) {}

// qry alertas inv
try {
    $q_al = $conexion->query("SELECT COUNT(*) as c FROM Ingrediente WHERE Id_Restaurante = $id_rest AND (Stock_Actual <= Stock_Minimo OR (Caducidad IS NOT NULL AND Caducidad <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)))");
    if ($q_al && $r = $q_al->fetch_assoc()) {
        $a_stock = (int)$r['c'];
    }
} catch (Exception $e) {}

// qry grafica 7d
$lbls = [];
$dts = [];
$ds = ["Dom", "Lun", "Mar", "Mié", "Jue", "Vie", "Sáb"];

for ($i = 6; $i >= 0; $i--) {
    $f = date('Y-m-d', strtotime("-$i days"));
    $idx = date('w', strtotime($f));
    $lbls[$f] = $ds[$idx];
    $dts[$f] = 0;
}

try {
    $q_g = $conexion->query("SELECT DATE(Fecha) as f, SUM(Total) as t FROM Ventas WHERE Id_Restaurante = $id_rest AND Fecha >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(Fecha)");
    if ($q_g) {
        while ($row = $q_g->fetch_assoc()) {
            if (isset($dts[$row['f']])) {
                $dts[$row['f']] = (float)$row['t'];
            }
        }
    }
} catch (Exception $e) {}

$js_lbls = json_encode(array_values($lbls));
$js_dts = json_encode(array_values($dts));

// qry logs rep
$logs = [];
try {
    $q_rep = $conexion->query("SELECT Fecha_Hora, Descripcion FROM Reportes WHERE Id_Restaurante = $id_rest ORDER BY Fecha_Hora DESC LIMIT 6");
    if ($q_rep) {
        while ($row = $q_rep->fetch_assoc()) {
            $logs[] = $row;
        }
    }
} catch (Exception $e) {}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Getrest - Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=Playfair+Display:ital,wght@0,400;0,600;1,400&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root { --crimson: #990616; --cream: #F1E9C6; --tan: #A18E5E; --charcoal: #474747; }
        body { font-family: 'DM Sans', sans-serif; background-color: var(--cream); margin: 0; }
        .sidebar-item { background: transparent; color: #F1E9C688; }
        .sidebar-item:hover { background: #ffffff08; }
        .sidebar-item.active { background: var(--crimson); color: var(--cream); }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-thumb { background: #47474733; border-radius: 10px; }
    </style>
</head>
<body>
    <div class="h-screen w-full flex overflow-hidden">
        
        <aside id="sidebar" class="flex flex-col h-full shrink-0 transition-all duration-300 z-30" style="width: 220px; background: var(--charcoal); border-right: 1px solid #5a5a5a44;">
            <div class="flex items-center gap-3 px-5 py-5 shrink-0 cursor-pointer" onclick="toggleSidebar()" style="border-bottom: 1px solid #ffffff10;">
                <div class="w-7 h-7 rounded flex items-center justify-center shrink-0" style="background: var(--crimson);">
                    <span style="font-family: 'Playfair Display', serif; color: var(--cream); font-weight: 600; font-size: 12px;">G</span>
                </div>
                <span class="sidebar-text text-sm tracking-widest uppercase font-light" style="color: #F1E9C6cc;">GetRest</span>
            </div>

            <nav class="flex-1 flex flex-col gap-1 px-2 py-4">
                <?php if ($_SESSION['rol'] === 'gerente'): ?>
                    <a href="/getrest/dashboard.php" class="sidebar-item active flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition-all duration-150">
                        <i data-lucide="layout-dashboard" class="w-4 h-4 shrink-0"></i>
                        <span class="sidebar-text">Inicio</span>
                    </a>
                <?php endif; ?>

                <?php if ($_SESSION['rol'] === 'gerente' || $_SESSION['rol'] === 'chef'): ?>
                    <a href="/getrest/recetas.php" class="sidebar-item flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition-all duration-150">
                        <i data-lucide="book-open" class="w-4 h-4 shrink-0"></i>
                        <span class="sidebar-text">Recetas</span>
                    </a>
                <?php endif; ?>

                <a href="/getrest/inventario.php" class="sidebar-item flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition-all duration-150">
                    <i data-lucide="package" class="w-4 h-4 shrink-0"></i>
                    <span class="sidebar-text">Inventario</span>
                </a>

                <?php if ($_SESSION['rol'] === 'gerente'): ?>
                    <a href="/getrest/ventas.php" class="sidebar-item flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition-all duration-150">
                        <i data-lucide="shopping-cart" class="w-4 h-4 shrink-0"></i>
                        <span class="sidebar-text">Ventas</span>
                    </a>
                    <a href="/getrest/reportes.php" class="sidebar-item flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition-all duration-150">
                        <i data-lucide="bar-chart-2" class="w-4 h-4 shrink-0"></i>
                        <span class="sidebar-text">Reportes</span>
                    </a>
                    <a href="/getrest/equipo.php" class="sidebar-item flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition-all duration-150">
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

        <div class="flex-1 flex flex-col min-w-0" style="background: var(--cream);">
            
            <header class="flex items-center justify-between px-8 py-4 shrink-0" style="background: var(--charcoal); border-bottom: 1px solid #ffffff10;">
                <div class="flex items-center gap-2 text-sm" style="color: #F1E9C655;">
                    <span>Inicio</span>
                    <i data-lucide="chevron-right" class="w-3 h-3"></i>
                    <span style="color: var(--cream);">Panel de Control</span>
                </div>
                <div class="flex items-center gap-4">
                    <div class="flex items-center gap-2.5">
                        <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-medium" style="background: var(--tan); color: var(--cream);">
                            <?= $ini_usr ?>
                        </div>
                        <span class="text-xs" style="color: #F1E9C688;"><?= $rol_usr ?></span>
                    </div>
                </div>
            </header>

            <main class="flex-1 overflow-y-auto p-8">
                
                <div class="mb-8">
                    <h1 style="font-family: 'Playfair Display', serif; font-size: 2rem; color: var(--charcoal);">
                        Bienvenido, <em style="color: var(--crimson);"><?= $rol_usr ?></em>
                    </h1>
                    <?php
                        $dias = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
                        $meses = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
                        $fecha_str = "Hoy es " . $dias[date('w')] . " " . date('j') . " de " . $meses[date('n')-1];
                    ?>
                    <p class="text-sm mt-1" style="color: #47474788;"><?= $fecha_str ?> · Resumen general del negocio</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                    <div class="bg-white p-5 rounded-2xl border border-[#47474712] shadow-sm relative">
                        <div class="absolute top-5 right-5 w-6 h-6 rounded bg-green-50 flex items-center justify-center"><i data-lucide="trending-up" class="w-3 h-3 text-green-600"></i></div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-[#47474777] mb-1">Ventas Hoy</p>
                        <p class="text-2xl font-serif text-[#474747]">$<?= number_format($v_hoy, 2) ?></p>
                        <p class="text-xs text-green-600 mt-2 flex items-center gap-1"><i data-lucide="activity" class="w-3 h-3"></i> En tiempo real</p>
                    </div>

                    <div class="bg-white p-5 rounded-2xl border border-[#47474712] shadow-sm relative">
                        <div class="absolute top-5 right-5 w-6 h-6 rounded bg-[#47474708] flex items-center justify-center"><i data-lucide="shopping-bag" class="w-3 h-3 text-[#47474755]"></i></div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-[#47474777] mb-1">Pedidos Completados</p>
                        <p class="text-2xl font-serif text-[#474747]"><?= $p_hoy ?></p>
                        <p class="text-xs text-green-600 mt-2 flex items-center gap-1"><i data-lucide="activity" class="w-3 h-3"></i> En tiempo real</p>
                    </div>

                    <div class="bg-white p-5 rounded-2xl border border-[#47474712] shadow-sm relative">
                        <div class="absolute top-5 right-5 w-6 h-6 rounded <?= $a_stock > 0 ? 'bg-red-50' : 'bg-gray-50' ?> flex items-center justify-center"><i data-lucide="alert-triangle" class="w-3 h-3 <?= $a_stock > 0 ? 'text-red-500' : 'text-gray-400' ?>"></i></div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-[#47474777] mb-1">Alertas de Stock</p>
                        <p class="text-2xl font-serif text-[#474747]"><?= $a_stock ?></p>
                        <?php if($a_stock > 0): ?>
                            <p class="text-xs text-red-500 mt-2 flex items-center gap-1"><i data-lucide="alert-circle" class="w-3 h-3"></i> Requieren atención</p>
                        <?php else: ?>
                            <p class="text-xs text-green-600 mt-2 flex items-center gap-1"><i data-lucide="check-circle" class="w-3 h-3"></i> Todo en orden</p>
                        <?php endif; ?>
                    </div>

                    <div class="bg-white p-5 rounded-2xl border border-[#47474712] shadow-sm relative">
                        <div class="absolute top-5 right-5 w-6 h-6 rounded bg-[#47474708] flex items-center justify-center"><i data-lucide="calendar" class="w-3 h-3 text-[#47474755]"></i></div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-[#47474777] mb-1">Ingresos del Mes</p>
                        <p class="text-2xl font-serif text-[#474747]">$<?= number_format($i_mes, 2) ?></p>
                        <p class="text-xs text-green-600 mt-2 flex items-center gap-1"><i data-lucide="activity" class="w-3 h-3"></i> En tiempo real</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="lg:col-span-2 bg-white rounded-2xl border border-[#47474712] p-6 shadow-sm">
                        <div class="flex items-center justify-between mb-6">
                            <div>
                                <h3 class="text-sm font-semibold text-[#474747]">Ventas de la semana</h3>
                                <p class="text-xs text-[#47474766] mt-0.5">Últimos 7 días</p>
                            </div>
                            <span class="px-2.5 py-1 rounded bg-green-50 text-green-700 text-[10px] font-bold uppercase tracking-wider">Actualizado</span>
                        </div>
                        <div class="relative h-64 w-full">
                            <canvas id="mainChart"></canvas>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl border border-[#47474712] shadow-sm flex flex-col">
                        <div class="p-6 border-b border-[#47474708] flex justify-between items-center">
                            <h3 class="text-sm font-semibold text-[#474747]">Actividad reciente</h3>
                            <a href="/getrest/reportes.php" class="text-xs text-[#47474788] hover:text-[#990616] transition-colors">Ver todo</a>
                        </div>
                        <div class="p-6 flex-1 flex flex-col gap-4 overflow-y-auto max-h-[300px]">
                            <?php if(empty($logs)): ?>
                                <div class="h-full flex items-center justify-center">
                                    <p class="text-xs text-[#47474755]">No hay actividad registrada aún.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach($logs as $l): ?>
                                    <div class="flex gap-3">
                                        <div class="mt-1 w-2 h-2 rounded-full shrink-0" style="background: var(--tan);"></div>
                                        <div>
                                            <p class="text-xs text-[#474747] leading-relaxed"><?= htmlspecialchars($l['Descripcion']) ?></p>
                                            <p class="text-[10px] text-[#47474766] mt-1"><?= date('d/m/Y H:i', strtotime($l['Fecha_Hora'])) ?></p>
                                        </div>
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
        
        // ui tog
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

        // render chart
        document.addEventListener('DOMContentLoaded', function() {
            Chart.defaults.font.family = "'DM Sans', sans-serif";
            Chart.defaults.color = "#47474788";
            
            const ctx = document.getElementById('mainChart').getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: <?= $js_lbls ?>,
                    datasets: [{
                        label: 'Ventas ($)',
                        data: <?= $js_dts ?>,
                        borderColor: '#990616',
                        backgroundColor: 'rgba(153, 6, 22, 0.05)',
                        borderWidth: 2,
                        pointBackgroundColor: '#990616',
                        pointBorderWidth: 0,
                        pointRadius: 4,
                        fill: true,
                        tension: 0.3
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { 
                            beginAtZero: true,
                            border: { display: false }, 
                            grid: { color: '#47474708' },
                            ticks: { callback: function(value) { return '$' + value; } }
                        },
                        x: { border: { display: false }, grid: { display: false } }
                    }
                }
            });
        });
    </script>
</body>
</html>