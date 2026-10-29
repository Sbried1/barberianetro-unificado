<?php
require_once __DIR__ . '/comun.php';

$error = $_GET["error"] ?? "";
$error_password = ($error === "password");
$error_email = ($error === "email");
$mensajes_error = [
    "datos"   => "Revisá los datos: nombre y apellido solo con letras (máximo 30) y un correo válido.",
    "corta"   => "La contraseña debe tener al menos 8 caracteres.",
    "sesion"  => "El formulario venció. Volvé a intentarlo.",
    "guardar" => "No se pudo crear la cuenta. Intentá de nuevo.",
];
?>


<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrarse - Netro</title>
    <link rel="stylesheet" href="../css/header.css">
    <link rel="stylesheet" href="../css/body.css">
    <link rel="stylesheet" href="../css/login.css">
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
                <h2>Registrarse</h2>
            </div>
            <div class="body_login">
                <form method="POST" action="registrar_cliente.php">
                    <?= csrf_campo() ?>
                    <?php if (isset($mensajes_error[$error])): ?>
                        <p class="mensaje-error"><?= e($mensajes_error[$error]) ?></p>
                    <?php endif; ?>
                    <p>Nombre <span class="requerido">*</span></p>
                    <input type="text" name="nombre" class="entrada" maxlength="30" required>
                    <p>Apellido <span class="requerido">*</span></p>
                    <input type="text" name="apellido" class="entrada" maxlength="30" required>
                    <p>Correo <span class="requerido">*</span></p>
                    <input type="email" name="correo" class="entrada <?php echo $error_email ? 'error' : ''; ?>" maxlength="150" required>
                    <?php if ($error_email): ?>
                        <p class="mensaje-error">Este correo ya esta en uso</p>
                    <?php endif; ?>
                    <p>Contraseña <span class="requerido">*</span></p>
                    <input type="password" name="passw" class="entrada" minlength="8" required>
                    <p>Confirmar contraseña <span class="requerido">*</span></p>
                    <input type="password" name="confirm_passw" class="entrada <?php echo $error_password ? 'error' : ''; ?>" minlength="8" required>

                    <?php if ($error_password): ?>
                        <p class="mensaje-error">Las contraseñas no coinciden</p>
                    <?php endif; ?>

                    <button type="submit" class="boton_login">Continuar</button>
                    <div id="enlaces">
                        <a href="login.php">¿Ya tienes una cuenta? Iniciar Sesión</a>
                    </div>
                </form>
            </div>
        </div>
    </main>
</body>
</html>