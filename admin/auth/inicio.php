<?php
/**
 * auth/inicio.php
 *
 * Pantalla a la que llega un barbero después de iniciar sesión. Según la
 * matriz de permisos aprobada, el barbero no tiene hoy ningún módulo
 * administrativo habilitado (ver auth/permisos.php), así que esta pantalla
 * solo confirma la sesión y permite cerrarla. No es una función de negocio
 * nueva. El gerente que abra esta URL es enviado al Dashboard.
 */
require_once __DIR__ . '/guard.php';

$usuario = auth_requerir_sesion();

if ($usuario['rol'] === AUTH_ROL_GERENTE) {
    auth_redirigir('index.php');
}

auth_vista_inicio('Inicio');
?>
        <h1 class="auth_titulo">Hola, <?php echo auth_e($usuario['nombre']); ?></h1>
        <p class="auth_sub">Sesión iniciada como barbero</p>
        <p class="auth_texto">
            Tu cuenta está activa, pero por ahora no tiene secciones habilitadas en el panel.
            Si necesitás acceso a alguna función, pedíselo al gerente.
        </p>
        <form method="post" action="<?php echo auth_e(auth_url('auth/logout.php')); ?>" class="auth_form_inline">
            <?php echo auth_csrf_campo(); ?>
            <button class="auth_btn auth_btn_sec" type="submit">Cerrar sesión</button>
        </form>
<?php
auth_vista_fin();
