<?php
/**
 * auth/cuentas.php
 *
 * Verificación de credenciales contra las tablas reales `gerente` y `barbero`.
 * Solo define funciones: no imprime nada.
 *
 * Reglas:
 *  - El ROL lo decide el servidor según la tabla donde se valida la cuenta;
 *    nunca llega desde un formulario. El FORMATO del identificador solo
 *    decide en qué tabla buscar:
 *        correo electrónico  -> tabla gerente  (gerente.email, único)
 *        solo dígitos        -> tabla barbero  (barbero.id_barbero, único)
 *    (barbero no tiene email ni otro campo único salvo id_barbero.)
 *  - Consultas preparadas; nada de datos del usuario concatenado en SQL.
 *  - Contraseñas verificadas EXCLUSIVAMENTE con password_verify() contra un
 *    hash válido. No existe ninguna comparación en texto plano: una cuenta
 *    cuya contraseña guardada no sea un hash válido simplemente no puede
 *    iniciar sesión hasta que se le asigne uno.
 *  - No se modifica ninguna contraseña (tampoco se re-hashea automáticamente).
 *  - Mensaje de error ÚNICO para "cuenta inexistente" y "contraseña
 *    incorrecta", y se realiza siempre un password_verify() para que el
 *    tiempo de respuesta no delate si la cuenta existe.
 *  - El campo barbero.estado (Disponible / No disponible) es disponibilidad,
 *    no estado de la cuenta: no se consulta al iniciar sesión.
 */
require_once __DIR__ . '/permisos.php';
auth_bloquear_acceso_directo(__FILE__);

// Hash bcrypt válido de un valor aleatorio descartado. Se usa para gastar el
// mismo tiempo cuando la cuenta no existe o su contraseña guardada no es un hash.
const AUTH_HASH_FICTICIO = '$2y$10$ZjVMFkjAn4oJbCySH9kzoe6N/9d2IQINozlgdTLgKgbkDOi6T52iW';

const AUTH_CLAVE_LARGO_MAX = 200;

// ¿Es un hash producido por password_hash() (bcrypt, argon2…)?
function auth_es_hash_valido(string $valor): bool
{
    $info = password_get_info($valor);
    return isset($info['algo']) && $info['algo'] !== null && $info['algo'] !== 0;
}

// Verifica una contraseña. Nunca compara texto plano.
function auth_verificar_clave($hash_guardado, string $clave): bool
{
    $usable   = is_string($hash_guardado) && auth_es_hash_valido($hash_guardado);
    $hash     = $usable ? $hash_guardado : AUTH_HASH_FICTICIO;
    $coincide = password_verify($clave, $hash);
    return $usable && $coincide;
}

// Decide en qué tabla buscar según el formato del identificador.
// Devuelve [rol, valor_a_buscar] o null si el formato no es válido.
function auth_clasificar_identificador(string $identificador): ?array
{
    if ($identificador === '' || strlen($identificador) > 150) {
        return null;
    }
    if (preg_match('/^[0-9]{1,10}\z/', $identificador)) {
        $id = (int) $identificador;
        return ($id > 0 && $id <= 2147483647) ? [AUTH_ROL_BARBERO, $id] : null;
    }
    if (filter_var($identificador, FILTER_VALIDATE_EMAIL) !== false) {
        return [AUTH_ROL_GERENTE, $identificador];
    }
    return null;
}

// Busca la cuenta con una consulta preparada. Devuelve la fila o null.
// Lanza una excepción si la base de datos falla.
function auth_buscar_cuenta($conexion, string $rol, $valor): ?array
{
    if ($rol === AUTH_ROL_GERENTE) {
        $sql  = 'SELECT id_gerente AS id, contrasena, nombre, apellido FROM gerente WHERE email = ? LIMIT 1';
        $tipo = 's';
    } else {
        $sql  = 'SELECT id_barbero AS id, contrasena, nombre, apellido FROM barbero WHERE id_barbero = ? LIMIT 1';
        $tipo = 'i';
    }

    $consulta = mysqli_prepare($conexion, $sql);
    if ($consulta === false) {
        throw new RuntimeException('No se pudo preparar la consulta de cuentas.');
    }
    mysqli_stmt_bind_param($consulta, $tipo, $valor);
    if (!mysqli_stmt_execute($consulta)) {
        throw new RuntimeException('No se pudo ejecutar la consulta de cuentas.');
    }
    $resultado = mysqli_stmt_get_result($consulta);
    $fila      = $resultado ? mysqli_fetch_assoc($resultado) : null;
    mysqli_stmt_close($consulta);

    return $fila ?: null;
}

// Intenta iniciar sesión. Devuelve:
//   ['ok' => true,  'usuario' => ['rol' => ..., 'id' => int, 'nombre' => ...], 'error' => null]
//   ['ok' => false, 'usuario' => null, 'error' => 'credenciales' | 'servicio']
// "credenciales" es el único mensaje para cuenta inexistente o contraseña mala.
function auth_intentar_login($conexion, string $identificador, string $clave): array
{
    $identificador = trim($identificador);
    $clasificado   = auth_clasificar_identificador($identificador);
    $largo_ok      = $clave !== '' && strlen($clave) <= AUTH_CLAVE_LARGO_MAX;

    $fila = null;
    if ($clasificado !== null) {
        try {
            $fila = auth_buscar_cuenta($conexion, $clasificado[0], $clasificado[1]);
        } catch (Throwable $e) {
            // Solo el mensaje técnico, nunca el identificador ni la contraseña.
            error_log('[auth] error de base de datos al buscar la cuenta: ' . $e->getMessage());
            return ['ok' => false, 'usuario' => null, 'error' => 'servicio'];
        }
    }

    // Siempre se ejecuta un password_verify (aunque no exista la cuenta).
    $coincide = auth_verificar_clave($fila['contrasena'] ?? null, $largo_ok ? $clave : '');

    if ($clasificado === null || $fila === null || !$largo_ok || !$coincide) {
        return ['ok' => false, 'usuario' => null, 'error' => 'credenciales'];
    }

    return [
        'ok'      => true,
        'error'   => null,
        'usuario' => [
            'rol'    => $clasificado[0],
            'id'     => (int) $fila['id'],
            'nombre' => trim($fila['nombre'] . ' ' . $fila['apellido']),
        ],
    ];
}
