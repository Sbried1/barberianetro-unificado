<?php
require_once __DIR__ . '/comun.php';

$token = $_GET['token'] ?? $_POST['token'] ?? '';
$token = is_string($token) ? $token : '';
$hash  = hash('sha256', $token);
$error = '';

$stmt = $conexion->prepare(
    "SELECT id, id_cliente FROM password_resets
    WHERE token_hash = ? AND usado = 0 AND expira > NOW()"
);
$stmt->bind_param("s", $hash);
$stmt->execute();
$reset = $stmt->get_result()->fetch_assoc();

if (!$reset) {
    exit('El enlace no es válido o ya expiró.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nueva   = isset($_POST['nueva']) && is_string($_POST['nueva']) ? $_POST['nueva'] : '';
    $repetir = isset($_POST['repetir']) && is_string($_POST['repetir']) ? $_POST['repetir'] : '';

    if (!csrf_valido()) {
        $error = 'El formulario venció. Volvé a intentarlo.';
    } elseif (strlen($nueva) < 8) {
        $error = 'La contraseña debe tener al menos 8 caracteres.';
    } elseif ($nueva !== $repetir) {
        $error = 'Las contraseñas no coinciden.';
    } else {
        $passHash = password_hash($nueva, PASSWORD_DEFAULT);

        // Contraseña nueva y enlaces usados, todo junto o nada.
        $conexion->begin_transaction();
        try {
            $stmt = $conexion->prepare("UPDATE cliente SET contrasena = ? WHERE id_cliente = ?");
            $stmt->bind_param("si", $passHash, $reset['id_cliente']);
            $stmt->execute();

            // Se anulan este enlace y cualquier otro pendiente del cliente.
            $stmt = $conexion->prepare("UPDATE password_resets SET usado = 1 WHERE id_cliente = ? AND usado = 0");
            $stmt->bind_param("i", $reset['id_cliente']);
            $stmt->execute();

            $conexion->commit();
        } catch (mysqli_sql_exception $e) {
            $conexion->rollback();
            error_log('restablecer.php: ' . $e->getMessage());
            exit('No se pudo cambiar la contraseña. Intentá de nuevo.');
        }

        header('Location: login.php');
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="referrer" content="no-referrer">
    <title>Cambiar contraseña - Netro</title>
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
                <h2>Cambiar contraseña</h2>
            </div>
            <div class="body_login">
                <form method="post">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="token" value="<?= e($token) ?>">
                    <p>Nueva contraseña</p>
                    <input type="password" name="nueva" required class="entrada" minlength="8">
                    <p>Confirmar contraseña</p>
                    <input type="password" name="repetir" required class="entrada" minlength="8">
                    <p class="mensaje-error"><?= htmlspecialchars($error) ?></p>
                    <button type="submit" class="boton_login boton_restablecer_passwd">Guardar</button>
                </form>
            </div>
        </div>
    </main>
</body>
</html>