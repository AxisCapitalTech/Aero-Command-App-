<?php
/**
 * PR1 AERO nativo — endpoints REST somente leitura para a página meteorológica interna.
 * Fontes: Open-Meteo (previsão), RainViewer (radar), INMET (avisos oficiais).
 * Sem cron, sem push, sem gravação além de cache transitório. Nada é simulado.
 */
if (!defined('ABSPATH')) exit;
require_once __DIR__ . '/pr1-aero-logic.php';
final class PR1_Aero_Native {
  public static function init(): void {
    add_action('rest_api_init', function () {
      foreach (['forecast' => 'forecast', 'radar' => 'radar', 'alerts' => 'alerts', 'lightning' => 'lightning'] as $r => $cb)
        register_rest_route('pr1-aero/v1', '/' . $r, ['methods' => 'GET', 'callback' => [__CLASS__, $cb], 'permission_callback' => '__return_true']);
    });
  }
  private static function city($request) {
    $id = sanitize_key((string) $request->get_param('city'));
    $all = PR1_Aero_Bridge::cities();
    return isset($all[$id]) ? [$id, $all[$id]] : null;
  }
  private static function unavailable(string $code, string $msg, int $status, array $extra = []) {
    return new WP_REST_Response(array_merge(['state' => 'unavailable', 'code' => $code, 'message' => $msg,
      'source' => null, 'checked_at' => gmdate('c')], $extra), $status);
  }
  private static function get_json(string $url, int $limit = 400000) {
    $r = wp_safe_remote_get($url, ['timeout' => 8, 'redirection' => 0, 'limit_response_size' => $limit,
      'headers' => ['Accept' => 'application/json', 'User-Agent' => 'PR1NewsWeather/1.0']]);
    if (is_wp_error($r) || wp_remote_retrieve_response_code($r) !== 200) return null;
    $j = json_decode(wp_remote_retrieve_body($r), true);
    return is_array($j) ? $j : null;
  }

  public static function forecast($request) {
    if (PR1_Aero_Bridge::commercial_locked())
      return self::unavailable('weather_license_pending', 'Previsão indisponível até a regularização da licença comercial.', 503);
    $c = self::city($request);
    if (!$c) return self::unavailable('invalid_city', 'Cidade não disponível.', 400);
    [$id, $city] = $c;
    $key = 'pr1_aero_fc_' . $id;
    $data = get_transient($key);
    if (!is_array($data)) {
      $j = self::get_json(add_query_arg([
        'latitude' => $city['lat'], 'longitude' => $city['lon'],
        'current' => 'temperature_2m,relative_humidity_2m,apparent_temperature,precipitation,weather_code,wind_speed_10m,wind_gusts_10m',
        'hourly' => 'temperature_2m,precipitation_probability,precipitation,wind_speed_10m',
        'forecast_days' => 3, 'timezone' => 'America/Sao_Paulo'], 'https://api.open-meteo.com/v1/forecast'));
      $now = $j['current'] ?? null;
      if (!is_array($now) || !isset($now['time'], $now['temperature_2m']))
        return self::unavailable('weather_unavailable', 'Previsão temporariamente indisponível.', 503, ['source' => 'Open-Meteo']);
      $data = ['city' => $city['name'], 'city_id' => $id, 'lat' => $city['lat'], 'lon' => $city['lon'],
        'observed_local' => (string) $now['time'],
        'current' => [
          'temperature' => (float) $now['temperature_2m'],
          'apparent_temperature' => isset($now['apparent_temperature']) ? (float) $now['apparent_temperature'] : null,
          'humidity' => isset($now['relative_humidity_2m']) ? (int) $now['relative_humidity_2m'] : null,
          'precipitation_mm' => isset($now['precipitation']) ? (float) $now['precipitation'] : null,
          'wind_kmh' => isset($now['wind_speed_10m']) ? (float) $now['wind_speed_10m'] : null,
          'gust_kmh' => isset($now['wind_gusts_10m']) ? (float) $now['wind_gusts_10m'] : null,
          'weather_code' => isset($now['weather_code']) ? (int) $now['weather_code'] : null],
        'periods' => PR1_Aero_Logic::periods((array) ($j['hourly'] ?? []), 6, (string) $now['time']),
        'retrieved_at' => time()];
      set_transient($key, $data, 10 * MINUTE_IN_SECONDS);
    }
    $ts = PR1_Aero_Logic::local_to_ts($data['observed_local']);
    $data['observed_at'] = $ts ? gmdate('c', $ts) : null;
    $data['age_min'] = PR1_Aero_Logic::age_minutes($ts, time());
    $data['state'] = PR1_Aero_Logic::freshness($ts, time());
    $data['stale_limit_min'] = PR1_Aero_Logic::STALE_MINUTES;
    $data['source'] = 'Open-Meteo'; $data['source_url'] = 'https://open-meteo.com/'; $data['license'] = 'CC BY 4.0';
    $data['notice'] = 'Modelo meteorológico; não é aviso oficial.';
    return rest_ensure_response($data);
  }

  public static function radar($request) {
    if (PR1_Aero_Bridge::commercial_locked())
      return self::unavailable('radar_license_pending', 'Radar indisponível: a API pública do RainViewer é só para uso pessoal/educacional.', 503);
    $j = get_transient('pr1_aero_radar');
    if (!is_array($j)) {
      $j = self::get_json('https://api.rainviewer.com/public/weather-maps.json', 100000);
      if (!$j) return self::unavailable('radar_unavailable', 'Radar temporariamente indisponível.', 503, ['source' => 'RainViewer']);
      set_transient('pr1_aero_radar', $j, 5 * MINUTE_IN_SECONDS);
    }
    $r = PR1_Aero_Logic::radar($j, time());
    $r['source'] = 'RainViewer'; $r['source_url'] = 'https://www.rainviewer.com/'; $r['stale_limit_min'] = PR1_Aero_Logic::STALE_MINUTES;
    $r['latest_at'] = $r['latest'] ? gmdate('c', $r['latest']) : null;
    return new WP_REST_Response($r, $r['state'] === 'unavailable' ? 503 : 200);
  }

  public static function alerts($request) {
    $c = self::city($request);
    if (!$c) return self::unavailable('invalid_city', 'Cidade não disponível.', 400);
    [$id, $city] = $c;
    $j = get_transient('pr1_aero_inmet');
    if (!is_array($j)) {
      $j = self::get_json('https://apiprevmet3.inmet.gov.br/avisos/ativos', 3000000);
      if (!$j || (!isset($j['hoje']) && !isset($j['futuro'])))
        return self::unavailable('alerts_unavailable', 'Não foi possível consultar o INMET agora. Isso não significa ausência de avisos.', 503, ['source' => 'INMET']);
      set_transient('pr1_aero_inmet', $j, 5 * MINUTE_IN_SECONDS);
      set_transient('pr1_aero_inmet_at', time(), 5 * MINUTE_IN_SECONDS);
    }
    $at = (int) get_transient('pr1_aero_inmet_at');
    return rest_ensure_response(['state' => PR1_Aero_Logic::freshness($at ?: null, time()), 'source' => 'INMET', 'official' => true,
      'source_url' => 'https://alertas2.inmet.gov.br/', 'fetched_at' => $at ? gmdate('c', $at) : null,
      'city' => $city['name'], 'items' => PR1_Aero_Logic::inmet_filter($j, $city['lat'], $city['lon'], $city['name'], 'PR', time()),
      'civil_defense_url' => 'https://www.defesacivil.pr.gov.br/']);
  }

  /** O código da rota de raios do AERO (api/lightning) não foi recuperado: estado honesto. */
  public static function lightning($request) {
    return self::unavailable('lightning_source_missing', 'Raios indisponíveis: a fonte de descargas do AERO não está integrada a este portal.', 503, ['source' => null]);
  }
}
PR1_Aero_Native::init();
