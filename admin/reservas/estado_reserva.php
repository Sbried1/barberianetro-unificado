<?php

require_once __DIR__ . '/../auth/guard.php';
auth_requerir('reservas');

include("../conexion.php");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: reservas.php");
    exit;
}

// "completada" no se asigna desde acá: solo se alcanza al registrar la
// venta de la reserva (venta/guardar_venta.php).
$transiciones = [
    "confirmar" => ["nuevo" => "confirmada", "desde" => ["pendiente"],               "ok" => "confirmada"],
    "cancelar"  => ["nuevo" => "cancelada",  "desde" => ["pendiente", "confirmada"], "ok" => "cancelada"],
];

$id_reserva = isset($_POST["id_reserva"]) && is_string($_POST["id_reserva"]) && preg_match('/^[0-9]{1,10}$/', $_POST["id_reserva"])
    ? (int) $_POST["id_reserva"]
    : 0;
$accion = isset($_POST["accion"]) && is_string($_POST["accion"]) ? $_POST["accion"] : "";

if ($id_reserva <= 0 || !isset($transiciones[$accion])) {
    header("Location: reservas.php?error=invalido");
    exit;
}

$transicion = $transiciones[$accion];

try {
    $sql_actual = "SELECT estado FROM reserva WHERE id_reserva = ? LIMIT 1";
    $consulta_actual = mysqli_prepare($conexion, $sql_actual);
    mysqli_stmt_bind_param($consulta_actual, "i", $id_reserva);
    mysqli_stmt_execute($consulta_actual);
    $reserva = mysqli_fetch_assoc(mysqli_stmt_get_result($consulta_actual));
    mysqli_stmt_close($consulta_actual);

    if (!$reserva) {
        header("Location: reservas.php?error=no_existe");
        exit;
    }

    // La condición sobre el estado va en el propio UPDATE: si una venta
    // completó la reserva entre la lectura y este punto, no se pisa.
    $placeholders = implode(",", array_fill(0, count($transicion["desde"]), "?"));
    $sql = "UPDATE reserva SET estado = ? WHERE id_reserva = ? AND estado IN ($placeholders)";
    $consulta = mysqli_prepare($conexion, $sql);
    $tipos = "si" . str_repeat("s", count($transicion["desde"]));
    $nuevo = $transicion["nuevo"];
    mysqli_stmt_bind_param($consulta, $tipos, $nuevo, $id_reserva, ...$transicion["desde"]);
    mysqli_stmt_execute($consulta);
    $filas = mysqli_stmt_affected_rows($consulta);
    mysqli_stmt_close($consulta);

    if ($filas !== 1) {
        header("Location: reservas.php?error=estado");
        exit;
    }

    header("Location: reservas.php?ok=" . $transicion["ok"]);
    exit;

} catch (Throwable $e) {
    error_log("[reservas] estado_reserva: " . $e->getMessage());
    header("Location: reservas.php?error=guardar");
    exit;
}
