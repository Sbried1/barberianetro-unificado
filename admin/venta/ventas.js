(function () {
    "use strict";

    // =====================================================
    // La tabla "venta" no tiene metodo_pago ni monto_recibido, por eso no
    // se maneja forma de pago ni vuelto. El selector de reservas envía
    // id_reserva: con una reserva elegida, cliente, barbero y servicios
    // quedan fijos (son los de la reserva) y el servidor vuelve a
    // validarlos. Con "Venta directa" id_reserva viaja vacío (NULL).
    // id_cliente es un entero (INT) y es obligatorio.
    // =====================================================

    // =====================================================
    // DATOS REALES (ya vienen resueltos con JOIN desde ventas.php)
    // =====================================================
    function leerJSON(id) {
        const el = document.getElementById(id);
        if (!el) return [];
        try {
            return JSON.parse(el.textContent || "[]");
        } catch (e) {
            return [];
        }
    }

    const clientes = leerJSON("datos_clientes");
    const servicios = leerJSON("datos_servicios");
    const reservasDisponibles = leerJSON("datos_reservas_disponibles");
    const ventas = leerJSON("datos_ventas");

    // =====================================================
    // ELEMENTOS DEL FORMULARIO "REGISTRAR VENTA"
    // =====================================================
    const form = document.getElementById("vta_form");
    const inputIdClienteHidden = document.getElementById("vta_id_cliente_hidden");
    const inputFechaHidden = document.getElementById("vta_fecha_hidden");

    const selectorReserva = document.getElementById("vta_selector_reserva");
    const ayudaReserva = document.getElementById("vta_ayuda_reserva");
    const TEXTO_AYUDA_DIRECTA = "Sin reserva: la venta se registra como venta directa.";

    const buscarCliente = document.getElementById("vta_buscar_cliente");
    const clienteResultados = document.getElementById("vta_cliente_resultados");
    const clienteSeleccionadoBox = document.getElementById("vta_cliente_seleccionado");
    const clienteNombreChip = document.getElementById("vta_cliente_nombre_chip");
    const btnQuitarCliente = document.getElementById("vta_quitar_cliente");

    const selectBarbero = document.getElementById("vta_select_barbero");

    const buscarServicio = document.getElementById("vta_buscar_servicio");
    const listaServiciosDisponibles = document.getElementById("vta_servicios_disponibles");

    const carritoVacio = document.getElementById("vta_carrito_vacio");
    const carritoLista = document.getElementById("vta_carrito_lista");
    const carritoContador = document.getElementById("vta_carrito_contador");
    const spanSubtotal = document.getElementById("vta_subtotal");
    const spanTotal = document.getElementById("vta_total");

    const btnConfirmar = document.getElementById("vta_btn_confirmar");
    const btnLimpiar = document.getElementById("vta_btn_limpiar");

    // =====================================================
    // ELEMENTOS DE "VENTAS RECIENTES"
    // =====================================================
    const filtroBuscar = document.getElementById("vta_filtro_buscar");
    const filtroFecha = document.getElementById("vta_filtro_fecha");
    const filtroBarbero = document.getElementById("vta_filtro_barbero");

    const listaBody = document.getElementById("vta_lista_body");
    const cabeceraLista = document.getElementById("vta_cabecera_lista");
    const vacioBox = document.getElementById("vta_vacio");
    const paginacionBox = document.getElementById("vta_paginacion");
    const pagTexto = document.getElementById("vta_pag_texto");
    const pagActual = document.getElementById("vta_pag_actual");
    const pagAnterior = document.getElementById("vta_pag_anterior");
    const pagSiguiente = document.getElementById("vta_pag_siguiente");

    // =====================================================
    // MODALES
    // =====================================================
    const modalVer = document.getElementById("modal_ver_venta");
    const cerrarModalVer = document.getElementById("cerrar_modal_ver_venta");
    const cerrarVerBtn = document.getElementById("cerrar_ver_venta_btn");

    const modalEditar = document.getElementById("modal_editar_venta");
    const cerrarModalEditar = document.getElementById("cerrar_modal_editar_venta");
    const cancelarEditarBtn = document.getElementById("cancelar_editar_venta");
    const inputEditarId = document.getElementById("editar_vta_id");
    const selectEditarBarbero = document.getElementById("editar_vta_barbero");
    const inputEditarFecha = document.getElementById("editar_vta_fecha");

    // =====================================================
    // ESTADO EN MEMORIA
    // =====================================================
    let carrito = []; // [{id_servicio, nombre, precio}]
    let clienteSeleccionado = null; // {id_cliente, nombre}
    let reservaSeleccionada = null; // reserva de reservasDisponibles, o null en venta directa
    let enviandoVenta = false;      // true mientras se envía el formulario de venta
    let enviandoEdicion = false;    // true mientras se envía el formulario de edición

    let paginaActual = 1;
    const porPagina = 8;

    // =====================================================
    // HELPERS
    // =====================================================
    function formatoMoneda(valor) {
        const numero = Number(valor) || 0;
        return "$" + numero.toLocaleString("es-UY", { maximumFractionDigits: 0 });
    }

    function formatoFechaCorta(fechaISO) {
        if (!fechaISO) return "";
        const partes = fechaISO.split("-");
        if (partes.length !== 3) return fechaISO;
        return partes[2] + "/" + partes[1] + "/" + partes[0];
    }

    function calcularTotalCarrito() {
        return carrito.reduce(function (acc, item) { return acc + Number(item.precio); }, 0);
    }

    function fechaLocalHoy() {
        const hoy = new Date();
        return hoy.getFullYear() + "-" +
            String(hoy.getMonth() + 1).padStart(2, "0") + "-" +
            String(hoy.getDate()).padStart(2, "0");
    }

    // =====================================================
    // CARRITO ("Venta actual")
    // Cada item del carrito se va a guardar como una FILA DE VENTA
    // independiente (la tabla real solo admite un id_servicio por fila).
    // =====================================================
    function renderCarrito() {
        const total = calcularTotalCarrito();

        if (carrito.length === 0) {
            carritoVacio.style.display = "flex";
            carritoLista.style.display = "none";
        } else {
            carritoVacio.style.display = "none";
            carritoLista.style.display = "flex";
        }

        carritoLista.innerHTML = "";

        carrito.forEach(function (item) {
            const fila = document.createElement("div");
            fila.className = "vta_carrito_item";

            const nombre = document.createElement("span");
            nombre.className = "vta_carrito_item_nombre";
            nombre.textContent = item.nombre;

            const derecha = document.createElement("div");
            derecha.className = "vta_carrito_item_derecha";

            const precio = document.createElement("span");
            precio.className = "vta_carrito_item_precio";
            precio.textContent = formatoMoneda(item.precio);
            derecha.appendChild(precio);

            if (!reservaSeleccionada) {
                const quitar = document.createElement("button");
                quitar.type = "button";
                quitar.className = "vta_carrito_item_quitar";
                quitar.title = "Quitar";
                quitar.textContent = "×";
                quitar.addEventListener("click", function () { quitarServicioDelCarrito(item.id_servicio); });
                derecha.appendChild(quitar);
            }

            fila.appendChild(nombre);
            fila.appendChild(derecha);
            carritoLista.appendChild(fila);
        });

        carritoContador.textContent = carrito.length + (carrito.length === 1 ? " item" : " items");
        spanSubtotal.textContent = formatoMoneda(total);
        spanTotal.textContent = formatoMoneda(total);

        renderServiciosDisponibles(buscarServicio ? buscarServicio.value : "");
    }

    function agregarServicioAlCarrito(idServicio) {
        if (reservaSeleccionada) return;
        const yaEsta = carrito.some(function (i) { return i.id_servicio === idServicio; });
        if (yaEsta) return;

        const servicio = servicios.find(function (s) { return s.id_servicio === idServicio; });
        if (!servicio || servicio.precio_valido !== true) return;

        carrito.push({ id_servicio: servicio.id_servicio, nombre: servicio.nombre, precio: servicio.precio });
        renderCarrito();
    }

    function quitarServicioDelCarrito(idServicio) {
        if (reservaSeleccionada) return;
        carrito = carrito.filter(function (i) { return i.id_servicio !== idServicio; });
        renderCarrito();
    }

    // =====================================================
    // LISTA DE SERVICIOS DISPONIBLES (buscador + botón "+")
    // =====================================================
    function renderServiciosDisponibles(filtroTexto) {
        if (!listaServiciosDisponibles) return;

        const texto = (filtroTexto || "").trim().toLowerCase();
        const filtrados = servicios.filter(function (s) {
            return s.nombre.toLowerCase().indexOf(texto) !== -1;
        });

        listaServiciosDisponibles.innerHTML = "";

        if (filtrados.length === 0) {
            const vacio = document.createElement("div");
            vacio.className = "vta_lista_servicios_vacio";
            vacio.textContent = "No se encontraron servicios.";
            listaServiciosDisponibles.appendChild(vacio);
            return;
        }

        filtrados.forEach(function (s) {
            const yaAgregado = carrito.some(function (i) { return i.id_servicio === s.id_servicio; });
            // servicio.precio es VARCHAR en la base: ventas.php marca si es un entero válido.
            const precioValido = s.precio_valido === true;

            const fila = document.createElement("div");
            fila.className = "vta_servicio_fila";

            const info = document.createElement("div");
            info.className = "vta_servicio_info";

            const nombre = document.createElement("span");
            nombre.className = "vta_servicio_nombre";
            nombre.textContent = s.nombre;

            const precioDuracion = document.createElement("span");
            precioDuracion.className = "vta_servicio_precio";
            precioDuracion.textContent = (precioValido ? formatoMoneda(s.precio) : "Precio no válido") + (s.duracion ? " · " + s.duracion : "");

            info.appendChild(nombre);
            info.appendChild(precioDuracion);

            const btn = document.createElement("button");
            btn.type = "button";
            btn.className = "vta_servicio_agregar" + (yaAgregado ? " agregado" : "");
            btn.textContent = yaAgregado ? "✓" : "+";
            btn.title = yaAgregado ? "Ya agregado" : "Agregar";
            if (!precioValido) {
                btn.textContent = "—";
                btn.title = "Este servicio no tiene un precio entero válido: corregilo en Servicios";
                btn.disabled = true;
                btn.style.opacity = "0.45";
                btn.style.cursor = "not-allowed";
            } else if (reservaSeleccionada) {
                btn.title = "Los servicios son los de la reserva: elegí Venta directa para cambiarlos";
                btn.disabled = true;
                btn.style.opacity = "0.45";
                btn.style.cursor = "not-allowed";
            }
            btn.addEventListener("click", function () { agregarServicioAlCarrito(s.id_servicio); });

            fila.appendChild(info);
            fila.appendChild(btn);
            listaServiciosDisponibles.appendChild(fila);
        });
    }

    if (buscarServicio) {
        buscarServicio.addEventListener("input", function () {
            renderServiciosDisponibles(buscarServicio.value);
        });
    }

    // =====================================================
    // BUSCAR / SELECCIONAR CLIENTE
    // =====================================================
    function renderClienteResultados(texto) {
        const t = texto.trim().toLowerCase();

        if (t === "") {
            clienteResultados.classList.remove("activo");
            clienteResultados.innerHTML = "";
            return;
        }

        const filtrados = clientes.filter(function (c) {
            const email = (c.email || "").toLowerCase();
            return c.nombre.toLowerCase().indexOf(t) !== -1 ||
                email.indexOf(t) !== -1;
        }).slice(0, 8);

        clienteResultados.innerHTML = "";

        if (filtrados.length === 0) {
            const vacio = document.createElement("div");
            vacio.className = "vta_autocomplete_vacio";
            vacio.textContent = "No se encontraron clientes.";
            clienteResultados.appendChild(vacio);
        } else {
            filtrados.forEach(function (c) {
                const item = document.createElement("div");
                item.className = "vta_autocomplete_item";

                const nombre = document.createElement("strong");
                nombre.textContent = c.nombre;

                const sub = document.createElement("span");
                sub.textContent = c.email || "";

                item.appendChild(nombre);
                item.appendChild(sub);
                item.addEventListener("click", function () { seleccionarCliente(c); });
                clienteResultados.appendChild(item);
            });
        }

        clienteResultados.classList.add("activo");
    }

    function seleccionarCliente(cliente) {
        clienteSeleccionado = cliente;
        inputIdClienteHidden.value = cliente.id_cliente;
        clienteNombreChip.textContent = cliente.nombre;
        clienteSeleccionadoBox.style.display = "inline-flex";
        buscarCliente.value = "";
        clienteResultados.classList.remove("activo");
        clienteResultados.innerHTML = "";
    }

    function quitarClienteSeleccionado() {
        clienteSeleccionado = null;
        inputIdClienteHidden.value = "";
        clienteSeleccionadoBox.style.display = "none";
    }

    if (buscarCliente) {
        buscarCliente.addEventListener("input", function () {
            renderClienteResultados(buscarCliente.value);
        });

        document.addEventListener("click", function (e) {
            if (!buscarCliente.contains(e.target) && !clienteResultados.contains(e.target)) {
                clienteResultados.classList.remove("activo");
            }
        });
    }

    if (btnQuitarCliente) {
        btnQuitarCliente.addEventListener("click", quitarClienteSeleccionado);
    }

    // =====================================================
    // VENTA DESDE UNA RESERVA
    // Cliente, barbero y servicios se copian de la reserva y quedan fijos
    // mientras esté seleccionada: así lo que se ve es lo que se guarda.
    // El barbero de la reserva puede estar "No disponible" y no figurar en
    // el select: en ese caso se agrega una opción temporal.
    // =====================================================
    function quitarOpcionBarberoTemporal() {
        const temporal = selectBarbero.querySelector("option[data-temporal]");
        if (temporal) temporal.remove();
    }

    function aplicarBloqueoReserva() {
        const bloqueado = reservaSeleccionada !== null;

        selectBarbero.disabled = bloqueado;
        buscarCliente.disabled = bloqueado;
        buscarServicio.disabled = bloqueado;
        if (btnQuitarCliente) btnQuitarCliente.style.display = bloqueado ? "none" : "";

        if (ayudaReserva) {
            ayudaReserva.textContent = bloqueado
                ? "Venta vinculada a la reserva #" + reservaSeleccionada.id_reserva + ". Al registrarla, la reserva pasa a Completada. Para cambiar los datos, elegí Venta directa."
                : TEXTO_AYUDA_DIRECTA;
        }
    }

    function vaciarDatosVenta() {
        reservaSeleccionada = null;
        quitarClienteSeleccionado();
        quitarOpcionBarberoTemporal();
        selectBarbero.value = "";
        carrito = [];
        buscarServicio.value = "";
        aplicarBloqueoReserva();
        renderServiciosDisponibles("");
        renderCarrito();
    }

    function prellenarDesdeReserva(reserva) {

        const idsVistos = {};
        const serviciosUnicos = reserva.servicios.filter(function (s) {
            if (idsVistos[s.id_servicio]) return false;
            idsVistos[s.id_servicio] = true;
            return true;
        });

        const conPrecioInvalido = serviciosUnicos.some(function (s) { return s.precio_valido !== true; });

        if (serviciosUnicos.length === 0 || conPrecioInvalido || !reserva.id_cliente) {
            selectorReserva.value = "";
            vaciarDatosVenta();
            PanelUI.avisar(serviciosUnicos.length === 0
                ? "La reserva #" + reserva.id_reserva + " no tiene servicios asociados: no se puede vender desde la reserva."
                : "La reserva #" + reserva.id_reserva + " tiene servicios sin un precio entero válido. Corregí el precio en Servicios antes de venderla.");
            return;
        }

        vaciarDatosVenta();
        reservaSeleccionada = reserva;

        seleccionarCliente({ id_cliente: reserva.id_cliente, nombre: reserva.cliente, email: "" });

        const idBarbero = String(reserva.id_barbero);
        const existeOpcion = Array.from(selectBarbero.options).some(function (o) { return o.value === idBarbero; });
        if (!existeOpcion) {
            const opcion = document.createElement("option");
            opcion.value = idBarbero;
            opcion.textContent = reserva.barbero;
            opcion.dataset.temporal = "1";
            selectBarbero.appendChild(opcion);
        }
        selectBarbero.value = idBarbero;

        carrito = serviciosUnicos.map(function (s) {
            return { id_servicio: s.id_servicio, nombre: s.nombre, precio: s.precio };
        });

        aplicarBloqueoReserva();
        renderCarrito();
    }

    if (selectorReserva) {
        selectorReserva.addEventListener("change", function () {
            const idReserva = parseInt(selectorReserva.value, 10);
            const reserva = idReserva
                ? reservasDisponibles.find(function (r) { return r.id_reserva === idReserva; })
                : null;

            if (!reserva) {
                selectorReserva.value = "";
                vaciarDatosVenta();
                return;
            }

            prellenarDesdeReserva(reserva);
        });
    }

    // =====================================================
    // LIMPIAR FORMULARIO
    // =====================================================
    function limpiarFormulario() {
        selectorReserva.value = "";
        vaciarDatosVenta();
    }

    if (btnLimpiar) {
        btnLimpiar.addEventListener("click", limpiarFormulario);
    }

    // =====================================================
    // ENVIAR FORMULARIO (armar servicios[] como inputs ocultos)
    // =====================================================
    if (form) {
        form.addEventListener("submit", function (e) {

            // Evita el doble envío (doble clic) mientras la primera petición está en curso.
            if (enviandoVenta) {
                e.preventDefault();
                return;
            }

            if (!inputIdClienteHidden.value) {
                e.preventDefault();
                PanelUI.avisar("Seleccioná un cliente antes de registrar la venta (es obligatorio).");
                return;
            }

            if (!selectBarbero.value) {
                e.preventDefault();
                PanelUI.avisar("Seleccioná un barbero antes de registrar la venta.");
                return;
            }

            if (carrito.length === 0) {
                e.preventDefault();
                PanelUI.avisar("Agregá al menos un servicio antes de registrar la venta.");
                return;
            }

            if (reservaSeleccionada && selectorReserva.value !== String(reservaSeleccionada.id_reserva)) {
                e.preventDefault();
                PanelUI.avisar("La reserva seleccionada cambió. Volvé a elegirla antes de registrar la venta.");
                return;
            }

            if (!reservaSeleccionada) {
                selectorReserva.value = "";
            }

            inputFechaHidden.value = fechaLocalHoy();

            // Limpiar inputs ocultos de servicios anteriores (si el usuario reenvía)
            const previos = form.querySelectorAll("input[name='servicios[]']");
            previos.forEach(function (input) { input.remove(); });

            carrito.forEach(function (item) {
                const input = document.createElement("input");
                input.type = "hidden";
                input.name = "servicios[]";
                input.value = item.id_servicio;
                form.appendChild(input);
            });

            // Un select deshabilitado no se envía: se habilita justo antes de que
            // el navegador arme los datos del formulario.
            selectBarbero.disabled = false;

            // Desde acá el envío sigue su curso: se bloquea el botón para que un
            // segundo clic no genere otra venta.
            enviandoVenta = true;
            if (btnConfirmar) btnConfirmar.disabled = true;
        });
    }

    // =====================================================
    // VENTAS RECIENTES — FILTROS + RENDER + PAGINACION
    // Cada fila de "ventas" ya representa un servicio vendido (una fila
    // de la tabla real venta).
    // =====================================================
    function obtenerVentasFiltradas() {
        const texto = filtroBuscar ? filtroBuscar.value.trim().toLowerCase() : "";
        const fecha = filtroFecha ? filtroFecha.value : "";
        const idBarbero = filtroBarbero ? filtroBarbero.value : "";

        return ventas.filter(function (v) {
            const coincideTexto = texto === "" ||
                v.cliente.toLowerCase().indexOf(texto) !== -1 ||
                v.barbero.toLowerCase().indexOf(texto) !== -1 ||
                (v.servicio || "").toLowerCase().indexOf(texto) !== -1;

            const coincideFecha = fecha === "" || v.fecha === fecha;
            const coincideBarbero = idBarbero === "" || String(v.id_barbero) === idBarbero;

            return coincideTexto && coincideFecha && coincideBarbero;
        });
    }

    function crearBotonAccion(clase, icono, titulo, onClick) {
        const btn = document.createElement("button");
        btn.type = "button";
        btn.className = clase;
        btn.title = titulo;
        btn.textContent = icono;
        btn.addEventListener("click", onClick);
        return btn;
    }

    function renderizarLista() {
        const filtradas = obtenerVentasFiltradas();
        const total = filtradas.length;
        const totalPaginas = Math.max(1, Math.ceil(total / porPagina));

        if (paginaActual > totalPaginas) paginaActual = totalPaginas;
        if (paginaActual < 1) paginaActual = 1;

        listaBody.innerHTML = "";

        if (total === 0) {
            cabeceraLista.style.display = "none";
            vacioBox.style.display = "flex";
            paginacionBox.style.display = "none";
            return;
        }

        cabeceraLista.style.display = "grid";
        vacioBox.style.display = "none";
        paginacionBox.style.display = "flex";

        const inicio = (paginaActual - 1) * porPagina;
        const pagina = filtradas.slice(inicio, inicio + porPagina);

        pagina.forEach(function (venta) {
            const fila = document.createElement("div");
            fila.className = "vta_fila";

            const fechaCol = document.createElement("p");
            fechaCol.className = "vta_fila_fecha";
            fechaCol.textContent = formatoFechaCorta(venta.fecha);

            const cliente = document.createElement("p");
            cliente.className = "vta_fila_cliente_nombre";
            cliente.textContent = venta.cliente;

            const servicioCol = document.createElement("p");
            servicioCol.className = "vta_fila_servicio";
            servicioCol.textContent = venta.servicio || "—";

            const barberoCol = document.createElement("p");
            barberoCol.className = "vta_fila_barbero";
            barberoCol.textContent = venta.barbero;

            const totalCol = document.createElement("p");
            totalCol.className = "vta_fila_total";
            totalCol.textContent = formatoMoneda(venta.total);

            const acciones = document.createElement("div");
            acciones.className = "vta_acciones";
            acciones.appendChild(crearBotonAccion("btn_ver_venta", "👁", "Ver", function () { abrirVerVenta(venta); }));
            acciones.appendChild(crearBotonAccion("btn_editar_venta", "✎", "Editar", function () { abrirEditarVenta(venta); }));
            acciones.appendChild(crearBotonAccion("btn_eliminar_venta", "🗑", "Eliminar", function () { eliminarVenta(venta); }));

            fila.appendChild(fechaCol);
            fila.appendChild(cliente);
            fila.appendChild(servicioCol);
            fila.appendChild(barberoCol);
            fila.appendChild(totalCol);
            fila.appendChild(acciones);

            listaBody.appendChild(fila);
        });

        pagTexto.textContent = "Mostrando " + pagina.length + " de " + total + " venta" + (total === 1 ? "" : "s");
        pagActual.textContent = paginaActual;
        pagAnterior.disabled = paginaActual <= 1;
        pagSiguiente.disabled = paginaActual >= totalPaginas;
    }

    [filtroBuscar, filtroFecha, filtroBarbero].forEach(function (el) {
        if (!el) return;
        const evento = el.tagName === "SELECT" || el.type === "date" ? "change" : "input";
        el.addEventListener(evento, function () {
            paginaActual = 1;
            renderizarLista();
        });
    });

    if (pagAnterior) {
        pagAnterior.addEventListener("click", function () {
            if (paginaActual > 1) {
                paginaActual--;
                renderizarLista();
            }
        });
    }

    if (pagSiguiente) {
        pagSiguiente.addEventListener("click", function () {
            paginaActual++;
            renderizarLista();
        });
    }

    // =====================================================
    // MODAL: VER VENTA
    // =====================================================
    function abrirVerVenta(venta) {
        document.getElementById("ver_vta_id").textContent = "#V-" + String(venta.id_venta).padStart(6, "0");
        document.getElementById("ver_vta_cliente").textContent = venta.cliente;
        document.getElementById("ver_vta_barbero").textContent = venta.barbero;
        document.getElementById("ver_vta_servicios").textContent = venta.servicio || "—";
        document.getElementById("ver_vta_total").textContent = formatoMoneda(venta.total);
        document.getElementById("ver_vta_fecha").textContent = formatoFechaCorta(venta.fecha);
        document.getElementById("ver_vta_origen").textContent = venta.id_reserva ? "Reserva #" + venta.id_reserva : "Venta directa";

        modalVer.style.display = "flex";
    }

    function cerrarVerVenta() {
        modalVer.style.display = "none";
    }

    if (cerrarModalVer) cerrarModalVer.addEventListener("click", cerrarVerVenta);
    if (cerrarVerBtn) cerrarVerBtn.addEventListener("click", cerrarVerVenta);

    // =====================================================
    // MODAL: EDITAR VENTA (solo barbero y fecha; ver actualizar_venta.php)
    // =====================================================
    const avisoEditarReserva = document.getElementById("editar_vta_aviso_reserva");

    function abrirEditarVenta(venta) {
        inputEditarId.value = venta.id_venta;
        selectEditarBarbero.value = venta.id_barbero;
        selectEditarBarbero.disabled = !!venta.id_reserva;
        if (avisoEditarReserva) avisoEditarReserva.style.display = venta.id_reserva ? "" : "none";
        inputEditarFecha.value = venta.fecha;
        modalEditar.style.display = "flex";
    }

    function cerrarEditarVenta() {
        modalEditar.style.display = "none";
    }

    if (cerrarModalEditar) cerrarModalEditar.addEventListener("click", cerrarEditarVenta);
    if (cancelarEditarBtn) cancelarEditarBtn.addEventListener("click", cerrarEditarVenta);

    // =====================================================
    // ELIMINAR VENTA
    // =====================================================
    function eliminarVenta(venta) {
        PanelUI.confirmar({
            titulo: "¿Eliminar la venta?",
            mensaje: "Vas a eliminar la venta #V-" + String(venta.id_venta).padStart(6, "0") + " de " + venta.cliente + ". Esta acción no se puede deshacer.",
            textoConfirmar: "Eliminar",
            textoCargando: "Eliminando...",
            peligro: true,
            alConfirmar: function () {
                PanelUI.enviarPost("eliminar_venta.php", { id_venta: venta.id_venta });
            }
        });
    }

    // =====================================================
    // CERRAR MODALES CON ESCAPE O CLICK AFUERA
    // =====================================================
    window.addEventListener("keydown", function (e) {
        if (e.key === "Escape") {
            cerrarVerVenta();
            cerrarEditarVenta();
        }
    });

    window.addEventListener("click", function (e) {
        if (e.target === modalVer) cerrarVerVenta();
        if (e.target === modalEditar) cerrarEditarVenta();
    });

    // =====================================================
    // EVITAR DOBLE ENVÍO EN EL FORMULARIO DE EDICIÓN
    // =====================================================
    const formEditar = document.getElementById("form_editar_venta");
    const btnGuardarEditar = formEditar ? formEditar.querySelector("button[type='submit']") : null;

    if (formEditar) {
        formEditar.addEventListener("submit", function (e) {
            if (enviandoEdicion) {
                e.preventDefault();
                return;
            }
            selectEditarBarbero.disabled = false;
            enviandoEdicion = true;
            if (btnGuardarEditar) btnGuardarEditar.disabled = true;
        });
    }

    // Si el navegador restaura la página desde su caché al volver con "Atrás",
    // se vuelven a habilitar los botones que quedaron bloqueados.
    window.addEventListener("pageshow", function (e) {
        if (!e.persisted) return;
        enviandoVenta = false;
        enviandoEdicion = false;
        if (btnConfirmar) btnConfirmar.disabled = false;
        if (btnGuardarEditar) btnGuardarEditar.disabled = false;
        aplicarBloqueoReserva();
    });

    // =====================================================
    // LIMPIAR EL MENSAJE (?ok=... / ?error=...) DE LA URL
    // El mensaje ya se dibujó. Se lo saca de la barra de direcciones para que
    // al recargar (F5) no vuelva a aparecer "Venta registrada" como si se
    // hubiera guardado otra vez. No recarga la página ni reenvía nada.
    // =====================================================
    (function limpiarMensajeDeLaUrl() {
        if (!window.history || typeof window.history.replaceState !== "function") return;
        const params = new URLSearchParams(window.location.search);
        if (!params.has("ok") && !params.has("error")) return;
        params.delete("ok");
        params.delete("error");
        const resto = params.toString();
        window.history.replaceState(null, "", window.location.pathname + (resto ? "?" + resto : "") + window.location.hash);
    })();

    // =====================================================
    // INICIO
    // =====================================================
    if (selectorReserva) selectorReserva.value = "";
    aplicarBloqueoReserva();
    renderServiciosDisponibles("");
    renderCarrito();
    renderizarLista();

})();
