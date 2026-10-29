<?php
/**
 * barberos_funciones.php
 * Funciones auxiliares compartidas por guardar_barbero.php y
 * actualizar_barbero.php. Solo validaciones y consultas relacionadas.
 * No maneja salida HTML.
 *
 * NOTA DE COMPATIBILIDAD: la tabla "barbero" real (barberia_db) tiene
 * estas columnas: id_barbero, contrasena, url_imagen, nombre, apellido,
 * especialidad (texto libre, varchar(50), opcional), en_netro_desde
 * (int, opcional), estado ('Disponible' / 'No disponible') y
 * anio_inicio (year, OBLIGATORIO).
 * NO existe la tabla "especialidad" ni la columna "id_especialidad", y
 * tampoco telefono, email ni calificacion.
 */

// Estados válidos que puede tener un barbero (coinciden con el ENUM real).
function estados_validos_barbero() {
    return ["Disponible", "No disponible"];
}

// Valida longitud mínima de contraseña.
function validar_password_barbero($password) {
    return strlen($password) >= 6;
}

// Devuelve true si el id_barbero realmente existe en la base de datos.
function existe_barbero($conexion, $id_barbero) {
    $sql = "SELECT id_barbero FROM barbero WHERE id_barbero = ? LIMIT 1";
    $consulta = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($consulta, "i", $id_barbero);
    mysqli_stmt_execute($consulta);
    $resultado = mysqli_stmt_get_result($consulta);
    $existe = mysqli_num_rows($resultado) > 0;
    mysqli_stmt_close($consulta);
    return $existe;
}

// =====================================================
// LECTURA Y VALIDACIÓN DE LOS CAMPOS DE TEXTO / AÑO
// =====================================================

// Lee un campo POST como texto. Si llega algo que no es un string (por
// ejemplo nombre[]=x) lo trata como vacío en lugar de provocar un error.
// $recortar = false se usa para la contraseña, que no debe perder espacios.
function post_texto_barbero($clave, $recortar = true) {
    $valor = $_POST[$clave] ?? "";

    if (!is_string($valor)) {
        return "";
    }

    return $recortar ? trim($valor) : $valor;
}

// Largo máximo de barbero.nombre y barbero.apellido (varchar(100) en la base).
function largo_maximo_nombre_barbero() {
    return 100;
}

// Largo máximo de barbero.especialidad (varchar(50) en la base).
function largo_maximo_especialidad_barbero() {
    return 50;
}

// "especialidad" es un texto libre y opcional (no hay tabla de
// especialidades). Se recortan los espacios de los extremos y se colapsan
// los repetidos. Devuelve:
//   ["ok" => true,  "valor" => null]      si viene vacía (se guarda NULL)
//   ["ok" => true,  "valor" => "texto"]   si es válida
//   ["ok" => false, "valor" => null]      si supera el largo máximo o tiene
//                                         una codificación inválida
function validar_especialidad_barbero($texto) {

    $limpia = preg_replace('/\s+/u', ' ', trim($texto));

    if ($limpia === null) {
        return ["ok" => false, "valor" => null];
    }

    if ($limpia === "") {
        return ["ok" => true, "valor" => null];
    }

    if (mb_strlen($limpia) > largo_maximo_especialidad_barbero()) {
        return ["ok" => false, "valor" => null];
    }

    return ["ok" => true, "valor" => $limpia];
}

// Los dos años del barbero son datos distintos:
//   anio_inicio:    año en que empezó a trabajar como barbero (la web de
//                   clientes calcula con él los "años de experiencia").
//   en_netro_desde: año en que entró a Netro (opcional).
// Rango aceptado para los dos: de 1950 al año actual.
function anio_inicio_minimo_barbero() {
    return 1950;
}

function anio_inicio_maximo_barbero() {
    return (int) date("Y");
}

// Un barbero no puede entrar a Netro antes de haber empezado como barbero.
function anios_barbero_coherentes($en_netro_desde, $anio_inicio) {
    return $en_netro_desde === null || $en_netro_desde >= $anio_inicio;
}

// "anio_inicio" es OBLIGATORIO en la base (year NOT NULL, sin valor por
// defecto). Devuelve el año como entero, o null si falta o es inválido.
function validar_anio_inicio_barbero($texto) {

    if (!preg_match('/^[0-9]{4}\z/', $texto)) {
        return null;
    }

    $anio = (int) $texto;

    if ($anio < anio_inicio_minimo_barbero() || $anio > anio_inicio_maximo_barbero()) {
        return null;
    }

    return $anio;
}

// =====================================================
// IMAGEN DE PERFIL DEL BARBERO
// La columna url_imagen sigue siendo la misma (no se agrega nada a la
// base de datos); ahora en vez de recibir una URL escrita a mano, el
// archivo se sube, se valida, se guarda físicamente en img/barberos/ y
// se persiste solo la RUTA RELATIVA del proyecto (ej: "img/barberos/x.jpg").
//
// Las rutas de disco se calculan siempre en base a __DIR__ (ubicación
// real de este archivo), nunca hardcodeadas, para que el proyecto
// funcione igual en Debian, XAMPP, WAMP, etc. Este archivo vive en
// la carpeta "barberos/", por lo tanto la carpeta de imágenes física
// es "__DIR__/../img/barberos/".
// =====================================================

// Nombre del usuario del sistema con el que corre PHP (en Docker suele ser
// www-data). Solo se usa para dejar un mensaje útil en el log de errores.
function usuario_php_actual_barbero() {
    if (function_exists("posix_geteuid") && function_exists("posix_getpwuid")) {
        $info = posix_getpwuid(posix_geteuid());
        if (is_array($info) && isset($info["name"])) {
            return $info["name"];
        }
    }
    return "desconocido";
}

// Carpeta física (en disco) donde se guardan las imágenes de barberos.
function carpeta_imagenes_barbero_fs() {
    return __DIR__ . "/../img/barberos/";
}

// Prefijo de ruta relativa al proyecto que se guarda en la base de datos.
function carpeta_imagenes_barbero_relativa() {
    return "img/barberos/";
}

// Tipos MIME de imagen permitidos y la extensión que se les asigna al
// guardarlas. Se valida el tipo REAL del archivo (con getimagesize),
// no la extensión que traiga el nombre original.
function tipos_imagen_permitidos_barbero() {
    return [
        "image/jpeg" => "jpg",
        "image/png"  => "png",
        "image/webp" => "webp",
    ];
}

// Tamaño máximo permitido para la imagen: 2 MB.
function tamano_maximo_imagen_barbero() {
    return 2 * 1024 * 1024;
}

/**
 * Valida y guarda en disco la imagen subida (elemento de $_FILES, ej.
 * $_FILES["imagen"] ?? null).
 *
 * Devuelve siempre un array:
 *   ["ok" => bool, "ruta" => string|null, "error" => string|null]
 *
 * - Si no se subió ningún archivo (campo opcional vacío), devuelve
 *   ok = true, ruta = null, error = null (no es un error, no reemplaza nada).
 * - Si se subió un archivo válido, lo guarda en img/barberos/ con un
 *   nombre único y devuelve ok = true, ruta = "img/barberos/xxxx.ext".
 * - Si algo falla, devuelve ok = false y un código de error apto para
 *   usar en "barberos.php?error=...".
 */
function procesar_imagen_barbero($archivo) {

    // Campo opcional: si no viene archivo, no es un error.
    if (!isset($archivo) || !is_array($archivo) || $archivo["error"] === UPLOAD_ERR_NO_FILE) {
        return ["ok" => true, "ruta" => null, "error" => null];
    }

    // El archivo superó upload_max_filesize (php.ini) o el MAX_FILE_SIZE del
    // formulario: es un problema de tamaño, no un error genérico de subida.
    if ($archivo["error"] === UPLOAD_ERR_INI_SIZE || $archivo["error"] === UPLOAD_ERR_FORM_SIZE) {
        return ["ok" => false, "ruta" => null, "error" => "imagen_tamano"];
    }

    if ($archivo["error"] !== UPLOAD_ERR_OK) {
        $codigo_subida = is_int($archivo["error"]) ? $archivo["error"] : "desconocido";
        error_log("[barberos] PHP rechazó la subida de la imagen (código de error de subida: " . $codigo_subida . ").");
        return ["ok" => false, "ruta" => null, "error" => "imagen_error"];
    }

    if ($archivo["size"] > tamano_maximo_imagen_barbero()) {
        return ["ok" => false, "ruta" => null, "error" => "imagen_tamano"];
    }

    // Validación REAL de imagen: getimagesize() lee la cabecera del
    // archivo. Si no es una imagen válida (aunque tenga extensión .jpg),
    // esto falla. Así evitamos que suban, por ejemplo, un .php disfrazado.
    $info = @getimagesize($archivo["tmp_name"]);
    if ($info === false) {
        return ["ok" => false, "ruta" => null, "error" => "imagen_invalida"];
    }

    $mime = $info["mime"];
    $permitidos = tipos_imagen_permitidos_barbero();
    if (!isset($permitidos[$mime])) {
        return ["ok" => false, "ruta" => null, "error" => "imagen_invalida"];
    }

    $extension = $permitidos[$mime];
    $nombre_archivo = uniqid("barbero_", true) . "_" . bin2hex(random_bytes(4)) . "." . $extension;

    $carpeta_fs = carpeta_imagenes_barbero_fs();

    // IMPORTANTE: mkdir() y move_uploaded_file() se llaman con @ a propósito.
    // Si fallan (por ejemplo "Permission denied" en Docker), PHP genera un
    // warning que, con display_errors activo, se IMPRIME en la respuesta y
    // hace fallar el header("Location: ...") posterior con "headers already
    // sent". Aquí el fallo se maneja devolviendo un código de error, y la
    // causa real queda registrada con error_log() (no se muestra al usuario).
    if (!is_dir($carpeta_fs)) {
        error_clear_last();
        if (!@mkdir($carpeta_fs, 0775, true) && !is_dir($carpeta_fs)) {
            $detalle = error_get_last();
            error_log("[barberos] No se pudo crear la carpeta de imágenes '" . $carpeta_fs
                . "' (usuario de PHP: " . usuario_php_actual_barbero() . "): "
                . ($detalle["message"] ?? "motivo desconocido"));
            return ["ok" => false, "ruta" => null, "error" => "imagen_carpeta"];
        }
    }

    // Comprobación previa: si la carpeta existe pero el usuario de PHP no
    // puede escribir en ella (típico de un bind-mount de Docker cuyo dueño
    // es otro usuario), se informa sin intentar mover el archivo.
    if (!is_writable($carpeta_fs)) {
        error_log("[barberos] La carpeta de imágenes '" . $carpeta_fs
            . "' no es escribible para el usuario de PHP (" . usuario_php_actual_barbero()
            . "). Revisar dueño y permisos de la carpeta en el contenedor.");
        return ["ok" => false, "ruta" => null, "error" => "imagen_carpeta"];
    }

    $destino_fs = $carpeta_fs . $nombre_archivo;

    error_clear_last();
    if (!@move_uploaded_file($archivo["tmp_name"], $destino_fs)) {
        $detalle = error_get_last();
        error_log("[barberos] No se pudo guardar la imagen en '" . $destino_fs
            . "' (usuario de PHP: " . usuario_php_actual_barbero() . "): "
            . ($detalle["message"] ?? "motivo desconocido"));
        return ["ok" => false, "ruta" => null, "error" => "imagen_guardar"];
    }

    $ruta_relativa = carpeta_imagenes_barbero_relativa() . $nombre_archivo;

    return ["ok" => true, "ruta" => $ruta_relativa, "error" => null];
}

/**
 * Elimina físicamente del disco una imagen de barbero, dada su ruta
 * relativa tal cual se guarda en barbero.url_imagen (ej:
 * "img/barberos/abc123.jpg"). No hace nada si la ruta viene vacía, si
 * el archivo no existe, o si la ruta no pertenece a la carpeta de
 * imágenes de barberos (medida de seguridad ante rutas inesperadas).
 */
function eliminar_imagen_barbero_fs($ruta_relativa) {

    if (!$ruta_relativa) {
        return;
    }

    $prefijo = carpeta_imagenes_barbero_relativa();
    if (strpos($ruta_relativa, $prefijo) !== 0) {
        return;
    }

    $nombre_archivo = basename($ruta_relativa);
    $ruta_fs = carpeta_imagenes_barbero_fs() . $nombre_archivo;

    if (is_file($ruta_fs)) {
        @unlink($ruta_fs);
    }
}

// Devuelve la url_imagen guardada actualmente para un barbero. Se usa
// al editar (para conservarla si no se sube una nueva) y al reemplazar
// o eliminar (para saber qué archivo borrar del disco).
function obtener_url_imagen_barbero($conexion, $id_barbero) {
    $sql = "SELECT url_imagen FROM barbero WHERE id_barbero = ? LIMIT 1";
    $consulta = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($consulta, "i", $id_barbero);
    mysqli_stmt_execute($consulta);
    $resultado = mysqli_stmt_get_result($consulta);
    $fila = mysqli_fetch_assoc($resultado);
    mysqli_stmt_close($consulta);
    return $fila ? $fila["url_imagen"] : null;
}
