<?php
/**
 * auth/vista.php
 *
 * Marco HTML compartido por las pantallas de acceso (login, cierre de sesión,
 * página de inicio del barbero y mensaje de "sin permiso"). Usa la identidad
 * visual del panel (auth/auth.css toma la paleta de Estilos_index/global.css).
 * Solo define funciones.
 */
require_once __DIR__ . '/sesion.php';
auth_bloquear_acceso_directo(__FILE__);

// Escapa texto para HTML.
function auth_e($texto): string
{
    return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
}

// Abre la página: <html>, <head>, tarjeta central con el logo.
function auth_vista_inicio(string $titulo): void
{
    $css  = auth_e(auth_url('auth/auth.css'));
    $logo = auth_e(auth_url('img/' . rawurlencode('ChatGPT Image 15 jun 2026, 13_41_39.png')));

    echo "<!DOCTYPE html>\n";
    echo "<html lang=\"es\">\n<head>\n";
    echo "    <meta charset=\"UTF-8\">\n";
    echo "    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n";
    echo "    <meta name=\"robots\" content=\"noindex, nofollow\">\n";
    echo "    <title>" . auth_e($titulo) . " · Netro Barbería</title>\n";
    echo "    <link rel=\"stylesheet\" href=\"" . $css . "\">\n";
    echo "</head>\n<body class=\"auth_body\">\n";
    echo "    <main class=\"auth_caja\">\n";
    echo "        <div class=\"auth_marca\">\n";
    echo "            <img src=\"" . $logo . "\" alt=\"\" class=\"auth_logo\">\n";
    echo "            <span class=\"auth_marca_texto\">Netro</span>\n";
    echo "        </div>\n";
}

// Cierra la página.
function auth_vista_fin(): void
{
    echo "    </main>\n</body>\n</html>\n";
}

// Mensaje destacado. $tipo: "error", "ok" o "aviso".
function auth_vista_alerta(string $tipo, string $mensaje): void
{
    $clase = in_array($tipo, ['error', 'ok', 'aviso'], true) ? $tipo : 'aviso';
    echo "        <div class=\"auth_alerta auth_alerta_" . $clase . "\" role=\"alert\">" . auth_e($mensaje) . "</div>\n";
}
