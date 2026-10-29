<?php

require_once __DIR__ . '/../auth/guard.php';
auth_requerir('servicios');

include("../conexion.php");

// =====================================================
// NOTA DE COMPATIBILIDAD: no existe tabla "categoria" ni id_categoria.
// "Eliminar una categoría" ahora significa quitarle ese texto a todos
// los servicios que la tengan (categoria queda en NULL), ya que no hay
// una fila independiente que borrar.
// =====================================================

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: servicios.php?modal=categorias");
    exit();
}

$categoria = trim((string) ($_POST["categoria"] ?? ""));

if ($categoria === "") {
    header("Location: servicios.php?modal=categorias&error=categoria_invalida");
    exit();
}

$sql = "UPDATE servicio SET categoria = NULL WHERE categoria = ?";
$stmt = mysqli_prepare($conexion, $sql);
mysqli_stmt_bind_param($stmt, "s", $categoria);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

header("Location: servicios.php?modal=categorias");
exit();
