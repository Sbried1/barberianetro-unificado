<?php
/**
 * configuracion.php
 * Sección Configuración del panel. Solo muestra como editable lo que tiene un
 * comportamiento definido y seguro: el límite de reservas del panel y la
 * contraseña de la cuenta. Las demás reglas se muestran en modo lectura porque
 * también afectan a la web de clientes.
 */

require_once __DIR__ . '/../auth/guard.php';
auth_requerir('configuracion');

include("../conexion.php");
include("../reservas/reservas_funciones.php");

$dias_actuales = resv_dias_maximos_admin($conexion);

// La migración puede no haberse ejecutado todavía: se avisa en lugar de
// mostrar un formulario que fallaría al guardar.
$tabla_lista = true;
try {
    mysqli_query($conexion, "SELECT 1 FROM configuracion LIMIT 1");
} catch (Throwable $e) {
    $tabla_lista = false;
}

// Los textos son fijos: el valor de la URL solo elige el mensaje.
$mensajes_error = [
    "dias"           => "El límite debe ser un número entero entre " . RESV_DIAS_ADMIN_MINIMO . " y " . RESV_DIAS_ADMIN_MAXIMO . " días.",
    "sesion"         => "No se pudo validar el formulario. Volvé a intentarlo.",
    "clave_largo"    => "La contraseña nueva debe tener entre 10 y 72 caracteres.",
    "clave_distinta" => "La contraseña nueva y su repetición no coinciden.",
    "clave_actual"   => "La contraseña actual no es correcta. No se hizo ningún cambio.",
    "guardar"        => "No se pudo guardar el cambio. Intentá nuevamente.",
];
$mensajes_ok = [
    "horizonte" => "Límite de reservas guardado.",
    "clave"     => "Contraseña actualizada. Usala la próxima vez que ingreses.",
];
$codigo_error = isset($_GET["error"]) && is_string($_GET["error"]) ? $_GET["error"] : "";
$codigo_ok = isset($_GET["ok"]) && is_string($_GET["ok"]) ? $_GET["ok"] : "";

$dias_semana = [1 => "Lunes", 2 => "Martes", 3 => "Miércoles", 4 => "Jueves", 5 => "Viernes", 6 => "Sábado", 7 => "Domingo"];

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../Estilos_index/global.css">
    <link rel="stylesheet" href="configuracion.css">
    <title>Barberia - Configuración</title>
</head>
<body>

    <header>
        <div class="nav">
            <div class="logo">
                <img src="../img/ChatGPT Image 15 jun 2026, 13_41_39.png" alt="">
                <h1 id="Netro">Netro</h1>
            </div>
        </div>
    </header>

    <div class="container">

        <section class="sidebar">
            <div class="menu-header">
                <img src="../img/ChatGPT Image 15 jun 2026, 13_41_39.png" alt="Logo">
                <h2>MENÚ</h2>
            </div>
            <nav class="menu-nav">
                <ul>
                    <li><a href="../index.php">Dashboard</a></li>
                    <li><a href="../servicios_categoria/servicios.php">Servicios</a></li>
                    <li><a href="../reservas/reservas.php">Reservas</a></li>
                    <li><a href="../cliente/clientes.php">Clientes</a></li>
                    <li><a href="../barberos/barberos.php">Barberos</a></li>
                    <li><a href="../venta/ventas.php">Ventas</a></li>
                    <li><a href="configuracion.php" aria-current="page">Configuración</a></li>
                    <?php auth_render_logout_item(); ?>
                </ul>
            </nav>
        </section>

        <section class="panel">

            <div class="servicios_header">
                <div class="title_servicios">
                    <h2>Configuración</h2>
                    <p>Reglas de reservas y datos de acceso del panel</p>
                </div>
            </div>

            <?php if (isset($mensajes_error[$codigo_error])) { ?>
                <div class="config_alerta error" role="alert">
                    <p>❌ <?php echo $mensajes_error[$codigo_error]; ?></p>
                    <button type="button" onclick="this.parentElement.hidden = true">Aceptar</button>
                </div>
            <?php } ?>

            <?php if (isset($mensajes_ok[$codigo_ok])) { ?>
                <div class="config_alerta ok" role="status">
                    <p>✅ <?php echo $mensajes_ok[$codigo_ok]; ?></p>
                    <button type="button" onclick="this.parentElement.hidden = true">Aceptar</button>
                </div>
            <?php } ?>

            <div class="config_grilla">

                <div class="config_tarjeta">
                    <h3>Reservas desde el panel</h3>
                    <p class="config_texto">Hasta cuántos días hacia adelante se puede agendar o mover un turno desde el panel. No cambia las reservas ya guardadas.</p>

                    <?php if (!$tabla_lista) { ?>
                        <p class="panel_alerta_form">Falta ejecutar <strong>migracion_panel_admin.sql</strong>. Mientras tanto rige el valor por defecto de <?php echo RESV_DIAS_ADMIN_DEFECTO; ?> días y no se puede cambiar.</p>
                    <?php } else { ?>
                        <form action="guardar_configuracion.php" method="POST">
                            <?php echo auth_csrf_campo(); ?>
                            <input type="hidden" name="accion" value="horizonte">

                            <label class="config_label" for="config_dias">Límite de días</label>
                            <input
                                type="number"
                                id="config_dias"
                                name="dias"
                                class="config_input"
                                value="<?php echo (int) $dias_actuales; ?>"
                                min="<?php echo RESV_DIAS_ADMIN_MINIMO; ?>"
                                max="<?php echo RESV_DIAS_ADMIN_MAXIMO; ?>"
                                step="1"
                                required>
                            <p class="config_ayuda">Entre <?php echo RESV_DIAS_ADMIN_MINIMO; ?> y <?php echo RESV_DIAS_ADMIN_MAXIMO; ?> días. Hoy permite agendar hasta el <?php echo htmlspecialchars(date("d/m/Y", strtotime("+" . (int) $dias_actuales . " days"))); ?>.</p>

                            <button type="submit" class="panel_btn">Guardar límite</button>
                        </form>
                    <?php } ?>
                </div>

                <div class="config_tarjeta">
                    <h3>Cambiar contraseña</h3>
                    <p class="config_texto">Cambia la contraseña de la cuenta con la que ingresaste.</p>

                    <form action="guardar_configuracion.php" method="POST" id="config_form_clave">
                        <?php echo auth_csrf_campo(); ?>
                        <input type="hidden" name="accion" value="clave">

                        <p class="panel_alerta_form" id="config_clave_error" role="alert" hidden></p>

                        <label class="config_label" for="config_clave_actual">Contraseña actual</label>
                        <input type="password" id="config_clave_actual" name="clave_actual" class="config_input" autocomplete="current-password" required>

                        <label class="config_label" for="config_clave_nueva">Contraseña nueva</label>
                        <input type="password" id="config_clave_nueva" name="clave_nueva" class="config_input" autocomplete="new-password" minlength="10" maxlength="72" required>
                        <p class="config_ayuda">Entre 10 y 72 caracteres.</p>

                        <label class="config_label" for="config_clave_repetir">Repetir contraseña nueva</label>
                        <input type="password" id="config_clave_repetir" name="clave_repetir" class="config_input" autocomplete="new-password" minlength="10" maxlength="72" required>

                        <button type="submit" class="panel_btn">Cambiar contraseña</button>
                    </form>
                </div>

                <div class="config_tarjeta config_tarjeta_ancha">
                    <h3>Reglas vigentes (solo lectura)</h3>
                    <p class="config_texto">Estas reglas también las usa la web de clientes, por eso se definen en el código y no se editan desde acá.</p>

                    <dl class="config_reglas">
                        <div><dt>Horario de atención</dt><dd><?php echo RESV_HORA_APERTURA; ?> a <?php echo RESV_HORA_CIERRE; ?></dd></div>
                        <div><dt>Intervalo entre turnos</dt><dd><?php echo RESV_INTERVALO_MINUTOS; ?> minutos</dd></div>
                        <div><dt>Día cerrado</dt><dd><?php echo $dias_semana[RESV_DIA_CERRADO]; ?></dd></div>
                        <div><dt>Zona horaria</dt><dd><?php echo htmlspecialchars(date_default_timezone_get()); ?></dd></div>
                    </dl>
                </div>

            </div>

        </section>

    </div>

    <script>
        document.getElementById("config_form_clave").addEventListener("submit", function (e) {

            const error = document.getElementById("config_clave_error");
            const nueva = document.getElementById("config_clave_nueva").value;
            const repetir = document.getElementById("config_clave_repetir").value;
            const distintas = nueva !== repetir;

            error.textContent = distintas ? "La contraseña nueva y su repetición no coinciden." : "";
            error.hidden = !distintas;

            if (distintas) {
                e.preventDefault();
            }

        });
    </script>

</body>
</html>
