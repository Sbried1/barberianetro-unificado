<?php
/**
 * index.php — Dashboard principal de Netro Barbería.
 *
 * Reemplaza a index.html. Todos los datos que se muestran salen de
 * consultas reales contra la base "barberia" (tablas reales: barbero,
 * cliente, especialidad, experiencia, gerente, reserva, servicio,
 * servicioreserva, venta). No hay valores hardcodeados: si una métrica
 * no tiene datos, se muestra en 0 / vacío en vez de inventarse.
 *
 * NOTA DE COMPATIBILIDAD IMPORTANTE:
 * - "reserva.estado" es pendiente/confirmada/completada/cancelada; las
 *   próximas reservas solo muestran pendientes y confirmadas.
 * - "venta" tiene id_reserva (NULL en ventas directas) pero no
 *   metodo_pago ni monto_recibido, así que no hay panel de "método de
 *   pago". Cada fila de "venta" representa un único servicio vendido.
 * - "barbero.estado" es un ENUM 'Disponible' / 'No disponible' (no
 *   'activo'/'inactivo').
 * - "detalle_reserva" no existe: se usa "servicioreserva" (que no
 *   guarda precio propio; el precio siempre se lee de "servicio",
 *   que es varchar, por eso se castea a float).
 */

require_once __DIR__ . '/auth/guard.php';
auth_requerir('dashboard');

include("conexion.php");

// =====================================================
// FECHA ACTUAL (para el subtítulo del header)
// =====================================================
$dias_es  = ["Sunday" => "domingo", "Monday" => "lunes", "Tuesday" => "martes", "Wednesday" => "miércoles", "Thursday" => "jueves", "Friday" => "viernes", "Saturday" => "sábado"];
$meses_es = ["January" => "enero", "February" => "febrero", "March" => "marzo", "April" => "abril", "May" => "mayo", "June" => "junio", "July" => "julio", "August" => "agosto", "September" => "septiembre", "October" => "octubre", "November" => "noviembre", "December" => "diciembre"];
$dia_semana_en = date("l");
$mes_en        = date("F");
$fecha_legible = $dias_es[$dia_semana_en] . ", " . (int) date("j") . " de " . $meses_es[$mes_en] . " de " . date("Y");

// =====================================================
// TARJETAS PRINCIPALES (KPIs)
// =====================================================

// Ventas de hoy: cantidad y monto total
$sql_ventas_hoy = "SELECT COUNT(*) AS cantidad, COALESCE(SUM(total), 0) AS monto FROM venta WHERE fecha = CURDATE()";
$ventas_hoy = mysqli_fetch_assoc(mysqli_query($conexion, $sql_ventas_hoy));

// Ventas del mes: cantidad y monto total
$sql_ventas_mes = "SELECT COUNT(*) AS cantidad, COALESCE(SUM(total), 0) AS monto FROM venta
                    WHERE MONTH(fecha) = MONTH(CURDATE()) AND YEAR(fecha) = YEAR(CURDATE())";
$ventas_mes = mysqli_fetch_assoc(mysqli_query($conexion, $sql_ventas_mes));

// Ticket promedio: total del mes / cantidad de ventas del mes
$ticket_promedio = $ventas_mes["cantidad"] > 0 ? $ventas_mes["monto"] / $ventas_mes["cantidad"] : 0;

// Reservas de hoy
$sql_reservas_hoy = "SELECT COUNT(*) AS cantidad FROM reserva WHERE fecha = CURDATE()";
$reservas_hoy = mysqli_fetch_assoc(mysqli_query($conexion, $sql_reservas_hoy))["cantidad"];

// Reservas próximas (de hoy en adelante) y total histórico — reemplaza
// al desglose por "estado" que ya no existe en la tabla real.
$sql_reservas_proximas_kpi = "SELECT COUNT(*) AS cantidad FROM reserva WHERE fecha >= CURDATE() AND estado IN ('pendiente', 'confirmada')";
$reservas_proximas_kpi = mysqli_fetch_assoc(mysqli_query($conexion, $sql_reservas_proximas_kpi))["cantidad"];

$sql_total_reservas = "SELECT COUNT(*) AS cantidad FROM reserva";
$total_reservas_registradas = mysqli_fetch_assoc(mysqli_query($conexion, $sql_total_reservas))["cantidad"];

// Reservas por barbero (para el panel inferior, reemplaza al panel de
// "estado de reservas" que dependía de una columna inexistente).
$sql_reservas_por_barbero = "
    SELECT b.nombre, b.apellido, COUNT(*) AS cantidad
    FROM reserva r
    INNER JOIN barbero b ON b.id_barbero = r.id_barbero
    GROUP BY r.id_barbero, b.nombre, b.apellido
    ORDER BY cantidad DESC
    LIMIT 6
";
$resultado_reservas_por_barbero = mysqli_query($conexion, $sql_reservas_por_barbero);
$reservas_por_barbero = [];
$max_reservas_barbero = 1;
while ($fila = mysqli_fetch_assoc($resultado_reservas_por_barbero)) {
    $cantidad = (int) $fila["cantidad"];
    $reservas_por_barbero[] = ["nombre" => trim($fila["nombre"] . " " . $fila["apellido"]), "cantidad" => $cantidad];
    if ($cantidad > $max_reservas_barbero) {
        $max_reservas_barbero = $cantidad;
    }
}

// Clientes registrados (total)
$sql_clientes = "SELECT COUNT(*) AS cantidad FROM cliente";
$clientes_registrados = mysqli_fetch_assoc(mysqli_query($conexion, $sql_clientes))["cantidad"];

// Barberos disponibles / total (ENUM real: 'Disponible' / 'No disponible')
$sql_barberos = "SELECT COUNT(*) AS total, SUM(CASE WHEN estado = 'Disponible' THEN 1 ELSE 0 END) AS disponibles FROM barbero";
$barberos_stats = mysqli_fetch_assoc(mysqli_query($conexion, $sql_barberos));
$barberos_total       = (int) $barberos_stats["total"];
$barberos_disponibles = (int) $barberos_stats["disponibles"];

// Servicios vendidos durante el mes: cada fila de "venta" ya es un
// servicio vendido, así que es simplemente la cantidad de ventas del mes.
$servicios_vendidos_mes = (int) $ventas_mes["cantidad"];

// =====================================================
// RESUMEN DE VENTAS — últimos 7 días (para el gráfico)
// =====================================================
$sql_resumen = "SELECT fecha, COUNT(*) AS cantidad, COALESCE(SUM(total), 0) AS monto
                 FROM venta
                 WHERE fecha >= CURDATE() - INTERVAL 6 DAY
                 GROUP BY fecha";
$resultado_resumen = mysqli_query($conexion, $sql_resumen);
$montos_por_fecha = [];
while ($fila = mysqli_fetch_assoc($resultado_resumen)) {
    $montos_por_fecha[$fila["fecha"]] = ["cantidad" => (int) $fila["cantidad"], "monto" => (float) $fila["monto"]];
}

// Se completan los 7 días aunque no haya ventas ese día (se muestran en 0, no se omiten).
$dias_abrev_es = ["Sun" => "Dom", "Mon" => "Lun", "Tue" => "Mar", "Wed" => "Mié", "Thu" => "Jue", "Fri" => "Vie", "Sat" => "Sáb"];
$resumen_7_dias = [];
for ($i = 6; $i >= 0; $i--) {
    $fecha_iter = date("Y-m-d", strtotime("-$i day"));
    $abrev_en   = date("D", strtotime($fecha_iter));
    $resumen_7_dias[] = [
        "fecha"     => $fecha_iter,
        "etiqueta"  => $dias_abrev_es[$abrev_en] . " " . date("d/m", strtotime($fecha_iter)),
        "cantidad"  => isset($montos_por_fecha[$fecha_iter]) ? $montos_por_fecha[$fecha_iter]["cantidad"] : 0,
        "monto"     => isset($montos_por_fecha[$fecha_iter]) ? $montos_por_fecha[$fecha_iter]["monto"] : 0.0,
    ];
}
$total_periodo_monto    = array_sum(array_column($resumen_7_dias, "monto"));
$total_periodo_cantidad = array_sum(array_column($resumen_7_dias, "cantidad"));
$monto_maximo_periodo   = max(1, max(array_column($resumen_7_dias, "monto")));

// =====================================================
// PROXIMAS RESERVAS (hoy en adelante)
// =====================================================
$sql_proximas = "
    SELECT r.id_reserva, r.fecha, r.hora, r.id_cliente,
           c.nombre AS cliente_nombre, c.apellido AS cliente_apellido,
           b.nombre AS barbero_nombre, b.apellido AS barbero_apellido
    FROM reserva r
    LEFT JOIN cliente c ON c.id_cliente = r.id_cliente
    INNER JOIN barbero b ON b.id_barbero = r.id_barbero
    WHERE r.fecha >= CURDATE() AND r.estado IN ('pendiente', 'confirmada')
    ORDER BY r.fecha ASC, r.hora ASC
    LIMIT 8
";
$resultado_proximas = mysqli_query($conexion, $sql_proximas);
$filas_proximas = [];
$ids_reservas_proximas = [];
while ($fila = mysqli_fetch_assoc($resultado_proximas)) {
    $filas_proximas[] = $fila;
    $ids_reservas_proximas[] = (int) $fila["id_reserva"];
}

// Servicios de esas reservas (para mostrar "Corte + Barba" en el panel).
// servicioreserva reemplaza a detalle_reserva y no guarda precio propio.
$servicios_por_reserva = [];
if (!empty($ids_reservas_proximas)) {
    $placeholders = implode(",", array_fill(0, count($ids_reservas_proximas), "?"));
    $tipos = str_repeat("i", count($ids_reservas_proximas));
    $sql_detalle_prox = "SELECT sr.id_reserva, s.nombre
                          FROM servicioreserva sr
                          INNER JOIN servicio s ON s.id_servicio = sr.id_servicio
                          WHERE sr.id_reserva IN ($placeholders)";
    $consulta_detalle_prox = mysqli_prepare($conexion, $sql_detalle_prox);
    mysqli_stmt_bind_param($consulta_detalle_prox, $tipos, ...$ids_reservas_proximas);
    mysqli_stmt_execute($consulta_detalle_prox);
    $resultado_detalle_prox = mysqli_stmt_get_result($consulta_detalle_prox);
    while ($fila = mysqli_fetch_assoc($resultado_detalle_prox)) {
        $servicios_por_reserva[$fila["id_reserva"]][] = $fila["nombre"];
    }
    mysqli_stmt_close($consulta_detalle_prox);
}

$proximas_reservas = [];
foreach ($filas_proximas as $fila) {
    $id_reserva = (int) $fila["id_reserva"];

    // Chip de "cuándo es" (hoy / mañana / fecha) en vez del badge de
    // estado que ya no existe.
    if ($fila["fecha"] === date("Y-m-d")) {
        $cuando = "Hoy";
    } elseif ($fila["fecha"] === date("Y-m-d", strtotime("+1 day"))) {
        $cuando = "Mañana";
    } else {
        $cuando = date("d/m", strtotime($fila["fecha"]));
    }

    $proximas_reservas[] = [
        "hora"      => substr($fila["hora"], 0, 5),
        "fecha"     => $fila["fecha"],
        "cuando"    => $cuando,
        "cliente"   => $fila["id_cliente"] ? trim($fila["cliente_nombre"] . " " . $fila["cliente_apellido"]) : "Cliente eliminado",
        "barbero"   => trim($fila["barbero_nombre"] . " " . $fila["barbero_apellido"]),
        "servicios" => isset($servicios_por_reserva[$id_reserva]) ? implode(" + ", $servicios_por_reserva[$id_reserva]) : "—",
    ];
}

// =====================================================
// VENTAS RECIENTES
// venta no tiene hora: solo se muestra la fecha.
// =====================================================
$sql_ventas_recientes = "
    SELECT v.id_venta, v.fecha, v.total, v.servicio,
           c.nombre AS cliente_nombre, c.apellido AS cliente_apellido,
           b.nombre AS barbero_nombre, b.apellido AS barbero_apellido
    FROM venta v
    LEFT JOIN cliente c ON c.id_cliente = v.id_cliente
    INNER JOIN barbero b ON b.id_barbero = v.id_barbero
    ORDER BY v.fecha DESC, v.id_venta DESC
    LIMIT 8
";
$resultado_ventas_recientes = mysqli_query($conexion, $sql_ventas_recientes);
$ventas_recientes = [];
while ($fila = mysqli_fetch_assoc($resultado_ventas_recientes)) {
    $ventas_recientes[] = [
        "id_venta" => (int) $fila["id_venta"],
        "fecha"    => $fila["fecha"],
        "cliente"  => $fila["cliente_nombre"] !== null ? trim($fila["cliente_nombre"] . " " . $fila["cliente_apellido"]) : "Cliente eliminado",
        "barbero"  => trim($fila["barbero_nombre"] . " " . $fila["barbero_apellido"]),
        "servicio" => $fila["servicio"],
        "total"    => (float) $fila["total"],
    ];
}

// =====================================================
// SERVICIOS MAS VENDIDOS (este mes)
// Cada fila de "venta" ya es un servicio vendido: se agrupa directo
// por id_servicio, sin pasar por reserva/detalle_reserva.
// =====================================================
$sql_top_servicios = "SELECT v.id_servicio, s.nombre, COUNT(*) AS cantidad
                       FROM venta v
                       INNER JOIN servicio s ON s.id_servicio = v.id_servicio
                       WHERE MONTH(v.fecha) = MONTH(CURDATE()) AND YEAR(v.fecha) = YEAR(CURDATE())
                       GROUP BY v.id_servicio, s.nombre
                       ORDER BY cantidad DESC
                       LIMIT 5";
$resultado_top_servicios = mysqli_query($conexion, $sql_top_servicios);
$top_servicios = [];
while ($fila = mysqli_fetch_assoc($resultado_top_servicios)) {
    $top_servicios[] = ["nombre" => $fila["nombre"], "cantidad" => (int) $fila["cantidad"]];
}
$max_top_servicio = 1;
foreach ($top_servicios as $s) {
    if ($s["cantidad"] > $max_top_servicio) {
        $max_top_servicio = $s["cantidad"];
    }
}

function formato_dinero($valor) {
    return "$" . number_format((float) $valor, 0, ",", ".");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="Estilos_index/global.css">
    <link rel="stylesheet" href="dashboard.css">
    <title>Barberia - Dashboard</title>
</head>
<body>

    <header>
        <div class="nav">
            <div class="logo">
                <img src="img/ChatGPT Image 15 jun 2026, 13_41_39.png" alt="">
                <h1 id="Netro">Netro</h1>
            </div>
        </div>
    </header>

    <div class="container">

        <section class="sidebar">
            <div class="menu-header">
                <img src="img/ChatGPT Image 15 jun 2026, 13_41_39.png" alt="Logo">
                <h2>MENÚ</h2>
            </div>
<?php
$pagina_actual = basename($_SERVER['PHP_SELF']);
?>
            <nav class="menu-nav">
                <ul>

                    <li>
                        <a href="index.php"
                        <?= $pagina_actual === 'index.php' ? 'aria-current="page"' : '' ?>>
                            Dashboard
                        </a>
                    </li>

                    <li>
                        <a href="servicios_categoria/servicios.php"
                        <?= $pagina_actual === 'servicios.php' ? 'aria-current="page"' : '' ?>>
                            Servicios
                        </a>
                    </li>

                    <li>
                        <a href="reservas/reservas.php"
                        <?= $pagina_actual === 'reservas.php' ? 'aria-current="page"' : '' ?>>
                            Reservas
                        </a>
                    </li>

                    <li>
                        <a href="cliente/clientes.php"
                        <?= $pagina_actual === 'clientes.php' ? 'aria-current="page"' : '' ?>>
                            Clientes
                        </a>
                    </li>

                    <li>
                        <a href="barberos/barberos.php"
                        <?= $pagina_actual === 'barberos.php' ? 'aria-current="page"' : '' ?>>
                            Barberos
                        </a>
                    </li>

                    <li>
                        <a href="venta/ventas.php"
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

        <!-- ==================== PANEL PRINCIPAL: DASHBOARD ==================== -->
        <section class="panel">

            <div class="servicios_header dash_top_header">
                <div class="title_servicios">
                    <h2>Dashboard</h2>
                    <p>Resumen general de la barbería · <?php echo htmlspecialchars(ucfirst($fecha_legible)); ?></p>
                </div>
                <div class="dash_accesos">
                    <a href="reservas/reservas.php" class="dash_btn_accion">+ Nueva reserva</a>
                    <a href="venta/ventas.php" class="dash_btn_accion dash_btn_accion_primario">+ Nueva venta</a>
                </div>
            </div>

            <!-- ==================== KPIs ==================== -->
            <div class="dash_stats">

                <div class="dash_stat_card">
                    <div class="dash_stat_icono morado">$</div>
                    <div class="dash_stat_texto">
                        <p>Ventas hoy</p>
                        <span><?php echo formato_dinero($ventas_hoy["monto"]); ?></span>
                        <small><?php echo (int) $ventas_hoy["cantidad"]; ?> venta<?php echo $ventas_hoy["cantidad"] == 1 ? "" : "s"; ?></small>
                    </div>
                </div>

                <div class="dash_stat_card">
                    <div class="dash_stat_icono verde">↑</div>
                    <div class="dash_stat_texto">
                        <p>Ventas del mes</p>
                        <span><?php echo formato_dinero($ventas_mes["monto"]); ?></span>
                        <small><?php echo (int) $ventas_mes["cantidad"]; ?> venta<?php echo $ventas_mes["cantidad"] == 1 ? "" : "s"; ?></small>
                    </div>
                </div>

                <div class="dash_stat_card">
                    <div class="dash_stat_icono ambar">≡</div>
                    <div class="dash_stat_texto">
                        <p>Ticket promedio</p>
                        <span><?php echo formato_dinero($ticket_promedio); ?></span>
                        <small>del mes actual</small>
                    </div>
                </div>

                <div class="dash_stat_card">
                    <div class="dash_stat_icono azul">✂</div>
                    <div class="dash_stat_texto">
                        <p>Servicios vendidos</p>
                        <span><?php echo (int) $servicios_vendidos_mes; ?></span>
                        <small>este mes</small>
                    </div>
                </div>

                <div class="dash_stat_card">
                    <div class="dash_stat_icono morado">📅</div>
                    <div class="dash_stat_texto">
                        <p>Reservas hoy</p>
                        <span><?php echo (int) $reservas_hoy; ?></span>
                        <small>turnos agendados</small>
                    </div>
                </div>

                <div class="dash_stat_card">
                    <div class="dash_stat_icono ambar">!</div>
                    <div class="dash_stat_texto">
                        <p>Reservas próximas</p>
                        <span><?php echo (int) $reservas_proximas_kpi; ?></span>
                        <small>de hoy en adelante</small>
                    </div>
                </div>

                <div class="dash_stat_card">
                    <div class="dash_stat_icono azul">👥</div>
                    <div class="dash_stat_texto">
                        <p>Clientes registrados</p>
                        <span><?php echo (int) $clientes_registrados; ?></span>
                        <small>en el sistema</small>
                    </div>
                </div>

                <div class="dash_stat_card">
                    <div class="dash_stat_icono verde">✔</div>
                    <div class="dash_stat_texto">
                        <p>Barberos disponibles</p>
                        <span><?php echo (int) $barberos_disponibles; ?> de <?php echo (int) $barberos_total; ?></span>
                        <small>disponibles ahora</small>
                    </div>
                </div>

            </div>

            <!-- ==================== RESUMEN DE VENTAS ==================== -->
            <div class="dash_card dash_resumen">
                <div class="dash_card_header">
                    <div>
                        <h3>Resumen de ventas</h3>
                        <p>Últimos 7 días</p>
                    </div>
                    <div class="dash_resumen_totales">
                        <div>
                            <span class="dash_resumen_valor"><?php echo formato_dinero($total_periodo_monto); ?></span>
                            <small>total vendido</small>
                        </div>
                        <div>
                            <span class="dash_resumen_valor"><?php echo (int) $total_periodo_cantidad; ?></span>
                            <small>ventas</small>
                        </div>
                    </div>
                </div>

                <?php if ($total_periodo_cantidad == 0) { ?>
                    <p class="dash_vacio">Todavía no hay ventas registradas en los últimos 7 días.</p>
                <?php } else { ?>
                    <div class="dash_grafico">
                        <?php foreach ($resumen_7_dias as $dia) {
                            $altura_pct = max(4, round(($dia["monto"] / $monto_maximo_periodo) * 100));
                        ?>
                            <div class="dash_barra_col" title="<?php echo htmlspecialchars($dia['etiqueta'] . ': ' . formato_dinero($dia['monto'])); ?>">
                                <div class="dash_barra" style="height: <?php echo (int) $altura_pct; ?>%;"></div>
                                <span class="dash_barra_label"><?php echo htmlspecialchars($dia["etiqueta"]); ?></span>
                            </div>
                        <?php } ?>
                    </div>
                <?php } ?>
            </div>

            <!-- ==================== RESERVAS Y VENTAS ==================== -->
            <div class="dash_dos_columnas">

                <div class="dash_card">
                    <div class="dash_card_header">
                        <div>
                            <h3>Próximas reservas</h3>
                            <p>Desde hoy en adelante</p>
                        </div>
                        <a href="reservas/reservas.php" class="dash_ver_todas">Ver todas</a>
                    </div>

                    <?php if (empty($proximas_reservas)) { ?>
                        <p class="dash_vacio">No hay próximas reservas cargadas.</p>
                    <?php } else { ?>
                        <div class="dash_lista">
                            <?php foreach ($proximas_reservas as $r) { ?>
                                <div class="dash_fila_reserva">
                                    <div class="dash_fila_hora">
                                        <strong><?php echo htmlspecialchars($r["hora"]); ?></strong>
                                        <small><?php echo htmlspecialchars(date("d/m", strtotime($r["fecha"]))); ?></small>
                                    </div>
                                    <div class="dash_fila_info">
                                        <p class="dash_fila_titulo"><?php echo htmlspecialchars($r["cliente"]); ?></p>
                                        <p class="dash_fila_sub"><?php echo htmlspecialchars($r["servicios"]); ?> · <?php echo htmlspecialchars($r["barbero"]); ?></p>
                                    </div>
                                    <span class="dash_badge dash_badge_cuando"><?php echo htmlspecialchars($r["cuando"]); ?></span>
                                </div>
                            <?php } ?>
                        </div>
                    <?php } ?>
                </div>

                <div class="dash_card">
                    <div class="dash_card_header">
                        <div>
                            <h3>Ventas recientes</h3>
                            <p>Últimas ventas registradas</p>
                        </div>
                        <a href="venta/ventas.php" class="dash_ver_todas">Ver todas</a>
                    </div>

                    <?php if (empty($ventas_recientes)) { ?>
                        <p class="dash_vacio">Todavía no hay ventas registradas.</p>
                    <?php } else { ?>
                        <div class="dash_lista">
                            <?php foreach ($ventas_recientes as $v) { ?>
                                <div class="dash_fila_venta">
                                    <div class="dash_fila_hora">
                                        <strong>#<?php echo str_pad($v["id_venta"], 6, "0", STR_PAD_LEFT); ?></strong>
                                        <small><?php echo htmlspecialchars(date("d/m", strtotime($v["fecha"]))); ?></small>
                                    </div>
                                    <div class="dash_fila_info">
                                        <p class="dash_fila_titulo"><?php echo htmlspecialchars($v["cliente"]); ?></p>
                                        <p class="dash_fila_sub"><?php echo htmlspecialchars($v["barbero"]); ?> · <?php echo htmlspecialchars($v["servicio"]); ?></p>
                                    </div>
                                    <span class="dash_fila_total"><?php echo formato_dinero($v["total"]); ?></span>
                                </div>
                            <?php } ?>
                        </div>
                    <?php } ?>
                </div>

            </div>

            <!-- ==================== SERVICIOS Y ESTADO DE RESERVAS ==================== -->
            <div class="dash_dos_columnas">

                <div class="dash_card">
                    <div class="dash_card_header">
                        <div>
                            <h3>Servicios más vendidos</h3>
                            <p>Este mes</p>
                        </div>
                        <a href="servicios_categoria/servicios.php" class="dash_ver_todas">Ver servicios</a>
                    </div>

                    <?php if (empty($top_servicios)) { ?>
                        <p class="dash_vacio">Todavía no hay servicios vendidos este mes.</p>
                    <?php } else { ?>
                        <div class="dash_ranking">
                            <?php $pos = 1; foreach ($top_servicios as $s) {
                                $ancho_pct = max(6, round(($s["cantidad"] / $max_top_servicio) * 100));
                            ?>
                                <div class="dash_ranking_fila">
                                    <span class="dash_ranking_pos"><?php echo $pos; ?></span>
                                    <div class="dash_ranking_barra_wrap">
                                        <p class="dash_ranking_nombre"><?php echo htmlspecialchars($s["nombre"]); ?></p>
                                        <div class="dash_ranking_barra_fondo">
                                            <div class="dash_ranking_barra" style="width: <?php echo (int) $ancho_pct; ?>%;"></div>
                                        </div>
                                    </div>
                                    <span class="dash_ranking_valor"><?php echo (int) $s["cantidad"]; ?></span>
                                </div>
                            <?php $pos++; } ?>
                        </div>
                    <?php } ?>
                </div>

                <div class="dash_card">
                    <div class="dash_card_header">
                        <div>
                            <h3>Reservas por barbero</h3>
                            <p><?php echo (int) $total_reservas_registradas; ?> reservas en total</p>
                        </div>
                    </div>

                    <?php if (empty($reservas_por_barbero)) { ?>
                        <p class="dash_vacio">Todavía no hay reservas cargadas.</p>
                    <?php } else { ?>
                        <div class="dash_ranking">
                            <?php foreach ($reservas_por_barbero as $rb) {
                                $ancho_pct = max(6, round(($rb["cantidad"] / $max_reservas_barbero) * 100));
                            ?>
                                <div class="dash_ranking_fila">
                                    <div class="dash_ranking_barra_wrap">
                                        <p class="dash_ranking_nombre"><?php echo htmlspecialchars($rb["nombre"]); ?></p>
                                        <div class="dash_ranking_barra_fondo">
                                            <div class="dash_ranking_barra" style="width: <?php echo (int) $ancho_pct; ?>%;"></div>
                                        </div>
                                    </div>
                                    <span class="dash_ranking_valor"><?php echo (int) $rb["cantidad"]; ?></span>
                                </div>
                            <?php } ?>
                        </div>
                    <?php } ?>
                </div>

            </div>

        </section>

    </div>

</body>
</html>
