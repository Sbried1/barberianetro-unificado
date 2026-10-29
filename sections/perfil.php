<?php

require_once __DIR__ . '/comun.php';
requerir_cliente();

$sql = 'SELECT r.id_reserva, r.fecha, r.hora, r.estado,
            b.nombre   AS barbero_nombre,
            b.apellido AS barbero_apellido,
            GROUP_CONCAT(s.nombre SEPARATOR ", ") AS servicios
        FROM reserva r
        LEFT JOIN barbero b ON r.id_barbero = b.id_barbero
        LEFT JOIN servicioreserva sr ON r.id_reserva = sr.id_reserva
        LEFT JOIN servicio s ON sr.id_servicio = s.id_servicio
        WHERE r.id_cliente = ?
        GROUP BY r.id_reserva, r.fecha, r.hora, r.estado, b.nombre, b.apellido
        ORDER BY r.fecha, r.hora';

$stmt = $conexion->prepare($sql);
$stmt->bind_param('i', $_SESSION['id_cliente']);
$stmt->execute();
$reservas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Activas: pendientes o confirmadas de hoy en adelante (se pueden cancelar).
// Historial: completadas, canceladas y las que ya pasaron (las 6 más recientes).
$hoy = date('Y-m-d');
$activas = [];
$historial = [];
foreach ($reservas as $reserva) {
    if (in_array($reserva['estado'], ['pendiente', 'confirmada'], true) && $reserva['fecha'] >= $hoy) {
        $activas[] = $reserva;
    } else {
        $historial[] = $reserva;
    }
}
$historial = array_slice(array_reverse($historial), 0, 6);

$nombresEstado = [
    'pendiente'  => 'Pendiente',
    'confirmada' => 'Confirmada',
    'completada' => 'Completada',
    'cancelada'  => 'Cancelada',
];

// Datos de una tarjeta de reserva (los usan las activas y el historial).
function mostrarDatosReserva($reserva, $nombresEstado) { ?>
                        <div class="reserva-datos">
                            <h2>Servicios:</h2>
                            <div class="servicios">
                                <p><?= e($reserva['servicios'] ?? 'Sin servicios') ?></p>
                            </div>
                            <span>Fecha:</span>
                            <p><?= date('d/m/Y', strtotime($reserva['fecha'])) ?></p>
                            <p><?= date('H:i', strtotime($reserva['hora'])) ?></p>
                            <span>Barbero:</span>
                            <p><?= e($reserva['barbero_nombre'] . ' ' . $reserva['barbero_apellido']) ?></p>
                            <span>Estado:</span>
                            <p><?= e($nombresEstado[$reserva['estado']] ?? $reserva['estado']) ?></p>
                        </div>
<?php }
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
    <link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/thin/style.css">
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
            <h2>Datos personales</h2>
            <div class="contenedor-datos">
                <div class="tarjeta">
                    <p class="categoria-dato">Nombre completo</p>
                    <div class="nombreapellido">
                        <p><?= e($_SESSION["nombre"]) ?></p>
                        <p><?= e($_SESSION["apellido"]) ?></p>
                    </div>
                </div>
                <div class="tarjeta">
                    <p class="categoria-dato">Correo</p>
                    <div class="nombreapellido">
                        <p><?= e($_SESSION["email"]) ?></p>
                    </div>
                </div>
            </div>
            <div id="contenedor-boton">
                <a href="modificar_perfil.php">
                    <button><i class="ph ph-pencil"></i> Editar perfil</button>
                </a>
            </div>
            <h2 id="h2-reservas">Tus reservas</h2>
            <div class="contenedor-reservas">
                <?php if (isset($_GET['cancelada'])): ?>
                    <p class="mensaje-exito">Reserva cancelada.</p>
                <?php elseif (isset($_GET['error']) && $_GET['error'] === 'cancelar'): ?>
                    <p class="mensaje-error">No se pudo cancelar esa reserva.</p>
                <?php endif; ?>
                <?php foreach ($activas as $reserva): ?>
                    <div class="tarjeta-reserva">
                        <div class="tarjeta-reserva-header">
                            <form action="eliminar_reserva.php" method="post" onsubmit="return confirm('¿Cancelar esta reserva?');">
                                <input type="hidden" name="id_reserva" value="<?= (int)$reserva['id_reserva'] ?>">
                                <?= csrf_campo() ?>
                                <button type="submit" class="papel-pin" aria-label="Cancelar reserva" title="Cancelar reserva"></button>
                            </form>
                        </div>
                        <?php mostrarDatosReserva($reserva, $nombresEstado); ?>
                    </div>
                <?php endforeach; ?>
                <?php if (count($activas) < RESERVA_MAX_ACTIVAS): ?>
                <a class="tarjeta-reserva-nuevo" href="reserva.php" title="Nueva reserva">
                    <i class="ph-thin ph-plus"></i>
                </a>
                <?php endif; ?>
            </div>
            <?php if (!empty($historial)): ?>
                <h2 id="h2-historial">Historial</h2>
                <div class="contenedor-reservas">
                    <?php foreach ($historial as $reserva): ?>
                        <div class="tarjeta-reserva">
                            <div class="tarjeta-reserva-header">
                                <span class="papel-pin fijo" aria-hidden="true"></span>
                            </div>
                            <?php mostrarDatosReserva($reserva, $nombresEstado); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>