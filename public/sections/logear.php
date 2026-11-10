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

$sql = "SELECT id_cliente, email, nombre, apellido, contrasena,
            intentos_fallidos, bloqueado_hasta
        FROM cliente
        WHERE email = ?";

$stmt = mysqli_prepare($conexion, $sql);
mysqli_stmt_bind_param($stmt, "s", $correo);
mysqli_stmt_execute($stmt);

$usuario = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

// Si el usuario existe, comprobar bloqueo.
if ($usuario) {

    if ($usuario['bloqueado_hasta'] !== null) {

        $ahora = new DateTime();
        $bloqueo = new DateTime($usuario['bloqueado_hasta']);

        if ($ahora < $bloqueo) {
            header('Location: login.php?error=bloqueado');
            exit;
        }

        // El tiempo de bloqueo terminó.
        $stmt = mysqli_prepare(
            $conexion,
            "UPDATE cliente
            SET intentos_fallidos = 0, bloqueado_hasta = NULL
            WHERE id_cliente = ?"
        );

        mysqli_stmt_bind_param($stmt, "i", $usuario['id_cliente']);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $usuario['intentos_fallidos'] = 0;
    }

}

// Comprobar credenciales.
if (!$usuario || !password_verify($password, $usuario["contrasena"])) {

    if ($usuario) {

        $intentos = $usuario['intentos_fallidos'] + 1;

        if ($intentos >= 5) {

            $stmt = mysqli_prepare(
                $conexion,
                "UPDATE cliente
                SET intentos_fallidos = 0,
                    bloqueado_hasta = DATE_ADD(NOW(), INTERVAL 1 MINUTE)
                WHERE id_cliente = ?"
            );

            mysqli_stmt_bind_param($stmt, "i", $usuario['id_cliente']);

            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            header('Location: login.php?error=bloqueado');
            exit;

        } else {

            $stmt = mysqli_prepare(
                $conexion,
                "UPDATE cliente
                SET intentos_fallidos = ?
                WHERE id_cliente = ?"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "ii",
                $intentos,
                $usuario['id_cliente']
            );

            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }

    header('Location: login.php?error=credenciales');
    exit;
}

// Contraseña correcta: reiniciar intentos.
$stmt = mysqli_prepare(
    $conexion,
    "UPDATE cliente
    SET intentos_fallidos = 0, bloqueado_hasta = NULL
    WHERE id_cliente = ?"
);

mysqli_stmt_bind_param($stmt, "i", $usuario['id_cliente']);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

// Nuevo id de sesión al iniciar sesión.
session_regenerate_id(true);

$_SESSION["id_cliente"] = (int) $usuario["id_cliente"];
$_SESSION["email"] = $usuario["email"];
$_SESSION["nombre"] = $usuario["nombre"];
$_SESSION["apellido"] = $usuario["apellido"];

header("Location: perfil.php");
exit;