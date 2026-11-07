<?php
/**
 * config/conexion.php
 *
 * Conexión única a MySQL, compartida por la web de clientes (public/sections)
 * y por el panel (public/admin). Está fuera de public/, así que no se puede
 * pedir por URL.
 *
 * Los datos de conexión salen del archivo .env (ver .env.example):
 *   DB_HOST, DB_NAME, DB_USER, DB_PASSWORD
 *
 * Deja la conexión en $conexion. Si faltan variables o MySQL rechaza la
 * conexión, lanza una excepción (login.php y los scripts de consola la
 * capturan para mostrar un mensaje propio).
 */

require_once __DIR__ . '/env.php';

date_default_timezone_set('America/Montevideo');

if (!function_exists('netro_conectar_db')) {
    function netro_conectar_db(): mysqli
    {
        $host     = netro_env('DB_HOST', '');
        $base     = netro_env('DB_NAME', '');
        $usuario  = netro_env('DB_USER', '');
        $clave    = netro_env('DB_PASSWORD', '');

        $faltan = [];
        foreach (['DB_HOST' => $host, 'DB_NAME' => $base, 'DB_USER' => $usuario] as $nombre => $valor) {
            if ($valor === '') {
                $faltan[] = $nombre;
            }
        }
        if ($faltan) {
            throw new RuntimeException('Faltan variables de la base de datos en el .env: ' . implode(', ', $faltan) . '.');
        }

        $conexion = mysqli_connect($host, $usuario, $clave, $base);
        if (!$conexion) {
            throw new RuntimeException('Error de conexión: ' . mysqli_connect_error());
        }

        mysqli_set_charset($conexion, 'utf8mb4');
        mysqli_query($conexion, "SET time_zone = '-03:00'");

        return $conexion;
    }
}

$conexion = netro_conectar_db();
