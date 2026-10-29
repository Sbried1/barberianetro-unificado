<?php
/**
 * auth/establecer_clave_barbero_cli.php
 *
 * SCRIPT DE CONSOLA para establecer una contraseña NUEVA (con hash válido) a
 * un barbero. Pensado para el barbero ID 5, cuya contraseña actual no es un
 * hash válido. NO se ejecuta desde el navegador (por URL responde 404) y NO
 * se ejecuta automáticamente: lo corrés vos, a mano.
 *
 * USO (desde C:\Barberia, con los contenedores levantados). El ID es
 * obligatorio y explícito:
 *
 *   Simulación: valida todo y NO escribe nada en la base
 *     docker compose exec web php auth/establecer_clave_barbero_cli.php --id=5 --dry-run
 *
 *   Ejecución real: actualiza UNA fila (la de ese barbero)
 *     docker compose exec web php auth/establecer_clave_barbero_cli.php --id=5
 *
 * QUÉ HACE:
 *   1. Busca al barbero por id (consulta preparada, solo lectura) y te
 *      muestra su nombre y SI su contraseña actual es un hash válido. El
 *      valor actual de la contraseña NO se muestra.
 *   2. Pide la contraseña nueva (sin mostrarla en pantalla, dos veces).
 *   3. Pide que escribas el ID del barbero para confirmar.
 *   4. Ejecuta UPDATE barbero SET contrasena = ? WHERE id_barbero = ? (solo
 *      esa columna y solo esa fila), guardando únicamente password_hash().
 *   5. Relee la fila y verifica con password_verify().
 *
 * Qué NO hace: no toca ninguna otra columna ni ninguna otra fila, no cambia
 * el esquema, no muestra ni registra ninguna contraseña (ni la anterior ni
 * la nueva).
 *
 * Alternativa sin script: una vez que exista un gerente, también se puede
 * cambiar la contraseña desde la pantalla Barberos (editar barbero), que ya
 * guarda hashes.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

function cli_salir(string $mensaje, int $codigo = 1): void
{
    fwrite($codigo === 0 ? STDOUT : STDERR, $mensaje . PHP_EOL);
    exit($codigo);
}

// Lee una línea. Si $secreto es true y hay terminal, no se muestra lo escrito.
function cli_leer(string $pregunta, bool $secreto = false): string
{
    fwrite(STDOUT, $pregunta);
    $terminal = function_exists('posix_isatty') && posix_isatty(STDIN);
    if ($secreto && $terminal) {
        shell_exec('stty -echo');
        register_shutdown_function(function () {
            shell_exec('stty echo');
        });
    }
    $linea = fgets(STDIN);
    if ($secreto && $terminal) {
        shell_exec('stty echo');
        fwrite(STDOUT, PHP_EOL);
    }
    return $linea === false ? '' : rtrim($linea, "\r\n");
}

$argumentos = $argv ?? [];
if (in_array('--help', $argumentos, true) || in_array('-h', $argumentos, true)) {
    cli_salir("Uso: php auth/establecer_clave_barbero_cli.php --id=N [--dry-run]\n  --id=N     id_barbero (obligatorio)\n  --dry-run  valida todo pero no escribe nada en la base", 0);
}
$simulacion = in_array('--dry-run', $argumentos, true);

$id_barbero = null;
foreach ($argumentos as $argumento) {
    if (preg_match('/^--id=([0-9]{1,10})$/', $argumento, $m) && (int) $m[1] > 0 && (int) $m[1] <= 2147483647) {
        $id_barbero = (int) $m[1];
    }
}
if ($id_barbero === null) {
    cli_salir("Falta el ID. Uso: php auth/establecer_clave_barbero_cli.php --id=5 [--dry-run]");
}

echo "== Establecer contraseña de un barbero ==" . PHP_EOL;
if ($simulacion) {
    echo "(modo --dry-run: se valida todo, pero NO se escribe nada en la base)" . PHP_EOL;
}

// ------------------------------------------------------- consulta (solo lectura)
try {
    require __DIR__ . '/../conexion.php';
} catch (Throwable $e) {
    cli_salir('No se pudo conectar a la base de datos: ' . $e->getMessage());
}
if (!isset($conexion) || !($conexion instanceof mysqli)) {
    cli_salir('No se pudo obtener la conexión a la base de datos.');
}

try {
    $consulta = mysqli_prepare($conexion, 'SELECT nombre, apellido, contrasena FROM barbero WHERE id_barbero = ? LIMIT 1');
    mysqli_stmt_bind_param($consulta, 'i', $id_barbero);
    mysqli_stmt_execute($consulta);
    $barbero = mysqli_fetch_assoc(mysqli_stmt_get_result($consulta));
    mysqli_stmt_close($consulta);
} catch (Throwable $e) {
    cli_salir('Error de base de datos al consultar: ' . $e->getMessage());
}

if (!$barbero) {
    cli_salir('No existe un barbero con id_barbero=' . $id_barbero . '. No se hizo ningún cambio.');
}

$info     = password_get_info((string) $barbero['contrasena']);
$es_hash  = isset($info['algo']) && $info['algo'] !== null && $info['algo'] !== 0;

echo PHP_EOL . 'Barbero: id ' . $id_barbero . ' - ' . $barbero['nombre'] . ' ' . $barbero['apellido'] . PHP_EOL;
echo 'Formato de la contraseña guardada hoy: '
    . ($es_hash ? 'hash válido (' . $info['algoName'] . ')' : 'NO es un hash válido (por eso hoy no podría iniciar sesión)')
    . PHP_EOL . '(el valor actual no se muestra)' . PHP_EOL . PHP_EOL;

// ------------------------------------------------------------ contraseña nueva
$clave  = cli_leer('Contraseña NUEVA (no se muestra): ', true);
$clave2 = cli_leer('Repetir contraseña nueva: ', true);

$errores = [];
if (strlen($clave) < 8) {
    $errores[] = 'La contraseña debe tener al menos 8 caracteres.';
}
if (strlen($clave) > 72) {
    $errores[] = 'La contraseña no puede superar los 72 bytes (bcrypt ignora lo que exceda).';
}
if ($clave !== $clave2) {
    $errores[] = 'Las dos contraseñas no coinciden.';
}
if ($errores) {
    cli_salir("No se hizo ningún cambio. Corregí lo siguiente:\n - " . implode("\n - ", $errores));
}

echo PHP_EOL . 'Se va a ACTUALIZAR 1 fila: barbero.contrasena del barbero id ' . $id_barbero . ' (solo esa columna).' . PHP_EOL;
echo 'Se guardará únicamente el hash de la contraseña nueva.' . PHP_EOL . PHP_EOL;

if ($simulacion) {
    cli_salir('[--dry-run] Todo es válido. No se escribió nada en la base.', 0);
}

if (cli_leer('Escribí el ID del barbero (' . $id_barbero . ') para confirmar (otra cosa cancela): ') !== (string) $id_barbero) {
    cli_salir('Cancelado. No se hizo ningún cambio.');
}

// ------------------------------------------------------------------- escritura
try {
    $hash = password_hash($clave, PASSWORD_DEFAULT);

    $actualizar = mysqli_prepare($conexion, 'UPDATE barbero SET contrasena = ? WHERE id_barbero = ?');
    mysqli_stmt_bind_param($actualizar, 'si', $hash, $id_barbero);
    mysqli_stmt_execute($actualizar);
    $filas = (int) mysqli_stmt_affected_rows($actualizar);
    mysqli_stmt_close($actualizar);

    if ($filas !== 1) {
        cli_salir('La actualización afectó ' . $filas . ' filas (se esperaba 1). Revisá la base.');
    }

    // Comprobación: se relee la fila y se verifica con password_verify().
    $leer = mysqli_prepare($conexion, 'SELECT contrasena FROM barbero WHERE id_barbero = ? LIMIT 1');
    mysqli_stmt_bind_param($leer, 'i', $id_barbero);
    mysqli_stmt_execute($leer);
    $fila = mysqli_fetch_assoc(mysqli_stmt_get_result($leer));
    mysqli_stmt_close($leer);

    if (!$fila || !password_verify($clave, $fila['contrasena'])) {
        cli_salir('Se actualizó la fila pero la verificación de la contraseña falló. Revisá la base.');
    }
} catch (Throwable $e) {
    cli_salir('Error de base de datos al actualizar: ' . $e->getMessage());
}

unset($clave, $clave2, $hash);

echo 'Listo: contraseña del barbero id ' . $id_barbero . ' actualizada.' . PHP_EOL;
echo 'Se guardó como hash y se verificó con password_verify().' . PHP_EOL;
echo 'Ahora puede iniciar sesión en /auth/login.php con su ID (' . $id_barbero . ') y la contraseña nueva.' . PHP_EOL;
exit(0);
