<?php

require_once __DIR__ . '/../auth/guard.php';
auth_requerir('servicios');

include("../conexion.php");

// =====================================================
// NOTA DE COMPATIBILIDAD: no existe tabla "categoria". "Renombrar una
// categoría" ahora significa actualizar el texto servicio.categoria en
// todos los servicios que tengan ese valor.
// =====================================================

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: servicios.php?modal=categorias");
    exit();
}

$categoria_actual = trim((string) ($_POST["categoria_actual"] ?? ""));
$nombre_nuevo = trim((string) ($_POST["nombre"] ?? ""));

if ($categoria_actual === "" || $nombre_nuevo === "" || mb_strlen($nombre_nuevo) > 100) {
    header("Location: servicios.php?modal=categorias&error=nombre_invalido");
    exit();
}

// Verificar que no exista ya otra categoría con ese nombre nuevo
$sql_existe = "SELECT id_servicio FROM servicio WHERE categoria = ? AND categoria <> ? LIMIT 1";
$stmt_existe = mysqli_prepare($conexion, $sql_existe);
mysqli_stmt_bind_param($stmt_existe, "ss", $nombre_nuevo, $categoria_actual);
mysqli_stmt_execute($stmt_existe);
$existe = mysqli_num_rows(mysqli_stmt_get_result($stmt_existe)) > 0;
mysqli_stmt_close($stmt_existe);

if ($existe) {
    header("Location: servicios.php?modal=categorias&error=categoria_existente");
    exit();
}

$sql_actualizar = "UPDATE servicio SET categoria = ? WHERE categoria = ?";
$stmt_actualizar = mysqli_prepare($conexion, $sql_actualizar);
mysqli_stmt_bind_param($stmt_actualizar, "ss", $nombre_nuevo, $categoria_actual);
mysqli_stmt_execute($stmt_actualizar);
mysqli_stmt_close($stmt_actualizar);

header("Location: servicios.php?modal=categorias");
exit();
