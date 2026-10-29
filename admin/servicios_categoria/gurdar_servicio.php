<?php

require_once __DIR__ . '/../auth/guard.php';
auth_requerir('servicios');

include("../conexion.php");
require_once __DIR__ . "/servicios_funciones.php";

// =====================================================
// NOTA DE COMPATIBILIDAD:
// - servicio.categoria es un texto libre (varchar), no un id_categoria
//   con FK a una tabla "categoria" (esa tabla no existe).
// - servicio.precio y servicio.duracion son varchar en la base real. Se
//   validan en el servidor (ver servicios_funciones.php): el precio debe
//   ser un entero y la duración un formato que Reservas entienda.
// - servicio NO tiene columna "estado": ya no existe Activo/Inactivo.
// =====================================================

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: servicios.php");
    exit();
}

// Validaciones de servidor (no depender solo de HTML/JS)
$validacion = servicio_validar_datos(
    servicio_post_texto("nombre"),
    servicio_post_texto("categoria"),
    servicio_post_texto("precio"),
    servicio_post_texto("duracion"),
    servicio_post_texto("descripcion")
);

if ($validacion["error"] !== null) {
    volver_a_servicios("error=" . $validacion["error"]);
}

$datos = $validacion["datos"];

// =====================================================
// INSERTAR SERVICIO
// id_servicio es AUTO_INCREMENT: no se envía.
// Funciona tanto si mysqli lanza excepciones (PHP 8.1+ por defecto) como
// si devuelve false.
// =====================================================

$errno = 0;

try {

    $sql = "INSERT INTO servicio (categoria, nombre, duracion, descripcion, precio) VALUES (?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param(
        $stmt,
        "sssss",
        $datos["categoria"],
        $datos["nombre"],
        $datos["duracion"],
        $datos["descripcion"],
        $datos["precio"]
    );

    if (!mysqli_stmt_execute($stmt)) {
        $errno = mysqli_stmt_errno($stmt) ?: -1;
        servicio_registrar_error_bd("gurdar_servicio.php", $errno, mysqli_stmt_error($stmt));
    }

    mysqli_stmt_close($stmt);

} catch (Throwable $e) {
    $errno = ($e instanceof mysqli_sql_exception) ? (int) $e->getCode() : -1;
    servicio_registrar_error_bd("gurdar_servicio.php", $errno, $e->getMessage());
}

if ($errno !== 0) {
    volver_a_servicios("error=bd");
}

volver_a_servicios("ok=agregado");
