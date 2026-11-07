<?php
/**
 * config/env.php
 *
 * Lee el archivo .env de la raíz del proyecto con vlucas/phpdotenv
 * (instalado con Composer en vendor/). Solo define funciones.
 *
 *  - Si una variable ya existe en el entorno (por ejemplo, porque Docker la
 *    cargó con env_file), NO se pisa: manda el entorno real.
 *  - Si el archivo .env no existe, no falla al cargarlo: cada variable que
 *    falte se detecta donde se usa (ver config/conexion.php).
 *  - Se usa el modo "unsafe" de phpdotenv para que las variables también se
 *    puedan leer con getenv() (lo usa mailer.php para SMTP_USER/SMTP_PASS).
 */

if (!function_exists('netro_env')) {

    require_once __DIR__ . '/../vendor/autoload.php';

    // Carga el .env una sola vez por pedido.
    function netro_cargar_env(): void
    {
        static $cargado = false;
        if ($cargado) {
            return;
        }
        $cargado = true;
        Dotenv\Dotenv::createUnsafeImmutable(dirname(__DIR__))->safeLoad();
    }

    // Valor de una variable de entorno, o $defecto si no está definida.
    function netro_env(string $clave, ?string $defecto = null): ?string
    {
        netro_cargar_env();

        if (isset($_ENV[$clave]) && is_string($_ENV[$clave])) {
            return $_ENV[$clave];
        }
        if (isset($_SERVER[$clave]) && is_string($_SERVER[$clave])) {
            return $_SERVER[$clave];
        }
        $valor = getenv($clave);
        return $valor === false ? $defecto : $valor;
    }
}
