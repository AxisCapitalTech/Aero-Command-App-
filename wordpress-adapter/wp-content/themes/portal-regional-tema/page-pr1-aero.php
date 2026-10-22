<?php if(!defined('ABSPATH'))exit; get_header();
$aero_url=PR1_Aero_Bridge::app_url();
$cities=PR1_Aero_Bridge::cities();
$api=[]; foreach(['forecast','radar','alerts','lightning'] as $e){$api[$e]=rest_url('pr1-aero/v1/'.$e);} ?>
<main id="conteudo" class="wrap pr1-main pr1-aero-page">
 <nav class="pr1-breadcrumb" aria-label="Você está aqui"><a href="<?php echo esc_url(home_url('/')); ?>">PR1 News</a> <span>/</span> Meteorologia</nav>
 <section class="pr1-aero-top"><span class="pr1-kicker">METEOROLOGIA REGIONAL</span><h1>AERO Command Center</h1>
  <p>Mapa, radar, previsão e avisos oficiais dentro do PR1 News. Cada bloco mostra a fonte e o horário; dados antigos aparecem como DESATUALIZADO e fontes fora do ar como INDISPONÍVEL.</p>
  <div class="pr1-aero-buttons"><a href="<?php echo esc_url(home_url('/')); ?>">← Voltar às notícias</a><button type="button" data-aero-expand aria-pressed="false">Ampliar painel</button></div>
 </section>

 <div class="pr1-aero-app" data-aero-app <?php foreach($api as $e=>$u): ?>data-api-<?php echo esc_attr($e); ?>="<?php echo esc_url($u); ?>" <?php endforeach; ?> data-default-city="arapongas">
  <div class="pr1-aero-watch" role="group" aria-label="Cidades monitoradas (Watch List)">
   <?php foreach($cities as $id=>$c): ?>
   <button type="button" class="pr1-aero-city" data-aero-city="<?php echo esc_attr($id); ?>" data-lat="<?php echo esc_attr($c['lat']); ?>" data-lon="<?php echo esc_attr($c['lon']); ?>" aria-pressed="<?php echo $id==='arapongas'?'true':'false'; ?>"><?php echo esc_html($c['name']); ?></button>
   <?php endforeach; ?>
  </div>
  <nav class="pr1-aero-tabs" role="tablist" aria-label="Seções do AERO">
   <?php foreach(['mapa'=>'Mapa e radar','previsao'=>'Previsão','alertas'=>'Avisos oficiais','raios'=>'Raios','brief'=>'AERO Brief'] as $k=>$label): ?>
   <button type="button" role="tab" id="aero-tab-<?php echo esc_attr($k); ?>" aria-controls="aero-panel-<?php echo esc_attr($k); ?>" aria-selected="<?php echo $k==='mapa'?'true':'false'; ?>" data-aero-tab="<?php echo esc_attr($k); ?>"><?php echo esc_html($label); ?></button>
   <?php endforeach; ?>
  </nav>

  <section class="pr1-aero-panel" id="aero-panel-mapa" role="tabpanel" aria-labelledby="aero-tab-mapa">
   <div class="pr1-aero-panel-head"><h2>Mapa e radar de chuva</h2><span class="pr1-aero-badge" data-aero-badge="radar">…</span></div>
   <div class="pr1-aero-layers" role="group" aria-label="Camadas do mapa">
    <label><input type="checkbox" data-aero-layer="radar" checked> Radar de chuva</label>
    <label><input type="checkbox" data-aero-layer="cities" checked> Cidades</label>
    <button type="button" data-aero-radar-play aria-pressed="false">▶ Animar radar</button>
   </div>
   <div class="pr1-aero-map" data-aero-map role="region" aria-label="Mapa meteorológico interativo"></div>
   <p class="pr1-aero-meta" data-aero-meta="radar" role="status" aria-live="polite">Carregando radar…</p>
  </section>

  <section class="pr1-aero-panel" id="aero-panel-previsao" role="tabpanel" aria-labelledby="aero-tab-previsao" hidden>
   <div class="pr1-aero-panel-head"><h2>Previsão</h2><span class="pr1-aero-badge" data-aero-badge="forecast">…</span></div>
   <div class="pr1-aero-now" data-aero-now role="status" aria-live="polite">Consultando previsão…</div>
   <div class="pr1-aero-periods" data-aero-periods></div>
   <p class="pr1-aero-meta" data-aero-meta="forecast"></p>
  </section>

  <section class="pr1-aero-panel" id="aero-panel-alertas" role="tabpanel" aria-labelledby="aero-tab-alertas" hidden>
   <div class="pr1-aero-panel-head"><h2>Avisos oficiais</h2><span class="pr1-aero-badge" data-aero-badge="alerts">…</span></div>
   <div data-aero-alerts role="status" aria-live="polite">Consultando INMET…</div>
   <p class="pr1-aero-meta" data-aero-meta="alerts"></p>
  </section>

  <section class="pr1-aero-panel" id="aero-panel-raios" role="tabpanel" aria-labelledby="aero-tab-raios" hidden>
   <div class="pr1-aero-panel-head"><h2>Raios</h2><span class="pr1-aero-badge" data-aero-badge="lightning">…</span></div>
   <div data-aero-lightning role="status" aria-live="polite">Consultando…</div>
  </section>

  <section class="pr1-aero-panel" id="aero-panel-brief" role="tabpanel" aria-labelledby="aero-tab-brief" hidden>
   <div class="pr1-aero-panel-head"><h2>AERO Brief</h2><span class="pr1-aero-badge" data-aero-badge="brief">…</span></div>
   <p class="pr1-aero-meta">Resumo automático montado só com os dados reais desta página (sem IA). Não é aviso oficial.</p>
   <div data-aero-brief role="status" aria-live="polite">Aguardando dados…</div>
  </section>
 </div>

 <section class="pr1-aero-inline" aria-labelledby="pr1-aero-forecast-title">
  <h2 id="pr1-aero-forecast-title">Condições nas cidades</h2>
  <?php get_template_part('template-parts/pr1-aero-conditions'); ?>
 </section>

 <?php if($aero_url!==''): ?>
 <details class="pr1-aero-frame-section"><summary id="pr1-aero-embed-title">Compatibilidade: painel AERO original (externo)</summary>
  <p>Recurso secundário. O painel nativo acima é a versão principal. Se o serviço externo impedir a exibição incorporada, use o link.</p>
  <p><a href="<?php echo esc_url($aero_url); ?>" target="_blank" rel="noopener noreferrer" class="pr1-aero-open">Abrir AERO completo ↗</a></p>
  <button type="button" class="pr1-aero-load" data-aero-load>Carregar painel externo</button>
  <div class="pr1-aero-embed" data-aero-container>
   <iframe title="AERO Command Center — painel externo" loading="lazy"
    data-src="<?php echo esc_url($aero_url); ?>" sandbox="allow-scripts allow-same-origin allow-forms allow-popups" referrerpolicy="no-referrer" allow="geolocation 'none'"></iframe>
  </div>
 </details>
 <?php else: ?><p class="pr1-aero-disclaimer" role="status">Painel AERO externo desativado até configurar PR1_AERO_HOST no wp-config.php.</p><?php endif; ?>
 <p class="pr1-aero-disclaimer"><strong>Aviso:</strong> não transforme previsão ou índices do AERO em alerta oficial. Para ocorrências perigosas, consulte o <a href="https://alertas2.inmet.gov.br/" target="_blank" rel="noopener noreferrer">INMET</a>, a <a href="https://www.defesacivil.pr.gov.br/" target="_blank" rel="noopener noreferrer">Defesa Civil do Paraná</a> (telefone 199) e demais autoridades.</p>
</main>
<?php get_footer(); ?>
