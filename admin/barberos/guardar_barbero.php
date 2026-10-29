<?php

require_once __DIR__ . '/../auth/guard.php';
auth_requerir('barberos');

include("../conexion.php");
include("barberos_funciones.php");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: barberos.php");
    exit;
}

// =====================================================
// LECTURA Y LIMPIEZA BÁSICA
// =====================================================
$nombre         = post_texto_barbero("nombre");
$apellido       = post_texto_barbero("apellido");
$especialidad   = post_texto_barbero("especialidad");
$en_netro_desde = post_texto_barbero("en_netro_desde");
$anio_inicio    = post_texto_barbero("anio_inicio");
$password       = post_texto_barbero("password", false);
$estado         = post_texto_barbero("estado");

// =====================================================
// VALIDACIONES
// =====================================================
if ($nombre === "" || $apellido === "" || $password === "") {
    header("Location: barberos.php?error=invalido");
    exit;
}

if (mb_strlen($nombre) > largo_maximo_nombre_barbero() || mb_strlen($apellido) > largo_maximo_nombre_barbero()) {
    header("Location: barberos.php?error=invalido");
    exit;
}

if (!validar_password_barbero($password)) {
    header("Location: barberos.php?error=password_corta");
    exit;
}

if (!in_array($estado, estados_validos_barbero(), true)) {
    $estado = "Disponible";
}

// especialidad: texto libre y opcional (máx. 50). No es una FK: la tabla
// "especialidad" y la columna "id_especialidad" no existen en la base real.
$validacion_especialidad = validar_especialidad_barbero($especialidad);
if (!$validacion_especialidad["ok"]) {
    header("Location: barberos.php?error=invalido");
    exit;
}
$especialidad_valor = $validacion_especialidad["valor"];

// en_netro_desde: año opcional (ej: 2020). Se guarda como int(11).
$en_netro_desde_valor = null;
if ($en_netro_desde !== "") {
    if (!ctype_digit($en_netro_desde) || (int) $en_netro_desde < 1950 || (int) $en_netro_desde > (int) date("Y")) {
        header("Location: barberos.php?error=invalido");
        exit;
    }
    $en_netro_desde_valor = (int) $en_netro_desde;
}

// anio_inicio: OBLIGATORIO (year NOT NULL, sin valor por defecto en la base).
$anio_inicio_valor = validar_anio_inicio_barbero($anio_inicio);
if ($anio_inicio_valor === null) {
    header("Location: barberos.php?error=invalido");
    exit;
}

if (!anios_barbero_coherentes($en_netro_desde_valor, $anio_inicio_valor)) {
    header("Location: barberos.php?error=anios");
    exit;
}

// =====================================================
// IMAGEN (opcional): se valida que sea una imagen real (no solo por
// extensión), con tamaño máximo de 2MB, y se guarda físicamente en
// img/barberos/. En la base de datos solo se guarda la ruta relativa
// (ej: "img/barberos/archivo.jpg"), la columna url_imagen no cambió.
// =====================================================
$resultado_imagen = procesar_imagen_barbero($_FILES["imagen"] ?? null);
if (!$resultado_imagen["ok"]) {
    header("Location: barberos.php?error=" . $resultado_imagen["error"]);
    exit;
}
$url_imagen_valor = $resultado_imagen["ruta"];

// =====================================================
// INSERCIÓN
// id_barbero es AUTO_INCREMENT: no se envía.
// Funciona tanto si mysqli lanza excepciones (PHP 8.1+ por defecto) como
// si devuelve false: en ambos casos, si el alta falla, no se deja la
// imagen recién subida huérfana en el disco.
// =====================================================
$password_hash = password_hash($password, PASSWORD_DEFAULT);

$guardado = false;

try {

    $sql = "INSERT INTO barbero (contrasena, url_imagen, nombre, apellido, especialidad, en_netro_desde, anio_inicio, estado)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $consulta = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param(
        $consulta,
        "sssssiis",
        $password_hash,
        $url_imagen_valor,
        $nombre,
        $apellido,
        $especialidad_valor,
        $en_netro_desde_valor,
        $anio_inicio_valor,
        $estado
    );

    $guardado = mysqli_stmt_execute($consulta);

    if (!$guardado) {
        error_log("guardar_barbero.php [" . mysqli_stmt_errno($consulta) . "]: " . mysqli_stmt_error($consulta));
    }

    mysqli_stmt_close($consulta);

} catch (Throwable $e) {
    // Solo código y mensaje del error: nunca se registran datos del formulario.
    $codigo = ($e instanceof mysqli_sql_exception) ? (int) $e->getCode() : -1;
    error_log("guardar_barbero.php [" . $codigo . "]: " . $e->getMessage());
}

if ($guardado) {
    header("Location: barberos.php?ok=agregado");
    exit;
}

eliminar_imagen_barbero_fs($url_imagen_valor);
header("Location: barberos.php?error=guardar");
exit;
