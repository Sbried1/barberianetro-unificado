<?php
/**
 * auth/inicio.php
 *
 * Pantalla a la que llega un barbero después de iniciar sesión: sus próximas
 * reservas, con la opción de confirmar las pendientes. Es lo único que el
 * barbero puede hacer en el panel (ver auth/permisos.php). El gerente que
 * abra esta URL es enviado al Dashboard.
 */
require_once __DIR__ . '/guard.php';

$cuenta = auth_requerir_sesion(); // conexion.php ya usa $usuario para MySQL

if ($cuenta['rol'] === AUTH_ROL_GERENTE) {
    auth_redirigir('index.php');
}

include(__DIR__ . '/../conexion.php');

// El barbero sale siempre de la sesión, nunca de un dato enviado por el navegador.
$reservas = [];
$error_lectura = false;

try {
    $sql = "SELECT r.id_reserva, r.fecha, r.hora, r.estado,
                   c.nombre AS cliente_nombre, c.apellido AS cliente_apellido,
                   GROUP_CONCAT(s.nombre ORDER BY s.nombre SEPARATOR ', ') AS servicios
            FROM reserva r
            LEFT JOIN cliente c ON c.id_cliente = r.id_cliente
            LEFT JOIN servicioreserva sr ON sr.id_reserva = r.id_reserva
            LEFT JOIN servicio s ON s.id_servicio = sr.id_servicio
            WHERE r.id_barbero = ?
              AND r.estado IN ('pendiente', 'confirmada')
              AND (r.fecha > CURDATE() OR (r.fecha = CURDATE() AND r.hora > CURTIME()))
            GROUP BY r.id_reserva, r.fecha, r.hora, r.estado, c.nombre, c.apellido
            ORDER BY r.fecha, r.hora";
    $consulta = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($consulta, "i", $cuenta['id']);
    mysqli_stmt_execute($consulta);
    $reservas = mysqli_fetch_all(mysqli_stmt_get_result($consulta), MYSQLI_ASSOC);
    mysqli_stmt_close($consulta);
} catch (Throwable $e) {
    error_log('[auth] inicio del barbero: ' . $e->getMessage());
    $error_lectura = true;
}

auth_vista_inicio('Mis reservas');
?>
        <link rel="stylesheet" href="<?php echo auth_e(auth_url_versionada('Estilos_index/panel_ui.css')); ?>">
        <script src="<?php echo auth_e(auth_url_versionada('panel_ui.js')); ?>"></script>

        <h1 class="auth_titulo">Hola, <?php echo auth_e($cuenta['nombre']); ?></h1>
        <p class="auth_sub">Tus próximas reservas</p>

        <?php
        if (($_GET['ok'] ?? '') === 'confirmada') {
            auth_vista_alerta('ok', 'Reserva confirmada. Pasará a Completada cuando se registre la venta.');
        }
        if (($_GET['error'] ?? '') === 'no_disponible') {
            auth_vista_alerta('error', 'No se pudo confirmar: la reserva ya no está pendiente, ya pasó o no está asignada a vos.');
        }
        if (($_GET['error'] ?? '') === 'solicitud') {
            auth_vista_alerta('error', 'No se pudo validar la solicitud. Volvé a intentarlo.');
        }
        if ($error_lectura) {
            auth_vista_alerta('error', 'No se pudieron cargar tus reservas. Intentá nuevamente.');
        }
        ?>

        <?php if (!$error_lectura && empty($reservas)) { ?>
            <p class="auth_texto">No tenés reservas pendientes ni confirmadas por delante.</p>
        <?php } ?>

        <div class="barb_reservas">
            <?php foreach ($reservas as $r) {
                $cliente = $r['cliente_nombre'] !== null ? trim($r['cliente_nombre'] . ' ' . $r['cliente_apellido']) : 'Cliente eliminado';
                $fecha = date('d/m/Y', strtotime($r['fecha']));
                $hora = substr($r['hora'], 0, 5);
            ?>
                <div class="barb_reserva">
                    <div class="barb_reserva_datos">
                        <strong><?php echo auth_e($fecha); ?> · <?php echo auth_e($hora); ?></strong>
                        <span><?php echo auth_e($cliente); ?></span>
                        <small><?php echo auth_e($r['servicios'] ?? 'Sin servicios'); ?></small>
                    </div>
                    <?php if ($r['estado'] === 'pendiente') { ?>
                        <form
                            method="post"
                            action="<?php echo auth_e(auth_url('reservas/confirmar_mi_reserva.php')); ?>"
                            class="auth_form_inline"
                            data-confirmar-titulo="¿Confirmar la reserva?"
                            data-confirmar="Reserva de <?php echo auth_e($cliente); ?> del <?php echo auth_e($fecha); ?> a las <?php echo auth_e($hora); ?>. Confirmar no completa el servicio."
                            data-confirmar-boton="Confirmar reserva"
                            data-confirmar-cargando="Confirmando..."
                            data-confirmar-tipo="normal">
                            <?php echo auth_csrf_campo(); ?>
                            <input type="hidden" name="id_reserva" value="<?php echo (int) $r['id_reserva']; ?>">
                            <button class="auth_btn" type="submit">Confirmar</button>
                        </form>
                    <?php } else { ?>
                        <span class="barb_reserva_estado">Confirmada</span>
                    <?php } ?>
                </div>
            <?php } ?>
        </div>

        <form method="post" action="<?php echo auth_e(auth_url('auth/logout.php')); ?>" class="auth_form_inline">
            <?php echo auth_csrf_campo(); ?>
            <button class="auth_btn auth_btn_sec" type="submit">Cerrar sesión</button>
        </form>
<?php
auth_vista_fin();
