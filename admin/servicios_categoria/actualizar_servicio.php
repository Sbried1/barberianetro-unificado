<?php

require_once __DIR__ . '/../auth/guard.php';
auth_requerir('servicios');

include("../conexion.php");
require_once __DIR__ . "/servicios_funciones.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: servicios.php");
    exit();
}

// id_servicio es un int autoincremental: debe ser un entero positivo.
$id_servicio = servicio_post_id("id_servicio");

if ($id_servicio === false) {
    volver_a_servicios("error=servicio_invalido");
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
// EXISTENCIA + ACTUALIZACIÓN
// Funciona tanto si mysqli lanza excepciones (PHP 8.1+ por defecto) como
// si devuelve false.
// =====================================================

$errno = 0;

try {

    // El servicio debe existir (si no, se avisa en vez de "guardar" en silencio)
    $sql_existe = "SELECT id_servicio FROM servicio WHERE id_servicio = ?";
    $stmt_existe = mysqli_prepare($conexion, $sql_existe);
    mysqli_stmt_bind_param($stmt_existe, "i", $id_servicio);
    mysqli_stmt_execute($stmt_existe);
    $existe = mysqli_num_rows(mysqli_stmt_get_result($stmt_existe)) > 0;
    mysqli_stmt_close($stmt_existe);

    if (!$existe) {
        volver_a_servicios("error=no_existe");
    }

    // NOTA: servicio ya no tiene columna "estado", por eso no se actualiza.
    $sql = "UPDATE servicio SET categoria = ?, nombre = ?, duracion = ?, descripcion = ?, precio = ? WHERE id_servicio = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param(
        $stmt,
        "sssssi",
        $datos["categoria"],
        $datos["nombre"],
        $datos["duracion"],
        $datos["descripcion"],
        $datos["precio"],
        $id_servicio
    );

    if (!mysqli_stmt_execute($stmt)) {
        $errno = mysqli_stmt_errno($stmt) ?: -1;
        servicio_registrar_error_bd("actualizar_servicio.php", $errno, mysqli_stmt_error($stmt));
    }

    mysqli_stmt_close($stmt);

} catch (Throwable $e) {
    $errno = ($e instanceof mysqli_sql_exception) ? (int) $e->getCode() : -1;
    servicio_registrar_error_bd("actualizar_servicio.php", $errno, $e->getMessage());
}

if ($errno !== 0) {
    volver_a_servicios("error=bd");
}

volver_a_servicios("ok=actualizado");
