<?php
/**
 * eliminar_venta.php
 *
 * Una venta con varios servicios son varias filas con el mismo id_reserva: la
 * reserva sigue completada mientras le quede alguna. Al eliminar la última,
 * vuelve a "pendiente" y puede volver a venderse. Solo cambia el estado, y solo
 * si estaba completada. No se usa "confirmada": la base no guarda si alguien la
 * había confirmado antes. Todo ocurre en una transacción.
 */

require_once __DIR__ . '/../auth/guard.php';
auth_requerir('ventas');

include("../conexion.php");
include("ventas_funciones.php");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ventas.php");
    exit;
}

$id_venta = venta_entero_positivo(venta_post_texto("id_venta"));

if ($id_venta === null) {
    venta_redirigir("error=invalido");
}

// Todo el acceso a la base está dentro del try/catch: cualquier fallo de
// MySQL se registra en el log del servidor y el usuario solo ve el mensaje
// genérico "no se pudo eliminar" (nunca el detalle técnico).
$destino = "";
$transaccion_iniciada = false;

try {

    $sql_reserva_venta = "SELECT id_reserva FROM venta WHERE id_venta = ? LIMIT 1";
    $consulta_reserva_venta = venta_preparar($conexion, $sql_reserva_venta);
    mysqli_stmt_bind_param($consulta_reserva_venta, "i", $id_venta);
    venta_ejecutar($consulta_reserva_venta);
    $fila_venta = mysqli_fetch_assoc(mysqli_stmt_get_result($consulta_reserva_venta));
    mysqli_stmt_close($consulta_reserva_venta);

    $id_reserva = ($fila_venta && $fila_venta["id_reserva"] !== null) ? (int) $fila_venta["id_reserva"] : null;

    if (!mysqli_begin_transaction($conexion)) {
        throw new RuntimeException("No se pudo iniciar la transacción.");
    }
    $transaccion_iniciada = true;

    if ($id_reserva !== null) {
        $sql_bloqueo = "SELECT id_reserva FROM reserva WHERE id_reserva = ? FOR UPDATE";
        $consulta_bloqueo = venta_preparar($conexion, $sql_bloqueo);
        mysqli_stmt_bind_param($consulta_bloqueo, "i", $id_reserva);
        venta_ejecutar($consulta_bloqueo);
        mysqli_stmt_get_result($consulta_bloqueo);
        mysqli_stmt_close($consulta_bloqueo);
    }

    $sql = "DELETE FROM venta WHERE id_venta = ?";
    $consulta = venta_preparar($conexion, $sql);
    mysqli_stmt_bind_param($consulta, "i", $id_venta);
    venta_ejecutar($consulta);

    $filas_afectadas = (int) mysqli_stmt_affected_rows($consulta);
    mysqli_stmt_close($consulta);

    if ($filas_afectadas < 0) {
        throw new RuntimeException("El borrado devolvió un estado inválido.");
    }

    if ($filas_afectadas > 0 && $id_reserva !== null) {
        $sql_restantes = "SELECT COUNT(*) AS cantidad FROM venta WHERE id_reserva = ?";
        $consulta_restantes = venta_preparar($conexion, $sql_restantes);
        mysqli_stmt_bind_param($consulta_restantes, "i", $id_reserva);
        venta_ejecutar($consulta_restantes);
        $restantes = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($consulta_restantes))["cantidad"];
        mysqli_stmt_close($consulta_restantes);

        if ($restantes === 0) {
            $sql_reabrir = "UPDATE reserva SET estado = 'pendiente' WHERE id_reserva = ? AND estado = 'completada'";
            $consulta_reabrir = venta_preparar($conexion, $sql_reabrir);
            mysqli_stmt_bind_param($consulta_reabrir, "i", $id_reserva);
            venta_ejecutar($consulta_reabrir);
            mysqli_stmt_close($consulta_reabrir);
        }
    }

    if (!mysqli_commit($conexion)) {
        throw new RuntimeException("No se pudo confirmar la transacción.");
    }
    $transaccion_iniciada = false;

    $destino = $filas_afectadas === 0 ? "error=no_existe" : "ok=eliminado";

} catch (Throwable $e) {
    if ($transaccion_iniciada) {
        try {
            mysqli_rollback($conexion);
        } catch (Throwable $e_rollback) {
        }
    }
    venta_registrar_error("eliminar_venta", $e);
    $destino = "error=restriccion";
}

venta_redirigir($destino);
