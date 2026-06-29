(function () {
  const ICONS = {
    facade:
      '<svg viewBox="0 0 48 48" fill="none" aria-hidden="true"><rect x="8" y="8" width="32" height="32" rx="4" stroke="currentColor" stroke-width="2.5"/><path d="M8 18h32M18 8v32" stroke="currentColor" stroke-width="2.5"/></svg>',
    windows:
      '<svg viewBox="0 0 48 48" fill="none" aria-hidden="true"><circle cx="24" cy="24" r="16" stroke="currentColor" stroke-width="2.5"/><path d="M16 28c4 6 12 6 16 0" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/></svg>',
    snow:
      '<svg viewBox="0 0 48 48" fill="none" aria-hidden="true"><path d="M24 6v36M6 24h36M11 11l26 26M37 11L11 37" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/><circle cx="24" cy="24" r="5" stroke="currentColor" stroke-width="2.5"/></svg>',
    scaffolding:
      '<svg viewBox="0 0 48 48" fill="none" aria-hidden="true"><path d="M10 38V14l14-8 14 8v24" stroke="currentColor" stroke-width="2.5" stroke-linejoin="round"/><path d="M18 38V22h12v16M10 38h28" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/></svg>',
    montage:
      '<svg viewBox="0 0 48 48" fill="none" aria-hidden="true"><path d="M14 34V18l10-8 10 8v16" stroke="currentColor" stroke-width="2.5" stroke-linejoin="round"/><path d="M20 34V24h8v10M10 34h28" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/></svg>',
  };

  const SERVICES = [
    {
      id: "facade",
      priceKey: "calc_price_facade",
      label: "Мойка фасада",
      unit: "m2",
      unitLabel: "м²",
      hint: "Площадь фасада",
    },
    {
      id: "windows",
      priceKey: "calc_price_windows",
      label: "Мойка окон",
      unit: "m2",
      unitLabel: "м²",
      hint: "Площадь остекления",
    },
    {
      id: "snow",
      priceKey: "calc_price_snow",
      label: "Уборка снега с кровли",
      unit: "m2",
      unitLabel: "м²",
      hint: "Площадь кровли",
    },
    {
      id: "scaffolding",
      priceKey: "calc_price_scaffolding",
      label: "Установка строительных лесов",
      unit: "m2",
      unitLabel: "м²",
      hint: "Площадь под леса",
    },
    {
      id: "montage",
      priceKey: "calc_price_montage",
      label: "Монтажные работы",
      unit: "pcs",
      unitLabel: "шт.",
      hint: "Количество точек",
    },
  ];

  const DEFAULT_PRICES = {
    calc_price_facade: 150,
    calc_price_windows: 120,
    calc_price_snow: 80,
    calc_price_scaffolding: 200,
    calc_price_montage: 3500,
  };

  let prices = { ...DEFAULT_PRICES };

  function settingsApiUrl() {
    return document.querySelector('meta[name="api-settings"]')?.content || "/api/settings.php";
  }

  function formatMoney(value) {
    return new Intl.NumberFormat("ru-RU", {
      style: "currency",
      currency: "RUB",
      maximumFractionDigits: 0,
    }).format(value);
  }

  function parseNumber(value) {
    const normalized = String(value).replace(",", ".").trim();
    const number = Number(normalized);
    return Number.isFinite(number) && number > 0 ? number : 0;
  }

  function getPrice(key) {
    const value = Number(prices[key]);
    return Number.isFinite(value) && value >= 0 ? value : DEFAULT_PRICES[key] || 0;
  }

  function unitText(service) {
    return service.unit === "pcs" ? "₽/шт." : "₽/м²";
  }

  function setRowState(row, enabled) {
    row.classList.toggle("is-on", enabled);
    row.setAttribute("aria-pressed", enabled ? "true" : "false");

    const input = row.querySelector(".calculator__input");
    if (!input) return;

    if (enabled) {
      input.disabled = false;
    } else {
      input.value = "";
      input.disabled = true;
    }
  }

  function toggleRow(row, focusInput = false) {
    const enabled = !row.classList.contains("is-on");
    setRowState(row, enabled);

    if (enabled && focusInput) {
      row.querySelector(".calculator__input")?.focus();
    }
  }

  function renderLines(container) {
    container.innerHTML = SERVICES.map((service) => {
      const price = getPrice(service.priceKey);
      const icon = ICONS[service.id] || ICONS.facade;

      return `
        <div
          class="calc-row"
          data-service="${service.id}"
          role="button"
          tabindex="0"
          aria-pressed="false"
          aria-label="${service.label}"
        >
          <div class="calc-row__service">
            <span class="calc-row__icon" aria-hidden="true">${icon}</span>
            <div class="calc-row__info">
              <strong class="calc-row__name">${service.label}</strong>
              <span class="calc-row__rate" data-rate="${service.id}">${formatMoney(price)} ${unitText(service)}</span>
            </div>
          </div>
          <div class="calc-row__qty" data-noclick>
            <label class="calc-row__qty-label visually-hidden">${service.hint}</label>
            <div class="calc-row__qty-field">
              <input
                type="number"
                class="calculator__input"
                data-service="${service.id}"
                min="0"
                step="1"
                inputmode="decimal"
                placeholder="0"
                disabled
                aria-label="${service.hint}"
              >
              <span class="calc-row__unit">${service.unitLabel}</span>
            </div>
          </div>
          <output class="calc-row__total calculator__line-total" data-service="${service.id}">—</output>
        </div>
      `;
    }).join("");
  }

  function updateRates(container) {
    SERVICES.forEach((service) => {
      const rate = container.querySelector(`[data-rate="${service.id}"]`);
      if (rate) {
        rate.textContent = `${formatMoney(getPrice(service.priceKey))} ${unitText(service)}`;
      }
    });
  }

  function updateBreakdown(items) {
    const list = document.getElementById("calculator-breakdown");
    const empty = document.getElementById("calculator-empty");
    if (!list || !empty) return;

    if (!items.length) {
      list.hidden = true;
      list.innerHTML = "";
      empty.hidden = false;
      return;
    }

    empty.hidden = true;
    list.hidden = false;
    list.innerHTML = items
      .map(
        (item) => `
          <li>
            <span>${item.label}</span>
            <strong>${formatMoney(item.sum)}</strong>
          </li>
        `,
      )
      .join("");
  }

  function calculate(container, totalEl) {
    let total = 0;
    const breakdown = [];

    SERVICES.forEach((service) => {
      const row = container.querySelector(`.calc-row[data-service="${service.id}"]`);
      const input = row?.querySelector(".calculator__input");
      const output = row?.querySelector(".calculator__line-total");
      const enabled = Boolean(row?.classList.contains("is-on"));
      const qty = enabled ? parseNumber(input?.value) : 0;
      const sum = qty * getPrice(service.priceKey);

      row?.classList.toggle("has-value", enabled && qty > 0);

      if (output) {
        if (enabled && qty > 0) {
          output.textContent = formatMoney(sum);
        } else if (enabled) {
          output.textContent = "0 ₽";
        } else {
          output.textContent = "—";
        }
      }

      total += sum;

      if (enabled && qty > 0) {
        breakdown.push({
          label: `${service.label} · ${qty} ${service.unitLabel}`,
          sum,
        });
      }
    });

    if (totalEl) {
      totalEl.textContent = formatMoney(total);
      totalEl.classList.toggle("is-filled", total > 0);
    }

    updateBreakdown(breakdown);
  }

  function bindEvents(container, totalEl) {
    container.addEventListener("input", (event) => {
      if (event.target.matches(".calculator__input")) {
        const row = event.target.closest(".calc-row");
        if (row && !row.classList.contains("is-on")) {
          setRowState(row, true);
        }
        calculate(container, totalEl);
      }
    });

    container.addEventListener("click", (event) => {
      if (event.target.closest("[data-noclick], .calculator__input")) {
        const row = event.target.closest(".calc-row");
        if (row && !row.classList.contains("is-on") && event.target.closest(".calc-row__qty")) {
          toggleRow(row, true);
          calculate(container, totalEl);
        }
        return;
      }

      const row = event.target.closest(".calc-row");
      if (!row) return;

      toggleRow(row, true);
      calculate(container, totalEl);
    });

    container.addEventListener("keydown", (event) => {
      const row = event.target.closest(".calc-row");
      if (!row || event.target.matches(".calculator__input")) return;

      if (event.key === "Enter" || event.key === " ") {
        event.preventDefault();
        toggleRow(row, true);
        calculate(container, totalEl);
      }
    });
  }

  async function loadPrices() {
    try {
      const response = await fetch(settingsApiUrl(), {
        headers: { Accept: "application/json" },
        cache: "no-store",
      });

      if (!response.ok) return;

      const data = await response.json();
      if (!data.success || !data.settings) return;

      SERVICES.forEach((service) => {
        const raw = data.settings[service.priceKey];
        const value = Number(String(raw).replace(",", "."));
        if (Number.isFinite(value) && value >= 0) {
          prices[service.priceKey] = value;
        }
      });
    } catch (error) {
      console.warn("Калькулятор: не удалось загрузить цены.", error);
    }
  }

  async function init() {
    const lines = document.getElementById("calculator-lines");
    const totalEl = document.getElementById("calculator-total");
    if (!lines) return;

    await loadPrices();
    renderLines(lines);
    updateRates(lines);
    bindEvents(lines, totalEl);
    calculate(lines, totalEl);
  }

  document.addEventListener("DOMContentLoaded", init);
})();
