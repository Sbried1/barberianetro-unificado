<?php

require_once __DIR__ . '/comun.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: register.php');
    exit;
}

if (!csrf_valido()) {
    header('Location: register.php?error=sesion');
    exit;
}

$nombre = post_texto('nombre');
$apellido = post_texto('apellido');
$correo = post_texto('correo');
$password = $_POST['passw'] ?? '';
$confirm_passw = $_POST['confirm_passw'] ?? '';

if (!is_string($password) || !is_string($confirm_passw)) {
    header('Location: register.php?error=datos');
    exit;
}

if (!nombre_valido($nombre) || !nombre_valido($apellido) || !email_valido($correo)) {
    header('Location: register.php?error=datos');
    exit;
}

if (strlen($password) < 8) {
    header('Location: register.php?error=corta');
    exit;
}

if ($confirm_passw !== $password) {
    header('Location: register.php?error=password');
    exit;
}

$password_hash = password_hash($password, PASSWORD_DEFAULT);

$sql = "INSERT INTO cliente (email, nombre, apellido, contrasena)
        VALUES (?, ?, ?, ?)";

$stmt = mysqli_prepare($conexion, $sql);

mysqli_stmt_bind_param(
    $stmt,
    "ssss",
    $correo,
    $nombre,
    $apellido,
    $password_hash
);

try {
    mysqli_stmt_execute($stmt);
} catch (mysqli_sql_exception $e) {
    mysqli_stmt_close($stmt);

    if ($e->getCode() === 1062) {
        header('Location: register.php?error=email');
        exit;
    }

    // El detalle queda en el log del servidor, no en pantalla.
    error_log('registrar_cliente: ' . $e->getMessage());
    header('Location: register.php?error=guardar');
    exit;
}

mysqli_stmt_close($stmt);

header('Location: login.php?registrado=1');
exit;
