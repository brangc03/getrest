<?php
// init req
session_start();
require(__DIR__ . "/conexion.php");

// eval usr lvl
if (!isset($_SESSION['usuario'])) {
    header("Location: /getrest/login.php");
    exit();
}

if ($_SESSION['rol'] !== 'gerente') {
    header("Location: /getrest/inventario.php");
    exit();
}

$rol_usuario = ucfirst($_SESSION['rol'] ?? 'Usuario');
$iniciales = strtoupper(substr($rol_usuario, 0, 2));
$id_rest = $_SESSION['restaurante'];

$err = "";
$msg = "";

// op form
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $c = trim($_POST['correo']);
    $p = trim($_POST['password']);
    $r = trim($_POST['rol']);

    // chk em
    $st = $conexion->prepare("SELECT Id_Usuario FROM Usuario WHERE Correo = ?");
    $st->bind_param("s", $c);
    $st->execute();
    if ($st->get_result()->num_rows > 0) {
        $err = "Este correo ya está en uso.";
    } else {
        // ins
        $st2 = $conexion->prepare("INSERT INTO Usuario (Id_Restaurante, Correo, Contrasena, Rol) VALUES (?, ?, ?, ?)");
        $st2->bind_param("isss", $id_rest, $c, $p, $r);
        if ($st2->execute()) {
            $msg = "Cuenta de $r creada con éxito.";
        } else {
            $err = "Error al crear cuenta.";
        }
    }
}

// pull data
$equipo = [];
$q = $conexion->query("SELECT * FROM Usuario WHERE Id_Restaurante = $id_rest ORDER BY Rol ASC");
if($q) while($row = $q->fetch_assoc()) $equipo[] = $row;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Getrest - Mi Equipo</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=Playfair+Display:ital,wght@0,400;0,600;1,400&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root { --crimson: #990616; --cream: #F1E9C6; --tan: #A18E5E; --charcoal: #474747; }
        body { font-family: 'DM Sans', sans-serif; background-color: var(--cream); margin: 0; }
        .sidebar-item { background: transparent; color: #F1E9C688; }
        .sidebar-item:hover { background: #ffffff08; }
        .sidebar-item.active { background: var(--crimson); color: var(--cream); }
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

        <div class="flex-1 flex flex-col min-w-0" style="background: var(--cream);">
            
            <header class="flex items-center justify-between px-8 py-4 shrink-0" style="background: var(--charcoal); border-bottom: 1px solid #ffffff10;">
                <div class="flex items-center gap-2 text-sm" style="color: #F1E9C655;">
                    <span>Configuración</span>
                    <i data-lucide="chevron-right" class="w-3 h-3"></i>
                    <span style="color: var(--cream);">Mi Equipo</span>
                </div>
                <div class="flex items-center gap-4">
                    <div class="flex items-center gap-2.5">
                        <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-medium" style="background: var(--tan); color: var(--cream);">
                            <?= $iniciales ?>
                        </div>
                        <span class="text-xs" style="color: #F1E9C688;"><?= $rol_usuario ?></span>
                    </div>
                </div>
            </header>

            <main class="flex-1 overflow-y-auto p-8 flex flex-col lg:flex-row gap-8">
                
                <div class="flex-1">
                    <h1 class="mb-6" style="font-family: 'Playfair Display', serif; font-size: 1.8rem; font-weight: 400; color: var(--charcoal); line-height: 1.1;">
                        Equipo de Trabajo
                    </h1>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                        <?php foreach($equipo as $u): ?>
                            <div class="bg-white p-5 rounded-2xl border border-[#47474712] shadow-sm flex items-center gap-4 transition-transform hover:-translate-y-px">
                                <div class="w-11 h-11 rounded-full flex items-center justify-center text-white shrink-0" style="background: <?= strtolower($u['Rol']) == 'gerente' ? 'var(--crimson)' : 'var(--tan)' ?>;">
                                    <i data-lucide="<?= strtolower($u['Rol']) == 'gerente' ? 'shield' : 'user' ?>" class="w-5 h-5"></i>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm font-bold text-[#474747] truncate" title="<?= htmlspecialchars($u['Correo']) ?>"><?= htmlspecialchars($u['Correo']) ?></p>
                                    <p class="text-[10px] uppercase text-[#47474788] tracking-wider mt-0.5"><?= htmlspecialchars($u['Rol']) ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="w-full lg:w-96 bg-white p-6 rounded-2xl border border-[#47474712] shadow-sm h-fit shrink-0">
                    <div class="flex items-center gap-2 mb-6">
                        <i data-lucide="user-plus" class="w-4 h-4 text-[#A18E5E]"></i>
                        <h3 class="text-sm font-bold uppercase tracking-wider text-[#474747]">Agregar Empleado</h3>
                    </div>
                    
                    <?php if($err): ?>
                        <div class="mb-4 p-3 rounded-xl bg-red-50 border border-red-100 text-red-700 text-xs flex items-center gap-2">
                            <i data-lucide="alert-circle" class="w-4 h-4 text-red-500 shrink-0"></i><?= $err ?>
                        </div>
                    <?php endif; ?>
                    <?php if($msg): ?>
                        <div class="mb-4 p-3 rounded-xl bg-green-50 border border-green-100 text-green-700 text-xs flex items-center gap-2">
                            <i data-lucide="check-circle" class="w-4 h-4 text-green-500 shrink-0"></i><?= $msg ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="flex flex-col gap-4">
                        <div>
                            <label class="text-[10px] uppercase text-[#47474788] font-medium">Correo electrónico</label>
                            <input type="email" name="correo" placeholder="empleado@correo.com" required class="w-full bg-[#F9FAFB] border border-[#E5E7EB] px-4 py-3 rounded-xl text-sm mt-1.5 outline-none focus:border-[#A18E5E] transition-colors text-[#474747]">
                        </div>
                        <div>
                            <label class="text-[10px] uppercase text-[#47474788] font-medium">Contraseña Temporal</label>
                            <input type="text" name="password" placeholder="Min. 6 caracteres" required class="w-full bg-[#F9FAFB] border border-[#E5E7EB] px-4 py-3 rounded-xl text-sm mt-1.5 outline-none focus:border-[#A18E5E] transition-colors text-[#474747]">
                        </div>
                        <div>
                            <label class="text-[10px] uppercase text-[#47474788] font-medium">Rol de sistema</label>
                            <div class="relative">
                                <select name="rol" required class="w-full bg-[#F9FAFB] border border-[#E5E7EB] px-4 py-3 rounded-xl text-sm mt-1.5 outline-none focus:border-[#A18E5E] transition-colors text-[#474747] appearance-none cursor-pointer">
                                    <option value="chef">Chef de Cocina (Recetas e Inv.)</option>
                                    <option value="inventorista">Encargado de Almacén (Solo Inv.)</option>
                                </select>
                                <i data-lucide="chevron-down" class="absolute right-4 top-1/2 -translate-y-1/2 w-4 h-4 text-[#47474755] pointer-events-none mt-0.5"></i>
                            </div>
                        </div>
                        <button type="submit" class="w-full py-3.5 rounded-xl text-sm font-medium text-white mt-4 transition-all shadow-md hover:shadow-lg transform hover:-translate-y-px" style="background: var(--charcoal);">
                            Crear Cuenta
                        </button>
                    </form>
                </div>

            </main>
        </div>
    </div>
    
    <script>
        lucide.createIcons();
        
        // tl side
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
    </script>
</body>
</html>