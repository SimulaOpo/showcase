/* public/assets/js/trivia.js  —  EXTRACTO
 *
 * Cliente SPA del trivial multijugador 1v1 de SimulaOpo. El archivo completo
 * (~1000 líneas) gestiona menú, selección de temario, sala privada con código,
 * partida rápida, sala en curso, resultados y revancha.
 *
 * Este extracto se centra en la parte más interesante: la conexión con el
 * servidor Node.js/Socket.IO, el manejo de eventos, y la recuperación de
 * partida tras reconexión (grace period de 30s en el servidor).
 *
 * JavaScript vanilla (sin frameworks), IIFE, ES5-friendly para compatibilidad
 * de navegadores antiguos en dispositivos de estudiantes que preparan oposición.
 */

(function () {
  'use strict';

  var C = window.TRIVIA_CONFIG || {};
  var socket = null;
  var modoActual = null;              // 'buscar_rapida' | 'crear_privada' | 'unirse_privada'
  var codigoSalaActual = null;
  var timeoutRecuperacion = null;

  var $ = function (id) { return document.getElementById(id); };
  if (!$('trivia-section-menu')) return;   // Página gate para usuarios no-premium.

  // Referencias DOM (extracto abreviado).
  var $sectMenu    = $('trivia-section-menu');
  var $sectSala    = $('trivia-section-sala');
  var $sectLoading = $('trivia-section-loading');
  var $sectPartida = $('trivia-section-partida');
  var $sectFinal   = $('trivia-section-final');
  var $loadingTitle = $('trivia-loading-title');
  var $loadingMsg   = $('trivia-loading-msg');
  var $btnCancelar  = $('btn-cancelar-busqueda');
  var $error        = $('trivia-error');

  // ──────────────────────────────────────────────────────────────
  // CONEXIÓN + HANDLERS
  // ──────────────────────────────────────────────────────────────

  function conectarYBuscar(token, opciones) {
    opciones = opciones || {};
    $loadingTitle.textContent = 'Conectando…';
    $loadingMsg.textContent = 'Estableciendo conexión con el servidor.';

    socket = io(C.serverUrl, {
      auth: { token: token },

      // IMPORTANTE: empezar con polling y upgrade a WebSocket.
      // El CDN de Hostinger (hcdn) rompe el upgrade WS de forma intermitente
      // si intentamos WS directo; con polling primero, la conexión se establece
      // por HTTP y luego Socket.IO negocia el upgrade cuando puede.
      transports: ['polling', 'websocket'],
      upgrade: true,
      timeout: 20000,

      // Reconexión generosa: si el usuario cambia a WhatsApp para copiar
      // el enlace de la sala, al volver el socket debe intentar reconectarse.
      reconnection: true,
      reconnectionAttempts: 15,
      reconnectionDelay: 500,
      reconnectionDelayMax: 4000,
    });

    // ── Conexión establecida — UI según lo que ibas a hacer.
    socket.on('connect', function () {
      if (opciones.unirse) {
        $loadingTitle.textContent = 'Uniéndote a la sala…';
        $btnCancelar.hidden = true;
      } else if (modoActual === 'crear_privada') {
        $loadingTitle.textContent = 'Creando sala…';
        $btnCancelar.hidden = true;
      } else {
        $loadingTitle.textContent = 'Buscando rival…';
        $btnCancelar.hidden = false;
      }
    });

    socket.on('connect_error', function (err) {
      mostrarSeccion($sectMenu);
      mostrarError('Error de conexión: ' + err.message);
    });

    // ── Autenticación OK. Aquí decidimos qué emitir según el contexto.
    socket.on('conectado', function () {
      // Si Socket.IO ha reconectado en mitad de una partida, NO re-emitimos
      // buscar/crear/unirse. Esperamos a que el server nos envíe
      // `partida_recuperada` con el estado actual (grace period 30s).
      // Timeout defensivo: si en 20s no llega, damos por perdida.
      if ($sectPartida && $sectPartida.classList.contains('is-active')) {
        if (timeoutRecuperacion) clearTimeout(timeoutRecuperacion);
        timeoutRecuperacion = setTimeout(function () {
          if ($sectPartida.classList.contains('is-active')) {
            mostrarSeccion($sectMenu);
            mostrarError('No se pudo recuperar la partida.');
            try { socket.disconnect(); } catch (_) { /* ignore */ }
          }
        }, 20000);
        return;
      }

      // Conexión "fresca" — pedir lo que el usuario quería.
      if (opciones.unirse) {
        socket.emit('unirse_a_sala', { codigo: opciones.unirse });
      } else if (modoActual === 'crear_privada') {
        socket.emit('crear_sala_privada');
      } else {
        socket.emit('buscar_partida_rapida');
      }
    });

    // ── Recuperación de partida tras reconexión.
    // El server envía el estado completo: marcador, rival, pregunta actual con
    // tiempo transcurrido. Reconstruimos la UI exactamente donde estabas.
    socket.on('partida_recuperada', function (data) {
      if (timeoutRecuperacion) { clearTimeout(timeoutRecuperacion); timeoutRecuperacion = null; }
      restaurarPartida(data);
    });

    // ── Rival desconectado — solo mostrar aviso, no romper la partida.
    socket.on('rival_desconectado', function () {
      mostrarError('Rival desconectado, esperando reconexión…');
    });
    socket.on('rival_reconectado', function () {
      $error.hidden = true;
    });

    // ── Sala privada creada — mostrar código para compartir.
    socket.on('sala_creada', function (data) {
      codigoSalaActual = data.codigo;
      var $c = $('trivia-sala-codigo');
      if ($c) $c.textContent = data.codigo;
      mostrarSeccion($sectSala);
    });

    socket.on('sala_expirada', function () {
      codigoSalaActual = null;
      if (socket) { socket.disconnect(); socket = null; }
      mostrarSeccion($sectMenu);
      mostrarError('La sala expiró tras 10 minutos sin que nadie se uniera.');
    });

    socket.on('error_sala', function (data) {
      // Mensajes específicos por motivo — mejor UX que un genérico.
      var mensajes = {
        no_encontrada:              'No se encontró ninguna sala con ese código. Puede haber expirado.',
        codigo_invalido:            'El código no es válido.',
        creador_desconectado:       'El creador de la sala se ha desconectado.',
        no_puedes_jugar_contra_ti:  'No puedes jugar contra ti mismo.',
      };
      mostrarSeccion($sectMenu);
      mostrarError(mensajes[data.motivo] || 'Error con la sala privada.');
      if (socket) { socket.disconnect(); socket = null; }
    });

    // ── Flujo principal de partida.
    socket.on('partida_iniciada',    arrancarPartida);
    socket.on('nueva_pregunta',      mostrarPregunta);
    socket.on('resultado_pregunta',  mostrarResultadoRonda);
    socket.on('partida_terminada',   mostrarResultadoFinal);

    // ── Disconnect inteligente: NO siempre significa "volver al menú".
    socket.on('disconnect', function (motivo) {
      // Si ya estás en la pantalla final, ignorar.
      if ($sectFinal.classList.contains('is-active')) return;

      // Si estás esperando en una sala privada creada por ti, tampoco romper:
      // Socket.IO auto-reconecta y el server tiene grace period.
      if ($sectSala.classList.contains('is-active')) return;

      // Si estás EN partida, no mostrar error ni volver al menú. Socket.IO
      // auto-reconectará y el server nos reubicará (grace 30s). El handler
      // `conectado` ya tiene el timeout defensivo.
      if ($sectPartida.classList.contains('is-active')) return;

      mostrarSeccion($sectMenu);
      mostrarError('Desconectado del servidor: ' + motivo);
    });
  }

  // ──────────────────────────────────────────────────────────────
  // ENVIAR RESPUESTA
  // ──────────────────────────────────────────────────────────────

  function elegirRespuesta(idx) {
    if (respuestaEnviada) return;
    respuestaEnviada = true;
    var tiempoMs = Date.now() - preguntaInicio;

    // Deshabilitar todas las opciones para feedback visual instantáneo,
    // sin esperar a la respuesta del server.
    Array.prototype.forEach.call($opciones.children, function (b) { b.disabled = true; });

    socket.emit('responder', { respuesta_idx: idx, tiempo_ms: tiempoMs });
  }

  // ──────────────────────────────────────────────────────────────
  // Métodos omitidos del extracto:
  //   - cargarTemario(), renderTemario(): selector de bloques temáticos
  //   - arrancarPartida(), mostrarPregunta(), mostrarResultadoRonda(),
  //     mostrarResultadoFinal(): UI de la partida en curso
  //   - restaurarPartida(): reconstrucción tras `partida_recuperada`
  //   - arrancarBarraTiempo(): barra de progreso del temporizador (20s/ronda)
  //   - Sistema de revancha con modal y timeout
  //   - Utilidades: escapar(), mostrarSeccion(), mostrarError(), cerrarModal()
  // ──────────────────────────────────────────────────────────────

})();
