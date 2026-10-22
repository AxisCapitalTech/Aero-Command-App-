<?php
/** PR1 AERO — trava de licença comercial em produção nos endpoints nativos (sem rede). */
define('ABSPATH', __DIR__.'/');
$GLOBALS['env']='production'; $GLOBALS['http_calls']=0;
function add_action(...$a){} function add_filter(...$a){} function register_rest_route(...$a){}
function esc_url_raw($u){return $u;} function sanitize_key($k){return strtolower(preg_replace('/[^a-z0-9_\-]/i','',$k));}
function wp_get_environment_type(){return $GLOBALS['env'];}
function wp_safe_remote_get(...$a){$GLOBALS['http_calls']++; return new WP_Error('x','offline');}
function is_wp_error($x){return $x instanceof WP_Error;}
function get_transient($k){return false;} function set_transient(...$a){return true;}
function add_query_arg($q,$u){return $u.'?'.http_build_query($q);}
function rest_ensure_response($d){return new WP_REST_Response($d,200);}
function wp_remote_retrieve_response_code($r){return 0;} function wp_remote_retrieve_body($r){return '';}
class WP_Error{public $code;function __construct($c='',$m='',$d=[]){$this->code=$c;}}
class WP_REST_Response{public $data;public $status;function __construct($d=null,$s=200){$this->data=$d;$this->status=$s;}}
class Req{private $p;function __construct($p){$this->p=$p;}function get_param($k){return $this->p[$k]??null;}}
define('MINUTE_IN_SECONDS',60);
require_once __DIR__.'/../wp-content/themes/portal-regional-tema/includes/pr1-aero-bridge.php';
require_once __DIR__.'/../wp-content/themes/portal-regional-tema/includes/pr1-aero-native.php';
$fail=0; function ok($c,$l){global $fail; if(!$c){fwrite(STDERR,"FAIL $l\n");$fail++;} else echo "OK $l\n";}
ok(PR1_Aero_Bridge::commercial_locked()===true,'produção sem PR1_AERO_COMMERCIAL_API_APPROVED = travado');
$f=PR1_Aero_Native::forecast(new Req(['city'=>'arapongas']));
ok($f->status===503&&$f->data['code']==='weather_license_pending'&&$f->data['state']==='unavailable','previsão bloqueada em produção');
ok(array_key_exists('source',$f->data)&&!empty($f->data['checked_at'])&&strtotime($f->data['checked_at'])!==false,'JSON de erro: fonte e horário explícitos');
$r=PR1_Aero_Native::radar(new Req([]));
ok($r->status===503&&$r->data['code']==='radar_license_pending','radar RainViewer bloqueado em produção');
ok($GLOBALS['http_calls']===0,'nenhuma chamada externa com licença pendente');
$l=PR1_Aero_Native::lightning(new Req([]));
ok($l->status===503&&$l->data['state']==='unavailable','raios: INDISPONÍVEL honesto (fonte não recuperada)');
$GLOBALS['env']='staging';
ok(PR1_Aero_Bridge::commercial_locked()===false,'homologação liberada para teste');
$a=PR1_Aero_Native::alerts(new Req(['city'=>'arapongas']));
ok($a->status===503&&$a->data['state']==='unavailable'&&strpos($a->data['message'],'não significa ausência')!==false,'INMET fora do ar = INDISPONÍVEL, nunca "sem avisos"');
$x=PR1_Aero_Native::forecast(new Req(['city'=>'../etc']));
ok($x->status===400,'cidade inválida rejeitada');
$f=PR1_Aero_Native::forecast(new Req(['city'=>'londrina']));
ok($f->status===503&&$f->data['state']==='unavailable','Open-Meteo fora do ar = INDISPONÍVEL');
if($fail)exit(1); echo "OK PR1 AERO: licença e falhas honestas\n";
