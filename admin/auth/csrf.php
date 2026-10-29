<?php
/**
 * auth/csrf.php
 *
 * Protección CSRF. Solo define funciones: no imprime nada.
 *
 * Dos mecanismos complementarios:
 *
 * 1) TOKEN por sesión, para los formularios NUEVOS (login y logout):
 *    auth_csrf_campo() lo agrega al formulario y auth_csrf_valido() lo
 *    verifica en el servidor con hash_equals().
 *
 * 2) VERIFICACIÓN DE ORIGEN para todo pedido que modifica datos (POST), que
 *    aplica la guardia (auth_requerir) a los endpoints existentes de los
 *    módulos sin tener que editar sus formularios:
 *      - Si el navegador envía Sec-Fetch-Site, tiene que ser "same-origin".
 *      - Si envía Origin, tiene que coincidir con el sitio actual.
 *      - Si no hay Origin, se mira Referer.
 *      - Si no hay ninguno de los dos, se rechaza (falla cerrado).
 *    Se combina con la cookie SameSite=Lax. Es una defensa más débil que un
 *    token en cada formulario, pero cubre los formularios existentes sin
 *    modificarlos.
 */
require_once __DIR__ . '/sesion.php';
auth_bloquear_acceso_directo(__FILE__);

// Token de la sesión actual (se crea si no existe).
function auth_csrf_token(): string
{
    if (empty($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

// Campo oculto listo para pegar dentro de un <form method="post">.
function auth_csrf_campo(): string
{
    return '<input type="hidden" name="csrf" value="' . htmlspecialchars(auth_csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

// ¿El token enviado coincide con el de la sesión?
function auth_csrf_valido($enviado): bool
{
    return is_string($enviado)
        && $enviado !== ''
        && isset($_SESSION['csrf'])
        && is_string($_SESSION['csrf'])
        && hash_equals($_SESSION['csrf'], $enviado);
}

// "esquema://host[:puerto]" normalizado (sin puerto por defecto, en minúsculas).
function auth_normalizar_origen(string $esquema, string $host, $puerto = null): string
{
    $esquema = strtolower($esquema);
    $host    = strtolower($host);
    $puerto  = ($puerto === null || $puerto === '') ? null : (int) $puerto;
    if ($puerto !== null && !(($esquema === 'http' && $puerto === 80) || ($esquema === 'https' && $puerto === 443))) {
        return $esquema . '://' . $host . ':' . $puerto;
    }
    return $esquema . '://' . $host;
}

// Origen propio de este pedido, según el esquema y el encabezado Host.
function auth_origen_propio(): string
{
    $esquema = auth_es_https() ? 'https' : 'http';
    $hostHdr = (string) ($_SERVER['HTTP_HOST'] ?? '');
    $partes  = parse_url($esquema . '://' . $hostHdr);
    if ($partes === false || empty($partes['host'])) {
        return '';
    }
    return auth_normalizar_origen($esquema, $partes['host'], $partes['port'] ?? null);
}

// Origen declarado por una URL (valor de Origin o de Referer), o "" si no es válida.
function auth_origen_de_url(string $url): string
{
    if ($url === '' || strtolower($url) === 'null') {
        return '';
    }
    $p = parse_url($url);
    if ($p === false || empty($p['scheme']) || empty($p['host'])) {
        return '';
    }
    return auth_normalizar_origen($p['scheme'], $p['host'], $p['port'] ?? null);
}

// ¿El pedido proviene de este mismo sitio? Solo se exige en los métodos que
// modifican datos; GET, HEAD y OPTIONS pasan.
function auth_origen_valido(): bool
{
    $metodo = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if (in_array($metodo, ['GET', 'HEAD', 'OPTIONS'], true)) {
        return true;
    }

    $propio = auth_origen_propio();
    if ($propio === '') {
        return false;
    }

    $sfs = $_SERVER['HTTP_SEC_FETCH_SITE'] ?? null;
    if ($sfs !== null && $sfs !== 'same-origin') {
        return false;
    }

    if (isset($_SERVER['HTTP_ORIGIN'])) {
        return hash_equals($propio, auth_origen_de_url((string) $_SERVER['HTTP_ORIGIN']));
    }

    if (isset($_SERVER['HTTP_REFERER'])) {
        return hash_equals($propio, auth_origen_de_url((string) $_SERVER['HTTP_REFERER']));
    }

    // Sin Origin ni Referer: solo se acepta si el navegador declaró same-origin.
    return $sfs === 'same-origin';
}
