// PR1 AERO — testes da máquina de estados do cliente (node tests/js/test-aero-state.js)
const assert=require('node:assert/strict');
const S=require('../../wp-content/themes/portal-regional-tema/assets/pr1-aero-state.js');
const now=Date.parse('2026-10-22T15:00:00Z'),m=60000;let n=0;
const t=(name,fn)=>{fn();n++;console.log('OK '+name);};
t('limite 30 min',()=>assert.equal(S.STALE_MINUTES,30));
t('recente = LIVE',()=>assert.equal(S.stateFor(now-5*m,now),'live'));
t('30 min = LIVE',()=>assert.equal(S.stateFor(now-30*m,now),'live'));
t('31 min = DESATUALIZADO',()=>assert.equal(S.stateFor(now-31*m,now),'stale'));
t('sem horário = INDISPONÍVEL',()=>assert.equal(S.stateFor(null,now),'unavailable'));
t('NaN = INDISPONÍVEL',()=>assert.equal(S.stateFor(NaN,now),'unavailable'));
t('futuro = INDISPONÍVEL',()=>assert.equal(S.stateFor(now+10*m,now),'unavailable'));
t('cache do servidor dizendo live com dado velho é rebaixado',()=>assert.equal(S.merge('live',new Date(now-45*m).toISOString(),now),'stale'));
t('cliente nunca promove: servidor stale continua stale',()=>assert.equal(S.merge('stale',new Date(now).toISOString(),now),'stale'));
t('estado desconhecido = INDISPONÍVEL',()=>assert.equal(S.merge('bogus',new Date(now).toISOString(),now),'unavailable'));
t('rótulos',()=>{assert.equal(S.label('live'),'LIVE');assert.equal(S.label('stale'),'DESATUALIZADO');assert.equal(S.label('x'),'INDISPONÍVEL');});
console.log(`OK ${n} testes de estado`);
