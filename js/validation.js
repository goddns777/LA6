/* AI: ChatGPT / 프롬프트: LA-5 검사와 지난 날짜·중복 검사를 모두 표시해줘.
 * [수정] 통과하면 기본 폼 POST를 허용한다. 최종 검사는 PHP가 수행한다. */
'use strict';
const FrameValidation = (() => {
  const fields = ['date','title','time','duration','genre','rating','price','language','note','poster'];
  const choices = {genre:['SF','드라마','애니메이션','스릴러','로맨스','액션','다큐멘터리'],rating:['전체','12세','15세','청불'],language:['원어','자막','더빙']};
  const minute = t => Number(t.slice(0,2))*60 + Number(t.slice(3));
  const numeric = s => s.trim()===s && /^[+-]?(?:\d+\.?\d*|\.\d+)(?:[eE][+-]?\d+)?$/.test(s) && Number.isFinite(Number(s));
  function validate(s, records, today) {
    const errors = {};
    const add = (field,message) => (errors[field] ||= []).push(message);
    const d = new Date(s.date+'T12:00:00');
    const dateOK = s.date.length===10 && /^\d{4}-\d{2}-\d{2}$/.test(s.date) && !Number.isNaN(d.getTime())
      && d.getFullYear()===Number(s.date.slice(0,4)) && d.getMonth()+1===Number(s.date.slice(5,7))
      && d.getDate()===Number(s.date.slice(8,10)) && s.date>='1900-01-01' && s.date<='2100-12-31';
    if (!dateOK) add('date','1900~2100년 사이의 실제 날짜를 입력해 주세요.');
    else if (s.date < today) add('date','지난 날짜에는 상영을 등록할 수 없습니다.');
    if (!s.title.trim()) add('title','영화 제목은 비워 두거나 공백만 입력할 수 없습니다.');
    if (s.title.length > 60) add('title','영화 제목은 60자 이내로 입력해 주세요.');
    const timeOK = s.time.length===5 && /^([01]\d|2[0-3]):[0-5]\d$/.test(s.time);
    if (!timeOK) add('time','시작 시간은 00:00~23:59의 HH:MM 형식이어야 합니다.');
    const durationOK = numeric(s.duration) && Number.isInteger(Number(s.duration)) && Number(s.duration)>=1 && Number(s.duration)<=600;
    if (!durationOK) add('duration','러닝타임은 1~600분의 정수여야 합니다.');
    if (!numeric(s.price) || !Number.isInteger(Number(s.price)) || Number(s.price)<0 || Number(s.price)>100000 || Number(s.price)%500!==0)
      add('price','관람료는 0~100,000원 사이의 500원 단위여야 합니다.');
    for (const [field,options] of Object.entries(choices)) if (!options.includes(s[field])) add(field,'제공된 선택지 중에서 선택해 주세요.');
    if (s.note.length>300) add('note','편성 메모는 300자 이내로 입력해 주세요.');
    if (timeOK && s.rating==='청불' && s.time<'18:00') add('time','청불 영화는 18:00 이후에 시작해야 합니다.');
    if (timeOK && durationOK && minute(s.time)+Number(s.duration)>1440) add('duration','상영은 당일 24:00까지 끝나야 합니다.');
    if (dateOK && timeOK) for (const o of records) {
      if (o.date!==s.date) continue;
      if (o.time===s.time) { add('time','같은 날 같은 시간에 이미 등록된 상영이 있습니다.'); break; }
      if (durationOK && minute(s.time)<minute(o.time)+o.duration+10 && minute(o.time)<minute(s.time)+Number(s.duration)+10) {
        add('time','기존 상영과 겹칩니다. 상영 사이 10분을 확보해 주세요.'); break;
      }
    }
    return errors;
  }
  function clear(form) {
    for (const f of fields) {
      form.elements.namedItem(f).removeAttribute('aria-invalid');
      document.getElementById(f+'Error').textContent='';
    }
    const summary=document.getElementById('formError');
    summary.replaceChildren(); summary.hidden=true;
  }
  function attach(form, records, today) {
    form.noValidate=true; // HTML 속성은 보존하고 validity 오류도 모아 보여 준다.
    form.addEventListener('submit',event => {
      clear(form);
      const values=Object.fromEntries(fields.filter(f=>f!=='poster').map(f=>[f,form.elements.namedItem(f).value]));
      const errors=validate(values,records,today);
      const file=form.elements.namedItem('poster').files[0];
      if (file && file.size>2*1024*1024) errors.poster=['포스터는 2MiB 이하만 올릴 수 있습니다.'];
      for (const f of fields) {
        const input=form.elements.namedItem(f);
        if (!input.validity.valid && !errors[f]) errors[f]=[input.validationMessage];
      }
      if (!Object.keys(errors).length) return; // [수정] fetch 없이 일반 폼 전송
      event.preventDefault();
      const summary=document.getElementById('formError');
      const lead=document.createElement('strong'); lead.textContent='입력 내용을 확인해 주세요.';
      const list=document.createElement('ul'); summary.append(lead,list);
      for (const [field,messages] of Object.entries(errors)) {
        form.elements.namedItem(field).setAttribute('aria-invalid','true');
        document.getElementById(field+'Error').textContent=messages.join('\n');
        for (const message of messages) { const li=document.createElement('li'); li.textContent=message; list.append(li); }
      }
      summary.hidden=false; summary.focus();
    });
  }
  return {validate,clear,attach};
})();
