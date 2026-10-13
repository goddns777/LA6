/* AI: ChatGPT / 프롬프트: 서버에서 읽은 상영으로 기존 FRAME 달력과 상세 보기를 그려줘.
 * [수정] localStorage·예시 저장·수정·삭제를 제거하고 textContent로 안전하게 표시한다. */
'use strict';
const $ = id => document.getElementById(id);
const boot=JSON.parse($('calendarData').textContent);
const screenings=boot.screenings;
const pad=n=>String(n).padStart(2,'0');
const el=(tag,text,className)=>{const node=document.createElement(tag);if(text!==undefined)node.textContent=text;if(className)node.className=className;return node;};
const minute=t=>Number(t.slice(0,2))*60+Number(t.slice(3));
const endTime=s=>{const end=minute(s.time)+s.duration;return `${pad(Math.floor(end/60))}:${pad(end%60)}`;};
const money=n=>`${n.toLocaleString('ko-KR')}원`;
const color=g=>['SF','액션'].includes(g)?'lime':['드라마','로맨스'].includes(g)?'purple':'blue';
let year=Number(boot.date.slice(0,4)),month=Number(boot.date.slice(5,7))-1;
let view='calendar',allMonths=false;
function filtered() {
  const query=$('search').value.trim().toLocaleLowerCase();
  return screenings.filter(s=>(allMonths||s.date.startsWith(`${year}-${pad(month+1)}`))
    && s.title.toLocaleLowerCase().includes(query) && (!$('genreFilter').value||s.genre===$('genreFilter').value)
    && (!$('ratingFilter').value||s.rating===$('ratingFilter').value))
    .sort((a,b)=>(a.date+a.time).localeCompare(b.date+b.time));
}
function render() {
  $('monthTitle').textContent=allMonths?'전체 상영 목록':`${year}. ${pad(month+1)}`;
  $('year').value=year; $('month').value=month;
  const current=screenings.filter(s=>s.date.startsWith(`${year}-${pad(month+1)}`));
  for (const [id,value,unit] of [['statCount',current.length,'회'],['statFilms',new Set(current.map(s=>s.title)).size,'편'],['statDays',new Set(current.map(s=>s.date)).size,'일'],['statHours',Math.round(current.reduce((n,s)=>n+s.duration,0)/60*10)/10,'시간']])
    $(id).replaceChildren(document.createTextNode(String(value)),el('small',unit));
  const records=filtered();
  $('resultCount').textContent=`${records.length}회 상영`; $('emptyBanner').hidden=current.length>0||allMonths;
  $('resetFilter').hidden=!($('search').value||$('genreFilter').value||$('ratingFilter').value);
  $('calendarPanel').hidden=view!=='calendar'; $('listPanel').hidden=view!=='list';
  for (const [id,mode] of [['calendarView','calendar'],['listView','list']]) {
    $(id).classList.toggle('selected',view===mode); $(id).setAttribute('aria-pressed',String(view===mode));
  }
  $('navCalendar').classList.toggle('active',!allMonths); $('navList').classList.toggle('active',allMonths);
  $('calendarBody').replaceChildren();
  const start=new Date(year,month,1).getDay(),count=new Date(year,month+1,0).getDate();
  let row;
  for (let i=0;i<Math.ceil((start+count)/7)*7;i++) {
    if (i%7===0) { row=el('tr'); $('calendarBody').append(row); }
    const cell=el('td'); row.append(cell); const day=i-start+1;
    if (day<1||day>count) { cell.className='blank'; continue; }
    const date=`${year}-${pad(month+1)}-${pad(day)}`;
    cell.dataset.date=date;
    const head=el('div',undefined,'day-head'),button=el('button',String(day),'day-button');
    button.type='button'; button.setAttribute('aria-label',`${date} 상영 등록`);
    if (date===boot.today) { button.classList.add('is-today'); button.setAttribute('aria-current','date'); }
    head.append(button,el('span','＋','day-add')); cell.append(head);
    for (const s of records.filter(s=>s.date===date)) {
      const b=el('button',undefined,`film ${color(s.genre)}`); b.type='button'; b.dataset.id=s.id;
      b.title=`${s.time}~${endTime(s)} · ${s.title}`;
      b.append(el('span',`${s.time} — ${endTime(s)}`,'film-time'),el('span',s.title,'film-title')); cell.append(b);
    }
  }
  $('listPanel').replaceChildren();
  if (!records.length) $('listPanel').append(el('div','조건에 맞는 상영이 없습니다.','empty-state'));
  for (const s of records) {
    const b=el('button',undefined,'list-row'); b.type='button'; b.dataset.id=s.id;
    b.append(el('span',s.date),el('span',s.time),el('strong',s.title),el('span',s.genre),el('span',money(s.price))); $('listPanel').append(b);
  }
  $('prev').disabled=year===1900&&month===0; $('next').disabled=year===2100&&month===11;
}
function setMonth(y,m) {
  const d=new Date(y,m,1); if (d.getFullYear()<1900||d.getFullYear()>2100) return;
  year=d.getFullYear(); month=d.getMonth(); allMonths=false; render();
}
function openEditor(date=boot.today) {
  $('screeningForm').reset(); FrameValidation.clear($('screeningForm'));
  $('date').value=date; $('editor').showModal(); $('title').focus();
}
function showDetail(id) {
  const s=screenings.find(s=>s.id===id); if (!s) return;
  const box=$('detailContent'); box.replaceChildren();
  const title=el('h2',s.title); title.id='detailTitle';
  box.append(title,el('p',`${s.date} · ${s.time} — ${endTime(s)}`,'detail-date'));
  if (s.poster) { const image=el('img',undefined,'detail-poster'); image.src=s.poster; image.alt=`${s.title} 포스터`; box.append(image); }
  const dl=el('dl',undefined,'detail-grid');
  for (const [label,value] of [['장르',s.genre],['관람 등급',s.rating],['러닝타임',`${s.duration}분`],['관람료',money(s.price)],['상영 언어',s.language],['상영관','01']]) {
    const pair=el('div'); pair.append(el('dt',label),el('dd',value)); dl.append(pair);
  }
  box.append(dl); if (s.note) box.append(el('p',s.note,'detail-note')); $('detail').showModal();
}
$('calendarBody').addEventListener('click',e=>{const b=e.target.closest('[data-id]');if(b){showDetail(b.dataset.id);return;}const cell=e.target.closest('td[data-date]');if(cell)openEditor(cell.dataset.date);});
$('listPanel').addEventListener('click',e=>{const b=e.target.closest('[data-id]');if(b)showDetail(b.dataset.id);});
FrameValidation.attach($('screeningForm'),screenings,boot.today);
for (const b of document.querySelectorAll('[data-close]')) b.addEventListener('click',()=>b.closest('dialog').close());
$('add').addEventListener('click',()=>openEditor());
$('prev').addEventListener('click',()=>setMonth(year,month-1)); $('next').addEventListener('click',()=>setMonth(year,month+1));
$('today').addEventListener('click',()=>setMonth(Number(boot.today.slice(0,4)),Number(boot.today.slice(5,7))-1));
$('year').addEventListener('change',()=>setMonth(Number($('year').value),month)); $('month').addEventListener('change',()=>setMonth(year,Number($('month').value)));
for (const id of ['search','genreFilter','ratingFilter']) $(id).addEventListener(id==='search'?'input':'change',render);
$('resetFilter').addEventListener('click',()=>{for(const id of ['search','genreFilter','ratingFilter'])$(id).value='';render();});
$('calendarView').addEventListener('click',()=>{view='calendar';allMonths=false;render();}); $('listView').addEventListener('click',()=>{view='list';render();});
$('navCalendar').addEventListener('click',()=>{view='calendar';allMonths=false;render();}); $('navList').addEventListener('click',()=>{view='list';allMonths=true;render();});
$('print').addEventListener('click',()=>window.print()); $('help').addEventListener('click',()=>$('helpDialog').showModal());
$('theme').addEventListener('click',()=>{document.body.classList.toggle('light');$('theme').setAttribute('aria-label',document.body.classList.contains('light')?'어두운 테마로 변경':'밝은 테마로 변경');});
document.addEventListener('keydown',e=>{if(e.ctrlKey||e.metaKey||e.altKey||document.querySelector('dialog[open]')||['INPUT','TEXTAREA','SELECT'].includes(e.target.tagName))return;if(e.key==='/'){e.preventDefault();$('search').focus();}if(e.key.toLowerCase()==='n'){e.preventDefault();$('add').click();}});
for (let y=1900;y<=2100;y++) { const o=el('option',`${y}년`);o.value=y;$('year').append(o); }
for (let m=0;m<12;m++) { const o=el('option',`${m+1}월`);o.value=m;$('month').append(o); }
render();
if (boot.retry) { $('editor').showModal(); $('title').focus(); }
// [수정] 뒤로 가기 캐시에서도 서버 파일을 다시 읽는다. 서버 검사 캡처 시 form.submit() 사용 가능.
window.form=$('screeningForm'); window.addForm=window.form; window.addform=window.form;
window.addEventListener('pageshow',e=>{if(e.persisted)location.reload();});
