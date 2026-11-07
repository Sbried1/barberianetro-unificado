<?php

// =====================================================
// FUNCIONES COMPARTIDAS DEL MÓDULO SERVICIOS
// Las usan gurdar_servicio.php, actualizar_servicio.php y
// eliminar_servicio.php, para que las reglas de validación y la forma de
// responder vivan en un solo lugar.
//
// Columnas reales de la tabla "servicio" (barberia_db):
//   id_servicio (int, autoincremental), categoria varchar(100) NULL,
//   nombre varchar(150) NOT NULL, duracion varchar(50) NULL,
//   descripcion varchar(255) NULL, precio varchar(50) NULL.
//
// "precio" y "duracion" son varchar en la base, así que el formato se
// controla acá, en el servidor.
// =====================================================

// Mismo tope que ya tiene el campo "Precio" del formulario (max="999999").
define("SERVICIO_PRECIO_MAXIMO", 999999);

// Límites de texto. Entran en los varchar de la tabla (150 y 255): son más
// cortos para que nombres y descripciones se lean bien en listados y modales.
define("SERVICIO_NOMBRE_MAXIMO", 80);
define("SERVICIO_DESCRIPCION_MAXIMO", 250);

// Textos de cada código de error. Los usa servicios.php (recarga completa)
// y la respuesta JSON de los formularios enviados con JavaScript.
function servicio_mensajes_error() {
    return array(
        "nombre_invalido"      => "El nombre del servicio es obligatorio y no puede superar los " . SERVICIO_NOMBRE_MAXIMO . " caracteres.",
        "nombre_duplicado"     => "Ya existe un servicio con ese nombre. Elegí otro nombre o editá el servicio existente.",
        "categoria_invalida"   => "La categoría es obligatoria y no puede superar los 100 caracteres.",
        "precio_invalido"      => "El precio debe ser un número entero mayor a 0, sin puntos, comas ni símbolos (por ejemplo: 350).",
        "duracion_invalida"    => "La duración no es válida. Usá un formato como 30 min, 1h 30min, 1:30 o 90.",
        "descripcion_invalida" => "La descripción no puede superar los " . SERVICIO_DESCRIPCION_MAXIMO . " caracteres.",
        "servicio_invalido"    => "El servicio indicado no es válido.",
        "no_existe"            => "El servicio indicado no existe (puede haber sido eliminado).",
        "restriccion"          => "No se puede eliminar este servicio porque tiene reservas o ventas asociadas.",
        "bd"                   => "No se pudo completar la operación por un error del sistema. Intentá nuevamente."
    );
}

// ¿El formulario se envió con JavaScript (fetch) y espera JSON?
function servicio_es_ajax() {
    return isset($_SERVER["HTTP_X_PANEL_AJAX"]);
}

// Redirige a servicios.php con un parámetro de resultado
// (?error=... o ?ok=...) y termina el script. Si el formulario se envió con
// JavaScript responde JSON, para mostrar el error sin perder lo escrito.
function volver_a_servicios($parametro) {

    if (servicio_es_ajax()) {
        $es_error = strpos($parametro, "error=") === 0;
        $codigo = $es_error ? substr($parametro, 6) : "";
        $mensajes = servicio_mensajes_error();

        header("Content-Type: application/json; charset=utf-8");
        echo json_encode(array(
            "ok" => !$es_error,
            "error" => $es_error ? $codigo : null,
            "mensaje" => $es_error ? ($mensajes[$codigo] ?? $mensajes["bd"]) : null,
            "destino" => $es_error ? null : "servicios.php?" . $parametro
        ), JSON_UNESCAPED_UNICODE);
        exit();
    }

    header("Location: servicios.php?" . $parametro);
    exit();
}

// Lee un campo POST como texto. Si llega algo que no es un string (por
// ejemplo precio[]=1) lo trata como vacío en lugar de provocar un error.
function servicio_post_texto($clave) {
    $valor = $_POST[$clave] ?? "";
    return is_string($valor) ? trim($valor) : "";
}

// Nombre tal como se guarda: sin espacios al principio ni al final, y con
// los espacios repetidos colapsados. Devuelve null si la codificación es inválida.
function servicio_normalizar_nombre($nombre) {
    $limpio = preg_replace('/[\s\p{Z}]+/u', ' ', (string) $nombre);
    return $limpio === null ? null : trim($limpio);
}

// El navegador envía los saltos de línea como \r\n, pero el contador y el
// maxlength del textarea los cuentan como un solo carácter.
function servicio_normalizar_descripcion($descripcion) {
    return trim(str_replace(array("\r\n", "\r"), "\n", (string) $descripcion));
}

// ¿Dos nombres son el mismo, sin distinguir mayúsculas ni espacios?
function servicio_mismo_nombre($a, $b) {
    return mb_strtolower((string) servicio_normalizar_nombre($a), "UTF-8") === mb_strtolower((string) servicio_normalizar_nombre($b), "UTF-8");
}

// Serializa la comprobación de duplicados y el guardado siguiente: si el
// formulario llega dos veces a la vez, el segundo pedido espera al primero y
// ve el servicio que ya guardó. MySQL libera el bloqueo al cerrar la conexión.
// No se usa un índice UNIQUE porque hoy hay duplicados y la migración fallaría.
function servicio_bloquear_nombres($conexion) {
    $resultado = mysqli_query($conexion, "SELECT GET_LOCK('netro_servicio_nombre', 5) AS obtenido");
    return $resultado && (int) mysqli_fetch_assoc($resultado)["obtenido"] === 1;
}

// ¿Hay OTRO servicio con ese nombre? Compara sin mayúsculas ni espacios en los
// extremos; la intercalación de la columna (utf8mb4_general_ci) además ignora
// los acentos, así "Corte clasico" también choca con "Corte clásico".
function servicio_nombre_existe($conexion, $nombre, $id_excluir = 0) {
    $sql = "SELECT id_servicio FROM servicio WHERE LOWER(TRIM(nombre)) = LOWER(TRIM(?)) AND id_servicio <> ? LIMIT 1";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "si", $nombre, $id_excluir);
    mysqli_stmt_execute($stmt);
    $existe = mysqli_num_rows(mysqli_stmt_get_result($stmt)) > 0;
    mysqli_stmt_close($stmt);
    return $existe;
}

// Lee un id positivo enviado por POST. Devuelve el entero o false.
function servicio_post_id($clave) {
    return filter_var($_POST[$clave] ?? null, FILTER_VALIDATE_INT, array("options" => array("min_range" => 1)));
}

// -----------------------------------------------------
// PRECIO: solo números enteros, sin puntos, comas, espacios ni símbolos.
// Así "venta.total" (entero) nunca tiene que redondear un decimal en
// silencio. Devuelve el precio normalizado (texto) o null si es inválido.
// -----------------------------------------------------
function servicio_normalizar_precio($texto) {

    if (!preg_match('/^[0-9]{1,7}\z/', $texto)) {
        return null;
    }

    $numero = (int) $texto;

    if ($numero < 1 || $numero > SERVICIO_PRECIO_MAXIMO) {
        return null;
    }

    // (int) elimina ceros a la izquierda: "0350" se guarda como "350".
    return (string) $numero;
}

// -----------------------------------------------------
// DURACIÓN: solo formatos que el módulo Reservas interpreta bien
// (parsear_duracion_a_minutos en reservas/reservas_funciones.php):
//   "30 min", "30min", "45 minutos", "90"  -> minutos
//   "1h", "1 hora", "2 horas"              -> horas
//   "1h 30min", "1 hora 30 minutos"        -> horas y minutos
//   "1:30"                                 -> horas:minutos
// Devuelve la duración en minutos, o null si el formato no es válido.
//
// Es deliberadamente más estricta que el parser de Reservas: ese parser
// lee mal textos como "1h30" (1 minuto) o "1h 30" (60 minutos) sin avisar,
// por eso acá se rechazan.
// -----------------------------------------------------
function servicio_duracion_a_minutos($texto) {

    $limpio = preg_replace('/\s+/u', ' ', trim((string) $texto));

    if ($limpio === null) {
        return null;   // texto con codificación inválida
    }

    $limpio = mb_strtolower($limpio, "UTF-8");

    $unidad_min = '(?:m|min|mins|minuto|minutos)';
    $unidad_hora = '(?:h|hs|hr|hrs|hora|horas)';
    $minutos = null;

    if (preg_match('/^([0-9]{1,2}):([0-5][0-9])\z/', $limpio, $m)) {

        $minutos = ((int) $m[1] * 60) + (int) $m[2];

    } elseif (preg_match('/^[0-9]{1,4}\z/', $limpio)) {

        $minutos = (int) $limpio;   // número solo: minutos

    } elseif (preg_match('/^([0-9]{1,4}) ?' . $unidad_min . '\z/', $limpio, $m)) {

        $minutos = (int) $m[1];

    } elseif (preg_match('/^([0-9]{1,2}) ?' . $unidad_hora . '(?: ([0-9]{1,3}) ?' . $unidad_min . ')?\z/', $limpio, $m)) {

        $minutos = ((int) $m[1] * 60) + (isset($m[2]) ? (int) $m[2] : 0);
    }

    return ($minutos !== null && $minutos > 0) ? $minutos : null;
}

// -----------------------------------------------------
// Valida y normaliza los datos de un servicio (alta y edición usan lo
// mismo). Devuelve:
//   array("error" => "codigo_de_error", "datos" => null)   si algo falla
//   array("error" => null, "datos" => array(...))          si todo está bien
// Los códigos son los que servicios.php ya sabe mostrar.
//
// $actual (solo en edición) es la fila guardada. Un nombre o descripción
// anterior a estos límites sigue siendo válido mientras no se cambie, para
// poder editar otros campos sin que el sistema obligue a recortar el texto.
// -----------------------------------------------------
function servicio_validar_datos($nombre, $categoria, $precio, $duracion, $descripcion, $actual = null) {

    $nombre = servicio_normalizar_nombre($nombre);
    $descripcion = servicio_normalizar_descripcion($descripcion);

    if ($nombre === null || !mb_check_encoding($descripcion, "UTF-8")) {
        return array("error" => "nombre_invalido", "datos" => null);
    }

    $nombre_sin_cambios = $actual !== null && servicio_mismo_nombre($nombre, $actual["nombre"]);
    $descripcion_sin_cambios = $actual !== null && $descripcion === servicio_normalizar_descripcion($actual["descripcion"] ?? "");

    if ($nombre === "" || (mb_strlen($nombre) > SERVICIO_NOMBRE_MAXIMO && !$nombre_sin_cambios)) {
        return array("error" => "nombre_invalido", "datos" => null);
    }

    if ($categoria === "" || mb_strlen($categoria) > 100) {
        return array("error" => "categoria_invalida", "datos" => null);
    }

    $precio_normalizado = servicio_normalizar_precio($precio);

    if ($precio_normalizado === null) {
        return array("error" => "precio_invalido", "datos" => null);
    }

    // Se guarda con los espacios repetidos colapsados ("1h   30min" -> "1h 30min").
    $duracion_limpia = preg_replace('/\s+/u', ' ', $duracion);

    if (
        $duracion_limpia === null ||
        $duracion_limpia === "" ||
        mb_strlen($duracion_limpia) > 50 ||
        servicio_duracion_a_minutos($duracion_limpia) === null
    ) {
        return array("error" => "duracion_invalida", "datos" => null);
    }

    if (mb_strlen($descripcion) > SERVICIO_DESCRIPCION_MAXIMO && !$descripcion_sin_cambios) {
        return array("error" => "descripcion_invalida", "datos" => null);
    }

    return array(
        "error" => null,
        "datos" => array(
            "nombre" => $nombre,
            "categoria" => $categoria,
            "precio" => $precio_normalizado,
            "duracion" => $duracion_limpia,
            "descripcion" => $descripcion
        )
    );
}

// -----------------------------------------------------
// Registra en el log de PHP un error inesperado de la base de datos.
// $errno es el número de error de MySQL (-1 si no es un error de mysqli).
// -----------------------------------------------------
function servicio_registrar_error_bd($archivo, $errno, $mensaje) {
    // Solo código y mensaje del error: nunca se registran datos del formulario.
    error_log($archivo . " [" . $errno . "]: " . $mensaje);
}
