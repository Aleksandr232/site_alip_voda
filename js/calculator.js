(function () {
  const ICONS = {
    facade:
      '<svg viewBox="0 0 48 48" fill="none" aria-hidden="true"><path d="M24 4L8 20h10v20h12V20h10L24 4z" stroke="currentColor" stroke-width="2.5" stroke-linejoin="round"/></svg>',
    windows:
      '<svg viewBox="0 0 48 48" fill="none" aria-hidden="true"><rect x="8" y="8" width="32" height="32" rx="4" stroke="currentColor" stroke-width="2.5"/><path d="M8 18h32M18 8v32" stroke="currentColor" stroke-width="2.5"/></svg>',
    snow:
      '<svg viewBox="0 0 48 48" fill="none" aria-hidden="true"><path d="M24 6v36M6 24h36M11 11l26 26M37 11L11 37" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/><circle cx="24" cy="24" r="5" stroke="currentColor" stroke-width="2.5"/></svg>',
    scaffolding:
      '<svg viewBox="0 0 48 48" fill="none" aria-hidden="true"><path d="M10 38V14l14-8 14 8v24" stroke="currentColor" stroke-width="2.5" stroke-linejoin="round"/><path d="M18 38V22h12v16M10 38h28" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/></svg>',
    montage:
      '<svg viewBox="0 0 48 48" fill="none" aria-hidden="true"><path d="M14 34l10-20 10 20" stroke="currentColor" stroke-width="2.5" stroke-linejoin="round"/><path d="M18 28h12M12 38h24" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/></svg>',
  };

  const SERVICES = [
    {
      id: "facade",
      priceKey: "calc_price_facade",
      label: "Мойка фасада",
      unit: "m2",
      unitLabel: "м²",
      placeholder: "0",
      hint: "Площадь фасада",
    },
    {
      id: "windows",
      priceKey: "calc_price_windows",
      label: "Мойка окон",
      unit: "m2",
      unitLabel: "м²",
      placeholder: "0",
      hint: "Площадь остекления",
    },
    {
      id: "snow",
      priceKey: "calc_price_snow",
      label: "Уборка снега с кровли",
      unit: "m2",
      unitLabel: "м²",
      placeholder: "0",
      hint: "Площадь кровли",
    },
    {
      id: "scaffolding",
      priceKey: "calc_price_scaffolding",
      label: "Установка строительных лесов",
      unit: "m2",
      unitLabel: "м²",
      placeholder: "0",
      hint: "Площадь под леса",
    },
    {
      id: "montage",
      priceKey: "calc_price_montage",
      label: "Монтажные работы",
      unit: "pcs",
      unitLabel: "шт.",
      placeholder: "0",
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

  function renderLines(container) {
    container.innerHTML = SERVICES.map((service) => {
      const price = getPrice(service.priceKey);
      const icon = ICONS[service.id] || ICONS.facade;

      return `
        <article class="calc-card" data-service="${service.id}">
          <label class="calc-card__switch" title="Включить услугу">
            <input type="checkbox" class="calculator__toggle" data-service="${service.id}">
            <span class="calc-card__switch-ui" aria-hidden="true"></span>
          </label>
          <div class="calc-card__icon">${icon}</div>
          <div class="calc-card__main">
            <h3 class="calc-card__title">${service.label}</h3>
            <span class="calc-card__rate">${formatMoney(price)} <span>${unitText(service)}</span></span>
            <div class="calc-card__field">
              <span class="calc-card__field-label">${service.hint}</span>
              <div class="calc-card__input-wrap">
                <input
                  type="number"
                  class="calculator__input"
                  data-service="${service.id}"
                  min="0"
                  step="1"
                  inputmode="decimal"
                  placeholder="${service.placeholder}"
                  disabled
                  aria-label="${service.hint}"
                >
                <span class="calc-card__unit">${service.unitLabel}</span>
              </div>
            </div>
          </div>
          <div class="calc-card__sum">
            <span class="calc-card__sum-label">Сумма</span>
            <output class="calculator__line-total" data-service="${service.id}">—</output>
          </div>
        </article>
      `;
    }).join("");
  }

  function updateRates(container) {
    SERVICES.forEach((service) => {
      const card = container.querySelector(`.calc-card[data-service="${service.id}"]`);
      const rate = card?.querySelector(".calc-card__rate");
      if (!rate) return;
      rate.innerHTML = `${formatMoney(getPrice(service.priceKey))} <span>${unitText(service)}</span>`;
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
      const card = container.querySelector(`.calc-card[data-service="${service.id}"]`);
      const toggle = card?.querySelector(".calculator__toggle");
      const input = card?.querySelector(".calculator__input");
      const output = card?.querySelector(".calculator__line-total");
      const enabled = Boolean(toggle?.checked);
      const qty = enabled ? parseNumber(input?.value) : 0;
      const sum = qty * getPrice(service.priceKey);

      if (input) {
        input.disabled = !enabled;
      }

      card?.classList.toggle("is-active", enabled);
      card?.classList.toggle("has-value", enabled && qty > 0);

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
        calculate(container, totalEl);
      }
    });

    container.addEventListener("change", (event) => {
      if (!event.target.matches(".calculator__toggle")) return;

      const serviceId = event.target.dataset.service;
      const input = container.querySelector(`.calculator__input[data-service="${serviceId}"]`);

      if (event.target.checked && input) {
        input.disabled = false;
        input.focus();
      } else if (input) {
        input.value = "";
        input.disabled = true;
      }

      calculate(container, totalEl);
    });

    container.addEventListener("click", (event) => {
      const card = event.target.closest(".calc-card");
      if (!card || event.target.closest(".calculator__input, .calc-card__switch")) return;

      const toggle = card.querySelector(".calculator__toggle");
      if (!toggle) return;

      toggle.checked = !toggle.checked;
      toggle.dispatchEvent(new Event("change", { bubbles: true }));
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
      console.warn("Калькулятор: не удалось загрузить цены, используются значения по умолчанию.", error);
    }
  }

  async function init() {
    const root = document.getElementById("calculator-root");
    const lines = document.getElementById("calculator-lines");
    const totalEl = document.getElementById("calculator-total");
    if (!root || !lines) return;

    await loadPrices();
    renderLines(lines);
    updateRates(lines);
    bindEvents(lines, totalEl);
    calculate(lines, totalEl);
  }

  document.addEventListener("DOMContentLoaded", init);
})();
