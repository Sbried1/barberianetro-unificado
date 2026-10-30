<?php
/**
 * guardar_configuracion.php
 * Recibe los formularios de la sección Configuración. Además de la guardia
 * de origen, estos formularios llevan token CSRF: cambian una contraseña y
 * una regla del negocio.
 */

require_once __DIR__ . '/../auth/guard.php';
$cuenta = auth_requerir('configuracion'); // conexion.php ya usa $usuario para MySQL

include("../conexion.php");
include("../reservas/reservas_funciones.php");
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

try {

    if ($accion === "horizonte") {

        $dias = filter_var($_POST["dias"] ?? null, FILTER_VALIDATE_INT, ["options" => ["min_range" => RESV_DIAS_ADMIN_MINIMO, "max_range" => RESV_DIAS_ADMIN_MAXIMO]]);

        if ($dias === false) {
            volver_a_configuracion("error=dias");
        }

        $valor = (string) $dias;
        $consulta = mysqli_prepare($conexion, "INSERT INTO configuracion (clave, valor) VALUES ('reserva_dias_admin', ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)");
        mysqli_stmt_bind_param($consulta, "s", $valor);
        mysqli_stmt_execute($consulta);
        mysqli_stmt_close($consulta);

        volver_a_configuracion("ok=horizonte");
    }

    if ($accion === "clave") {

        $actual  = isset($_POST["clave_actual"]) && is_string($_POST["clave_actual"]) ? $_POST["clave_actual"] : "";
        $nueva   = isset($_POST["clave_nueva"]) && is_string($_POST["clave_nueva"]) ? $_POST["clave_nueva"] : "";
        $repetir = isset($_POST["clave_repetir"]) && is_string($_POST["clave_repetir"]) ? $_POST["clave_repetir"] : "";

        // Mismas reglas que auth/crear_gerente_cli.php. bcrypt solo usa los
        // primeros 72 bytes: una contraseña más larga se rechaza.
        if (strlen($nueva) < 10 || strlen($nueva) > 72) {
            volver_a_configuracion("error=clave_largo");
        }

        if ($nueva !== $repetir) {
            volver_a_configuracion("error=clave_distinta");
        }

        $consulta = mysqli_prepare($conexion, "SELECT contrasena FROM gerente WHERE id_gerente = ? LIMIT 1");
        mysqli_stmt_bind_param($consulta, "i", $cuenta["id"]);
        mysqli_stmt_execute($consulta);
        $fila = mysqli_fetch_assoc(mysqli_stmt_get_result($consulta));
        mysqli_stmt_close($consulta);

        if (!$fila || !auth_verificar_clave($fila["contrasena"], $actual)) {
            volver_a_configuracion("error=clave_actual");
        }

        $hash = password_hash($nueva, PASSWORD_DEFAULT);
        $consulta = mysqli_prepare($conexion, "UPDATE gerente SET contrasena = ? WHERE id_gerente = ?");
        mysqli_stmt_bind_param($consulta, "si", $hash, $cuenta["id"]);
        mysqli_stmt_execute($consulta);
        mysqli_stmt_close($consulta);

        // Sesión con un ID nuevo después de cambiar la credencial.
        if (auth_iniciar_sesion()) {
            session_regenerate_id(true);
            session_write_close();
        }

        volver_a_configuracion("ok=clave");
    }

} catch (Throwable $e) {
    // Solo el mensaje técnico, nunca los datos del formulario.
    error_log("[configuracion] " . $e->getMessage());
    volver_a_configuracion("error=guardar");
}

volver_a_configuracion("error=guardar");
