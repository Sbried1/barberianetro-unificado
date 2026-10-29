<?php
/**
 * auth/crear_gerente_cli.php
 *
 * SCRIPT DE CONSOLA para crear una cuenta de gerente. NO se ejecuta desde el
 * navegador (si alguien lo pide por URL responde 404) y NO se ejecuta
 * automáticamente: lo corrés vos, a mano.
 *
 * USO (desde C:\Barberia, con los contenedores levantados):
 *
 *   Simulación: valida todo y NO escribe nada en la base
 *     docker compose exec web php auth/crear_gerente_cli.php --dry-run
 *
 *   Ejecución real: inserta UNA fila en la tabla `gerente`
 *     docker compose exec web php auth/crear_gerente_cli.php
 *
 * QUÉ HACE:
 *   1. Pide email, nombre, apellido y contraseña (la contraseña se escribe
 *      sin que se vea en pantalla y se pide dos veces).
 *   2. Valida los datos y consulta la base SOLO en lectura (cuántos gerentes
 *      hay y si el email ya existe).
 *   3. Muestra un resumen SIN la contraseña.
 *   4. Pide que escribas CREAR. Solo entonces hace el INSERT, con consulta
 *      preparada y guardando únicamente password_hash() de la contraseña.
 *   5. Vuelve a leer la fila y comprueba con password_verify() que la
 *      contraseña quedó bien guardada.
 *
 * Qué NO hace: no modifica ni borra ninguna fila existente, no cambia el
 * esquema, no muestra ni registra la contraseña (ni en pantalla ni en logs).
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

function cli_largo(string $texto): int
{
    return function_exists('mb_strlen') ? mb_strlen($texto, 'UTF-8') : strlen($texto);
}

$argumentos = $argv ?? [];
if (in_array('--help', $argumentos, true) || in_array('-h', $argumentos, true)) {
    cli_salir("Uso: php auth/crear_gerente_cli.php [--dry-run]\n  --dry-run  valida todo pero no escribe nada en la base", 0);
}
$simulacion = in_array('--dry-run', $argumentos, true);

echo "== Crear cuenta de gerente ==" . PHP_EOL;
if ($simulacion) {
    echo "(modo --dry-run: se valida todo, pero NO se escribe nada en la base)" . PHP_EOL;
}

$email    = trim(cli_leer('Email del gerente: '));
$nombre   = trim(cli_leer('Nombre: '));
$apellido = trim(cli_leer('Apellido: '));
$clave    = cli_leer('Contraseña (no se muestra): ', true);
$clave2   = cli_leer('Repetir contraseña: ', true);

// ---------------------------------------------------------------- validaciones
$errores = [];

if ($email === '' || strlen($email) > 150 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    $errores[] = 'El email no es válido (máximo 150 caracteres).';
}
foreach (['Nombre' => $nombre, 'Apellido' => $apellido] as $campo => $valor) {
    if ($valor === '' || cli_largo($valor) > 100 || preg_match('/[\x00-\x1F\x7F]/', $valor)) {
        $errores[] = $campo . ' es obligatorio, de hasta 100 caracteres y sin caracteres de control.';
    }
}
if (strlen($clave) < 10) {
    $errores[] = 'La contraseña debe tener al menos 10 caracteres.';
}
if (strlen($clave) > 72) {
    $errores[] = 'La contraseña no puede superar los 72 bytes (bcrypt ignora lo que exceda).';
}
if ($clave !== $clave2) {
    $errores[] = 'Las dos contraseñas no coinciden.';
}
if ($email !== '' && strcasecmp($clave, $email) === 0) {
    $errores[] = 'La contraseña no puede ser igual al email.';
}

if ($errores) {
    cli_salir("No se hizo ningún cambio. Corregí lo siguiente:\n - " . implode("\n - ", $errores));
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
    $total = (int) mysqli_fetch_row(mysqli_query($conexion, 'SELECT COUNT(*) FROM gerente'))[0];

    $consulta = mysqli_prepare($conexion, 'SELECT id_gerente FROM gerente WHERE email = ? LIMIT 1');
    mysqli_stmt_bind_param($consulta, 's', $email);
    mysqli_stmt_execute($consulta);
    $existe = mysqli_num_rows(mysqli_stmt_get_result($consulta)) > 0;
    mysqli_stmt_close($consulta);
} catch (Throwable $e) {
    cli_salir('Error de base de datos al consultar: ' . $e->getMessage());
}

if ($existe) {
    cli_salir('Ya existe un gerente con ese email. No se hizo ningún cambio.');
}

echo PHP_EOL . 'Gerentes existentes hoy: ' . $total . PHP_EOL;
echo 'Se va a INSERTAR 1 fila en la tabla `gerente`:' . PHP_EOL;
echo '  email    : ' . $email . PHP_EOL;
echo '  nombre   : ' . $nombre . PHP_EOL;
echo '  apellido : ' . $apellido . PHP_EOL;
echo '  contraseña: (se guardará solo su hash; no se muestra)' . PHP_EOL . PHP_EOL;

if ($simulacion) {
    cli_salir('[--dry-run] Todo es válido. No se escribió nada en la base.', 0);
}

if (cli_leer('Escribí CREAR para confirmar (cualquier otra cosa cancela): ') !== 'CREAR') {
    cli_salir('Cancelado. No se hizo ningún cambio.');
}

// ------------------------------------------------------------------- escritura
try {
    $hash = password_hash($clave, PASSWORD_DEFAULT);

    $insertar = mysqli_prepare($conexion, 'INSERT INTO gerente (contrasena, nombre, apellido, email) VALUES (?, ?, ?, ?)');
    mysqli_stmt_bind_param($insertar, 'ssss', $hash, $nombre, $apellido, $email);
    mysqli_stmt_execute($insertar);
    $id = (int) mysqli_insert_id($conexion);
    mysqli_stmt_close($insertar);

    // Comprobación: se relee la fila y se verifica la contraseña con password_verify().
    $leer = mysqli_prepare($conexion, 'SELECT contrasena FROM gerente WHERE id_gerente = ? LIMIT 1');
    mysqli_stmt_bind_param($leer, 'i', $id);
    mysqli_stmt_execute($leer);
    $fila = mysqli_fetch_assoc(mysqli_stmt_get_result($leer));
    mysqli_stmt_close($leer);

    if (!$fila || !password_verify($clave, $fila['contrasena'])) {
        cli_salir('Se insertó la fila (id_gerente=' . $id . ') pero la verificación de la contraseña falló. Revisá la base.');
    }
} catch (Throwable $e) {
    cli_salir('Error de base de datos al insertar: ' . $e->getMessage());
}

unset($clave, $clave2, $hash);

echo 'Listo: gerente creado (id_gerente=' . $id . ', email=' . $email . ').' . PHP_EOL;
echo 'La contraseña se guardó como hash y se verificó con password_verify().' . PHP_EOL;
echo 'Ahora podés iniciar sesión en /auth/login.php con ese email.' . PHP_EOL;
exit(0);
