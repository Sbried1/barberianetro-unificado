<?php
/**
 * reservas_funciones.php
 * Funciones auxiliares compartidas por guardar_reserva.php y
 * actualizar_reserva.php. Solo validaciones y consultas relacionadas
 * al conflicto de horarios. No maneja salida HTML.
 *
 * ESTADOS: reserva.estado es pendiente, confirmada, completada o
 * cancelada. Una reserva cancelada libera su horario; las demás lo ocupan.
 */

// Valida que una fecha tenga formato real (YYYY-MM-DD) y sea una fecha válida.
function validar_fecha_reserva($fecha) {
    if (!is_string($fecha) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})\z/', $fecha, $partes)) {
        return false;
    }
    return checkdate((int) $partes[2], (int) $partes[3], (int) $partes[1]);
}

// Valida que una hora tenga formato HH:MM o HH:MM:SS válido.
function validar_hora_reserva($hora) {
    if (is_string($hora) && preg_match('/^([01]\d|2[0-3]):([0-5]\d)(:([0-5]\d))?\z/', $hora)) {
        return true;
    }
    return false;
}

// Verifica que el barbero o el servicio realmente existan en la base de
// datos antes de guardar nada. El cliente se valida con existe_cliente_id().
function ids_existen($conexion, $tabla, $columna_id, $id) {
    $tablas_permitidas = ["barbero" => "id_barbero", "servicio" => "id_servicio"];
    if (!isset($tablas_permitidas[$tabla]) || $tablas_permitidas[$tabla] !== $columna_id) {
        return false;
    }
    $sql = "SELECT $columna_id FROM $tabla WHERE $columna_id = ? LIMIT 1";
    $consulta = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($consulta, "i", $id);
    mysqli_stmt_execute($consulta);
    $resultado = mysqli_stmt_get_result($consulta);
    $existe = mysqli_num_rows($resultado) > 0;
    mysqli_stmt_close($consulta);
    return $existe;
}

// Verifica que el cliente exista (cliente.id_cliente es INT).
function existe_cliente_id($conexion, $id_cliente) {
    $sql = "SELECT id_cliente FROM cliente WHERE id_cliente = ? LIMIT 1";
    $consulta = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($consulta, "i", $id_cliente);
    mysqli_stmt_execute($consulta);
    $resultado = mysqli_stmt_get_result($consulta);
    $existe = mysqli_num_rows($resultado) > 0;
    mysqli_stmt_close($consulta);
    return $existe;
}


// =====================================================================
// HORARIOS DISPONIBLES (selector visual del modal "Nueva reserva")
//
// "servicio.duracion" es un VARCHAR libre en la base real (ej: "30 min",
// "1h 30min", "45 min"), no un entero. No se agrega ninguna columna
// nueva: en cambio se interpreta ese texto para poder calcular la
// disponibilidad real a partir de las reservas existentes.
//
// El horario comercial (apertura/cierre/intervalo entre turnos) no
// existe como tabla de configuración en la base real, así que se define
// acá como una regla de negocio simple y editable a mano si hiciera
// falta ajustarla más adelante.
// =====================================================================
define("RESV_HORA_APERTURA", "09:00");
define("RESV_HORA_CIERRE", "20:00");
define("RESV_INTERVALO_MINUTOS", 30);
// Día de la semana en que la barbería está cerrada (date("N"): 7 = domingo).
define("RESV_DIA_CERRADO", 7);
// Duración de respaldo si un servicio tiene "duracion" vacía o con un
// texto que no se puede interpretar como tiempo (nunca debería pasar
// si se carga bien desde el módulo de Servicios, pero evita romper el
// cálculo de horarios si sucediera).
define("RESV_DURACION_MINIMA_MINUTOS", 30);

// Interpreta el texto libre de "servicio.duracion" y devuelve minutos
// enteros. Entiende formatos como: "30 min", "45 minutos", "1 hora",
// "1h", "1h 30min", "1:30", "90". Si no logra interpretar nada, usa
// RESV_DURACION_MINIMA_MINUTOS como resguardo.
function parsear_duracion_a_minutos($texto) {

    $texto = trim(mb_strtolower((string) $texto));

    if ($texto === "") {
        return RESV_DURACION_MINIMA_MINUTOS;
    }

    // Formato "H:MM" (ej: "1:30")
    if (preg_match('/^(\d{1,2}):([0-5]\d)$/', $texto, $m)) {
        $minutos = ((int) $m[1] * 60) + (int) $m[2];
        return $minutos > 0 ? $minutos : RESV_DURACION_MINIMA_MINUTOS;
    }

    $minutos = 0;
    $encontro_algo = false;

    // Horas: "1h", "1 h", "1hs", "1 hora", "2 horas"
    if (preg_match('/(\d+(?:[.,]\d+)?)\s*(?:h|hs|hr|hrs|hora|horas)\b/u', $texto, $m)) {
        $minutos += (float) str_replace(",", ".", $m[1]) * 60;
        $encontro_algo = true;
    }

    // Minutos: "30m", "30 min", "30 minutos"
    if (preg_match('/(\d+(?:[.,]\d+)?)\s*(?:m|min|mins|minuto|minutos)\b/u', $texto, $m)) {
        $minutos += (float) str_replace(",", ".", $m[1]);
        $encontro_algo = true;
    }

    // Si no matcheó ni "h" ni "min" pero hay un número suelto (ej: "30"),
    // se asume que ese número ya está en minutos.
    if (!$encontro_algo && preg_match('/(\d+(?:[.,]\d+)?)/', $texto, $m)) {
        $minutos = (float) str_replace(",", ".", $m[1]);
        $encontro_algo = true;
    }

    $minutos = (int) round($minutos);

    return $minutos > 0 ? $minutos : RESV_DURACION_MINIMA_MINUTOS;
}

// Suma la duración real (en minutos) de un conjunto de id_servicio,
// leyendo servicio.duracion desde la base y usando parsear_duracion_a_minutos().
function duracion_total_servicios_ids($conexion, array $ids_servicios) {

    $ids_servicios = array_values(array_unique(array_filter(array_map("intval", $ids_servicios))));

    if (empty($ids_servicios)) {
        return 0;
    }

    $placeholders = implode(",", array_fill(0, count($ids_servicios), "?"));
    $tipos = str_repeat("i", count($ids_servicios));

    $sql = "SELECT duracion FROM servicio WHERE id_servicio IN ($placeholders)";
    $consulta = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($consulta, $tipos, ...$ids_servicios);
    mysqli_stmt_execute($consulta);
    $resultado = mysqli_stmt_get_result($consulta);

    $total = 0;
    while ($fila = mysqli_fetch_assoc($resultado)) {
        $total += parsear_duracion_a_minutos($fila["duracion"]);
    }
    mysqli_stmt_close($consulta);

    return $total > 0 ? $total : RESV_DURACION_MINIMA_MINUTOS;
}

// Devuelve los intervalos [inicio_minutos, fin_minutos] (minutos desde
// las 00:00) que ya están ocupados para un barbero en una fecha dada,
// calculados con la duración REAL de los servicios de cada reserva
// existente (vía servicioreserva). Permite excluir una reserva puntual
// (para editar una reserva sin chocar con sí misma).
function obtener_intervalos_ocupados($conexion, $id_barbero, $fecha, $id_reserva_excluir = null) {

    if ($id_reserva_excluir !== null) {
        $sql = "SELECT id_reserva, hora FROM reserva WHERE id_barbero = ? AND fecha = ? AND id_reserva <> ? AND estado <> 'cancelada'";
        $consulta = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($consulta, "isi", $id_barbero, $fecha, $id_reserva_excluir);
    } else {
        $sql = "SELECT id_reserva, hora FROM reserva WHERE id_barbero = ? AND fecha = ? AND estado <> 'cancelada'";
        $consulta = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($consulta, "is", $id_barbero, $fecha);
    }

    mysqli_stmt_execute($consulta);
    $resultado = mysqli_stmt_get_result($consulta);

    $reservas_dia = [];
    while ($fila = mysqli_fetch_assoc($resultado)) {
        $reservas_dia[] = ["id_reserva" => (int) $fila["id_reserva"], "hora" => substr($fila["hora"], 0, 5)];
    }
    mysqli_stmt_close($consulta);

    if (empty($reservas_dia)) {
        return [];
    }

    // Duración real de cada una de esas reservas, según sus propios
    // servicios asociados (una reserva puede tener varios). DISTINCT: si
    // un servicio quedó repetido en servicioreserva, se cuenta una vez.
    $ids_reserva = array_map(function ($r) { return $r["id_reserva"]; }, $reservas_dia);
    $placeholders = implode(",", array_fill(0, count($ids_reserva), "?"));
    $tipos = str_repeat("i", count($ids_reserva));

    $sql_dur = "SELECT DISTINCT sr.id_reserva, sr.id_servicio, s.duracion
                FROM servicioreserva sr
                INNER JOIN servicio s ON s.id_servicio = sr.id_servicio
                WHERE sr.id_reserva IN ($placeholders)";
    $consulta_dur = mysqli_prepare($conexion, $sql_dur);
    mysqli_stmt_bind_param($consulta_dur, $tipos, ...$ids_reserva);
    mysqli_stmt_execute($consulta_dur);
    $resultado_dur = mysqli_stmt_get_result($consulta_dur);

    $duracion_por_reserva = [];
    while ($fila = mysqli_fetch_assoc($resultado_dur)) {
        $id_r = (int) $fila["id_reserva"];
        $duracion_por_reserva[$id_r] = ($duracion_por_reserva[$id_r] ?? 0) + parsear_duracion_a_minutos($fila["duracion"]);
    }
    mysqli_stmt_close($consulta_dur);

    $intervalos = [];
    foreach ($reservas_dia as $r) {
        $partes = explode(":", $r["hora"]);
        $inicio = ((int) $partes[0] * 60) + (int) $partes[1];
        $duracion = isset($duracion_por_reserva[$r["id_reserva"]]) ? $duracion_por_reserva[$r["id_reserva"]] : RESV_DURACION_MINIMA_MINUTOS;
        $intervalos[] = [$inicio, $inicio + $duracion, "id_reserva" => $r["id_reserva"]];
    }

    return $intervalos;
}

// Bloquea la agenda de un barbero hasta el fin de la transacción en curso.
// Dos altas o ediciones simultáneas para el mismo barbero se ejecutan de a
// una: la segunda ve la reserva que guardó la primera y detecta el choque.
// Debe llamarse dentro de una transacción, antes de comprobar conflictos.
function bloquear_agenda_barbero($conexion, $id_barbero) {
    $sql = "SELECT id_barbero FROM barbero WHERE id_barbero = ? FOR UPDATE";
    $consulta = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($consulta, "i", $id_barbero);
    mysqli_stmt_execute($consulta);
    $existe = mysqli_num_rows(mysqli_stmt_get_result($consulta)) > 0;
    mysqli_stmt_close($consulta);
    return $existe;
}

// ¿El turno [hora, hora + duración) entra en el horario comercial? Es la
// misma regla que usa la grilla de generar_horarios_disponibles().
function turno_dentro_de_horario($hora, $duracion_minutos) {
    $inicio = hora_a_minutos($hora);
    $apertura = hora_a_minutos(RESV_HORA_APERTURA);
    $cierre = hora_a_minutos(RESV_HORA_CIERRE);
    return $inicio !== null && $inicio >= $apertura && $inicio + (int) $duracion_minutos <= $cierre;
}

// ¿La fecha y hora ya pasaron? Usa la zona horaria de conexion.php
// (America/Montevideo), la misma que la grilla.
function turno_ya_paso($fecha, $hora) {
    $momento = DateTime::createFromFormat("Y-m-d H:i", $fecha . " " . substr($hora, 0, 5));
    return $momento === false || $momento <= new DateTime();
}

// Cuántos días hacia adelante se puede agendar desde el panel. Se edita en
// Configuración (tabla "configuracion"); si la migración todavía no se
// ejecutó, rige el valor por defecto. La web de clientes tiene su propio
// límite (RESERVA_DIAS_ADELANTE, en sections/comun.php).
define("RESV_DIAS_ADMIN_DEFECTO", 60);
define("RESV_DIAS_ADMIN_MINIMO", 1);
define("RESV_DIAS_ADMIN_MAXIMO", 365);

function resv_dias_maximos_admin($conexion) {
    try {
        $resultado = mysqli_query($conexion, "SELECT valor FROM configuracion WHERE clave = 'reserva_dias_admin' LIMIT 1");
        $fila = $resultado ? mysqli_fetch_assoc($resultado) : null;
    } catch (Throwable $e) {
        $fila = null;
    }

    $dias = $fila ? filter_var($fila["valor"], FILTER_VALIDATE_INT, ["options" => ["min_range" => RESV_DIAS_ADMIN_MINIMO, "max_range" => RESV_DIAS_ADMIN_MAXIMO]]) : false;

    return $dias !== false ? $dias : RESV_DIAS_ADMIN_DEFECTO;
}

// Última fecha que se puede agendar desde el panel ("AAAA-MM-DD").
function resv_fecha_limite_admin($conexion) {
    return date("Y-m-d", strtotime("+" . resv_dias_maximos_admin($conexion) . " days"));
}

// ¿Ese día la barbería está cerrada? (fecha "AAAA-MM-DD")
function es_dia_cerrado($fecha) {
    $momento = strtotime($fecha);
    return $momento !== false && (int) date("N", $momento) === RESV_DIA_CERRADO;
}

// "HH:MM" o "HH:MM:SS" -> minutos desde las 00:00, o null si no es válida.
function hora_a_minutos($hora) {
    if (!is_string($hora) || !preg_match('/^([01]\d|2[0-3]):([0-5]\d)(:[0-5]\d)?$/', $hora, $m)) {
        return null;
    }
    return ((int) $m[1] * 60) + (int) $m[2];
}

// Minutos desde las 00:00 -> "HH:MM".
function minutos_a_hora($minutos) {
    return sprintf("%02d:%02d", intdiv((int) $minutos, 60), (int) $minutos % 60);
}

// Minutos -> "1h 30min", "2h" o "45 min" (mismo formato que reservas.js).
function formato_duracion_minutos($minutos) {
    $minutos = max(0, (int) $minutos);
    $horas = intdiv($minutos, 60);
    $resto = $minutos % 60;
    if ($horas > 0 && $resto > 0) {
        return $horas . "h " . $resto . "min";
    }
    if ($horas > 0) {
        return $horas . "h";
    }
    return $resto . " min";
}

// Chequeo de conflicto por SOLAPAMIENTO real (no por hora exacta):
// una reserva nueva/editada choca si su rango [inicio, inicio+duración)
// se superpone con el rango de cualquier reserva ya existente de ese
// barbero en esa fecha.
function existe_conflicto_intervalo($conexion, $id_barbero, $fecha, $hora, $duracion_minutos, $id_reserva_excluir = null) {

    $partes = explode(":", substr($hora, 0, 5));
    if (count($partes) < 2) {
        return true; // hora mal formada: se trata como conflicto para no guardar cualquier cosa
    }

    $inicio = ((int) $partes[0] * 60) + (int) $partes[1];
    $fin = $inicio + max((int) $duracion_minutos, 1);

    $ocupados = obtener_intervalos_ocupados($conexion, $id_barbero, $fecha, $id_reserva_excluir);

    foreach ($ocupados as $intervalo) {
        list($ocupado_inicio, $ocupado_fin) = $intervalo;
        if ($inicio < $ocupado_fin && $ocupado_inicio < $fin) {
            return true;
        }
    }

    return false;
}

// Genera la grilla de horarios (según RESV_HORA_APERTURA/CIERRE/INTERVALO)
// para un barbero, fecha y duración total de servicios dados, marcando
// cada horario como disponible u ocupado según las reservas reales.
// Si la fecha es hoy, además descarta los horarios que ya pasaron. Un día
// cerrado no tiene horarios.
function generar_horarios_disponibles($conexion, $id_barbero, $fecha, $duracion_minutos, $id_reserva_excluir = null) {

    if (es_dia_cerrado($fecha)) {
        return [];
    }

    if ($duracion_minutos <= 0) {
        $duracion_minutos = RESV_DURACION_MINIMA_MINUTOS;
    }

    $partes_apertura = explode(":", RESV_HORA_APERTURA);
    $partes_cierre = explode(":", RESV_HORA_CIERRE);
    $apertura = ((int) $partes_apertura[0] * 60) + (int) $partes_apertura[1];
    $cierre = ((int) $partes_cierre[0] * 60) + (int) $partes_cierre[1];

    $ocupados = obtener_intervalos_ocupados($conexion, $id_barbero, $fecha, $id_reserva_excluir);

    $ahora_minutos = null;
    if ($fecha === date("Y-m-d")) {
        $ahora_minutos = ((int) date("G") * 60) + (int) date("i");
    }

    $horarios = [];

    for ($inicio = $apertura; $inicio + $duracion_minutos <= $cierre; $inicio += RESV_INTERVALO_MINUTOS) {

        $fin = $inicio + $duracion_minutos;
        $disponible = true;
        $motivo = null;
        $choca_con = null;

        if ($ahora_minutos !== null && $inicio <= $ahora_minutos) {
            $disponible = false;
            $motivo = "pasado";
        }

        if ($disponible) {
            foreach ($ocupados as $intervalo) {
                list($ocupado_inicio, $ocupado_fin) = $intervalo;
                if ($inicio < $ocupado_fin && $ocupado_inicio < $fin) {
                    $disponible = false;
                    $motivo = "ocupado";
                    $choca_con = minutos_a_hora($ocupado_inicio) . "–" . minutos_a_hora($ocupado_fin);
                    break;
                }
            }
        }

        $horarios[] = [
            "hora" => minutos_a_hora($inicio),
            "fin" => minutos_a_hora($fin),
            "disponible" => $disponible,
            "motivo" => $motivo,
            "choca_con" => $choca_con,
        ];
    }

    return $horarios;
}
