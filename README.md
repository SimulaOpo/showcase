# SimulaOpo — Showcase técnico

Extracto público de decisiones técnicas del código de [SimulaOpo.com](https://simulaopo.com), plataforma SaaS de simulacros para oposiciones en producción desde 2024.

Este repositorio **no es el proyecto completo**: es una selección curada de módulos representativos, sanitizada para uso público. El código completo vive en un repositorio privado (`SimulaOpo/core`) — acceso bajo petición para procesos de selección.

## Sobre SimulaOpo

SimulaOpo es una plataforma web de simulacros para preparación de oposiciones. La he diseñado, desarrollado, desplegado y mantengo íntegramente yo. Todo el trabajo aquí mostrado, desde la arquitectura hasta el diagnóstico de incidencias en producción, es individual.

## Stack

- **Backend**: PHP 8 sobre arquitectura MVC · MySQL · Composer
- **Frontend**: HTML5, CSS3, JavaScript vanilla, diseño responsive
- **Real-time**: servidor Node.js con WebSockets para el trivial multijugador 1v1 (repo separado)
- **Pagos**: Stripe — Checkout, suscripciones recurrentes, cupones, webhooks, reembolsos
- **Autenticación**: OAuth (Google) + sistema propio con verificación por email
- **Infraestructura**: Hostinger (LAMP + Node en subdominio) · Cloudflare (CDN, DNS, WAF, rate limiting) · cron jobs
- **Monitorización**: UptimeRobot con endpoint `/health` propio

## Índice del showcase

### [01 · Stripe Webhook Handler](./01-stripe-webhook/)

Integración completa con Stripe: verificación de firma, dispatch de 5 tipos de eventos y aplicación idempotente sobre BD. Cubre por qué el orden de eventos no importa y cómo protegerse de reintentos.

### [02 · Sistema de afiliados — atribución de clicks](./02-affiliate-system/)

Motor de atribución del programa de afiliados. Diseño defensivo "fail silent" para que la lógica de tracking nunca bloquee el registro o el pago del usuario. Manejo de cookie de 30 días, validación estricta de slugs, hashing de IP compatible con RGPD.

### [03 · Restauración de IP real tras Cloudflare](./03-cloudflare-real-ip/)

Módulo autocontenido que restaura la IP real del visitante desde `CF-Connecting-IP`, verificando primero que la conexión TCP proviene de un rango CIDR oficial de Cloudflare. Incluye implementación propia de CIDR matching para IPv4 e IPv6.

### [04 · Migración SQL versionada](./04-sql-migration/)

Ejemplo de migración de esquema con disciplina: versionado, cambios documentados en el propio SQL, índices donde toca, `ENUM` para valores cerrados.

## Qué no aparece aquí (pero existe en el core)

- Sistema completo de trial premium de 7 días con cupón post-trial
- Registro y autenticación OAuth Google
- Dashboard de administración con panel de afiliados
- Motor de tests, banco de preguntas, sistema de retención
- Servidor Node.js con Socket.IO para el trivial multijugador 1v1 (`SimulaOpo/trivia-server`, también privado)
- Suite de scripts CLI ejecutados por cron (emails, recordatorios, reportes)
- Rate limiting propio + medidas anti-brute-force en `/login`

## Contacto

- **Sitio en producción**: https://simulaopo.com
- **LinkedIn**: https://linkedin.com/in/angelhueso89
- **Email**: angelhueso89@hotmail.com

Para acceso al repositorio privado del core en un proceso de selección, contactar por LinkedIn o email.
