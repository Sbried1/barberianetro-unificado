/* =====================================================================
   BARBEROS.JS
   Lógica del módulo Barberos: búsqueda y filtro reales, ordenamiento,
   lista paginada, y manejo de los modales de nuevo/editar/ver barbero.
   La contraseña nunca viaja en los datos embebidos ni se muestra.

   NOTA DE COMPATIBILIDAD: la tabla "barbero" real no tiene columnas
   "telefono", "email" ni "calificacion", ni tabla/columna de
   especialidad por id. Se usan en cambio "especialidad" (texto libre),
   "en_netro_desde", "anio_inicio" (obligatorio) y "estado"
   (ENUM 'Disponible' / 'No disponible').
   ===================================================================== */

(function () {

    // ---------- DATOS BASE (vienen del PHP; nunca incluyen password) ----------
    const datosOriginales = JSON.parse(document.getElementById("datos_barberos").textContent || "[]");

    const ETIQUETAS_ESTADO = { "Disponible": "Disponible", "No disponible": "No disponible" };

    // ---------- ESTADO DE LA UI ----------
    let filtrados = datosOriginales.slice();
    let paginaActual = 1;
    const filasPorPagina = 10;

    // =====================================================
    // UTILIDADES
    // =====================================================
    function iniciales(nombre, apellido) {
        const n = (nombre || "").trim().charAt(0);
        const a = (apellido || "").trim().charAt(0);
        return (n + a).toUpperCase();
    }

    function claseEstado(estado) {
        return estado === "Disponible" ? "disponible" : "no_disponible";
    }

    // =====================================================
    // FILTROS + ORDEN
    // =====================================================
    const inputBuscar = document.getElementById("barb_buscar");
    const selectEstado = document.getElementById("barb_filtro_estado");
    const selectOrden = document.getElementById("barb_orden");
    const btnLimpiar = document.getElementById("barb_limpiar_filtros");

    function aplicarFiltros() {

        const texto = inputBuscar.value.trim().toLowerCase();
        const estado = selectEstado.value;
        const orden = selectOrden.value;

        filtrados = datosOriginales.filter(function (b) {

            const coincideTexto =
                texto === "" ||
                b.nombre.toLowerCase().includes(texto) ||
                b.apellido.toLowerCase().includes(texto) ||
                (b.especialidad || "").toLowerCase().includes(texto);

            const coincideEstado = estado === "" || b.estado === estado;

            return coincideTexto && coincideEstado;
        });

        filtrados.sort(function (a, b) {
            if (orden === "nombre_desc") {
                return (b.nombre + b.apellido).localeCompare(a.nombre + a.apellido);
            }
            // nombre_asc por defecto
            return (a.nombre + a.apellido).localeCompare(b.nombre + b.apellido);
        });

        paginaActual = 1;
        renderizarLista();
    }

    inputBuscar.addEventListener("input", aplicarFiltros);
    selectEstado.addEventListener("change", aplicarFiltros);
    selectOrden.addEventListener("change", aplicarFiltros);

    btnLimpiar.addEventListener("click", function () {
        inputBuscar.value = "";
        selectEstado.value = "";
        selectOrden.value = "nombre_asc";
        aplicarFiltros();
    });

    // =====================================================
    // VISTA LISTA
    // =====================================================
    const cuerpoLista = document.getElementById("lista_barberos_body");
    const listaVacia = document.getElementById("barb_lista_vacia");
    const textoVacioTitulo = document.getElementById("barb_vacio_titulo");
    const textoVacioDescripcion = document.getElementById("barb_vacio_texto");
    const cabeceraLista = document.querySelector(".barb_cabecera_lista");
    const paginacionBox = document.getElementById("barb_paginacion");
    const textoPaginacion = document.getElementById("barb_paginacion_texto");
    const btnPagAnterior = document.getElementById("barb_pag_anterior");
    const btnPagSiguiente = document.getElementById("barb_pag_siguiente");
    const spanPagActual = document.getElementById("barb_pag_actual");
    const btnAbrirBarberoVacio = document.getElementById("btn_abrir_barbero_vacio");

    function crearBotonAccion(clase, iconoTexto, titulo, onClick) {
        const boton = document.createElement("button");
        boton.type = "button";
        boton.className = clase;
        boton.title = titulo;
        boton.textContent = iconoTexto;
        boton.addEventListener("click", onClick);
        return boton;
    }

    function crearFilaLista(barbero) {

        const fila = document.createElement("div");
        fila.className = "barb_fila";

        const colNombre = document.createElement("div");
        colNombre.className = "barb_nombre_col";
        const avatar = document.createElement("div");
        avatar.className = "barb_avatar";
        avatar.textContent = iniciales(barbero.nombre, barbero.apellido);
        const nombreSpan = document.createElement("span");
        nombreSpan.className = "nombre_completo";
        nombreSpan.textContent = `${barbero.nombre} ${barbero.apellido}`;
        colNombre.append(avatar, nombreSpan);

        const colEspecialidad = document.createElement("p");
        colEspecialidad.textContent = barbero.especialidad || "—";

        const colDesde = document.createElement("p");
        colDesde.textContent = barbero.en_netro_desde || "—";

        const colEstado = document.createElement("div");
        const badge = document.createElement("span");
        badge.className = "barb_badge " + claseEstado(barbero.estado);
        badge.textContent = ETIQUETAS_ESTADO[barbero.estado] || barbero.estado;
        colEstado.appendChild(badge);

        const colAcciones = document.createElement("div");
        colAcciones.className = "barb_acciones";
        colAcciones.appendChild(crearBotonAccion("btn_ver_barbero", "👁", "Ver", function () { abrirVerBarbero(barbero); }));
        colAcciones.appendChild(crearBotonAccion("btn_editar_barbero", "✎", "Editar", function () { abrirEditarBarbero(barbero); }));
        colAcciones.appendChild(crearBotonAccion("btn_eliminar_barbero", "🗑", "Eliminar", function () { eliminarBarbero(barbero); }));

        fila.append(colNombre, colEspecialidad, colDesde, colEstado, colAcciones);

        return fila;
    }

    function renderizarLista() {

        cuerpoLista.innerHTML = "";

        const total = filtrados.length;
        const totalPaginas = Math.max(1, Math.ceil(total / filasPorPagina));
        if (paginaActual > totalPaginas) paginaActual = totalPaginas;

        const inicio = (paginaActual - 1) * filasPorPagina;
        const pagina = filtrados.slice(inicio, inicio + filasPorPagina);

        if (total === 0) {
            cabeceraLista.style.display = "none";
            listaVacia.style.display = "flex";
            paginacionBox.style.display = "none";

            const hayFiltrosActivos =
                inputBuscar.value.trim() !== "" || selectEstado.value !== "";

            if (hayFiltrosActivos && datosOriginales.length > 0) {
                textoVacioTitulo.textContent = "No se encontraron barberos";
                textoVacioDescripcion.textContent = "Probá con otros criterios de búsqueda o quitá algún filtro.";
                btnAbrirBarberoVacio.style.display = "none";
            } else {
                textoVacioTitulo.textContent = "No hay barberos registrados";
                textoVacioDescripcion.textContent = "Aún no se han creado barberos.";
                btnAbrirBarberoVacio.style.display = "inline-flex";
            }

            return;
        }

        cabeceraLista.style.display = "grid";
        listaVacia.style.display = "none";
        paginacionBox.style.display = "flex";

        pagina.forEach(function (barbero) {
            cuerpoLista.appendChild(crearFilaLista(barbero));
        });

        textoPaginacion.textContent = `Mostrando ${pagina.length} de ${total} barbero${total === 1 ? "" : "s"}`;
        spanPagActual.textContent = String(paginaActual);
        btnPagAnterior.disabled = paginaActual <= 1;
        btnPagSiguiente.disabled = paginaActual >= totalPaginas;
    }

    btnPagAnterior.addEventListener("click", function () {
        if (paginaActual > 1) { paginaActual--; renderizarLista(); }
    });

    btnPagSiguiente.addEventListener("click", function () {
        const totalPaginas = Math.max(1, Math.ceil(filtrados.length / filasPorPagina));
        if (paginaActual < totalPaginas) { paginaActual++; renderizarLista(); }
    });

    // =====================================================
    // IMAGEN: selección, arrastrar y soltar, y vista previa
    // (compartido entre "nuevo barbero" y "editar barbero")
    // La imagen nunca viaja como URL de texto: es un archivo real que
    // el input[type=file] envía junto con el resto del formulario.
    // =====================================================
    const TIPOS_IMAGEN_PERMITIDOS = ["image/jpeg", "image/png", "image/webp"];
    const TAMANO_MAXIMO_IMAGEN = 2 * 1024 * 1024; // 2 MB

    // Las fotos subidas desde este módulo se guardan como
    // "img/barberos/archivo.ext" (ruta relativa a la raíz del proyecto).
    // Esta página vive en "barberos/", por eso se antepone "../".
    const PREFIJO_IMAGENES_BARBERO = "img/barberos/";

    // Devuelve la URL con la que se puede mostrar la foto guardada, o null
    // si no hay una foto utilizable. Un url_imagen que no sea una ruta
    // "img/barberos/..." (por ejemplo "../resources/images/barbero.jpg",
    // que viene de otro proyecto y acá no existe) NO se intenta cargar: se
    // evita mostrar una imagen rota. El dato guardado en la base no se toca.
    function urlParaMostrarImagen(urlGuardada) {
        if (typeof urlGuardada !== "string") return null;

        const ruta = urlGuardada.trim();

        if (ruta.indexOf(PREFIJO_IMAGENES_BARBERO) !== 0 || ruta.indexOf("..") !== -1) {
            return null;
        }

        return "../" + ruta;
    }

    function crearManejadorImagen(cfg) {

        const input = document.getElementById(cfg.inputId);
        const zona = document.getElementById(cfg.zonaId);
        const vacio = document.getElementById(cfg.vacioId);
        const preview = document.getElementById(cfg.previewId);
        const img = document.getElementById(cfg.imgId);
        const btnQuitar = document.getElementById(cfg.quitarId);
        const elError = document.getElementById(cfg.errorId);

        // Imagen ya guardada en la base (solo aplica al editar). Si el
        // usuario no selecciona una nueva, esta es la que se conserva.
        let urlImagenActual = null;

        function mostrarError(mensaje) {
            elError.textContent = mensaje || "";
            elError.style.display = mensaje ? "block" : "none";
        }

        function mostrarVacio() {
            vacio.style.display = "flex";
            preview.style.display = "none";
            img.src = "";
        }

        function mostrarPreview(src) {
            vacio.style.display = "none";
            preview.style.display = "flex";
            img.src = src;
        }

        function limpiarInput() {
            input.value = "";
        }

        // Vuelve a mostrar la imagen actual (si es edición) o el estado
        // vacío (si es alta), descartando cualquier selección pendiente.
        function actualizarVistaSegunEstadoInicial() {
            const urlMostrar = urlParaMostrarImagen(urlImagenActual);

            if (urlMostrar) {
                mostrarPreview(urlMostrar);
            } else {
                mostrarVacio();
            }
        }

        // Respaldo: si la foto guardada no se puede cargar (por ejemplo, el
        // archivo ya no está en img/barberos/), se vuelve al estado vacío en
        // lugar de dejar una imagen rota. No aplica a las vistas previas de
        // archivos recién elegidos (data:), que siempre cargan.
        img.addEventListener("error", function () {
            if (!img.getAttribute("src") || img.getAttribute("src").indexOf("data:") === 0) return;
            mostrarVacio();
        });

        function validarArchivo(archivo) {
            if (TIPOS_IMAGEN_PERMITIDOS.indexOf(archivo.type) === -1) {
                mostrarError("Formato no permitido. Usá JPG, PNG o WEBP.");
                return false;
            }
            if (archivo.size > TAMANO_MAXIMO_IMAGEN) {
                mostrarError("La imagen supera el tamaño máximo permitido (2 MB).");
                return false;
            }
            return true;
        }

        function manejarArchivoSeleccionado(archivo) {

            mostrarError(null);

            if (!archivo) return;

            if (!validarArchivo(archivo)) {
                limpiarInput();
                actualizarVistaSegunEstadoInicial();
                return;
            }

            const lector = new FileReader();
            lector.onload = function (e) {
                mostrarPreview(e.target.result);
            };
            lector.readAsDataURL(archivo);
        }

        input.addEventListener("change", function () {
            const archivo = input.files && input.files[0] ? input.files[0] : null;
            manejarArchivoSeleccionado(archivo);
        });

        zona.addEventListener("click", function (e) {
            if (e.target === btnQuitar) return;
            input.click();
        });

        zona.addEventListener("dragover", function (e) {
            e.preventDefault();
            zona.classList.add("barb_drop_activo");
        });

        zona.addEventListener("dragleave", function () {
            zona.classList.remove("barb_drop_activo");
        });

        zona.addEventListener("drop", function (e) {
            e.preventDefault();
            zona.classList.remove("barb_drop_activo");

            const archivo = e.dataTransfer.files && e.dataTransfer.files[0] ? e.dataTransfer.files[0] : null;
            if (!archivo) return;

            // Asignamos el archivo soltado al input real para que viaje con el formulario.
            if (typeof DataTransfer !== "undefined") {
                const listaArchivos = new DataTransfer();
                listaArchivos.items.add(archivo);
                input.files = listaArchivos.files;
            }

            manejarArchivoSeleccionado(archivo);
        });

        btnQuitar.addEventListener("click", function (e) {
            e.stopPropagation();
            limpiarInput();
            mostrarError(null);
            actualizarVistaSegunEstadoInicial();
        });

        return {
            // Reinicia el widget. urlImagenExistente es la imagen ya
            // guardada en la base (solo para "editar"); null para "nuevo".
            reiniciar: function (urlImagenExistente) {
                urlImagenActual = urlImagenExistente || null;
                limpiarInput();
                mostrarError(null);
                actualizarVistaSegunEstadoInicial();
            }
        };
    }

    const manejadorImagenNuevo = crearManejadorImagen({
        inputId: "barb_imagen_input",
        zonaId: "barb_drop_zona",
        vacioId: "barb_drop_vacio",
        previewId: "barb_drop_preview",
        imgId: "barb_preview_img",
        quitarId: "barb_quitar_imagen",
        errorId: "barb_imagen_error"
    });

    const manejadorImagenEditar = crearManejadorImagen({
        inputId: "editar_barb_imagen_input",
        zonaId: "editar_barb_drop_zona",
        vacioId: "editar_barb_drop_vacio",
        previewId: "editar_barb_drop_preview",
        imgId: "editar_barb_preview_img",
        quitarId: "editar_barb_quitar_imagen",
        errorId: "editar_barb_imagen_error"
    });

    // =====================================================
    // MODAL: NUEVO BARBERO
    // =====================================================
    const modalBarbero = document.getElementById("modal_barbero");
    const btnAbrirBarbero = document.getElementById("btn_abrir_barbero");
    const cerrarModalBarbero = document.getElementById("cerrar_modal_barbero");
    const cancelarBarbero = document.getElementById("cancelar_barbero");
    const formBarbero = document.getElementById("form_barbero");

    function abrirNuevoBarbero() {
        formBarbero.reset();
        manejadorImagenNuevo.reiniciar(null);
        modalBarbero.style.display = "flex";
    }

    function cerrarNuevoBarbero() {
        modalBarbero.style.display = "none";
        formBarbero.reset();
        manejadorImagenNuevo.reiniciar(null);
    }

    btnAbrirBarbero.addEventListener("click", abrirNuevoBarbero);
    btnAbrirBarberoVacio.addEventListener("click", abrirNuevoBarbero);
    cerrarModalBarbero.addEventListener("click", cerrarNuevoBarbero);
    cancelarBarbero.addEventListener("click", cerrarNuevoBarbero);

    // =====================================================
    // MODAL: EDITAR BARBERO
    // =====================================================
    const modalEditarBarbero = document.getElementById("modal_editar_barbero");
    const cerrarModalEditarBarbero = document.getElementById("cerrar_modal_editar_barbero");
    const cancelarEditarBarbero = document.getElementById("cancelar_editar_barbero");
    const formEditarBarbero = document.getElementById("form_editar_barbero");

    function abrirEditarBarbero(barbero) {

        document.getElementById("editar_barb_id").value = barbero.id_barbero;
        document.getElementById("editar_barb_nombre").value = barbero.nombre;
        document.getElementById("editar_barb_apellido").value = barbero.apellido;
        document.getElementById("editar_barb_especialidad").value = barbero.especialidad || "";
        document.getElementById("editar_barb_en_netro_desde").value = barbero.en_netro_desde || "";
        document.getElementById("editar_barb_anio_inicio").value = barbero.anio_inicio || "";
        document.getElementById("editar_barb_estado").value = barbero.estado;

        // Muestra la imagen actual del barbero (si tiene) en la zona de
        // carga. Si el usuario no selecciona una nueva, se conserva esta.
        manejadorImagenEditar.reiniciar(barbero.url_imagen || null);

        // El campo de contraseña se deja siempre vacío: no viaja al cliente
        // y si el usuario no escribe nada, el backend conserva la actual.
        const inputPassword = formEditarBarbero.querySelector('input[name="password"]');
        if (inputPassword) inputPassword.value = "";

        modalEditarBarbero.style.display = "flex";
    }

    function cerrarEditarBarbero() {
        modalEditarBarbero.style.display = "none";
        manejadorImagenEditar.reiniciar(null);
    }

    cerrarModalEditarBarbero.addEventListener("click", cerrarEditarBarbero);
    cancelarEditarBarbero.addEventListener("click", cerrarEditarBarbero);

    // =====================================================
    // MODAL: VER BARBERO (nunca muestra la contraseña)
    // =====================================================
    const modalVerBarbero = document.getElementById("modal_ver_barbero");
    const cerrarModalVerBarbero = document.getElementById("cerrar_modal_ver_barbero");
    const cerrarVerBarberoBtn = document.getElementById("cerrar_ver_barbero_btn");

    function abrirVerBarbero(barbero) {

        document.getElementById("ver_barb_id").textContent = barbero.id_barbero;
        document.getElementById("ver_barb_nombre").textContent = barbero.nombre;
        document.getElementById("ver_barb_apellido").textContent = barbero.apellido;
        document.getElementById("ver_barb_especialidad").textContent = barbero.especialidad || "—";
        document.getElementById("ver_barb_en_netro_desde").textContent = barbero.en_netro_desde || "—";
        document.getElementById("ver_barb_anio_inicio").textContent = barbero.anio_inicio || "—";

        const estadoSpan = document.getElementById("ver_barb_estado");
        estadoSpan.textContent = ETIQUETAS_ESTADO[barbero.estado] || barbero.estado;
        estadoSpan.className = "ver_valor barb_badge " + claseEstado(barbero.estado);

        modalVerBarbero.style.display = "flex";
    }

    function cerrarVerBarbero() {
        modalVerBarbero.style.display = "none";
    }

    cerrarModalVerBarbero.addEventListener("click", cerrarVerBarbero);
    cerrarVerBarberoBtn.addEventListener("click", cerrarVerBarbero);

    // =====================================================
    // ELIMINAR BARBERO
    // =====================================================
    function eliminarBarbero(barbero) {

        const confirmacion = confirm(
            `¿Querés eliminar a ${barbero.nombre} ${barbero.apellido}? Esta acción no se puede deshacer.`
        );

        if (!confirmacion) return;

        const form = document.createElement("form");
        form.method = "POST";
        form.action = "eliminar_barbero.php";
        form.style.display = "none";

        const input = document.createElement("input");
        input.type = "hidden";
        input.name = "id_barbero";
        input.value = barbero.id_barbero;

        form.appendChild(input);
        document.body.appendChild(form);
        form.submit();
    }

    // =====================================================
    // CERRAR MODALES CLICKEANDO AFUERA
    // =====================================================
    window.addEventListener("click", function (e) {
        if (e.target === modalBarbero) cerrarNuevoBarbero();
        if (e.target === modalEditarBarbero) cerrarEditarBarbero();
        if (e.target === modalVerBarbero) cerrarVerBarbero();
    });

    // =====================================================
    // INICIO
    // =====================================================
    aplicarFiltros();

})();
