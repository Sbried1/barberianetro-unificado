<?php

require_once __DIR__ . '/comun.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

if (!csrf_valido()) {
    header('Location: login.php?error=sesion');
    exit;
}

$correo = post_texto('correo');
$password = isset($_POST['pass']) && is_string($_POST['pass']) ? $_POST['pass'] : '';

$sql = "SELECT id_cliente, email, nombre, apellido, contrasena
        FROM cliente
        WHERE email = ?";

$stmt = mysqli_prepare($conexion, $sql);
mysqli_stmt_bind_param($stmt, "s", $correo);
mysqli_stmt_execute($stmt);
$usuario = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

// Mismo mensaje si el correo no existe o si la contraseña no coincide,
// así no se puede averiguar qué correos están registrados.
if (!$usuario || !password_verify($password, $usuario["contrasena"])) {
    header('Location: login.php?error=credenciales');
    exit;
}

// Nuevo id de sesión al iniciar sesión (evita la fijación de sesión).
session_regenerate_id(true);

$_SESSION["id_cliente"] = (int) $usuario["id_cliente"];
$_SESSION["email"] = $usuario["email"];
$_SESSION["nombre"] = $usuario["nombre"];
$_SESSION["apellido"] = $usuario["apellido"];

header("Location: perfil.php");
exit;
