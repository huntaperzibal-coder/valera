"use strict";
document.querySelectorAll("[data-copy]").forEach((button) => {
  button.addEventListener("click", async () => {
    const text = button.getAttribute("data-copy") || "";
    const feedback = document.querySelector(".copy-feedback");
    try {
      await navigator.clipboard.writeText(text);
      if (feedback) feedback.textContent = "Скопировано.";
    } catch {
      if (feedback) feedback.textContent = "Не удалось скопировать.";
    }
  });
});

document.querySelectorAll("form[data-backup-download]").forEach((form) => {
  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    const status = form.querySelector("[role=status]");
    if (status) {
      status.hidden = false;
      status.textContent = "Готовим архив… это может занять минуту.";
    }
    const target = form.getAttribute("action") || "backup-download.php";
    try {
      const response = await fetch(target, {
        method: "POST",
        credentials: "same-origin",
        headers: { Accept: "application/json" },
        body: new FormData(form),
      });
      const raw = await response.text();
      let payload;
      try {
        payload = JSON.parse(raw);
      } catch {
        throw new Error("Сервер вернул неожиданный ответ. Проверь расширение PHP zip и свободное место на диске.");
      }
      if (!response.ok || payload.error) {
        throw new Error(payload.error || "Не удалось подготовить копию");
      }
      if (!payload.url) {
        throw new Error("Сервер не вернул ссылку на архив.");
      }
      window.location.href = payload.url;
      if (status) status.textContent = "Скачивание началось.";
    } catch (error) {
      if (status) status.textContent = error.message || "Не удалось подготовить копию.";
    }
  });
});