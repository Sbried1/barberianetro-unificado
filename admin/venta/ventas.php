<?php

require_once __DIR__ . '/../auth/guard.php';
auth_requerir('ventas');

include("../conexion.php");
include("ventas_funciones.php");

// =====================================================
// La tabla "venta" tiene id_barbero, id_cliente (INT, obligatorio),
// id_servicio (uno solo por fila), total (INT), fecha, "servicio" (texto)
// e id_reserva (NULL en una venta directa). No tiene método de pago ni
// monto recibido, por eso esta pantalla no los muestra.
// Elegir una reserva en el formulario envía su id_reserva: la venta queda
// vinculada y la reserva pasa a "completada" (ver guardar_venta.php).
// =====================================================

// =====================================================
// CARGA DE DATOS
// Todas las consultas de lectura van dentro de un try/catch: si la base
// falla, la página igual se dibuja (con datos vacíos) y muestra un aviso,
// en lugar de cortarse con un error fatal que expone detalles técnicos.
// El detalle del error queda en el log del servidor. (El bloque del try no
// se reindentó a propósito para mantener el diff lo más chico posible.)
// =====================================================
$error_carga = false;
$stats_hoy = ["cantidad" => 0, "monto" => 0];
$stats_mes = ["cantidad" => 0, "monto" => 0];
$ticket_promedio = 0;
$servicios_vendidos_mes = 0;
$top_servicios = [];
$lista_clientes = [];
$lista_barberos = [];
$lista_barberos_todos = [];
$lista_servicios = [];
$reservas_disponibles = [];
$ventas_filas = [];
$ventas_por_barbero = [];

try {

// =====================================================
// ESTADISTICAS (tarjetas superiores)
// =====================================================
$sql_hoy = "SELECT COUNT(*) AS cantidad, COALESCE(SUM(total), 0) AS monto FROM venta WHERE fecha = CURDATE()";
$stats_hoy = mysqli_fetch_assoc(mysqli_query($conexion, $sql_hoy));

$sql_mes = "SELECT COUNT(*) AS cantidad, COALESCE(SUM(total), 0) AS monto FROM venta
            WHERE MONTH(fecha) = MONTH(CURDATE()) AND YEAR(fecha) = YEAR(CURDATE())";
$stats_mes = mysqli_fetch_assoc(mysqli_query($conexion, $sql_mes));

$ticket_promedio = $stats_mes["cantidad"] > 0 ? $stats_mes["monto"] / $stats_mes["cantidad"] : 0;

// Cada fila de "venta" ya representa un servicio vendido, así que
// "servicios vendidos" es simplemente la cantidad de filas del mes.
$servicios_vendidos_mes = (int) $stats_mes["cantidad"];

// Servicios más vendidos (mes) — se agrupa por id_servicio real
$sql_top_servicios = "SELECT v.id_servicio, s.nombre, COUNT(*) AS cantidad
                       FROM venta v
                       INNER JOIN servicio s ON s.id_servicio = v.id_servicio
                       WHERE MONTH(v.fecha) = MONTH(CURDATE()) AND YEAR(v.fecha) = YEAR(CURDATE())
                       GROUP BY v.id_servicio, s.nombre
                       ORDER BY cantidad DESC
                       LIMIT 5";
$resultado_top = mysqli_query($conexion, $sql_top_servicios);
$top_servicios = [];
while ($fila = mysqli_fetch_assoc($resultado_top)) {
    $top_servicios[] = ["nombre" => $fila["nombre"], "cantidad" => (int) $fila["cantidad"]];
}

// =====================================================
// LISTAS PARA EL FORMULARIO "REGISTRAR VENTA"
// id_cliente es INT en la base real. cliente NO tiene columna "telefono"
// ni "estado". barbero SÍ tiene "estado" (Disponible / No disponible): el
// formulario de venta nueva ofrece solo los Disponibles, pero el filtro y
// el modal de edición necesitan todos (una venta puede ser de un barbero
// que luego quedó "No disponible").
// =====================================================
$sql_clientes = "SELECT id_cliente, nombre, apellido, email FROM cliente ORDER BY nombre, apellido";
$resultado_clientes = mysqli_query($conexion, $sql_clientes);
$lista_clientes = [];
while ($fila = mysqli_fetch_assoc($resultado_clientes)) {
    $lista_clientes[] = [
        "id_cliente" => (string) $fila["id_cliente"],
        "nombre"     => trim($fila["nombre"] . " " . $fila["apellido"]),
        "email"      => $fila["email"],
    ];
}

$sql_barberos = "SELECT id_barbero, nombre, apellido, estado FROM barbero ORDER BY nombre, apellido";
$resultado_barberos = mysqli_query($conexion, $sql_barberos);
while ($fila = mysqli_fetch_assoc($resultado_barberos)) {
    $lista_barberos_todos[] = $fila;
    if ($fila["estado"] === "Disponible") {
        $lista_barberos[] = $fila;
    }
}

$sql_servicios = "SELECT id_servicio, nombre, precio, duracion FROM servicio ORDER BY nombre";
$resultado_servicios = mysqli_query($conexion, $sql_servicios);
while ($fila = mysqli_fetch_assoc($resultado_servicios)) {
    // servicio.precio es VARCHAR: se valida que sea un entero (venta.total es
    // INT). Si no lo es, "precio" queda en null y "precio_valido" en false, y
    // el formulario no deja agregar ese servicio (ver ventas.js).
    $servicio = venta_armar_servicio($fila);
    $servicio["duracion"] = $fila["duracion"];
    $lista_servicios[] = $servicio;
}

// Reservas que todavía se pueden vender: pendientes o confirmadas y sin
// ninguna venta asociada. Las completadas y canceladas no se ofrecen.
$sql_reservas_disp = "
    SELECT r.id_reserva, r.id_cliente, r.id_barbero, r.fecha, r.hora, r.estado,
           c.nombre AS cliente_nombre, c.apellido AS cliente_apellido,
           b.nombre AS barbero_nombre, b.apellido AS barbero_apellido
    FROM reserva r
    LEFT JOIN cliente c ON c.id_cliente = r.id_cliente
    INNER JOIN barbero b ON b.id_barbero = r.id_barbero
    WHERE r.estado IN ('pendiente', 'confirmada')
      AND NOT EXISTS (SELECT 1 FROM venta v WHERE v.id_reserva = r.id_reserva)
    ORDER BY r.fecha DESC, r.hora DESC
    LIMIT 100
";
$resultado_reservas_disp = mysqli_query($conexion, $sql_reservas_disp);

// servicioreserva reemplaza a detalle_reserva y no guarda precio propio:
// el precio real siempre se lee desde "servicio" (varchar, se valida).
$sql_detalle_disp = "
    SELECT sr.id_reserva, s.id_servicio, s.nombre, s.precio
    FROM servicioreserva sr
    INNER JOIN servicio s ON s.id_servicio = sr.id_servicio
";
$resultado_detalle_disp = mysqli_query($conexion, $sql_detalle_disp);
$detalle_por_reserva_disp = [];
while ($fila = mysqli_fetch_assoc($resultado_detalle_disp)) {
    $detalle_por_reserva_disp[$fila["id_reserva"]][] = venta_armar_servicio($fila);
}

$reservas_disponibles = [];
while ($fila = mysqli_fetch_assoc($resultado_reservas_disp)) {
    $id_reserva = (int) $fila["id_reserva"];
    $servicios_reserva = isset($detalle_por_reserva_disp[$id_reserva]) ? $detalle_por_reserva_disp[$id_reserva] : [];
    $total_reserva = array_sum(array_map(function ($s) { return $s["precio_valido"] ? $s["precio"] : 0; }, $servicios_reserva));

    $reservas_disponibles[] = [
        "id_reserva"  => $id_reserva,
        "id_cliente"  => $fila["id_cliente"] !== null ? (string) $fila["id_cliente"] : "",
        "cliente"     => $fila["id_cliente"] ? trim($fila["cliente_nombre"] . " " . $fila["cliente_apellido"]) : "Cliente eliminado",
        "id_barbero"  => (int) $fila["id_barbero"],
        "barbero"     => trim($fila["barbero_nombre"] . " " . $fila["barbero_apellido"]),
        "fecha"       => $fila["fecha"],
        "hora"        => substr($fila["hora"], 0, 5),
        "estado"      => $fila["estado"],
        "servicios"   => $servicios_reserva,
        "servicios_ids" => array_map(function ($s) { return $s["id_servicio"]; }, $servicios_reserva),
        "servicios_nombres" => implode(", ", array_map(function ($s) { return $s["nombre"]; }, $servicios_reserva)),
        "total"       => $total_reserva,
    ];
}

// =====================================================
// VENTAS (historial). Cada fila es un servicio vendido; las que vienen
// de una reserva comparten el mismo id_reserva.
// =====================================================
$sql_ventas = "
    SELECT v.id_venta, v.id_barbero, v.id_cliente, v.id_servicio, v.total, v.fecha, v.servicio, v.id_reserva,
           c.nombre AS cliente_nombre, c.apellido AS cliente_apellido,
           b.nombre AS barbero_nombre, b.apellido AS barbero_apellido
    FROM venta v
    LEFT JOIN cliente c ON c.id_cliente = v.id_cliente
    INNER JOIN barbero b ON b.id_barbero = v.id_barbero
    ORDER BY v.fecha DESC, v.id_venta DESC
";
$resultado_ventas = mysqli_query($conexion, $sql_ventas);

$ventas_filas = [];
while ($fila = mysqli_fetch_assoc($resultado_ventas)) {
    $ventas_filas[] = [
        "id_venta"    => (int) $fila["id_venta"],
        "id_cliente"  => (string) $fila["id_cliente"],
        "cliente"     => $fila["cliente_nombre"] !== null ? trim($fila["cliente_nombre"] . " " . $fila["cliente_apellido"]) : "Cliente eliminado",
        "id_barbero"  => (int) $fila["id_barbero"],
        "barbero"     => trim($fila["barbero_nombre"] . " " . $fila["barbero_apellido"]),
        "id_servicio" => (int) $fila["id_servicio"],
        "servicio"    => $fila["servicio"],
        "total"       => (int) $fila["total"],
        "fecha"       => $fila["fecha"],
        "id_reserva"  => $fila["id_reserva"] !== null ? (int) $fila["id_reserva"] : null,
    ];
}

// Ventas por barbero (hoy) — reemplaza al panel "por método de pago"
// (esa columna no existe en la tabla real).
$sql_por_barbero = "
    SELECT b.nombre, b.apellido, COALESCE(SUM(v.total), 0) AS monto
    FROM venta v
    INNER JOIN barbero b ON b.id_barbero = v.id_barbero
    WHERE v.fecha = CURDATE()
    GROUP BY v.id_barbero, b.nombre, b.apellido
    ORDER BY monto DESC
";
$resultado_por_barbero = mysqli_query($conexion, $sql_por_barbero);
$ventas_por_barbero = [];
while ($fila = mysqli_fetch_assoc($resultado_por_barbero)) {
    $ventas_por_barbero[] = [
        "nombre" => trim($fila["nombre"] . " " . $fila["apellido"]),
        "monto"  => (float) $fila["monto"],
    ];
}

} catch (Throwable $e) {
    $error_carga = true;
    venta_registrar_error("ventas.php (carga de datos)", $e);
}

$flags_json = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE;
$json_ventas = json_encode($ventas_filas, $flags_json) ?: "[]";
$json_reservas_disponibles = json_encode($reservas_disponibles, $flags_json) ?: "[]";
$json_servicios = json_encode($lista_servicios, $flags_json) ?: "[]";
$json_clientes = json_encode($lista_clientes, $flags_json) ?: "[]";

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../Estilos_index/global.css">
    <link rel="stylesheet" href="ventas_panel.css">
    <title>Barberia - Ventas</title>
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

        <!-- ==================== PANEL PRINCIPAL: VENTAS ==================== -->
        <section class="panel">

            <div class="servicios_header">
                <div class="title_servicios">
                    <h2>Ventas</h2>
                    <p>Registra y gestiona las ventas de la barbería</p>
                </div>
            </div>

            <!-- ALERTAS -->
            <?php if ($error_carga) { ?>
                <div class="vta_alerta error">
                    <p>❌ No se pudieron cargar los datos de ventas. Intentá nuevamente; si el problema sigue, avisá al administrador.</p>
                    <button onclick="this.parentElement.style.display='none'">Aceptar</button>
                </div>
            <?php } ?>

            <?php if (isset($_GET["error"]) && $_GET["error"] == "invalido") { ?>
                <div class="vta_alerta error">
                    <p>❌ Revisá los datos ingresados: falta el barbero, los servicios o algún dato es inválido.</p>
                    <button onclick="this.parentElement.style.display='none'">Aceptar</button>
                </div>
            <?php } ?>

            <?php if (isset($_GET["error"]) && $_GET["error"] == "no_existe") { ?>
                <div class="vta_alerta error">
                    <p>❌ El cliente, barbero, servicio o venta seleccionado no existe.</p>
                    <button onclick="this.parentElement.style.display='none'">Aceptar</button>
                </div>
            <?php } ?>

            <?php if (isset($_GET["error"]) && $_GET["error"] == "cliente_requerido") { ?>
                <div class="vta_alerta error">
                    <p>❌ Toda venta necesita un cliente seleccionado (es un dato obligatorio).</p>
                    <button onclick="this.parentElement.style.display='none'">Aceptar</button>
                </div>
            <?php } ?>

            <?php if (isset($_GET["error"]) && $_GET["error"] == "precio_invalido") { ?>
                <div class="vta_alerta error">
                    <p>❌ Algún servicio seleccionado no tiene un precio válido (debe ser un número entero, sin decimales ni signos). Corregí el precio en Servicios.</p>
                    <button onclick="this.parentElement.style.display='none'">Aceptar</button>
                </div>
            <?php } ?>

            <?php if (isset($_GET["error"]) && $_GET["error"] == "guardar") { ?>
                <div class="vta_alerta error">
                    <p>❌ Ocurrió un error al guardar la venta. Intentá nuevamente.</p>
                    <button onclick="this.parentElement.style.display='none'">Aceptar</button>
                </div>
            <?php } ?>

            <?php if (isset($_GET["error"]) && $_GET["error"] == "reserva_no_disponible") { ?>
                <div class="vta_alerta error">
                    <p>❌ Esa reserva ya fue vendida, completada o cancelada: no se puede registrar otra venta para ella.</p>
                    <button onclick="this.parentElement.style.display='none'">Aceptar</button>
                </div>
            <?php } ?>

            <?php if (isset($_GET["error"]) && $_GET["error"] == "reserva_no_coincide") { ?>
                <div class="vta_alerta error">
                    <p>❌ El cliente, el barbero o los servicios no coinciden con los de la reserva seleccionada. Volvé a elegir la reserva.</p>
                    <button onclick="this.parentElement.style.display='none'">Aceptar</button>
                </div>
            <?php } ?>

            <?php if (isset($_GET["error"]) && $_GET["error"] == "venta_reserva") { ?>
                <div class="vta_alerta error">
                    <p>❌ Esta venta está vinculada a una reserva: el barbero no se puede cambiar.</p>
                    <button onclick="this.parentElement.style.display='none'">Aceptar</button>
                </div>
            <?php } ?>

            <?php if (isset($_GET["error"]) && $_GET["error"] == "restriccion") { ?>
                <div class="vta_alerta error">
                    <p>❌ No se pudo eliminar la venta.</p>
                    <button onclick="this.parentElement.style.display='none'">Aceptar</button>
                </div>
            <?php } ?>

            <?php if (isset($_GET["ok"]) && $_GET["ok"] == "agregado") { ?>
                <div class="vta_alerta ok">
                    <p>✅ Venta registrada correctamente.</p>
                    <button onclick="this.parentElement.style.display='none'">Aceptar</button>
                </div>
            <?php } ?>

            <?php if (isset($_GET["ok"]) && $_GET["ok"] == "actualizado") { ?>
                <div class="vta_alerta ok">
                    <p>✅ Venta actualizada correctamente.</p>
                    <button onclick="this.parentElement.style.display='none'">Aceptar</button>
                </div>
            <?php } ?>

            <?php if (isset($_GET["ok"]) && $_GET["ok"] == "eliminado") { ?>
                <div class="vta_alerta ok">
                    <p>✅ Venta eliminada correctamente.</p>
                    <button onclick="this.parentElement.style.display='none'">Aceptar</button>
                </div>
            <?php } ?>

            <!-- ESTADISTICAS -->
            <div class="vta_stats">
                <div class="vta_stat_card">
                    <div class="vta_stat_icono morado">$</div>
                    <div class="vta_stat_texto">
                        <p>Ventas hoy</p>
                        <span>$<?php echo number_format((float) $stats_hoy["monto"], 0, ",", "."); ?></span>
                        <small><?php echo (int) $stats_hoy["cantidad"]; ?> venta<?php echo $stats_hoy["cantidad"] == 1 ? "" : "s"; ?></small>
                    </div>
                </div>

                <div class="vta_stat_card">
                    <div class="vta_stat_icono verde">↑</div>
                    <div class="vta_stat_texto">
                        <p>Ventas del mes</p>
                        <span>$<?php echo number_format((float) $stats_mes["monto"], 0, ",", "."); ?></span>
                        <small><?php echo (int) $stats_mes["cantidad"]; ?> venta<?php echo $stats_mes["cantidad"] == 1 ? "" : "s"; ?></small>
                    </div>
                </div>

                <div class="vta_stat_card">
                    <div class="vta_stat_icono ambar">≡</div>
                    <div class="vta_stat_texto">
                        <p>Ticket promedio</p>
                        <span>$<?php echo number_format($ticket_promedio, 0, ",", "."); ?></span>
                        <small>del mes actual</small>
                    </div>
                </div>

                <div class="vta_stat_card">
                    <div class="vta_stat_icono azul">🛍</div>
                    <div class="vta_stat_texto">
                        <p>Servicios vendidos</p>
                        <span><?php echo (int) $servicios_vendidos_mes; ?></span>
                        <small>este mes</small>
                    </div>
                </div>
            </div>

            <!-- ==================== DOS TARJETAS INDEPENDIENTES ==================== -->
            <div class="vta_paneles">

                <!-- ---------- TARJETA: REGISTRAR VENTA DE TURNO ---------- -->
                <div class="vta_card vta_card_registrar">

                    <div class="vta_card_header">
                        <h3>Registrar venta</h3>
                        <p>Elegí una reserva pendiente o confirmada, o cargá una venta directa.</p>
                    </div>

                    <form action="guardar_venta.php" method="POST" id="vta_form">

                        <input type="hidden" name="id_cliente" id="vta_id_cliente_hidden" value="">
                        <input type="hidden" name="fecha" id="vta_fecha_hidden" value="<?php echo date('Y-m-d'); ?>">

                        <label class="vta_label" for="vta_selector_reserva">Reserva (opcional)</label>
                        <select name="id_reserva" id="vta_selector_reserva" class="vta_select">
                            <option value="">Venta directa (sin reserva)</option>
                            <?php foreach ($reservas_disponibles as $r) { ?>
                                <option value="<?php echo (int) $r["id_reserva"]; ?>">
                                    #<?php echo (int) $r["id_reserva"]; ?> — <?php echo htmlspecialchars($r["cliente"]); ?> — <?php echo htmlspecialchars(date("d/m/Y", strtotime($r["fecha"]))); ?> <?php echo htmlspecialchars($r["hora"]); ?> (<?php echo htmlspecialchars($r["estado"]); ?>)
                                </option>
                            <?php } ?>
                        </select>
                        <small class="vta_ayuda_texto" id="vta_ayuda_reserva">
                            Sin reserva: la venta se registra como venta directa.
                        </small>

                        <div id="vta_bloque_manual">

                            <label class="vta_label">Cliente <span class="vta_obligatorio">*</span></label>
                            <div class="vta_buscador_cliente">
                                <input type="text" id="vta_buscar_cliente" class="vta_input" placeholder="Buscar cliente por nombre o email..." autocomplete="off">
                                <div id="vta_cliente_resultados" class="vta_autocomplete"></div>
                            </div>
                            <div id="vta_cliente_seleccionado" class="vta_chip_cliente" style="display:none;">
                                <span id="vta_cliente_nombre_chip"></span>
                                <button type="button" id="vta_quitar_cliente">&times;</button>
                            </div>

                            <label class="vta_label" for="vta_select_barbero">Barbero</label>
                            <select name="id_barbero" id="vta_select_barbero" class="vta_select" required>
                                <option value="">Seleccionar barbero...</option>
                                <?php foreach ($lista_barberos as $b) { ?>
                                    <option value="<?php echo (int) $b["id_barbero"]; ?>">
                                        <?php echo htmlspecialchars(trim($b["nombre"] . " " . $b["apellido"])); ?>
                                    </option>
                                <?php } ?>
                            </select>

                            <label class="vta_label">Servicios</label>
                            <input type="text" id="vta_buscar_servicio" class="vta_input" placeholder="Buscar servicio..." autocomplete="off">

                            <div id="vta_servicios_disponibles" class="vta_lista_servicios"></div>

                        </div>

                        <!-- ---------- VENTA ACTUAL (carrito) ---------- -->
                        <div class="vta_resumen">
                            <div class="vta_resumen_header">
                                <h4>Venta actual</h4>
                                <span id="vta_carrito_contador">0 items</span>
                            </div>

                            <div id="vta_carrito_vacio" class="vta_carrito_vacio">
                                <div class="vta_carrito_vacio_icono">🛒</div>
                                <p>Aún no agregaste servicios</p>
                                <small>Seleccioná servicios para comenzar</small>
                            </div>

                            <div id="vta_carrito_lista" class="vta_carrito_lista"></div>

                            <div class="vta_totales">
                                <div class="vta_fila_total">
                                    <span>Subtotal</span>
                                    <span id="vta_subtotal">$0</span>
                                </div>
                                <div class="vta_fila_total vta_fila_total_final">
                                    <span>TOTAL</span>
                                    <span id="vta_total">$0</span>
                                </div>
                            </div>
                            <small class="vta_ayuda_texto">
                                Cada servicio se guarda como una fila de venta; si la venta viene
                                de una reserva, todas las filas quedan vinculadas a ella.
                            </small>
                        </div>

                        <div class="vta_form_botones">
                            <button type="submit" class="vta_btn_confirmar" id="vta_btn_confirmar">
                                <img src="../img/tick.png" alt="">
                                Confirmar venta
                            </button>
                            <button type="button" class="vta_btn_limpiar" id="vta_btn_limpiar">Limpiar</button>
                        </div>

                    </form>
                </div>

                <!-- ---------- TARJETA: VENTAS RECIENTES ---------- -->
                <div class="vta_card vta_card_recientes">

                    <div class="vta_card_header">
                        <h3>Ventas recientes</h3>
                        <p>Historial de ventas registradas.</p>
                    </div>

                    <div class="vta_filtros">
                        <input type="text" id="vta_filtro_buscar" class="vta_input" placeholder="Buscar por cliente, barbero o servicio...">
                        <input type="date" id="vta_filtro_fecha" class="vta_input">
                        <select id="vta_filtro_barbero" class="vta_select">
                            <option value="">Todos los barberos</option>
                            <?php foreach ($lista_barberos_todos as $b) { ?>
                                <option value="<?php echo (int) $b["id_barbero"]; ?>">
                                    <?php echo htmlspecialchars(trim($b["nombre"] . " " . $b["apellido"])); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>

                    <div class="vta_lista_wrap">

                        <div class="vta_cabecera_lista" id="vta_cabecera_lista">
                            <p>Fecha</p>
                            <p>Cliente</p>
                            <p>Servicio</p>
                            <p>Barbero</p>
                            <p>Total</p>
                            <p>Acciones</p>
                        </div>

                        <div id="vta_lista_body"></div>

                        <div class="vta_vacio" id="vta_vacio">
                            <div class="vta_vacio_icono">🧾</div>
                            <p class="vta_vacio_titulo" id="vta_vacio_titulo">No hay ventas registradas</p>
                            <p class="vta_vacio_texto" id="vta_vacio_texto">Registrá tu primera venta desde el formulario de la izquierda.</p>
                        </div>

                        <div class="vta_paginacion" id="vta_paginacion">
                            <p id="vta_pag_texto">Mostrando 0 de 0 ventas</p>
                            <div class="vta_paginacion_botones">
                                <button type="button" id="vta_pag_anterior">Anterior</button>
                                <span id="vta_pag_actual">1</span>
                                <button type="button" id="vta_pag_siguiente">Siguiente</button>
                            </div>
                        </div>

                    </div>

                </div>

            </div>

            <!-- ==================== WIDGETS EXTRA (datos reales) ==================== -->
            <div class="vta_extra_paneles">

                <div class="vta_card vta_card_extra">
                    <div class="vta_card_header">
                        <h3>Ventas por barbero</h3>
                        <p>Hoy</p>
                    </div>
                    <?php if (empty($ventas_por_barbero)) { ?>
                        <p class="vta_extra_vacio">Todavía no hay ventas registradas hoy.</p>
                    <?php } else { ?>
                        <div class="vta_pago_lista">
                            <?php foreach ($ventas_por_barbero as $p) { ?>
                                <div class="vta_pago_item">
                                    <span class="vta_pago_nombre"><?php echo htmlspecialchars($p["nombre"]); ?></span>
                                    <span class="vta_pago_monto">$<?php echo number_format($p["monto"], 0, ",", "."); ?></span>
                                </div>
                            <?php } ?>
                        </div>
                    <?php } ?>
                </div>

                <div class="vta_card vta_card_extra">
                    <div class="vta_card_header">
                        <h3>Servicios más vendidos</h3>
                        <p>Este mes</p>
                    </div>
                    <?php if (empty($top_servicios)) { ?>
                        <p class="vta_extra_vacio">Todavía no hay servicios vendidos este mes.</p>
                    <?php } else { ?>
                        <ol class="vta_top_lista">
                            <?php foreach ($top_servicios as $s) { ?>
                                <li>
                                    <span><?php echo htmlspecialchars($s["nombre"]); ?></span>
                                    <strong><?php echo (int) $s["cantidad"]; ?></strong>
                                </li>
                            <?php } ?>
                        </ol>
                    <?php } ?>
                </div>

            </div>

        </section>

    </div>

    <!-- ==================== MODAL: VER VENTA ==================== -->
    <div id="modal_ver_venta" class="modal">
        <div class="modal-contenido_ver">
            <span class="cerrar_modal" id="cerrar_modal_ver_venta">&times;</span>

            <div class="ver_cliente_titulo">
                <h2>Detalle de la venta</h2>
                <p>Información completa de la venta seleccionada.</p>
            </div>

            <div id="ver_venta_body">
                <div class="ver_fila"><span class="ver_label">ID</span><span class="ver_valor" id="ver_vta_id"></span></div>
                <div class="ver_fila"><span class="ver_label">Cliente</span><span class="ver_valor" id="ver_vta_cliente"></span></div>
                <div class="ver_fila"><span class="ver_label">Barbero</span><span class="ver_valor" id="ver_vta_barbero"></span></div>
                <div class="ver_fila"><span class="ver_label">Servicio</span><span class="ver_valor" id="ver_vta_servicios"></span></div>
                <div class="ver_fila"><span class="ver_label">Total</span><span class="ver_valor" id="ver_vta_total"></span></div>
                <div class="ver_fila"><span class="ver_label">Fecha</span><span class="ver_valor" id="ver_vta_fecha"></span></div>
                <div class="ver_fila"><span class="ver_label">Origen</span><span class="ver_valor" id="ver_vta_origen"></span></div>
            </div>

            <div class="ver_cliente_footer">
                <button type="button" class="btn_cancelar_cli" id="cerrar_ver_venta_btn">Cerrar</button>
            </div>
        </div>
    </div>

    <!-- ==================== MODAL: EDITAR VENTA ==================== -->
    <div id="modal_editar_venta" class="modal">
        <div class="modal-contenido_serv" style="height:auto;">
            <span class="cerrar_modal" id="cerrar_modal_editar_venta">&times;</span>

            <div class="categoria_header">
                <div class="title_servicios">
                    <h2>Editar venta</h2>
                    <p>Solo se puede corregir el barbero y la fecha. El cliente, el servicio y el total no se editan acá: si están mal, eliminá la venta y cargala de nuevo.</p>
                    <p id="editar_vta_aviso_reserva" style="display:none;">Esta venta está vinculada a una reserva: el barbero debe coincidir con el de la reserva.</p>
                </div>
            </div>

            <form action="actualizar_venta.php" method="POST" id="form_editar_venta">
                <input type="hidden" name="id_venta" id="editar_vta_id">

                <div id="vta_editar_caja_form">
                    <label class="vta_label" for="editar_vta_barbero">Barbero</label>
                    <select name="id_barbero" id="editar_vta_barbero" class="vta_select" required>
                        <?php foreach ($lista_barberos_todos as $b) { ?>
                            <option value="<?php echo (int) $b["id_barbero"]; ?>">
                                <?php echo htmlspecialchars(trim($b["nombre"] . " " . $b["apellido"])); ?>
                            </option>
                        <?php } ?>
                    </select>

                    <label class="vta_label" for="editar_vta_fecha">Fecha</label>
                    <input type="date" name="fecha" id="editar_vta_fecha" class="vta_input" required>

                    <div class="botones_modal_cli vta_editar_botones">
                        <button type="submit" class="btn_guardar_cli">
                            <img src="../img/tick.png" alt="">
                            Guardar
                        </button>

                        <button type="button" class="btn_cancelar_cli" id="cancelar_editar_venta">
                            <img src="../img/cruzroja.png" alt="">
                            Cancelar
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Datos reales (JOIN ya resuelto en PHP) para el JS del módulo -->
    <script id="datos_ventas" type="application/json"><?php echo $json_ventas; ?></script>
    <script id="datos_reservas_disponibles" type="application/json"><?php echo $json_reservas_disponibles; ?></script>
    <script id="datos_servicios" type="application/json"><?php echo $json_servicios; ?></script>
    <script id="datos_clientes" type="application/json"><?php echo $json_clientes; ?></script>

    <script src="ventas.js"></script>

</body>
</html>
