<?php
/**
 * auth/login.php
 *
 * Login del PERSONAL (gerente y barberos). Es una página separada del login
 * de clientes.
 *
 *   - Gerente: correo (gerente.email) + contraseña.
 *   - Barbero: ID de barbero (barbero.id_barbero) + contraseña.
 *
 * El servidor decide el rol según la tabla donde se valida la cuenta (ver
 * auth/cuentas.php). Protecciones: token CSRF + verificación de origen,
 * consultas preparadas, mensaje de error genérico, regeneración del ID de
 * sesión al iniciar, y ninguna contraseña se muestra ni se guarda.
 */
require_once __DIR__ . '/guard.php';
require_once __DIR__ . '/cuentas.php';

if (headers_sent()) {
    auth_error_interno();
}
auth_cabeceras_seguridad();
if (!auth_iniciar_sesion()) {
    auth_error_interno();
}

// Si ya hay una sesión válida, se va directo a la página de inicio del rol.
$usuario_actual = auth_usuario();
if ($usuario_actual !== null) {
    auth_redirigir(auth_inicio_por_rol($usuario_actual['rol']));
}

$mensaje       = null;   // [tipo, texto]
$identificador = '';

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {

    $identificador = (isset($_POST['identificador']) && is_string($_POST['identificador']))
        ? substr(trim($_POST['identificador']), 0, 150)
        : '';
    $clave = (isset($_POST['clave']) && is_string($_POST['clave'])) ? $_POST['clave'] : '';

    if (!auth_origen_valido() || !auth_csrf_valido($_POST['csrf'] ?? null)) {
        // Formulario vencido o enviado desde otro sitio: no se procesa.
        unset($_SESSION['csrf']);
        $mensaje = ['error', 'La página venció o la solicitud no es válida. Intentá de nuevo.'];

    } else {
        $conexion = null;
        try {
            require __DIR__ . '/../conexion.php';
        } catch (Throwable $e) {
            $conexion = null;
            error_log('[auth] no se pudo conectar a la base de datos: ' . $e->getMessage());
        }

        if (!($conexion instanceof mysqli)) {
            $mensaje = ['error', 'No se pudo iniciar sesión por un problema del servidor. Intentá más tarde.'];
        } else {
            $resultado = auth_intentar_login($conexion, $identificador, $clave);

            if ($resultado['ok']) {
                auth_registrar_login($resultado['usuario']['rol'], $resultado['usuario']['id'], $resultado['usuario']['nombre']);
                auth_redirigir(auth_inicio_por_rol($resultado['usuario']['rol']));
            }

            $mensaje = ($resultado['error'] === 'servicio')
                ? ['error', 'No se pudo iniciar sesión por un problema del servidor. Intentá más tarde.']
                : ['error', 'Credenciales incorrectas. Revisá los datos e intentá de nuevo.'];
        }
    }

    unset($clave);

} else {
    if (isset($_GET['salio'])) {
        $mensaje = ['ok', 'Cerraste sesión correctamente.'];
    } elseif (($_SESSION['aviso'] ?? '') === 'expirada') {
        $mensaje = ['aviso', 'Tu sesión expiró por inactividad. Iniciá sesión de nuevo.'];
    }
    unset($_SESSION['aviso']);
}

auth_vista_inicio('Iniciar sesión');
?>
        <h1 class="auth_titulo">Iniciar sesión</h1>
        <p class="auth_sub">Acceso del personal: gerente y barberos</p>
<?php if ($mensaje !== null) { auth_vista_alerta($mensaje[0], $mensaje[1]); } ?>
        <form method="post" action="<?php echo auth_e(auth_url('auth/login.php')); ?>" autocomplete="on">
            <?php echo auth_csrf_campo(); ?>

            <label class="auth_label" for="identificador">Correo (gerente) o ID de barbero</label>
            <input class="auth_input" type="text" id="identificador" name="identificador"
                   maxlength="150" required autofocus autocomplete="username"
                   placeholder="correo@ejemplo.com  o  5"
                   value="<?php echo auth_e($identificador); ?>">

            <label class="auth_label" for="clave">Contraseña</label>
            <input class="auth_input" type="password" id="clave" name="clave"
                   maxlength="<?php echo (int) AUTH_CLAVE_LARGO_MAX; ?>" required autocomplete="current-password">

            <button class="auth_btn" type="submit">Iniciar sesión</button>
        </form>
<?php
auth_vista_fin();
