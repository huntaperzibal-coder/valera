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
      status.textContent = "Готовим архив…";
    }
    try {
      const response = await fetch(form.action || window.location.href, {
        method: "POST",
        credentials: "same-origin",
        body: new FormData(form),
      });
      const payload = await response.json();
      if (!response.ok) throw new Error(payload.error || "Ошибка");
      window.location.href = payload.url;
      if (status) status.textContent = "Скачивание началось.";
    } catch (error) {
      if (status) status.textContent = error.message || "Не удалось подготовить копию.";
    }
  });
});