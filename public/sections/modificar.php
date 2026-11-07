<?php

require_once __DIR__ . '/comun.php';
requerir_cliente();

$errores = [];
$exito = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!csrf_valido()) {
        $_SESSION["errores_modificar"] = ["El formulario venció. Volvé a intentarlo."];
        header("Location: modificar_perfil.php");
        exit;
    }

    // 1. Validar que el id enviado coincida con la sesión
    $id_post = $_POST["id"] ?? "";
    if (!ctype_digit((string)$id_post) || (int)$id_post !== (int)$_SESSION["id_cliente"]) {
        http_response_code(403);
        exit("Acción no permitida.");
    }

    // A partir de acá se usa SIEMPRE el id de la sesión, nunca el del POST
    $id_cliente = (int)$_SESSION["id_cliente"];

    $nombre          = post_texto("nombre");
    $apellido        = post_texto("apellido");
    $password_actual = isset($_POST["password_actual"]) && is_string($_POST["password_actual"]) ? $_POST["password_actual"] : "";
    $password_nuevo  = isset($_POST["password_nuevo"]) && is_string($_POST["password_nuevo"]) ? $_POST["password_nuevo"] : "";
    $password_confirmar = isset($_POST["password_confirmar"]) && is_string($_POST["password_confirmar"]) ? $_POST["password_confirmar"] : "";

    // 2. Validaciones básicas (misma regla de nombre que el registro y el panel)
    if ($nombre !== "" && !nombre_valido($nombre)) {
        $errores[] = "El nombre solo puede tener letras y espacios (máximo 30).";
    }
    if ($apellido !== "" && !nombre_valido($apellido)) {
        $errores[] = "El apellido solo puede tener letras y espacios (máximo 30).";
    }
    if ($password_nuevo !== "" && mb_strlen($password_nuevo) < 8) {
        $errores[] = "La nueva contraseña debe tener al menos 8 caracteres.";
    }
    if ($nombre === "" && $apellido === "" && $password_nuevo === "") {
        $errores[] = "No ingresaste ningún cambio.";
    }
    if ($password_nuevo !== $password_confirmar) {
        $errores[] = "Las contraseñas no coinciden.";
    }

    // 3. Si quiere cambiar la contraseña, verificar la actual
    if (empty($errores) && $password_nuevo !== "") {
        if ($password_actual === "") {
            $errores[] = "Ingresa tu contraseña actual para cambiarla.";
        } else {
            $stmt = $conexion->prepare("SELECT contrasena FROM cliente WHERE id_cliente = ?");
            $stmt->bind_param("i", $id_cliente);
            $stmt->execute();
            $fila = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$fila || !password_verify($password_actual, $fila["contrasena"])) {
                $errores[] = "La contraseña actual es incorrecta.";
            }
        }
    }

if (!empty($errores)) {
    $_SESSION["errores_modificar"] = $errores;
    header("Location: modificar_perfil.php");
    exit;
}

    // 4. Construir el UPDATE solo con los campos que se completaron
    if (empty($errores)) {
        $campos = [];
        $tipos  = "";
        $valores = [];

        if ($nombre !== "") {
            $campos[] = "nombre = ?";
            $tipos .= "s";
            $valores[] = $nombre;
        }
        if ($apellido !== "") {
            $campos[] = "apellido = ?";
            $tipos .= "s";
            $valores[] = $apellido;
        }
        if ($password_nuevo !== "") {
            $campos[] = "contrasena = ?";
            $tipos .= "s";
            $valores[] = password_hash($password_nuevo, PASSWORD_DEFAULT);
        }

        $sql = "UPDATE cliente SET " . implode(", ", $campos) . " WHERE id_cliente = ?";
        $tipos .= "i";
        $valores[] = $id_cliente;

        $stmt = $conexion->prepare($sql);
        $stmt->bind_param($tipos, ...$valores);

        try {
            $actualizado = $stmt->execute();
        } catch (mysqli_sql_exception $e) {
            error_log("modificar.php: " . $e->getMessage());
            $actualizado = false;
        }

        if ($actualizado) {
            if ($nombre !== "") {
                $_SESSION["nombre"] = $nombre;
            }
            if ($apellido !== "") {
                $_SESSION["apellido"] = $apellido;
            }   
            header("Location: perfil.php?actualizado=1");
            exit;
        } else {
            $_SESSION["errores_modificar"] = ["No se pudo actualizar. Intenta de nuevo."];
            header("Location: modificar_perfil.php");
            exit;
        }
        $stmt->close();
    }
}
?>