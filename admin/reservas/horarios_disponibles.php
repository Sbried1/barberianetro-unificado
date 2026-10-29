<?php
/**
 * horarios_disponibles.php
 * Endpoint AJAX (GET) usado por el modal "Nueva reserva" en reservas.js.
 * Calcula la grilla real de horarios disponibles/ocupados para un
 * barbero, una fecha y la duración total de los servicios elegidos,
 * usando las reservas ya existentes en la base (sin datos inventados).
 *
 * No modifica nada: es de solo lectura.
 *
 * Parámetros GET esperados:
 *   id_barbero  -> int, obligatorio
 *   fecha       -> "YYYY-MM-DD", obligatorio
 *   servicios   -> ids de servicio separados por coma, ej: "1,4,7", obligatorio
 *   id_reserva  -> int, opcional (para no chocar con la propia reserva al editar)
 *
 * Respuesta JSON:
 *   {
 *     "ok": true,
 *     "duracion_minutos": 45,
 *     "horarios": [ { "hora": "09:00", "fin": "09:45", "disponible": true,
 *                     "motivo": null | "pasado" | "ocupado", "choca_con": "10:00–11:00" | null }, ... ],
 *     "ocupados": [ "10:00–11:00", ... ]   turnos ya tomados por ese barbero ese día
 *   }
 */

require_once __DIR__ . '/../auth/guard.php';
auth_requerir('reservas', 'json');

include("../conexion.php");
include("reservas_funciones.php");

header("Content-Type: application/json; charset=utf-8");

$respuesta = [
    "ok" => false,
    "duracion_minutos" => 0,
    "horarios" => [],
    "error" => null,
];

$id_barbero = isset($_GET["id_barbero"]) ? (int) $_GET["id_barbero"] : 0;
$fecha      = isset($_GET["fecha"]) ? trim($_GET["fecha"]) : "";
$servicios_raw = isset($_GET["servicios"]) ? trim($_GET["servicios"]) : "";

$id_reserva_excluir = isset($_GET["id_reserva"]) ? (int) $_GET["id_reserva"] : 0;
if ($id_reserva_excluir <= 0) {
    $id_reserva_excluir = null;
}

if ($id_barbero <= 0 || $fecha === "" || $servicios_raw === "") {
    $respuesta["error"] = "faltan_datos";
    echo json_encode($respuesta);
    exit;
}

if (!validar_fecha_reserva($fecha)) {
    $respuesta["error"] = "fecha_invalida";
    echo json_encode($respuesta);
    exit;
}

if (!ids_existen($conexion, "barbero", "id_barbero", $id_barbero)) {
    $respuesta["error"] = "barbero_no_existe";
    echo json_encode($respuesta);
    exit;
}

$ids_servicios = array_values(array_filter(array_map("intval", explode(",", $servicios_raw))));

if (empty($ids_servicios)) {
    $respuesta["error"] = "servicios_invalidos";
    echo json_encode($respuesta);
    exit;
}

// Solo se consideran servicios que realmente existen en la base.
$ids_servicios_validos = [];
foreach ($ids_servicios as $id_servicio) {
    if ($id_servicio > 0 && ids_existen($conexion, "servicio", "id_servicio", $id_servicio)) {
        $ids_servicios_validos[] = $id_servicio;
    }
}

if (empty($ids_servicios_validos)) {
    $respuesta["error"] = "servicios_invalidos";
    echo json_encode($respuesta);
    exit;
}

$duracion_minutos = duracion_total_servicios_ids($conexion, $ids_servicios_validos);

$respuesta["ok"] = true;
$respuesta["duracion_minutos"] = $duracion_minutos;
$respuesta["horarios"] = generar_horarios_disponibles($conexion, $id_barbero, $fecha, $duracion_minutos, $id_reserva_excluir);
$respuesta["ocupados"] = array_map(function ($intervalo) {
    return minutos_a_hora($intervalo[0]) . "–" . minutos_a_hora($intervalo[1]);
}, obtener_intervalos_ocupados($conexion, $id_barbero, $fecha, $id_reserva_excluir));
sort($respuesta["ocupados"]);

echo json_encode($respuesta, JSON_UNESCAPED_UNICODE);
