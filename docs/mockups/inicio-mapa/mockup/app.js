(function(){
const D=window.SIID_MAPA, GEO=window.META_GEO, CO=window.CO_GEO;
const nf0=new Intl.NumberFormat('es-CO',{maximumFractionDigits:0}), nf1=new Intl.NumberFormat('es-CO',{minimumFractionDigits:1,maximumFractionDigits:1}), nf2=new Intl.NumberFormat('es-CO',{minimumFractionDigits:2,maximumFractionDigits:2});
const nfd=d=>new Intl.NumberFormat('es-CO',{minimumFractionDigits:d,maximumFractionDigits:d});
const num=(c,v)=>nfd(c.decimales||0).format(v);
const fmt=(c,v)=>v==null?'s. d.':(c.unidad==='%'?num(c,v)+' %':nf0.format(v));
const fmtU=(c,v)=>v==null?'s. d.':(c.unidad==='%'?num(c,v)+' %':nf0.format(v)+' hab.');
const PAL={azul:['#e6edfa','#b4c8ef','#769ce0','#3a6bcb','#0943b5'],verde:['#e5f4ec','#b2ddc3','#72bf93','#2c975f','#006b38'],semaforo:['#c8463e','#ea935b','#efcd6a','#94c47e','#2b8e57']};
const NAMES={azul:'Azul institucional',verde:'Verde',semaforo:'Semáforo (rojo–verde)'};
// ---- configuración (desde el webmaster por hash) ----
const H=new URLSearchParams(location.hash.slice(1));
const cfg={capa:H.get('capa')||'NBI-2018',capas:(H.get('capas')||'POB-2026,NBI-2018,IPM-2018,EDU-CN-2024').split(',').filter(Boolean),
  paleta:H.get('paleta')||'auto',pol:H.get('pol')||'auto',metaTxt:H.get('txt')||'29 municipios · capital Villavicencio',cta:H.get('cta')||'Explorar en el visor',bloque:H.get('bloque')!=='0',mun:H.get('mun')};
if(!cfg.capas.includes(cfg.capa)) cfg.capas.unshift(cfg.capa);
const byCode=Object.fromEntries(D.capas.map(c=>[c.codigo,c]));
let cur=byCode[cfg.capa]||D.capas[1], sel=null;
// ---- contadores ----
const COUNTERS=[
 {n:203,l:'Indicadores Sectoriales',rows:[['Economía y finanzas',41],['Educación',38],['Salud',34],['Infraestructura y transporte',27],['Ambiente y gestión del riesgo',22],['Gobierno y finanzas públicas',22],['Seguridad y convivencia',19]]},
 {n:154,l:'Indicadores Territoriales',rows:[['Sociodemográfico',46],['Pobreza y condiciones de vida',28],['Educación',24],['Salud',21],['Economía',20],['Ambiente',15]]},
 {n:18,l:'Políticas Públicas',rows:[['Sociales',7],['Económicas',4],['Institucionales',4],['Ambientales',3]]}];
const cdiv=document.getElementById('counters');
COUNTERS.forEach(c=>{const b=document.createElement('button');b.className='counter';b.innerHTML=`<b>${c.n}</b><span>${c.l}</span><i>Ver detalle →</i>`;
 b.onclick=()=>openModal(`<h3>${c.n} ${c.l}</h3><span class="ej">ejemplo</span> <span style="font-size:12.5px;color:var(--muted)">Distribución ilustrativa por área temática; se reemplaza con datos del SIID.</span><table><tr><th>Área temática</th><th style="text-align:right">Cantidad</th></tr>${c.rows.map(r=>`<tr class="click" data-a="${r[0]}" data-n="${r[1]}"><td>${r[0]}</td><td class="num"><span class="linkcount">${r[1]}</span></td></tr>`).join('')}<tr><td><b>Total</b></td><td class="num">${c.n}</td></tr></table>`,(m)=>{m.querySelectorAll('tr.click').forEach(tr=>tr.onclick=()=>openModal(`<h3>${tr.dataset.a} — ${tr.dataset.n} ${c.l.toLowerCase()}</h3><span class="ej">ejemplo</span><p class="note">Aquí se listarán los ${tr.dataset.n} registros del área en formato «código — nombre», cada uno con enlace a su ficha técnica.</p>`))});
 cdiv.appendChild(b)});
// ---- modal ----
window.openModal=(html,after)=>{const m=document.getElementById('modalbody');m.innerHTML=html;document.getElementById('modal').classList.add('open');after&&after(m)};
window.closeModal=()=>document.getElementById('modal').classList.remove('open');
document.getElementById('modal').addEventListener('click',e=>{if(e.target.id==='modal')closeModal()});
document.addEventListener('keydown',e=>{if(e.key==='Escape'){closeModal();closeSide()}});
// ---- bloque mapa ----
if(!cfg.bloque){document.getElementById('mapblock').classList.add('hidden');document.querySelector('.hero .wrap').style.gridTemplateColumns='1fr'}
document.getElementById('cta').textContent=cfg.cta+' →';
document.getElementById('float').querySelector('p').innerHTML=cfg.metaTxt.replace(/(\d+)\s+municipios/,'<button class="linkcount" id="cnt29">$1 municipios</button>');
const lp=document.getElementById('layerpills');
cfg.capas.slice(0,4).forEach(code=>{const c=byCode[code];if(!c)return;const b=document.createElement('button');b.className='lp';b.dataset.c=code;b.textContent=pillName(c);b.onclick=()=>{cur=c;render()};lp.appendChild(b)});
function pillName(c){return {"POB-2026":"Población 2026","NBI-2018":"NBI 2018","IPM-2018":"IPM 2018","EDU-CN-2024":"Cobertura neta educación 2024"}[c.codigo]||c.nombre}
const svg=d3.select('#map'), box=document.querySelector('.mapbox');
const W=box.clientWidth,Hh=box.clientHeight;svg.attr('viewBox',`0 0 ${W} ${Hh}`);
const MAPH=Hh-96;const proj=d3.geoMercator(), path=d3.geoPath(proj);
proj.fitExtent([[96,8],[W-8,MAPH]],GEO);
const g=svg.append('g');
const paths=g.selectAll('path').data(GEO.features).join('path').attr('class','m').attr('d',path)
 .on('mousemove',(e,f)=>{const v=val(f.properties.divipola);const t=document.getElementById('tip');t.innerHTML=`<b>${f.properties.divipola} — ${f.properties.municipio}</b>: ${fmtU(cur,v)} (${cur.periodo})`;const r=box.getBoundingClientRect();t.style.left=(e.clientX-r.left)+'px';t.style.top=(e.clientY-r.top)+'px';t.style.opacity=1})
 .on('mouseleave',()=>document.getElementById('tip').style.opacity=0)
 .on('click',(e,f)=>openSide(f.properties.divipola));
// capital marker
let vc=proj([-73.63,4.142]);
const cap=g.append('circle').attr('cx',vc[0]).attr('cy',vc[1]).attr('r',4.5).attr('fill','#fff').attr('stroke','#17233a').attr('stroke-width',2).style('pointer-events','none');
const capT=g.append('text').attr('x',vc[0]+8).attr('y',vc[1]-6).text('Villavicencio').attr('font-size',11).attr('font-weight',700).attr('fill','#17233a').style('pointer-events','none').style('paint-order','stroke').attr('stroke','#fff').attr('stroke-width',3);
// inset
const ins=d3.select('#inset');ins.attr('viewBox','0 0 86 104');const ip=d3.geoMercator().fitExtent([[4,4],[82,100]],CO);
ins.selectAll('path').data(CO.features).join('path').attr('d',d3.geoPath(ip)).attr('class',f=>f.properties.meta?'me':'');
function val(code,c){c=c||cur;const r=c.valores.find(x=>x.divipola===code);return r?r.valor:null}
function polOf(c){return cfg.pol!=='auto'&&c===byCode[cfg.capa]?cfg.pol:c.polaridad}
function palOf(c){let p=cfg.paleta!=='auto'&&c===byCode[cfg.capa]?cfg.paleta:(c.codigo.startsWith('POB')?'azul':'semaforo');let cols=PAL[p].slice();
 // semáforo: bajo=rojo..alto=verde; si alto_malo se invierte. Monocromáticas: oscuro=valor alto, se invierte si alto_malo y paleta semáforo únicamente
 if(polOf(c)==='alto_malo'&&p==='semaforo')cols.reverse();return {name:p,cols}}
function breaks(c){const vs=c.valores.map(v=>v.valor).filter(v=>v!=null).sort(d3.ascending);const q=[.2,.4,.6,.8].map(p=>d3.quantileSorted(vs,p));
 const r=c.unidad==='%'?(x=>Math.round(x)):(x=>{const m=Math.pow(10,Math.max(0,Math.floor(Math.log10(x))-1));return Math.round(x/m)*m});return [...new Set(q.map(r))]}
function classOf(v,b){if(v==null)return -1;let i=0;while(i<b.length&&v>b[i])i++;return i}
let FIRST=true;
function render(){
 document.querySelectorAll('.lp').forEach(b=>b.classList.toggle('on',b.dataset.c===cur.codigo));
 document.getElementById('ltitle').textContent=`${cur.codigo} — ${cur.nombre}`;
 document.getElementById('lmeta').innerHTML=`Periodo ${cur.periodo} · Fuente: ${cur.fuente.split(' — ')[0]} · <a href="${cur.fuente_url}" target="_blank" style="color:var(--accent);font-weight:600">ver fuente</a>`;
 const dep=document.getElementById('ldep');
 if(cur.valor_departamental!=null){dep.className='dep';dep.innerHTML=`<small>Dato departamental</small><b>${fmtU(cur,cur.valor_departamental)}</b>`}
 else{dep.className='dep na';dep.innerHTML=`<small>Dato departamental</small><b>No publicado en la fuente</b>`}
 const b=breaks(cur),pal=palOf(cur);
 (FIRST?paths:paths.transition().duration(350)).attr('fill',f=>{const k=classOf(val(f.properties.divipola),b);return k<0?'#e3e6ec':pal.cols[k*(5/(b.length+1))|0]});
 // leyenda
 const lab=[];for(let i=0;i<=b.length;i++){const lo=i?b[i-1]:null,hi=i<b.length?b[i]:null;const cmp=new Intl.NumberFormat('es-CO',{notation:'compact',maximumFractionDigits:1});const f=v=>cur.unidad==='%'?nf0.format(v):cmp.format(v).replace(/\s?K/,' mil'),u=cur.unidad==='%'?' %':'';lab.push(lo==null?`≤ ${f(hi)}${u}`:hi==null?`> ${f(lo)}${u}`:`${f(lo)}–${f(hi)}${u}`)}
 const groups=lab.map((l,i)=>cur.valores.filter(v=>classOf(v.valor,b)===i));
 const pol=polOf(cur);
 document.getElementById('legend').innerHTML=`<h4>${cur.unidad==='%'?'Porcentaje':'Habitantes'} · ${groups.reduce((a,x)=>a+x.length,0)} municipios por rango</h4><div class="rows">`+lab.map((l,i)=>`<button class="row" data-i="${i}"><span class="sw" style="background:${pal.cols[i*(5/(b.length+1))|0]}"></span>${l}<span class="n">${groups[i].length} mun.</span></button>`).join('')+'</div>'+
  `<div class="pol">${cur.codigo.startsWith('POB')?'Escala descriptiva (no indica mejor/peor)':pol==='alto_malo'?'▲ Valor alto = situación desfavorable':'▲ Valor alto = situación favorable'}</div>`;
 document.querySelectorAll('#legend .row').forEach(r=>r.onclick=()=>{const gr=groups[+r.dataset.i].slice().sort((a,b)=>b.valor-a.valor);openModal(`<h3>${cur.codigo} — ${cur.nombre}</h3><div style="color:var(--muted);font-size:13px">Rango ${lab[r.dataset.i]} · ${gr.length} municipios · ${cur.periodo}</div><table><tr><th>Municipio</th><th style="text-align:right">Valor</th></tr>${gr.map(v=>`<tr class="click" data-c="${v.divipola}"><td>${v.divipola} — ${v.municipio}</td><td class="num">${fmtU(cur,v.valor)}</td></tr>`).join('')}</table>`,m=>m.querySelectorAll('tr.click').forEach(tr=>tr.onclick=()=>{closeModal();openSide(tr.dataset.c)}))});
 if(sel)openSide(sel);
 setHash();FIRST=false;
}
function rankOf(code,c){c=c||cur;const vs=c.valores.filter(v=>v.valor!=null).slice();const pol=polOf(c);vs.sort((a,b)=>c.codigo.startsWith('POB')||pol==='alto_bueno'?b.valor-a.valor:a.valor-b.valor);return vs.findIndex(v=>v.divipola===code)+1}
let INSTANT=false;
function refit(open,code){let k=1,tx=0,ty=0;if(open){const f=GEO.features.find(x=>x.properties.divipola===code);const [[x0,y0],[x1,y1]]=path.bounds(f);const aw=W-300-16,ah=MAPH-16;k=Math.max(.7,Math.min(2.6,.55*Math.min(aw/(x1-x0),ah/(y1-y0))));const cx=(x0+x1)/2,cy=(y0+y1)/2;tx=8+aw/2-k*cx;ty=8+ah/2-k*cy}
 (INSTANT?g:g.transition().duration(450)).attr('transform',`translate(${tx},${ty}) scale(${k})`);cap.attr('r',4.5/k).attr('stroke-width',2/k);capT.attr('font-size',11/k).attr('stroke-width',3/k).attr('x',vc[0]+8/k).attr('y',vc[1]-6/k);
 document.getElementById('legend').classList.toggle('narrow',open);document.getElementById('inset').classList.toggle('hidden',open)}
function openSide(code){sel=code;refit(true,code);const s=document.getElementById('side');const v=cur.valores.find(x=>x.divipola===code);
 paths.classed('sel',f=>f.properties.divipola===code);svg.classed('has-sel',true);document.getElementById('float').classList.add('hidden');
 const rk=rankOf(code);const rkTxt=cur.codigo.startsWith('POB')?'1 = mayor población':polOf(cur)==='alto_malo'?'1 = menor '+(cur.codigo.split('-')[0]):'1 = mayor cobertura';
 const bars=D.capas.map(c=>{const vv=val(code,c),mx=d3.max(c.valores,x=>x.valor);const w=vv==null?0:Math.min(100,vv/mx*100);const dm=c.valor_departamental!=null&&!c.codigo.startsWith('POB')?`<span class="dm" style="left:${Math.min(100,c.valor_departamental/mx*100)}%" title="Dato departamental"></span>`:'';
  return `<div class="bar"><div class="lbl"><span>${c.codigo} — ${pillName(c)}</span><b>${fmt(c,vv)}</b></div><div class="track"><span class="fill" style="width:${w}%;${c===cur?'':'opacity:.45'}"></span>${dm}</div></div>`}).join('');
 s.innerHTML=`<button class="x" aria-label="Cerrar">✕</button><div class="code">DIVIPOLA ${code}</div><h4>${code} — ${v.municipio}</h4>
  <div style="font-size:12px;color:var(--muted)">${cur.codigo} — ${cur.nombre} (${cur.periodo})</div>
  <div class="big">${cur.unidad==='%'?num(cur,v.valor)+'<small>%</small>':nf0.format(v.valor)+'<small>hab.</small>'}</div>
  <span class="rank" title="${rkTxt}">Puesto ${rk} de 29 · ${rkTxt}</span>
  <div class="bars"><h6>El municipio en las 4 capas <span style="text-transform:none;letter-spacing:0">(barra = valor frente al máximo municipal; línea = Meta)</span></h6>${bars}</div>
  <div class="actions"><a class="btn block" href="#" onclick="return false">Ver ficha técnica del indicador</a><a class="btn ghost block" href="#" onclick="return false">Ver indicadores del municipio</a></div>`;
 s.classList.add('open');s.querySelector('.x').onclick=closeSide;setHash()}
function closeSide(){if(sel)refit(false);sel=null;document.getElementById('side').classList.remove('open');paths.classed('sel',false);svg.classed('has-sel',false);document.getElementById('float').classList.remove('hidden');setHash()}
function setHash(){const p=new URLSearchParams(location.hash.slice(1));p.set('capa',cur.codigo);sel?p.set('mun',sel):p.delete('mun');history.replaceState(null,'','#'+p.toString())}
document.addEventListener('click',e=>{if(e.target.id==='cnt29'){const c=cur;openModal(`<h3>29 municipios del Meta</h3><div style="color:var(--muted);font-size:13px">${c.codigo} — ${c.nombre} (${c.periodo})</div><table><tr><th>Municipio</th><th style="text-align:right">Valor</th></tr>${c.valores.map(v=>`<tr class="click" data-c="${v.divipola}"><td>${v.divipola} — ${v.municipio}</td><td class="num">${fmtU(c,v.valor)}</td></tr>`).join('')}</table>`,m=>m.querySelectorAll('tr.click').forEach(tr=>tr.onclick=()=>{closeModal();openSide(tr.dataset.c)}))}});
// ---- tarjetas "lo último" ----
const E=byCode['EDU-CN-2024'],P=byCode['POB-2026'],N=byCode['NBI-2018'];
const dEdu=E.valor_departamental-E.valor_anterior_departamental, dPob=(P.valor_departamental/P.valor_anterior_departamental-1)*100;
const cards=[
 {b:'IT-0106',t:'IT-0106 — Porcentaje del nivel de cobertura neta',d:'Cobertura neta en educación (transición a media) del departamento del Meta. Fuente: MinEducación.',v:nf2.format(E.valor_departamental)+' %',f:'Meta · año 2024',delta:`${dEdu>=0?'▲ +':'▼ −'}${nf2.format(Math.abs(dEdu))} p.p. vs. 2023`,cls:dEdu>=0?'up':'down'},
 {b:'POB-2026',t:'POB-2026 — Población total proyectada',d:'Habitantes proyectados del Meta (cabecera, centros poblados y rural disperso). Fuente: DANE.',v:nf0.format(P.valor_departamental),f:'habitantes · 2026',delta:`▲ +${nf1.format(dPob)} % vs. 2025`,cls:'up'},
 {b:'NBI-2018',t:'NBI-2018 — Personas con Necesidades Básicas Insatisfechas',d:'Proporción de personas con al menos una NBI en el Meta. Fuente: DANE, CNPV 2018.',v:nf2.format(N.valor_departamental)+' %',f:'Meta · CNPV 2018',delta:'Último censo',cls:'neu'}];
document.getElementById('cards').innerHTML=cards.map(c=>`<article class="card"><span class="badge">${c.b}</span><h3>${c.t}</h3><p>${c.d}</p><div class="val">${c.v}</div><div class="foot"><span>${c.f}</span><span class="delta ${c.cls}">${c.delta}</span></div></article>`).join('');
render();
if(cfg.mun&&GEO.features.some(f=>f.properties.divipola===cfg.mun)){INSTANT=true;openSide(cfg.mun);INSTANT=false}
})();
