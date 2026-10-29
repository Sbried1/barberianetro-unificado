<?php

require_once __DIR__ . '/../auth/guard.php';
auth_requerir('servicios');

require "../conexion.php";

// NOTA DE COMPATIBILIDAD: no existe tabla "categoria". Las categorías
// se calculan a partir de los valores distintos de servicio.categoria.
$sql = "SELECT DISTINCT categoria FROM servicio WHERE categoria IS NOT NULL AND categoria <> '' ORDER BY categoria";

$resultado = mysqli_query($conexion, $sql);

while ($fila = mysqli_fetch_assoc($resultado)) {
    echo htmlspecialchars($fila["categoria"]) . "<br>";
}
