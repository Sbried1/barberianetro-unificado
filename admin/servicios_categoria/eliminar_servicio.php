<?php

require_once __DIR__ . '/../auth/guard.php';
auth_requerir('servicios');

include("../conexion.php");
require_once __DIR__ . "/servicios_funciones.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: servicios.php");
    exit();
}

// id_servicio es un int autoincremental: debe ser un entero positivo.
$id_servicio = servicio_post_id("id_servicio");

if ($id_servicio === false) {
    volver_a_servicios("error=servicio_invalido");
}

// =====================================================
// ELIMINAR SERVICIO
// Si el servicio tiene reservas (fk_sr_servicio) o ventas
// (fk_venta_servicio), MySQL rechaza el borrado con el error 1451. Se
// informa al usuario y NO se elimina nada más: ni reservas ni ventas.
// El código funciona tanto si mysqli lanza excepciones (PHP 8.1+ por
// defecto) como si devuelve false.
// =====================================================

$errno = 0;
$filas_afectadas = 0;

try {

    $sql = "DELETE FROM servicio WHERE id_servicio = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id_servicio);

    if (mysqli_stmt_execute($stmt)) {
        $filas_afectadas = mysqli_stmt_affected_rows($stmt);
    } else {
        $errno = mysqli_stmt_errno($stmt) ?: -1;

        if ($errno !== 1451) {
            servicio_registrar_error_bd("eliminar_servicio.php", $errno, mysqli_stmt_error($stmt));
        }
    }

    mysqli_stmt_close($stmt);

} catch (Throwable $e) {
    $errno = ($e instanceof mysqli_sql_exception) ? (int) $e->getCode() : -1;

    if ($errno !== 1451) {
        servicio_registrar_error_bd("eliminar_servicio.php", $errno, $e->getMessage());
    }
}

if ($errno === 1451) {
    volver_a_servicios("error=restriccion");
}

if ($errno !== 0) {
    volver_a_servicios("error=bd");
}

if ($filas_afectadas === 0) {
    volver_a_servicios("error=no_existe");
}

volver_a_servicios("ok=eliminado");
