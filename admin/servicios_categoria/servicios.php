<?php

require_once __DIR__ . '/../auth/guard.php';
auth_requerir('servicios');

include("../conexion.php");
require_once __DIR__ . "/servicios_funciones.php";

// =====================================================
// CONSULTAS SQL
// NOTA DE COMPATIBILIDAD: la base real NO tiene tabla "categoria" ni
// servicio.id_categoria ni servicio.estado. servicio.categoria es un
// campo de texto libre. Las "categorías" ahora se calculan como los
// valores distintos de esa columna.
// =====================================================
$sql_total_servicios = "SELECT COUNT(*) AS total FROM servicio";
$resultado_total = mysqli_query($conexion, $sql_total_servicios);
$total_servicios = mysqli_fetch_assoc($resultado_total);

$sql_total_categorias = "SELECT COUNT(DISTINCT categoria) AS total FROM servicio WHERE categoria IS NOT NULL AND categoria <> ''";
$resultado_total_categorias = mysqli_query($conexion, $sql_total_categorias);
$total_categorias = mysqli_fetch_assoc($resultado_total_categorias);

// Categorías (para el listado del modal "Categorías" y los <select>)
$sql_categorias_lista = "SELECT DISTINCT categoria AS nombre FROM servicio WHERE categoria IS NOT NULL AND categoria <> '' ORDER BY categoria";
$resultado_categorias_lista2 = mysqli_query($conexion, $sql_categorias_lista);
$resultado_categorias_lista = mysqli_query($conexion, $sql_categorias_lista);
$resultado_categorias_agregar = mysqli_query($conexion, $sql_categorias_lista);
$resultado_categorias_editar = mysqli_query($conexion, $sql_categorias_lista);

// Servicios (categoria ya viene como texto plano, no hace falta JOIN)
$sql_servicios_lista = "SELECT * FROM servicio ORDER BY nombre";
$resultado_servicios_lista = mysqli_query($conexion, $sql_servicios_lista);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../Estilos_index/global.css">
    
    <link rel="stylesheet" href="servicios_panel.css">
    <title>Barberia</title>
</head>

<!-- ==================== MODAL: CATEGORÍAS ==================== -->
<div id="modal_categoria" class="modal">

    <div class="modal-contenido">
        <div>
            <span class="cerrar_modal">&times;</span>
            <div class="categoria_header">
                <div class="title_servicios">
                    <h2>Categorías</h2>
                    <p>Acá podés administrar las categorías.</p>
                </div>
                <button class="btn_add_servicio" id="btn_agregar_categoria" style="display: none;">
                        <img src="../img/mas.png" alt="">          
                </button>
            </div>
            <form action="guardar_categoria.php" method="POST" id="form_categoria" >
                    <h2 id="titulo_agregar_cat">Agregar categoría</h2>
                    <p class="negrita" id="sub_titulo_cat">Nombre de la categoría :</p>
                    <div id="div_input_botones">
                        <input 
                         type="text" name="nombre" 
                         placeholder="Escribi el nombre de la categoría."
                         class="input_agregar_caegoria" 
                         required
                         maxlength="100"
                         
                         >



                        <div class="btns_cat_aniadir_eliminar">
                            <button class="btn_add_cat_ok" type="submit" style="display:flex;">
                                <img src="../img/tick.png" alt="">
                                Guardar
                            </button>
                            <button class="btn_add_cat_delete" id="cerrar_modal2" type="button" style="display:flex;">
                                <img src="../img/cruzroja.png" alt="">
                                <p>Cancelar</p>
                            </button>
                        </div>

                        <p class="ayuda_categoria_texto">
                            Nota: las categorías se generan automáticamente a partir de los
                            servicios cargados. Para que una categoría nueva aparezca acá,
                            asignala a un servicio desde "Agregar servicio".
                        </p>

                    </div>
            </form>
            
            <div id="lista_categorias">
                <?php if (isset($_GET["error"]) && $_GET["error"] == "en_uso") { ?>

                    <div class="alerta_error_categoria">

                        <p>
                            ❌ No se puede eliminar la categoría porque está siendo utilizada.
                        </p>

                        <button class="cerrar_alerta">
                            Aceptar
                        </button>

                    </div>

                <?php } ?>

                <?php if (isset($_GET["error"]) && $_GET["error"] == "categoria_no_soportada") { ?>

                    <div class="alerta_error_categoria">

                        <p>
                            ❌ Las categorías no se crean acá: se generan al asignarlas a un servicio.
                        </p>

                        <button class="cerrar_alerta">
                            Aceptar
                        </button>

                    </div>

                <?php } ?>

                <?php if (isset($_GET["error"]) && $_GET["error"] == "nombre_invalido") { ?>

                    <div class="alerta_error_categoria">

                        <p>
                            ❌ El nuevo nombre de la categoría es obligatorio y no puede superar los 100 caracteres.
                        </p>

                        <button class="cerrar_alerta">
                            Aceptar
                        </button>

                    </div>

                <?php } ?>

                <?php if (isset($_GET["error"]) && $_GET["error"] == "categoria_invalida") { ?>

                    <div class="alerta_error_categoria">

                        <p>
                            ❌ No se indicó qué categoría eliminar.
                        </p>

                        <button class="cerrar_alerta">
                            Aceptar
                        </button>

                    </div>

                <?php } ?>

                <?php if (isset($_GET["error"]) && $_GET["error"] == "categoria_existente") { ?>

                    <div class="alerta_error_categoria">

                        <p>
                            ❌ Ya existe una categoría con ese nombre.
                        </p>

                        <button class="cerrar_alerta">
                            Aceptar
                        </button>

                    </div>

                <?php } ?>
                <?php while($categoria = mysqli_fetch_assoc($resultado_categorias_lista)){ ?>
                

                <div class="categorias_listadas">

                   <p class="nombre_categoria">
                        <?php echo htmlspecialchars($categoria["nombre"]); ?>
                    </p>

                    <form action="actualizar_categoria.php" method="POST" class="form_editar" style="display:none;">

                        <input
                            type="hidden"
                            name="categoria_actual"
                            value="<?php echo htmlspecialchars($categoria["nombre"]); ?>">

                        <input
                            type="text"
                            name="nombre"
                            class="input_editar"
                            value="<?php echo htmlspecialchars($categoria["nombre"]); ?>">

                        <button type="submit" class="btn_guardar btn_add_cat_ok_edi">
                           <img src="../img/tick.png" alt="">
                              
                        </button>

                        <button type="button" class="btn_cancelar btn_add_cat_delete_edi">
                            <img src="../img/cruzroja.png" alt="">
                            
                        </button>

                    </form>

                    <div id="boton_derecha">

                        <form
                            action="eliminar_categoria.php"
                            method="POST"
                            data-confirmar-titulo="¿Eliminar la categoría?"
                            data-confirmar="Vas a eliminar la categoría «<?php echo htmlspecialchars($categoria["nombre"]); ?>». Los servicios que la usan quedarán sin categoría.">

                            <input
                                type="hidden"
                                name="categoria"
                                value="<?php echo htmlspecialchars($categoria["nombre"]); ?>">

                            <button
                                type="submit"
                                id="boton_eli_cat">

                                <img src="../img/cruzroja.png" alt="">

                            </button>

                        </form>

                    
                        <button
                            type="button"
                            class="boton_edi_cat">

                            <img src="../img/lapiz.png" alt="">

                        </button>

                    </div>

                </div>

                <?php } ?>
            </div>
            
        </div>
    </div>

</div>

<!-- ==================== MODAL: AGREGAR SERVICIO ==================== -->
<div id="modal_servicio" class="modal">

    <div class="modal-contenido_serv">

        <span id="cerrar_modal_servicio" class="cerrar_modal">
            &times;
        </span>

        <div class="categoria_header">

            <div class="title_servicios">

                <h2>Agregar servicio</h2>

                <p>Completá los datos del servicio.</p>

            </div>

        </div>

        <form action="gurdar_servicio.php" method="POST" id="form_servicio" class="form_servicio_ajax">
            <div id="caja_form_serv">
                    <p class="panel_alerta_form" role="alert" hidden></p>
                    <div class="panel_campo_cabecera">
                        <p>Nombre del servicio</p>
                        <span class="panel_contador" data-contador="nuevo_nombre"></span>
                    </div>
                    <input
                        type="text"
                        placeholder="Ej: Corte clasico"
                        name="nombre"
                        id="nuevo_nombre"
                        class="input_form_serv"
                        required
                        maxlength="<?php echo SERVICIO_NOMBRE_MAXIMO; ?>"
                    >
                    <p>Categoria</p>
                    <input
                        type="text"
                        name="categoria"
                        id="categ_modserv"
                        class="input_form_serv"
                        list="lista_categorias_datalist"
                        placeholder="Ej: Cortes"
                        maxlength="100"
                        required
                    >
                    <datalist id="lista_categorias_datalist">
                        <?php while ($categoria_agregar = mysqli_fetch_assoc($resultado_categorias_agregar)) { ?>
                            <option value="<?php echo htmlspecialchars($categoria_agregar["nombre"]); ?>">
                        <?php } ?>
                    </datalist>

                            <div id="precio_duracion_estestadp">
                                
                                    
                                    
                                    
                                
                                    <div id="pre_dur_esta">
                                        <div class="order_box">
                                            <p>Precio</p>
                                            <input 
                                            placeholder="Ej: 350"
                                            type="number" 
                                            name="precio" 
                                            class="input_form_serv"
                                            required
                                            min="1"
                                            max="999999"
                                            step="1"
                                            >

                                        </div>
                                        
                                        <div class="order_box">
                                            <p>Duracion</p>
                                            <input 
                                                placeholder="Ej: 30 min"
                                                type="text" 
                                                name="duracion"
                                                class="input_form_serv"
                                                required
                                                maxlength="50"
                                            >
                                        </div>
                                    </div>
                            </div>
                    
                    <div class="panel_campo_cabecera">
                        <p>Descripción (opcional)</p>
                        <span class="panel_contador" data-contador="nueva_descripcion"></span>
                    </div>
                    <div id="colum">
                            <textarea
                                name="descripcion"
                                id="nueva_descripcion"
                                placeholder="Ej: Corte clásico con tijera y máquina."
                                class="caja_de_texto"
                                rows="5"
                                maxlength="<?php echo SERVICIO_DESCRIPCION_MAXIMO; ?>"
                            ></textarea>
                        
                        <div class="btns_cat_aniadir_eliminar">

                            <button 
                                class="btn_add_ser_ok" 
                                type="submit" 
                                style="display:flex;"
                            >
                                <img src="../img/tick.png" alt="">
                                Guardar
                            </button>

                            <button
                                class="btn_add_ser_delete" 
                                id="cerrar_modal_servicio2" 
                                type="button" 
                                style="display:flex;"
                            >
                                <img src="../img/cruzroja.png" alt="">
                                Cancelar
                            </button>

                        </div>
                    </div>
            </div>
        </form>

    </div>

</div>
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

        <!-- ==================== PANEL PRINCIPAL: LISTADO DE SERVICIOS ==================== -->
        <section class="panel">

            <div class="servicios_header">

                <div class="title_servicios">
                    <h2>Servicios</h2>
                    <p>Administra los servicios de la barbería</p>
                </div>

                <div id="btn_derecha">

                    <button id="btn_abrir_ventana_categoria" class="btn_add_servicio">
                    
                        <img src="../img/2_puntos.png" alt="">

                        <span>Categorias</span>
                    </button>

                    <button class="btn_add_servicio" id="btn_abrir_servicio">
                        <img src="../img/mas.png" alt="">
                        <span>Agregar servicio</span>
                    </button>

                </div>

            </div>

            <?php
            // Avisos de resultado (?error=... / ?ok=...). Los textos son fijos:
            // el valor que llega por la URL solo se usa para elegir el mensaje,
            // nunca se imprime. Los errores de categorías llegan con
            // ?modal=categorias y se muestran dentro de ese modal.
            $mensajes_error_servicio = servicio_mensajes_error();
            $mensajes_ok_servicio = [
                "agregado"    => "Servicio agregado correctamente.",
                "actualizado" => "Servicio actualizado correctamente.",
                "eliminado"   => "Servicio eliminado correctamente."
            ];
            $codigo_error_servicio = (isset($_GET["error"]) && is_string($_GET["error"])) ? $_GET["error"] : "";
            $codigo_ok_servicio = (isset($_GET["ok"]) && is_string($_GET["ok"])) ? $_GET["ok"] : "";
            $resultado_de_categorias = isset($_GET["modal"]);
            ?>

            <?php if (!$resultado_de_categorias && isset($mensajes_error_servicio[$codigo_error_servicio])) { ?>

                <div class="alerta_error_categoria">

                    <p>
                        ❌ <?php echo $mensajes_error_servicio[$codigo_error_servicio]; ?>
                    </p>

                    <button class="cerrar_alerta">
                        Aceptar
                    </button>

                </div>

            <?php } ?>

            <?php if (!$resultado_de_categorias && isset($mensajes_ok_servicio[$codigo_ok_servicio])) { ?>

                <!-- Reutiliza la estructura de .alerta_error_categoria (el botón
                     "Aceptar" la busca por esa clase) con los colores de éxito. -->
                <div class="alerta_error_categoria" style="background: var(--verde-bg); border-color: rgba(74, 222, 128, 0.35); color: var(--verde);">

                    <p>
                        ✅ <?php echo $mensajes_ok_servicio[$codigo_ok_servicio]; ?>
                    </p>

                    <button class="cerrar_alerta" style="background: var(--verde);">
                        Aceptar
                    </button>

                </div>

            <?php } ?>

            <!-- ESTADISTICAS -->
            <div class="cuadro_stats">
                <div class="stat_card">
                    <div class="stat_icono morado">🛠</div>
                    <div class="stat_texto">
                        <p>Servicios registrados</p>
                        <span><?php echo (int) $total_servicios["total"]; ?></span>
                    </div>
                </div>

                <div class="stat_card">
                    <div class="stat_icono verde">🏷</div>
                    <div class="stat_texto">
                        <p>Categorías</p>
                        <span><?php echo (int) $total_categorias["total"]; ?></span>
                    </div>
                </div>
            </div>

            <div id="navegacion">

                <input type="text" placeholder="Buscar servicio..." id="buscar_servicio">

                <select id="categoria">

                    <option value="">Todas las categorías</option>

                    <?php while ($categoria = mysqli_fetch_assoc($resultado_categorias_lista2)) { ?>

                        <option value="<?php echo htmlspecialchars(strtolower($categoria["nombre"])); ?>">

                            <?php echo htmlspecialchars($categoria["nombre"]); ?>

                        </option>

                    <?php } ?>

                </select>

            </div>

            <div class="listar_servicios">
                <div class="cabecera">
                    <p>ID</p>
                    <p>Nombre</p>
                    <p>Categoría</p>
                    <p>Duración</p>
                    <p>Precio</p>
                    <p>Acciones</p>
                </div>

                <?php while ($servicio = mysqli_fetch_assoc($resultado_servicios_lista)) { ?>

                    <div
                        class="fila"
                        data-nombre="<?php echo strtolower(htmlspecialchars($servicio["nombre"])); ?>"
                        data-categoria="<?php echo strtolower(htmlspecialchars($servicio["categoria"] ?? "")); ?>">

                        <p class="id_ser_listar">
                            <?php echo (int) $servicio["id_servicio"]; ?>
                        </p>

                        <p class="nombre_ser_listar">
                            <?php echo htmlspecialchars($servicio["nombre"]); ?>
                        </p>

                        <p class="categoria_ser_listar">
                            <?php echo htmlspecialchars($servicio["categoria"] ?? "—"); ?>
                        </p>

                        <p class="duracion_ser_listar">
                            <?php echo htmlspecialchars($servicio["duracion"] ?? "—"); ?>
                        </p>

                        <p class="precio_ser_listar">
                            $<?php echo htmlspecialchars($servicio["precio"] ?? "—"); ?>
                        </p>

                        <div class="contenedor_opciones_servicio">

                            <button 
                                type="button"
                                class="btn_opciones_servicio_ver"
                                onclick="verServicio(this)"
                                data-id="<?php echo (int) $servicio['id_servicio']; ?>"
                                data-nombre="<?php echo htmlspecialchars($servicio['nombre']); ?>"
                                data-categoria-nombre="<?php echo htmlspecialchars($servicio['categoria'] ?? ''); ?>"
                                data-precio="<?php echo htmlspecialchars($servicio['precio'] ?? ''); ?>"
                                data-duracion="<?php echo htmlspecialchars($servicio['duracion'] ?? ''); ?>"
                                data-descripcion="<?php echo htmlspecialchars($servicio['descripcion'] ?? ''); ?>">
                                <img src="../img/ojo.png">
                            </button>

                            <button
                                type="button"
                                class="btn_opciones_servicio_editar"
                                onclick="editarServicio(this)"
                                data-id="<?php echo (int) $servicio['id_servicio']; ?>"
                                data-nombre="<?php echo htmlspecialchars($servicio['nombre']); ?>"
                                data-categoria="<?php echo htmlspecialchars($servicio['categoria'] ?? ''); ?>"
                                data-precio="<?php echo htmlspecialchars($servicio['precio'] ?? ''); ?>"
                                data-duracion="<?php echo htmlspecialchars($servicio['duracion'] ?? ''); ?>"
                                data-descripcion="<?php echo htmlspecialchars($servicio['descripcion'] ?? ''); ?>">

                                <img src="../img/lapizz.png">

                            </button>

                            <form
                                action="eliminar_servicio.php"
                                method="POST"
                                data-confirmar-titulo="¿Eliminar el servicio?"
                                data-confirmar="Vas a eliminar «<?php echo htmlspecialchars($servicio['nombre']); ?>». Esta acción no se puede deshacer. Si tiene reservas o ventas asociadas, no se eliminará.">

                                <input
                                    type="hidden"
                                    name="id_servicio"
                                    value="<?php echo (int) $servicio['id_servicio']; ?>">

                                <button
                                    type="submit"
                                    class="btn_opciones_servicio_eliminar">

                                    <img src="../img/basura.png">

                                </button>

                            </form>

                        </div>

                    </div>

                <?php } ?>

            </div>

        </section>

    </div>
    <!-- ==================== MODAL: EDITAR SERVICIO ==================== -->
    <div id="modal_editar_servicio" class="modal">

    <div class="modal-contenido_serv">

        <span id="cerrar_modal_editar_servicio" class="cerrar_modal">
            &times;
        </span>

        <div class="categoria_header">

            <div class="title_servicios">

                <h2>Editar servicio</h2>

                <p>Modificá los datos del servicio.</p>

            </div>

        </div>

        <form action="actualizar_servicio.php" method="POST" id="form_editar_servicio" class="form_servicio_ajax">

            <input
                type="hidden"
                name="id_servicio"
                id="editar_id_servicio">

            <div id="caja_form_serv">

                <p class="panel_alerta_form" role="alert" hidden></p>

                <div class="panel_campo_cabecera">
                    <p>Nombre del servicio</p>
                    <span class="panel_contador" data-contador="editar_nombre"></span>
                </div>

                <input
                    type="text"
                    name="nombre"
                    id="editar_nombre"
                    class="input_form_serv"
                    maxlength="<?php echo SERVICIO_NOMBRE_MAXIMO; ?>"
                    required>

                <p>Categoría</p>

                <input
                    type="text"
                    name="categoria"
                    id="editar_categoria"
                    class="input_form_serv"
                    list="lista_categorias_datalist_editar"
                    maxlength="100"
                    required>

                <datalist id="lista_categorias_datalist_editar">
                    <?php while ($categoria_editar = mysqli_fetch_assoc($resultado_categorias_editar)) { ?>
                        <option value="<?php echo htmlspecialchars($categoria_editar["nombre"]); ?>">
                    <?php } ?>
                </datalist>

                <div id="precio_duracion_estestadp">

                    <div id="pre_dur_esta">

                        <div class="order_box">

                            <p>Precio</p>

                            <input
                                type="number"
                                name="precio"
                                id="editar_precio"
                                class="input_form_serv"
                                min="1"
                                max="999999"
                                step="1"
                                required>

                        </div>

                        <div class="order_box">

                            <p>Duración</p>

                            <input
                                type="text"
                                name="duracion"
                                id="editar_duracion"
                                class="input_form_serv"
                                maxlength="50"
                                required>

                        </div>

                    </div>

                </div>

                <div class="panel_campo_cabecera">
                    <p>Descripción (opcional)</p>
                    <span class="panel_contador" data-contador="editar_descripcion"></span>
                </div>

                <div id="colum">

                    <textarea
                        name="descripcion"
                        id="editar_descripcion"
                        class="caja_de_texto"
                        rows="5"
                        maxlength="<?php echo SERVICIO_DESCRIPCION_MAXIMO; ?>"></textarea>

                    <div class="btns_cat_aniadir_eliminar">

                        <button
                            class="btn_add_ser_ok"
                            type="submit">

                            <img src="../img/tick.png" alt="">
                            Guardar

                        </button>

                        <button
                            class="btn_add_ser_delete"
                            type="button"
                            id="cancelar_edicion_servicio">

                            <img src="../img/cruzroja.png" alt="">
                            Cancelar

                        </button>

                    </div>

                </div>

            </div>

        </form>

    </div>


</div>
<!-- ==================== MODAL: VER SERVICIO ==================== -->
<div id="modal_ver_servicio" class="modal">

    <div class="modal-contenido_ver">

        <span id="cerrar_modal_ver_servicio" class="cerrar_modal">&times;</span>

        <div class="ver_servicio_titulo">
            <h2>Detalle del servicio</h2>
            <p>Información completa del servicio seleccionado.</p>
        </div>

        <div id="ver_servicio_body">

            <div class="ver_fila">
                <span class="ver_label">ID</span>
                <span class="ver_valor" id="ver_id"></span>
            </div>

            <div class="ver_fila">
                <span class="ver_label">Nombre</span>
                <span class="ver_valor" id="ver_nombre"></span>
            </div>

            <div class="ver_fila">
                <span class="ver_label">Categoría</span>
                <span class="ver_valor" id="ver_categoria"></span>
            </div>

            <div class="ver_fila">
                <span class="ver_label">Duración</span>
                <span class="ver_valor" id="ver_duracion"></span>
            </div>

            <div class="ver_fila">
                <span class="ver_label">Precio</span>
                <span class="ver_valor" id="ver_precio"></span>
            </div>

            <div id="ver_descripcion_box">
                <p class="ver_label">Descripción</p>
                <p id="ver_descripcion"></p>
            </div>

        </div>

        <div class="ver_servicio_footer">
            <button type="button" class="btn_add_ser_delete" id="btn_cerrar_ver_servicio">
                Cerrar
            </button>
        </div>

    </div>

</div>
</body>
<!-- ==================== SCRIPTS ==================== -->
<script>

    const inputBuscar = document.getElementById("buscar_servicio");
    const selectCategoria = document.getElementById("categoria");

    const filas = document.querySelectorAll(".fila");

    function filtrarServicios() {

        const texto = inputBuscar.value.toLowerCase();

        const categoria = selectCategoria.value;

        filas.forEach(function (fila) {

            const nombre = fila.dataset.nombre;

            const idCategoria = fila.dataset.categoria;

            const coincideNombre = nombre.includes(texto);

            const coincideCategoria =
                categoria === "" || idCategoria === categoria;

            if (coincideNombre && coincideCategoria) {

                fila.style.display = "grid";

            } else {

                fila.style.display = "none";

            }

        });

    }

    inputBuscar.addEventListener("input", filtrarServicios);

    selectCategoria.addEventListener("change", filtrarServicios);


    const botonesCerrarAlerta =
        document.querySelectorAll(".cerrar_alerta");

    botonesCerrarAlerta.forEach(function (boton) {

        boton.addEventListener("click", function () {

            const alerta =
                boton.closest(".alerta_error_categoria");

            alerta.style.display = "none";

        });

    });


    const cerrarEditar =
        document.getElementById("cerrar_modal_editar_servicio");

    cerrarEditar.addEventListener("click", function () {

        document.getElementById(
            "modal_editar_servicio"
        ).style.display = "none";

    });


    const cancelarEdicionServicio =
        document.getElementById("cancelar_edicion_servicio");

    cancelarEdicionServicio.addEventListener("click", function () {

        document.getElementById(
            "modal_editar_servicio"
        ).style.display = "none";

    });


    const botonesOpcionesServicio =
        document.querySelectorAll(".btn_opciones_servicio");


    function verServicio(boton) {

    document.getElementById("ver_id").textContent =
        boton.dataset.id;

    document.getElementById("ver_nombre").textContent =
        boton.dataset.nombre;

    document.getElementById("ver_categoria").textContent =
        boton.dataset.categoriaNombre;

    document.getElementById("ver_duracion").textContent =
        /^\d+$/.test(boton.dataset.duracion.trim()) ? boton.dataset.duracion.trim() + " min" : boton.dataset.duracion;

    document.getElementById("ver_precio").textContent =
        "$" + boton.dataset.precio;

    document.getElementById("ver_descripcion").textContent =
        boton.dataset.descripcion || "Sin descripción.";

    document.getElementById("modal_ver_servicio").style.display = "flex";

}


const modalVerServicio =
    document.getElementById("modal_ver_servicio");

const cerrarVerServicio =
    document.getElementById("cerrar_modal_ver_servicio");

const btnCerrarVerServicio =
    document.getElementById("btn_cerrar_ver_servicio");


cerrarVerServicio.addEventListener("click", function () {
    modalVerServicio.style.display = "none";
});

btnCerrarVerServicio.addEventListener("click", function () {
    modalVerServicio.style.display = "none";
});


    function editarServicio(boton) {

        document.getElementById("editar_id_servicio").value =
            boton.dataset.id;

        document.getElementById("editar_nombre").value =
            boton.dataset.nombre;

        document.getElementById("editar_categoria").value =
            boton.dataset.categoria;

        document.getElementById("editar_precio").value =
            boton.dataset.precio;

        document.getElementById("editar_duracion").value =
            boton.dataset.duracion;

        document.getElementById("editar_descripcion").value =
            boton.dataset.descripcion;

        // Los valores se cargaron por código: el contador no se entera solo.
        ["editar_nombre", "editar_descripcion"].forEach(function (id) {
            document.getElementById(id).dispatchEvent(new Event("input"));
        });

        limpiarErrorServicio(document.getElementById("form_editar_servicio"));

        document.getElementById(
            "modal_editar_servicio"
        ).style.display = "flex";

    }


    // Alta y edición se envían con fetch: si el servidor rechaza los datos
    // (por ejemplo, un nombre duplicado) el error aparece en el modal y no se
    // pierde lo escrito. Sin JavaScript, el formulario se envía normalmente.
    function limpiarErrorServicio(form) {

        const alerta = form.querySelector(".panel_alerta_form");

        alerta.textContent = "";
        alerta.hidden = true;

    }


    function mostrarErrorServicio(form, mensaje) {

        const alerta = form.querySelector(".panel_alerta_form");

        alerta.textContent = mensaje;
        alerta.hidden = false;

        alerta.scrollIntoView({ block: "center" });

    }


    document.querySelectorAll(".form_servicio_ajax").forEach(function (form) {

        form.addEventListener("submit", function (e) {

            e.preventDefault();

            if (form.dataset.enviando) {
                return;
            }

            const boton = form.querySelector('button[type="submit"]');

            form.dataset.enviando = "1";
            PanelUI.cargando(boton, true);
            limpiarErrorServicio(form);

            fetch(form.action, {
                method: "POST",
                body: new FormData(form),
                headers: { "X-Panel-Ajax": "1" }
            })
                .then(function (respuesta) {

                    if (respuesta.status === 401) {
                        window.location.reload();
                        return new Promise(function () {});
                    }

                    return respuesta.json();

                })
                .then(function (datos) {

                    // Éxito: la página se recarga con el aviso y el botón sigue ocupado.
                    if (datos.ok) {
                        window.location.href = datos.destino;
                        return;
                    }

                    mostrarErrorServicio(form, datos.mensaje || "No se pudo guardar el servicio. Intentá nuevamente.");
                    delete form.dataset.enviando;
                    PanelUI.cargando(boton, false);

                })
                .catch(function () {

                    mostrarErrorServicio(form, "No se pudo comprobar si el servicio se guardó. Recargá la página y revisá el listado antes de reintentar.");
                    delete form.dataset.enviando;
                    PanelUI.cargando(boton, false);

                });

        });

    });


    botonesOpcionesServicio.forEach(function (boton) {

        boton.addEventListener("click", function () {

            const menu = boton.nextElementSibling;

            if (menu.style.display === "block") {

                menu.style.display = "none";

            } else {

                menu.style.display = "block";

            }

        });

    });


    const btnAbrirServicio =
        document.getElementById("btn_abrir_servicio");

    const modalServicio =
        document.getElementById("modal_servicio");

    const cerrarServicio =
        document.getElementById("cerrar_modal_servicio");

    const cerrarServicio2 =
        document.getElementById("cerrar_modal_servicio2");


    btnAbrirServicio.addEventListener("click", function () {

        // Nunca dos modales principales abiertos a la vez: si estaba
        // abierto el de categorías, se cierra antes de abrir este.
        modalCategoria.style.display = "none";
        formCategoria.style.display = "none";

        modalServicio.style.display = "flex";

    });


    cerrarServicio.addEventListener("click", function () {

        modalServicio.style.display = "none";

    });


    cerrarServicio2.addEventListener("click", function () {

        modalServicio.style.display = "none";

    });


    const botonCategoria =
        document.getElementById("btn_abrir_ventana_categoria");

    const modalCategoria =
        document.getElementById("modal_categoria");

    const cerrarCategoria =
        modalCategoria.querySelector(".cerrar_modal");

    const cerrarFormulario =
        document.getElementById("cerrar_modal2");

    const btnAgregarCategoria =
        document.getElementById("btn_agregar_categoria");

    const formCategoria =
        document.getElementById("form_categoria");


    botonCategoria.addEventListener("click", function () {

        // Misma regla en la otra dirección: si estaba abierto el modal
        // de "Agregar servicio", se cierra antes de abrir Categorías.
        modalServicio.style.display = "none";

        modalCategoria.style.display = "flex";

    });


    cerrarCategoria.addEventListener("click", function () {

        modalCategoria.style.display = "none";

        formCategoria.style.display = "none";

        const alertas =
            document.querySelectorAll(".alerta_error_categoria");

        alertas.forEach(function (alerta) {

            alerta.style.display = "none";

        });

    });


    cerrarFormulario.addEventListener("click", function () {

        formCategoria.style.display = "none";

    });


    btnAgregarCategoria.addEventListener("click", function () {

        formCategoria.style.display = "flex";

    });


    window.addEventListener("click", function (e) {

        if (e.target === modalCategoria) {

            modalCategoria.style.display = "none";

            formCategoria.style.display = "none";

            const alertas =
                document.querySelectorAll(".alerta_error_categoria");

            alertas.forEach(function (alerta) {

                alerta.style.display = "none";

            });

        }


        if (e.target === modalServicio) {

            modalServicio.style.display = "none";

        }

    });


    const parametros =
        new URLSearchParams(window.location.search);


    if (parametros.get("modal") === "categorias") {

        modalCategoria.style.display = "flex";

    }


    const botonesEditar =
        document.querySelectorAll(".boton_edi_cat");


    botonesEditar.forEach(function (boton) {

        boton.addEventListener("click", function () {

            const fila =
                boton.closest(".categorias_listadas");

            fila.querySelector(
                ".nombre_categoria"
            ).style.display = "none";

            fila.querySelector(
                ".form_editar"
            ).style.display = "flex";

            fila.querySelector(
                "#boton_derecha"
            ).style.display = "none";

        });

    });


    const botonesCancelar =
        document.querySelectorAll(".btn_cancelar");


    botonesCancelar.forEach(function (boton) {

        boton.addEventListener("click", function () {

            const fila =
                boton.closest(".categorias_listadas");

            fila.querySelector(
                ".nombre_categoria"
            ).style.display = "block";

            fila.querySelector(
                ".form_editar"
            ).style.display = "none";

            fila.querySelector(
                "#boton_derecha"
            ).style.display = "flex";

        });

    });

</script>
</html>