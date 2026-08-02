<?php
// public/includes/banner_trial.php
//
// Cronómetro del trial gratuito de 7 días. Se muestra solo si:
//   - Usuario logueado
//   - NO es pagador (suscripción premium activa)
//   - Está en trial (trial_iniciado_at en los últimos TRIAL_DIAS días)
//   - NO tiene cookie de afiliado activa (los afiliados tienen otro descuento)
//
// Incluir desde la página correspondiente (test.php, mi_cuenta.php).
// Llamar antes con require_once de premium.php (que normalmente ya está cargado).

declare(strict_types=1);

if (!function_exists('isPremium')) {
    require_once APP_PATH . '/helpers/premium.php';
}

// No mostrar si no hay sesión
if (empty($_SESSION['usuario_id'])) return;

// Trigger la query y cacheo (también setea es_pagador, en_trial, trial_iniciado_at)
isPremium();

// No mostrar a pagadores reales
if (!empty($_SESSION['es_pagador'])) return;

// No mostrar si no está en trial activo
if (empty($_SESSION['en_trial'])) return;

// Si el usuario fue atribuido a un afiliado al registrarse, su descuento es el
// afi (permanente, no caduca con el trial) → no mostrar este banner. El check
// se hace contra la BD (usuarios.afiliado_id), NO contra la cookie, porque la
// cookie se borra automáticamente tras el registro en atribuirUsuarioRecienCreado.
try {
    $stmt = getPDO()->prepare('SELECT afiliado_id FROM usuarios WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => (int) $_SESSION['usuario_id']]);
    if ($stmt->fetchColumn()) {
        return; // tiene afiliado_id → el banner que verá es el del afiliado, no el del trial
    }
} catch (\Throwable $e) {
    error_log('[banner_trial] check afiliado_id: ' . $e->getMessage());
}

// Calcular segundos restantes
$segRest = function_exists('trialSegundosRestantes') ? trialSegundosRestantes() : 0;
if ($segRest <= 0) return;

$diasRest  = (int) floor($segRest / 86400);
$horasRest = (int) floor(($segRest % 86400) / 3600);
$baseUrl   = rtrim((string) BASE_URL, '/');
?>
<div id="banner-trial" role="region" aria-label="Trial gratuito activo">
    <div class="banner-trial-inner">
        <div class="banner-trial-text">
            ⏳ Tienes <strong><span data-trial-countdown="dh"><?= $diasRest ?>d <?= $horasRest ?>h</span></strong> de acceso total.
            Suscríbete al plan <strong>Mensual</strong> antes de que acabe y consigue un <strong>25 %</strong> de descuento en tu primer pago.
        </div>
        <a class="banner-trial-cta" href="<?= $baseUrl ?>/planes.php">Ver planes &rarr;</a>
    </div>
</div>
<style>
#banner-trial {
    background: linear-gradient(135deg, #fef3c7, #fde68a);
    border-bottom: 1px solid #fbbf24;
    padding: .7rem 1rem;
    font-size: .92rem;
    color: #78350f;
    text-align: center;
    line-height: 1.5;
}
.banner-trial-inner {
    max-width: 1100px;
    margin: 0 auto;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: .9rem;
    flex-wrap: wrap;
}
.banner-trial-cta {
    background: #1e3a5f;
    color: #fff !important;
    padding: .35rem .9rem;
    border-radius: 999px;
    font-weight: 600;
    text-decoration: none;
    font-size: .85rem;
    white-space: nowrap;
    transition: background .15s;
}
.banner-trial-cta:hover { background: #0f172a; }
@media (max-width: 600px) {
    #banner-trial { font-size: .82rem; padding: .6rem .8rem; }
    .banner-trial-cta { font-size: .78rem; padding: .3rem .75rem; }
}
</style>
<script>
(function(){
    // Tick visual cada minuto para que el contador (d/h) se vaya bajando.
    // El segundo restante exacto lo calculamos cliente-side desde la marca
    // inicial puesta por el servidor.
    var spans = document.querySelectorAll('[data-trial-countdown]');
    if (!spans.length) return;
    var endTs = <?= time() + $segRest ?> * 1000;

    function actualizar(){
        var rest = Math.max(0, endTs - Date.now());
        if (rest <= 0) {
            var b = document.getElementById('banner-trial');
            if (b) b.remove();
            return;
        }
        var d = Math.floor(rest / 86400000);
        var h = Math.floor((rest % 86400000) / 3600000);
        var m = Math.floor((rest % 3600000) / 60000);
        spans.forEach(function(s){
            var fmt = s.getAttribute('data-trial-countdown');
            if (fmt === 'dh') {
                s.textContent = (d > 0)
                    ? (d + 'd ' + h + 'h')
                    : (h + 'h ' + m + 'min');
            }
        });
    }
    setInterval(actualizar, 60000);
})();
</script>
