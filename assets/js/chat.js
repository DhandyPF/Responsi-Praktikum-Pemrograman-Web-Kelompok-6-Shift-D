// Pembaruan pesan near-real-time lewat polling sederhana. Konten disisipkan dengan textContent (aman dari XSS).
(() => {
  const log = document.getElementById("chatLog");
  if (!log) return;
  const form = document.getElementById("chatForm");
  const box = document.getElementById("isi");
  let last = Number(log.dataset.last || 0);
  const nearBottom = () => log.scrollHeight - log.scrollTop - log.clientHeight < 120;
  const toBottom = () => { log.scrollTop = log.scrollHeight; };
  toBottom();

  const addMessage = (m) => {
    if (log.querySelector('[data-id="' + m.id + '"]')) return;
    log.querySelector(".chat-empty")?.remove();
    const row = document.createElement("div");
    row.className = "msg " + (m.mine ? "mine" : "theirs");
    row.dataset.id = String(m.id);
    const bubble = document.createElement("div");
    bubble.className = "bubble";
    bubble.style.whiteSpace = "pre-wrap";
    bubble.textContent = m.isi;
    const meta = document.createElement("div");
    meta.className = "msg-meta";
    meta.append(m.waktu);
    if (m.mine) {
      meta.append(" · ");
      const st = document.createElement("span");
      st.className = "msg-status";
      st.textContent = "✓ Terkirim";
      meta.append(st);
    }
    row.append(bubble, meta);
    log.insertBefore(row, document.getElementById("bawah"));
  };

  let busy = false;
  const poll = async () => {
    if (busy || document.hidden) return;
    busy = true;
    try {
      const res = await fetch(log.dataset.url + "&after=" + last, { credentials: "same-origin", headers: { Accept: "application/json" } });
      if (!res.ok) return;
      const data = await res.json();
      const stick = nearBottom();
      (data.messages || []).forEach((m) => { addMessage(m); last = Math.max(last, m.id); });
      (data.read_ids || []).forEach((id) => {
        const s = log.querySelector('[data-id="' + id + '"] .msg-status');
        if (s) s.textContent = "✓✓ Dibaca";
      });
      if (stick && (data.messages || []).length) toBottom();
    } catch (_) {
      /* jaringan terputus: coba lagi pada siklus berikutnya */
    } finally {
      busy = false;
    }
  };
  setInterval(poll, 4000);
  document.addEventListener("visibilitychange", poll);

  box?.addEventListener("keydown", (e) => {
    if (e.key === "Enter" && !e.shiftKey && !e.isComposing) {
      e.preventDefault();
      if (box.value.trim() !== "") form.requestSubmit();
    }
  });
})();
