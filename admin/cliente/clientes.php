<?php

require_once __DIR__ . '/../auth/guard.php';
auth_requerir('clientes');

include("../conexion.php");

// Escapa cualquier valor antes de imprimirlo en HTML o dentro de un atributo.
function esc($valor) {
    return htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
}

// =====================================================
// CONSULTAS SQL
// =====================================================

// NOTA DE COMPATIBILIDAD: la tabla "cliente" no tiene columna "estado" ni
// "telefono". Columnas reales: id_cliente (int, autoincremental), email
// (único), nombre, apellido y contrasena.
$sql_total = "SELECT COUNT(*) AS total FROM cliente";
$resultado_total = mysqli_query($conexion, $sql_total);
$total_clientes = mysqli_fetch_assoc($resultado_total);

$sql_con_reservas = "SELECT COUNT(DISTINCT id_cliente) AS total FROM reserva";
$resultado_con_reservas = mysqli_query($conexion, $sql_con_reservas);
$total_con_reservas = mysqli_fetch_assoc($resultado_con_reservas);

// Se listan solo las columnas que la pantalla necesita: nunca se lee
// "contrasena" (el hash no debe llegar al HTML ni a la memoria de la página).
$sql_clientes_lista = "SELECT id_cliente, nombre, apellido, email FROM cliente ORDER BY nombre, apellido";
$resultado_clientes_lista = mysqli_query($conexion, $sql_clientes_lista);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../Estilos_index/global.css">
    <link rel="stylesheet" href="clientes_panel.css">
    <title>Barberia - Clientes</title>
</head>
<body>

    <header>
        <div class="nav">
            <div class="logo">
                <img src="../img/ChatGPT Image 15 jun 2026, 13_41_39.png" alt="" >
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
            <!-- Los enlaces son relativos a esta carpeta (cliente/), así funcionan
                 sin importar en qué ruta esté instalada la aplicación. -->
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
                        <a href="<?php echo auth_e(auth_url('configuracion/configuracion.php')); ?>">
                            Configuración
                        </a>
                    </li>
                    <?php auth_render_logout_item(); ?>

                </ul>
            </nav>
        </section>

        <!-- ==================== PANEL PRINCIPAL: LISTADO DE CLIENTES ==================== -->
        <section class="panel">

            <div class="clientes_header">
                <div class="title_servicios">
                    <h2>Clientes</h2>
                    <p>Administra los clientes de la barbería</p>
                </div>

                <button class="btn_add_servicio" id="btn_abrir_cliente">
                    <img src="../img/mas.png" alt="">
                    <span>Agregar cliente</span>
                </button>
            </div>

            <?php if (isset($_GET["error"]) && $_GET["error"] == "duplicado") { ?>
                <div class="alerta_clientes error" id="alerta_php">
                    <p>❌ Ya existe un cliente con ese email.</p>
                    <button onclick="this.parentElement.style.display='none'">Aceptar</button>
                </div>
            <?php } ?>

            <?php if (isset($_GET["error"]) && $_GET["error"] == "invalido") { ?>
                <div class="alerta_clientes error" id="alerta_php">
                    <p>❌ Revisá los datos ingresados, hay campos inválidos.</p>
                    <button onclick="this.parentElement.style.display='none'">Aceptar</button>
                </div>
            <?php } ?>

            <?php if (isset($_GET["error"]) && $_GET["error"] == "no_existe") { ?>
                <div class="alerta_clientes error" id="alerta_php">
                    <p>❌ El cliente que intentás modificar no existe.</p>
                    <button onclick="this.parentElement.style.display='none'">Aceptar</button>
                </div>
            <?php } ?>

            <?php if (isset($_GET["error"]) && $_GET["error"] == "restriccion") { ?>
                <div class="alerta_clientes error" id="alerta_php">
                    <p>❌ No se puede eliminar este cliente porque tiene reservas o ventas asociadas.</p>
                    <button onclick="this.parentElement.style.display='none'">Aceptar</button>
                </div>
            <?php } ?>

            <?php if (isset($_GET["error"]) && $_GET["error"] == "bd") { ?>
                <div class="alerta_clientes error" id="alerta_php">
                    <p>❌ No se pudo completar la operación por un error del sistema. Intentá nuevamente.</p>
                    <button onclick="this.parentElement.style.display='none'">Aceptar</button>
                </div>
            <?php } ?>

            <?php if (isset($_GET["ok"]) && $_GET["ok"] == "agregado") { ?>
                <div class="alerta_clientes ok" id="alerta_php">
                    <p>✅ Cliente agregado correctamente.</p>
                    <button onclick="this.parentElement.style.display='none'">Aceptar</button>
                </div>
            <?php } ?>

            <?php if (isset($_GET["ok"]) && $_GET["ok"] == "actualizado") { ?>
                <div class="alerta_clientes ok" id="alerta_php">
                    <p>✅ Cliente actualizado correctamente.</p>
                    <button onclick="this.parentElement.style.display='none'">Aceptar</button>
                </div>
            <?php } ?>

            <?php if (isset($_GET["ok"]) && $_GET["ok"] == "eliminado") { ?>
                <div class="alerta_clientes ok" id="alerta_php">
                    <p>✅ Cliente eliminado correctamente.</p>
                    <button onclick="this.parentElement.style.display='none'">Aceptar</button>
                </div>
            <?php } ?>

            <!-- ESTADISTICAS -->
            <div class="cuadro_stats">
                <div class="stat_card">
                    <div class="stat_icono morado">👥</div>
                    <div class="stat_texto">
                        <p>Clientes registrados</p>
                        <span><?php echo (int) $total_clientes["total"]; ?></span>
                    </div>
                </div>

                <div class="stat_card">
                    <div class="stat_icono verde">📅</div>
                    <div class="stat_texto">
                        <p>Con reservas</p>
                        <span><?php echo (int) $total_con_reservas["total"]; ?></span>
                    </div>
                </div>
            </div>

            <!-- BUSQUEDA -->
            <div id="navegacion_clientes">
                <input type="text" placeholder="Buscar por nombre o email..." id="buscar_cliente">
            </div>

            <!-- TABLA -->
            <div class="listar_servicios">

            <div class="cabecera_clientes">
                <p>Cliente</p>
                <p>Email</p>
                <p>Acciones</p>
            </div>

            <div id="lista_clientes">

                <?php while ($cliente = mysqli_fetch_assoc($resultado_clientes_lista)) {

                    $nombre_completo = $cliente["nombre"] . " " . $cliente["apellido"];
                    // mb_*: los nombres pueden empezar con letras acentuadas (Á, É, Ñ...)
                    $inicial_nombre = mb_strtoupper(
                        mb_substr($cliente["nombre"], 0, 1, "UTF-8") . mb_substr($cliente["apellido"], 0, 1, "UTF-8"),
                        "UTF-8"
                    );
                    ?>

                    <div
                        class="fila_cliente"
                        data-nombre="<?php echo esc(mb_strtolower($nombre_completo, "UTF-8")); ?>"
                        data-email="<?php echo esc(mb_strtolower($cliente["email"], "UTF-8")); ?>">

                        <div class="cliente_nombre_col">
                            <div class="avatar_cliente"><?php echo esc($inicial_nombre); ?></div>
                            <span class="nombre_completo"><?php echo esc($nombre_completo); ?></span>
                        </div>

                        <p><?php echo esc($cliente["email"]); ?></p>

                        <div class="acciones_cliente">

                            <button
                                type="button"
                                class="btn_accion_cliente btn_ver_cliente"
                                onclick="verCliente(this)"
                                data-id="<?php echo (int) $cliente['id_cliente']; ?>"
                                data-nombre="<?php echo esc($cliente['nombre']); ?>"
                                data-apellido="<?php echo esc($cliente['apellido']); ?>"
                                data-email="<?php echo esc($cliente['email']); ?>">
                                <img src="../img/ojo.png" alt="Ver">
                            </button>

                            <button
                                type="button"
                                class="btn_accion_cliente btn_editar_cliente"
                                onclick="editarCliente(this)"
                                data-id="<?php echo (int) $cliente['id_cliente']; ?>"
                                data-nombre="<?php echo esc($cliente['nombre']); ?>"
                                data-apellido="<?php echo esc($cliente['apellido']); ?>"
                                data-email="<?php echo esc($cliente['email']); ?>">
                                <img src="../img/lapiz.png" alt="Editar">
                            </button>

                            <form
                                action="eliminar_cliente.php"
                                method="POST"
                                style="display:contents;"
                                data-confirmar-titulo="¿Eliminar al cliente?"
                                data-confirmar="Vas a eliminar a <?php echo esc($nombre_completo); ?>. Esta acción no se puede deshacer.">
                                <input type="hidden" name="id_cliente" value="<?php echo (int) $cliente['id_cliente']; ?>">
                                <button
                                    type="submit"
                                    class="btn_accion_cliente btn_eliminar_cliente">
                                    <img src="../img/basura.png" alt="Eliminar">
                                </button>
                            </form>

                        </div>

                    </div>

                <?php } ?>

            </div>

            </div>

            <p class="sin_resultados" id="sin_resultados">No se encontraron clientes con esos criterios.</p>

        </section>

    </div>

    <!-- ==================== MODAL: AGREGAR CLIENTE ==================== -->
    <div id="modal_cliente" class="modal">
        <div class="modal-contenido_serv">
            <span class="cerrar_modal" id="cerrar_modal_cliente">&times;</span>

            <div class="categoria_header">
                <div class="title_servicios">
                    <h2>Agregar cliente</h2>
                    <p>Completá los datos del cliente.</p>
                </div>
            </div>

            <form action="agregar_cliente.php" method="POST" id="form_cliente">
                <div id="caja_form_cli">

                    <p>Nombre</p>
                    <div class="fila_doble">
                        <div>
                            <input type="text" name="nombre" class="input_form_cli" placeholder="Ej: Juan" required maxlength="30">
                        </div>
                        <div>
                            <input type="text" name="apellido" class="input_form_cli" placeholder="Ej: Pérez (apellido)" required maxlength="30">
                        </div>
                    </div>

                    <p>Email</p>
                    <input type="email" name="email" class="input_form_cli" placeholder="Ej: juan@gmail.com" required maxlength="60">

                    <div class="botones_modal_cli">
                        <button type="submit" class="btn_guardar_cli">
                            <img src="../img/tick.png" alt="">
                            Guardar
                        </button>
                        <button type="button" class="btn_cancelar_cli" id="cancelar_cliente">
                            <img src="../img/cruzroja.png" alt="">
                            Cancelar
                        </button>
                    </div>

                </div>
            </form>
        </div>
    </div>

    <!-- ==================== MODAL: EDITAR CLIENTE ==================== -->
    <div id="modal_editar_cliente" class="modal">
        <div class="modal-contenido_serv">
            <span class="cerrar_modal" id="cerrar_modal_editar_cliente">&times;</span>

            <div class="categoria_header">
                <div class="title_servicios">
                    <h2>Editar cliente</h2>
                    <p>Modificá los datos del cliente.</p>
                </div>
            </div>

            <form action="actualizar_cliente.php" method="POST" id="form_editar_cliente">

                <input type="hidden" name="id_cliente" id="editar_cli_id">

                <div id="caja_form_cli">

                    <p>Nombre</p>
                    <div class="fila_doble">
                        <div>
                            <input type="text" name="nombre" id="editar_cli_nombre" class="input_form_cli" required maxlength="30">
                        </div>
                        <div>
                            <input type="text" name="apellido" id="editar_cli_apellido" class="input_form_cli" required maxlength="30">
                        </div>
                    </div>

                    <p>Email</p>
                    <input type="email" name="email" id="editar_cli_email" class="input_form_cli" required maxlength="60">

                    <div class="botones_modal_cli">
                        <button type="submit" class="btn_guardar_cli">
                            <img src="../img/tick.png" alt="">
                            Guardar
                        </button>
                        <button type="button" class="btn_cancelar_cli" id="cancelar_editar_cliente">
                            <img src="../img/cruzroja.png" alt="">
                            Cancelar
                        </button>
                    </div>

                </div>
            </form>
        </div>
    </div>

    <!-- ==================== MODAL: VER CLIENTE ==================== -->
    <div id="modal_ver_cliente" class="modal">
        <div class="modal-contenido_ver">
            <span class="cerrar_modal" id="cerrar_modal_ver_cliente">&times;</span>

            <div class="ver_cliente_titulo">
                <h2>Detalle del cliente</h2>
                <p>Información completa del cliente seleccionado.</p>
            </div>

            <div id="ver_cliente_body">

                <span id="ver_cli_id" style="display: none;"></span>

                <div class="ver_fila">
                    <span class="ver_label">Nombre</span>
                    <span class="ver_valor" id="ver_cli_nombre"></span>
                </div>

                <div class="ver_fila">
                    <span class="ver_label">Apellido</span>
                    <span class="ver_valor" id="ver_cli_apellido"></span>
                </div>

                <div class="ver_fila">
                    <span class="ver_label">Email</span>
                    <span class="ver_valor" id="ver_cli_email"></span>
                </div>

            </div>

            <div class="ver_cliente_footer">
                <button type="button" class="btn_cancelar_cli" id="cerrar_ver_cliente_btn">Cerrar</button>
            </div>

        </div>
    </div>

    <!-- ==================== SCRIPTS ==================== -->
    <script>

        // ---------- BUSQUEDA ----------
        const inputBuscarCliente = document.getElementById("buscar_cliente");
        const filasClientes = document.querySelectorAll(".fila_cliente");
        const sinResultados = document.getElementById("sin_resultados");

        function filtrarClientes() {

            const texto = inputBuscarCliente.value.toLowerCase().trim();
            let visibles = 0;

            filasClientes.forEach(function (fila) {

                const coincideTexto =
                    fila.dataset.nombre.includes(texto) ||
                    fila.dataset.email.includes(texto);

                if (coincideTexto) {
                    fila.style.display = "grid";
                    visibles++;
                } else {
                    fila.style.display = "none";
                }

            });

            sinResultados.style.display = visibles === 0 ? "block" : "none";
        }

        inputBuscarCliente.addEventListener("input", filtrarClientes);

        // ---------- MODAL AGREGAR ----------
        const btnAbrirCliente = document.getElementById("btn_abrir_cliente");
        const modalCliente = document.getElementById("modal_cliente");
        const cerrarModalCliente = document.getElementById("cerrar_modal_cliente");
        const cancelarCliente = document.getElementById("cancelar_cliente");

        btnAbrirCliente.addEventListener("click", function () {
            modalCliente.style.display = "flex";
        });

        function cerrarAgregarCliente() {
            modalCliente.style.display = "none";
            document.getElementById("form_cliente").reset();
        }

        cerrarModalCliente.addEventListener("click", cerrarAgregarCliente);
        cancelarCliente.addEventListener("click", cerrarAgregarCliente);

        // ---------- MODAL EDITAR ----------
        const modalEditarCliente = document.getElementById("modal_editar_cliente");
        const cerrarModalEditarCliente = document.getElementById("cerrar_modal_editar_cliente");
        const cancelarEditarCliente = document.getElementById("cancelar_editar_cliente");

        function editarCliente(boton) {

            document.getElementById("editar_cli_id").value = boton.dataset.id;
            document.getElementById("editar_cli_nombre").value = boton.dataset.nombre;
            document.getElementById("editar_cli_apellido").value = boton.dataset.apellido;
            document.getElementById("editar_cli_email").value = boton.dataset.email;

            modalEditarCliente.style.display = "flex";
        }

        function cerrarEditarCliente() {
            modalEditarCliente.style.display = "none";
        }

        cerrarModalEditarCliente.addEventListener("click", cerrarEditarCliente);
        cancelarEditarCliente.addEventListener("click", cerrarEditarCliente);

        // ---------- MODAL VER ----------
        const modalVerCliente = document.getElementById("modal_ver_cliente");
        const cerrarModalVerCliente = document.getElementById("cerrar_modal_ver_cliente");
        const cerrarVerClienteBtn = document.getElementById("cerrar_ver_cliente_btn");

        function verCliente(boton) {

            document.getElementById("ver_cli_id").textContent = boton.dataset.id;
            document.getElementById("ver_cli_nombre").textContent = boton.dataset.nombre;
            document.getElementById("ver_cli_apellido").textContent = boton.dataset.apellido;
            document.getElementById("ver_cli_email").textContent = boton.dataset.email;

            modalVerCliente.style.display = "flex";
        }

        function cerrarVerCliente() {
            modalVerCliente.style.display = "none";
        }

        cerrarModalVerCliente.addEventListener("click", cerrarVerCliente);
        cerrarVerClienteBtn.addEventListener("click", cerrarVerCliente);

        // ---------- CERRAR MODALES CLICKEANDO AFUERA ----------
        window.addEventListener("click", function (e) {
            if (e.target === modalCliente) cerrarAgregarCliente();
            if (e.target === modalEditarCliente) cerrarEditarCliente();
            if (e.target === modalVerCliente) cerrarVerCliente();
        });

    </script>

</body>
</html>
