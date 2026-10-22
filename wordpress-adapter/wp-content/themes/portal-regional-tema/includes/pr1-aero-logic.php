<?php
/**
 * PR1 AERO — lógica pura (sem WordPress, sem rede), testável em CLI.
 * Estados: live | stale | unavailable. Nunca fabrica dados.
 */
if (!defined('ABSPATH')) exit;
final class PR1_Aero_Logic {
  /** Limite documentado: dado com mais de 30 min é DESATUALIZADO (ageMin > 30). */
  public const STALE_MINUTES = 30;
  /** Tolerância para relógio adiantado da fonte. Acima disso o dado é inválido. */
  public const FUTURE_TOLERANCE_MINUTES = 5;
  public const RAINVIEWER_HOST = 'https://tilecache.rainviewer.com';

  /** @return string live|stale|unavailable */
  public static function freshness(?int $observed_ts, int $now_ts, int $limit_min = self::STALE_MINUTES): string {
    if ($observed_ts === null || $observed_ts <= 0) return 'unavailable';
    $age = ($now_ts - $observed_ts) / 60;
    if ($age < -self::FUTURE_TOLERANCE_MINUTES) return 'unavailable';
    return $age > $limit_min ? 'stale' : 'live';
  }
  public static function age_minutes(?int $observed_ts, int $now_ts): ?int {
    if ($observed_ts === null || $observed_ts <= 0) return null;
    return (int) floor(($now_ts - $observed_ts) / 60);
  }
  /** Converte "2026-10-22T12:15" (America/Sao_Paulo, sem offset, como devolve o Open-Meteo) em timestamp. */
  public static function local_to_ts(string $local, string $tz = 'America/Sao_Paulo'): ?int {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?$/D', $local)) return null;
    try { $d = new DateTimeImmutable($local, new DateTimeZone($tz)); } catch (Exception $e) { return null; }
    return $d->getTimestamp();
  }

  /** Agrupa a previsão horária em períodos (madrugada/manhã/tarde/noite) dos próximos dias. */
  public static function periods(array $hourly, int $max = 6, string $from_local = ''): array {
    $from = substr($from_local, 0, 13);
    $times = $hourly['time'] ?? null;
    if (!is_array($times)) return [];
    $names = [0 => 'Madrugada', 6 => 'Manhã', 12 => 'Tarde', 18 => 'Noite'];
    $buckets = [];
    foreach ($times as $i => $t) {
      if (!is_string($t) || !preg_match('/^(\d{4}-\d{2}-\d{2})T(\d{2})/', $t, $m)) continue;
      if ($from !== '' && substr($t, 0, 13) < $from) continue; // só do horário atual em diante
      $h = (int) $m[2]; $start = $h < 6 ? 0 : ($h < 12 ? 6 : ($h < 18 ? 12 : 18));
      $key = $m[1] . '-' . $start;
      if (!isset($buckets[$key])) $buckets[$key] = ['date' => $m[1], 'period' => $names[$start], 't' => [], 'p' => [], 'mm' => 0.0, 'w' => []];
      $b = &$buckets[$key];
      if (isset($hourly['temperature_2m'][$i]) && is_numeric($hourly['temperature_2m'][$i])) $b['t'][] = (float) $hourly['temperature_2m'][$i];
      if (isset($hourly['precipitation_probability'][$i]) && is_numeric($hourly['precipitation_probability'][$i])) $b['p'][] = (int) $hourly['precipitation_probability'][$i];
      if (isset($hourly['precipitation'][$i]) && is_numeric($hourly['precipitation'][$i])) $b['mm'] += (float) $hourly['precipitation'][$i];
      if (isset($hourly['wind_speed_10m'][$i]) && is_numeric($hourly['wind_speed_10m'][$i])) $b['w'][] = (float) $hourly['wind_speed_10m'][$i];
      unset($b);
    }
    $out = [];
    foreach ($buckets as $b) {
      if (!$b['t']) continue;
      $out[] = [
        'date' => $b['date'], 'period' => $b['period'],
        'temp_min' => round(min($b['t']), 1), 'temp_max' => round(max($b['t']), 1),
        'rain_probability_max' => $b['p'] ? max(0, min(100, max($b['p']))) : null,
        'rain_mm' => round($b['mm'], 1),
        'wind_max_kmh' => $b['w'] ? round(max($b['w']), 1) : null,
      ];
      if (count($out) >= $max) break;
    }
    return $out;
  }

  /** Valida o JSON do RainViewer e devolve quadros com host em allowlist. */
  public static function radar(array $json, int $now_ts): array {
    $host = $json['host'] ?? '';
    $past = $json['radar']['past'] ?? null;
    if ($host !== self::RAINVIEWER_HOST || !is_array($past) || !$past)
      return ['state' => 'unavailable', 'frames' => [], 'latest' => null, 'age_min' => null];
    $frames = [];
    foreach ($past as $f) {
      if (!isset($f['time'], $f['path']) || !is_int($f['time'])) continue;
      if (!preg_match('#^/v2/radar/[0-9a-f]+$#D', (string) $f['path'])) continue;
      $frames[] = ['time' => $f['time'], 'path' => $f['path']];
    }
    usort($frames, fn($a, $b) => $a['time'] <=> $b['time']);
    $frames = array_slice($frames, -6);
    $latest = $frames ? end($frames)['time'] : null;
    return ['state' => self::freshness($latest, $now_ts), 'frames' => $frames, 'latest' => $latest,
            'age_min' => self::age_minutes($latest, $now_ts), 'host' => self::RAINVIEWER_HOST];
  }

  private static function norm(string $s): string {
    $s = function_exists('iconv') ? (string) @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) : $s;
    return strtolower(trim(preg_replace('/\s+/', ' ', preg_replace('/[^A-Za-z0-9 ]/', ' ', $s))));
  }
  private static function in_ring(float $lon, float $lat, array $ring): bool {
    $in = false; $n = count($ring);
    for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
      if (!isset($ring[$i][1], $ring[$j][1])) continue;
      [$xi, $yi] = $ring[$i]; [$xj, $yj] = $ring[$j];
      if ((($yi > $lat) !== ($yj > $lat)) && ($lon < ($xj - $xi) * ($lat - $yi) / (($yj - $yi) ?: 1e-12) + $xi)) $in = !$in;
    }
    return $in;
  }
  public static function covers($geo, float $lat, float $lon): bool {
    if (is_string($geo)) { $geo = json_decode($geo, true); }
    if (!is_array($geo)) return false;
    $type = $geo['type'] ?? '';
    if ($type === 'Feature') return self::covers($geo['geometry'] ?? null, $lat, $lon);
    if ($type === 'FeatureCollection') { foreach ($geo['features'] ?? [] as $f) if (self::covers($f, $lat, $lon)) return true; return false; }
    $poly = function ($c) use ($lat, $lon) {
      if (!is_array($c) || !isset($c[0]) || !self::in_ring($lon, $lat, $c[0])) return false;
      for ($k = 1; $k < count($c); $k++) if (self::in_ring($lon, $lat, $c[$k])) return false;
      return true;
    };
    if ($type === 'Polygon') return $poly($geo['coordinates'] ?? null);
    if ($type === 'MultiPolygon') { foreach ($geo['coordinates'] ?? [] as $p) if ($poly($p)) return true; }
    return false;
  }

  /** Porta de lista() do AERO V13: aceita array, "[...]" ou texto simples. */
  public static function as_list($v): array {
    if (is_array($v)) return array_values(array_filter(array_map(fn($x) => trim((string) $x), $v), 'strlen'));
    $s = trim((string) $v);
    if ($s === '') return [];
    if ($s[0] === '[') {
      $j = json_decode(str_replace("'", '"', $s), true);
      if (is_array($j)) return self::as_list($j);
      $s = trim($s, '[]');
      return array_values(array_filter(array_map(fn($x) => trim($x, " '"), preg_split("/'\\s*,\\s*'/", $s)), 'strlen'));
    }
    return [$s];
  }
  private static function brt(string $s): ?int {
    if (!preg_match('/(\d{4}-\d{2}-\d{2})[ T](\d{2}:\d{2})/', $s, $m)) return null;
    return self::local_to_ts($m[1] . 'T' . $m[2]);
  }
  /**
   * Porta de filtrar() do AERO V13 (api/_lib/inmet.js): avisos ativos do INMET que cobrem
   * o ponto (polígono) ou o município (nome - UF). Ignora encerrados e vencidos. Nada é inventado.
   */
  public static function inmet_filter(array $json, float $lat, float $lon, string $city, string $uf, int $now_ts): array {
    $out = []; $seen = []; $target = self::norm($city) . ' ' . strtolower($uf);
    foreach (array_merge((array) ($json['hoje'] ?? []), (array) ($json['futuro'] ?? [])) as $a) {
      if (!is_array($a)) continue;
      $id = preg_replace('/\D/', '', (string) ($a['id'] ?? ''));
      if ($id === '' || isset($seen[$id])) continue;
      $seen[$id] = true;
      if (($a['encerrado'] ?? false) === true) continue;
      $byPoly = self::covers($a['poligono'] ?? null, $lat, $lon);
      $byCity = false;
      foreach (preg_split('/\),\s*/', (string) ($a['municipios'] ?? '')) as $m) {
        $n = self::norm(preg_replace('/\s*\(\d+\)?\s*$/', '', $m));
        if ($n !== '' && $n === $target) { $byCity = true; break; }
      }
      if (!$byPoly && !$byCity) continue;
      $ini = $a['inicio'] ?? (substr((string) ($a['data_inicio'] ?? ''), 0, 10) . ' ' . ($a['hora_inicio'] ?? '00:00'));
      $fim = $a['fim'] ?? (substr((string) ($a['data_fim'] ?? ''), 0, 10) . ' ' . ($a['hora_fim'] ?? '23:59'));
      $tIni = self::brt((string) $ini); $tFim = self::brt((string) $fim);
      if ($tFim !== null && $tFim < $now_ts) continue;
      $out[] = [
        'id' => $id,
        'event' => (string) ($a['descricao'] ?? 'Aviso meteorológico'),
        'severity' => (string) ($a['severidade'] ?? ''),
        'start' => $tIni ? gmdate('c', $tIni) : null,
        'end' => $tFim ? gmdate('c', $tFim) : null,
        'active_now' => $tIni !== null && $tFim !== null && $tIni <= $now_ts && $now_ts <= $tFim,
        'risks' => self::as_list($a['riscos'] ?? ''),
        'instructions' => self::as_list($a['instrucoes'] ?? ''),
        'area' => $city . ' - ' . strtoupper($uf),
        'coverage' => $byPoly ? 'polígono' : 'município',
        'source' => 'INMET',
        'url' => 'https://alertas2.inmet.gov.br/' . $id,
      ];
    }
    $w = fn($s) => preg_match('/grande/i', $s) ? 3 : (preg_match('/^perigo$/i', trim($s)) ? 2 : 1);
    usort($out, fn($x, $y) => [$y['active_now'], $w($y['severity']), (string) $x['start']] <=> [$x['active_now'], $w($x['severity']), (string) $y['start']]);
    return $out;
  }
}
