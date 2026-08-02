<?php
// app/helpers/afiliado_ref_capture.php
//
// Captura ?ref=SLUG en cualquier URL (no solo /r/SLUG) y, si el slug
// es válido y activo, registra el click y setea la cookie de atribución.
//
// Llamar al principio de header.php — fail silently si algo va mal,
// no debe bloquear el render de la página.

declare(strict_types=1);

if (!function_exists('capture_afiliado_ref')) {
    function capture_afiliado_ref(): void
    {
        $ref = $_GET['ref'] ?? '';
        if (!is_string($ref) || $ref === '') return;

        try {
            require_once APP_PATH . '/services/AfiliadoService.php';
            $svc = new AfiliadoService(getPDO());
            $svc->trackClick($ref);   // normaliza, valida, setea cookie 30d
        } catch (\Throwable $e) {
            error_log('[capture_afiliado_ref] ' . $e->getMessage());
        }
    }
}
