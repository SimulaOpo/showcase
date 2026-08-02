<?php
// public/webhook_stripe.php
// Endpoint para recibir eventos de Stripe (webhooks)
//
// URL configurada en Stripe Dashboard:
//   https://simulaopo.com/webhook_stripe.php
//
// Eventos suscritos:
//   - checkout.session.completed
//   - customer.subscription.created
//   - customer.subscription.updated
//   - customer.subscription.deleted
//   - invoice.payment_succeeded
//   - invoice.payment_failed

declare(strict_types=1);

// No usar sesión en el webhook (no es una petición de usuario)
require_once __DIR__ . '/../app/config/config.php';
require_once APP_PATH . '/services/StripeService.php';

// Stripe envía el body como raw JSON
$payload = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

if (empty($payload) || empty($sigHeader)) {
    http_response_code(400);
    echo json_encode(['error' => 'No payload or signature']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = getPDO();
    $stripeService = new StripeService($pdo);

    $result = $stripeService->handleWebhookEvent($payload, $sigHeader);

    if ($result['success']) {
        http_response_code(200);
        echo json_encode(['received' => true, 'type' => $result['type'] ?? '']);
    } else {
        http_response_code(400);
        echo json_encode(['error' => $result['error'] ?? 'Unknown error']);
    }
} catch (\Throwable $e) {
    error_log('Webhook Stripe error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
