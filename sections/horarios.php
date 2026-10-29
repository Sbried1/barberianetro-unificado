<?php

// Horarios para la reserva de la web (lo pide reserva.php con fetch).
// Usa las mismas reglas que el panel: cada 30 minutos, según la duración
// de los servicios elegidos y sin pisar otras reservas del barbero.
//
// GET: id_barbero, fecha (AAAA-MM-DD), servicios (ids separados por coma)
// Respuesta: { "ok": true, "horarios": [ { "hora": "09:00", "disponible": true, "motivo": null }, ... ] }

require_once __DIR__ . '/comun.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['id_cliente'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'sin_sesion']);
    exit;
}

$id_barbero = isset($_GET['id_barbero']) && is_string($_GET['id_barbero']) && ctype_digit($_GET['id_barbero']) ? (int) $_GET['id_barbero'] : 0;
$fecha = isset($_GET['fecha']) && is_string($_GET['fecha']) ? $_GET['fecha'] : '';
$servicios = servicios_validos($conexion, $_GET['servicios'] ?? '');

if ($id_barbero <= 0 || empty($servicios) || !barbero_disponible($conexion, $id_barbero)) {
    echo json_encode(['ok' => false, 'error' => 'datos']);
    exit;
}

// Día fuera del rango de la web o cerrado: sin horarios.
if (!fecha_reservable_web($fecha)) {
    echo json_encode(['ok' => true, 'horarios' => []]);
    exit;
}

$duracion = duracion_total_servicios_ids($conexion, $servicios);
$horarios = [];
foreach (generar_horarios_disponibles($conexion, $id_barbero, $fecha, $duracion) as $h) {
    $horarios[] = ['hora' => $h['hora'], 'disponible' => $h['disponible'], 'motivo' => $h['motivo']];
}

echo json_encode(['ok' => true, 'duracion_minutos' => $duracion, 'horarios' => $horarios]);
