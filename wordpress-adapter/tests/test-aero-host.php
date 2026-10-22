<?php
/** Testa configuração fail closed do AERO sem WordPress nem rede. */
define('ABSPATH', __DIR__.'/');
function add_action(...$args){} function add_filter(...$args){}
function esc_url_raw($url){return $url;}
require_once __DIR__.'/../wp-content/themes/portal-regional-tema/includes/pr1-aero-bridge.php';
function check_hostname($passed, $label){
  if (!$passed){fwrite(STDERR,"FAIL $label\n");exit(1);}
  echo "OK $label\n";
}
check_hostname(PR1_Aero_Bridge::app_url()==='', 'AERO desativado sem constante');
foreach (['aero.example.org', 'Clima.Example.ORG', 'portal.exemplo.com.br'] as $host) {
 check_hostname(PR1_Aero_Bridge::validated_host($host)===strtolower($host), 'host seguro: '.$host);
}
foreach (['','localhost','127.0.0.1','192.168.1.1','https://clima.example.org','aero.example.org:443','aero.example.org/painel','aero.example.org?x=1','foo..example.org','-foo.example.org','foo-.example.org','example.c','example.org#hash','example.org@evil.net'] as $bad) {
 check_hostname(PR1_Aero_Bridge::validated_host($bad)==='', 'rejeita: '.($bad===''?'(vazio)':$bad));
}
echo "OK host AERO sem redirecionamento arbitrario\n";
