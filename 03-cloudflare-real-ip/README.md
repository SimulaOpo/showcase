# 03 · Restauración de IP real tras Cloudflare

Cuando un sitio se sirve a través del proxy de Cloudflare, la conexión TCP que llega al servidor proviene de una IP de Cloudflare — no del visitante. `$_SERVER['REMOTE_ADDR']` deja de ser útil para cualquier lógica basada en IP: rate limiting, atribución de afiliados, logging, password reset, feedback.

Este helper restaura la IP real desde la cabecera `CF-Connecting-IP`, pero solo cuando la petición llega efectivamente por un rango CIDR oficial de Cloudflare. Si no se validase el origen, cualquiera podría spoofear la cabecera enviándola directamente contra la IP del servidor para saltarse rate limits o falsear el sistema de afiliados.

## Fichero

- **`cloudflare_ip.php`** — Módulo autocontenido. Sin dependencias externas.

## Qué demuestra

- **Comprensión del problema real**: entender por qué el sitio "de repente" empezó a rate-limitear a todo el mundo tras activar Cloudflare, y saber exactamente qué código de la aplicación se ve afectado.
- **Threat modeling correcto**: identificar el vector de ataque (spoofear `CF-Connecting-IP` contra la IP origen) y neutralizarlo verificando el `REMOTE_ADDR` contra la lista oficial de rangos CIDR de Cloudflare.
- **CIDR matching a mano para IPv4 e IPv6**: sin librerías externas. Manejo correcto de máscaras `/0`, `/32`, `/128`, y de las rarezas de IPv6 (`inet_pton`, aritmética por bytes).
- **Validación de entrada exhaustiva**: `filter_var(..., FILTER_VALIDATE_IP)` antes de cualquier operación aritmética, para no confiar en que la cabecera contiene realmente una IP.
- **Mantenibilidad reconocida**: los rangos CIDR están hardcodeados con un comentario que dice "Revisar cada 12-18 meses". Alternativas evaluadas (fetch dinámico desde `https://www.cloudflare.com/ips/`) y descartadas conscientemente por no querer añadir una dependencia de red en cada arranque.

## Contexto en producción

Se llama en `app/config/config.php` al inicio de cada request, antes de cualquier otro código que lea `REMOTE_ADDR`. Sin este helper, tras activar Cloudflare en julio de 2026 todo el sistema de rate limits, la atribución de afiliados y las medidas anti-brute-force dejaban de funcionar (contaban todo el tráfico como misma IP).

## Referencias

- Cloudflare — IPs oficiales: https://www.cloudflare.com/ips/
- Cabecera CF-Connecting-IP: https://developers.cloudflare.com/fundamentals/reference/http-request-headers/#cf-connecting-ip
