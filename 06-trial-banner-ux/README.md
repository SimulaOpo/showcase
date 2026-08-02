# 06 · Banner de trial — full-stack en un fichero

Componente que muestra un contador de trial gratuito de 7 días con lógica condicional no trivial. Incluye backend PHP (4 gates de decisión, uno de ellos consultando BBDD), HTML semántico, CSS con responsive design, y JS de countdown en tiempo real. Un solo archivo, tres responsabilidades.

## Fichero

- **`banner_trial.php`** — Componente completo: PHP + HTML + CSS + JS.

## Qué demuestra

### Backend — lógica de producto no obvia

El banner se muestra solo si se cumplen **cuatro** condiciones. Tres son directas (logueado, no pagador, en trial). La cuarta es la interesante:

> Si el usuario fue atribuido a un afiliado al registrarse, su descuento es el afi (permanente, no caduca con el trial). En ese caso, el banner que verá es otro (`banner_afiliado_descuento.php`), y este debe callarse — son mutuamente excluyentes.

El comentario del código explica también **por qué** se consulta a BBDD y no a la cookie: "la cookie se borra automáticamente tras el registro en `atribuirUsuarioRecienCreado`". Es información de negocio que sin ese comentario obligaría al siguiente que lea el código a excavar tres archivos para entenderlo.

### Frontend — HTML semántico

- `role="region"` y `aria-label` para lectores de pantalla.
- `<a>` con `href` real (no `<button onclick>`) — funciona sin JS y respeta cmd/ctrl-click.

### CSS — patrones modernos

- `linear-gradient` sutil (amarillo pálido → dorado) coherente con el color de "aviso" pero sin gritar.
- `flex-wrap` para que en móvil el CTA baje debajo del texto en lugar de comprimirse.
- Media query específica que **también** reduce padding, no solo tamaño de letra.
- `transition` corta (150ms) sobre `background` en el hover del CTA.
- `border-radius: 999px` para pill button en vez de `border-radius: 50%` (patrón correcto para píldoras de texto variable).

### JavaScript — countdown eficiente

- Tick cada **minuto**, no cada segundo. El formato mostrado es `d/h`, así que segundo a segundo sería derroche de CPU.
- El timestamp de fin se calcula cliente-side desde la marca inicial del servidor. Así el countdown funciona incluso si el reloj del servidor y el del cliente están desincronizados.
- Auto-remove del banner cuando `rest <= 0` — el usuario ve desaparecer el banner en tiempo real sin recargar página.
- IIFE encapsulado, sin contaminar scope global.

### Meta

- **Un solo archivo, un solo `include`, sin build step**. Encaja con el enfoque general de SimulaOpo: vanilla + PHP directo, sin bundler, sin framework de componentes. Fricción cero para tocar el banner en 5 minutos.
- **Manejo defensivo del fallo de BBDD**: el `try/catch` en la query de `afiliado_id` — si la BBDD casca, se loggea y se sigue mostrando el banner (comportamiento por defecto seguro), en lugar de ocultarlo o romper la página.

## Contexto en producción

En producción coexisten dos banners: **banner_trial** (amarillo, este) y **banner_afiliado_descuento** (verde). Nunca aparecen los dos a la vez — la exclusión mutua está garantizada por la cuarta gate. Documentado en la memoria del proyecto como "trampa crítica del trial" para evitar regresiones futuras.
