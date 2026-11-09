"use strict";
const fox=document.getElementById("fox"),bubble=document.getElementById("bubble"),status=document.getElementById("status"),listen=document.getElementById("listen"),textForm=document.getElementById("text-form"),textInput=document.getElementById("message-input"),sendText=document.getElementById("send-text");
const Recognition=window.SpeechRecognition||window.webkitSpeechRecognition;
const synth=window.speechSynthesis;
let busy=false,turn=0,csrf="",history=[],sending=false;
const replies=["Привет! Я Фокси! Хочешь загадку про лес?","Слушай загадку: зимой и летом одним цветом. Что это?","Давай придумаем вместе сказку про волшебного дракончика!"];
function state(s,t){fox.dataset.state=s;document.getElementById("voice-indicator").dataset.state=s;status.textContent=t}
function speak(text){bubble.textContent=text;if(!synth||!window.SpeechSynthesisUtterance){state("idle","Ответ показан на экране");return} synth.cancel();const u=new SpeechSynthesisUtterance(text);u.lang="ru-RU";u.rate=.88;u.onstart=()=>state("speaking","Фокси говорит");u.onend=()=>state("idle","Можно поговорить ещё");u.onerror=()=>state("idle","Ответ на экране, но озвучка недоступна");synth.speak(u)}
async function request(data){const r=await fetch("/api/chat.php",{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json"},body:JSON.stringify(data)});const j=await r.json();if(!r.ok)throw new Error((j.error||"Не удалось получить ответ")+(j.finish_reason?" (причина: "+j.finish_reason+")":""));return j}
async function parentLogin(){
  if(csrf)return true;
  try{const s=await request({action:"status"});csrf=s.csrf;return true}catch(e){}
  const password=window.prompt("Для включения ИИ взрослый должен ввести пароль доступа:");
  if(password===null)return false;
  try{const s=await request({action:"login",password});csrf=s.csrf;state("idle","ИИ подключён, можно поговорить");return true}
  catch(e){state("idle",e.message);bubble.textContent="Нужен родительский вход";return false}
}
async function respond(heard){
  heard=String(heard||"").trim();
  if(!heard){state("idle","Напиши или произнеси вопрос");return}
  if(sending)return;
  sending=true;sendText.disabled=true;listen.disabled=true;
  try{
    if(!(await parentLogin())){state("idle","Сначала нужен взрослый");return}
    state("thinking","Фокси думает");bubble.textContent="Сейчас подумаю…";
    const r=await request({action:"chat",csrf,message:heard,history});
    history.push({role:"user",content:heard},{role:"assistant",content:r.reply});
    history=history.slice(-8);
    speak(r.reply);
  }catch(e){state("idle",e.message);bubble.textContent="Кажется, связь пропала. Давай попробуем позже."}
  finally{sending=false;sendText.disabled=false;listen.disabled=false}
}
textForm.addEventListener("submit",e=>{e.preventDefault();const value=textInput.value.trim();if(!value)return;textInput.value="";respond(value)});
document.getElementById("demo").addEventListener("click",()=>{state("thinking","Фокси придумывает");setTimeout(()=>speak(replies[turn++%replies.length]),650)});
listen.addEventListener("click",()=>{if(busy||sending)return;if(!Recognition){state("idle","Браузер не поддерживает распознавание речи");return}if(synth)synth.cancel();const rec=new Recognition();rec.lang="ru-RU";rec.interimResults=false;rec.onstart=()=>{busy=true;state("listening","Я тебя слушаю");bubble.textContent="Я слушаю!"};rec.onresult=e=>respond(e.results[0][0].transcript);rec.onerror=e=>{state("idle",e.error==="not-allowed"?"Разреши микрофон в браузере":"Не расслышал, повтори");bubble.textContent="Попробуем ещё?"};rec.onend=()=>{busy=false;if(fox.dataset.state==="listening")state("idle","Попробуй ещё раз")};try{rec.start()}catch(e){state("idle","Не получилось включить микрофон")}});
document.getElementById("adult").addEventListener("click",()=>document.getElementById("info").showModal());

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
