<?php
require_once __DIR__ . '/comun.php';
session_check();

$error = $_GET["error"] ?? "";
$error_password = ($error === "password");
$error_email = ($error === "email");
$mensajes_error = [
    "datos"   => "Revisá los datos: nombre y apellido solo con letras (máximo 30) y un correo válido.",
    "corta"   => "La contraseña debe tener al menos 8 caracteres.",
    "sesion"  => "El formulario venció. Volvé a intentarlo.",
    "guardar" => "No se pudo crear la cuenta. Intentá de nuevo.",
];
?>


<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrarse - Netro</title>
    <link rel="stylesheet" href="../css/header.css">
    <link rel="stylesheet" href="../css/body.css">
    <link rel="stylesheet" href="../css/login.css">
    <link rel="stylesheet" href="../css/spinner.css">
    <link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/regular/style.css">
    <link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/fill/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,200..800;1,200..800&display=swap" rel="stylesheet">
</head>
<header class="header">
    <p id="Titulo">Barbería Netro</p>
    <div class="contenedor_botones">
        <a href="index.php" class="boton">Inicio</a>
        <a href="reserva.php" class="boton">Reserva</a>
        <a href="perfil.php" class="perfil" title="Perfil"><i class="ph ph-user-circle"></i></a>
    </div>
</header>
<body>
    <main>
        <div id="fondo"></div>
        <div class="caja_login">
            <div class="header_login">
                <h2>Registrarse</h2>
            </div>
            <div class="body_login">
                <form method="POST" action="registrar_cliente.php" onsubmit="checkCharacters(event)">
                    <?= csrf_campo() ?>
                    <?php if (isset($mensajes_error[$error])): ?>
                        <p class="mensaje-error"><?= e($mensajes_error[$error]) ?></p>
                    <?php endif; ?>
                    <p>Nombre <span class="requerido">*</span></p>
                    <input type="text" name="nombre" class="entrada" maxlength="30" required>
                    <p>Apellido <span class="requerido">*</span></p>
                    <input type="text" name="apellido" class="entrada" maxlength="30" required>
                    <p>Correo <span class="requerido">*</span></p>
                    <input type="email" name="correo" class="entrada <?php echo $error_email ? 'error' : ''; ?>" maxlength="150" required>
                    <?php if ($error_email): ?>
                        <p class="mensaje-error">Este correo ya esta en uso</p>
                    <?php endif; ?>
                    <p>Contraseña <span class="requerido">*</span></p>
                    <input type="password" name="passw" class="entrada" minlength="12" id="pass" required>
                    <div class="requisitos">
                        <div id="txt">
                            <div id="caracteres">
                                <p>Caracteres:</p>
                                <p id="actuales">0</p>
                                <p>/</p>
                                <p id="requeridos">12</p>
                            </div>
                            <div id="especiales">
                                <p>Al menos un caracter especial</p>
                            </div>
                            <div id="mayus-min">
                                <p>Mayúsculas y minúsculas</p>
                            </div>
                            <div id="num">
                                <p>Al menos un numero</p>
                            </div>
                        </div>
                        <div id="checks">
                            <i class="ph ph-x mal" id="primero"></i>
                            <i class="ph ph-x mal" id="segundo"></i>
                            <i class="ph ph-x mal" id="tercero"></i>
                            <i class="ph ph-x mal" id="cuarto"></i>
                        </div>
                    </div>
                    <p>Confirmar contraseña <span class="requerido">*</span></p>
                    <input type="password" name="confirm_passw" id="conf_passwd" class="entrada <?php echo $error_password ? 'error' : ''; ?>" minlength="12" required>
                    <p id="coincidencia">Las contraseñas no coinciden</p>
                    <?php if ($error_password): ?>
                        <p class="mensaje-error"></p>
                    <?php endif; ?>

                    <button type="submit" class="boton_login" id="boton_continuar" onclick="checkCharacters(event)">
                        <span class="btn_texto">Registrarse</span>
                        <span class="spinner"></span>
                    </button>
                    <div id="enlaces">
                        <a href="login.php">¿Ya tienes una cuenta? Iniciar Sesión</a>
                    </div>
                </form>
            </div>
        </div>
    </main>
</body>
<script>
    const pass = document.getElementById("pass");
    const conf_pass = document.getElementById("conf_passwd");
    const caracteres_check = document.getElementById("primero");
    const especial_check = document.getElementById("segundo");
    const mayusmin_check = document.getElementById("tercero");
    const num_check = document.getElementById("cuarto");
    const coincidencia = document.getElementById("coincidencia");
    let passLength = 0;
    let tieneMayuscula = false;
    let tieneNumero = false;
    let tieneEspecial = false;
    let password_coinciden = false;

    function chequear(cual){
        cual.classList.remove("ph-x")
        cual.classList.add("ph-check");
        cual.classList.remove("mal");
        cual.classList.add("bien");
    }
    function uncheck(cual){
        cual.classList.add("ph-x")
        cual.classList.remove("ph-check");
        cual.classList.add("mal");
        cual.classList.remove("bien");
    }

    pass.addEventListener("input", () => {
        passLength = pass.value.length;
        conf_passLength = conf_pass.value.length;
        tieneMayuscula = /[A-Z]/.test(pass.value);
        tieneNumero = /[0-9]/.test(pass.value);
        tieneEspecial = /[^a-zA-Z0-9]/.test(pass.value);
        document.getElementById("actuales").textContent = passLength;

        if(passLength >= 12){chequear(caracteres_check);}else{uncheck(caracteres_check);}
        if(tieneMayuscula){chequear(mayusmin_check);}else{uncheck(mayusmin_check);}
        if(tieneNumero){chequear(num_check);}else{uncheck(num_check);}
        if(tieneEspecial){chequear(especial_check);}else{uncheck(especial_check);}
    });

    function comprobarCoincidencia() {
        if (pass.value === conf_pass.value && pass.value.length > 0) {
            coincidencia.style.color = "#4FFF00";
            coincidencia.textContent = "Las contraseñas coinciden";
            password_coinciden = true;
        } else {
            coincidencia.style.color = "#ff0000";
            coincidencia.textContent = "Las contraseñas no coinciden";
            password_coinciden = false;
        }
    }

    pass.addEventListener("input", comprobarCoincidencia);
    conf_pass.addEventListener("input", comprobarCoincidencia);

    function checkCharacters(){
        if (passLength < 11 || !tieneMayuscula || !tieneNumero || !tieneEspecial || !password_coinciden){
            event.preventDefault();
            alert("La contraseña no cumple los requisitos")
        }
    }

    document.querySelector("form").addEventListener("submit", function () {
        const boton = this.querySelector('button[type="submit"]');
        boton.classList.add("loading");
        boton.disabled = true;
    });
</script>
</html>