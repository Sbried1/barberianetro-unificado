<?php
require_once __DIR__ . '/comun.php';
session_check();

$error = $_GET["error"] ?? "";
$error_credenciales = ($error === "credenciales");
$error_sesion = ($error === "sesion");
$error_bloqueado = ($error === "bloqueado");
?>


<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesion - Netro</title>
    <link rel="stylesheet" href="../css/header.css">
    <link rel="stylesheet" href="../css/body.css">
    <link rel="stylesheet" href="../css/login.css">
    <link rel="stylesheet" href="../css/spinner.css">
    <link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/regular/style.css">
    <link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/fill/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,200..800;1,200..800&display=swap" rel="stylesheet">
</head>
<header class="header">
    <p id="Titulo">Barbería Netro</p>
    <div class="contenedor_botones">
        <a href="index.php" class="boton">Inicio</a>
        <a href="reserva.php" class="boton">Reserva</a>
        <a href="perfil.php" class="perfil" title="Perfil"><i class="ph ph-user-circle"></i></a>
    </div>
</header>
<body>
    <main>
        <div id="fondo"></div>
        <div class="caja_login">
            <div class="header_login">
                <h2>Iniciar sesión</h2>
            </div>
            <div class="body_login">
                <form method="POST" action="logear.php">
                    <?= csrf_campo() ?>
                    <?php if (isset($_GET["registrado"])): ?>
                        <p class="msg">Tu cuenta fue creada. Ya podés iniciar sesión.</p>
                    <?php endif ?>
                    <p>Correo</p>
                    <input type="email" name="correo" class="entrada <?php echo $error_credenciales ? 'error' : ''; ?>" required>
                    <p>Contraseña</p>
                    <input type="password" name="pass" class="entrada <?php echo $error_credenciales ? 'error' : ''; ?>" required>
                    <?php if ($error_credenciales): ?>
                        <p class="mensaje-error">Correo o contraseña incorrectos</p>
                    <?php endif ?>
                    <?php if ($error_sesion): ?>
                        <p class="mensaje-error">El formulario venció. Volvé a intentarlo.</p>
                    <?php endif ?>
                    <?php if ($error_bloqueado): ?>
                        <p class="mensaje-error">
                            Demasiados intentos. Vuelve a intentarlo en 1 minuto.
                        </p>
                    <?php endif ?>
                    <button type="submit" class="boton_login" id="boton_continuar">
                        <span class="btn_texto">Iniciar sesión</span>
                        <span class="spinner"></span>
                    </button>
                    <div id="enlaces">
                        <a href="register.php">Registrarse</a>
                        <a href="forgot_password.php">Olvidé mi contraseña</a>
                    </div>
                </form>
            </div>
        </div>
    </main>
</body>
<script>
    document.querySelector("form").addEventListener("submit", function () {
        const boton = this.querySelector('button[type="submit"]');
        boton.classList.add("loading");
        boton.disabled = true;
    });
</script>
</html>