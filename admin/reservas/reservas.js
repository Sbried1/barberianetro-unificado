/* =====================================================================
   RESERVAS.JS
   Lógica del módulo Reservas: filtros reales, vista lista con
   paginación, vista calendario (mes/semana/día) construida a partir
   de los datos reales de la base de datos, y manejo de los modales
   de nueva/editar/ver reserva.
   ===================================================================== */

(function () {

    // ---------- DATOS BASE (vienen del PHP, ya con los JOIN resueltos) ----------
    const datosOriginales = JSON.parse(document.getElementById("datos_reservas").textContent || "[]");

    const DIAS_SEMANA = ["Dom", "Lun", "Mar", "Mié", "Jue", "Vie", "Sáb"];
    const DIAS_LARGOS = ["Domingo", "Lunes", "Martes", "Miércoles", "Jueves", "Viernes", "Sábado"];
    const MESES = ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];

    const ETIQUETAS_ESTADO = {
        pendiente: "Pendiente",
        confirmada: "Confirmada",
        completada: "Completada",
        cancelada: "Cancelada"
    };

    // Solo las reservas pendientes o confirmadas se pueden editar, confirmar,
    // cancelar o eliminar; completadas y canceladas son historial.
    function esReservaActiva(reserva) {
        return reserva.estado === "pendiente" || reserva.estado === "confirmada";
    }

    // ---------- ESTADO DE LA UI ----------
    let filtrados = datosOriginales.slice();
    let paginaActual = 1;
    const filasPorPagina = 10;

    let vistaActiva = "lista";
    let modoCalendario = "mes";
    let fechaCalendario = new Date(); // fecha de referencia para mes/semana/día

    // =====================================================
    // UTILIDADES
    // =====================================================
    function fechaLocal(fechaStr, horaStr) {
        // fechaStr = "YYYY-MM-DD", horaStr opcional = "HH:MM"
        const [anio, mes, dia] = fechaStr.split("-").map(Number);
        if (horaStr) {
            const [h, m] = horaStr.split(":").map(Number);
            return new Date(anio, mes - 1, dia, h, m);
        }
        return new Date(anio, mes - 1, dia);
    }

    function aYMD(date) {
        const anio = date.getFullYear();
        const mes = String(date.getMonth() + 1).padStart(2, "0");
        const dia = String(date.getDate()).padStart(2, "0");
        return `${anio}-${mes}-${dia}`;
    }

    function formatoFechaCorta(fechaStr) {
        const [anio, mes, dia] = fechaStr.split("-");
        return `${dia}/${mes}/${anio}`;
    }

    function formatoMoneda(valor) {
        const numero = Number(valor) || 0;
        return "$" + numero.toLocaleString("es-UY", { minimumFractionDigits: 0, maximumFractionDigits: 2 });
    }

    function esMismoDia(a, b) {
        return a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();
    }

    // Formatea minutos totales como "1h 30min", "2h" o "45 min".
    function formatoDuracion(minutos) {
        const total = Math.max(0, Math.round(Number(minutos) || 0));
        const horas = Math.floor(total / 60);
        const mins = total % 60;
        if (horas > 0 && mins > 0) return `${horas}h ${mins}min`;
        if (horas > 0) return `${horas}h`;
        return `${mins} min`;
    }

    // =====================================================
    // FILTROS
    // =====================================================
    const inputBuscar = document.getElementById("resv_buscar");
    const inputDesde = document.getElementById("resv_desde");
    const inputHasta = document.getElementById("resv_hasta");
    const selectBarbero = document.getElementById("resv_filtro_barbero");
    const selectEstado = document.getElementById("resv_filtro_estado");

    function aplicarFiltros() {

        const texto = inputBuscar.value.trim().toLowerCase();
        const desde = inputDesde.value;
        const hasta = inputHasta.value;
        const barbero = selectBarbero.value;
        const estado = selectEstado.value;

        filtrados = datosOriginales.filter(function (r) {

            const coincideTexto =
                texto === "" ||
                r.cliente.toLowerCase().includes(texto) ||
                r.barbero.toLowerCase().includes(texto) ||
                r.servicios_nombres.toLowerCase().includes(texto);

            const coincideDesde = desde === "" || r.fecha >= desde;
            const coincideHasta = hasta === "" || r.fecha <= hasta;
            const coincideBarbero = barbero === "" || String(r.id_barbero) === barbero;
            const coincideEstado =
                estado === "" ||
                (estado === "activas" ? esReservaActiva(r) : r.estado === estado);

            return coincideTexto && coincideDesde && coincideHasta && coincideBarbero && coincideEstado;
        });

        paginaActual = 1;
        renderizarVistaActiva();
    }

    [inputBuscar].forEach(function (el) { el.addEventListener("input", aplicarFiltros); });
    [inputDesde, inputHasta, selectBarbero, selectEstado].forEach(function (el) {
        el.addEventListener("change", aplicarFiltros);
    });

    // =====================================================
    // TABS (Lista / Calendario)
    // =====================================================
    const tabLista = document.getElementById("tab_lista");
    const tabCalendario = document.getElementById("tab_calendario");
    const vistaLista = document.getElementById("vista_lista");
    const vistaCalendario = document.getElementById("vista_calendario");

    function cambiarVista(vista) {
        vistaActiva = vista;

        tabLista.classList.toggle("activo", vista === "lista");
        tabCalendario.classList.toggle("activo", vista === "calendario");
        vistaLista.style.display = vista === "lista" ? "block" : "none";
        vistaCalendario.style.display = vista === "calendario" ? "block" : "none";

        renderizarVistaActiva();
    }

    tabLista.addEventListener("click", function () { cambiarVista("lista"); });
    tabCalendario.addEventListener("click", function () { cambiarVista("calendario"); });

    function renderizarVistaActiva() {
        if (vistaActiva === "lista") {
            renderizarLista();
        } else {
            renderizarCalendario();
        }
    }

    // =====================================================
    // VISTA LISTA
    // =====================================================
    const cuerpoLista = document.getElementById("lista_reservas_body");
    const listaVacia = document.getElementById("resv_lista_vacia");
    const textoVacioTitulo = document.getElementById("resv_vacio_titulo");
    const textoVacioDescripcion = document.getElementById("resv_vacio_texto");
    const cabeceraLista = document.querySelector(".resv_cabecera_lista");
    const paginacionBox = document.getElementById("resv_paginacion");
    const textoPaginacion = document.getElementById("resv_paginacion_texto");
    const btnPagAnterior = document.getElementById("resv_pag_anterior");
    const btnPagSiguiente = document.getElementById("resv_pag_siguiente");
    const spanPagActual = document.getElementById("resv_pag_actual");

    function crearBotonAccion(clase, iconoTexto, titulo, onClick) {
        const boton = document.createElement("button");
        boton.type = "button";
        boton.className = clase;
        boton.title = titulo;
        boton.textContent = iconoTexto;
        boton.addEventListener("click", onClick);
        return boton;
    }

    function crearBadgeEstado(estado) {
        const badge = document.createElement("span");
        badge.className = "resv_badge " + estado;
        badge.textContent = ETIQUETAS_ESTADO[estado] || estado;
        return badge;
    }

    function crearFilaLista(reserva) {

        const fila = document.createElement("div");
        fila.className = "resv_fila";

        const colId = document.createElement("p");
        colId.className = "resv_id";
        colId.textContent = "#" + reserva.id_reserva;

        const colCliente = document.createElement("p");
        colCliente.className = "resv_cliente_nombre";
        colCliente.textContent = reserva.cliente;

        const colServicio = document.createElement("p");
        colServicio.className = "resv_servicio_texto";
        colServicio.textContent = reserva.servicios_nombres || "—";

        const colBarbero = document.createElement("p");
        colBarbero.textContent = reserva.barbero;

        const colFecha = document.createElement("p");
        colFecha.textContent = formatoFechaCorta(reserva.fecha);

        const colHora = document.createElement("p");
        colHora.textContent = reserva.hora;

        const colEstado = document.createElement("div");
        colEstado.appendChild(crearBadgeEstado(reserva.estado));

        const colAcciones = document.createElement("div");
        colAcciones.className = "resv_acciones";

        colAcciones.appendChild(crearBotonAccion("btn_ver_reserva", "👁", "Ver", function () { abrirVerReserva(reserva); }));

        if (reserva.estado === "pendiente") {
            colAcciones.appendChild(crearBotonAccion("btn_confirmar_reserva", "✓", "Confirmar", function () { cambiarEstadoReserva(reserva, "confirmar"); }));
        }

        if (esReservaActiva(reserva)) {
            colAcciones.appendChild(crearBotonAccion("btn_editar_reserva", "✎", "Editar", function () { abrirEditarReserva(reserva); }));
            colAcciones.appendChild(crearBotonAccion("btn_cancelar_reserva", "⊘", "Cancelar reserva", function () { cambiarEstadoReserva(reserva, "cancelar"); }));
            colAcciones.appendChild(crearBotonAccion("btn_eliminar_reserva", "🗑", "Eliminar", function () { eliminarReserva(reserva); }));
        }

        fila.append(colId, colCliente, colServicio, colBarbero, colFecha, colHora, colEstado, colAcciones);

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

            if (datosOriginales.length > 0) {
                textoVacioTitulo.textContent = "No se encontraron reservas";
                textoVacioDescripcion.textContent = "Probá con otros criterios de búsqueda, quitá algún filtro o elegí \"Historial completo\".";
                btnAbrirReservaVacio.style.display = "none";
            } else {
                textoVacioTitulo.textContent = "No hay reservas registradas";
                textoVacioDescripcion.textContent = "Aún no se han creado reservas.";
                btnAbrirReservaVacio.style.display = "inline-flex";
            }

            return;
        }

        cabeceraLista.style.display = "grid";
        listaVacia.style.display = "none";
        paginacionBox.style.display = "flex";

        pagina.forEach(function (reserva) {
            cuerpoLista.appendChild(crearFilaLista(reserva));
        });

        const desde = total === 0 ? 0 : inicio + 1;
        const hasta = Math.min(inicio + filasPorPagina, total);
        textoPaginacion.textContent = `Mostrando ${desde} a ${hasta} de ${total} reserva${total === 1 ? "" : "s"}`;
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
    // VISTA CALENDARIO
    // =====================================================
    const calTitulo = document.getElementById("cal_titulo");
    const calContenedor = document.getElementById("calendario_contenedor");
    const btnCalAnterior = document.getElementById("cal_anterior");
    const btnCalSiguiente = document.getElementById("cal_siguiente");
    const btnCalHoy = document.getElementById("cal_hoy");
    const botonesModo = document.querySelectorAll(".resv_cal_modo");

    function reservasDelDia(date) {
        const ymd = aYMD(date);
        return filtrados
            .filter(function (r) { return r.fecha === ymd; })
            .sort(function (a, b) { return a.hora.localeCompare(b.hora); });
    }

    function crearEventoCalendario(reserva, compacto) {
        const evento = document.createElement("div");
        evento.className = "resv_cal_evento " + reserva.estado;
        evento.title = `${reserva.hora} · ${reserva.cliente} · ${ETIQUETAS_ESTADO[reserva.estado] || reserva.estado}`;

        const punto = document.createElement("i");
        punto.className = "punto " + reserva.estado;

        const texto = document.createElement("span");
        texto.className = "resv_cal_evento_texto";
        texto.textContent = compacto ? `${reserva.hora} ${reserva.cliente}` : `${reserva.hora} · ${reserva.cliente}`;

        evento.append(punto, texto);
        evento.addEventListener("click", function () { abrirVerReserva(reserva); });

        return evento;
    }

    function renderizarCalendario() {
        if (modoCalendario === "mes") {
            renderizarMes();
        } else if (modoCalendario === "semana") {
            renderizarSemana();
        } else {
            renderizarDia();
        }
    }

    function renderizarMes() {

        const anio = fechaCalendario.getFullYear();
        const mes = fechaCalendario.getMonth();

        calTitulo.textContent = `${MESES[mes]} ${anio}`;

        const primerDiaMes = new Date(anio, mes, 1);
        const ultimoDiaMes = new Date(anio, mes + 1, 0);

        const inicioGrilla = new Date(primerDiaMes);
        inicioGrilla.setDate(inicioGrilla.getDate() - inicioGrilla.getDay());

        const finGrilla = new Date(ultimoDiaMes);
        finGrilla.setDate(finGrilla.getDate() + (6 - finGrilla.getDay()));

        calContenedor.innerHTML = "";

        const cabecera = document.createElement("div");
        cabecera.className = "resv_cal_grid_dias";
        DIAS_SEMANA.forEach(function (d) {
            const span = document.createElement("span");
            span.textContent = d.toUpperCase();
            cabecera.appendChild(span);
        });
        calContenedor.appendChild(cabecera);

        const grilla = document.createElement("div");
        grilla.className = "resv_cal_grid_mes";

        const hoy = new Date();
        let cursor = new Date(inicioGrilla);

        while (cursor <= finGrilla) {

            const celda = document.createElement("div");
            celda.className = "resv_cal_celda";
            if (cursor.getMonth() !== mes) celda.classList.add("otro_mes");
            if (esMismoDia(cursor, hoy)) celda.classList.add("hoy");

            const numero = document.createElement("span");
            numero.className = "resv_cal_numero";
            numero.textContent = String(cursor.getDate());
            celda.appendChild(numero);

            const eventosDia = reservasDelDia(cursor);
            const maximoVisible = 3;

            eventosDia.slice(0, maximoVisible).forEach(function (r) {
                celda.appendChild(crearEventoCalendario(r, true));
            });

            if (eventosDia.length > maximoVisible) {
                const fechaCelda = new Date(cursor);
                const mas = document.createElement("span");
                mas.className = "resv_cal_mas";
                mas.textContent = `+${eventosDia.length - maximoVisible} más`;
                mas.addEventListener("click", function () {
                    fechaCalendario = fechaCelda;
                    cambiarModoCalendario("dia");
                });
                celda.appendChild(mas);
            }

            grilla.appendChild(celda);
            cursor.setDate(cursor.getDate() + 1);
        }

        calContenedor.appendChild(grilla);
    }

    function renderizarSemana() {

        const inicioSemana = new Date(fechaCalendario);
        inicioSemana.setDate(inicioSemana.getDate() - inicioSemana.getDay());

        const finSemana = new Date(inicioSemana);
        finSemana.setDate(finSemana.getDate() + 6);

        const mismoMs = inicioSemana.getMonth() === finSemana.getMonth();
        calTitulo.textContent = mismoMs
            ? `${inicioSemana.getDate()} al ${finSemana.getDate()} de ${MESES[inicioSemana.getMonth()]} ${inicioSemana.getFullYear()}`
            : `${inicioSemana.getDate()} de ${MESES[inicioSemana.getMonth()]} al ${finSemana.getDate()} de ${MESES[finSemana.getMonth()]} ${finSemana.getFullYear()}`;

        calContenedor.innerHTML = "";
        const agenda = document.createElement("div");
        agenda.className = "resv_cal_agenda";

        for (let i = 0; i < 7; i++) {
            const dia = new Date(inicioSemana);
            dia.setDate(dia.getDate() + i);

            const bloque = document.createElement("div");
            bloque.className = "resv_cal_agenda_dia";

            const titulo = document.createElement("div");
            titulo.className = "resv_cal_agenda_dia_titulo";
            titulo.textContent = `${DIAS_LARGOS[dia.getDay()]} ${dia.getDate()} de ${MESES[dia.getMonth()]}`;
            bloque.appendChild(titulo);

            const eventosDia = reservasDelDia(dia);

            if (eventosDia.length === 0) {
                const vacio = document.createElement("div");
                vacio.className = "resv_cal_agenda_vacio";
                vacio.textContent = "Sin reservas";
                bloque.appendChild(vacio);
            } else {
                eventosDia.forEach(function (r) {
                    bloque.appendChild(crearItemAgenda(r));
                });
            }

            agenda.appendChild(bloque);
        }

        calContenedor.appendChild(agenda);
    }

    function renderizarDia() {

        calTitulo.textContent = `${DIAS_LARGOS[fechaCalendario.getDay()]} ${fechaCalendario.getDate()} de ${MESES[fechaCalendario.getMonth()]} de ${fechaCalendario.getFullYear()}`;

        calContenedor.innerHTML = "";
        const agenda = document.createElement("div");
        agenda.className = "resv_cal_agenda";

        const bloque = document.createElement("div");
        bloque.className = "resv_cal_agenda_dia";

        const eventosDia = reservasDelDia(fechaCalendario);

        if (eventosDia.length === 0) {
            const vacio = document.createElement("div");
            vacio.className = "resv_cal_agenda_vacio";
            vacio.textContent = "No hay reservas para este día.";
            bloque.appendChild(vacio);
        } else {
            eventosDia.forEach(function (r) {
                bloque.appendChild(crearItemAgenda(r));
            });
        }

        agenda.appendChild(bloque);
        calContenedor.appendChild(agenda);
    }

    function crearItemAgenda(reserva) {
        const item = document.createElement("div");
        item.className = "resv_cal_agenda_item";
        item.addEventListener("click", function () { abrirVerReserva(reserva); });

        const hora = document.createElement("div");
        hora.className = "resv_cal_agenda_hora";
        hora.textContent = reserva.hora;

        const info = document.createElement("div");
        info.className = "resv_cal_agenda_info";
        info.textContent = `${reserva.cliente} · ${reserva.servicios_nombres || "Sin servicio"}`;
        const small = document.createElement("small");
        small.textContent = `Con ${reserva.barbero} · ${ETIQUETAS_ESTADO[reserva.estado] || reserva.estado}`;
        info.appendChild(small);

        item.append(hora, info);
        return item;
    }

    function cambiarModoCalendario(modo) {
        modoCalendario = modo;
        botonesModo.forEach(function (b) { b.classList.toggle("activo", b.dataset.modo === modo); });
        renderizarCalendario();
    }

    botonesModo.forEach(function (b) {
        b.addEventListener("click", function () { cambiarModoCalendario(b.dataset.modo); });
    });

    btnCalAnterior.addEventListener("click", function () {
        if (modoCalendario === "mes") {
            fechaCalendario.setMonth(fechaCalendario.getMonth() - 1);
        } else if (modoCalendario === "semana") {
            fechaCalendario.setDate(fechaCalendario.getDate() - 7);
        } else {
            fechaCalendario.setDate(fechaCalendario.getDate() - 1);
        }
        renderizarCalendario();
    });

    btnCalSiguiente.addEventListener("click", function () {
        if (modoCalendario === "mes") {
            fechaCalendario.setMonth(fechaCalendario.getMonth() + 1);
        } else if (modoCalendario === "semana") {
            fechaCalendario.setDate(fechaCalendario.getDate() + 7);
        } else {
            fechaCalendario.setDate(fechaCalendario.getDate() + 1);
        }
        renderizarCalendario();
    });

    btnCalHoy.addEventListener("click", function () {
        fechaCalendario = new Date();
        renderizarCalendario();
    });

    // =====================================================
    // MODAL: NUEVA RESERVA
    // =====================================================
    const modalReserva = document.getElementById("modal_reserva");
    const btnAbrirReserva = document.getElementById("btn_abrir_reserva");
    const btnAbrirReservaVacio = document.getElementById("btn_abrir_reserva_vacio");
    const cerrarModalReserva = document.getElementById("cerrar_modal_reserva");
    const cancelarReserva = document.getElementById("cancelar_reserva");
    const formReserva = document.getElementById("form_reserva");

    // ---- Elementos del selector visual de horarios (solo "Nueva reserva") ----
    const selectBarberoNueva = document.getElementById("resv_nueva_barbero");
    const contenedorServiciosNueva = document.getElementById("resv_nueva_servicios");
    const inputFechaNueva = document.getElementById("resv_nueva_fecha");
    const horariosGrid = document.getElementById("resv_horarios_grid");
    const inputHoraNueva = document.getElementById("resv_nueva_hora");
    const resumenBox = document.getElementById("resv_resumen");

    const MENSAJE_HORARIOS_INICIAL = "Elegí barbero, al menos un servicio y una fecha para ver los horarios disponibles.";

    let duracionActualMinutos = 0;
    let horariosRequestId = 0; // evita que una respuesta AJAX vieja pise a una más nueva

    function mensajeHorarios(texto, esError) {
        horariosGrid.innerHTML = "";
        const p = document.createElement("p");
        p.className = "resv_horarios_mensaje" + (esError ? " error" : "");
        p.textContent = texto;
        horariosGrid.appendChild(p);
    }

    function resetearPasoHorarios() {
        mensajeHorarios(MENSAJE_HORARIOS_INICIAL, false);
        inputHoraNueva.value = "";
        duracionActualMinutos = 0;
        resumenBox.style.display = "none";
    }

    // Consulta real al servidor (horarios_disponibles.php): calcula la
    // disponibilidad usando las reservas existentes, el barbero, la
    // fecha y la duración real de los servicios elegidos. No se
    // inventan horarios en el cliente.
    function actualizarHorariosNueva() {

        const idBarbero = selectBarberoNueva.value;
        const fecha = inputFechaNueva.value;
        const idsServicios = Array.from(
            contenedorServiciosNueva.querySelectorAll('input[name="servicios[]"]:checked')
        ).map(function (cb) { return cb.value; });

        // Cambió algún dato base: la hora elegida antes ya no es válida.
        inputHoraNueva.value = "";
        resumenBox.style.display = "none";

        if (!idBarbero || !fecha || idsServicios.length === 0) {
            mensajeHorarios(MENSAJE_HORARIOS_INICIAL, false);
            return;
        }

        mensajeHorarios("Buscando horarios disponibles...", false);

        const idPeticion = ++horariosRequestId;
        const params = new URLSearchParams({
            id_barbero: idBarbero,
            fecha: fecha,
            servicios: idsServicios.join(",")
        });

        fetch("horarios_disponibles.php?" + params.toString())
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (idPeticion !== horariosRequestId) return; // hubo un cambio más nuevo: descartar
                renderizarHorarios(data);
            })
            .catch(function () {
                if (idPeticion !== horariosRequestId) return;
                mensajeHorarios("No se pudieron cargar los horarios. Probá nuevamente.", true);
            });
    }

    function renderizarHorarios(data) {

        if (!data || !data.ok) {
            mensajeHorarios("No se pudieron calcular los horarios disponibles.", true);
            return;
        }

        duracionActualMinutos = data.duracion_minutos || 0;

        if (!data.horarios || data.horarios.length === 0) {
            mensajeHorarios("No hay horarios disponibles ese día para la duración total elegida.", false);
            return;
        }

        horariosGrid.innerHTML = "";

        data.horarios.forEach(function (h) {
            const boton = document.createElement("button");
            boton.type = "button";
            boton.className = "resv_horario_slot " + (h.disponible ? "disponible" : (h.motivo === "pasado" ? "pasado" : "ocupado"));
            boton.textContent = h.hora;
            boton.disabled = !h.disponible;
            boton.dataset.hora = h.hora;
            if (h.disponible) {
                boton.title = `Libre: ${h.hora}–${h.fin}`;
                boton.addEventListener("click", function () { seleccionarHorarioNueva(h.hora, boton); });
            } else if (h.motivo === "pasado") {
                boton.title = "Este horario ya pasó";
            } else {
                boton.title = `${h.hora}–${h.fin} se superpone con el turno ${h.choca_con}`;
            }
            horariosGrid.appendChild(boton);
        });

        if (data.ocupados && data.ocupados.length > 0) {
            const aviso = document.createElement("p");
            aviso.className = "resv_horarios_mensaje";
            aviso.textContent = "Turnos ya reservados con este barbero ese día: " + data.ocupados.join(", ");
            horariosGrid.appendChild(aviso);
        }
    }

    function seleccionarHorarioNueva(hora, boton) {

        horariosGrid.querySelectorAll(".resv_horario_slot.seleccionado").forEach(function (b) {
            b.classList.remove("seleccionado");
        });
        boton.classList.add("seleccionado");

        inputHoraNueva.value = hora;
        actualizarResumenNueva(hora);
    }

    function actualizarResumenNueva(hora) {

        const opcionBarbero = selectBarberoNueva.options[selectBarberoNueva.selectedIndex];
        const nombreBarbero = opcionBarbero ? opcionBarbero.textContent.trim() : "—";

        const marcados = Array.from(contenedorServiciosNueva.querySelectorAll('input[name="servicios[]"]:checked'));
        const nombresServicios = marcados.map(function (cb) {
            const span = cb.closest(".resv_servicio_item").querySelector(".resv_servicio_nombre");
            return span.childNodes[0] ? span.childNodes[0].textContent.trim() : "";
        });
        const precioTotal = marcados.reduce(function (acc, cb) {
            return acc + (parseFloat(cb.dataset.precio) || 0);
        }, 0);

        document.getElementById("resumen_barbero").textContent = nombreBarbero || "—";
        document.getElementById("resumen_servicios").textContent = nombresServicios.join(", ") || "—";
        document.getElementById("resumen_duracion").textContent = formatoDuracion(duracionActualMinutos);
        document.getElementById("resumen_fecha").textContent = formatoFechaCorta(inputFechaNueva.value);
        document.getElementById("resumen_hora").textContent = hora;
        document.getElementById("resumen_precio").textContent = formatoMoneda(precioTotal);

        resumenBox.style.display = "flex";
    }

    // Recalcular horarios cada vez que cambia barbero, fecha, o los
    // servicios marcados (delegado en el contenedor de checkboxes).
    selectBarberoNueva.addEventListener("change", actualizarHorariosNueva);
    inputFechaNueva.addEventListener("change", actualizarHorariosNueva);
    contenedorServiciosNueva.addEventListener("change", function (e) {
        if (e.target && e.target.matches('input[name="servicios[]"]')) {
            actualizarHorariosNueva();
        }
    });

    function abrirNuevaReserva() {
        formReserva.reset();
        if (inputFechaNueva) inputFechaNueva.min = aYMD(new Date());
        resetearPasoHorarios();
        modalReserva.style.display = "flex";
    }

    function cerrarNuevaReserva() {
        modalReserva.style.display = "none";
        formReserva.reset();
        resetearPasoHorarios();
    }

    btnAbrirReserva.addEventListener("click", abrirNuevaReserva);
    btnAbrirReservaVacio.addEventListener("click", abrirNuevaReserva);
    cerrarModalReserva.addEventListener("click", cerrarNuevaReserva);
    cancelarReserva.addEventListener("click", cerrarNuevaReserva);

    formReserva.addEventListener("submit", function (e) {

        const marcados = formReserva.querySelectorAll('input[name="servicios[]"]:checked');
        if (marcados.length === 0) {
            e.preventDefault();
            alert("Seleccioná al menos un servicio para la reserva.");
            return;
        }

        if (!inputHoraNueva.value) {
            e.preventDefault();
            alert("Seleccioná un horario disponible antes de guardar.");
        }
    });

    // =====================================================
    // MODAL: EDITAR RESERVA
    // =====================================================
    const modalEditarReserva = document.getElementById("modal_editar_reserva");
    const cerrarModalEditarReserva = document.getElementById("cerrar_modal_editar_reserva");
    const cancelarEditarReserva = document.getElementById("cancelar_editar_reserva");
    const formEditarReserva = document.getElementById("form_editar_reserva");

    function abrirEditarReserva(reserva) {

        document.getElementById("editar_resv_id").value = reserva.id_reserva;
        document.getElementById("editar_resv_cliente").value = reserva.id_cliente;
        document.getElementById("editar_resv_barbero").value = reserva.id_barbero;
        document.getElementById("editar_resv_fecha").value = reserva.fecha;
        document.getElementById("editar_resv_hora").value = reserva.hora;
        document.getElementById("editar_resv_email").value = reserva.email || "";

        const contenedorServicios = document.getElementById("editar_resv_servicios");
        contenedorServicios.querySelectorAll('input[name="servicios[]"]').forEach(function (cb) {
            cb.checked = reserva.servicios_ids.includes(Number(cb.value));
        });

        modalEditarReserva.style.display = "flex";
    }

    function cerrarEditarReserva() {
        modalEditarReserva.style.display = "none";
    }

    cerrarModalEditarReserva.addEventListener("click", cerrarEditarReserva);
    cancelarEditarReserva.addEventListener("click", cerrarEditarReserva);

    formEditarReserva.addEventListener("submit", function (e) {
        const marcados = formEditarReserva.querySelectorAll('input[name="servicios[]"]:checked');
        if (marcados.length === 0) {
            e.preventDefault();
            alert("Seleccioná al menos un servicio para la reserva.");
        }
    });

    // =====================================================
    // MODAL: VER RESERVA
    // =====================================================
    const modalVerReserva = document.getElementById("modal_ver_reserva");
    const cerrarModalVerReserva = document.getElementById("cerrar_modal_ver_reserva");
    const cerrarVerReservaBtn = document.getElementById("cerrar_ver_reserva_btn");

    function abrirVerReserva(reserva) {

        document.getElementById("ver_resv_id").textContent = reserva.id_reserva;
        document.getElementById("ver_resv_cliente").textContent = reserva.cliente;
        document.getElementById("ver_resv_barbero").textContent = reserva.barbero;
        document.getElementById("ver_resv_servicios").textContent = reserva.servicios_nombres || "Sin servicios asociados";
        document.getElementById("ver_resv_total").textContent = formatoMoneda(reserva.precio_total);
        document.getElementById("ver_resv_fecha").textContent = formatoFechaCorta(reserva.fecha);
        document.getElementById("ver_resv_hora").textContent = reserva.hora;
        document.getElementById("ver_resv_email").textContent = reserva.email || "—";

        const verEstado = document.getElementById("ver_resv_estado");
        verEstado.textContent = "";
        verEstado.appendChild(crearBadgeEstado(reserva.estado));

        modalVerReserva.style.display = "flex";
    }

    function cerrarVerReserva() {
        modalVerReserva.style.display = "none";
    }

    cerrarModalVerReserva.addEventListener("click", cerrarVerReserva);
    cerrarVerReservaBtn.addEventListener("click", cerrarVerReserva);

    // =====================================================
    // CONFIRMAR / CANCELAR RESERVA (estado_reserva.php)
    // =====================================================
    function cambiarEstadoReserva(reserva, accion) {

        const pregunta = accion === "confirmar"
            ? `¿Confirmar la reserva de ${reserva.cliente} del ${formatoFechaCorta(reserva.fecha)} a las ${reserva.hora}?`
            : `¿Cancelar la reserva de ${reserva.cliente} del ${formatoFechaCorta(reserva.fecha)} a las ${reserva.hora}? Quedará en el historial como cancelada.`;

        if (!confirm(pregunta)) return;

        const form = document.createElement("form");
        form.method = "POST";
        form.action = "estado_reserva.php";
        form.style.display = "none";

        [["id_reserva", reserva.id_reserva], ["accion", accion]].forEach(function (par) {
            const input = document.createElement("input");
            input.type = "hidden";
            input.name = par[0];
            input.value = par[1];
            form.appendChild(input);
        });

        document.body.appendChild(form);
        form.submit();
    }

    // =====================================================
    // ELIMINAR RESERVA
    // =====================================================
    function eliminarReserva(reserva) {

        const confirmacion = confirm(
            `¿Querés eliminar la reserva de ${reserva.cliente} del ${formatoFechaCorta(reserva.fecha)} a las ${reserva.hora}?`
        );

        if (!confirmacion) return;

        const form = document.createElement("form");
        form.method = "POST";
        form.action = "eliminar_reserva.php";
        form.style.display = "none";

        const input = document.createElement("input");
        input.type = "hidden";
        input.name = "id_reserva";
        input.value = reserva.id_reserva;

        form.appendChild(input);
        document.body.appendChild(form);
        form.submit();
    }

    // =====================================================
    // CERRAR MODALES CLICKEANDO AFUERA
    // =====================================================
    window.addEventListener("click", function (e) {
        if (e.target === modalReserva) cerrarNuevaReserva();
        if (e.target === modalEditarReserva) cerrarEditarReserva();
        if (e.target === modalVerReserva) cerrarVerReserva();
    });

    // =====================================================
    // INICIO
    // =====================================================
    aplicarFiltros();

})();
