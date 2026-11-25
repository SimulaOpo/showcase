# 01 · Stripe Webhook Handler

Integración completa con Stripe para gestionar el ciclo de vida de suscripciones: procesa 5 tipos de eventos, verifica la firma criptográfica del payload y aplica actualizaciones idempotentes sobre la base de datos.

## Ficheros

- **`webhook_stripe.php`** — Endpoint HTTP que recibe los POST de Stripe. Delegación pura al servicio; no contiene lógica de negocio (arquitectura "thin controller").
- **`StripeService-excerpt.php`** — Extracto del servicio real. Se muestra el dispatcher (`handleWebhookEvent`) y un handler de ejemplo (`onCheckoutCompleted`).

## Qué demuestra

- **Seguridad**: verificación de firma con `Stripe\Webhook::constructEvent` usando el webhook secret. Un payload sin firma válida se rechaza con 400, no llega a los handlers.
- **Idempotencia**: los webhooks de Stripe pueden reintentarse. Antes de crear una suscripción, se comprueba `findByStripeSubscriptionId()`; si ya existe, no se duplica.
- **Orden no garantizado**: se documenta explícitamente que los eventos no llegan en orden y por qué `checkout.session.completed` puede llegar después de `customer.subscription.created`.
- **Contract con Stripe**: solo se responde 200 cuando el handler completa sin excepción. Un 5xx hace que Stripe reintente, un 2xx marca el evento como procesado. La distinción importa.

## Contexto

Este endpoint estuvo desplegado en producción contra Stripe en modo live durante la etapa de suscripciones de SimulaOpo. Hoy las suscripciones están desactivadas (ver "Estado actual" en el [README principal](../README.md)). Los cinco eventos gestionados cubren el ciclo completo de una suscripción: alta, renovación, cambios de plan, cancelaciones y fallos de cobro.

## Referencias

- Stripe Webhooks: https://stripe.com/docs/webhooks
- Verificación de firmas: https://stripe.com/docs/webhooks/signatures
