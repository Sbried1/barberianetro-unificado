<?php
/**
 * auth/permisos.php
 *
 * Roles del sistema y matriz de permisos. Es el ÚNICO lugar donde se decide
 * qué rol puede entrar a qué módulo. Solo define constantes y funciones:
 * no imprime nada.
 *
 * El rol de una cuenta lo decide el servidor según la tabla donde se validó
 * (gerente / barbero). Nunca viene de un formulario.
 *
 * MATRIZ APROBADA:
 *   - Gerente: todos los módulos.
 *   - Barbero: los mismos módulos que el gerente EXCEPTO "barberos" (la
 *     gestión de barberos es exclusiva del gerente), y SIN permiso para
 *     eliminar registros (permisos "*_eliminar"); sí puede cancelar
 *     reservas. Además conserva "mis_reservas" (auth/inicio.php: ver y
 *     confirmar SUS reservas).
 *     Después de iniciar sesión, los dos roles llegan al Dashboard.
 *
 * El menú lateral (auth_render_menu en auth/guard.php) usa esta misma
 * matriz para decidir qué enlaces mostrar, pero la protección real es
 * auth_requerir() en cada página y endpoint.
 *
 * Para habilitar un módulo a otro rol en el futuro, se agrega el rol a la
 * lista del módulo. Un módulo que NO figura en la matriz se deniega siempre
 * (denegado por defecto).
 */

// Bloquea el acceso directo por URL a este archivo (solo se puede incluir).
if (!function_exists('auth_bloquear_acceso_directo')) {
    function auth_bloquear_acceso_directo(string $archivo): void
    {
        $script = isset($_SERVER['SCRIPT_FILENAME']) ? realpath($_SERVER['SCRIPT_FILENAME']) : false;
        if ($script !== false && $script === realpath($archivo)) {
            http_response_code(404);
            exit;
        }
    }
}
auth_bloquear_acceso_directo(__FILE__);

const AUTH_ROL_GERENTE = 'gerente';
const AUTH_ROL_BARBERO = 'barbero';

const AUTH_PERMISOS = [
    'dashboard' => [AUTH_ROL_GERENTE, AUTH_ROL_BARBERO],
    'servicios' => [AUTH_ROL_GERENTE, AUTH_ROL_BARBERO],
    'reservas'  => [AUTH_ROL_GERENTE, AUTH_ROL_BARBERO],
    'clientes'  => [AUTH_ROL_GERENTE, AUTH_ROL_BARBERO],
    'barberos'  => [AUTH_ROL_GERENTE],
    'ventas'    => [AUTH_ROL_GERENTE, AUTH_ROL_BARBERO],
    'configuracion' => [AUTH_ROL_GERENTE, AUTH_ROL_BARBERO],
    'mis_reservas'  => [AUTH_ROL_BARBERO],

    // Eliminar registros: solo el gerente. El barbero usa estos módulos,
    // pero no puede borrar (sí puede cancelar reservas: eso es "reservas").
    'clientes_eliminar'  => [AUTH_ROL_GERENTE],
    'reservas_eliminar'  => [AUTH_ROL_GERENTE],
    'ventas_eliminar'    => [AUTH_ROL_GERENTE],
    'servicios_eliminar' => [AUTH_ROL_GERENTE],   // servicios y categorías
];

// ¿Puede este rol entrar a este módulo? Un módulo desconocido se deniega.
function auth_rol_puede(string $rol, string $modulo): bool
{
    return isset(AUTH_PERMISOS[$modulo]) && in_array($rol, AUTH_PERMISOS[$modulo], true);
}

// Página a la que se envía a cada rol después de iniciar sesión.
function auth_inicio_por_rol(string $rol): string
{
    return auth_rol_puede($rol, 'dashboard') ? 'index.php' : 'auth/inicio.php';
}
