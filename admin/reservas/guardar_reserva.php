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
// id_cliente es INT en la base: se acepta solo si son dígitos, así un
// valor como "1abc" no se convierte en el cliente 1.
$id_cliente = isset($_POST["id_cliente"]) ? trim((string) $_POST["id_cliente"]) : "";
$id_cliente = ctype_digit($id_cliente) ? (int) $id_cliente : 0;
$id_barbero = isset($_POST["id_barbero"]) ? (int) $_POST["id_barbero"] : 0;
$fecha      = isset($_POST["fecha"]) && is_string($_POST["fecha"]) ? trim($_POST["fecha"]) : "";
$hora       = isset($_POST["hora"]) && is_string($_POST["hora"]) ? trim($_POST["hora"]) : "";
$email      = isset($_POST["email"]) && is_string($_POST["email"]) ? trim($_POST["email"]) : "";
$servicios  = isset($_POST["servicios"]) && is_array($_POST["servicios"]) ? $_POST["servicios"] : [];

// =====================================================
// VALIDACIONES
// =====================================================
if ($id_cliente <= 0 || $id_barbero <= 0 || $fecha === "" || $hora === "" || empty($servicios)) {
    header("Location: reservas.php?error=invalido");
    exit;
}

if (!validar_fecha_reserva($fecha) || !validar_hora_reserva($hora)) {
    header("Location: reservas.php?error=invalido");
    exit;
}

if (turno_ya_paso($fecha, $hora)) {
    header("Location: reservas.php?error=pasado");
    exit;
}

if ($fecha > resv_fecha_limite_admin($conexion)) {
    header("Location: reservas.php?error=horizonte");
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

if (!existe_cliente_id($conexion, $id_cliente)) {
    header("Location: reservas.php?error=no_existe");
    exit;
}

if (!ids_existen($conexion, "barbero", "id_barbero", $id_barbero)) {
    header("Location: reservas.php?error=no_existe");
    exit;
}

// Los servicios deben venir como IDs numéricos y existir realmente
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

// Misma regla que la grilla: el turno no puede empezar antes de la apertura
// ni terminar después del cierre.
if (!turno_dentro_de_horario($hora, $duracion_minutos)) {
    header("Location: reservas.php?error=fuera_horario");
    exit;
}

// =====================================================
// INSERCIÓN (reserva + servicioreserva)
// La superposición se comprueba dentro de la transacción, con la agenda
// del barbero bloqueada: dos pedidos simultáneos no pueden pasar los dos.
// =====================================================
mysqli_begin_transaction($conexion);

try {

    bloquear_agenda_barbero($conexion, $id_barbero);

    if (existe_conflicto_intervalo($conexion, $id_barbero, $fecha, $hora, $duracion_minutos)) {
        mysqli_rollback($conexion);
        header("Location: reservas.php?error=conflicto");
        exit;
    }

    $sql_reserva = "INSERT INTO reserva (id_cliente, id_barbero, fecha, hora, email) VALUES (?, ?, ?, ?, ?)";
    $consulta_reserva = mysqli_prepare($conexion, $sql_reserva);
    $email_valor = $email !== "" ? $email : null;
    mysqli_stmt_bind_param($consulta_reserva, "iisss", $id_cliente, $id_barbero, $fecha, $hora, $email_valor);

    if (!mysqli_stmt_execute($consulta_reserva)) {
        throw new Exception("No se pudo crear la reserva.");
    }

    $id_reserva_nueva = mysqli_insert_id($conexion);
    mysqli_stmt_close($consulta_reserva);

    // servicioreserva solo relaciona id_servicio <-> id_reserva (no guarda
    // precio: el precio real se lee siempre desde "servicio" al mostrarlo).
    $sql_detalle = "INSERT INTO servicioreserva (id_servicio, id_reserva) VALUES (?, ?)";
    $consulta_detalle = mysqli_prepare($conexion, $sql_detalle);

    foreach ($servicios_validos as $id_servicio) {
        mysqli_stmt_bind_param($consulta_detalle, "ii", $id_servicio, $id_reserva_nueva);
        if (!mysqli_stmt_execute($consulta_detalle)) {
            throw new Exception("No se pudo asociar el servicio a la reserva.");
        }
    }
    mysqli_stmt_close($consulta_detalle);

    mysqli_commit($conexion);

    header("Location: reservas.php?ok=agregado");
    exit;

} catch (Exception $e) {
    mysqli_rollback($conexion);
    header("Location: reservas.php?error=guardar");
    exit;
}
