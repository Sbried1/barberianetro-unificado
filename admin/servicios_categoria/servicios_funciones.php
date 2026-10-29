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

// Redirige a servicios.php con un parámetro de resultado
// (?error=... o ?ok=...) y termina el script.
function volver_a_servicios($parametro) {
    header("Location: servicios.php?" . $parametro);
    exit();
}

// Lee un campo POST como texto. Si llega algo que no es un string (por
// ejemplo precio[]=1) lo trata como vacío en lugar de provocar un error.
function servicio_post_texto($clave) {
    $valor = $_POST[$clave] ?? "";
    return is_string($valor) ? trim($valor) : "";
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
// -----------------------------------------------------
function servicio_validar_datos($nombre, $categoria, $precio, $duracion, $descripcion) {

    if ($nombre === "" || mb_strlen($nombre) > 150) {
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

    if (mb_strlen($descripcion) > 255) {
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
