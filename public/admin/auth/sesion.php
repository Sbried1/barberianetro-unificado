<?php
/**
 * auth/sesion.php
 *
 * Arranque seguro de la sesión PHP y utilidades de identidad/URL.
 * Solo define constantes y funciones: no imprime nada.
 *
 * Qué garantiza:
 *  - Cookie propia (NETRO_SESSID), HttpOnly y SameSite=Lax. "Secure" se
 *    activa sola cuando el sitio se sirve por HTTPS.
 *  - Modo estricto: el servidor rechaza un ID de sesión que él no generó
 *    (defensa contra fijación de sesión).
 *  - El ID se regenera al iniciar sesión (auth_registrar_login).
 *  - Expira a los 30 minutos sin actividad y, en cualquier caso, a las 8 horas.
 *  - Nunca se guarda la contraseña en la sesión: solo rol, id y nombre.
 */
require_once __DIR__ . '/permisos.php';
auth_bloquear_acceso_directo(__FILE__);

const AUTH_COOKIE_NOMBRE   = 'NETRO_SESSID';
const AUTH_INACTIVIDAD_MAX = 1800;   // 30 minutos sin actividad
const AUTH_DURACION_MAX    = 28800;  // 8 horas desde que se inició sesión

function auth_es_https(): bool
{
    return !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
}

// Prefijo de URL del proyecto respecto de la raíz web ("" cuando el proyecto
// ES la raíz, como en el contenedor Docker; "/Proyectos/Barberia" si se sirve
// desde una subcarpeta).
function auth_base_url(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    $base = '';
    $raiz = realpath(__DIR__ . '/..');
    $doc  = isset($_SERVER['DOCUMENT_ROOT']) ? realpath((string) $_SERVER['DOCUMENT_ROOT']) : false;
    if ($raiz !== false && $doc !== false && $raiz !== $doc && strpos($raiz, $doc) === 0) {
        $relativa = trim(str_replace('\\', '/', substr($raiz, strlen($doc))), '/');
        if ($relativa !== '') {
            $base = '/' . implode('/', array_map('rawurlencode', explode('/', $relativa)));
        }
    }
    return $base;
}

// URL absoluta (desde la raíz web) de una ruta del proyecto. Solo se la usa
// con rutas fijas del propio código, nunca con datos del usuario.
function auth_url(string $ruta = ''): string
{
    return auth_base_url() . '/' . ltrim($ruta, '/');
}

function auth_redirigir(string $ruta): void
{
    header('Location: ' . auth_url($ruta));
    exit;
}

// Inicia la sesión con la configuración segura. Devuelve false si no se pudo
// (por ejemplo, si ya se había enviado salida): quien llama debe fallar cerrado.
function auth_iniciar_sesion(): bool
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return true;
    }
    if (headers_sent()) {
        return false;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.gc_maxlifetime', (string) AUTH_INACTIVIDAD_MAX);

    session_name(AUTH_COOKIE_NOMBRE);
    session_set_cookie_params([
        'lifetime' => 0,          // cookie de sesión: se borra al cerrar el navegador
        'path'     => '/',
        'domain'   => '',
        'secure'   => auth_es_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    // Cualquier aviso que PHP emita al abrir la sesión (por ejemplo, que el
    // recolector de sesiones viejas no pueda listar la carpeta de sesiones)
    // se registra en el log del servidor en lugar de imprimirse en la página:
    // una salida accidental rompería las cabeceras ("headers already sent").
    set_error_handler(static function (int $nivel, string $mensaje): bool {
        error_log('[auth] aviso de PHP al abrir la sesión: ' . $mensaje);
        return true;
    }, E_NOTICE | E_WARNING | E_DEPRECATED);
    try {
        $abierta = session_start();
    } finally {
        restore_error_handler();
    }
    if (!$abierta) {
        return false;
    }

    // Vencimiento de una sesión ya autenticada.
    if (isset($_SESSION['auth'])) {
        $ahora = time();
        $a     = $_SESSION['auth'];
        $vencida = !is_array($a)
            || !isset($a['rol'], $a['id'], $a['creada'], $a['ultima'])
            || ($ahora - (int) $a['ultima']) > AUTH_INACTIVIDAD_MAX
            || ($ahora - (int) $a['creada']) > AUTH_DURACION_MAX;

        if ($vencida) {
            // Se descarta la identidad y se rota el ID; queda una sesión anónima.
            $_SESSION = [];
            session_regenerate_id(true);
            $_SESSION['aviso'] = 'expirada';
        } else {
            $_SESSION['auth']['ultima'] = $ahora;
        }
    }

    return true;
}

// Cuenta autenticada de la sesión actual, o null si no hay ninguna.
function auth_usuario(): ?array
{
    $a = $_SESSION['auth'] ?? null;
    if (!is_array($a) || !isset($a['rol'], $a['id']) || !is_string($a['rol']) || !is_int($a['id'])) {
        return null;
    }
    return [
        'rol'    => $a['rol'],
        'id'     => $a['id'],
        'nombre' => is_string($a['nombre'] ?? null) ? $a['nombre'] : '',
    ];
}

// Marca la sesión como autenticada. Regenera el ID ANTES de guardar la
// identidad (evita la fijación de sesión) y descarta todo dato anterior.
function auth_registrar_login(string $rol, int $id, string $nombre): void
{
    session_regenerate_id(true);
    $_SESSION = [];
    $ahora = time();
    $_SESSION['auth'] = [
        'rol'    => $rol,
        'id'     => $id,
        'nombre' => $nombre,
        'creada' => $ahora,
        'ultima' => $ahora,
    ];
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

// Cierra la sesión por completo: vacía los datos, borra la cookie y destruye
// el archivo de sesión en el servidor.
function auth_destruir_sesion(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $p['path'],
            'domain'   => $p['domain'],
            'secure'   => $p['secure'],
            'httponly' => $p['httponly'],
            'samesite' => $p['samesite'] ?: 'Lax',
        ]);
    }
    session_destroy();
}
