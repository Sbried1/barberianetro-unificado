<?php

require_once __DIR__ . '/comun.php';
requerir_cliente();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perfil - Netro</title>
    <link rel="stylesheet" href="../css/body.css">
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/header.css">
    <link rel="stylesheet" href="../css/perfil.css">
    <link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/regular/style.css">
    <link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/fill/style.css">
    <link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/bold/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,200..800;1,200..800&display=swap" rel="stylesheet">
</head>
<body>
    <header class="header">
        <p id="Titulo">Barbería Netro</p>
        <div class="contenedor_botones">
            <a href="index.php" class="boton">Inicio</a>
            <a href="reserva.php" class="boton">Reserva</a>
            <a href="perfil.php" class="perfil" title="Perfil"><i class="ph ph-user-circle"></i></a>
        </div>
    </header>
    <main>
        <div class="contenedor-perfil">
            <div class="contenedor-nombre">
                <i class="ph ph-user-circle"></i>
                <p><?= e($_SESSION["nombre"]) ?></p>
                <p><?= e($_SESSION["apellido"]) ?></p>
                <a href="logout.php" title="Cerrar sesión">
                    <button class="logout"><i class="ph ph-sign-out"></i></button>
                </a>
            </div>
            <?php if (!empty($_SESSION["errores_modificar"])): ?>
                <div class="mensajes-error">
                    <?php foreach ($_SESSION["errores_modificar"] as $error): ?>
                        <p class="mensaje-error"><?= htmlspecialchars($error) ?></p>
                    <?php endforeach; ?>
                </div>
                <?php unset($_SESSION["errores_modificar"]); ?>
            <?php endif; ?>

            <?php if (isset($_GET["actualizado"])): ?>
                <p class="mensaje-exito">Datos actualizados correctamente.</p>
            <?php endif; ?> 
            <form action="modificar.php" method="post" class="formulario-datos">
                <?= csrf_campo() ?>
                <div class="div-datos">
                    <input type="hidden" name="id" value="<?= htmlspecialchars($_SESSION["id_cliente"]) ?>">
                    <input class="input-comun input-formulario" type="text" name="nombre" placeholder="Nuevo nombre" maxlength="30">
                    <div class="separador-vertical"></div>
                    <input class="input-comun input-formulario" type="text" name="apellido" placeholder="Nuevo apellido" maxlength="30">
                    <div class="separador-vertical"></div>
                    <div class="contenedor-password">
                        <input class="input-comun input-password" type="password" name="password_actual" placeholder="Contraseña actual">
                        <input class="input-comun input-password" type="password" name="password_nuevo" placeholder="Nueva contraseña">
                        <input class="input-comun input-password" type="password" name="password_confirmar" placeholder="Confirmar contraseña">
                    </div>
                </div>
                <div class="div-boton">
                    <button type="submit" class="boton-formulario">Confirmar <i class="ph ph-check-circle"></i></button>
                    <a href="perfil.php" class="boton-cancelar">Volver atrás <i class="ph ph-x-circle"></i></a>
                </div>
            </form>
        </div>
    </main>
</body>
</html>