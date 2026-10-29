<?php

// Conexión única a la base, compartida por la web de clientes y por el
// panel (/admin/conexion.php incluye este archivo).

$host = "mysql";
$usuario = "barberia";
$password = "barberia123";
$base = "barberia_db";

date_default_timezone_set('America/Montevideo');

$conexion = mysqli_connect($host, $usuario, $password, $base);

if (!$conexion) {
    die("Error de conexión: " . mysqli_connect_error());
}

mysqli_set_charset($conexion, 'utf8mb4');
mysqli_query($conexion, "SET time_zone = '-03:00'");
