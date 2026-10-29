<?php
require_once __DIR__ . '/comun.php';
requerir_cliente();

$hoy = date('Y-m-d');
$primerDiaValido = $hoy; // se puede reservar el mismo día; los horarios pasados se descartan
$finRango = date('Y-m-d', strtotime('+' . RESERVA_DIAS_ADELANTE . ' days'));

// Horarios que se muestran antes de elegir barbero y fecha (cada 30
// minutos). Después los reemplaza la respuesta de horarios.php.
$HORAS_INICIALES = [];
for ($m = hora_a_minutos(RESV_HORA_APERTURA); $m < hora_a_minutos(RESV_HORA_CIERRE); $m += RESV_INTERVALO_MINUTOS) {
    $HORAS_INICIALES[] = minutos_a_hora($m);
}

$errorFormulario = '';
$id_cliente = (int) $_SESSION["id_cliente"];

// Máximo de reservas activas (las completadas y canceladas no cuentan).
if (reservas_activas($conexion, $id_cliente) >= RESERVA_MAX_ACTIVAS && !isset($_GET['maxreservas'])) {
    header('Location: index.php?maxreservas');
    exit;
}

// ---------- Guardar la reserva ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_valido()) {
    $errorFormulario = 'El formulario venció. Volvé a intentarlo.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = (string) ($_SESSION['email'] ?? '');
    // Uno por categoría como máximo, pero pueden venir varias categorías
    // a la vez (ej: un corte + un servicio de barba).
    $enviados = isset($_POST['id_servicio']) && is_array($_POST['id_servicio']) ? array_values($_POST['id_servicio']) : [];
    $serviciosSeleccionados = servicios_validos($conexion, $enviados);
    $idBarbero = isset($_POST['id_barbero']) && is_string($_POST['id_barbero']) && ctype_digit($_POST['id_barbero']) ? (int) $_POST['id_barbero'] : 0;
    $fecha = post_texto('fecha');
    $hora = post_texto('hora');
    $minutos = hora_a_minutos($hora);
    $duracion = empty($serviciosSeleccionados) ? 0 : duracion_total_servicios_ids($conexion, $serviciosSeleccionados);

    if (empty($serviciosSeleccionados) || !$idBarbero || $fecha === '' || $hora === '') {
        $errorFormulario = 'Completá todos los campos.';
    } elseif (!barbero_disponible($conexion, $idBarbero)) {
        $errorFormulario = 'Ese barbero no está disponible. Elegí otro.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorFormulario = 'El email no es válido.';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || !validar_fecha_reserva($fecha)) {
        $errorFormulario = 'Elegí una fecha válida.';
    } elseif (es_dia_cerrado($fecha)) {
        $errorFormulario = 'Los domingos permanecemos cerrados.';
    } elseif (!fecha_reservable_web($fecha)) {
        $errorFormulario = 'Elegí una fecha entre hoy y los próximos ' . RESERVA_DIAS_ADELANTE . ' días.';
    } elseif ($minutos === null || $minutos % RESV_INTERVALO_MINUTOS !== 0) {
        $errorFormulario = 'Elegí un horario válido.';
    } elseif (turno_ya_paso($fecha, $hora)) {
        $errorFormulario = 'Ese horario ya pasó, elegí otro.';
    } elseif (!turno_dentro_de_horario($hora, $duracion)) {
        $errorFormulario = 'Con los servicios elegidos, ese horario termina después del cierre. Elegí uno más temprano.';
    } else {

        // Se guarda en una transacción con el cliente y la agenda del
        // barbero bloqueados: dos pedidos al mismo tiempo no pueden pasar
        // el máximo de reservas ni pisar el mismo turno.
        $conexion->begin_transaction();
        try {
            $stmt = $conexion->prepare("SELECT id_cliente FROM cliente WHERE id_cliente = ? FOR UPDATE");
            $stmt->bind_param("i", $id_cliente);
            $stmt->execute();
            $stmt->get_result();
            $stmt->close();

            bloquear_agenda_barbero($conexion, $idBarbero);

            if (reservas_activas($conexion, $id_cliente) >= RESERVA_MAX_ACTIVAS) {
                $conexion->rollback();
                header('Location: index.php?maxreservas');
                exit;
            }

            if (existe_conflicto_intervalo($conexion, $idBarbero, $fecha, $hora, $duracion)) {
                $conexion->rollback();
                header('Location: reserva.php?error=ocupado');
                exit;
            }

            $stmt = $conexion->prepare("INSERT INTO reserva (id_cliente, id_barbero, fecha, hora, email) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("iisss", $id_cliente, $idBarbero, $fecha, $hora, $email);
            $stmt->execute();
            $idReserva = $conexion->insert_id;
            $stmt->close();

            $stmt = $conexion->prepare("INSERT INTO servicioreserva (id_servicio, id_reserva) VALUES (?, ?)");
            foreach ($serviciosSeleccionados as $idServicio) {
                $stmt->bind_param("ii", $idServicio, $idReserva);
                $stmt->execute();
            }
            $stmt->close();

            $conexion->commit();

            header('Location: index.php?reservado=1');
            exit;

        } catch (mysqli_sql_exception $e) {
            $conexion->rollback();
            error_log('reserva.php: ' . $e->getMessage());
            $errorFormulario = 'No se pudo guardar la reserva, probá de nuevo.';
        }
    }
}

// ---------- Datos para mostrar el formulario ----------

// Servicios agrupados por categoría, para armar el acordeón.
$serviciosPorCategoria = [];
$resS = mysqli_query($conexion, "SELECT id_servicio, categoria, nombre, duracion, descripcion, precio FROM servicio ORDER BY categoria, id_servicio");
if ($resS) {
    while ($fila = mysqli_fetch_assoc($resS)) {
        $serviciosPorCategoria[$fila['categoria']][] = $fila;
    }
}

// Ícono y bajada de cada categoría (no están en la tabla `servicio`,
// así que se definen acá; agregá una entrada si sumás una categoría nueva).
$categoriaInfo = [
    'Servicios de Cortes' => ['icono' => 'ph-scissors', 'desc' => 'Cortes clásicos y modernos.'],
    'Servicios de Barba'  => ['icono' => 'ph-mask-happy', 'desc' => 'Perfilado, arreglo y cuidado de la barba.'],
    'Servicios de Color'  => ['icono' => 'ph-palette', 'desc' => 'Tintes, reflejos y diseños.'],
    'Servicios Extras'    => ['icono' => 'ph-star', 'desc' => 'Lavado, cejas, mascarillas y más.'],
];

// Clave simple para usar la categoría como índice de array en el <input>
// (así cada categoría es su propio grupo de radios: uno por categoría,
// pero categorías distintas no se pisan entre sí).
function claveCategoria($categoria) {
    $clave = mb_strtolower($categoria, 'UTF-8');
    $clave = preg_replace('/[^a-z0-9]+/', '_', $clave);
    return trim($clave, '_');
}

$barberos = [];
$resB = mysqli_query($conexion, "SELECT b.id_barbero, b.nombre, b.apellido, b.url_imagen, b.especialidad
                                FROM barbero b
                                WHERE b.estado = 'Disponible'");
if ($resB) {
    while ($fila = mysqli_fetch_assoc($resB)) {
        $barberos[] = $fila;
    }
}

function generarCeldasMes($anio, $mes, $hoy, $primerDiaValido, $finRango) {
    $celdas = [];
    $primerDiaTs = mktime(0, 0, 0, $mes, 1, $anio);
    $diasEnMes = (int)date('t', $primerDiaTs);
    $diaSemanaPrimero = (int)date('N', $primerDiaTs);

    $huecosIniciales = $diaSemanaPrimero - 1;

    if ($huecosIniciales > 0) {
        $diasMesAnterior = (int)date('t', mktime(0, 0, 0, $mes - 1, 1, $anio));

        for ($i = $huecosIniciales; $i >= 1; $i--) {
            $celdas[] = [
                'fuera' => true,
                'numero' => $diasMesAnterior - $i + 1
            ];
        }
    }

    for ($d = 1; $d <= $diasEnMes; $d++) {
        $fecha = sprintf('%04d-%02d-%02d', $anio, $mes, $d);

        $celdas[] = [
            'fuera' => false,
            'numero' => $d,
            'fecha' => $fecha,
            'deshabilitado' => $fecha < $primerDiaValido 
                || $fecha > $finRango
                || (int)date('N', mktime(0, 0, 0, $mes, $d, $anio)) === 7,
            'hoy' => $fecha === $hoy,
        ];
    }

    $siguiente = 1;

    while (count($celdas) % 7 !== 0) {
        $celdas[] = [
            'fuera' => true,
            'numero' => $siguiente
        ];
        $siguiente++;
    }

    return $celdas;
}

function renderCeldas($celdas) {
    foreach ($celdas as $c) {
        if ($c['fuera']) {
            echo '<div class="calendario-dia fuera">' . $c['numero'] . '</div>';
            continue;
        }
        $clases = 'calendario-dia' . ($c['deshabilitado'] ? ' deshabilitado' : '') . ($c['hoy'] ? ' hoy' : '');
        echo '<div class="' . $clases . '" data-fecha="' . $c['fecha'] . '">' . $c['numero'] . '</div>';
    }
}

$nombresMes = ['', 'Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
$mesActualNum = (int)date('n');
$anioActualNum = (int)date('Y');
$mesSiguienteNum = $mesActualNum == 12 ? 1 : $mesActualNum + 1;
$anioSiguienteNum = $mesActualNum == 12 ? $anioActualNum + 1 : $anioActualNum;
$celdasActual = generarCeldasMes($anioActualNum, $mesActualNum, $hoy, $primerDiaValido, $finRango);
$celdasSiguiente = generarCeldasMes($anioSiguienteNum, $mesSiguienteNum, $hoy, $primerDiaValido, $finRango);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reserva - Netro</title>
    <link rel="stylesheet" href="../css/body.css">
    <link rel="stylesheet" href="../css/header.css">
    <link rel="stylesheet" href="../css/reserva.css">
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
    <main>
        <div class="panel-reserva">
            <?php if ($errorFormulario): ?>
                <div class="reserva-error"><?= htmlspecialchars($errorFormulario) ?></div>
            <?php elseif (isset($_GET['error']) && $_GET['error'] === 'ocupado'): ?>
                <div class="reserva-error">Ese horario se acaba de ocupar. Elegí otro.</div>
            <?php endif; ?>
            <form method="POST" id="form-reserva" class="reserva-grid">
                <?= csrf_campo() ?>
                <div class="reserva-columna">
                    <div class="reserva-encabezado">
                        <div class="reserva-icono"><i class="ph ph-scissors"></i></div>
                        <div>
                            <h1>Reservá tu turno</h1>
                            <p>Elegí la fecha y hora que mejor te quede.</p>
                        </div>
                    </div>
                    <div class="reserva-separador"><span></span><i class="ph ph-scissors"></i><span></span></div>

                    <p class="reserva-subtitulo"><i class="ph ph-scissors"></i> Seleccioná un servicio</p>
                    <?php if (empty($serviciosPorCategoria)): ?>
                        <p class="reserva-aviso">No hay servicios cargados por el momento.</p>
                    <?php else: ?>
                        <div class="acordeon-servicios" id="acordeon-servicios">
                            <?php foreach ($serviciosPorCategoria as $categoria => $servicios): ?>
                                <?php
                                    $info = $categoriaInfo[$categoria] ?? ['icono' => 'ph-sparkle', 'desc' => ''];
                                ?>
                                <div class="categoria-servicio">
                                    <button type="button" class="categoria-header">
                                        <i class="ph <?= $info['icono'] ?>"></i>
                                        <span class="categoria-textos">
                                            <span class="categoria-titulo"><?= htmlspecialchars($categoria) ?></span>
                                            <span class="categoria-desc"><?= htmlspecialchars($info['desc']) ?></span>
                                        </span>
                                        <i class="ph ph-caret-down categoria-caret"></i>
                                    </button>
                                    <div class="categoria-lista-envoltorio">
                                        <div class="categoria-lista">
                                            <?php foreach ($servicios as $s): ?>
                                                <label class="tarjeta-servicio">
                                                    <input type="radio" name="id_servicio[<?= claveCategoria($categoria) ?>]" value="<?= (int)$s['id_servicio'] ?>">
                                                    <span class="tarjeta-servicio-info">
                                                        <span class="tarjeta-servicio-nombre"><?= htmlspecialchars($s['nombre']) ?></span>
                                                        <span class="tarjeta-servicio-desc"><?= htmlspecialchars($s['descripcion']) ?></span>
                                                    </span>
                                                    <span class="tarjeta-servicio-datos">
                                                        <span><i class="ph ph-clock"></i> <?= htmlspecialchars($s['duracion']) ?></span>
                                                        <span><i class="ph ph-currency-dollar"></i> <?= htmlspecialchars($s['precio']) ?></span>
                                                    </span>
                                                    <span class="tarjeta-servicio-radio"></span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="reserva-seccion-barbero" id="seccion-barbero">
                        <p class="reserva-subtitulo"><i class="ph ph-users-three"></i> Seleccioná el barbero</p>
                        <?php if (empty($barberos)): ?>
                            <p class="reserva-aviso">No hay barberos disponibles por el momento.</p>
                        <?php else: ?>
                            <p class="reserva-aviso" id="aviso-elegir-servicio"><i class="ph ph-user"></i> Seleccioná primero un servicio para elegir el barbero.</p>
                            <div class="mini-tarjetas-grid" id="lista-barberos" style="display:none">
                                <?php foreach ($barberos as $b): ?>
                                    <?php
                                        $fotoB = foto_barbero($b['url_imagen']);
                                        $nombreB = $b['nombre'] . ' ' . $b['apellido'];
                                    ?>
                                    <div class="mini-tarjeta-barbero" data-id="<?= (int)$b['id_barbero'] ?>">
                                        <img src="<?= htmlspecialchars($fotoB) ?>" alt="Foto de <?= htmlspecialchars($nombreB) ?>">
                                        <p class="mini-tarjeta-nombre"><?= htmlspecialchars($nombreB) ?></p>
                                        <p class="mini-tarjeta-especialidad"><?= htmlspecialchars($b['especialidad'] ?: 'Barbero') ?></p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <input type="hidden" name="id_barbero" id="input-id-barbero">
                    <input type="hidden" name="fecha" id="input-fecha">
                    <input type="hidden" name="hora" id="input-hora">

                    <button type="submit" class="boton-reservar"><i class="ph ph-calendar-check"></i> Reservar turno</button>
                    <p class="reserva-nota"><i class="ph ph-lock-simple"></i> Tus datos están protegidos y no serán compartidos.</p>
                </div>
                <div class="reserva-columna">
                    <p class="reserva-subtitulo"><i class="ph ph-calendar"></i> Seleccioná la fecha</p>
                    <div class="calendario">
                        <div class="calendario-header">
                            <button type="button" id="mes-anterior" disabled><i class="ph ph-caret-left"></i></button>
                            <span id="calendario-titulo"><?= $nombresMes[$mesActualNum] . ' ' . $anioActualNum ?></span>
                            <button type="button" id="mes-siguiente"><i class="ph ph-caret-right"></i></button>
                        </div>
                        <div class="calendario-dias-semana">
                            <span>LUN</span><span>MAR</span><span>MIÉ</span><span>JUE</span><span>VIE</span><span>SÁB</span><span>DOM</span>
                        </div>
                        <div class="calendario-grid" id="grid-mes-actual" data-titulo="<?= $nombresMes[$mesActualNum] . ' ' . $anioActualNum ?>">
                            <?php renderCeldas($celdasActual); ?>
                        </div>
                        <div class="calendario-grid" id="grid-mes-siguiente" data-titulo="<?= $nombresMes[$mesSiguienteNum] . ' ' . $anioSiguienteNum ?>" style="display:none">
                            <?php renderCeldas($celdasSiguiente); ?>
                        </div>
                    </div>

                    <p class="reserva-subtitulo"><i class="ph ph-clock"></i> Seleccioná la hora</p>
                    <div class="horarios-grid" id="grid-horarios">
                        <?php foreach ($HORAS_INICIALES as $h): ?>
                            <button type="button" class="horario-slot" data-hora="<?= $h ?>"><?= $h ?></button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </form>
            <div class="reserva-abajo">
                <i class="ph ph-scissors"></i> Gracias por elegirnos. ¡Te esperamos!
            </div>
        </div>
    </main>
    <script>
        const gridHorarios = document.getElementById('grid-horarios');
        let pedidoHorarios = 0;
        let barberoSeleccionado = null;
        let fechaSeleccionada = null;
        let horaSeleccionada = null;

        // Acordeón de categorías de servicios
        document.querySelectorAll('.categoria-header').forEach(header => {
            header.addEventListener('click', () => {
                const categoria = header.closest('.categoria-servicio');
                const yaAbierta = categoria.classList.contains('abierta');
                document.querySelectorAll('.categoria-servicio').forEach(c => c.classList.remove('abierta'));
                if (!yaAbierta) categoria.classList.add('abierta');
            });
        });

        // Al elegir un servicio, se habilita la selección de barbero.
        // Además, si tocás de nuevo el servicio ya elegido de esa categoría,
        // se deselecciona (los radios nativos no permiten esto solos).
        const avisoElegirServicio = document.getElementById('aviso-elegir-servicio');
        const listaBarberos = document.getElementById('lista-barberos');
        const serviciosElegidos = {};

        function actualizarSeccionBarbero() {
            const hayServicioElegido = Object.keys(serviciosElegidos).length > 0;
            if (avisoElegirServicio) avisoElegirServicio.style.display = hayServicioElegido ? 'none' : '';
            if (listaBarberos) listaBarberos.style.display = hayServicioElegido ? 'flex' : 'none';
        }

        document.querySelectorAll('input[name^="id_servicio"]').forEach(radio => {
            radio.addEventListener('click', () => {
                if (serviciosElegidos[radio.name] === radio.value) {
                    radio.checked = false;
                    delete serviciosElegidos[radio.name];
                } else {
                    serviciosElegidos[radio.name] = radio.value;
                }
                const categoria = radio.closest('.categoria-servicio');
                categoria.classList.toggle('con-seleccion', !!categoria.querySelector('input[type="radio"]:checked'));
                actualizarSeccionBarbero();
                actualizarHorarios();
            });
        });

        document.querySelectorAll('.mini-tarjeta-barbero').forEach(tarjeta => {
            tarjeta.addEventListener('click', () => {
                document.querySelectorAll('.mini-tarjeta-barbero').forEach(t => t.classList.remove('seleccionado'));
                tarjeta.classList.add('seleccionado');
                barberoSeleccionado = tarjeta.dataset.id;
                document.getElementById('input-id-barbero').value = barberoSeleccionado;
                actualizarHorarios();
            });
        });

        const gridActual = document.getElementById('grid-mes-actual');
        const gridSiguiente = document.getElementById('grid-mes-siguiente');
        const btnAnterior = document.getElementById('mes-anterior');
        const btnSiguiente = document.getElementById('mes-siguiente');
        const tituloCalendario = document.getElementById('calendario-titulo');

        btnSiguiente.addEventListener('click', () => {
            gridActual.style.display = 'none';
            gridSiguiente.style.display = 'grid';
            tituloCalendario.textContent = gridSiguiente.dataset.titulo;
            btnSiguiente.disabled = true;
            btnAnterior.disabled = false;
        });
        btnAnterior.addEventListener('click', () => {
            gridSiguiente.style.display = 'none';
            gridActual.style.display = 'grid';
            tituloCalendario.textContent = gridActual.dataset.titulo;
            btnAnterior.disabled = true;
            btnSiguiente.disabled = false;
        });

        document.querySelectorAll('.calendario-dia:not(.fuera):not(.deshabilitado)').forEach(dia => {
            dia.addEventListener('click', () => {
                document.querySelectorAll('.calendario-dia').forEach(d => d.classList.remove('seleccionado'));
                dia.classList.add('seleccionado');
                fechaSeleccionada = dia.dataset.fecha;
                document.getElementById('input-fecha').value = fechaSeleccionada;
                actualizarHorarios();
            });
        });

        function serviciosMarcados() {
            return Array.from(document.querySelectorAll('input[name^="id_servicio"]:checked')).map(r => r.value);
        }

        // Pide al servidor los horarios del barbero para esa fecha, según la
        // duración de los servicios elegidos (cada 30 minutos, sin pisar
        // otras reservas), y arma los botones.
        function actualizarHorarios() {
            const servicios = serviciosMarcados();
            if (!barberoSeleccionado || !fechaSeleccionada || servicios.length === 0) return;
            const numero = ++pedidoHorarios;
            const params = new URLSearchParams({
                id_barbero: barberoSeleccionado,
                fecha: fechaSeleccionada,
                servicios: servicios.join(',')
            });
            fetch('horarios.php?' + params.toString())
                .then(r => r.json())
                .then(datos => {
                    if (numero !== pedidoHorarios) return; // ya hay un pedido más nuevo
                    dibujarHorarios(datos.ok ? datos.horarios : []);
                })
                .catch(() => {
                    if (numero === pedidoHorarios) dibujarHorarios([]);
                });
        }

        function dibujarHorarios(horarios) {
            const anterior = horaSeleccionada;
            gridHorarios.innerHTML = '';
            horaSeleccionada = null;
            document.getElementById('input-hora').value = '';

            if (horarios.length === 0) {
                const aviso = document.createElement('p');
                aviso.className = 'reserva-aviso';
                aviso.style.gridColumn = '1 / -1';
                aviso.textContent = 'No hay horarios disponibles ese día con los servicios elegidos.';
                gridHorarios.appendChild(aviso);
                return;
            }

            horarios.forEach(h => {
                const boton = document.createElement('button');
                boton.type = 'button';
                boton.className = 'horario-slot';
                boton.dataset.hora = h.hora;
                boton.textContent = h.hora;
                if (!h.disponible) {
                    boton.classList.add('ocupado');
                    if (h.motivo === 'pasado') boton.classList.add('pasado');
                } else if (h.hora === anterior) {
                    // Si el horario que estaba elegido sigue libre, se mantiene.
                    boton.classList.add('seleccionado');
                    horaSeleccionada = h.hora;
                    document.getElementById('input-hora').value = h.hora;
                }
                gridHorarios.appendChild(boton);
            });
        }

        // Los botones se vuelven a crear con cada respuesta del servidor,
        // por eso el clic se escucha en la grilla.
        gridHorarios.addEventListener('click', (e) => {
            const slot = e.target.closest('.horario-slot');
            if (!slot) return;
            if (slot.classList.contains('pasado')) {
                return;
            }
            if (slot.classList.contains('ocupado')) {
                alert('Ese horario ya está reservado. Elegí otro horario o barbero.');
                return;
            }
            if (!barberoSeleccionado || !fechaSeleccionada) {
                alert('Elegí primero un barbero y una fecha.');
                return;
            }
            document.querySelectorAll('.horario-slot').forEach(s => s.classList.remove('seleccionado'));
            slot.classList.add('seleccionado');
            horaSeleccionada = slot.dataset.hora;
            document.getElementById('input-hora').value = horaSeleccionada;
        });

        document.getElementById('form-reserva').addEventListener('submit', (e) => {
            if (!document.querySelector('input[name^="id_servicio"]:checked')) {
                e.preventDefault();
                alert('Elegí un servicio.');
                return;
            }
            if (!barberoSeleccionado) {
                e.preventDefault();
                alert('Elegí un barbero.');
                return;
            }
            if (!fechaSeleccionada || !horaSeleccionada) {
                e.preventDefault();
                alert('Elegí una fecha y un horario.');
            }
        });
    </script>
</body>
</html>