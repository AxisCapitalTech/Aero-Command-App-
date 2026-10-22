/* PR1 AERO — máquina de estados de atualização (pura, testável em Node). */
(function(root){'use strict';
const STALE_MINUTES=30, FUTURE_TOLERANCE_MINUTES=5;
const LABELS={live:'LIVE',stale:'DESATUALIZADO',unavailable:'INDISPONÍVEL'};
/** observedMs: epoch ms da observação (ou null). Retorna live|stale|unavailable. */
function stateFor(observedMs,nowMs,limit){
 limit=Number.isFinite(limit)?limit:STALE_MINUTES;
 if(observedMs===null||observedMs===undefined||!Number.isFinite(observedMs)||observedMs<=0)return 'unavailable';
 const age=(nowMs-observedMs)/60000;
 if(age<-FUTURE_TOLERANCE_MINUTES)return 'unavailable';
 return age>limit?'stale':'live';
}
/** Recalcula no cliente: o servidor nunca é a única fonte de verdade (cache). Rebaixa, nunca promove. */
function merge(serverState,observedIso,nowMs,limit){
 const t=observedIso?Date.parse(observedIso):NaN;
 const local=stateFor(Number.isFinite(t)?t:null,nowMs,limit);
 const rank={live:0,stale:1,unavailable:2};
 const s=rank[serverState]===undefined?'unavailable':serverState;
 return rank[local]>rank[s]?local:s;
}
const api={STALE_MINUTES,LABELS,stateFor,merge,label:s=>LABELS[s]||LABELS.unavailable};
if(typeof module==='object'&&module.exports)module.exports=api;else root.PR1AeroState=api;
})(typeof self!=='undefined'?self:this);
