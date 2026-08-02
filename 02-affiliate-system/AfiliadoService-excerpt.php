<?php
// app/services/AfiliadoService.php  —  EXTRACTO
//
// Extracto del AfiliadoService real (~252 líneas). Se muestra el ciclo
// de tracking: recibir click con ?ref=SLUG → validar → cookie 30 días →
// registrar en BBDD (silencioso si falla). Se omite la parte de atribución
// de venta al recibir pago en Stripe (registrarVentaParaUsuario) por
// brevedad, aunque sigue el mismo patrón defensivo.

declare(strict_types=1);

/**
 * Lógica de afiliación: detección de ?ref / /r/{slug}, cookie de
 * atribución de 30 días, vincular usuario nuevo a afiliado, y registrar
 * ventas cuando se reciben pagos en Stripe.
 *
 * El módulo está diseñado para "fallar silenciosamente": si algo va mal
 * con la atribución, NO debe romper el registro o el pago. La afiliación
 * es importante pero no es un proceso bloqueante.
 */
final class AfiliadoService
{
    public const COOKIE_NAME = 'simulaopo_ref';
    public const COOKIE_TTL  = 60 * 60 * 24 * 30; // 30 días

    private AfiliadoRepository $repo;

    public function __construct(\PDO $pdo)
    {
        $this->repo = new AfiliadoRepository($pdo);
    }

    // ────────────────────────────────────────────────────────────
    // TRACKING DE CLICKS
    // ────────────────────────────────────────────────────────────

    /**
     * Llamado al detectar un visitante con ?ref=slug (o que viene de /r/slug):
     *   1) verifica que el slug existe y está activo
     *   2) setea cookie de atribución (30 días)
     *   3) registra el click en BBDD (silencioso si falla)
     *
     * Devuelve true si se atribuyó correctamente, false si el slug no existe.
     */
    public function trackClick(string $slug): bool
    {
        $slug = $this->normalizeSlug($slug);
        if ($slug === '') return false;

        $afi = $this->repo->findBySlug($slug);
        if (!$afi || !$afi->activo) {
            return false;
        }

        // 1) Cookie primero — es lo más importante para la conversión
        $this->setCookie($slug);

        // 2) Registrar click en BD (best-effort, no debe bloquear)
        try {
            $this->repo->registerClick(
                (int)$afi->id,
                $this->hashIp(),
                $this->hashUserAgent(),
                $this->getReferrer(),
                $this->getLandingPath()
            );
        } catch (\Throwable $e) {
            error_log('[AfiliadoService] registerClick falló: ' . $e->getMessage());
        }

        return true;
    }

    /**
     * Lee la cookie de atribución del visitante y devuelve el afiliado
     * (o null si no hay cookie o si no corresponde a un afiliado activo).
     */
    public function getCookieAfiliado(): ?Afiliado
    {
        $slug = $_COOKIE[self::COOKIE_NAME] ?? null;
        if (!is_string($slug) || $slug === '') return null;
        $slug = $this->normalizeSlug($slug);
        if ($slug === '') return null;

        $afi = $this->repo->findBySlug($slug);
        return ($afi && $afi->activo) ? $afi : null;
    }

    // ────────────────────────────────────────────────────────────
    // UTILIDADES
    // ────────────────────────────────────────────────────────────

    /**
     * Normaliza un slug: minúsculas, sin espacios, solo [a-z0-9_-]{1,60}.
     * Devuelve cadena vacía si el slug no es válido — así, todo el resto
     * del código puede confiar en que el slug que recibe es "seguro".
     */
    public function normalizeSlug(string $raw): string
    {
        $s = strtolower(trim($raw));
        if ($s === '') return '';
        if (!preg_match('/^[a-z0-9_\-]{1,60}$/', $s)) return '';
        return $s;
    }

    private function setCookie(string $slug): void
    {
        if (headers_sent()) return;

        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])
                    && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

        setcookie(self::COOKIE_NAME, $slug, [
            'expires'  => time() + self::COOKIE_TTL,
            'path'     => '/',
            'secure'   => $isHttps,
            'httponly' => false, // permitir lectura JS por si hace falta analítica cliente
            'samesite' => 'Lax',
        ]);
        $_COOKIE[self::COOKIE_NAME] = $slug; // disponible en la misma request
    }

    /**
     * Hash de la IP con salt fijo — para poder detectar visitas repetidas
     * del mismo click sin almacenar la IP en claro (RGPD friendly).
     */
    private function hashIp(): string
    {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
        if (is_string($ip) && strpos($ip, ',') !== false) {
            $ip = trim(explode(',', $ip)[0]);
        }
        return hash('sha256', (string)$ip . '|simulaopo-afiliados');
    }

    private function hashUserAgent(): ?string
    {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        return (is_string($ua) && $ua !== '') ? hash('sha256', $ua) : null;
    }

    private function getReferrer(): ?string
    {
        $r = $_SERVER['HTTP_REFERER'] ?? '';
        return (is_string($r) && $r !== '') ? substr($r, 0, 500) : null;
    }

    private function getLandingPath(): ?string
    {
        $p = $_SERVER['REQUEST_URI'] ?? '';
        return (is_string($p) && $p !== '') ? substr($p, 0, 500) : null;
    }

    // ────────────────────────────────────────────────────────────
    // Métodos omitidos del extracto:
    //   - atribuirUsuarioRecienCreado(): asocia usuario nuevo a afiliado
    //   - registrarVentaParaUsuario(): registra comisión al recibir pago
    //   - Métodos de anulación de comisión al reembolsar (v3)
    // ────────────────────────────────────────────────────────────
}
