<?php

require_once __DIR__ . '/../auth/guard.php';
auth_requerir('barberos');

include("../conexion.php");

// =====================================================
// ESTADISTICAS (tarjetas superiores)
// NOTA DE COMPATIBILIDAD: la tabla "barbero" real no tiene columnas
// "telefono", "email" ni "calificacion". El estado real es un ENUM
// ('Disponible' / 'No disponible'), no 'activo'/'inactivo'.
// =====================================================
$sql_total = "SELECT COUNT(*) AS total FROM barbero";
$total_barberos = mysqli_fetch_assoc(mysqli_query($conexion, $sql_total))["total"];

$sql_disponibles = "SELECT COUNT(*) AS total FROM barbero WHERE estado = 'Disponible'";
$total_disponibles = mysqli_fetch_assoc(mysqli_query($conexion, $sql_disponibles))["total"];

$sql_no_disponibles = "SELECT COUNT(*) AS total FROM barbero WHERE estado = 'No disponible'";
$total_no_disponibles = mysqli_fetch_assoc(mysqli_query($conexion, $sql_no_disponibles))["total"];

// =====================================================
// ESPECIALIDADES SUGERIDAS (para el campo de texto del formulario)
// No existe una tabla de especialidades: "especialidad" es un texto libre
// (varchar(50)) de cada barbero. Las sugerencias son SOLO los valores que
// ya están cargados en la base; no se agrega ninguno.
// =====================================================
$sql_especialidades = "
    SELECT DISTINCT TRIM(especialidad) AS especialidad_texto
    FROM barbero
    WHERE especialidad IS NOT NULL AND TRIM(especialidad) <> ''
    ORDER BY especialidad_texto
";
$resultado_especialidades = mysqli_query($conexion, $sql_especialidades);
$especialidades_sugeridas = [];
while ($fila = mysqli_fetch_assoc($resultado_especialidades)) {
    $especialidades_sugeridas[] = $fila["especialidad_texto"];
}

// =====================================================
// LISTADO DE BARBEROS (sin traer nunca la contraseña)
// =====================================================
$sql_barberos = "
    SELECT id_barbero, nombre, apellido, url_imagen, especialidad, en_netro_desde, anio_inicio, estado
    FROM barbero
    ORDER BY nombre, apellido
";
$resultado_barberos = mysqli_query($conexion, $sql_barberos);

$barberos = [];
while ($fila = mysqli_fetch_assoc($resultado_barberos)) {
    $barberos[] = [
        "id_barbero"      => (int) $fila["id_barbero"],
        "nombre"          => $fila["nombre"],
        "apellido"        => $fila["apellido"],
        "url_imagen"      => $fila["url_imagen"],
        "especialidad"    => $fila["especialidad"],
        "en_netro_desde"  => $fila["en_netro_desde"] !== null ? (int) $fila["en_netro_desde"] : null,
        "anio_inicio"     => $fila["anio_inicio"] !== null ? (int) $fila["anio_inicio"] : null,
        "estado"          => $fila["estado"],
    ];
}

$json_barberos = json_encode(
    $barberos,
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../Estilos_index/global.css">
    <link rel="stylesheet" href="barberos_panel.css">
    <title>Barberia - Barberos</title>
</head>
<body>

    <header>
        <div class="nav">
            <div class="logo">
                <img src="../img/ChatGPT Image 15 jun 2026, 13_41_39.png" alt="">
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
                        <a href="#">
                            Configuración
                        </a>
                    </li>
                    <?php auth_render_logout_item(); ?>

                </ul>
            </nav>
        </section>

        <!-- ==================== PANEL PRINCIPAL: BARBEROS ==================== -->
        <section class="panel">

            <div class="servicios_header">
                <div class="title_servicios">
                    <h2>Barberos</h2>
                    <p>Gestiona los barberos de la barbería</p>
                </div>

                <button class="btn_add_servicio" id="btn_abrir_barbero">
                    <img src="../img/mas.png" alt="">
                    <span>Nuevo barbero</span>
                </button>
            </div>

            <!-- ALERTAS -->
            <?php if (isset($_GET["error"]) && $_GET["error"] == "invalido") { ?>
                <div class="barb_alerta error" id="alerta_php">
                    <p>❌ Revisá los datos ingresados: hay campos obligatorios vacíos o inválidos.</p>
                    <button onclick="this.parentElement.style.display='none'">Aceptar</button>
                </div>
            <?php } ?>

            <?php if (isset($_GET["error"]) && $_GET["error"] == "anios") { ?>
                <div class="barb_alerta error" id="alerta_php">
                    <p>❌ "En Netro desde" no puede ser anterior al año en que empezó como barbero.</p>
                    <button onclick="this.parentElement.style.display='none'">Aceptar</button>
                </div>
            <?php } ?>

            <?php if (isset($_GET["error"]) && $_GET["error"] == "password_corta") { ?>
                <div class="barb_alerta error" id="alerta_php">
                    <p>❌ La contraseña debe tener al menos 6 caracteres.</p>
                    <button onclick="this.parentElement.style.display='none'">Aceptar</button>
                </div>
            <?php } ?>

            <?php if (isset($_GET["error"]) && $_GET["error"] == "no_existe") { ?>
                <div class="barb_alerta error" id="alerta_php">
                    <p>❌ El barbero que intentás modificar no existe.</p>
                    <button onclick="this.parentElement.style.display='none'">Aceptar</button>
                </div>
            <?php } ?>

            <?php if (isset($_GET["error"]) && $_GET["error"] == "restriccion") { ?>
                <div class="barb_alerta error" id="alerta_php">
                    <p>❌ No se pudo eliminar: el barbero tiene reservas o ventas asociadas.</p>
                    <button onclick="this.parentElement.style.display='none'">Aceptar</button>
                </div>
            <?php } ?>

            <?php if (isset($_GET["error"]) && $_GET["error"] == "guardar") { ?>
                <div class="barb_alerta error" id="alerta_php">
                    <p>❌ Ocurrió un error al guardar el barbero. Intentá nuevamente.</p>
                    <button onclick="this.parentElement.style.display='none'">Aceptar</button>
                </div>
            <?php } ?>

            <?php if (isset($_GET["error"]) && $_GET["error"] == "imagen_tamano") { ?>
                <div class="barb_alerta error" id="alerta_php">
                    <p>❌ La imagen supera el tamaño máximo permitido (2 MB).</p>
                    <button onclick="this.parentElement.style.display='none'">Aceptar</button>
                </div>
            <?php } ?>

            <?php if (isset($_GET["error"]) && $_GET["error"] == "imagen_invalida") { ?>
                <div class="barb_alerta error" id="alerta_php">
                    <p>❌ El archivo seleccionado no es una imagen válida. Usá JPG, PNG o WEBP.</p>
                    <button onclick="this.parentElement.style.display='none'">Aceptar</button>
                </div>
            <?php } ?>

            <?php if (isset($_GET["error"]) && ($_GET["error"] == "imagen_error" || $_GET["error"] == "imagen_carpeta" || $_GET["error"] == "imagen_guardar")) { ?>
                <div class="barb_alerta error" id="alerta_php">
                    <p>❌ Ocurrió un error al subir la imagen. Intentá nuevamente.</p>
                    <button onclick="this.parentElement.style.display='none'">Aceptar</button>
                </div>
            <?php } ?>

            <?php if (isset($_GET["ok"]) && $_GET["ok"] == "agregado") { ?>
                <div class="barb_alerta ok" id="alerta_php">
                    <p>✅ Barbero agregado correctamente.</p>
                    <button onclick="this.parentElement.style.display='none'">Aceptar</button>
                </div>
            <?php } ?>

            <?php if (isset($_GET["ok"]) && $_GET["ok"] == "actualizado") { ?>
                <div class="barb_alerta ok" id="alerta_php">
                    <p>✅ Barbero actualizado correctamente.</p>
                    <button onclick="this.parentElement.style.display='none'">Aceptar</button>
                </div>
            <?php } ?>

            <?php if (isset($_GET["ok"]) && $_GET["ok"] == "eliminado") { ?>
                <div class="barb_alerta ok" id="alerta_php">
                    <p>✅ Barbero eliminado correctamente.</p>
                    <button onclick="this.parentElement.style.display='none'">Aceptar</button>
                </div>
            <?php } ?>

            <!-- ESTADISTICAS -->
            <div class="barb_stats">
                <div class="barb_stat_card">
                    <div class="barb_stat_icono morado">👥</div>
                    <div class="barb_stat_texto">
                        <p>Total barberos</p>
                        <span><?php echo (int) $total_barberos; ?></span>
                    </div>
                </div>

                <div class="barb_stat_card">
                    <div class="barb_stat_icono verde">✔</div>
                    <div class="barb_stat_texto">
                        <p>Disponibles</p>
                        <span><?php echo (int) $total_disponibles; ?></span>
                    </div>
                </div>

                <div class="barb_stat_card">
                    <div class="barb_stat_icono ambar">⏱</div>
                    <div class="barb_stat_texto">
                        <p>No disponibles</p>
                        <span><?php echo (int) $total_no_disponibles; ?></span>
                    </div>
                </div>
            </div>

            <!-- FILTROS -->
            <div id="barb_filtros">
                <input type="text" id="barb_buscar" placeholder="Buscar por nombre, apellido o especialidad...">

                <div class="barb_filtro_campo">
                    <label for="barb_filtro_estado">Estado</label>
                    <select id="barb_filtro_estado">
                        <option value="">Todos los estados</option>
                        <option value="Disponible">Disponible</option>
                        <option value="No disponible">No disponible</option>
                    </select>
                </div>

                <div class="barb_filtro_campo">
                    <label for="barb_orden">Ordenar por</label>
                    <select id="barb_orden">
                        <option value="nombre_asc">Nombre A-Z</option>
                        <option value="nombre_desc">Nombre Z-A</option>
                    </select>
                </div>

                <button type="button" id="barb_limpiar_filtros" class="barb_btn_limpiar">
                    <span>↺</span> Limpiar
                </button>
            </div>

            <!-- LISTADO -->
            <div class="barb_panel_lista">

                <div class="barb_cabecera_lista">
                    <p>Barbero</p>
                    <p>Especialidad</p>
                    <p>En Netro desde</p>
                    <p>Estado</p>
                    <p>Acciones</p>
                </div>

                <div id="lista_barberos_body"></div>

                <div class="barb_vacio" id="barb_lista_vacia">
                    <div class="barb_vacio_icono">✂️</div>
                    <p class="barb_vacio_titulo" id="barb_vacio_titulo">No hay barberos registrados</p>
                    <p class="barb_vacio_texto" id="barb_vacio_texto">Aún no se han creado barberos.</p>
                    <button type="button" class="btn_add_servicio" id="btn_abrir_barbero_vacio">
                        <img src="../img/mas.png" alt="">
                        <span>Nuevo barbero</span>
                    </button>
                </div>

                <div class="barb_paginacion" id="barb_paginacion">
                    <p id="barb_paginacion_texto">Mostrando 0 de 0 barberos</p>
                    <div class="barb_paginacion_botones">
                        <button type="button" id="barb_pag_anterior">Anterior</button>
                        <span id="barb_pag_actual">1</span>
                        <button type="button" id="barb_pag_siguiente">Siguiente</button>
                    </div>
                </div>

            </div>

        </section>

    </div>

    <!-- ==================== MODAL: NUEVO BARBERO ==================== -->
    <div id="modal_barbero" class="modal">
        <div class="modal-contenido_serv">
            <span class="cerrar_modal" id="cerrar_modal_barbero">&times;</span>

            <div class="categoria_header">
                <div class="title_servicios">
                    <h2>Nuevo barbero</h2>
                    <p>Completá los datos del barbero.</p>
                </div>
            </div>

            <form action="guardar_barbero.php" method="POST" id="form_barbero" enctype="multipart/form-data">
                <div id="barb_caja_form">

                    <p>Nombre</p>
                    <div class="fila_doble">
                        <div>
                            <input type="text" name="nombre" class="barb_input" placeholder="Ej: Carlos" required maxlength="100">
                        </div>
                        <div>
                            <input type="text" name="apellido" class="barb_input" placeholder="Ej: Gómez (apellido)" required maxlength="100">
                        </div>
                    </div>

                    <p>Especialidad (opcional)</p>
                    <input type="text" name="especialidad" class="barb_input" list="barb_especialidades_sugeridas" maxlength="50" placeholder="Máximo 50 caracteres" autocomplete="off">

                    <p>En Netro desde (año en que entró a la barbería)</p>
                    <input type="number" name="en_netro_desde" class="barb_input" placeholder="Ej: 2022" min="1950" max="<?php echo date('Y'); ?>">

                    <p>Año en que empezó como barbero</p>
                    <input type="number" name="anio_inicio" class="barb_input" placeholder="Año de 4 dígitos" required min="1950" max="<?php echo date('Y'); ?>">

                    <p>Foto (opcional)</p>
                    <input type="file" name="imagen" id="barb_imagen_input" class="barb_input_file" accept="image/jpeg,image/png,image/webp">
                    <div class="barb_drop_zona" id="barb_drop_zona">
                        <div class="barb_drop_vacio" id="barb_drop_vacio">
                            <span class="barb_drop_icono">🖼️</span>
                            <p class="barb_drop_texto">Arrastrá una imagen acá o <span class="barb_drop_link">elegí un archivo</span></p>
                            <p class="barb_drop_ayuda">JPG, PNG o WEBP · máx. 2 MB</p>
                        </div>
                        <div class="barb_drop_preview" id="barb_drop_preview">
                            <img id="barb_preview_img" src="" alt="Vista previa de la imagen">
                            <button type="button" class="barb_quitar_imagen" id="barb_quitar_imagen" title="Quitar imagen">✕</button>
                        </div>
                    </div>
                    <p class="barb_imagen_error" id="barb_imagen_error"></p>

                    <p>Contraseña</p>
                    <input type="password" name="password" class="barb_input" placeholder="Mínimo 6 caracteres" required minlength="6" maxlength="255">

                    <p>Estado</p>
                    <select name="estado" class="barb_select">
                        <option value="Disponible">Disponible</option>
                        <option value="No disponible">No disponible</option>
                    </select>

                    <div class="botones_modal_cli">
                        <button type="submit" class="btn_guardar_cli">
                            <img src="../img/tick.png" alt="">
                            Guardar
                        </button>
                        <button type="button" class="btn_cancelar_cli" id="cancelar_barbero">
                            <img src="../img/cruzroja.png" alt="">
                            Cancelar
                        </button>
                    </div>

                </div>
            </form>
        </div>
    </div>

    <!-- ==================== MODAL: EDITAR BARBERO ==================== -->
    <div id="modal_editar_barbero" class="modal">
        <div class="modal-contenido_serv">
            <span class="cerrar_modal" id="cerrar_modal_editar_barbero">&times;</span>

            <div class="categoria_header">
                <div class="title_servicios">
                    <h2>Editar barbero</h2>
                    <p>Modificá los datos del barbero.</p>
                </div>
            </div>

            <form action="actualizar_barbero.php" method="POST" id="form_editar_barbero" enctype="multipart/form-data">

                <input type="hidden" name="id_barbero" id="editar_barb_id">

                <div id="barb_caja_form">

                    <p>Nombre</p>
                    <div class="fila_doble">
                        <div>
                            <input type="text" name="nombre" id="editar_barb_nombre" class="barb_input" required maxlength="100">
                        </div>
                        <div>
                            <input type="text" name="apellido" id="editar_barb_apellido" class="barb_input" required maxlength="100">
                        </div>
                    </div>

                    <p>Especialidad (opcional)</p>
                    <input type="text" name="especialidad" id="editar_barb_especialidad" class="barb_input" list="barb_especialidades_sugeridas" maxlength="50" placeholder="Máximo 50 caracteres" autocomplete="off">

                    <p>En Netro desde (año en que entró a la barbería)</p>
                    <input type="number" name="en_netro_desde" id="editar_barb_en_netro_desde" class="barb_input" min="1950" max="<?php echo date('Y'); ?>">

                    <p>Año en que empezó como barbero</p>
                    <input type="number" name="anio_inicio" id="editar_barb_anio_inicio" class="barb_input" placeholder="Año de 4 dígitos" required min="1950" max="<?php echo date('Y'); ?>">

                    <p>Foto (opcional)</p>
                    <input type="file" name="imagen" id="editar_barb_imagen_input" class="barb_input_file" accept="image/jpeg,image/png,image/webp">
                    <div class="barb_drop_zona" id="editar_barb_drop_zona">
                        <div class="barb_drop_vacio" id="editar_barb_drop_vacio">
                            <span class="barb_drop_icono">🖼️</span>
                            <p class="barb_drop_texto">Arrastrá una imagen acá o <span class="barb_drop_link">elegí un archivo</span></p>
                            <p class="barb_drop_ayuda">JPG, PNG o WEBP · máx. 2 MB</p>
                        </div>
                        <div class="barb_drop_preview" id="editar_barb_drop_preview">
                            <img id="editar_barb_preview_img" src="" alt="Vista previa de la imagen">
                            <button type="button" class="barb_quitar_imagen" id="editar_barb_quitar_imagen" title="Quitar imagen">✕</button>
                        </div>
                    </div>
                    <p class="barb_imagen_error" id="editar_barb_imagen_error"></p>

                    <p>Contraseña</p>
                    <input type="password" name="password" class="barb_input" placeholder="Dejar en blanco para mantener la actual" minlength="6" maxlength="255">

                    <p>Estado</p>
                    <select name="estado" id="editar_barb_estado" class="barb_select" required>
                        <option value="Disponible">Disponible</option>
                        <option value="No disponible">No disponible</option>
                    </select>

                    <div class="botones_modal_cli">
                        <button type="submit" class="btn_guardar_cli">
                            <img src="../img/tick.png" alt="">
                            Guardar
                        </button>
                        <button type="button" class="btn_cancelar_cli" id="cancelar_editar_barbero">
                            <img src="../img/cruzroja.png" alt="">
                            Cancelar
                        </button>
                    </div>

                </div>
            </form>
        </div>
    </div>

    <!-- ==================== MODAL: VER BARBERO ==================== -->
    <div id="modal_ver_barbero" class="modal">
        <div class="modal-contenido_ver">
            <span class="cerrar_modal" id="cerrar_modal_ver_barbero">&times;</span>

            <div class="ver_cliente_titulo">
                <h2>Detalle del barbero</h2>
                <p>Información completa del barbero seleccionado.</p>
            </div>

            <div id="ver_barbero_body">

                <div class="ver_fila">
                    <span class="ver_label">ID</span>
                    <span class="ver_valor" id="ver_barb_id"></span>
                </div>

                <div class="ver_fila">
                    <span class="ver_label">Nombre</span>
                    <span class="ver_valor" id="ver_barb_nombre"></span>
                </div>

                <div class="ver_fila">
                    <span class="ver_label">Apellido</span>
                    <span class="ver_valor" id="ver_barb_apellido"></span>
                </div>

                <div class="ver_fila">
                    <span class="ver_label">Especialidad</span>
                    <span class="ver_valor" id="ver_barb_especialidad"></span>
                </div>

                <div class="ver_fila">
                    <span class="ver_label">En Netro desde</span>
                    <span class="ver_valor" id="ver_barb_en_netro_desde"></span>
                </div>

                <div class="ver_fila">
                    <span class="ver_label">Empezó como barbero</span>
                    <span class="ver_valor" id="ver_barb_anio_inicio"></span>
                </div>

                <div class="ver_fila">
                    <span class="ver_label">Estado</span>
                    <span class="ver_valor" id="ver_barb_estado"></span>
                </div>

            </div>

            <div class="ver_cliente_footer">
                <button type="button" class="btn_cancelar_cli" id="cerrar_ver_barbero_btn">Cerrar</button>
            </div>

        </div>
    </div>

    <!-- Sugerencias del campo "Especialidad": solo valores ya cargados en barbero.especialidad -->
    <datalist id="barb_especialidades_sugeridas">
        <?php foreach ($especialidades_sugeridas as $especialidad_sugerida) { ?>
            <option value="<?php echo htmlspecialchars($especialidad_sugerida, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?>"></option>
        <?php } ?>
    </datalist>

    <!-- Datos de barberos (NUNCA incluye la contraseña) -->
    <script id="datos_barberos" type="application/json"><?php echo $json_barberos; ?></script>

    <script src="barberos.js"></script>

</body>
</html>
