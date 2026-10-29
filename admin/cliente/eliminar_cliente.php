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

// id_cliente es un int autoincremental: debe ser un entero positivo.
$id_cliente = filter_var($_POST["id_cliente"] ?? null, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);

if ($id_cliente === false) {
    volver("error=no_existe");
}

// =====================================================
// ELIMINAR CLIENTE
// Si el cliente tiene reservas (fk_reserva_cliente) o ventas
// (fk_venta_cliente), MySQL rechaza el borrado con el error 1451. Se
// informa al usuario y NO se elimina nada más: ni reservas ni ventas.
// El código funciona tanto si mysqli lanza excepciones (PHP 8.1+ por
// defecto) como si devuelve false.
// =====================================================

$errno = 0;
$filas_afectadas = 0;

try {

    $sql_eliminar = "DELETE FROM cliente WHERE id_cliente = ?";
    $stmt_eliminar = mysqli_prepare($conexion, $sql_eliminar);
    mysqli_stmt_bind_param($stmt_eliminar, "i", $id_cliente);

    if (mysqli_stmt_execute($stmt_eliminar)) {
        $filas_afectadas = mysqli_stmt_affected_rows($stmt_eliminar);
    } else {
        $errno = mysqli_stmt_errno($stmt_eliminar) ?: -1;
    }

    mysqli_stmt_close($stmt_eliminar);

} catch (Throwable $e) {
    $errno = ($e instanceof mysqli_sql_exception) ? (int) $e->getCode() : -1;

    if ($errno !== 1451) {
        error_log("eliminar_cliente.php [" . $errno . "]: " . $e->getMessage());
    }
}

if ($errno === 1451) {
    volver("error=restriccion");
}

if ($errno !== 0) {
    volver("error=bd");
}

if ($filas_afectadas === 0) {
    volver("error=no_existe");
}

volver("ok=eliminado");
