<?php
declare(strict_types=1);

/**
 * app/security/cloudflare_ip.php
 *
 * Cuando el sitio se sirve a través del proxy de Cloudflare, la conexión TCP
 * que llega al servidor proviene de una IP de Cloudflare, no del visitante.
 * Como resultado, $_SERVER['REMOTE_ADDR'] es la IP de Cloudflare y todo el
 * código que dependa de la IP del usuario (rate_limit, atribución de
 * afiliados, logging, password reset, etc.) queda roto.
 *
 * Cloudflare pone la IP real del visitante en la cabecera CF-Connecting-IP.
 * Este helper la restaura en $_SERVER['REMOTE_ADDR'], pero SOLO si la
 * conexión TCP proviene efectivamente de un rango conocido de Cloudflare —
 * si no lo hiciésemos, cualquiera podría spoofear la cabecera enviándola
 * directamente contra la IP del servidor para saltarse rate limits o
 * falsear el sistema de afiliados.
 *
 * Rangos de IPs: https://www.cloudflare.com/ips/
 * Actualizados en 2026. Revisar cada 12-18 meses.
 */

const CLOUDFLARE_IPV4_RANGES = [
    '173.245.48.0/20',
    '103.21.244.0/22',
    '103.22.200.0/22',
    '103.31.4.0/22',
    '141.101.64.0/18',
    '108.162.192.0/18',
    '190.93.240.0/20',
    '188.114.96.0/20',
    '197.234.240.0/22',
    '198.41.128.0/17',
    '162.158.0.0/15',
    '104.16.0.0/13',
    '104.24.0.0/14',
    '172.64.0.0/13',
    '131.0.72.0/22',
];

const CLOUDFLARE_IPV6_RANGES = [
    '2400:cb00::/32',
    '2606:4700::/32',
    '2803:f800::/32',
    '2405:b500::/32',
    '2405:8100::/32',
    '2a06:98c0::/29',
    '2c0f:f248::/32',
];

/**
 * Comprueba si una IP está dentro de un rango CIDR (IPv4 o IPv6).
 */
function ip_in_cidr(string $ip, string $cidr): bool
{
    if (strpos($cidr, '/') === false) return false;
    [$subnet, $maskStr] = explode('/', $cidr, 2);
    $mask = (int)$maskStr;

    $isIpv4 = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
    $subnetIsIpv4 = filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;

    if ($isIpv4 && $subnetIsIpv4) {
        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        if ($ipLong === false || $subnetLong === false) return false;
        if ($mask <= 0)  return true;
        if ($mask >= 32) return $ipLong === $subnetLong;
        $maskLong = -1 << (32 - $mask);
        return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
    }

    $isIpv6 = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
    $subnetIsIpv6 = filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
    if ($isIpv6 && $subnetIsIpv6) {
        $ipBin = inet_pton($ip);
        $subnetBin = inet_pton($subnet);
        if ($ipBin === false || $subnetBin === false) return false;
        $bytes = intdiv($mask, 8);
        $bits  = $mask % 8;
        if ($bytes > 0 && substr($ipBin, 0, $bytes) !== substr($subnetBin, 0, $bytes)) {
            return false;
        }
        if ($bits === 0) return true;
        $shift = 8 - $bits;
        return ((ord($ipBin[$bytes]) >> $shift) === (ord($subnetBin[$bytes]) >> $shift));
    }

    return false;
}

/**
 * ¿La IP dada pertenece a alguno de los rangos de Cloudflare?
 */
function is_cloudflare_ip(string $ip): bool
{
    if ($ip === '') return false;
    $ranges = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false
        ? CLOUDFLARE_IPV6_RANGES
        : CLOUDFLARE_IPV4_RANGES;
    foreach ($ranges as $range) {
        if (ip_in_cidr($ip, $range)) return true;
    }
    return false;
}

/**
 * Restaura la IP real del visitante en $_SERVER['REMOTE_ADDR'] cuando la
 * petición llega vía Cloudflare. Guarda la IP original de Cloudflare en
 * $_SERVER['CF_PROXY_IP'] por si hace falta para debug o log.
 *
 * Solo actúa si:
 *   1) $_SERVER['REMOTE_ADDR'] existe y es una IP de Cloudflare (validado
 *      contra los rangos oficiales).
 *   2) $_SERVER['HTTP_CF_CONNECTING_IP'] existe y es una IP válida.
 *
 * Si la petición viene por otro camino (local dev, DNS-only, acceso directo
 * a la IP del servidor con cabecera spoofeada, etc.) NO se toca $REMOTE_ADDR.
 */
function restore_cloudflare_client_ip(): void
{
    $remote = $_SERVER['REMOTE_ADDR'] ?? '';
    $cfIp   = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '';

    if ($remote === '' || $cfIp === '') return;
    if (filter_var($cfIp, FILTER_VALIDATE_IP) === false) return;
    if (!is_cloudflare_ip($remote)) return;

    $_SERVER['CF_PROXY_IP'] = $remote;
    $_SERVER['REMOTE_ADDR'] = $cfIp;
}
