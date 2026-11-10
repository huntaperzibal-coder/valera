"use strict";
const fox=document.getElementById("fox"),bubble=document.getElementById("bubble"),status=document.getElementById("status"),listen=document.getElementById("listen"),textForm=document.getElementById("text-form"),textInput=document.getElementById("message-input"),sendText=document.getElementById("send-text");
const Recognition=window.SpeechRecognition||window.webkitSpeechRecognition;
const synth=window.speechSynthesis;
let busy=false,turn=0,csrf="",history=[],sending=false,learning=false,turnsSinceLearn=0;

function timeContext(){
  const d=new Date(),h=d.getHours(),m=d.getMonth(),w=d.getDay();
  return {dayPart:h>=5&&h<11?"morning":h>=11&&h<17?"day":h>=17&&h<22?"evening":"night",dayType:w===0||w===6?"weekend":"weekday",season:m===11||m<=1?"winter":m<=4?"spring":m<=7?"summer":"autumn"};
}
function openingText(){
  const t=timeContext();
  const greetings={
    morning:["Доброе утро! Как тебе спалось?","Привет! Какое у тебя сегодня настроение?","С добрым утром! Чем хочется заняться?"],
    day:["Привет! Что интересного было сегодня?","О, привет! Чем занимался?","Рада тебя видеть! Что расскажешь?"],
    evening:["Добрый вечер! Как прошёл день?","Привет! Что сегодня тебя порадовало?","Как прошёл твой вечер?"],
    night:["О, уже поздно! Хочешь пожелать друг другу спокойной ночи?","Доброй ночи! Как ты себя чувствуешь?","Привет! Если собираешься спать — сладких снов!"]
  };
  const extras={winter:"А зимой ты любишь снег?",spring:"Тебе нравится весна?",summer:"Ты любишь летние прогулки?",autumn:"Нравятся разноцветные листья?"};
  const list=greetings[t.dayPart],chosen=list[Math.floor(Math.random()*list.length)];
  return Math.random()<0.15 ? chosen+" "+extras[t.season] : chosen;
}
let greeting=openingText();
bubble.textContent=greeting;

function state(s,t,showMessage=false){
  status.classList.toggle("visually-hidden", !showMessage);fox.dataset.state=s;document.getElementById("voice-indicator").dataset.state=s;status.textContent=t;
  listen.dataset.listening = String(s === "listening");
  listen.setAttribute("aria-pressed", String(s === "listening"));
  document.getElementById("mic-symbol").textContent = s === "listening" ? "🔴" : "🎤";
}
function speechText(text){
  return String(text).replace(/[\p{Extended_Pictographic}\uFE0E\uFE0F\u200D]/gu,"").replace(/[*_#\x60~]/g,"").replace(/https?:\/\/\S+/g,"").replace(/\s+/g," ").trim();
}
let availableVoices=[];
function refreshVoices(){
  if(!synth)return;
  availableVoices=synth.getVoices().filter(v=>v.lang.toLowerCase().startsWith("ru"));
  const el=document.getElementById("voice-choice");
  const saved=el.value||localStorage.getItem("foxy_voice")||"";
  el.replaceChildren(new Option("По умолчанию",""));
  availableVoices.forEach(v=>el.add(new Option(v.name+" ("+v.lang+")",v.voiceURI)));
  if([...el.options].some(o=>o.value===saved))el.value=saved;
}
if(synth){refreshVoices();synth.addEventListener?.("voiceschanged",refreshVoices)}
let replyAudio = null;
function stopReplyAudio(){
  if (!replyAudio) return;
  replyAudio.pause();
  replyAudio = null;
}
function speak(text, mp3, allowBrowserVoice=true){
  stopReplyAudio();
  if (mp3) {
    synth?.cancel();
    bubble.textContent = text;
    const audio = new Audio("data:audio/mpeg;base64," + mp3);
    replyAudio = audio;
    const fallback = () => { if (replyAudio === audio) speak(text, null, allowBrowserVoice); };
    audio.onplaying = () => { if (replyAudio === audio) state("speaking", "Фокси говорит"); };
    audio.onended = () => {
      if (replyAudio !== audio) return;
      stopReplyAudio();
      state("idle", "Можно поговорить ещё");
    };
    audio.onerror = fallback;
    audio.play().catch(fallback);
    return;
  }
  bubble.textContent=text;
  if (!allowBrowserVoice) { state("idle","Голос Марины недоступен",true); return; }
  if(!synth||!window.SpeechSynthesisUtterance){state("idle","Ответ показан на экране");return}
  synth.cancel();
  const cleaned=speechText(text);
  if(!cleaned){state("idle","Ответ показан на экране");return}
  const u=new SpeechSynthesisUtterance(cleaned);
  u.lang="ru-RU";
  const selected=document.getElementById("voice-choice").value;
  const chosen=availableVoices.find(v=>v.voiceURI===selected);
  if(chosen)u.voice=chosen;
  u.rate=parseFloat(document.getElementById("voice-speed").value)||0.95;
  u.pitch=1.05;
  u.onstart=()=>state("speaking","Фокси говорит");
  u.onend=()=>state("idle","Можно поговорить ещё");
  u.onerror=()=>state("idle","Озвучка недоступна",true);
  synth.speak(u);
}
async function request(data, signal){const r=await fetch("/api/chat.php",{method:"POST",cache:"no-store",signal,credentials:"same-origin",headers:{"Content-Type":"application/json"},body:JSON.stringify(data)});const j=await r.json();if(!r.ok){if(r.status===401)csrf="";const error=new Error((j.error||"Не удалось получить ответ")+(j.finish_reason?" (причина: "+j.finish_reason+")":""));error.status=r.status;throw error;}return j}
function showPresence(online, label){
  document.getElementById("foxy-presence").dataset.online = String(online);
  const status = document.getElementById("foxy-presence-label");
  if (status.textContent !== label) status.textContent = label;
}
let presenceCheck = 0;
async function checkPresence(){
  if (document.hidden || !navigator.onLine) return;
  const check = ++presenceCheck;
  const started = performance.now();
  try {
    const result = await request({action:"status"}, AbortSignal.timeout(5000));
    if (check !== presenceCheck || !navigator.onLine) return;
    const online = Boolean(result.ok && result.csrf);
    showPresence(online, online ? `В сети · ${Math.round(performance.now() - started)} мс` : "Нужен вход");
  } catch (error) {
    if (check !== presenceCheck) return;
    showPresence(false, error.message === "Сначала нужен родительский вход" ? "Нужен вход" : "Нет связи");
  }
}
window.addEventListener("online", checkPresence);
window.addEventListener("offline", () => { presenceCheck++; showPresence(false, "Нет связи"); });
document.addEventListener("visibilitychange", checkPresence);
setInterval(checkPresence, 15000);
if (navigator.onLine) checkPresence();
else showPresence(false, "Нет связи");
async function parentLogin(){
  if(csrf)return true;
  try{const s=await request({action:"status"});csrf=s.csrf;return true}catch(e){if(e.status!==401){state("idle","Не удалось проверить вход",true);return false;}}
  const password=window.prompt("Для включения ИИ взрослый должен ввести пароль доступа:");
  if(password===null)return false;
  try{const s=await request({action:"login",password});csrf=s.csrf;showPresence(true,"В сети");state("idle","ИИ подключён, можно поговорить");return true}
  catch(e){state("idle",e.message,true);bubble.textContent="Нужен родительский вход";return false}
}
async function respond(heard,forceSearch=false){
  heard=String(heard||"").trim();
  if(!heard){state("idle","Напиши или произнеси вопрос");return}
  if(sending)return;
  sending=true;sendText.disabled=true;listen.disabled=true;
  try{
    if(!(await parentLogin())){state("idle","Сначала нужен взрослый",true);return}
    state("thinking","Готовлю ответ…");bubble.textContent="Сейчас подумаю…";
    const r=await request({action:"chat",csrf,message:heard,history:history.length ? history : [{role:"assistant",content:greeting}],context:timeContext(),search:forceSearch});
    showWebResult(r.web);
    history.push({role:"user",content:heard},{role:"assistant",content:r.reply});
    history=history.slice(-8);
    turnsSinceLearn++;
    speak(r.reply,r.audio_mp3);
    if(turnsSinceLearn>=4 && history.length>=6 && !learning){
      turnsSinceLearn=0;
      learning=true;
      const turns=history.slice();
      // Nonblocking: never delay a spoken response to write optional memories.
      request({action:"memory_learn",csrf,turns}).catch(e=>{console.warn("Фокси: память не обновилась",e.message)}).finally(()=>{learning=false});
    }
  }catch(e){state("idle",e.message,true);bubble.textContent="Кажется, связь пропала. Давай попробуем позже."}
  finally{sending=false;sendText.disabled=false;listen.disabled=false}
}
textForm.addEventListener("submit",e=>{e.preventDefault();const value=textInput.value.trim();if(!value)return;textInput.value="";respond(value)});
let marinaGreeting = null;
async function playMarinaGreeting(){
  if (sending || busy) return;
  sending = true;
  try {
    if (!(await parentLogin())) return;
    state("thinking", "Фокси готовится поздороваться");
    const result = marinaGreeting || await request({action:"greeting",csrf});
    if (result.audio_mp3) marinaGreeting = result;
    greeting = result.reply;
    speak(greeting, result.audio_mp3, false);
  } catch (error) {
    state("idle",error.message,true);
  } finally {
    sending = false;
  }
}
document.getElementById("demo").addEventListener("click",playMarinaGreeting);
let activeRecognition = null;
listen.addEventListener("click", () => {
  if (sending) return;
  if (activeRecognition) {
    try { activeRecognition.stop(); } catch (error) {
      if (error.name !== "InvalidStateError") state("idle", "Не получилось выключить микрофон", true);
    }
    return;
  }
  if (!Recognition) {
    state("idle", "Браузер не поддерживает микрофон для разговора", true);
    return;
  }
  stopReplyAudio();
  synth?.cancel();
  const rec = new Recognition();
  rec.lang = "ru-RU";
  rec.interimResults = false;
  rec.onstart = () => {
    state("listening", "Микрофон включён");
    bubble.textContent = "Я слушаю!";
  };
  rec.onresult = event => respond(event.results[0][0].transcript);
  rec.onerror = event => {
    const messages = {
      "not-allowed": "Разреши микрофон для сайта в браузере",
      "audio-capture": "Браузер не видит микрофон",
      "network": "Служба распознавания речи недоступна",
      "no-speech": "Не расслышала. Попробуй ещё раз"
    };
    state("idle", messages[event.error] || "Не удалось распознать речь", true);
  };
  rec.onend = () => {
    activeRecognition = null;
    busy = false;
    if (!sending && fox.dataset.state === "listening") {
      state("idle", "Не расслышала. Попробуй ещё раз", true);
    }
  };
  try {
    activeRecognition = rec;
    busy = true;
    rec.start();
  } catch {
    activeRecognition = null;
    busy = false;
    state("idle", "Не получилось включить микрофон", true);
  }
});
document.getElementById("adult").addEventListener("click",()=>{document.getElementById("info").showModal();loadParentNotes();});

const usageButton=document.getElementById("usage-refresh");
const formatRub=value=>typeof value==="number" ? value.toLocaleString("ru-RU",{minimumFractionDigits:3,maximumFractionDigits:4})+" ₽" : "—";
usageButton.addEventListener("click",async()=>{
  const el=document.getElementById("usage-status");
  el.textContent="Загружаем статистику…";
  usageButton.disabled=true;
  try{
    if(!(await parentLogin())){el.textContent="Для просмотра нужен родительский пароль";return}
    const data=await request({action:"usage"});
    const p=data.periods;
    const describe=entry=>formatRub(entry.rub)+" · "+entry.requests+" запр.";
    document.getElementById("usage-today").textContent=describe(p.today);
    document.getElementById("usage-month").textContent=describe(p.month);
    document.getElementById("usage-all").textContent=describe(p.all);
    const last=data.last;
    document.getElementById("usage-last").textContent=last ? formatRub(last.rub)+" · "+last.input+" + "+last.output+" ток." : "Пока нет данных";
    document.getElementById("usage-tokens").textContent="За месяц: входящих "+p.month.input+", исходящих "+p.month.output+", кешированных "+p.month.cached+", рассуждений "+p.month.reasoning+" токенов.";
    document.getElementById("usage-display").hidden=false;
    el.textContent="Обновлено";
  }catch(e){el.textContent=e.message}
  finally{usageButton.disabled=false}
});

const memoryNotes=document.getElementById("memory-notes"),memoryStatus=document.getElementById("memory-status");
async function loadParentNotes(){try{memoryStatus.textContent="Загружаем сведения…";if(!(await parentLogin())){memoryStatus.textContent="Для просмотра нужен родительский пароль";return}const r=await request({action:"memory_get"});memoryNotes.value=r.memory||"";memoryStatus.textContent=r.memory?"Сведения сохранены на сервере":"Пока сведений нет — добавь их и нажми «Сохранить»"}catch(e){memoryStatus.textContent=e.message}}
document.getElementById("memory-load").addEventListener("click",loadParentNotes);
document.getElementById("memory-save").addEventListener("click",async()=>{try{if(!(await parentLogin()))return;await request({action:"memory_save",csrf,notes:memoryNotes.value});memoryStatus.textContent="Память сохранена"}catch(e){memoryStatus.textContent=e.message}});
document.getElementById("memory-clear").addEventListener("click",async()=>{if(!confirm("Удалить все воспоминания Фокси?"))return;try{if(!(await parentLogin()))return;await request({action:"memory_save",csrf,notes:""});memoryNotes.value="";memoryStatus.textContent="Память очищена"}catch(e){memoryStatus.textContent=e.message}});
document.getElementById("voice-choice").addEventListener("change",e=>localStorage.setItem("foxy_voice",e.target.value));
document.getElementById("voice-test").addEventListener("click",playMarinaGreeting);

const autoList=document.getElementById("auto-memory-list");
const autoStatus=document.getElementById("auto-memory-status");
async function loadAutoMemories(){
  try{
    if(!(await parentLogin()))return;
    const data=await request({action:"memory_list"});
    autoList.replaceChildren();
    const probe=data.write_probe||{};
    if(probe.ok) autoStatus.textContent="Проверка записи из браузера: работает.";
    else autoStatus.textContent="Проверка записи из браузера: "+(probe.status||"неизвестная ошибка")+".";

    if(!data.available){autoStatus.textContent="SQLite на сервере недоступен. Проверь расширение pdo_sqlite.";return}
    if(!data.memories.length){
      const labels={not_started:"Анализ разговоров пока не запускался.",analyzing:"Фокси анализирует разговор.",no_facts:"Анализ прошёл, но фактов для памяти не найдено.",facts_filtered:"Найденные факты не прошли проверку.",db_unavailable:"База SQLite недоступна.",not_enough_turns:"Для анализа недостаточно реплик.",db_write_failed:"Не удалось записать результат в SQLite.",sqlite_readonly:"SQLite запрещает запись: проверь права на файл и папку.",sqlite_locked:"База SQLite заблокирована другим процессом.",sqlite_io:"Ошибка ввода-вывода SQLite.",sqlite_syntax:"Ошибка SQL-запроса.",sqlite_schema:"Не найдена таблица SQLite.",sqlite_constraint:"Конфликт ограничений базы SQLite."};
      const d=data.diagnostic||{};
      const info=labels[d.status]||(d.status?.startsWith("provider_error_")?"Ошибка подключения к модели.":d.status?.startsWith("empty_model_reply")?"Модель вернула пустой ответ.":d.status?.startsWith("invalid_json")?"Модель вернула неверный формат ответа.":d.status?.startsWith("sqlite_error_")?"Ошибка SQLite, код "+d.status.slice(13):"Пока нет воспоминаний.");
      autoStatus.textContent=(probe.ok?"Проверка записи: работает. ":"Проверка записи: "+(probe.status||"ошибка")+". ")+info;return
    }
    autoStatus.textContent="Сохранено: "+data.memories.length;
    for(const entry of data.memories){
      const li=document.createElement("li");
      const span=document.createElement("span");span.textContent=entry.content;
      const button=document.createElement("button");button.type="button";button.textContent="Удалить";
      button.addEventListener("click",async()=>{
        if(!confirm("Удалить это воспоминание?"))return;
        try{await request({action:"memory_delete",csrf,id:entry.id});await loadAutoMemories()}catch(e){autoStatus.textContent=e.message}
      });
      li.append(span,button);autoList.appendChild(li);
    }
  }catch(e){autoStatus.textContent=e.message}
}
document.getElementById("auto-memory-refresh").addEventListener("click",loadAutoMemories);
document.getElementById("auto-memory-clear").addEventListener("click",async()=>{
  if(!confirm("Удалить все автоматические воспоминания Фокси?"))return;
  try{if(!(await parentLogin()))return;await request({action:"memory_clear_auto",csrf});await loadAutoMemories()}catch(e){autoStatus.textContent=e.message}
});

function showWebResult(web){
  const el=document.getElementById("web-status"),out=document.getElementById("web-sources");
  out.replaceChildren();
  if(!web||!web.attempted){el.textContent="Для этого ответа поиск не требовался";return}
  if(!web.ok){el.textContent="Поиск не удался. Фокси отвечает без подтверждённых свежих сведений." ;return}
  el.textContent=web.searched?"Фокси использовал поиск Яндекса":"Поисковый инструмент был доступен, но обращения к выдаче не зафиксировано";
  (web.sources||[]).forEach(url=>{
    try{const u=new URL(url);if(u.protocol!=="https:")return;
      const a=document.createElement("a");a.href=u.href;a.target="_blank";a.rel="noopener noreferrer";a.textContent=u.hostname;out.appendChild(a);
    }catch(e){}
  });
}
document.getElementById("web-ask").addEventListener("click",()=>{
  const question=textInput.value.trim();
  if(!question){state("idle","Напиши вопрос для поиска");textInput.focus();return}
  textInput.value="";
  respond(question,true);
});

document.addEventListener("keydown", event => {
  if (event.code !== "Space" || event.defaultPrevented || event.isComposing ||
      event.altKey || event.ctrlKey || event.metaKey || event.shiftKey ||
      document.getElementById("info").open) return;
  const target = event.target;
  if (target !== listen && target.closest("input, textarea, select, button, a, [contenteditable], [role=button]")) return;
  event.preventDefault();
  if (!event.repeat) listen.click();
});
