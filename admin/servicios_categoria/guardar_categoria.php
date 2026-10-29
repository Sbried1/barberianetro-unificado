<?php

/**
 * NOTA DE COMPATIBILIDAD IMPORTANTE:
 * La base de datos real NO tiene una tabla "categoria" independiente.
 * En la tabla "servicio" la columna "categoria" es un simple texto
 * libre (varchar), sin relación (FK) a ninguna otra tabla.
 *
 * Por eso ya no es posible crear una "categoría" que no esté asociada
 * a ningún servicio: no hay dónde guardarla. Las categorías que ve el
 * usuario ahora se calculan dinámicamente a partir de los valores ya
 * cargados en servicio.categoria (ver listar.php).
 *
 * Esta pantalla se mantiene para no romper el flujo existente, pero
 * ya no inserta nada: la categoría se crea automáticamente la primera
 * vez que se guarda un servicio con ese texto en su campo "Categoría".
 */

require_once __DIR__ . '/../auth/guard.php';
auth_requerir('servicios');

header("Location: servicios.php?modal=categorias&error=categoria_no_soportada");
exit();
