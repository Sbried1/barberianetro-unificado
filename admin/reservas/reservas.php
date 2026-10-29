<?php

require_once __DIR__ . '/../auth/guard.php';
auth_requerir('reservas');

include("../conexion.php");
include("reservas_funciones.php");

// =====================================================
// ESTADISTICAS (tarjetas superiores): cantidad de reservas por estado.
// =====================================================
$totales_estado = ["pendiente" => 0, "confirmada" => 0, "completada" => 0, "cancelada" => 0];
$resultado_estados = mysqli_query($conexion, "SELECT estado, COUNT(*) AS total FROM reserva GROUP BY estado");
while ($fila = mysqli_fetch_assoc($resultado_estados)) {
    if (isset($totales_estado[$fila["estado"]])) {
        $totales_estado[$fila["estado"]] = (int) $fila["total"];
    }
}

// =====================================================
// LISTAS PARA LOS SELECTS (formularios y filtro de barbero)
// =====================================================
$sql_clientes = "SELECT id_cliente, nombre, apellido FROM cliente ORDER BY nombre, apellido";
$resultado_clientes = mysqli_query($conexion, $sql_clientes);

$sql_barberos = "SELECT id_barbero, nombre, apellido FROM barbero ORDER BY nombre, apellido";
$resultado_barberos = mysqli_query($conexion, $sql_barberos);

$sql_servicios = "SELECT id_servicio, nombre, precio, duracion FROM servicio ORDER BY nombre";
$resultado_servicios = mysqli_query($conexion, $sql_servicios);

$lista_clientes = [];
while ($fila = mysqli_fetch_assoc($resultado_clientes)) {
    $lista_clientes[] = $fila;
}

$lista_barberos = [];
while ($fila = mysqli_fetch_assoc($resultado_barberos)) {
    $lista_barberos[] = $fila;
}

$lista_servicios = [];
while ($fila = mysqli_fetch_assoc($resultado_servicios)) {
    $lista_servicios[] = $fila;
}

// =====================================================
// RESERVAS + JOINS (cliente, barbero, servicios)
// El JOIN con cliente es LEFT para que la reserva se siga listando
// aunque falte el cliente (se muestra "Cliente eliminado").
// =====================================================
$sql_reservas = "
    SELECT
        r.id_reserva, r.id_cliente, r.id_barbero, r.fecha, r.hora, r.email, r.estado,
        c.nombre AS cliente_nombre, c.apellido AS cliente_apellido,
        b.nombre AS barbero_nombre, b.apellido AS barbero_apellido
    FROM reserva r
    LEFT JOIN cliente c ON c.id_cliente = r.id_cliente
    INNER JOIN barbero b ON b.id_barbero = r.id_barbero
    ORDER BY r.fecha DESC, r.hora DESC
";
$resultado_reservas = mysqli_query($conexion, $sql_reservas);

// Traemos el detalle de servicios de todas las reservas de una sola vez
// (vía servicioreserva, que reemplaza a detalle_reserva) y lo agrupamos
// en PHP por id_reserva (evita 1 consulta por fila). servicioreserva no
// guarda precio propio: el precio real siempre se lee desde "servicio"
// (que además es varchar, por eso se castea a float).
$sql_detalle = "
    SELECT sr.id_reserva, s.id_servicio, s.nombre, s.precio
    FROM servicioreserva sr
    INNER JOIN servicio s ON s.id_servicio = sr.id_servicio
";
$resultado_detalle = mysqli_query($conexion, $sql_detalle);

$detalle_por_reserva = [];
while ($fila = mysqli_fetch_assoc($resultado_detalle)) {
    $detalle_por_reserva[$fila["id_reserva"]][] = [
        "id_servicio" => (int) $fila["id_servicio"],
        "nombre"      => $fila["nombre"],
        "precio"      => (float) $fila["precio"],
    ];
}

$reservas = [];
while ($fila = mysqli_fetch_assoc($resultado_reservas)) {

    $id_reserva = (int) $fila["id_reserva"];
    $servicios_reserva = isset($detalle_por_reserva[$id_reserva]) ? $detalle_por_reserva[$id_reserva] : [];

    $nombres_servicios = array_map(function ($s) { return $s["nombre"]; }, $servicios_reserva);
    $ids_servicios     = array_map(function ($s) { return $s["id_servicio"]; }, $servicios_reserva);
    $precio_total       = array_sum(array_map(function ($s) { return $s["precio"]; }, $servicios_reserva));

    $reservas[] = [
        "id_reserva"        => $id_reserva,
        "id_cliente"        => (string) $fila["id_cliente"],
        "cliente"            => $fila["cliente_nombre"] !== null ? trim($fila["cliente_nombre"] . " " . $fila["cliente_apellido"]) : "Cliente eliminado",
        "id_barbero"        => (int) $fila["id_barbero"],
        "barbero"            => trim($fila["barbero_nombre"] . " " . $fila["barbero_apellido"]),
        "servicios"          => $servicios_reserva,
        "servicios_nombres" => implode(", ", $nombres_servicios),
        "servicios_ids"      => $ids_servicios,
        "precio_total"       => $precio_total,
        "fecha"              => $fila["fecha"],
        "hora"               => substr($fila["hora"], 0, 5),
        "email"              => $fila["email"],
        "estado"             => $fila["estado"],
    ];
}

$json_reservas = json_encode(
    $reservas,
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../Estilos_index/global.css">
    <link rel="stylesheet" href="reservas_panel.css">
    <title>Barberia - Reservas</title>
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
<?php
$pagina_actual = basename($_SERVER['PHP_SELF']);
?>
            <nav class="menu-nav">
                <ul>

                    <li>
                        <a href="../index.php"
                        <?= $pagina_actual === 'index.php' ? 'aria-current="page"' : '' ?>>
                            Dashboard
                        </a>
                    </li>

                    <li>
                        <a href="../servicios_categoria/servicios.php"
                        <?= $pagina_actual === 'servicios.php' ? 'aria-current="page"' : '' ?>>
                            Servicios
                        </a>
                    </li>

                    <li>
                        <a href="../reservas/reservas.php"
                        <?= $pagina_actual === 'reservas.php' ? 'aria-current="page"' : '' ?>>
                            Reservas
                        </a>
                    </li>

                    <li>
                        <a href="../cliente/clientes.php"
                        <?= $pagina_actual === 'clientes.php' ? 'aria-current="page"' : '' ?>>
                            Clientes
                        </a>
                    </li>

                    <li>
                        <a href="../barberos/barberos.php"
                        <?= $pagina_actual === 'barberos.php' ? 'aria-current="page"' : '' ?>>
                            Barberos
                        </a>
                    </li>

                    <li>
                        <a href="../venta/ventas.php"
                        <?= $pagina_actual === 'ventas.php' ? 'aria-current="page"' : '' ?>>
                            Ventas
                        </a>
                    </li>

                    <li>
                        <a href="#">
                            Configuración
                        </a>
                    </li>
                    <?php auth_render_logout_item(); ?>

                </ul>
            </nav>
        </section>

        <!-- ==================== PANEL PRINCIPAL: RESERVAS ==================== -->
        <section class="panel">

            <div class="servicios_header">
                <div class="title_servicios">
                    <h2>Reservas</h2>
                    <p>Gestiona las reservas de la barbería</p>
                </div>

                <button class="btn_add_servicio" id="btn_abrir_reserva">
                    <img src="../img/mas.png" alt="">
                    <span>Nueva reserva</span>
                </button>
            </div>

            <!-- ALERTAS -->
            <?php if (isset($_GET["error"]) && $_GET["error"] == "invalido") { ?>
                <div class="resv_alerta error" id="alerta_php">
                    <p>❌ Revisá los datos ingresados: hay campos obligatorios vacíos o inválidos.</p>
                    <button onclick="this.parentElement.style.display='none'">Aceptar</button>
                </div>
            <?php } ?>

            <?php if (isset($_GET["error"]) && $_GET["error"] == "no_existe") { ?>
                <div class="resv_alerta error" id="alerta_php">
                    <p>❌ El cliente, barbero o reserva seleccionado no existe.</p>
                    <button onclick="this.parentElement.style.display='none'">Aceptar</button>
                </div>
            <?php } ?>

            <?php if (isset($_GET["error"]) && $_GET["error"] == "conflicto") { ?>
                <div class="resv_alerta error" id="alerta_php">
                    <p>❌ Ese horario se superpone con otra reserva del mismo barbero. Elegí un horario libre.</p>
                    <button onclick="this.parentElement.style.display='none'">Aceptar</button>
                </div>
            <?php } ?>

            <?php if (isset($_GET["error"]) && $_GET["error"] == "cerrado") { ?>
                <div class="resv_alerta error" id="alerta_php">
                    <p>❌ Los domingos la barbería está cerrada. Elegí otro día.</p>
                    <button onclick="this.parentElement.style.display='none'">Aceptar</button>
                </div>
            <?php } ?>

            <?php if (isset($_GET["error"]) && $_GET["error"] == "pasado") { ?>
                <div class="resv_alerta error" id="alerta_php">
                    <p>❌ Ese horario ya pasó. Elegí una fecha y hora futuras.</p>
                    <button onclick="this.parentElement.style.display='none'">Aceptar</button>
                </div>
            <?php } ?>

            <?php if (isset($_GET["error"]) && $_GET["error"] == "fuera_horario") { ?>
                <div class="resv_alerta error" id="alerta_php">
                    <p>❌ El turno queda fuera del horario de atención (<?php echo RESV_HORA_APERTURA; ?> a <?php echo RESV_HORA_CIERRE; ?>) con la duración de los servicios elegidos.</p>
                    <button onclick="this.parentElement.style.display='none'">Aceptar</button>
                </div>
            <?php } ?>

            <?php if (isset($_GET["error"]) && $_GET["error"] == "guardar") { ?>
                <div class="resv_alerta error" id="alerta_php">
                    <p>❌ Ocurrió un error al guardar la reserva. Intentá nuevamente.</p>
                    <button onclick="this.parentElement.style.display='none'">Aceptar</button>
                </div>
            <?php } ?>

            <?php if (isset($_GET["error"]) && $_GET["error"] == "restriccion") { ?>
                <div class="resv_alerta error" id="alerta_php">
                    <p>❌ No se pudo eliminar la reserva porque tiene información relacionada.</p>
                    <button onclick="this.parentElement.style.display='none'">Aceptar</button>
                </div>
            <?php } ?>

            <?php if (isset($_GET["error"]) && $_GET["error"] == "estado") { ?>
                <div class="resv_alerta error" id="alerta_php">
                    <p>❌ La reserva no está en un estado que permita esa acción (las completadas y canceladas no se modifican).</p>
                    <button onclick="this.parentElement.style.display='none'">Aceptar</button>
                </div>
            <?php } ?>

            <?php if (isset($_GET["error"]) && $_GET["error"] == "historial") { ?>
                <div class="resv_alerta error" id="alerta_php">
                    <p>❌ Las reservas completadas, canceladas o con una venta registrada forman parte del historial y no se pueden eliminar.</p>
                    <button onclick="this.parentElement.style.display='none'">Aceptar</button>
                </div>
            <?php } ?>

            <?php if (isset($_GET["ok"]) && $_GET["ok"] == "confirmada") { ?>
                <div class="resv_alerta ok" id="alerta_php">
                    <p>✅ Reserva confirmada.</p>
                    <button onclick="this.parentElement.style.display='none'">Aceptar</button>
                </div>
            <?php } ?>

            <?php if (isset($_GET["ok"]) && $_GET["ok"] == "cancelada") { ?>
                <div class="resv_alerta ok" id="alerta_php">
                    <p>✅ Reserva cancelada. Se conserva en el historial.</p>
                    <button onclick="this.parentElement.style.display='none'">Aceptar</button>
                </div>
            <?php } ?>

            <?php if (isset($_GET["ok"]) && $_GET["ok"] == "agregado") { ?>
                <div class="resv_alerta ok" id="alerta_php">
                    <p>✅ Reserva creada correctamente.</p>
                    <button onclick="this.parentElement.style.display='none'">Aceptar</button>
                </div>
            <?php } ?>

            <?php if (isset($_GET["ok"]) && $_GET["ok"] == "actualizado") { ?>
                <div class="resv_alerta ok" id="alerta_php">
                    <p>✅ Reserva actualizada correctamente.</p>
                    <button onclick="this.parentElement.style.display='none'">Aceptar</button>
                </div>
            <?php } ?>

            <?php if (isset($_GET["ok"]) && $_GET["ok"] == "eliminado") { ?>
                <div class="resv_alerta ok" id="alerta_php">
                    <p>✅ Reserva eliminada correctamente.</p>
                    <button onclick="this.parentElement.style.display='none'">Aceptar</button>
                </div>
            <?php } ?>

            <!-- FILTROS -->
            <div id="resv_filtros">
                <input type="text" id="resv_buscar" placeholder="Buscar por cliente, servicio o barbero...">

                <div class="resv_filtro_fecha">
                    <label for="resv_desde">Desde</label>
                    <input type="date" id="resv_desde">
                </div>

                <div class="resv_filtro_fecha">
                    <label for="resv_hasta">Hasta</label>
                    <input type="date" id="resv_hasta">
                </div>

                <select id="resv_filtro_barbero">
                    <option value="">Todos los barberos</option>
                    <?php foreach ($lista_barberos as $b) { ?>
                        <option value="<?php echo (int) $b["id_barbero"]; ?>">
                            <?php echo htmlspecialchars(trim($b["nombre"] . " " . $b["apellido"])); ?>
                        </option>
                    <?php } ?>
                </select>

                <select id="resv_filtro_estado">
                    <option value="activas">Pendientes y confirmadas</option>
                    <option value="pendiente">Pendientes</option>
                    <option value="confirmada">Confirmadas</option>
                    <option value="completada">Completadas</option>
                    <option value="cancelada">Canceladas</option>
                    <option value="">Historial completo</option>
                </select>
            </div>

            <!-- ESTADISTICAS -->
            <div class="resv_stats">
                <div class="resv_stat_card">
                    <div class="resv_stat_icono ambar">⏱</div>
                    <div class="resv_stat_texto">
                        <p>Pendientes</p>
                        <span><?php echo (int) $totales_estado["pendiente"]; ?></span>
                    </div>
                </div>

                <div class="resv_stat_card">
                    <div class="resv_stat_icono verde">✓</div>
                    <div class="resv_stat_texto">
                        <p>Confirmadas</p>
                        <span><?php echo (int) $totales_estado["confirmada"]; ?></span>
                    </div>
                </div>

                <div class="resv_stat_card">
                    <div class="resv_stat_icono morado">📅</div>
                    <div class="resv_stat_texto">
                        <p>Completadas</p>
                        <span><?php echo (int) $totales_estado["completada"]; ?></span>
                    </div>
                </div>

                <div class="resv_stat_card">
                    <div class="resv_stat_icono rojo">✕</div>
                    <div class="resv_stat_texto">
                        <p>Canceladas</p>
                        <span><?php echo (int) $totales_estado["cancelada"]; ?></span>
                    </div>
                </div>
            </div>

            <!-- TABS DE VISTA -->
            <div class="resv_panel_vistas">

                <div class="resv_tabs">
                    <button type="button" class="resv_tab activo" data-vista="lista" id="tab_lista">
                        <span>☰</span> Lista
                    </button>
                    <button type="button" class="resv_tab" data-vista="calendario" id="tab_calendario">
                        <span>📅</span> Calendario
                    </button>
                </div>

                <!-- ==================== VISTA LISTA ==================== -->
                <div id="vista_lista" class="resv_vista">

                    <div class="resv_cabecera_lista">
                        <p>ID</p>
                        <p>Cliente</p>
                        <p>Servicio</p>
                        <p>Barbero</p>
                        <p>Fecha</p>
                        <p>Hora</p>
                        <p>Estado</p>
                        <p>Acciones</p>
                    </div>

                    <div id="lista_reservas_body"></div>

                    <div class="resv_vacio" id="resv_lista_vacia">
                        <div class="resv_vacio_icono">📅</div>
                        <p class="resv_vacio_titulo" id="resv_vacio_titulo">No hay reservas registradas</p>
                        <p class="resv_vacio_texto" id="resv_vacio_texto">Aún no se han creado reservas.</p>
                        <button type="button" class="btn_add_servicio" id="btn_abrir_reserva_vacio">
                            <img src="../img/mas.png" alt="">
                            <span>Nueva reserva</span>
                        </button>
                    </div>

                    <div class="resv_paginacion" id="resv_paginacion">
                        <p id="resv_paginacion_texto">Mostrando 0 a 0 de 0 reservas</p>
                        <div class="resv_paginacion_botones">
                            <button type="button" id="resv_pag_anterior">Anterior</button>
                            <span id="resv_pag_actual">1</span>
                            <button type="button" id="resv_pag_siguiente">Siguiente</button>
                        </div>
                    </div>

                </div>

                <!-- ==================== VISTA CALENDARIO ==================== -->
                <div id="vista_calendario" class="resv_vista" style="display:none;">

                    <div class="resv_cal_toolbar">
                        <div class="resv_cal_nav">
                            <button type="button" id="cal_anterior">&lsaquo;</button>
                            <button type="button" id="cal_hoy">Hoy</button>
                            <button type="button" id="cal_siguiente">&rsaquo;</button>
                        </div>

                        <h3 id="cal_titulo"></h3>

                        <div class="resv_cal_modos">
                            <button type="button" class="resv_cal_modo activo" data-modo="mes">Mes</button>
                            <button type="button" class="resv_cal_modo" data-modo="semana">Semana</button>
                            <button type="button" class="resv_cal_modo" data-modo="dia">Día</button>
                        </div>
                    </div>

                    <div id="calendario_contenedor"></div>

                    <div class="resv_cal_leyenda">
                        <span><i class="punto pendiente"></i> Pendiente</span>
                        <span><i class="punto confirmada"></i> Confirmada</span>
                        <span><i class="punto completada"></i> Completada</span>
                        <span><i class="punto cancelada"></i> Cancelada</span>
                        <span class="resv_cal_ayuda">Hacé clic en un evento para ver más detalles</span>
                    </div>

                </div>

            </div>

        </section>

    </div>

    <!-- ==================== MODAL: NUEVA RESERVA ==================== -->
    <div id="modal_reserva" class="modal">
        <div class="modal-contenido_serv modal_reserva_contenido">
            <span class="cerrar_modal" id="cerrar_modal_reserva">&times;</span>

            <div class="categoria_header">
                <div class="title_servicios">
                    <h2>Nueva reserva</h2>
                    <p>Elegí barbero, servicios, fecha y horario para agendar.</p>
                </div>
            </div>

            <form action="guardar_reserva.php" method="POST" id="form_reserva">
                <div id="resv_caja_form" class="resv_form_reserva">

                    <div class="resv_paso">
                        <p class="resv_paso_titulo">Cliente</p>
                        <select name="id_cliente" class="resv_select" required>
                            <option value="">Seleccioná un cliente</option>
                            <?php foreach ($lista_clientes as $c) { ?>
                                <option value="<?php echo htmlspecialchars((string) $c["id_cliente"]); ?>">
                                    <?php echo htmlspecialchars(trim($c["nombre"] . " " . $c["apellido"])); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>

                    <div class="resv_paso">
                        <p class="resv_paso_titulo"><span class="resv_paso_num">1</span> Barbero</p>
                        <select name="id_barbero" class="resv_select" id="resv_nueva_barbero" required>
                            <option value="">Seleccioná un barbero</option>
                            <?php foreach ($lista_barberos as $b) { ?>
                                <option value="<?php echo (int) $b["id_barbero"]; ?>">
                                    <?php echo htmlspecialchars(trim($b["nombre"] . " " . $b["apellido"])); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>

                    <div class="resv_paso">
                        <p class="resv_paso_titulo"><span class="resv_paso_num">2</span> Servicios</p>
                        <div class="resv_servicios_check" id="resv_nueva_servicios">
                            <?php foreach ($lista_servicios as $s) { ?>
                                <label class="resv_servicio_item">
                                    <input type="checkbox" name="servicios[]"
                                           value="<?php echo (int) $s["id_servicio"]; ?>"
                                           data-precio="<?php echo htmlspecialchars($s["precio"]); ?>">
                                    <span class="resv_servicio_nombre">
                                        <?php echo htmlspecialchars($s["nombre"]); ?>
                                        <?php if (!empty($s["duracion"])) { ?>
                                            <small>(<?php echo htmlspecialchars(formato_duracion_minutos(parsear_duracion_a_minutos($s["duracion"]))); ?>)</small>
                                        <?php } ?>
                                    </span>
                                    <span class="resv_servicio_precio">$<?php echo htmlspecialchars($s["precio"]); ?></span>
                                </label>
                            <?php } ?>
                        </div>
                    </div>

                    <div class="resv_paso">
                        <p class="resv_paso_titulo"><span class="resv_paso_num">3</span> Fecha</p>
                        <input type="date" name="fecha" class="resv_input" id="resv_nueva_fecha" required>
                    </div>

                    <div class="resv_paso">
                        <p class="resv_paso_titulo"><span class="resv_paso_num">4</span> Horario</p>

                        <div class="resv_horarios_leyenda">
                            <span><i class="resv_punto_leyenda disponible"></i> Disponible</span>
                            <span><i class="resv_punto_leyenda ocupado"></i> Ocupado</span>
                            <span><i class="resv_punto_leyenda seleccionado"></i> Seleccionado</span>
                            <span><i class="resv_punto_leyenda pasado"></i> Ya pasó</span>
                        </div>

                        <div class="resv_horarios_grid" id="resv_horarios_grid">
                            <p class="resv_horarios_mensaje">
                                Elegí barbero, al menos un servicio y una fecha para ver los horarios disponibles.
                            </p>
                        </div>

                        <!-- La hora elegida viaja acá; guardar_reserva.php sigue leyendo "hora" igual que antes. -->
                        <input type="hidden" name="hora" id="resv_nueva_hora">
                    </div>

                    <div class="resv_resumen" id="resv_resumen" style="display:none;">
                        <p class="resv_resumen_titulo">Resumen de la reserva</p>
                        <div class="resv_resumen_fila">
                            <span>Barbero</span>
                            <strong id="resumen_barbero">—</strong>
                        </div>
                        <div class="resv_resumen_fila">
                            <span>Servicios</span>
                            <strong id="resumen_servicios">—</strong>
                        </div>
                        <div class="resv_resumen_fila">
                            <span>Duración</span>
                            <strong id="resumen_duracion">—</strong>
                        </div>
                        <div class="resv_resumen_fila">
                            <span>Fecha</span>
                            <strong id="resumen_fecha">—</strong>
                        </div>
                        <div class="resv_resumen_fila">
                            <span>Hora</span>
                            <strong id="resumen_hora">—</strong>
                        </div>
                        <div class="resv_resumen_fila resv_resumen_total">
                            <span>Precio total</span>
                            <strong id="resumen_precio">—</strong>
                        </div>
                    </div>

                    <p>Email de contacto (opcional)</p>
                    <input type="email" name="email" class="resv_input" placeholder="Ej: cliente@correo.com" maxlength="150">

                    <div class="botones_modal_cli">
                        <button type="submit" class="btn_guardar_cli">
                            <img src="../img/tick.png" alt="">
                            Guardar
                        </button>
                        <button type="button" class="btn_cancelar_cli" id="cancelar_reserva">
                            <img src="../img/cruzroja.png" alt="">
                            Cancelar
                        </button>
                    </div>

                </div>
            </form>
        </div>
    </div>

    <!-- ==================== MODAL: EDITAR RESERVA ==================== -->
    <div id="modal_editar_reserva" class="modal">
        <div class="modal-contenido_serv">
            <span class="cerrar_modal" id="cerrar_modal_editar_reserva">&times;</span>

            <div class="categoria_header">
                <div class="title_servicios">
                    <h2>Editar reserva</h2>
                    <p>Modificá los datos de la reserva.</p>
                </div>
            </div>

            <form action="actualizar_reserva.php" method="POST" id="form_editar_reserva">

                <input type="hidden" name="id_reserva" id="editar_resv_id">

                <div id="resv_caja_form">

                    <p>Cliente</p>
                    <select name="id_cliente" id="editar_resv_cliente" class="resv_select" required>
                        <option value="">Seleccioná un cliente</option>
                        <?php foreach ($lista_clientes as $c) { ?>
                            <option value="<?php echo htmlspecialchars((string) $c["id_cliente"]); ?>">
                                <?php echo htmlspecialchars(trim($c["nombre"] . " " . $c["apellido"])); ?>
                            </option>
                        <?php } ?>
                    </select>

                    <p>Barbero</p>
                    <select name="id_barbero" id="editar_resv_barbero" class="resv_select" required>
                        <option value="">Seleccioná un barbero</option>
                        <?php foreach ($lista_barberos as $b) { ?>
                            <option value="<?php echo (int) $b["id_barbero"]; ?>">
                                <?php echo htmlspecialchars(trim($b["nombre"] . " " . $b["apellido"])); ?>
                            </option>
                        <?php } ?>
                    </select>

                    <p>Servicios</p>
                    <div class="resv_servicios_check" id="editar_resv_servicios">
                        <?php foreach ($lista_servicios as $s) { ?>
                            <label class="resv_servicio_item">
                                <input type="checkbox" name="servicios[]" value="<?php echo (int) $s["id_servicio"]; ?>">
                                <span class="resv_servicio_nombre">
                                    <?php echo htmlspecialchars($s["nombre"]); ?>
                                    <?php if (!empty($s["duracion"])) { ?>
                                        <small>(<?php echo htmlspecialchars(formato_duracion_minutos(parsear_duracion_a_minutos($s["duracion"]))); ?>)</small>
                                    <?php } ?>
                                </span>
                                <span class="resv_servicio_precio">$<?php echo htmlspecialchars($s["precio"]); ?></span>
                            </label>
                        <?php } ?>
                    </div>

                    <div class="fila_doble">
                        <div>
                            <p>Fecha</p>
                            <input type="date" name="fecha" id="editar_resv_fecha" class="resv_input" required>
                        </div>
                        <div>
                            <p>Hora</p>
                            <input type="time" name="hora" id="editar_resv_hora" class="resv_input" required>
                        </div>
                    </div>

                    <p>Email de contacto (opcional)</p>
                    <input type="email" name="email" id="editar_resv_email" class="resv_input" maxlength="150">

                    <div class="botones_modal_cli">
                        <button type="submit" class="btn_guardar_cli">
                            <img src="../img/tick.png" alt="">
                            Guardar
                        </button>
                        <button type="button" class="btn_cancelar_cli" id="cancelar_editar_reserva">
                            <img src="../img/cruzroja.png" alt="">
                            Cancelar
                        </button>
                    </div>

                </div>
            </form>
        </div>
    </div>

    <!-- ==================== MODAL: VER RESERVA ==================== -->
    <div id="modal_ver_reserva" class="modal">
        <div class="modal-contenido_ver">
            <span class="cerrar_modal" id="cerrar_modal_ver_reserva">&times;</span>

            <div class="ver_cliente_titulo">
                <h2>Detalle de la reserva</h2>
                <p>Información completa de la reserva seleccionada.</p>
            </div>

            <div id="ver_reserva_body">

                <div class="ver_fila">
                    <span class="ver_label">ID</span>
                    <span class="ver_valor" id="ver_resv_id"></span>
                </div>

                <div class="ver_fila">
                    <span class="ver_label">Cliente</span>
                    <span class="ver_valor" id="ver_resv_cliente"></span>
                </div>

                <div class="ver_fila">
                    <span class="ver_label">Barbero</span>
                    <span class="ver_valor" id="ver_resv_barbero"></span>
                </div>

                <div class="ver_fila">
                    <span class="ver_label">Servicios</span>
                    <span class="ver_valor" id="ver_resv_servicios"></span>
                </div>

                <div class="ver_fila">
                    <span class="ver_label">Total</span>
                    <span class="ver_valor" id="ver_resv_total"></span>
                </div>

                <div class="ver_fila">
                    <span class="ver_label">Fecha</span>
                    <span class="ver_valor" id="ver_resv_fecha"></span>
                </div>

                <div class="ver_fila">
                    <span class="ver_label">Hora</span>
                    <span class="ver_valor" id="ver_resv_hora"></span>
                </div>

                <div class="ver_fila">
                    <span class="ver_label">Estado</span>
                    <span class="ver_valor" id="ver_resv_estado"></span>
                </div>

                <div class="ver_fila">
                    <span class="ver_label">Email</span>
                    <span class="ver_valor" id="ver_resv_email"></span>
                </div>

            </div>

            <div class="ver_cliente_footer">
                <button type="button" class="btn_cancelar_cli" id="cerrar_ver_reserva_btn">Cerrar</button>
            </div>

        </div>
    </div>

    <!-- Datos de reservas (con joins ya resueltos) para la lista y el calendario -->
    <script id="datos_reservas" type="application/json"><?php echo $json_reservas; ?></script>

    <script src="reservas.js"></script>

</body>
</html>
