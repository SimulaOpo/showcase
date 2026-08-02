# 02 · Sistema de Afiliados — Atribución de Clicks

Motor de atribución para el programa de afiliados de SimulaOpo. Captura clicks con `?ref=SLUG` en cualquier URL del sitio, valida el slug, setea una cookie de atribución de 30 días y registra el click en base de datos. Todo con arquitectura "fail silent": si algo falla, la página del usuario sigue funcionando.

## Ficheros

- **`afiliado_ref_capture.php`** — Helper llamado al principio de cada request. Detecta `?ref=SLUG` y delega al servicio.
- **`AfiliadoService-excerpt.php`** — Extracto del servicio. Muestra el ciclo completo de tracking: validación → cookie → registro en BD, más utilidades de normalización y hashing.

## Qué demuestra

- **Diseño defensivo por decisión**: la atribución es importante para el negocio, pero *no* puede bloquear el registro de un usuario o el render de una página. Todos los errores se capturan y loggean, nunca se propagan al usuario.
- **Orden intencional de operaciones**: primero la cookie (crítica para atribución), después el `INSERT` de tracking (best-effort). Si el `INSERT` falla, el usuario sigue quedando atribuido.
- **Validación de entrada estricta**: `normalizeSlug()` centraliza toda la validación con un único regex (`[a-z0-9_-]{1,60}`). Todo el resto del código puede confiar en que el slug que recibe es seguro para logs, cookies y queries.
- **Cookies seguras**: `Secure` dinámico según HTTPS real (respeta `X-Forwarded-Proto` detrás de proxy), `SameSite=Lax`, path raíz, TTL explícito.
- **Privacidad / RGPD**: la IP no se almacena en claro. Se hashea con un salt fijo (`sha256(ip . '|simulaopo-afiliados')`) — suficiente para detectar visitas repetidas del mismo click sin conservar datos personales identificables.
- **Manejo de `X-Forwarded-For`**: cuando la cabecera contiene múltiples IPs (`client, proxy1, proxy2`), se toma solo la primera. Ver también la pieza 03 sobre restauración de IP real tras Cloudflare, que actúa antes de esto.

## Contexto en producción

El programa de afiliados de SimulaOpo funciona con cookie de 30 días. Cuando el visitante atribuido se registra, `atribuirUsuarioRecienCreado()` (omitido del extracto) enlaza el `usuario_id` al `afiliado_id`. Cuando ese usuario paga, `registrarVentaParaUsuario()` calcula y registra la comisión — sistema completo desde click hasta cobro.
