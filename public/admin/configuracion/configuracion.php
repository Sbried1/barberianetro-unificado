<?php
/**
 * configuracion.php
 * Sección Configuración del panel: solo permite cambiar la contraseña de la
 * cuenta con la que se ingresó (gerente o barbero). No necesita la base de
 * datos para dibujarse; el cambio lo procesa guardar_configuracion.php.
 */

require_once __DIR__ . '/../auth/guard.php';
auth_requerir('configuracion');

// Los textos son fijos: el valor de la URL solo elige el mensaje.
$mensajes_error = [
    "sesion"         => "No se pudo validar el formulario. Volvé a intentarlo.",
    "clave_largo"    => "La contraseña nueva debe tener entre 10 y 72 caracteres.",
    "clave_distinta" => "La contraseña nueva y su repetición no coinciden.",
    "clave_actual"   => "La contraseña actual no es correcta. No se hizo ningún cambio.",
    "guardar"        => "No se pudo guardar el cambio. Intentá nuevamente.",
];
$mensajes_ok = [
    "clave"     => "Contraseña actualizada. Usala la próxima vez que ingreses.",
];
$codigo_error = isset($_GET["error"]) && is_string($_GET["error"]) ? $_GET["error"] : "";
$codigo_ok = isset($_GET["ok"]) && is_string($_GET["ok"]) ? $_GET["ok"] : "";

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
            <?php auth_render_menu('configuracion'); ?>
        </section>

        <section class="panel">

            <div class="servicios_header">
                <div class="title_servicios">
                    <h2>Configuración</h2>
                    <p>Datos de acceso de tu cuenta</p>
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
