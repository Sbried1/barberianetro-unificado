<?php

require_once __DIR__ . '/../auth/guard.php';
auth_requerir('clientes');

include("../conexion.php");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: clientes.php");
    exit;
}

// Vuelve al listado con un parámetro de resultado (?error=... / ?ok=...).
function volver($parametro) {
    header("Location: clientes.php?" . $parametro);
    exit;
}

// Lee un campo POST como texto. Si llega algo que no es un string (por
// ejemplo nombre[]=x) lo trata como vacío en lugar de provocar un error.
function post_texto($clave) {
    $valor = $_POST[$clave] ?? "";
    return is_string($valor) ? trim($valor) : "";
}

// =====================================================
// SANITIZACIÓN Y VALIDACIÓN
// =====================================================

// id_cliente es un int autoincremental: debe ser un entero positivo.
$id_cliente = filter_var($_POST["id_cliente"] ?? null, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
$nombre = post_texto("nombre");
$apellido = post_texto("apellido");
$email = post_texto("email");

$errores = false;

if ($id_cliente === false) {
    $errores = true;
}

if (
    $nombre === "" || $apellido === "" ||
    mb_strlen($nombre) > 30 || mb_strlen($apellido) > 30 ||
    !preg_match("/^[\p{L}\s]+$/u", $nombre) ||
    !preg_match("/^[\p{L}\s]+$/u", $apellido)
) {
    $errores = true;
}

if ($email === "" || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
    $errores = true;
}

if ($errores) {
    volver("error=invalido");
}

// =====================================================
// EXISTENCIA + DUPLICADOS + ACTUALIZACIÓN
// "email" es UNIQUE en la base. Se verifica antes para dar un mensaje
// claro y, además, se captura el error 1062 por si dos cambios con el
// mismo email llegan al mismo tiempo.
// =====================================================

$errno = 0;

try {

    // El cliente debe existir
    $sql_existe = "SELECT id_cliente FROM cliente WHERE id_cliente = ?";
    $stmt_existe = mysqli_prepare($conexion, $sql_existe);
    mysqli_stmt_bind_param($stmt_existe, "i", $id_cliente);
    mysqli_stmt_execute($stmt_existe);
    $existe = mysqli_num_rows(mysqli_stmt_get_result($stmt_existe)) > 0;
    mysqli_stmt_close($stmt_existe);

    if (!$existe) {
        volver("error=no_existe");
    }

    // El email no puede pertenecer a otro cliente (se excluye el actual)
    $sql_verificar = "SELECT id_cliente FROM cliente WHERE email = ? AND id_cliente != ? LIMIT 1";
    $stmt_verificar = mysqli_prepare($conexion, $sql_verificar);
    mysqli_stmt_bind_param($stmt_verificar, "si", $email, $id_cliente);
    mysqli_stmt_execute($stmt_verificar);
    $duplicado = mysqli_num_rows(mysqli_stmt_get_result($stmt_verificar)) > 0;
    mysqli_stmt_close($stmt_verificar);

    if ($duplicado) {
        volver("error=duplicado");
    }

    // Solo nombre, apellido y email: la columna "contrasena" NO se toca.
    $sql_actualizar = "UPDATE cliente SET nombre = ?, apellido = ?, email = ? WHERE id_cliente = ?";
    $stmt_actualizar = mysqli_prepare($conexion, $sql_actualizar);
    mysqli_stmt_bind_param($stmt_actualizar, "sssi", $nombre, $apellido, $email, $id_cliente);

    if (!mysqli_stmt_execute($stmt_actualizar)) {
        $errno = mysqli_stmt_errno($stmt_actualizar) ?: -1;
    }

    mysqli_stmt_close($stmt_actualizar);

} catch (Throwable $e) {
    $errno = ($e instanceof mysqli_sql_exception) ? (int) $e->getCode() : -1;

    if ($errno !== 1062) {
        error_log("actualizar_cliente.php [" . $errno . "]: " . $e->getMessage());
    }
}

if ($errno === 1062) {
    volver("error=duplicado");
}

if ($errno !== 0) {
    volver("error=bd");
}

volver("ok=actualizado");
