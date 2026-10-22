<?php
/** PR1 AERO nativo — lógica pura: estados LIVE/DESATUALIZADO/INDISPONÍVEL, radar, períodos e INMET (fixture). Sem rede. */
define('ABSPATH', __DIR__.'/');
require_once __DIR__.'/../wp-content/themes/portal-regional-tema/includes/pr1-aero-logic.php';
$fail=0;
function ok($c,$l){global $fail; if(!$c){fwrite(STDERR,"FAIL $l\n");$fail++;} else echo "OK $l\n";}
$now=1792683000; // instante fixo
ok(PR1_Aero_Logic::STALE_MINUTES===30,'limite documentado = 30 min');
ok(PR1_Aero_Logic::freshness($now,$now)==='live','agora = LIVE');
ok(PR1_Aero_Logic::freshness($now-30*60,$now)==='live','30 min exatos = LIVE');
ok(PR1_Aero_Logic::freshness($now-31*60,$now)==='stale','31 min = DESATUALIZADO');
ok(PR1_Aero_Logic::freshness($now-6*3600,$now)==='stale','raio/dado de 6 h nunca LIVE');
ok(PR1_Aero_Logic::freshness(null,$now)==='unavailable','sem horário = INDISPONÍVEL');
ok(PR1_Aero_Logic::freshness(0,$now)==='unavailable','timestamp zero = INDISPONÍVEL');
ok(PR1_Aero_Logic::freshness($now+10*60,$now)==='unavailable','horário no futuro (>5 min) = INDISPONÍVEL');
ok(PR1_Aero_Logic::freshness($now+4*60,$now)==='live','relógio 4 min adiantado tolerado');
ok(PR1_Aero_Logic::local_to_ts('2026-10-22T12:00')===strtotime('2026-10-22T15:00:00Z'),'hora local de Brasília convertida (UTC-3)');
ok(PR1_Aero_Logic::local_to_ts('22/10/2026 12:00')===null,'formato inválido rejeitado');
// Radar
$rv=['host'=>'https://tilecache.rainviewer.com','radar'=>['past'=>[['time'=>$now-20*60,'path'=>'/v2/radar/abc123'],['time'=>$now-10*60,'path'=>'/v2/radar/abc124']]]];
$r=PR1_Aero_Logic::radar($rv,$now);
ok($r['state']==='live'&&count($r['frames'])===2&&$r['age_min']===10,'radar recente = LIVE com 2 quadros');
$r=PR1_Aero_Logic::radar(['host'=>'https://tilecache.rainviewer.com','radar'=>['past'=>[['time'=>$now-3600,'path'=>'/v2/radar/ff']]]],$now);
ok($r['state']==='stale','radar de 60 min = DESATUALIZADO');
ok(PR1_Aero_Logic::radar(['host'=>'https://evil.example','radar'=>$rv['radar']],$now)['state']==='unavailable','host de tiles fora da allowlist rejeitado');
$bad=PR1_Aero_Logic::radar(['host'=>'https://tilecache.rainviewer.com','radar'=>['past'=>[['time'=>$now,'path'=>'/../../x']]]],$now);
ok($bad['state']==='unavailable'&&!$bad['frames'],'path de quadro malicioso rejeitado');
ok(PR1_Aero_Logic::radar([],$now)['state']==='unavailable','JSON vazio = INDISPONÍVEL');
// Períodos
$h=['time'=>[],'temperature_2m'=>[],'precipitation_probability'=>[],'precipitation'=>[],'wind_speed_10m'=>[]];
for($i=0;$i<24;$i++){$h['time'][]=sprintf('2026-10-22T%02d:00',$i);$h['temperature_2m'][]=15+$i/2;$h['precipitation_probability'][]=$i>=12&&$i<18?80:10;$h['precipitation'][]=0.5;$h['wind_speed_10m'][]=10+$i;}
$p=PR1_Aero_Logic::periods($h);
ok(count($p)===4&&$p[0]['period']==='Madrugada'&&$p[2]['period']==='Tarde','4 períodos do dia');
ok($p[2]['rain_probability_max']===80&&$p[2]['rain_mm']==3.0&&$p[0]['temp_min']==15.0,'agregação de chuva/temperatura correta');
$q=PR1_Aero_Logic::periods($h,6,'2026-10-22T13:30');
ok($q[0]['period']==='Tarde'&&count($q)===2,'períodos já passados não aparecem (a partir das 13h: tarde e noite)');
ok(PR1_Aero_Logic::periods([])===[],'sem dados horários = sem períodos (nada inventado)');
// INMET
$j=json_decode(file_get_contents(__DIR__.'/fixtures/inmet-avisos-ativos.json'),true);
$at=strtotime('2026-10-22T15:00:00Z');
$a=PR1_Aero_Logic::inmet_filter($j,-23.4197,-51.4242,'Arapongas','PR',$at);
$ids=array_column($a,'id');
ok($ids===['51234'],'Arapongas: só o aviso vigente do PR (sem duplicado, encerrado, vencido ou homônimo de SP)');
ok($a[0]['active_now']===true&&$a[0]['coverage']==='município','vigência e cobertura indicadas');
ok($a[0]['risks']===['Rajadas de vento','Queda de granizo'],'riscos em formato texto-lista do INMET interpretados');
ok($a[0]['url']==='https://alertas2.inmet.gov.br/51234'&&$a[0]['source']==='INMET','link oficial verificável');
ok($a[0]['start']==='2026-10-22T13:00:00+00:00'&&$a[0]['end']==='2026-10-23T13:00:00+00:00','início e validade convertidos de Brasília');
$m=PR1_Aero_Logic::inmet_filter($j,-23.4205,-51.9333,'Maringá','PR',$at);
ok(array_column($m,'id')===['51235']&&$m[0]['coverage']==='polígono','Maringá: aviso por polígono');
ok(PR1_Aero_Logic::inmet_filter([],-23.4,-51.4,'Arapongas','PR',$at)===[],'fonte vazia = lista vazia (sem aviso fictício)');
if($fail){fwrite(STDERR,"$fail falha(s)\n");exit(1);} echo "OK PR1 AERO nativo: lógica\n";
