<?php
/**
 * auth/guard.php
 *
 * GUARDIA de autenticación y autorización. Cada página y cada endpoint del
 * panel la incluye en su primera línea de código:
 *
 *     require_once __DIR__ . '/../auth/guard.php';
 *     auth_requerir('ventas');            // página o endpoint que redirige
 *     auth_requerir('reservas', 'json');  // endpoint AJAX: responde JSON
 *
 * Qué hace auth_requerir():
 *   1. Sin sesión válida: redirige al login (o responde 401 en JSON).
 *   2. Con sesión, pero con un rol sin permiso para ese módulo: responde 403
 *      (la decisión se toma en el SERVIDOR, no ocultando botones).
 *   3. En pedidos que modifican datos (POST): verifica el origen (CSRF).
 *   4. Envía cabeceras para que el navegador no guarde copias de la página
 *      (así, "Atrás" después de cerrar sesión no muestra datos).
 *   5. Libera el bloqueo de la sesión (session_write_close) antes de seguir.
 *
 * Todas las funciones llevan el prefijo "auth_" para no chocar con las
 * funciones de los módulos (volver(), post_texto(), venta_*, etc.).
 * Este archivo solo define funciones y no imprime nada al incluirse.
 */
require_once __DIR__ . '/permisos.php';
require_once __DIR__ . '/sesion.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/vista.php';
auth_bloquear_acceso_directo(__FILE__);

function auth_cabeceras_seguridad(): void
{
    if (headers_sent()) {
        return;
    }
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
}

// Falla cerrado con un mensaje genérico (nunca detalles técnicos).
function auth_error_interno(): void
{
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'No se pudo comprobar la sesión. Intentá nuevamente.';
    exit;
}

function auth_responder_json(int $codigo, string $error): void
{
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => $error]);
    exit;
}

// Página de rechazo (403) con la identidad visual del panel.
function auth_denegar_html(int $codigo, string $titulo, string $mensaje, ?array $usuario): void
{
    http_response_code($codigo);
    auth_vista_inicio($titulo);
    echo '        <h1 class="auth_titulo">' . auth_e($titulo) . "</h1>\n";
    echo '        <p class="auth_texto">' . auth_e($mensaje) . "</p>\n";
    if ($usuario !== null) {
        echo '        <a class="auth_btn" href="' . auth_e(auth_url(auth_inicio_por_rol($usuario['rol']))) . "\">Ir al inicio</a>\n";
        echo '        <form method="post" action="' . auth_e(auth_url('auth/logout.php')) . '" class="auth_form_inline">'
            . auth_csrf_campo()
            . '<button type="submit" class="auth_btn auth_btn_sec">Cerrar sesión</button></form>' . "\n";
    }
    auth_vista_fin();
    exit;
}

// Exige una sesión válida (cualquier rol). Devuelve la cuenta autenticada.
// $tipo: "pagina" (redirige al login) o "json" (responde 401 en JSON).
function auth_requerir_sesion(string $tipo = 'pagina'): array
{
    if (headers_sent()) {
        auth_error_interno();
    }
    auth_cabeceras_seguridad();

    // Sin cookie de sesión no hay nada que validar: no se crea ninguna sesión.
    $usuario = null;
    if (isset($_COOKIE[AUTH_COOKIE_NOMBRE])) {
        if (!auth_iniciar_sesion()) {
            auth_error_interno();
        }
        $usuario = auth_usuario();
    }

    if ($usuario === null) {
        if ($tipo === 'json') {
            auth_responder_json(401, 'no_autenticado');
        }
        auth_redirigir('auth/login.php');
    }

    // Pedidos que modifican datos: solo se aceptan desde este mismo sitio.
    if (!auth_origen_valido()) {
        if ($tipo === 'json') {
            auth_responder_json(403, 'origen_invalido');
        }
        auth_denegar_html(
            403,
            'Solicitud rechazada',
            'No se pudo comprobar que la solicitud proviene de este sitio. Volvé a la página e intentá de nuevo.',
            $usuario
        );
    }

    auth_csrf_token();      // asegura el token que usa el botón "Cerrar sesión"
    session_write_close();  // libera el bloqueo de la sesión

    return $usuario;
}

// Exige sesión Y permiso sobre el módulo indicado (ver auth/permisos.php).
function auth_requerir(string $modulo, string $tipo = 'pagina'): array
{
    $usuario = auth_requerir_sesion($tipo);

    if (!auth_rol_puede($usuario['rol'], $modulo)) {
        if ($tipo === 'json') {
            auth_responder_json(403, 'sin_permiso');
        }
        auth_denegar_html(
            403,
            'Sin permiso',
            'Tu cuenta (' . $usuario['rol'] . ') no tiene acceso a esta sección.',
            $usuario
        );
    }

    return $usuario;
}

// Dibuja el ítem "Cerrar sesión" del menú lateral con el mismo marcado que los
// demás ítems (<li><a>). El enlace pide confirmación (panel_ui.js) y envía un
// formulario POST con token CSRF; si el navegador no ejecuta JavaScript, el
// enlace abre una pantalla de confirmación que hace lo mismo.
// También carga los componentes compartidos, porque todas las páginas con
// menú pasan por acá y los scripts de cada módulo van después del menú.
function auth_render_logout_item(): void
{
    $accion = auth_e(auth_url('auth/logout.php'));
    echo '<link rel="stylesheet" href="' . auth_e(auth_url_versionada('Estilos_index/panel_ui.css')) . "\">\n";
    echo '<script src="' . auth_e(auth_url_versionada('panel_ui.js')) . "\"></script>\n";
    echo "<li>\n";
    echo '    <form id="auth_form_logout" action="' . $accion . '" method="post" style="display:none">' . auth_csrf_campo() . "</form>\n";
    echo '    <a href="' . $accion . '" data-panel-logout="auth_form_logout">' . "\n";
    echo "        Cerrar sesión\n";
    echo "    </a>\n";
    echo "</li>\n";
}

// URL de un recurso estático con su fecha de modificación, para que el
// navegador no siga usando una copia vieja después de un cambio.
function auth_url_versionada(string $ruta): string
{
    $fecha = @filemtime(__DIR__ . '/../' . $ruta);
    return auth_url($ruta) . '?v=' . (int) $fecha;
}
