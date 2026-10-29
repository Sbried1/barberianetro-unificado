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

$nombre = post_texto("nombre");
$apellido = post_texto("apellido");
$email = post_texto("email");

$errores = false;

// Nombre y apellido: obligatorios, solo letras/espacios, largo razonable
if (
    $nombre === "" || $apellido === "" ||
    mb_strlen($nombre) > 30 || mb_strlen($apellido) > 30 ||
    !preg_match("/^[\p{L}\s]+$/u", $nombre) ||
    !preg_match("/^[\p{L}\s]+$/u", $apellido)
) {
    $errores = true;
}

// Email: obligatorio y con formato válido (la columna es varchar(150))
if ($email === "" || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
    $errores = true;
}

if ($errores) {
    volver("error=invalido");
}

// =====================================================
// DUPLICADOS + INSERCIÓN
// "email" es UNIQUE en la base. Se verifica antes para dar un mensaje
// claro y, además, se captura el error 1062 por si dos altas con el mismo
// email llegan al mismo tiempo.
// =====================================================

$errno = 0;

try {

    $sql_verificar = "SELECT id_cliente FROM cliente WHERE email = ? LIMIT 1";
    $stmt_verificar = mysqli_prepare($conexion, $sql_verificar);
    mysqli_stmt_bind_param($stmt_verificar, "s", $email);
    mysqli_stmt_execute($stmt_verificar);
    $resultado_verificar = mysqli_stmt_get_result($stmt_verificar);
    $ya_existe = mysqli_num_rows($resultado_verificar) > 0;
    mysqli_stmt_close($stmt_verificar);

    if ($ya_existe) {
        volver("error=duplicado");
    }

    // La columna "contrasena" es obligatoria, pero estos clientes se cargan
    // desde el panel, no se registran solos. Se genera una clave aleatoria
    // que NADIE conoce: solo se guarda su hash y la clave en texto plano se
    // descarta enseguida (no se muestra, no se guarda y no se registra).
    $contrasena_plana = bin2hex(random_bytes(32));
    $contrasena_hash = password_hash($contrasena_plana, PASSWORD_DEFAULT);
    unset($contrasena_plana);

    // id_cliente es AUTO_INCREMENT: no se envía.
    $sql_insertar = "INSERT INTO cliente (nombre, apellido, email, contrasena) VALUES (?, ?, ?, ?)";
    $stmt_insertar = mysqli_prepare($conexion, $sql_insertar);
    mysqli_stmt_bind_param($stmt_insertar, "ssss", $nombre, $apellido, $email, $contrasena_hash);

    if (!mysqli_stmt_execute($stmt_insertar)) {
        $errno = mysqli_stmt_errno($stmt_insertar) ?: -1;
    }

    mysqli_stmt_close($stmt_insertar);

} catch (Throwable $e) {
    $errno = ($e instanceof mysqli_sql_exception) ? (int) $e->getCode() : -1;

    if ($errno !== 1062) {
        // Solo código y mensaje del error: nunca se registran datos del formulario.
        error_log("agregar_cliente.php [" . $errno . "]: " . $e->getMessage());
    }
}

if ($errno === 1062) {
    volver("error=duplicado");
}

if ($errno !== 0) {
    volver("error=bd");
}

volver("ok=agregado");
