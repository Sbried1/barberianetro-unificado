<?php

require_once __DIR__ . '/../auth/guard.php';
auth_requerir('reservas');

include("../conexion.php");
include("reservas_funciones.php");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: reservas.php");
    exit;
}

// =====================================================
// LECTURA Y LIMPIEZA BÁSICA DE LOS DATOS DEL FORMULARIO
// =====================================================
$id_reserva = isset($_POST["id_reserva"]) ? (int) $_POST["id_reserva"] : 0;
$id_cliente = isset($_POST["id_cliente"]) ? trim((string) $_POST["id_cliente"]) : "";
$id_cliente = ctype_digit($id_cliente) ? (int) $id_cliente : 0;
$id_barbero = isset($_POST["id_barbero"]) ? (int) $_POST["id_barbero"] : 0;
$fecha      = isset($_POST["fecha"]) ? trim($_POST["fecha"]) : "";
$hora       = isset($_POST["hora"]) ? trim($_POST["hora"]) : "";
$email      = isset($_POST["email"]) ? trim($_POST["email"]) : "";
$servicios  = isset($_POST["servicios"]) && is_array($_POST["servicios"]) ? $_POST["servicios"] : [];

// =====================================================
// VALIDACIONES
// =====================================================
if ($id_reserva <= 0 || $id_cliente <= 0 || $id_barbero <= 0 || $fecha === "" || $hora === "" || empty($servicios)) {
    header("Location: reservas.php?error=invalido");
    exit;
}

if (!validar_fecha_reserva($fecha) || !validar_hora_reserva($hora)) {
    header("Location: reservas.php?error=invalido");
    exit;
}

if (es_dia_cerrado($fecha)) {
    header("Location: reservas.php?error=cerrado");
    exit;
}

if ($email !== "" && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header("Location: reservas.php?error=invalido");
    exit;
}

$sql_existe_reserva = "SELECT estado FROM reserva WHERE id_reserva = ? LIMIT 1";
$consulta_existe = mysqli_prepare($conexion, $sql_existe_reserva);
mysqli_stmt_bind_param($consulta_existe, "i", $id_reserva);
mysqli_stmt_execute($consulta_existe);
$reserva_actual = mysqli_fetch_assoc(mysqli_stmt_get_result($consulta_existe));
mysqli_stmt_close($consulta_existe);

if (!$reserva_actual) {
    header("Location: reservas.php?error=no_existe");
    exit;
}

// Una reserva completada (ya vendida) o cancelada forma parte del
// historial: no se edita.
if (!in_array($reserva_actual["estado"], ["pendiente", "confirmada"], true)) {
    header("Location: reservas.php?error=estado");
    exit;
}

if (!existe_cliente_id($conexion, $id_cliente)) {
    header("Location: reservas.php?error=no_existe");
    exit;
}

if (!ids_existen($conexion, "barbero", "id_barbero", $id_barbero)) {
    header("Location: reservas.php?error=no_existe");
    exit;
}

$servicios_validos = [];
foreach ($servicios as $id_servicio) {
    $id_servicio = (int) $id_servicio;
    if ($id_servicio > 0 && !in_array($id_servicio, $servicios_validos, true) && ids_existen($conexion, "servicio", "id_servicio", $id_servicio)) {
        $servicios_validos[] = $id_servicio;
    }
}

if (empty($servicios_validos)) {
    header("Location: reservas.php?error=invalido");
    exit;
}

// Duración real de los servicios elegidos (a partir de servicio.duracion,
// interpretado por parsear_duracion_a_minutos ya que es texto libre).
$duracion_minutos = duracion_total_servicios_ids($conexion, $servicios_validos);

// =====================================================
// ACTUALIZACIÓN (reserva + servicioreserva)
// =====================================================
mysqli_begin_transaction($conexion);

try {

    $sql_bloqueo = "SELECT estado FROM reserva WHERE id_reserva = ? FOR UPDATE";
    $consulta_bloqueo = mysqli_prepare($conexion, $sql_bloqueo);
    mysqli_stmt_bind_param($consulta_bloqueo, "i", $id_reserva);
    mysqli_stmt_execute($consulta_bloqueo);
    $estado_bloqueado = mysqli_fetch_assoc(mysqli_stmt_get_result($consulta_bloqueo));
    mysqli_stmt_close($consulta_bloqueo);

    if (!$estado_bloqueado || !in_array($estado_bloqueado["estado"], ["pendiente", "confirmada"], true)) {
        mysqli_rollback($conexion);
        header("Location: reservas.php?error=estado");
        exit;
    }

    // Superposición con OTRA reserva del barbero, comprobada con su agenda
    // bloqueada (ver guardar_reserva.php).
    bloquear_agenda_barbero($conexion, $id_barbero);

    if (existe_conflicto_intervalo($conexion, $id_barbero, $fecha, $hora, $duracion_minutos, $id_reserva)) {
        mysqli_rollback($conexion);
        header("Location: reservas.php?error=conflicto");
        exit;
    }

    $sql_reserva = "UPDATE reserva SET id_cliente = ?, id_barbero = ?, fecha = ?, hora = ?, email = ? WHERE id_reserva = ?";
    $consulta_reserva = mysqli_prepare($conexion, $sql_reserva);
    $email_valor = $email !== "" ? $email : null;
    mysqli_stmt_bind_param($consulta_reserva, "iisssi", $id_cliente, $id_barbero, $fecha, $hora, $email_valor, $id_reserva);

    if (!mysqli_stmt_execute($consulta_reserva)) {
        throw new Exception("No se pudo actualizar la reserva.");
    }
    mysqli_stmt_close($consulta_reserva);

    // Reemplazamos por completo los servicios asociados a esta reserva
    $sql_borrar_detalle = "DELETE FROM servicioreserva WHERE id_reserva = ?";
    $consulta_borrar = mysqli_prepare($conexion, $sql_borrar_detalle);
    mysqli_stmt_bind_param($consulta_borrar, "i", $id_reserva);
    if (!mysqli_stmt_execute($consulta_borrar)) {
        throw new Exception("No se pudo actualizar los servicios de la reserva.");
    }
    mysqli_stmt_close($consulta_borrar);

    $sql_detalle = "INSERT INTO servicioreserva (id_servicio, id_reserva) VALUES (?, ?)";
    $consulta_detalle = mysqli_prepare($conexion, $sql_detalle);

    foreach ($servicios_validos as $id_servicio) {
        mysqli_stmt_bind_param($consulta_detalle, "ii", $id_servicio, $id_reserva);
        if (!mysqli_stmt_execute($consulta_detalle)) {
            throw new Exception("No se pudo asociar el servicio a la reserva.");
        }
    }
    mysqli_stmt_close($consulta_detalle);

    mysqli_commit($conexion);

    header("Location: reservas.php?ok=actualizado");
    exit;

} catch (Exception $e) {
    mysqli_rollback($conexion);
    header("Location: reservas.php?error=guardar");
    exit;
}
