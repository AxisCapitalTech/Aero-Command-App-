/* PR1 AERO — consulta REST de previsão e carregamento explícito do painel externo. */
(()=>{'use strict';
const labels={0:'Céu limpo',1:'Predominantemente limpo',2:'Parcialmente nublado',3:'Nublado',45:'Neblina',48:'Neblina',51:'Garoa',53:'Garoa',55:'Garoa',61:'Chuva',63:'Chuva',65:'Chuva forte',71:'Neve',73:'Neve',75:'Neve',80:'Pancadas de chuva',81:'Pancadas de chuva',82:'Pancadas fortes',95:'Trovoada',96:'Trovoada',99:'Trovoada'};
for(const widget of document.querySelectorAll('[data-aero-widget]')){
 const buttons=[...widget.querySelectorAll('[data-city]')],out=widget.querySelector('.pr1-aero-reading');
 const endpoint=widget.getAttribute('data-endpoint');
 let current=null,sequence=0;
 const clean=v=>String(v??'—');
 async function load(id){
  const seq=++sequence;current=id;
  buttons.forEach(b=>b.setAttribute('aria-pressed',String(b.dataset.city===id)));
  out.textContent='Buscando dados da previsão…';
  const ctl=new AbortController();const timer=setTimeout(()=>ctl.abort(),11000);
  try{
   const url=new URL(endpoint,window.location.href);url.searchParams.set('city',id);
   const res=await fetch(url.toString(),{signal:ctl.signal,credentials:'same-origin'});
   if(!res.ok)throw Error('HTTP '+res.status);
   const data=await res.json();
   if(seq!==sequence)return;
   if(!Number.isFinite(Number(data.temperature))||!data.observed_at)throw Error('Incomplete data');
   const observed=new Date(data.observed_at.replace(' ','T')+'-03:00');
   const age=Date.now()-observed.getTime();
   if(!Number.isFinite(age)||age < -20*60*1000||age>120*60*1000)
     throw Error('Stale weather data');
   const temp=Number(data.temperature).toLocaleString('pt-BR',{maximumFractionDigits:1});
   const chunks=[clean(labels[data.weather_code]||'Condições não classificadas'), 'Vento '+clean(data.wind_kmh)+' km/h', 'Umidade '+clean(data.humidity)+'%'];
   if(data.rain_probability!==null&&data.rain_probability!==undefined)chunks.push('Chance de chuva '+clean(data.rain_probability)+'%');
   out.replaceChildren();
   const title=document.createElement('strong');title.textContent=data.city+' · '+temp+' °C';
   const details=document.createElement('span');details.textContent=chunks.join(' · ');
   const time=document.createElement('small');time.textContent='Leitura '+data.observed_at.replace('T',' ')+' (Brasília). Fonte: Open-Meteo.';
   out.append(title,details,time);
  }catch(e){if(seq!==sequence)return;out.textContent='Dados temporariamente indisponíveis ou desatualizados. Não há condições atuais confirmadas.';}
  finally{clearTimeout(timer);}
 }
 buttons.forEach(b=>b.addEventListener('click',()=>load(b.dataset.city)));
 if(buttons.length)load('arapongas');
}
const loadBtn=document.querySelector('[data-aero-load]');
if(loadBtn)loadBtn.addEventListener('click',()=>{
 const container=document.querySelector('[data-aero-container]');
 const frame=container?.querySelector('iframe');if(!frame)return;
 const src=frame.getAttribute('data-src');if(!src||!src.startsWith('https://'))return;
 frame.setAttribute('src',src);container.classList.add('is-open');loadBtn.disabled=true;loadBtn.textContent='Painel solicitado — use o link externo caso não abra';
});
})();
