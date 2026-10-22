<?php
/** PR1 AERO — bridge read-only. Sem decisões de segurança nem alertas simulados. */
if (!defined('ABSPATH')) exit;
final class PR1_Aero_Bridge {
  private const CITIES=[
    'arapongas'=>['name'=>'Arapongas','lat'=>-23.4197,'lon'=>-51.4242],
    'londrina'=>['name'=>'Londrina','lat'=>-23.3103,'lon'=>-51.1628],
    'maringa'=>['name'=>'Maringá','lat'=>-23.4205,'lon'=>-51.9333],
    'apucarana'=>['name'=>'Apucarana','lat'=>-23.5508,'lon'=>-51.4608]
  ];
  public static function init():void {
    add_action('rest_api_init', function(){
      register_rest_route('pr1-aero/v1','/conditions',[
        'methods'=>'GET','callback'=>[__CLASS__,'conditions'],
        'permission_callback'=>'__return_true'
      ]);
    });
    add_filter('query_vars',function($vars){$vars[]='pr1_aero';return $vars;});
    add_filter('pre_handle_404',function($override){return get_query_var('pr1_aero') ? true : $override;});
    add_filter('template_include',function($template){
      if(get_query_var('pr1_aero')){
        $found=get_template_directory().'/page-pr1-aero.php';
        if(file_exists($found))return $found;
      }
      return $template;
    });
  }
  public static function city_ids():array {return array_keys(self::CITIES);}
  /** Lista pública (somente leitura) das cidades monitoradas. */
  public static function cities():array {return self::CITIES;}
  /** Trava de licença comercial (Open-Meteo/RainViewer gratuitos = uso não comercial). */
  public static function commercial_locked():bool {
    return wp_get_environment_type()==='production'
      && (!defined('PR1_AERO_COMMERCIAL_API_APPROVED') || PR1_AERO_COMMERCIAL_API_APPROVED!==true);
  }
  public static function portal_url():string {return add_query_arg('pr1_aero','1',home_url('/'));}
  /** Only this configured hostname may be used for navigation or embeds. */
  public static function validated_host(string $raw):string {
    $host=strtolower(trim($raw));
    if(strlen($host)<4||strlen($host)>253||strpos($host,'.')===false)return '';
    if(!preg_match('/^[a-z0-9-]+(?:\\.[a-z0-9-]+)+$/D',$host))return '';
    if(!filter_var($host,FILTER_VALIDATE_DOMAIN,FILTER_FLAG_HOSTNAME))return '';
    if(filter_var($host,FILTER_VALIDATE_IP))return '';
    if(preg_match('/^[0-9.]+$/D',$host))return '';
    $labels=explode('.',$host);
    if(strlen(end($labels))<2)return '';
    return $host;
  }
  public static function app_url():string {
    if(!defined('PR1_AERO_HOST')||!is_string(PR1_AERO_HOST))return '';
    $host=self::validated_host(PR1_AERO_HOST);
    return $host!==''?esc_url_raw('https://'.$host.'/'):'';
  }
  public static function conditions($request) {
    // A API gratuita Open-Meteo só permite uso não comercial. Site com anúncios
    // ou assinaturas requer plano/licença comercial configurado antes de usar.
    if(self::commercial_locked())
      return new WP_Error('weather_license_pending','Serviço climático indisponível até a regularização da licença comercial.',['status'=>503]);
    $id=sanitize_key((string)$request->get_param('city'));
    if(!isset(self::CITIES[$id]))return new WP_Error('invalid_city','Cidade não disponível.',['status'=>400]);
    $cache='pr1_aero_cond_'.$id;
    $cached=get_transient($cache);
    if(is_array($cached)&&isset($cached['temperature']))return rest_ensure_response($cached);
    $city=self::CITIES[$id];
    $query=[
      'latitude'=>$city['lat'],'longitude'=>$city['lon'],
      'current'=>'temperature_2m,relative_humidity_2m,apparent_temperature,precipitation,weather_code,wind_speed_10m',
      'hourly'=>'precipitation_probability','forecast_days'=>1,'timezone'=>'America/Sao_Paulo'
    ];
    $url=add_query_arg($query,'https://api.open-meteo.com/v1/forecast');
    $response=wp_safe_remote_get($url,[
      'timeout'=>8,'redirection'=>0,'limit_response_size'=>200000,
      'headers'=>['Accept'=>'application/json','User-Agent'=>'PR1NewsWeather/0.9']
    ]);
    if(is_wp_error($response)||wp_remote_retrieve_response_code($response)!==200)
      return new WP_Error('weather_unavailable','Previsão temporariamente indisponível.',['status'=>503]);
    $json=json_decode(wp_remote_retrieve_body($response),true);
    $now=is_array($json)?($json['current']??[]):[];
    if(!is_array($now)||!isset($now['temperature_2m'],$now['time'],$now['weather_code']))
      return new WP_Error('weather_invalid','A fonte não retornou dados suficientes.',['status'=>503]);
    $hourly=$json['hourly']??[];
    $probability=null;
    if(is_array($hourly)&&isset($hourly['time'],$hourly['precipitation_probability'])&&is_array($hourly['time'])){
      $hour=substr((string)$now['time'],0,13).':00';
      $idx=array_search($hour,$hourly['time'],true);
      if($idx!==false && isset($hourly['precipitation_probability'][$idx])){
        $probability=max(0,min(100,(int)$hourly['precipitation_probability'][$idx]));
      }
    }
    $data=[
      'city'=>$city['name'],'city_id'=>$id,'source'=>'Open-Meteo',
      'observed_at'=>substr((string)$now['time'],0,19),
      'temperature'=>round((float)$now['temperature_2m'],1),
      'apparent_temperature'=>isset($now['apparent_temperature'])?round((float)$now['apparent_temperature'],1):null,
      'humidity'=>isset($now['relative_humidity_2m'])?(int)$now['relative_humidity_2m']:null,
      'wind_kmh'=>isset($now['wind_speed_10m'])?round((float)$now['wind_speed_10m'],1):null,
      'rain_mm'=>isset($now['precipitation'])?round((float)$now['precipitation'],1):null,
      'rain_probability'=>$probability,'weather_code'=>(int)$now['weather_code'],
      'retrieved_at'=>gmdate('c'),
      'notice'=>'Dados de modelo meteorológico; não são alertas oficiais de risco.'
    ];
    set_transient($cache,$data,10*MINUTE_IN_SECONDS);
    return rest_ensure_response($data);
  }
}
PR1_Aero_Bridge::init();
