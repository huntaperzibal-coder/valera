"use strict";
const fox=document.getElementById("fox"),bubble=document.getElementById("bubble"),status=document.getElementById("status"),listen=document.getElementById("listen");
const Recognition=window.SpeechRecognition||window.webkitSpeechRecognition;
const synth=window.speechSynthesis;
let busy=false,turn=0;
const replies=["Привет! Я Валера! Хочешь загадку про лес?","Слушай загадку: зимой и летом одним цветом. Что это?","Давай придумаем вместе сказку про волшебного дракончика!"];
function state(s,t){fox.dataset.state=s;status.textContent=t}
function speak(text){bubble.textContent=text;if(!synth||!window.SpeechSynthesisUtterance){state("idle","Озвучка в браузере недоступна");return} synth.cancel();const u=new SpeechSynthesisUtterance(text);u.lang="ru-RU";u.rate=.88;u.onstart=()=>state("speaking","Валера говорит");u.onend=()=>state("idle","Можно поговорить ещё");u.onerror=()=>state("idle","Не удалось воспроизвести речь");synth.speak(u)}
function respond(heard){state("thinking","Валера думает");if(heard)bubble.textContent="Я услышал: «"+heard.slice(0,90)+"»";setTimeout(()=>speak(replies[turn++%replies.length]),700)}
document.getElementById("demo").addEventListener("click",()=>respond(""));
listen.addEventListener("click",()=>{if(busy)return;if(!Recognition){state("idle","Браузер не поддерживает распознавание");respond("");return} if(synth)synth.cancel();const rec=new Recognition();rec.lang="ru-RU";rec.interimResults=false;rec.onstart=()=>{busy=true;state("listening","Я тебя слушаю");bubble.textContent="Я слушаю!"};rec.onresult=e=>respond(e.results[0][0].transcript);rec.onerror=e=>{state("idle",e.error==="not-allowed"?"Разреши микрофон в браузере":"Не расслышал, повтори");bubble.textContent="Попробуем ещё?"};rec.onend=()=>{busy=false;if(fox.dataset.state==="listening")state("idle","Попробуй ещё раз")};try{rec.start()}catch(e){state("idle","Не получилось включить микрофон")}});
document.getElementById("adult").addEventListener("click",()=>document.getElementById("info").showModal());
