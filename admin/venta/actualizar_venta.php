<?php
/**
 * actualizar_venta.php
 *
 * Solo se corrigen el barbero y la fecha; el cliente, el servicio y el
 * total no se editan acá (si están mal, conviene eliminar la venta y
 * cargarla de nuevo). Una venta vinculada a una reserva conserva el
 * barbero de la reserva.
 */

require_once __DIR__ . '/../auth/guard.php';
auth_requerir('ventas');

include("../conexion.php");
include("ventas_funciones.php");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ventas.php");
    exit;
}

// Los ids se aceptan SOLO si son enteros positivos escritos con dígitos.
$id_venta   = venta_entero_positivo(venta_post_texto("id_venta"));
$id_barbero = venta_entero_positivo(venta_post_texto("id_barbero"));
$fecha      = venta_post_texto("fecha");

if ($id_venta === null) {
    venta_redirigir("error=no_existe");
}

if ($id_barbero === null) {
    venta_redirigir("error=invalido");
}

// Fecha con formato estricto AAAA-MM-DD (el que envía <input type="date">).
if (!venta_fecha_valida($fecha)) {
    venta_redirigir("error=invalido");
}

// Todo el acceso a la base está dentro del try/catch: cualquier fallo de
// MySQL se registra en el log del servidor y el usuario solo ve el mensaje
// genérico "error al guardar" (nunca el detalle técnico).
$destino = "";

try {

    $sql_check = "SELECT id_barbero, id_reserva FROM venta WHERE id_venta = ? LIMIT 1";
    $consulta_check = venta_preparar($conexion, $sql_check);
    mysqli_stmt_bind_param($consulta_check, "i", $id_venta);
    venta_ejecutar($consulta_check);
    $venta_actual = mysqli_fetch_assoc(mysqli_stmt_get_result($consulta_check));
    mysqli_stmt_close($consulta_check);

    if (!$venta_actual) {
        $destino = "error=no_existe";

    } elseif (!venta_id_existe($conexion, "barbero", "id_barbero", $id_barbero)) {
        $destino = "error=no_existe";

    } elseif ($venta_actual["id_reserva"] !== null && (int) $venta_actual["id_barbero"] !== $id_barbero) {
        $destino = "error=venta_reserva";

    } else {

        $sql = "UPDATE venta SET id_barbero = ?, fecha = ? WHERE id_venta = ?";
        $consulta = venta_preparar($conexion, $sql);
        mysqli_stmt_bind_param($consulta, "isi", $id_barbero, $fecha, $id_venta);
        venta_ejecutar($consulta);
        mysqli_stmt_close($consulta);

        $destino = "ok=actualizado";
    }

} catch (Throwable $e) {
    venta_registrar_error("actualizar_venta", $e);
    $destino = "error=guardar";
}

venta_redirigir($destino);
