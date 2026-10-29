<?php

require_once __DIR__ . '/../auth/guard.php';
auth_requerir('reservas');

include("../conexion.php");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: reservas.php");
    exit;
}

$id_reserva = isset($_POST["id_reserva"]) ? (int) $_POST["id_reserva"] : 0;

if ($id_reserva <= 0) {
    header("Location: reservas.php?error=invalido");
    exit;
}

mysqli_begin_transaction($conexion);

try {

    // Las reservas completadas, canceladas o con ventas vinculadas son
    // parte del historial: no se eliminan.
    $sql_estado = "SELECT estado FROM reserva WHERE id_reserva = ? FOR UPDATE";
    $consulta_estado = mysqli_prepare($conexion, $sql_estado);
    mysqli_stmt_bind_param($consulta_estado, "i", $id_reserva);
    mysqli_stmt_execute($consulta_estado);
    $reserva_estado = mysqli_fetch_assoc(mysqli_stmt_get_result($consulta_estado));
    mysqli_stmt_close($consulta_estado);

    $sql_ventas = "SELECT id_venta FROM venta WHERE id_reserva = ? LIMIT 1";
    $consulta_ventas = mysqli_prepare($conexion, $sql_ventas);
    mysqli_stmt_bind_param($consulta_ventas, "i", $id_reserva);
    mysqli_stmt_execute($consulta_ventas);
    $tiene_ventas = mysqli_num_rows(mysqli_stmt_get_result($consulta_ventas)) > 0;
    mysqli_stmt_close($consulta_ventas);

    if ($reserva_estado && (in_array($reserva_estado["estado"], ["completada", "cancelada"], true) || $tiene_ventas)) {
        mysqli_rollback($conexion);
        header("Location: reservas.php?error=historial");
        exit;
    }

    // Primero borramos las relaciones en servicioreserva para respetar
    // la integridad referencial (fk_sr_reserva).
    $sql_detalle = "DELETE FROM servicioreserva WHERE id_reserva = ?";
    $consulta_detalle = mysqli_prepare($conexion, $sql_detalle);
    mysqli_stmt_bind_param($consulta_detalle, "i", $id_reserva);
    if (!mysqli_stmt_execute($consulta_detalle)) {
        throw new Exception("No se pudo eliminar el detalle de la reserva.");
    }
    mysqli_stmt_close($consulta_detalle);

    $sql_reserva = "DELETE FROM reserva WHERE id_reserva = ?";
    $consulta_reserva = mysqli_prepare($conexion, $sql_reserva);
    mysqli_stmt_bind_param($consulta_reserva, "i", $id_reserva);

    if (!mysqli_stmt_execute($consulta_reserva)) {
        // Si existiera alguna otra restricción de integridad referencial
        // que impida borrar, lo informamos de forma entendible.
        throw new Exception("No se pudo eliminar la reserva.");
    }

    $filas_afectadas = mysqli_stmt_affected_rows($consulta_reserva);
    mysqli_stmt_close($consulta_reserva);

    if ($filas_afectadas === 0) {
        mysqli_rollback($conexion);
        header("Location: reservas.php?error=no_existe");
        exit;
    }

    mysqli_commit($conexion);
    header("Location: reservas.php?ok=eliminado");
    exit;

} catch (Exception $e) {
    mysqli_rollback($conexion);
    header("Location: reservas.php?error=restriccion");
    exit;
}
