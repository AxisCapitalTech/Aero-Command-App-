/* PR1 AERO nativo — mapa (Leaflet local), radar RainViewer, previsão, avisos INMET, raios e Brief.
   Regras: só dados reais da API do portal; estado LIVE apenas com atualização ≤ 30 min. */
(()=>{'use strict';
const app=document.querySelector('[data-aero-app]');if(!app)return;
const S=window.PR1AeroState;
const $=(s,r=app)=>r.querySelector(s), $$=(s,r=app)=>[...r.querySelectorAll(s)];
const fmtTime=iso=>{const d=new Date(iso);return Number.isFinite(d.getTime())?d.toLocaleString('pt-BR',{timeZone:'America/Sao_Paulo',day:'2-digit',month:'2-digit',hour:'2-digit',minute:'2-digit'})+' (Brasília)':'—';};
const num=(v,u)=>v===null||v===undefined||!Number.isFinite(Number(v))?'—':Number(v).toLocaleString('pt-BR',{maximumFractionDigits:1})+u;
const el=(tag,cls,text)=>{const e=document.createElement(tag);if(cls)e.className=cls;if(text!==undefined)e.textContent=text;return e;};
const WX={0:'Céu limpo',1:'Predominantemente limpo',2:'Parcialmente nublado',3:'Nublado',45:'Neblina',48:'Neblina',51:'Garoa',53:'Garoa',55:'Garoa',61:'Chuva fraca',63:'Chuva',65:'Chuva forte',80:'Pancadas de chuva',81:'Pancadas de chuva',82:'Pancadas fortes',95:'Trovoada',96:'Trovoada com granizo',99:'Trovoada com granizo'};
function badge(key,state){const b=$(`[data-aero-badge="${key}"]`);if(!b)return;b.textContent=S.label(state);b.dataset.state=state;}
async function getJSON(path,params){
 const base=app.getAttribute('data-api-'+path);if(!base)return {ok:false,status:0,data:{}};const u=new URL(base,window.location.href);Object.entries(params||{}).forEach(([k,v])=>u.searchParams.set(k,v));
 const ctl=new AbortController(),t=setTimeout(()=>ctl.abort(),12000);
 try{const r=await fetch(u.toString(),{signal:ctl.signal,credentials:'same-origin'});let j=null;try{j=await r.json();}catch(_){}const ok=r.ok&&j&&typeof j==='object';return {ok:!!ok,status:r.status,data:ok||j&&typeof j==='object'?j:{}};}
 catch(_){return {ok:false,status:0,data:{}};}finally{clearTimeout(t);}
}
let city=app.getAttribute('data-default-city')||'arapongas', seq=0, map=null, radarLayer=null, cityLayer=null, frames=[], frameIdx=0, timer=null, radarHost='';
const cityBtns=$$('[data-aero-city]');
const brief={forecast:null,alerts:null,radar:null};

/* ---------- Tabs + ampliar ---------- */
$$('[data-aero-tab]').forEach(t=>t.addEventListener('click',()=>{
 $$('[data-aero-tab]').forEach(x=>{const on=x===t;x.setAttribute('aria-selected',String(on));document.getElementById(x.getAttribute('aria-controls')).hidden=!on;});
 if(t.dataset.aeroTab==='mapa'&&map)setTimeout(()=>map.invalidateSize(),50);
}));
const exp=document.querySelector('[data-aero-expand]');
if(exp)exp.addEventListener('click',()=>{
 const on=!document.body.classList.contains('pr1-aero-expanded');
 document.body.classList.toggle('pr1-aero-expanded',on);exp.setAttribute('aria-pressed',String(on));exp.textContent=on?'Reduzir painel':'Ampliar painel';
 if(map)setTimeout(()=>map.invalidateSize(),80);
});
document.addEventListener('keydown',e=>{if(e.key==='Escape'&&document.body.classList.contains('pr1-aero-expanded'))exp&&exp.click();});

/* ---------- Mapa ---------- */
function initMap(){
 const box=$('[data-aero-map]');if(!box)return;
 if(!window.L){box.textContent='Mapa indisponível: biblioteca de mapas não carregou.';return;}
 map=L.map(box,{zoomControl:true,attributionControl:true,maxBounds:[[-34,-58],[-19,-44]],minZoom:6,maxZoom:12}).setView([-23.42,-51.42],8);
 L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:12,attribution:'&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'}).addTo(map);
 cityLayer=L.layerGroup().addTo(map);
 cityBtns.forEach(b=>{const m=L.circleMarker([+b.dataset.lat,+b.dataset.lon],{radius:7,color:'#003087',weight:2,fillColor:'#E30613',fillOpacity:.9});m.bindTooltip(b.textContent,{permanent:false});m.on('click',()=>selectCity(b.dataset.aeroCity));m.addTo(cityLayer);});
}
function showFrame(i){
 if(!map||!frames.length)return;
 frameIdx=(i+frames.length)%frames.length;const f=frames[frameIdx];
 const url=radarHost+f.path+'/256/{z}/{x}/{y}/2/1_1.png';
 if(radarLayer)map.removeLayer(radarLayer);
 radarLayer=L.tileLayer(url,{opacity:.65,maxNativeZoom:7,maxZoom:12,attribution:'Radar: <a href="https://www.rainviewer.com/">RainViewer</a>'});
 if($('[data-aero-layer="radar"]').checked)radarLayer.addTo(map);
 const meta=$('[data-aero-meta="radar"]');meta.textContent='Quadro '+(frameIdx+1)+'/'+frames.length+' · '+fmtTime(new Date(f.time*1000).toISOString())+' · Fonte: RainViewer (uso pessoal/educacional).';
}
async function loadRadar(){
 const r=await getJSON('radar');const d=r.data;
 const state=r.ok?S.merge(d.state,d.latest_at,Date.now(),d.stale_limit_min):'unavailable';
 badge('radar',state);brief.radar={state,age:d.age_min};
 const meta=$('[data-aero-meta="radar"]');
 if(!r.ok||!Array.isArray(d.frames)||!d.frames.length||d.host!=='https://tilecache.rainviewer.com'){meta.textContent=(d.message||'Radar indisponível no momento.')+' O mapa continua navegável.';frames=[];if(radarLayer){map&&map.removeLayer(radarLayer);radarLayer=null;}renderBrief();return;}
 radarHost=d.host;frames=d.frames;showFrame(frames.length-1);
 if(state==='stale')meta.textContent+=' Último quadro com '+d.age_min+' min — DESATUALIZADO.';
 renderBrief();
}
$('[data-aero-layer="radar"]').addEventListener('change',e=>{if(!radarLayer||!map)return;e.target.checked?radarLayer.addTo(map):map.removeLayer(radarLayer);});
$('[data-aero-layer="cities"]').addEventListener('change',e=>{if(!cityLayer||!map)return;e.target.checked?cityLayer.addTo(map):map.removeLayer(cityLayer);});
const play=$('[data-aero-radar-play]');
play.addEventListener('click',()=>{
 if(timer){clearInterval(timer);timer=null;play.setAttribute('aria-pressed','false');play.textContent='▶ Animar radar';showFrame(frames.length-1);return;}
 if(frames.length<2)return;play.setAttribute('aria-pressed','true');play.textContent='❚❚ Parar';timer=setInterval(()=>showFrame(frameIdx+1),900);
});

/* ---------- Previsão ---------- */
async function loadForecast(id,my){
 const now=$('[data-aero-now]'),per=$('[data-aero-periods]'),meta=$('[data-aero-meta="forecast"]');
 now.textContent='Consultando previsão…';per.replaceChildren();
 const r=await getJSON('forecast',{city:id});if(my!==seq)return;
 const d=r.data;const state=r.ok?S.merge(d.state,d.observed_at,Date.now(),d.stale_limit_min):'unavailable';
 badge('forecast',state);brief.forecast=r.ok?Object.assign({},d,{state}):{state};
 if(!r.ok||!d.current){now.textContent=d.message||'Previsão indisponível no momento.';meta.textContent='Fonte: Open-Meteo.';renderBrief();return;}
 const c=d.current;now.replaceChildren();
 const h=el('div','pr1-aero-now-main');h.append(el('strong',null,num(c.temperature,' °C')),el('span',null,WX[c.weather_code]||'Condição não classificada'));
 const grid=el('dl','pr1-aero-now-grid');
 [['Sensação',num(c.apparent_temperature,' °C')],['Umidade',num(c.humidity,'%')],['Precipitação',num(c.precipitation_mm,' mm')],['Vento',num(c.wind_kmh,' km/h')],['Rajada',num(c.gust_kmh,' km/h')]].forEach(([k,v])=>{grid.append(el('dt',null,k),el('dd',null,v));});
 now.append(h,grid);
 if(state!=='live'){const w=el('p','pr1-aero-warn',state==='stale'?'Leitura com '+d.age_min+' min: DESATUALIZADO — não representa a condição atual.':'Horário da leitura inválido: INDISPONÍVEL.');now.append(w);}
 (d.periods||[]).forEach(p=>{const card=el('article','pr1-aero-period');card.append(el('h3',null,p.period+' · '+p.date.split('-').reverse().slice(0,2).join('/')),el('p',null,num(p.temp_min,'°')+' a '+num(p.temp_max,'°')),el('p',null,'Chuva: '+num(p.rain_probability_max,'%')+' · '+num(p.rain_mm,' mm')),el('p',null,'Vento máx.: '+num(p.wind_max_kmh,' km/h')));per.append(card);});
 meta.replaceChildren(document.createTextNode('Leitura '+fmtTime(d.observed_at)+' · Fonte: '),Object.assign(el('a',null,'Open-Meteo'),{href:'https://open-meteo.com/',target:'_blank',rel:'noopener noreferrer'}),document.createTextNode(' (CC BY 4.0). Modelo meteorológico, não aviso oficial.'));
 renderBrief();
}

/* ---------- Avisos oficiais (INMET) ---------- */
async function loadAlerts(id,my){
 const box=$('[data-aero-alerts]'),meta=$('[data-aero-meta="alerts"]');box.textContent='Consultando INMET…';
 const r=await getJSON('alerts',{city:id});if(my!==seq)return;const d=r.data;
 const state=r.ok?S.merge(d.state,d.fetched_at,Date.now(),30):'unavailable';badge('alerts',state);
 brief.alerts=r.ok?{state,items:d.items||[]}:{state,items:null};
 box.replaceChildren();
 if(!r.ok){box.append(el('p','pr1-aero-warn',d.message||'Não foi possível consultar o INMET. Isso não significa ausência de avisos.'));meta.textContent='Consulte diretamente: alertas2.inmet.gov.br e Defesa Civil (199).';renderBrief();return;}
 if(!Array.isArray(d.items)){box.append(el('p','pr1-aero-warn','Resposta do INMET em formato inesperado: INDISPONÍVEL.'));badge('alerts','unavailable');brief.alerts={state:'unavailable',items:null};renderBrief();return;}
 if(!d.items.length){box.append(el('p',null,'Sem avisos ativos do INMET para '+d.city+' nesta consulta.'));}
 d.items.forEach(a=>{const art=el('article','pr1-aero-alert');art.dataset.severity=a.severity;
  art.append(el('h3',null,a.event+(a.severity?' — '+a.severity:'')));
  const dl=el('dl');[['Local',a.area+' ('+a.coverage+')'],['Início',a.start?fmtTime(a.start):'—'],['Validade',a.end?fmtTime(a.end):'—'],['Situação',a.active_now?'Vigente agora':'Previsto'],['Fonte','INMET (oficial)']].forEach(([k,v])=>dl.append(el('dt',null,k),el('dd',null,v)));
  art.append(dl);if((a.risks||[]).length){const ul=el('ul');(a.risks||[]).forEach(x=>ul.append(el('li',null,x)));art.append(el('h4',null,'Riscos'),ul);}
  const link=el('a',null,'Ver aviso no INMET ↗');link.href=/^https:\/\/alertas2\.inmet\.gov\.br\/\d*$/.test(a.url)?a.url:'https://alertas2.inmet.gov.br/';link.target='_blank';link.rel='noopener noreferrer';art.append(link);box.append(art);});
 meta.textContent='Consulta ao INMET em '+fmtTime(d.fetched_at)+'. Para emergências: Defesa Civil 199.';
 renderBrief();
}

/* ---------- Raios ---------- */
async function loadLightning(){
 const r=await getJSON('lightning');const d=r.data;const state=r.ok&&d.state?S.merge(d.state,d.observed_at,Date.now(),30):'unavailable';
 badge('lightning',state);$('[data-aero-lightning]').textContent=r.ok?(d.message||''):(d.message||'Raios indisponíveis.');
}

/* ---------- AERO Brief (determinístico, só com dados reais carregados) ---------- */
function renderBrief(){
 const box=$('[data-aero-brief]');if(!box)return;const f=brief.forecast,a=brief.alerts,rd=brief.radar;const lines=[];
 if(f&&f.current&&f.state==='live'){
  lines.push(f.city+': '+num(f.current.temperature,' °C')+', '+(WX[f.current.weather_code]||'condição não classificada').toLowerCase()+'.');
  const p=(f.periods||[]).slice(0,4).filter(x=>Number(x.rain_probability_max)>=60);
  if(p.length)lines.push('Chance alta de chuva (≥ 60%) em: '+p.map(x=>x.period.toLowerCase()).join(', ')+'.');
  else if((f.periods||[]).length)lines.push('Sem período com chance de chuva ≥ 60% nas próximas 24 h, segundo o modelo.');
 }else lines.push('Previsão atual '+(f?S.label(f.state):'aguardando')+'.');
 if(a&&Array.isArray(a.items))lines.push(a.items.length?a.items.length+' aviso(s) oficial(is) do INMET ativo(s) — veja a aba Avisos.':'Sem avisos ativos do INMET nesta consulta.');
 else if(a)lines.push('Avisos oficiais INDISPONÍVEIS: não é possível afirmar que não há avisos.');
 if(rd)lines.push('Radar: '+S.label(rd.state)+(rd.age!==null&&rd.age!==undefined?' (último quadro há '+rd.age+' min)':'')+'.');
 const st=f&&f.state==='live'&&a&&a.state!=='unavailable'?'live':(f&&f.state==='stale'?'stale':(f?'unavailable':'unavailable'));
 badge('brief',st);box.replaceChildren(...lines.map(t=>el('p',null,t)));
}

function selectCity(id){
 if(!cityBtns.some(b=>b.dataset.aeroCity===id))return;city=id;const my=++seq;
 cityBtns.forEach(b=>b.setAttribute('aria-pressed',String(b.dataset.aeroCity===id)));
 const b=cityBtns.find(x=>x.dataset.aeroCity===id);if(map&&b)map.setView([+b.dataset.lat,+b.dataset.lon],9);
 brief.forecast=null;brief.alerts=null;loadForecast(id,my);loadAlerts(id,my);
}
cityBtns.forEach(b=>b.addEventListener('click',()=>selectCity(b.dataset.aeroCity)));
initMap();loadRadar();loadLightning();selectCity(city);
setInterval(()=>{loadRadar();},5*60*1000);
})();
