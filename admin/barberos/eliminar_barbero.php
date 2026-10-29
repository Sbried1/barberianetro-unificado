<?php

require_once __DIR__ . '/../auth/guard.php';
auth_requerir('barberos');

include("../conexion.php");
include("barberos_funciones.php");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: barberos.php");
    exit;
}

$id_barbero = isset($_POST["id_barbero"]) ? (int) $_POST["id_barbero"] : 0;

if ($id_barbero <= 0) {
    header("Location: barberos.php?error=invalido");
    exit;
}

// Guardamos la ruta de la imagen ANTES de borrar el registro, para poder
// eliminarla del disco una vez que el borrado en la base sea exitoso.
$url_imagen_actual = obtener_url_imagen_barbero($conexion, $id_barbero);

$sql = "DELETE FROM barbero WHERE id_barbero = ?";
$consulta = mysqli_prepare($conexion, $sql);
mysqli_stmt_bind_param($consulta, "i", $id_barbero);

try {

    if (!mysqli_stmt_execute($consulta)) {
        throw new Exception("No se pudo eliminar el barbero.");
    }

    $filas_afectadas = mysqli_stmt_affected_rows($consulta);
    mysqli_stmt_close($consulta);

    if ($filas_afectadas === 0) {
        header("Location: barberos.php?error=no_existe");
        exit;
    }

    // El barbero se eliminó correctamente: ahora sí borramos su imagen.
    eliminar_imagen_barbero_fs($url_imagen_actual);

    header("Location: barberos.php?ok=eliminado");
    exit;

} catch (Exception $e) {
    // Si el barbero tiene reservas asociadas, la base de datos rechaza el
    // borrado por integridad referencial. Lo informamos de forma clara.
    header("Location: barberos.php?error=restriccion");
    exit;
}
