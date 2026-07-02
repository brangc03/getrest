<?php
// conf inc
session_start();
require(__DIR__ . "/conexion.php");

if (isset($_SESSION['usuario'])) {
    header("Location: /getrest/dashboard.php");
    exit();
}

$error = "";

// eval auth
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $correo = $_POST['correo'];
    $password = $_POST['password'];

    // map acc bd
    $stmt = $conexion->prepare("SELECT Id_Usuario, Rol, Id_Restaurante FROM Usuario WHERE Correo = ? AND Contrasena = ?");
    $stmt->bind_param("ss", $correo, $password);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows > 0) {
        $usuario = $resultado->fetch_assoc();
        
        // set acc vars
        $_SESSION['usuario'] = $usuario['Id_Usuario'];
        $_SESSION['rol'] = strtolower($usuario['Rol']);
        $_SESSION['restaurante'] = $usuario['Id_Restaurante']; // AQUI ESTA LA MAGIA: El id del workspace se carga automático
        
        header("Location: /getrest/dashboard.php");
        exit();
    } else {
        $error = "Correo o contraseña incorrectos.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Getrest - Iniciar Sesión</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;700&family=Playfair+Display:ital,wght@0,400;0,600;1,400&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        .animate-fade-in { animation: fadeIn 1.2s ease-out forwards; opacity: 0; }
        .animate-fade-in-up { animation: fadeInUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards; opacity: 0; }
        .delay-100 { animation-delay: 100ms; }
        .delay-200 { animation-delay: 200ms; }
    </style>
</head>
<body>
    <div class="h-screen w-full flex" style="font-family: 'DM Sans', sans-serif;">
        <div class="hidden lg:flex flex-col justify-center w-[52%] p-14 relative overflow-hidden" style="background: #990616;">
            <div class="absolute inset-0 animate-fade-in" style="background-image: linear-gradient(#7A0512 1px, transparent 1px), linear-gradient(90deg, #7A0512 1px, transparent 1px); background-size: 48px 48px;"></div>
            
            <svg class="absolute bottom-0 right-0 animate-fade-in" width="420" height="420" viewBox="0 0 420 420" fill="none">
                <circle cx="420" cy="420" r="300" stroke="#7A0512" stroke-width="1" />
                <circle cx="420" cy="420" r="200" stroke="#7A0512" stroke-width="1" />
                <circle cx="420" cy="420" r="100" stroke="#7A0512" stroke-width="1" />
            </svg>

            <div class="relative z-10 mb-auto mt-4 animate-fade-in">
                <span class="text-sm tracking-widest uppercase font-light" style="color: #F1E9C699;">GetRest</span>
            </div>

            <div class="relative z-10 mb-32 animate-fade-in">
                <p class="text-xs tracking-widest uppercase mb-8" style="color: #F1E9C655;">Bienvenido de nuevo</p>
                <h1 class="leading-[1.15] mb-6" style="font-family: 'Playfair Display', serif; font-size: clamp(2.5rem, 4vw, 3.5rem); font-weight: 400; color: #F1E9C6;">
                    Organiza, controla<br>y <em style="color: #A18E5E;">crece</em>
                </h1>
            </div>
        </div>

        <div class="flex-1 flex items-center justify-center p-8" style="background-color: #F4EED1;">
            <div class="w-full max-w-sm">
                <div class="animate-fade-in-up">
                    <div class="flex items-center gap-3 mb-12 lg:hidden">
                        <div class="w-8 h-8 rounded-sm flex items-center justify-center" style="background: #4F9150;">
                            <span style="font-family: 'Playfair Display', serif; color: #F1E9C6; font-weight: 600; font-size: 14px;">G</span>
                        </div>
                        <span class="text-sm tracking-widest uppercase font-light" style="color: #47474799;">GetRest</span>
                    </div>

                    <p class="text-xs tracking-widest uppercase mb-3" style="color: #47474766;">Iniciar sesión</p>
                    <h2 class="mb-10" style="font-family: 'Playfair Display', serif; font-size: 1.75rem; font-weight: 400; color: #474747;">
                        Tu colección te espera.
                    </h2>

                    <?php if ($error): ?>
                        <div class="mb-6 p-3 rounded-md bg-red-100 text-red-700 text-sm font-medium border border-red-200">
                            <?= $error ?>
                        </div>
                    <?php endif; ?>
                </div>

                <form method="POST" class="flex flex-col gap-5 animate-fade-in-up delay-100">
                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs tracking-wide uppercase font-medium" style="color: #47474788;">Correo electrónico</label>
                        <div class="relative rounded-lg transition-all duration-200 border-[1.5px] border-[#47474722] bg-[#F1E9C6cc] focus-within:border-[#A18E5E] focus-within:bg-[#fffdf4]">
                            <input type="email" name="correo" placeholder="tu@correo.com" required class="w-full bg-transparent px-4 py-3.5 text-sm outline-none" style="color: #474747; font-family: 'DM Sans', sans-serif;">
                        </div>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <div class="flex items-center justify-between">
                            <label class="text-xs tracking-wide uppercase font-medium" style="color: #47474788;">Contraseña</label>
                            <button type="button" class="text-xs transition-colors" style="color: #47474766; font-family: 'DM Sans', sans-serif;">¿Olvidaste tu contraseña?</button>
                        </div>
                        <div class="relative rounded-lg transition-all duration-200 border-[1.5px] border-[#47474722] bg-[#F1E9C6cc] focus-within:border-[#A18E5E] focus-within:bg-[#fffdf4]">
                            <input type="password" id="password" name="password" placeholder="••••••••••" required class="w-full bg-transparent px-4 py-3.5 pr-12 text-sm outline-none" style="color: #474747; font-family: 'DM Sans', sans-serif;">
                            
                            <button type="button" onclick="const p = document.getElementById('password'); p.type = p.type === 'password' ? 'text' : 'password'; document.getElementById('eye-icon').classList.toggle('hidden'); document.getElementById('eye-off-icon').classList.toggle('hidden');" class="absolute right-4 top-1/2 -translate-y-1/2 transition-colors" style="color: #47474755;">
                                <svg id="eye-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>
                                <svg id="eye-off-icon" class="hidden" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" y1="2" x2="22" y2="22"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <label class="flex items-center gap-3 cursor-pointer group">
                        <div class="relative">
                            <input type="checkbox" name="recordar" class="sr-only peer">
                            <div class="w-4 h-4 rounded transition-all duration-150 peer-checked:opacity-0" style="border: 1.5px solid #47474733;"></div>
                            <div class="absolute inset-0 rounded scale-0 peer-checked:scale-100 transition-transform duration-150 flex items-center justify-center" style="background: #A18E5E;">
                                <svg width="8" height="6" viewBox="0 0 8 6" fill="none">
                                    <path d="M1 3L3 5L7 1" stroke="#F1E9C6" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </div>
                        </div>
                        <span class="text-xs select-none transition-colors" style="color: #47474777;">Mantenerme conectado 30 días</span>
                    </label>

                    <button type="submit" class="relative mt-2 w-full py-3.5 rounded-lg text-sm font-medium flex items-center justify-center gap-2 transition-all duration-200 overflow-hidden group" style="background: #990616; color: #F1E9C6; font-family: 'DM Sans', sans-serif;">
                        <span class="absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity duration-200" style="background: #7a0512;"></span>
                        <span class="relative flex items-center gap-2">
                            Entrar
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>
                            </svg>
                        </span>
                    </button>
                </form>

                <p class="text-center text-xs mt-8 animate-fade-in-up delay-200" style="color: #47474755;">
                    ¿No tienes cuenta? <a href="/getrest/registro.php" class="underline underline-offset-2 transition-colors" style="color: #990616; font-family: 'DM Sans', sans-serif;"><span style="font-size: 0.7rem;">Registrarme</span></a>
                </p>
            </div>
        </div>
    </div>
</body>
</html>