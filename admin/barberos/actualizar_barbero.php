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
// id_barbero es un int autoincremental: debe ser un entero positivo.
$id_barbero     = filter_var($_POST["id_barbero"] ?? null, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
$nombre         = post_texto_barbero("nombre");
$apellido       = post_texto_barbero("apellido");
$especialidad   = post_texto_barbero("especialidad");
$en_netro_desde = post_texto_barbero("en_netro_desde");
$anio_inicio    = post_texto_barbero("anio_inicio");
$password       = post_texto_barbero("password", false); // opcional
$estado         = post_texto_barbero("estado");

// =====================================================
// VALIDACIONES
// =====================================================
if ($id_barbero === false || $nombre === "" || $apellido === "") {
    header("Location: barberos.php?error=invalido");
    exit;
}

if (mb_strlen($nombre) > largo_maximo_nombre_barbero() || mb_strlen($apellido) > largo_maximo_nombre_barbero()) {
    header("Location: barberos.php?error=invalido");
    exit;
}

if (!in_array($estado, estados_validos_barbero(), true)) {
    header("Location: barberos.php?error=invalido");
    exit;
}

if (!existe_barbero($conexion, $id_barbero)) {
    header("Location: barberos.php?error=no_existe");
    exit;
}

// especialidad: texto libre y opcional (máx. 50). No es una FK: la tabla
// "especialidad" y la columna "id_especialidad" no existen en la base real.
$validacion_especialidad = validar_especialidad_barbero($especialidad);
if (!$validacion_especialidad["ok"]) {
    header("Location: barberos.php?error=invalido");
    exit;
}
$especialidad_valor = $validacion_especialidad["valor"];

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
// IMAGEN (opcional al editar): si se sube un archivo nuevo, se valida
// y se guarda; si no se sube nada, se conserva la url_imagen actual
// del barbero (ya no viaja como texto oculto por el formulario, se
// busca directamente en la base de datos).
// =====================================================
$url_imagen_anterior = obtener_url_imagen_barbero($conexion, $id_barbero);

$resultado_imagen = procesar_imagen_barbero($_FILES["imagen"] ?? null);
if (!$resultado_imagen["ok"]) {
    header("Location: barberos.php?error=" . $resultado_imagen["error"]);
    exit;
}

$imagen_reemplazada = $resultado_imagen["ruta"] !== null;
$url_imagen_valor = $imagen_reemplazada ? $resultado_imagen["ruta"] : $url_imagen_anterior;

// La contraseña es opcional al editar: si viene vacía, se conserva la actual.
$cambiar_password = $password !== "";

if ($cambiar_password && !validar_password_barbero($password)) {
    // Si ya se había subido y guardado una imagen nueva, no la dejamos huérfana.
    if ($imagen_reemplazada) {
        eliminar_imagen_barbero_fs($resultado_imagen["ruta"]);
    }
    header("Location: barberos.php?error=password_corta");
    exit;
}

// =====================================================
// ACTUALIZACIÓN
// Funciona tanto si mysqli lanza excepciones (PHP 8.1+ por defecto) como
// si devuelve false: en ambos casos, si la actualización falla, no se
// deja la imagen recién subida huérfana en el disco.
// =====================================================
$actualizado = false;

try {

    if ($cambiar_password) {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $sql = "UPDATE barbero SET nombre = ?, apellido = ?, especialidad = ?, en_netro_desde = ?, anio_inicio = ?, url_imagen = ?, contrasena = ?, estado = ? WHERE id_barbero = ?";
        $consulta = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param(
            $consulta,
            "sssiisssi",
            $nombre, $apellido, $especialidad_valor, $en_netro_desde_valor, $anio_inicio_valor, $url_imagen_valor, $password_hash, $estado, $id_barbero
        );
    } else {
        $sql = "UPDATE barbero SET nombre = ?, apellido = ?, especialidad = ?, en_netro_desde = ?, anio_inicio = ?, url_imagen = ?, estado = ? WHERE id_barbero = ?";
        $consulta = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param(
            $consulta,
            "sssiissi",
            $nombre, $apellido, $especialidad_valor, $en_netro_desde_valor, $anio_inicio_valor, $url_imagen_valor, $estado, $id_barbero
        );
    }

    $actualizado = mysqli_stmt_execute($consulta);

    if (!$actualizado) {
        error_log("actualizar_barbero.php [" . mysqli_stmt_errno($consulta) . "]: " . mysqli_stmt_error($consulta));
    }

    mysqli_stmt_close($consulta);

} catch (Throwable $e) {
    // Solo código y mensaje del error: nunca se registran datos del formulario.
    $codigo = ($e instanceof mysqli_sql_exception) ? (int) $e->getCode() : -1;
    error_log("actualizar_barbero.php [" . $codigo . "]: " . $e->getMessage());
}

if ($actualizado) {
    // Se actualizó correctamente: si se reemplazó la imagen, ahora sí
    // borramos la anterior del disco.
    if ($imagen_reemplazada) {
        eliminar_imagen_barbero_fs($url_imagen_anterior);
    }
    header("Location: barberos.php?ok=actualizado");
    exit;
}

// La actualización falló: no dejamos huérfana la imagen recién subida.
if ($imagen_reemplazada) {
    eliminar_imagen_barbero_fs($resultado_imagen["ruta"]);
}
header("Location: barberos.php?error=guardar");
exit;
