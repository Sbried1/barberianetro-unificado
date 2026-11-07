/* =====================================================================
   PANEL_UI.JS
   Componentes compartidos del panel: modal de confirmación y de aviso
   (reemplazan a confirm() y alert(), que el navegador puede bloquear),
   contadores de caracteres y protección contra envíos duplicados.
   Lo carga auth_render_logout_item() en todas las páginas con menú.

   Formularios declarativos: un <form data-confirmar="mensaje"> pide
   confirmación antes de enviarse. Atributos opcionales:
   data-confirmar-titulo, data-confirmar-boton, data-confirmar-cargando y
   data-confirmar-tipo="normal" (si la acción no es destructiva).
   ===================================================================== */

window.PanelUI = (function () {

    let capa = null;
    let titulo, mensaje, btnCancelar, btnAceptar;
    let ocupado = false;
    let alConfirmar = null;
    let focoAnterior = null;

    function crearModal() {
        if (capa) return;

        capa = document.createElement("div");
        capa.className = "panel_modal";
        capa.hidden = true;
        capa.setAttribute("role", "alertdialog");
        capa.setAttribute("aria-modal", "true");
        capa.setAttribute("aria-labelledby", "panel_modal_titulo");
        capa.setAttribute("aria-describedby", "panel_modal_mensaje");

        const caja = document.createElement("div");
        caja.className = "panel_modal_caja";

        titulo = document.createElement("h2");
        titulo.id = "panel_modal_titulo";

        mensaje = document.createElement("p");
        mensaje.id = "panel_modal_mensaje";

        const botones = document.createElement("div");
        botones.className = "panel_modal_botones";

        btnCancelar = document.createElement("button");
        btnCancelar.type = "button";
        btnCancelar.className = "panel_btn panel_btn_sec";

        btnAceptar = document.createElement("button");
        btnAceptar.type = "button";
        btnAceptar.className = "panel_btn";

        botones.append(btnCancelar, btnAceptar);
        caja.append(titulo, mensaje, botones);
        capa.appendChild(caja);
        document.body.appendChild(capa);

        btnCancelar.addEventListener("click", cerrar);
        btnAceptar.addEventListener("click", aceptar);
        capa.addEventListener("click", function (e) {
            if (e.target === capa) cerrar();
        });
        capa.addEventListener("keydown", function (e) {
            if (e.key === "Escape") {
                e.preventDefault();
                cerrar();
            } else if (e.key === "Tab" && !btnCancelar.hidden) {
                e.preventDefault();
                (document.activeElement === btnCancelar ? btnAceptar : btnCancelar).focus();
            }
        });
    }

    function abrir(opciones) {
        crearModal();
        ocupado = false;
        focoAnterior = document.activeElement;

        titulo.textContent = opciones.titulo;
        mensaje.textContent = opciones.mensaje;
        btnCancelar.textContent = opciones.textoCancelar || "Cancelar";
        btnCancelar.hidden = !!opciones.soloAceptar;
        btnCancelar.disabled = false;
        btnAceptar.textContent = opciones.textoConfirmar || "Aceptar";
        btnAceptar.disabled = false;
        btnAceptar.classList.remove("cargando");
        btnAceptar.classList.toggle("panel_btn_peligro", !!opciones.peligro);
        btnAceptar.dataset.cargando = opciones.textoCargando || "Procesando...";
        capa.classList.toggle("peligro", !!opciones.peligro);
        alConfirmar = opciones.alConfirmar || null;

        capa.hidden = false;
        (opciones.soloAceptar ? btnAceptar : btnCancelar).focus();
    }

    function cerrar() {
        if (!capa || ocupado) return;
        capa.hidden = true;
        alConfirmar = null;
        if (focoAnterior && focoAnterior.focus) focoAnterior.focus();
    }

    function aceptar() {
        if (ocupado) return;

        if (!alConfirmar) {
            cerrar();
            return;
        }

        // Queda bloqueado hasta que el navegador cambie de página: un segundo
        // clic no puede repetir la acción.
        ocupado = true;
        btnCancelar.disabled = true;
        btnAceptar.disabled = true;
        btnAceptar.classList.add("cargando");
        btnAceptar.textContent = btnAceptar.dataset.cargando;
        alConfirmar();
    }

    // Pide confirmación. opciones: titulo, mensaje, textoConfirmar,
    // textoCancelar, textoCargando, peligro, alConfirmar.
    function confirmar(opciones) {
        abrir(opciones);
    }

    // Aviso con un solo botón. Acepta un texto o { titulo, mensaje }.
    function avisar(opciones) {
        const datos = typeof opciones === "string" ? { mensaje: opciones } : opciones;
        abrir({ titulo: datos.titulo || "Revisá los datos", mensaje: datos.mensaje, soloAceptar: true });
    }

    // Envía un POST real con un formulario oculto (misma navegación que
    // un formulario común, incluida la redirección con el resultado).
    function enviarPost(accion, campos) {
        const form = document.createElement("form");
        form.method = "POST";
        form.action = accion;
        form.style.display = "none";

        Object.keys(campos).forEach(function (nombre) {
            const input = document.createElement("input");
            input.type = "hidden";
            input.name = nombre;
            input.value = campos[nombre];
            form.appendChild(input);
        });

        document.body.appendChild(form);
        form.submit();
    }

    // Marca un botón de envío como ocupado (o lo restablece).
    function cargando(boton, activo) {
        if (!boton) return;
        boton.disabled = activo;
        boton.classList.toggle("cargando", activo);
    }

    // =====================================================
    // CONTADORES DE CARACTERES
    // <span class="panel_contador" data-contador="id_del_campo"></span>
    // El límite es el atributo maxlength del campo.
    // =====================================================
    function actualizarContador(salida, campo) {
        const maximo = Number(campo.getAttribute("maxlength")) || 0;
        const usados = campo.value.length;
        const quedan = maximo - usados;

        salida.classList.toggle("limite", quedan <= 0);

        if (quedan < 0) {
            salida.textContent = `${usados}/${maximo}: superás el máximo por ${-quedan}`;
        } else if (quedan === 0) {
            salida.textContent = `${usados}/${maximo}: alcanzaste el máximo permitido`;
        } else {
            salida.textContent = `${usados}/${maximo}: quedan ${quedan}`;
        }
    }

    function iniciarContadores() {
        document.querySelectorAll("[data-contador]").forEach(function (salida) {
            const campo = document.getElementById(salida.dataset.contador);
            if (!campo) return;

            const refrescar = function () { actualizarContador(salida, campo); };
            campo.addEventListener("input", refrescar);
            if (campo.form) {
                campo.form.addEventListener("reset", function () { setTimeout(refrescar, 0); });
            }
            refrescar();
        });
    }

    // =====================================================
    // FORMULARIOS
    // =====================================================

    // Fase de captura: se resuelve la confirmación antes que nada. Al
    // confirmar se usa form.submit(), que no vuelve a disparar este evento.
    document.addEventListener("submit", function (e) {
        const form = e.target;
        if (!form.dataset || !form.dataset.confirmar) return;

        e.preventDefault();
        e.stopPropagation();

        confirmar({
            titulo: form.dataset.confirmarTitulo || "¿Confirmar la acción?",
            mensaje: form.dataset.confirmar,
            textoConfirmar: form.dataset.confirmarBoton || "Eliminar",
            textoCargando: form.dataset.confirmarCargando || "Eliminando...",
            peligro: form.dataset.confirmarTipo !== "normal",
            alConfirmar: function () { form.submit(); }
        });
    }, true);

    // Fase de burbuja: corre después de las validaciones de cada formulario.
    // Un formulario que ya se está enviando no se envía otra vez.
    document.addEventListener("submit", function (e) {
        const form = e.target;
        if (e.defaultPrevented) return;

        if (form.dataset.enviando) {
            e.preventDefault();
            return;
        }

        form.dataset.enviando = "1";

        // Con setTimeout, para que el botón deshabilitado siga viajando en
        // los datos del formulario que el navegador arma tras este evento.
        const boton = form.querySelector('button[type="submit"]');
        setTimeout(function () { cargando(boton, true); }, 0);
    });

    // Al volver con "Atrás" el navegador puede restaurar la página tal como
    // estaba: se limpia el estado de envío para que los botones sigan andando.
    window.addEventListener("pageshow", function (e) {
        if (!e.persisted) return;
        document.querySelectorAll("form[data-enviando]").forEach(function (form) {
            delete form.dataset.enviando;
            cargando(form.querySelector('button[type="submit"]'), false);
        });
        ocupado = false;
        if (capa) capa.hidden = true;
    });

    // =====================================================
    // CERRAR SESIÓN
    // Sin JavaScript, el enlace abre la pantalla de confirmación del servidor.
    // =====================================================
    document.addEventListener("click", function (e) {
        const enlace = e.target.closest ? e.target.closest("[data-panel-logout]") : null;
        if (!enlace) return;

        const form = document.getElementById(enlace.dataset.panelLogout);
        if (!form) return;

        e.preventDefault();

        confirmar({
            titulo: "¿Cerrar sesión?",
            mensaje: "Se cerrará tu sesión actual y vas a tener que volver a ingresar para usar el panel.",
            textoConfirmar: "Cerrar sesión",
            textoCargando: "Cerrando sesión...",
            alConfirmar: function () { form.submit(); }
        });
    });

    document.addEventListener("DOMContentLoaded", iniciarContadores);

    return { confirmar, avisar, enviarPost, cargando };

})();
