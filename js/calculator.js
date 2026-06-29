(function () {
  const THUMBS = {
    facade:
      "https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?w=96&h=96&fit=crop&q=80",
    windows:
      "https://images.unsplash.com/photo-1545324418-cc1a3fa10c00?w=96&h=96&fit=crop&q=80",
    snow:
      "https://images.unsplash.com/photo-1515165562835-39bbff16e79f?w=96&h=96&fit=crop&q=80",
    scaffolding:
      "https://images.unsplash.com/photo-1504307651254-71820befef20?w=96&h=96&fit=crop&q=80",
    montage:
      "https://images.unsplash.com/photo-1541888946425-d81bb19240f5?w=96&h=96&fit=crop&q=80",
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

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
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
      const thumb = THUMBS[service.id] || THUMBS.facade;

      return `
        <div class="calc-row" data-service="${service.id}">
          <label class="calc-row__pick">
            <input type="checkbox" class="calculator__toggle" data-service="${service.id}">
            <span class="calc-row__check" aria-hidden="true"></span>
          </label>
          <div class="calc-row__service">
            <span class="calc-row__thumb">
              <img src="${thumb}" alt="${escapeHtml(service.label)}" width="36" height="36" loading="lazy" decoding="async">
            </span>
            <div class="calc-row__info">
              <strong class="calc-row__name">${service.label}</strong>
              <span class="calc-row__rate" data-rate="${service.id}">${formatMoney(price)} ${unitText(service)}</span>
            </div>
          </div>
          <div class="calc-row__qty">
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
      const toggle = row?.querySelector(".calculator__toggle");
      const input = row?.querySelector(".calculator__input");
      const output = row?.querySelector(".calculator__line-total");
      const enabled = Boolean(toggle?.checked);
      const qty = enabled ? parseNumber(input?.value) : 0;
      const sum = qty * getPrice(service.priceKey);

      if (input) {
        input.disabled = !enabled;
      }

      row?.classList.toggle("is-on", enabled);
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
