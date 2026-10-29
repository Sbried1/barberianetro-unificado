<?php

// Arranque común de las páginas de la web de clientes: conexión, sesión y
// funciones de seguridad (escape de HTML, token CSRF y validaciones).

require_once __DIR__ . '/../conexion.php';

// Reglas de horarios compartidas con el panel (duración de los servicios,
// horario de atención, domingo cerrado, superposición de turnos).
require_once __DIR__ . '/../admin/reservas/reservas_funciones.php';

// Reglas propias de la web.
define('RESERVA_DIAS_ADELANTE', 15);
define('RESERVA_MAX_ACTIVAS', 3);

if (session_status() !== PHP_SESSION_ACTIVE) {
    // El servidor solo acepta ids de sesión que él mismo generó, la cookie
    // no es accesible desde JavaScript y no se envía desde otros sitios.
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('NETRO_CLIENTE');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Escapa un texto para mostrarlo en HTML.
function e($texto) {
    return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
}

// Token CSRF de la sesión (se crea la primera vez).
function csrf_token() {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

// Campo oculto para poner dentro de cada <form method="post">.
function csrf_campo() {
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

// ¿El formulario trae el token de esta sesión?
function csrf_valido() {
    $enviado = $_POST['csrf'] ?? '';
    return is_string($enviado) && $enviado !== '' && hash_equals(csrf_token(), $enviado);
}

// Lee un campo POST como texto (un arreglo cuenta como vacío).
function post_texto($clave) {
    return isset($_POST[$clave]) && is_string($_POST[$clave]) ? trim($_POST[$clave]) : '';
}

// Nombre y apellido: misma regla que el panel (1 a 30 letras o espacios),
// así un cliente registrado acá se puede editar después desde /admin.
function nombre_valido($texto) {
    return $texto !== '' && mb_strlen($texto) <= 30 && preg_match('/^[\p{L}\s]+$/u', $texto);
}

function email_valido($texto) {
    return $texto !== '' && mb_strlen($texto) <= 150 && filter_var($texto, FILTER_VALIDATE_EMAIL);
}

// Reservas activas del cliente: pendientes o confirmadas de hoy en
// adelante. Las completadas y canceladas no cuentan para el máximo.
function reservas_activas($conexion, $id_cliente) {
    $stmt = $conexion->prepare("SELECT COUNT(*) AS cantidad FROM reserva
                                WHERE id_cliente = ? AND estado IN ('pendiente', 'confirmada')
                                  AND fecha >= CURDATE()");
    $stmt->bind_param("i", $id_cliente);
    $stmt->execute();
    $cantidad = (int) $stmt->get_result()->fetch_assoc()["cantidad"];
    $stmt->close();
    return $cantidad;
}

// ¿Se puede reservar ese día desde la web? Formato AAAA-MM-DD, de hoy a
// RESERVA_DIAS_ADELANTE días y que no sea un día cerrado.
function fecha_reservable_web($fecha) {
    if (!is_string($fecha) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || !validar_fecha_reserva($fecha)) {
        return false;
    }
    $hoy = date('Y-m-d');
    $limite = date('Y-m-d', strtotime('+' . RESERVA_DIAS_ADELANTE . ' days'));
    return $fecha >= $hoy && $fecha <= $limite && !es_dia_cerrado($fecha);
}

// ¿Existe el barbero y está disponible para reservar?
function barbero_disponible($conexion, $id_barbero) {
    $stmt = $conexion->prepare("SELECT id_barbero FROM barbero WHERE id_barbero = ? AND estado = 'Disponible'");
    $stmt->bind_param("i", $id_barbero);
    $stmt->execute();
    $existe = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $existe;
}

// Ids de servicio enviados (arreglo o lista separada por comas). Devuelve
// los ids sin repetir, o un arreglo vacío si alguno no es válido o no
// existe (así nunca se guarda una reserva distinta de la elegida).
function servicios_validos($conexion, $valores) {
    if (is_string($valores)) {
        $valores = explode(',', $valores);
    }
    if (!is_array($valores)) {
        return [];
    }
    $ids = [];
    foreach ($valores as $valor) {
        if (!is_string($valor) || !ctype_digit($valor) || (int) $valor <= 0
            || !ids_existen($conexion, 'servicio', 'id_servicio', (int) $valor)) {
            return [];
        }
        if (!in_array((int) $valor, $ids, true)) {
            $ids[] = (int) $valor;
        }
    }
    return $ids;
}

// Ruta para mostrar la foto de un barbero desde sections/. En la base se
// guarda como la guarda el panel ("img/barberos/archivo", relativa a
// admin/); cualquier otra cosa muestra la imagen genérica.
function foto_barbero($url_imagen) {
    if (is_string($url_imagen) && preg_match('#^img/barberos/[A-Za-z0-9._-]+$#', $url_imagen)) {
        return '../admin/' . $url_imagen;
    }
    return '../resources/images/landscape-placeholder.svg';
}

// Si no hay un cliente con sesión iniciada, lo manda al login.
function requerir_cliente() {
    if (!isset($_SESSION['id_cliente'])) {
        header('Location: login.php');
        exit;
    }
}
