<?php
/**
 * guardar_configuracion.php
 * Recibe el formulario de la sección Configuración: cambio de contraseña de
 * la cuenta con la que se ingresó (gerente o barbero). Además de la guardia
 * de origen, el formulario lleva token CSRF.
 *
 * La cuenta a modificar sale SIEMPRE de la sesión (rol + id), nunca de un
 * dato enviado por el navegador.
 */

require_once __DIR__ . '/../auth/guard.php';
$cuenta = auth_requerir('configuracion');

require_once __DIR__ . "/../../../config/conexion.php";
require_once __DIR__ . '/../auth/cuentas.php';

function volver_a_configuracion($parametro) {
    header("Location: configuracion.php?" . $parametro);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: configuracion.php");
    exit;
}

if (!auth_csrf_valido($_POST["csrf"] ?? null)) {
    volver_a_configuracion("error=sesion");
}

$accion = isset($_POST["accion"]) && is_string($_POST["accion"]) ? $_POST["accion"] : "";

// Tabla y columna de id de cada rol (valores fijos, nunca datos del usuario).
$tablas_por_rol = [
    AUTH_ROL_GERENTE => ["gerente", "id_gerente"],
    AUTH_ROL_BARBERO => ["barbero", "id_barbero"],
];

if ($accion !== "clave" || !isset($tablas_por_rol[$cuenta["rol"]])) {
    volver_a_configuracion("error=guardar");
}

[$tabla, $columna_id] = $tablas_por_rol[$cuenta["rol"]];

try {

    $actual  = isset($_POST["clave_actual"]) && is_string($_POST["clave_actual"]) ? $_POST["clave_actual"] : "";
    $nueva   = isset($_POST["clave_nueva"]) && is_string($_POST["clave_nueva"]) ? $_POST["clave_nueva"] : "";
    $repetir = isset($_POST["clave_repetir"]) && is_string($_POST["clave_repetir"]) ? $_POST["clave_repetir"] : "";

    // Mismas reglas que scripts/crear_gerente_cli.php. bcrypt solo usa los
    // primeros 72 bytes: una contraseña más larga se rechaza.
    if (strlen($nueva) < 10 || strlen($nueva) > 72) {
        volver_a_configuracion("error=clave_largo");
    }

    if ($nueva !== $repetir) {
        volver_a_configuracion("error=clave_distinta");
    }

    $consulta = mysqli_prepare($conexion, "SELECT contrasena FROM $tabla WHERE $columna_id = ? LIMIT 1");
    mysqli_stmt_bind_param($consulta, "i", $cuenta["id"]);
    mysqli_stmt_execute($consulta);
    $fila = mysqli_fetch_assoc(mysqli_stmt_get_result($consulta));
    mysqli_stmt_close($consulta);

    if (!$fila || !auth_verificar_clave($fila["contrasena"], $actual)) {
        volver_a_configuracion("error=clave_actual");
    }

    $hash = password_hash($nueva, PASSWORD_DEFAULT);
    $consulta = mysqli_prepare($conexion, "UPDATE $tabla SET contrasena = ? WHERE $columna_id = ?");
    mysqli_stmt_bind_param($consulta, "si", $hash, $cuenta["id"]);
    mysqli_stmt_execute($consulta);
    mysqli_stmt_close($consulta);

    // Sesión con un ID nuevo después de cambiar la credencial.
    if (auth_iniciar_sesion()) {
        session_regenerate_id(true);
        session_write_close();
    }

    volver_a_configuracion("ok=clave");

} catch (Throwable $e) {
    // Solo el mensaje técnico, nunca los datos del formulario.
    error_log("[configuracion] " . $e->getMessage());
    volver_a_configuracion("error=guardar");
}
