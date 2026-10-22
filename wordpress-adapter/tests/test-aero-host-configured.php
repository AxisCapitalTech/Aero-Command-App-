<?php
/** Testa a formação do endereço HTTPS quando o operador define um domínio. */
define('ABSPATH', __DIR__.'/');
define('PR1_AERO_HOST', 'Clima.Example.ORG');
function add_action(...$args){} function add_filter(...$args){}
function esc_url_raw($url){return $url;}
require_once __DIR__.'/../wp-content/themes/portal-regional-tema/includes/pr1-aero-bridge.php';
$actual=PR1_Aero_Bridge::app_url();
if ($actual!=='https://clima.example.org/') {
  fwrite(STDERR,"FAIL configured URL: $actual\n");
  exit(1);
}
echo "OK endereco AERO usa HTTPS e somente host configurado\n";
