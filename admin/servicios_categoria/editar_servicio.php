<?php

require_once __DIR__ . '/../auth/guard.php';
auth_requerir('servicios');

include("../conexion.php");

$id_servicio = $_GET["id_servicio"] ?? "";

if (!ctype_digit((string) $id_servicio)) {
    die("Servicio inválido.");
}

$id_servicio = (int) $id_servicio;

$sql = "SELECT * FROM servicio WHERE id_servicio = ?";
$stmt = mysqli_prepare($conexion, $sql);
mysqli_stmt_bind_param($stmt, "i", $id_servicio);
mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);

$servicio = mysqli_fetch_assoc($resultado);
mysqli_stmt_close($stmt);
