<?php
require_once __DIR__ . '/comun.php';

$sql = "SELECT b.id_barbero, b.nombre, b.apellido, b.url_imagen,
                b.anio_inicio, b.en_netro_desde, b.especialidad
        FROM barbero b";
$resultado = mysqli_query($conexion, $sql);
if (!$resultado) {
    die("Error en la consulta: " . mysqli_error($conexion));
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inicio - Netro</title>
    <link rel="stylesheet" href="../css/body.css">
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/header.css">
    <link rel="stylesheet" href="../css/footer.css">
    <link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/regular/style.css">
    <link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/fill/style.css">
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
    <?php if (isset($_GET['reservado'])): ?>
        <div class="banner-exito" id="banner-exito">
            <i class="ph ph-check-circle"></i> ¡Listo! Tu reserva quedó registrada.
        </div>
    <?php elseif (isset($_GET['error']) && $_GET['error'] === 'ocupado'): ?>
        <div class="banner-error" id="banner-exito">
            <i class="ph ph-warning-circle"></i> Ese horario se acaba de ocupar. Probá con otro.
        </div>
    <?php elseif (isset($_GET['maxreservas'])): ?>
        <div class="banner-error" id="banner-exito">
            <i class="ph ph-warning-circle"></i> Ya tenés el máximo de <?= RESERVA_MAX_ACTIVAS ?> reservas activas. Podés cancelar una desde tu perfil.
        </div>
    <?php endif; ?>
    <main>
        <section id="hero">
            <h1>Barberia local en Fray Bentos</h1>
            <h2>Tenemos los mejores barberos de la ciudad</h2>
            <a href="reserva.php" class="botonhero">Reserva</a>
        </section>
        <section id="about">
            <h2>Sobre nosotros</h2>
            <i class="ph ph-arrow-down flecha"></i>
            <div id="grid-about">
                <div class="fila">
                    <div class="texto">
                        <p>En nuestro local, cada corte es mucho más que un simple servicio: es una <span class="destacado">experiencia</span> pensada para que salgas con un estilo que refleje tu personalidad.</p>
                    </div>
                    <div class="imagen-p" id="img1"></div>
                </div>
                <div class="separador"></div>
                <div class="fila-inv">
                    <div class="imagen-p" id="img2"></div>
                    <div class="texto-inv">
                        <p>Contamos con un equipo de profesionales que acumula más de <span class="destacado">10 años de experiencia</span> en el rubro, perfeccionando cada técnica y manteniéndose al día con las últimas tendencias en barbería y peluquería.</p>
                    </div>
                </div>
                <div class="separador"></div>
                <div class="fila">
                    <div class="texto">
                        <p>Realizamos <span class="destacado">cortes de todo tipo y para todos los estilos</span>, desde los clásicos y elegantes hasta los más modernos y personalizados.</p>
                    </div>
                    <div class="imagen-p" id="img3"></div>
                </div>
                <div class="separador"></div>
            </div>
            <div id="ubicacion">
                <div class="ubicacion-tarjeta">
                    <div class="ubicacion-info">
                        <div class="ubicacion-textos">
                            <p class="ubicacion-etiqueta">Nuestra ubicación</p>
                            <h3 class="ubicacion-titulo">Roberto Young, Fray Bentos</h3>
                            <p class="ubicacion-desc">Te esperamos en un ambiente cómodo y profesional, fácil de encontrar.</p>
                            <a href="https://maps.app.goo.gl/tH9YdmRhossLJBuM8" target="_blank" class="ubicacion-boton">
                                <i class="ph ph-map-trifold"></i> Cómo llegar
                            </a>
                        </div>
                    </div>
                    <a href="https://maps.app.goo.gl/tH9YdmRhossLJBuM8" target="_blank" class="ubicacion-mapa">
                        <i class="ph-fill ph-map-pin"></i>
                        <span>Fray Bentos</span>
                    </a>
                </div>
            </div>
        </section>
        <section id="team">
            <h2>Nuestro equipo</h2>
            <div class="equipo-grid">
                <?php while ($barbero = mysqli_fetch_assoc($resultado)): ?>
                    <?php
                        $foto = foto_barbero($barbero['url_imagen']);
                        $nombreCompleto = $barbero['nombre'] . ' ' . $barbero['apellido'];
                        // anio_inicio: año en que empezó como barbero (su experiencia).
                        // en_netro_desde: año en que entró a Netro. Son datos distintos.
                        $experiencia = max(0, (int) date('Y') - (int) $barbero['anio_inicio']);
                        $especialidad = $barbero['especialidad'];
                    ?>
                    <div class="tarjeta-barbero">
                        <div class="tarjeta-barbero-foto">
                            <img src="<?= htmlspecialchars($foto) ?>" alt="Foto de <?= htmlspecialchars($nombreCompleto) ?>">
                        </div>
                        <div class="tarjeta-barbero-info">
                            <p class="tarjeta-barbero-nombre"><?= htmlspecialchars($nombreCompleto) ?></p>
                            <p class="tarjeta-barbero-puesto">Barbero</p>
                            <div class="separador"></div>
                            <div class="especialidad">
                                <i class="ph-fill ph-star especialidad_icon"></i>
                                <p><?= e($especialidad) ?></p>
                            </div>
                            <p class="tarjeta-barbero-datos"><?= $experiencia ?> <?= $experiencia === 1 ? 'Año' : 'Años' ?> de experiencia</p>
                            <?php if (!empty($barbero['en_netro_desde'])): ?>
                                <p class="tarjeta-barbero-datos">En Netro desde <?= (int) $barbero['en_netro_desde'] ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </section>
        <footer>
            <div class="footer-grid">
                <div class="footer-marca">
                    <p class="footer-titulo">Netro</p>
                    <p class="footer-subtitulo">Barbería local en Fray Bentos</p>
                    <p class="footer-descripcion">Cortes de todo tipo y para todos los estilos. Calidad, estilo y confianza en cada corte.</p>
                    <div class="footer-redes">
                        <a href="https://instagram.com/netro_barbershop" target="_blank"><i class="ph ph-instagram-logo"></i></a>
                        <a href="https://wa.me/59898000000" target="_blank"><i class="ph ph-whatsapp-logo"></i></a>
                        <a href="https://maps.app.goo.gl/tH9YdmRhossLJBuM8" target="_blank"><i class="ph ph-map-pin"></i></a>
                    </div>
                </div>
                <div class="footer-columna">
                    <p class="footer-titulo-columna">Contacto</p>
                    <p><i class="ph ph-whatsapp-logo"></i> Whatsapp: 098 000 000</p>
                    <p><i class="ph ph-instagram-logo"></i> Instagram: @netro_barbershop</p>
                    <p><i class="ph ph-envelope-simple"></i> Email: netrobarbershop@gmail.com</p>
                    <p><i class="ph ph-map-pin"></i> Roberto Young 3302, Fray Bentos</p>
                </div>
                <div class="footer-columna">
                    <p class="footer-titulo-columna">Secciones</p>
                    <a href="#hero">Inicio <i class="ph ph-caret-right"></i></a>
                    <a href="reserva.php">Reserva <i class="ph ph-caret-right"></i></a>
                    <a href="#about">Sobre nosotros <i class="ph ph-caret-right"></i></a>
                    <a href="#team">Nuestro equipo <i class="ph ph-caret-right"></i></a>
                </div>
                <div class="footer-columna">
                    <p class="footer-titulo-columna">Horario</p>
                    <div class="footer-horario-fila">
                        <i class="ph ph-clock"></i>
                        <div>
                            <p class="footer-horario-dia">Lunes a Sábado</p>
                            <p class="footer-horario-hora">09:00 - 20:00</p>
                        </div>
                    </div>
                    <div class="footer-horario-fila footer-horario-fila-simple">
                        <p class="footer-horario-dia">Domingos</p>
                        <p class="footer-horario-hora">Cerrado</p>
                    </div>
                </div>
            </div>
            <div class="footer-abajo">
                <p>&copy; 2024 Netro Barbería. Todos los derechos reservados.</p>
                <a href="#" class="footer-arriba"><i class="ph ph-caret-up"></i></a>
            </div>
        </footer>
    </main>
    <script>
        const banner = document.getElementById('banner-exito');
        if (banner) {
            setTimeout(() => {
                banner.style.opacity = '0';
                setTimeout(() => banner.remove(), 400);
            }, 4000);
        }
    </script>
</body>
</html>