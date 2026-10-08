const $ = id => document.getElementById(id);
let page = 1, search = '', activities = [], loading = false;
const labels = {pendiente:'Pendiente',en_proceso:'En proceso',completada:'Completada'};
try { document.body.dataset.tema = localStorage.getItem('tema') || 'claro'; } catch {}
$('tema').onclick = () => { const theme = document.body.dataset.tema === 'oscuro' ? 'claro' : 'oscuro'; document.body.dataset.tema = theme; try { localStorage.setItem('tema',theme); } catch {} };
function message(text,error=false){ $('mensaje').textContent=text; $('mensaje').classList.toggle('error',error); }
async function api(path, options={}) {
  const response=await fetch('/api/'+path,options);
  let data; try { data=await response.json(); } catch { throw new Error('No fue posible conectar con la aplicación.'); }
  if(!response.ok) throw new Error(data.error+(data.campos?' '+Object.values(data.campos).join(' '):''));
  return data;
}
function el(tag,text,className){const node=document.createElement(tag);if(text!==undefined)node.textContent=text;if(className)node.className=className;return node;}
function reset(){ $('form').reset();$('id').value='';$('form-titulo').textContent='Nueva actividad';$('guardar').textContent='Crear actividad';$('cancelar').hidden=true; }
function today(){return new Intl.DateTimeFormat('en-CA',{timeZone:'America/Mexico_City',year:'numeric',month:'2-digit',day:'2-digit'}).format(new Date());}
function render(){
  $('lista').replaceChildren();
  if(!activities.length)$('lista').append(el('p','No hay actividades para mostrar.'));
  for(const a of activities){
    const card=el('article',undefined,'activity');card.append(el('h3',a.titulo),el('p',a.descripcion||'Sin descripción'));
    const meta=el('div',undefined,'meta');meta.append(el('span',labels[a.estado]),el('span','Prioridad '+a.prioridad,'badge '+a.prioridad),el('span',a.fecha_limite?'Vence: '+a.fecha_limite:'Sin fecha límite'));
    if(a.fecha_limite&&a.estado!=='completada'){
      const diff=Math.round((Date.parse(a.fecha_limite+'T00:00:00Z')-Date.parse(today()+'T00:00:00Z'))/86400000);
      if(diff<0)meta.append(el('span','Vencida','vencida'));else if(diff<=3)meta.append(el('span',diff===0?'Vence hoy':'Próxima a vencer','proxima'));
    }
    card.append(meta);const actions=el('div',undefined,'actions');actions.style.marginTop='14px';
    const edit=el('button','Editar','secondary');edit.type='button';edit.onclick=()=>{for(const field of ['id','titulo','descripcion','estado','prioridad','fecha_limite'])$(field).value=a[field]??'';$('form-titulo').textContent='Editar actividad';$('guardar').textContent='Guardar cambios';$('cancelar').hidden=false;$('titulo').focus();};
    const remove=el('button','Eliminar','secondary danger');remove.type='button';remove.onclick=async()=>{if(!confirm('¿Eliminar la actividad «'+a.titulo+'»?'))return;remove.disabled=true;try{await api('actividades/'+a.id,{method:'DELETE'});if($('id').value===String(a.id))reset();message('Actividad eliminada.');await load();}catch(e){message(e.message,true);}finally{remove.disabled=false;}};
    actions.append(edit,remove);card.append(actions);$('lista').append(card);
  }
}
async function load(){
 if(loading)return;loading=true;$('refrescar').disabled=true;$('anterior').disabled=true;$('siguiente').disabled=true;
 try{
   let data=await api('actividades?'+new URLSearchParams({buscar:search,pagina:page,limite:5}));
   const last=Math.max(1,Math.ceil(data.total/5));if(page>last){page=last;data=await api('actividades?'+new URLSearchParams({buscar:search,pagina:page,limite:5}));}
   activities=data.actividades;render();$('resumen').textContent=data.total+' actividades · Consulta desde '+(data.origen==='redis'?'caché':'base de datos')+' · Consultas: '+data.contador_consultas;
   $('pagina').textContent='Página '+page+' de '+last;$('anterior').disabled=page<=1;$('siguiente').disabled=page>=last;
 }catch(e){message(e.message,true);}finally{loading=false;$('refrescar').disabled=false;}
}
$('form').onsubmit=async event=>{
 event.preventDefault();const button=$('guardar');button.disabled=true;
 const data={};for(const field of ['titulo','descripcion','estado','prioridad','fecha_limite'])data[field]=$(field).value.trim();data.fecha_limite ||= null;
 const id=$('id').value;
 try{const result=await api('actividades'+(id?'/'+id:''),{method:id?'PUT':'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)});reset();message(result.mensaje+(result.correo_enviado===false?' La actividad se guardó, pero no se pudo enviar el correo.':''));await load();}catch(e){message(e.message,true);}finally{button.disabled=false;}
};
$('cancelar').onclick=reset;
$('busqueda').onsubmit=e=>{e.preventDefault();search=$('buscar').value.trim();page=1;load();};
$('refrescar').onclick=()=>load();$('anterior').onclick=()=>{if(!loading){page--;load();}};$('siguiente').onclick=()=>{if(!loading){page++;load();}};
load();
