# 05 · Cliente en tiempo real del trivial multijugador

SPA del trivial multijugador 1v1 de SimulaOpo. JavaScript vanilla puro (sin frameworks) que conecta con un servidor Node.js/Socket.IO en un subdominio separado. Este extracto se centra en la conexión y el manejo de eventos, incluyendo recuperación de partida tras desconexión momentánea.

## Fichero

- **`trivia-client-excerpt.js`** — Extracto (~200 líneas) del cliente completo (~1000 líneas).

## Qué demuestra

- **JavaScript vanilla ES5-friendly**: sin frameworks, sin bundler. IIFE, `document.getElementById`, `Array.prototype.forEach.call`. Decisión consciente para no bajar compatibilidad en dispositivos antiguos de estudiantes.

- **Real-time con Socket.IO**: conexión autenticada por token, gestión de eventos entrantes y salientes, cleanup manual de sockets.

- **Configuración de transports por experiencia real en producción**: el archivo documenta explícitamente por qué se fuerza `polling` antes de `websocket` — el CDN de Hostinger rompe el upgrade WS intermitentemente si se intenta WS directo. Este es el tipo de detalle que solo aprendes tras depurar el problema en producción.

- **Recuperación de partida tras reconexión**: cuando Socket.IO reconecta en mitad de una partida, el cliente NO re-emite `buscar_partida_rapida` (crearía una partida nueva). En su lugar, espera a que el server envíe `partida_recuperada` con el estado actual (marcador, pregunta en curso, tiempo transcurrido). Timeout defensivo de 20s si no llega.

- **Disconnect handler inteligente**: no todo `disconnect` significa "volver al menú y mostrar error". El código diferencia entre 4 contextos (pantalla final, sala esperando, partida en curso, resto) y solo muestra error en el último. Esto elimina falsos positivos que arruinaban la UX cuando Socket.IO reconectaba solo.

- **Mensajes de error específicos por motivo**: el server envía códigos (`no_encontrada`, `codigo_invalido`, `creador_desconectado`, `no_puedes_jugar_contra_ti`) y el cliente los mapea a mensajes claros para el usuario, en vez de un genérico "Error".

- **Feedback UI instantáneo**: al elegir respuesta, deshabilita las opciones inmediatamente sin esperar la respuesta del server. Si luego el server rechaza, se ajusta; si acepta, la UI ya reaccionó.

## Contexto en producción

Servidor Node.js en `trivia.simulaopo.com` (subdominio con app Node en Hostinger Business, auto-deploy desde GitHub). El servidor implementa emparejamiento por criterios (temario compartido), gestión de salas privadas con código, sincronización de rondas con temporizador, sistema de ELO y grace period de 30 segundos para reconexiones.

Durante la etapa de suscripciones era una función exclusiva para usuarios premium: a los no-premium se les mostraba una página gate (de ahí la comprobación al inicio del extracto).

## Referencias

- Socket.IO client: https://socket.io/docs/v4/client-api/
