<?php
require_once __DIR__ . '/comun.php';
require_once __DIR__ . '/../mailer.php';

$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_valido()) {
    $mensaje = 'El formulario venció. Volvé a intentarlo.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo = post_texto('correo');

    $stmt = $conexion->prepare("SELECT id_cliente, nombre FROM cliente WHERE email = ?");
    $stmt->bind_param("s", $correo);
    $stmt->execute();
    $cliente = $stmt->get_result()->fetch_assoc();

    if ($cliente) {
        $token = bin2hex(random_bytes(32));
        $hash  = hash('sha256', $token);

        // Un enlace nuevo anula los que se hayan pedido antes.
        $stmt = $conexion->prepare("UPDATE password_resets SET usado = 1 WHERE id_cliente = ? AND usado = 0");
        $stmt->bind_param("i", $cliente['id_cliente']);
        $stmt->execute();

        $stmt = $conexion->prepare(
            "INSERT INTO password_resets (id_cliente, token_hash, expira)
            VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))"
        );
        $stmt->bind_param("is", $cliente['id_cliente'], $hash);
        $stmt->execute();

        // APP_URL viene del .env (Docker). Si no está (por ejemplo en XAMPP),
        // se usa la dirección con la que se abrió esta página.
        $base = rtrim((string) getenv('APP_URL'), '/');
        if ($base === '') {
            $esquema = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            // En Windows dirname() puede devolver "\", por eso se normaliza.
            $carpeta = str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'])));
            $base = $esquema . '://' . $_SERVER['HTTP_HOST'] . rtrim($carpeta, '/');
        }
        $enlace = $base . '/sections/restablecer.php?token=' . $token;
        $nombre = htmlspecialchars($cliente['nombre']);

        enviarCorreo($correo, $cliente['nombre'], 'Recupera tu contraseña',
            "<p>Hola $nombre,</p>
            <p>Haz clic para crear una nueva contraseña (válido por 1 hora):</p>
            <p><a href='$enlace'>Restablecer contraseña</a></p>
            <p>Si no lo solicitaste, ignora este correo.</p>");
    }

    // Mismo mensaje exista o no el correo
    $mensaje = 'Si el correo está registrado, recibirás un enlace en unos minutos.';
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Olvidé mi contraseña - Netro</title>
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
                <h2>Recuperar contraseña</h2>
            </div>
            <div class="body_login">
                <form method="POST">
                    <?= csrf_campo() ?>
                    <p>Correo</p>
                    <input type="email" name="correo" class="entrada" required>
                    <?php if($mensaje):?>
                        <p class="msg"><i class="ph ph-envelope-simple"></i> <?= htmlspecialchars($mensaje) ?></p>
                    <?php endif; ?>
                    <button type="submit" class="boton_login">Continuar</button>
                    <div id="enlaces">
                        <a href="register.php">Registrarse</a>
                        <a href="login.php">¿Ya tienes una cuenta? Iniciar Sesión</a>
                    </div>
                </form>
            </div>
        </div>
    </main>
</body>
</html>