<?php

require_once __DIR__ . '/../auth/guard.php';
auth_requerir('ventas');

include("../conexion.php");
include("ventas_funciones.php");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ventas.php");
    exit;
}

$id_cliente = venta_entero_positivo(venta_post_texto("id_cliente"));
$id_barbero = venta_entero_positivo(venta_post_texto("id_barbero"));
$id_reserva_post = venta_post_texto("id_reserva");
$id_reserva = null;
$fecha_post = venta_post_texto("fecha");

if ($id_reserva_post !== "") {
    $id_reserva = venta_entero_positivo($id_reserva_post);

    if ($id_reserva === null) {
        venta_redirigir("error=invalido");
    }
}

if ($id_cliente === null) {
    venta_redirigir("error=cliente_requerido");
}

if ($id_barbero === null) {
    venta_redirigir("error=invalido");
}

if ($fecha_post === "") {
    $fecha = date("Y-m-d");
} elseif (venta_fecha_valida($fecha_post)) {
    $fecha = $fecha_post;
} else {
    venta_redirigir("error=invalido");
}

$servicios_post = isset($_POST["servicios"]) && is_array($_POST["servicios"]) ? $_POST["servicios"] : [];
$ids_servicios = [];

foreach ($servicios_post as $valor) {
    $id_servicio = venta_entero_positivo($valor);

    if ($id_servicio === null) {
        venta_redirigir("error=invalido");
    }

    if (!in_array($id_servicio, $ids_servicios, true)) {
        $ids_servicios[] = $id_servicio;
    }
}

if (empty($ids_servicios)) {
    venta_redirigir("error=invalido");
}

$destino = "";
$transaccion_iniciada = false;

try {
    if (!venta_existe_cliente($conexion, $id_cliente)) {
        $destino = "error=no_existe";
    } elseif (!venta_id_existe($conexion, "barbero", "id_barbero", $id_barbero)) {
        $destino = "error=no_existe";
    } else {
        $info_servicios = venta_servicios_desde_ids($conexion, $ids_servicios);

        if ($info_servicios["error"] !== null) {
            $destino = "error=" . $info_servicios["error"];
        } else {
            if (!mysqli_begin_transaction($conexion)) {
                throw new RuntimeException("No se pudo iniciar la transacción.");
            }

            $transaccion_iniciada = true;

            if ($id_reserva !== null) {
                $sql_reserva = "SELECT id_cliente, id_barbero, estado FROM reserva WHERE id_reserva = ? FOR UPDATE";
                $consulta_reserva = venta_preparar($conexion, $sql_reserva);
                mysqli_stmt_bind_param($consulta_reserva, "i", $id_reserva);
                venta_ejecutar($consulta_reserva);

                $resultado_reserva = mysqli_stmt_get_result($consulta_reserva);
                $reserva = mysqli_fetch_assoc($resultado_reserva);

                mysqli_stmt_close($consulta_reserva);

                if (!$reserva) {
                    throw new VentaRechazada("no_existe");
                }

                if (!in_array($reserva["estado"], ["pendiente", "confirmada"], true)) {
                    throw new VentaRechazada("reserva_no_disponible");
                }

                $sql_vendida = "SELECT id_venta FROM venta WHERE id_reserva = ? LIMIT 1";
                $consulta_vendida = venta_preparar($conexion, $sql_vendida);
                mysqli_stmt_bind_param($consulta_vendida, "i", $id_reserva);
                venta_ejecutar($consulta_vendida);
                $ya_vendida = mysqli_num_rows(mysqli_stmt_get_result($consulta_vendida)) > 0;
                mysqli_stmt_close($consulta_vendida);

                if ($ya_vendida) {
                    throw new VentaRechazada("reserva_no_disponible");
                }

                if ((int) $reserva["id_cliente"] !== $id_cliente || (int) $reserva["id_barbero"] !== $id_barbero) {
                    throw new VentaRechazada("reserva_no_coincide");
                }

                $servicios_reserva = venta_servicios_de_reserva($conexion, $id_reserva);
                $ids_reserva = array_values(array_unique(array_map(function ($s) {
                    return $s["id_servicio"];
                }, $servicios_reserva["servicios"])));
                $ids_enviados = $ids_servicios;
                sort($ids_reserva);
                sort($ids_enviados);

                if (empty($ids_reserva) || $ids_reserva !== $ids_enviados) {
                    throw new VentaRechazada("reserva_no_coincide");
                }
            }

            $sql_venta = "INSERT INTO venta (id_barbero, id_cliente, id_servicio, total, fecha, servicio, id_reserva) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $consulta_venta = venta_preparar($conexion, $sql_venta);

            $p_id_servicio = 0;
            $p_total = 0;
            $p_nombre = "";
            $p_id_reserva = $id_reserva;

            mysqli_stmt_bind_param(
                $consulta_venta,
                "iiiissi",
                $id_barbero,
                $id_cliente,
                $p_id_servicio,
                $p_total,
                $fecha,
                $p_nombre,
                $p_id_reserva
            );

            foreach ($info_servicios["servicios"] as $servicio) {
                $p_id_servicio = $servicio["id_servicio"];
                $p_total = $servicio["precio"];
                $p_nombre = $servicio["nombre"];

                venta_ejecutar($consulta_venta);
            }

            mysqli_stmt_close($consulta_venta);

            if ($id_reserva !== null) {
                $sql_actualizar_reserva = "UPDATE reserva SET estado = 'completada' WHERE id_reserva = ? AND estado IN ('pendiente', 'confirmada')";
                $consulta_actualizar_reserva = venta_preparar($conexion, $sql_actualizar_reserva);
                mysqli_stmt_bind_param($consulta_actualizar_reserva, "i", $id_reserva);
                venta_ejecutar($consulta_actualizar_reserva);

                if (mysqli_stmt_affected_rows($consulta_actualizar_reserva) !== 1) {
                    mysqli_stmt_close($consulta_actualizar_reserva);
                    throw new RuntimeException("No se pudo completar la reserva.");
                }

                mysqli_stmt_close($consulta_actualizar_reserva);
            }

            if (!mysqli_commit($conexion)) {
                throw new RuntimeException("No se pudo confirmar la transacción.");
            }

            $transaccion_iniciada = false;
            $destino = "ok=agregado";
        }
    }
} catch (Throwable $e) {
    if ($transaccion_iniciada) {
        try {
            mysqli_rollback($conexion);
        } catch (Throwable $e_rollback) {
        }
    }

    if ($e instanceof VentaRechazada) {
        $destino = "error=" . $e->getMessage();
    } else {
        venta_registrar_error("guardar_venta", $e);
        $destino = "error=guardar";
    }
}

venta_redirigir($destino);
