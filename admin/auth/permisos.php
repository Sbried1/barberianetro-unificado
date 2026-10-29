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
 *   - Barbero: ningún módulo administrativo (Barberos y Ventas son
 *     exclusivos del gerente; Dashboard, Clientes, Servicios y Reservas
 *     tampoco se pueden dar en solo lectura sin modificar su lógica).
 *     Después de iniciar sesión, el barbero llega a auth/inicio.php.
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
    'dashboard' => [AUTH_ROL_GERENTE],
    'servicios' => [AUTH_ROL_GERENTE],
    'reservas'  => [AUTH_ROL_GERENTE],
    'clientes'  => [AUTH_ROL_GERENTE],
    'barberos'  => [AUTH_ROL_GERENTE],
    'ventas'    => [AUTH_ROL_GERENTE],
];

// ¿Puede este rol entrar a este módulo? Un módulo desconocido se deniega.
function auth_rol_puede(string $rol, string $modulo): bool
{
    return isset(AUTH_PERMISOS[$modulo]) && in_array($rol, AUTH_PERMISOS[$modulo], true);
}

// Página a la que se envía a cada rol después de iniciar sesión.
function auth_inicio_por_rol(string $rol): string
{
    return $rol === AUTH_ROL_GERENTE ? 'index.php' : 'auth/inicio.php';
}
