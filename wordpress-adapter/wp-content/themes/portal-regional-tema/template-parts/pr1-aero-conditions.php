<?php if (!defined('ABSPATH')) exit; ?>
<div class="pr1-aero-weather" data-aero-widget data-endpoint="<?php echo esc_url(rest_url('pr1-aero/v1/conditions')); ?>">
 <div class="pr1-aero-city-tabs" role="group" aria-label="Consultar previsão por cidade">
 <?php foreach(['arapongas'=>'Arapongas','londrina'=>'Londrina','maringa'=>'Maringá','apucarana'=>'Apucarana'] as $code=>$city): ?>
 <button type="button" class="pr1-aero-city" data-city="<?php echo esc_attr($code); ?>" aria-pressed="<?php echo $code==='arapongas'?'true':'false'; ?>"><?php echo esc_html($city); ?></button>
 <?php endforeach; ?>
 </div>
 <div class="pr1-aero-reading" role="status" aria-live="polite">Consultando previsão regional…</div>
 <p class="pr1-aero-source">Fonte: Open-Meteo · previsão meteorológica, não aviso oficial. <a href="https://open-meteo.com/" target="_blank" rel="noopener noreferrer">Open-Meteo ↗</a> · <a href="https://creativecommons.org/licenses/by/4.0/" target="_blank" rel="noopener noreferrer">CC BY 4.0</a></p>
</div>
