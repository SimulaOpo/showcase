<?php
// app/services/StripeService.php  —  EXTRACTO
//
// Este archivo es un extracto del StripeService real (~770 líneas). Se
// muestra únicamente:
//   - handleWebhookEvent(): dispatch central de los 5 eventos suscritos
//   - onCheckoutCompleted(): ejemplo de handler específico
//
// Los handlers de suscripción, invoice y payment_failed siguen el mismo
// patrón: verificar idempotencia, actualizar estado en BBDD y registrar
// eventos para observabilidad.

declare(strict_types=1);

use Stripe\StripeClient;

final class StripeService
{
    private StripeClient $stripe;
    private SuscripcionRepository $repo;

    public function __construct(\PDO $pdo)
    {
        $this->stripe = new StripeClient(STRIPE_SECRET_KEY);
        $this->repo   = new SuscripcionRepository($pdo);
    }

    /**
     * Procesa eventos del webhook de Stripe.
     *
     * Pasos:
     *   1. Verificar la firma criptográfica del payload usando el webhook
     *      secret (Stripe\Webhook::constructEvent lo hace por nosotros).
     *      Si la firma no coincide → 400.
     *   2. Enrutar el evento al handler correspondiente.
     *   3. Devolver siempre 200 si el handler no lanza — Stripe reintenta
     *      si no recibe un 2xx, así que 200 = "recibido, no reintentar".
     */
    public function handleWebhookEvent(string $payload, string $sigHeader): array
    {
        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload,
                $sigHeader,
                STRIPE_WEBHOOK_SECRET
            );
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            return ['success' => false, 'error' => 'Firma inválida: ' . $e->getMessage()];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Error al parsear evento: ' . $e->getMessage()];
        }

        $type   = $event->type;
        $object = $event->data->object;

        switch ($type) {
            case 'checkout.session.completed':
                $this->onCheckoutCompleted($object);
                break;

            case 'customer.subscription.created':
            case 'customer.subscription.updated':
                $this->onSubscriptionUpdated($object);
                break;

            case 'customer.subscription.deleted':
                $this->onSubscriptionDeleted($object);
                break;

            case 'invoice.payment_succeeded':
                $this->onInvoicePaid($object);
                break;

            case 'invoice.payment_failed':
                $this->onInvoicePaymentFailed($object);
                break;
        }

        return ['success' => true, 'type' => $type];
    }

    /**
     * Checkout completado: crear registro de suscripción si no existe (idempotente).
     *
     * Nota importante: los webhooks NO llegan ordenados, y `checkout.session.completed`
     * a veces llega DESPUÉS de `customer.subscription.created`. Por eso comprobamos
     * la existencia por stripe_subscription_id antes de insertar, en vez de asumir
     * que este es el primer evento que vemos para la subscription.
     */
    private function onCheckoutCompleted(object $session): void
    {
        $userId         = (int) ($session->metadata->usuario_id ?? 0);
        $plan           = $session->metadata->plan ?? 'mensual';
        $customerId     = $session->customer ?? '';
        $subscriptionId = $session->subscription ?? '';

        if (!$userId || !$customerId) return;

        $precioMap = [
            'mensual'    => STRIPE_PRECIO_MENSUAL,
            'trimestral' => STRIPE_PRECIO_TRIMESTRAL,
            'anual'      => STRIPE_PRECIO_ANUAL,
        ];
        $precioEur = $precioMap[$plan] ?? STRIPE_PRECIO_MENSUAL;

        // Guardar customer_id en el usuario si aún no lo tenía
        $this->repo->setStripeCustomerId($userId, $customerId);

        // Idempotencia: solo insertar si no existe ya
        $existing = $this->repo->findByStripeSubscriptionId($subscriptionId);
        if (!$existing) {
            $this->repo->create([
                'usuario_id'             => $userId,
                'stripe_customer_id'     => $customerId,
                'stripe_subscription_id' => $subscriptionId,
                'plan'                   => $plan,
                'estado'                 => 'active',
                'precio_eur'             => $precioEur,
                'fecha_inicio'           => date('Y-m-d H:i:s'),
            ]);
        }
    }

    // ────────────────────────────────────────────────────────────
    // Handlers omitidos del extracto:
    //   - onSubscriptionUpdated(): sincroniza estado (active, past_due, canceled)
    //   - onSubscriptionDeleted(): marca fin de acceso premium
    //   - onInvoicePaid(): registra pago en tabla de facturación
    //   - onInvoicePaymentFailed(): notifica al usuario + al admin
    // ────────────────────────────────────────────────────────────
}
