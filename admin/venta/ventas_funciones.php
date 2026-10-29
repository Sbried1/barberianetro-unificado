<?php
/**
 * ventas_funciones.php
 * Funciones auxiliares compartidas por ventas.php, guardar_venta.php,
 * actualizar_venta.php y eliminar_venta.php. Solo validaciones y
 * consultas relacionadas al módulo Ventas. No maneja salida HTML.
 *
 * ESTRUCTURA: la tabla "venta" tiene id_barbero, id_cliente, id_servicio
 * (uno solo por fila), total, fecha, "servicio" (nombre denormalizado) e
 * id_reserva (NULL en una venta directa). Una venta con varios servicios
 * se guarda como varias filas con el mismo id_reserva. No existen
 * "metodo_pago" ni "monto_recibido".
 *
 * TIPOS REALES (según el esquema): todos los id_* son INT. venta.total
 * es INT, pero servicio.precio es VARCHAR(50): por eso el precio de un
 * servicio se valida de forma estricta antes de usarlo como total (ver
 * venta_precio_a_entero). cliente NO tiene columna "telefono".
 */

// Rechazo de negocio (no un fallo técnico): el mensaje es el código que se
// envía a ventas.php como ?error=...
class VentaRechazada extends RuntimeException {}

// Valor máximo de una columna INT (con signo) en MySQL. Se usa para ids y total.
function venta_entero_maximo() {
    return 2147483647;
}

// Lee un campo de $_POST como texto limpio. Si falta o no es texto (por
// ejemplo, si llegó como arreglo), devuelve "".
function venta_post_texto($clave) {
    return isset($_POST[$clave]) && is_string($_POST[$clave]) ? trim($_POST[$clave]) : "";
}

// Convierte un valor en un id entero positivo (columnas INT). Acepta SOLO
// dígitos: "5abc", "-3", "1.5", "" o un arreglo devuelven null (a
// diferencia de un (int) directo, que convertiría "5abc" en 5).
function venta_entero_positivo($valor) {
    if (is_int($valor)) {
        return ($valor > 0 && $valor <= venta_entero_maximo()) ? $valor : null;
    }
    if (!is_string($valor)) {
        return null;
    }
    $texto = trim($valor);
    if (!preg_match('/^[0-9]{1,10}\z/', $texto)) {
        return null;
    }
    $numero = (int) $texto;
    return ($numero > 0 && $numero <= venta_entero_maximo()) ? $numero : null;
}

// Convierte servicio.precio (VARCHAR) en un entero apto para venta.total
// (INT). Devuelve null si el precio NO es válido. Es válido únicamente un
// número entero no negativo escrito solo con dígitos (0-9) que entre en un
// INT. Se rechazan: NULL, vacío, negativos ("-5"), decimales ("12.5",
// "12,5"), separadores de miles ("1.500"), símbolos ("$1500"), notación
// científica ("1e3"), texto ("abc") y valores que superan el máximo de INT.
function venta_precio_a_entero($precio) {
    if ($precio === null) {
        return null;
    }
    if (!is_string($precio) && !is_int($precio)) {
        return null;
    }
    $texto = trim((string) $precio);
    if (!preg_match('/^[0-9]{1,10}\z/', $texto)) {
        return null;
    }
    $numero = (int) $texto;
    return $numero <= venta_entero_maximo() ? $numero : null;
}

// Valida una fecha con formato estricto AAAA-MM-DD (el que envía <input type="date">).
function venta_fecha_valida($fecha) {
    if (!is_string($fecha) || !preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})\z/', $fecha, $partes)) {
        return false;
    }
    $anio = (int) $partes[1];
    return $anio >= 1000 && checkdate((int) $partes[2], (int) $partes[3], $anio);
}

// Redirige a ventas.php (con un mensaje opcional por query string) y corta
// la ejecución. Siempre se redirige después de un POST (patrón
// Post/Redirect/Get) para que recargar la página no reenvíe el formulario.
function venta_redirigir($consulta = "") {
    header("Location: ventas.php" . ($consulta !== "" ? "?" . $consulta : ""));
    exit;
}

// Deja el detalle técnico de un error en el log del servidor. El usuario
// nunca ve este detalle (solo un mensaje genérico en pantalla).
function venta_registrar_error($contexto, $error) {
    error_log("[ventas] " . $contexto . ": " . $error->getMessage());
}

// Prepara una consulta. Si falla, lanza una excepción en lugar de devolver
// false: así el error se maneja igual tanto si mysqli está configurado para
// lanzar excepciones (por defecto desde PHP 8.1) como si no lo está.
function venta_preparar($conexion, $sql) {
    $consulta = mysqli_prepare($conexion, $sql);
    if ($consulta === false) {
        throw new RuntimeException("No se pudo preparar la consulta: " . mysqli_error($conexion));
    }
    return $consulta;
}

// Ejecuta una consulta preparada; lanza una excepción si falla.
function venta_ejecutar($consulta) {
    if (!mysqli_stmt_execute($consulta)) {
        throw new RuntimeException("No se pudo ejecutar la consulta: " . mysqli_stmt_error($consulta));
    }
}

// Verifica que un ID exista realmente en la tabla indicada (barbero o servicio).
// Solo se aceptan las combinaciones tabla/columna de la lista blanca.
function venta_id_existe($conexion, $tabla, $columna_id, $id) {
    $tablas_permitidas = ["barbero" => "id_barbero", "servicio" => "id_servicio"];
    if (!isset($tablas_permitidas[$tabla]) || $tablas_permitidas[$tabla] !== $columna_id) {
        return false;
    }
    $id = (int) $id;
    $sql = "SELECT $columna_id FROM $tabla WHERE $columna_id = ? LIMIT 1";
    $consulta = venta_preparar($conexion, $sql);
    mysqli_stmt_bind_param($consulta, "i", $id);
    venta_ejecutar($consulta);
    $resultado = mysqli_stmt_get_result($consulta);
    $existe = mysqli_num_rows($resultado) > 0;
    mysqli_stmt_close($consulta);
    return $existe;
}

// cliente.id_cliente es INT (autoincremental) en la base real, y además
// venta.id_cliente es NOT NULL: toda venta requiere un cliente existente.
function venta_existe_cliente($conexion, $id_cliente) {
    $id_cliente = (int) $id_cliente;
    $sql = "SELECT id_cliente FROM cliente WHERE id_cliente = ? LIMIT 1";
    $consulta = venta_preparar($conexion, $sql);
    mysqli_stmt_bind_param($consulta, "i", $id_cliente);
    venta_ejecutar($consulta);
    $resultado = mysqli_stmt_get_result($consulta);
    $existe = mysqli_num_rows($resultado) > 0;
    mysqli_stmt_close($consulta);
    return $existe;
}

// Arma el arreglo de un servicio a partir de una fila con id_servicio,
// nombre y precio. "precio" es un entero, o null si el precio guardado en
// servicio.precio (VARCHAR) no es válido; "precio_valido" lo indica.
function venta_armar_servicio($fila) {
    $precio = venta_precio_a_entero($fila["precio"]);
    return [
        "id_servicio"   => (int) $fila["id_servicio"],
        "nombre"        => $fila["nombre"],
        "precio"        => $precio,
        "precio_valido" => $precio !== null,
    ];
}

// Trae los servicios asociados a una reserva vía servicioreserva (que
// reemplaza a detalle_reserva y NO guarda precio propio). El precio
// real siempre se lee desde "servicio". Cada servicio indica si su precio
// es válido (ver venta_armar_servicio); el total suma solo los válidos.
// Se usa al validar en el servidor una venta vinculada a una reserva.
function venta_servicios_de_reserva($conexion, $id_reserva) {
    $id_reserva = (int) $id_reserva;
    $sql = "SELECT s.id_servicio, s.nombre, s.precio
            FROM servicioreserva sr
            INNER JOIN servicio s ON s.id_servicio = sr.id_servicio
            WHERE sr.id_reserva = ?";
    $consulta = venta_preparar($conexion, $sql);
    mysqli_stmt_bind_param($consulta, "i", $id_reserva);
    venta_ejecutar($consulta);
    $resultado = mysqli_stmt_get_result($consulta);

    $servicios = [];
    $total = 0;
    while ($fila = mysqli_fetch_assoc($resultado)) {
        $servicio = venta_armar_servicio($fila);
        $servicios[] = $servicio;
        if ($servicio["precio_valido"]) {
            $total += $servicio["precio"];
        }
    }
    mysqli_stmt_close($consulta);

    return ["servicios" => $servicios, "total" => $total];
}

// Valida y trae de la tabla servicio los datos REALES (nunca confiar en
// precios enviados desde el formulario) para una lista de IDs de servicio
// ya validados como enteros positivos y sin repetir.
//
// Devuelve ["error" => ..., "servicios" => [...], "total" => int], donde
// "error" es null si todo está bien, o uno de:
//   - "no_existe":       algún id_servicio no existe en la tabla servicio
//                        (ya NO se ignora en silencio: se rechaza todo);
//   - "precio_invalido": algún servicio tiene un precio que no es un
//                        entero válido (ver venta_precio_a_entero).
// Si hay error, no se debe guardar nada.
function venta_servicios_desde_ids($conexion, $ids_servicios) {

    $servicios = [];
    $total = 0;
    $falta_alguno = false;
    $hay_precio_invalido = false;

    $sql = "SELECT id_servicio, nombre, precio FROM servicio WHERE id_servicio = ?";
    $consulta = venta_preparar($conexion, $sql);
    $id_actual = 0;
    mysqli_stmt_bind_param($consulta, "i", $id_actual);

    foreach ($ids_servicios as $id_servicio) {
        $id_actual = (int) $id_servicio;
        venta_ejecutar($consulta);
        $resultado = mysqli_stmt_get_result($consulta);
        $fila = mysqli_fetch_assoc($resultado);

        if (!$fila) {
            $falta_alguno = true;
            continue;
        }

        $servicio = venta_armar_servicio($fila);
        if (!$servicio["precio_valido"]) {
            $hay_precio_invalido = true;
            continue;
        }

        $servicios[] = $servicio;
        $total += $servicio["precio"];
    }
    mysqli_stmt_close($consulta);

    // Una referencia inexistente tiene prioridad sobre un precio inválido.
    if ($falta_alguno) {
        return ["error" => "no_existe", "servicios" => [], "total" => 0];
    }
    if ($hay_precio_invalido) {
        return ["error" => "precio_invalido", "servicios" => [], "total" => 0];
    }

    return ["error" => null, "servicios" => $servicios, "total" => $total];
}
