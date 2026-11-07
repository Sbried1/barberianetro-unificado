<?php
require_once __DIR__ . '/comun.php';
requerir_cliente();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_valido()) {
    http_response_code(403);
    exit('Solicitud no válida');
}

$id_reserva = filter_input(INPUT_POST, 'id_reserva', FILTER_VALIDATE_INT);
if (!$id_reserva) {
    header('Location: perfil.php');
    exit;
}

// La reserva no se borra: pasa a "cancelada" y queda en el historial (el
// panel la sigue mostrando y su horario se libera). Solo se pueden
// cancelar las reservas propias que estén pendientes o confirmadas; una
// completada ya tiene su venta registrada.
$id_cliente = (int) $_SESSION['id_cliente'];
$stmt = $conexion->prepare(
    "UPDATE reserva SET estado = 'cancelada'
     WHERE id_reserva = ? AND id_cliente = ? AND estado IN ('pendiente', 'confirmada')"
);
$stmt->bind_param('ii', $id_reserva, $id_cliente);
$stmt->execute();
$cancelada = $stmt->affected_rows === 1;
$stmt->close();

header('Location: perfil.php?' . ($cancelada ? 'cancelada=1' : 'error=cancelar'));
exit;
