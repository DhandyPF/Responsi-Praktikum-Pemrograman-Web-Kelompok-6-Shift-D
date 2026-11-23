document.addEventListener("DOMContentLoaded", () => {
  const themeToggle = document.getElementById("themeToggle");
  const savedTheme = localStorage.getItem("nusakarya-theme");
  const prefersDark = window.matchMedia("(prefers-color-scheme: dark)").matches;
  const initialTheme = savedTheme || (prefersDark ? "dark" : "light");
  const applyTheme = (theme) => {
    document.documentElement.dataset.theme = theme;
    document.body.dataset.theme = theme;
    if (themeToggle) {
      const dark = theme === "dark";
      themeToggle.setAttribute(
        "aria-label",
        dark ? "Aktifkan mode terang" : "Aktifkan mode gelap",
      );
      themeToggle.setAttribute("aria-pressed", String(dark));
    }
  };
  applyTheme(initialTheme);
  themeToggle?.addEventListener("click", () => {
    const nextTheme = document.body.dataset.theme === "dark" ? "light" : "dark";
    localStorage.setItem("nusakarya-theme", nextTheme);
    applyTheme(nextTheme);
  });

  const toggle = document.getElementById("menuToggle");
  const nav = document.getElementById("navLinks");
  if (toggle && nav)
    toggle.addEventListener("click", () => {
      const open = nav.classList.toggle("open");
      toggle.setAttribute("aria-expanded", String(open));
    });

  // Filter client-side pada kartu yang telah dirender server.
  const search = document.querySelector("[data-filter-search]");
  const category = document.querySelector("[data-filter-category]");
  const location = document.querySelector("[data-filter-location]");
  const cards = [...document.querySelectorAll("[data-listing-card]")];
  const applyFilter = () => {
    const q = (search?.value || "").toLowerCase().trim();
    const c = category?.value || "";
    const l = (location?.value || "").toLowerCase().trim();
    cards.forEach((card) => {
      const haystack = (card.dataset.search || "").toLowerCase();
      const match =
        haystack.includes(q) &&
        (!c || card.dataset.category === c) &&
        (!l || (card.dataset.location || "").toLowerCase().includes(l));
      card.hidden = !match;
    });
  };
  [search, category, location].filter(Boolean).forEach((el) => {
    el.addEventListener("input", applyFilter);
    el.addEventListener("change", applyFilter);
  });

  // Estimasi upah sederhana: tarif per jam x jam kerja x hari.
  const wageForm = document.getElementById("wageCalculator");
  if (wageForm)
    wageForm.addEventListener("submit", (e) => {
      e.preventDefault();
      const rate = Number(document.getElementById("rateHour").value);
      const hours = Number(document.getElementById("hoursDay").value);
      const days = Number(document.getElementById("daysMonth").value);
      const result = document.getElementById("wageResult");
      if (!rate || !hours || !days || rate < 0 || hours < 0 || days < 0) {
        result.textContent = "Masukkan angka yang valid.";
        return;
      }
      result.textContent = new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        maximumFractionDigits: 0,
      }).format(rate * hours * days);
    });

  document.querySelectorAll("form[data-confirm]").forEach((form) =>
    form.addEventListener("submit", (e) => {
      if (!window.confirm(form.dataset.confirm || "Lanjutkan tindakan ini?"))
        e.preventDefault();
    }),
  );
});
