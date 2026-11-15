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
    const button = form.querySelector("button[type=submit]");
    if (button?.disabled) return;
    if (button) button.disabled = true;
    if (status) {
      status.hidden = false;
      status.classList.remove("orda-notice", "error");
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
      if (status) status.textContent = "Скачивание началось…";
      const download = await fetch(payload.url, { credentials: "same-origin" });
      if (!download.ok) {
        const message = download.headers.get("Content-Type")?.startsWith("text/plain")
          ? await download.text()
          : "";
        throw new Error(message || "Не удалось скачать архив.");
      }
      const archive = await download.blob();
      if (archive.type !== "application/zip" || archive.size === 0) {
        throw new Error("Сервер не прислал ZIP-архив.");
      }

      const disposition = download.headers.get("Content-Disposition") || "";
      const filename = disposition.match(/filename="([a-zA-Z0-9._-]+)"/)?.[1];
      const url = URL.createObjectURL(archive);
      const link = document.createElement("a");
      link.href = url;
      link.download = filename || "foxy-valera.zip";
      document.body.append(link);
      try {
        link.click();
      } finally {
        link.remove();
        window.setTimeout(() => URL.revokeObjectURL(url), 30_000);
      }
      if (status) {
        status.classList.add("orda-notice");
        status.textContent = "Готово. ZIP скачан и передан браузеру на сохранение.";
      }
    } catch (error) {
      if (status) {
        status.classList.add("orda-notice", "error");
        status.textContent = error.message || "Не удалось скачать резервную копию.";
      }
    } finally {
      if (button) button.disabled = false;
    }
  });
});
