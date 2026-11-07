<?php
/**
 * auth/logout.php
 *
 * Cierre de sesión.
 *   - POST con token CSRF válido: destruye la sesión (vacía los datos, borra
 *     la cookie y elimina la sesión del servidor) y vuelve al login.
 *   - GET (por ejemplo, si el navegador no ejecutó el JavaScript del menú):
 *     muestra una pantalla de confirmación que envía el mismo POST.
 */
require_once __DIR__ . '/guard.php';

if (headers_sent()) {
    auth_error_interno();
}
auth_cabeceras_seguridad();

// Sin cookie, o con una sesión inexistente o vencida: no hay nada que cerrar.
if (!isset($_COOKIE[AUTH_COOKIE_NOMBRE]) || !auth_iniciar_sesion() || auth_usuario() === null) {
    auth_redirigir('auth/login.php');
}

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
    if (auth_origen_valido() && auth_csrf_valido($_POST['csrf'] ?? null)) {
        auth_destruir_sesion();
        auth_redirigir('auth/login.php?salio=1');
    }
    auth_denegar_html(
        403,
        'Solicitud rechazada',
        'No se pudo cerrar la sesión porque la solicitud no es válida. Volvé a intentarlo.',
        auth_usuario()
    );
}

// GET: pantalla de confirmación.
$usuario = auth_usuario();
auth_vista_inicio('Cerrar sesión');
?>
        <h1 class="auth_titulo">Cerrar sesión</h1>
        <p class="auth_texto">Vas a cerrar la sesión de <?php echo auth_e($usuario['nombre']); ?>.</p>
        <form method="post" action="<?php echo auth_e(auth_url('auth/logout.php')); ?>" class="auth_form_inline">
            <?php echo auth_csrf_campo(); ?>
            <button class="auth_btn" type="submit">Cerrar sesión</button>
        </form>
        <a class="auth_btn auth_btn_sec" href="<?php echo auth_e(auth_url(auth_inicio_por_rol($usuario['rol']))); ?>">Cancelar</a>
<?php
auth_vista_fin();
