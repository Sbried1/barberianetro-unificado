<?php
/**
 * confirmar_mi_reserva.php
 * Un barbero confirma una reserva asignada a él. El gerente usa
 * estado_reserva.php, que permite gestionar todas.
 *
 * La regla vive en el propio UPDATE: el barbero sale de la sesión (nunca de un
 * dato enviado por el navegador) y la reserva tiene que seguir siendo suya,
 * estar pendiente y no haber pasado. El rechazo es el mismo en todos los casos
 * para no revelar si existe una reserva de otro barbero.
 */

require_once __DIR__ . '/../auth/guard.php';
$cuenta = auth_requerir('mis_reservas'); // conexion.php ya usa $usuario para MySQL

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    auth_redirigir('auth/inicio.php');
}

if (!auth_csrf_valido($_POST["csrf"] ?? null)) {
    auth_redirigir('auth/inicio.php?error=solicitud');
}

include("../conexion.php");

$id_reserva = isset($_POST["id_reserva"]) && is_string($_POST["id_reserva"]) && preg_match('/^[0-9]{1,10}$/', $_POST["id_reserva"])
    ? (int) $_POST["id_reserva"]
    : 0;

$filas = 0;

try {
    $sql = "UPDATE reserva SET estado = 'confirmada'
            WHERE id_reserva = ? AND id_barbero = ? AND estado = 'pendiente'
              AND (fecha > CURDATE() OR (fecha = CURDATE() AND hora > CURTIME()))";
    $consulta = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($consulta, "ii", $id_reserva, $cuenta["id"]);
    mysqli_stmt_execute($consulta);
    $filas = mysqli_stmt_affected_rows($consulta);
    mysqli_stmt_close($consulta);
} catch (Throwable $e) {
    error_log("[reservas] confirmar_mi_reserva: " . $e->getMessage());
}

auth_redirigir($filas === 1 ? 'auth/inicio.php?ok=confirmada' : 'auth/inicio.php?error=no_disponible');
