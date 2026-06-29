(function () {
  const SERVICES = [
    {
      id: "facade",
      priceKey: "calc_price_facade",
      label: "Мойка фасада",
      unit: "m2",
      unitLabel: "м²",
      placeholder: "Площадь фасада",
    },
    {
      id: "windows",
      priceKey: "calc_price_windows",
      label: "Мойка окон",
      unit: "m2",
      unitLabel: "м²",
      placeholder: "Площадь остекления",
    },
    {
      id: "snow",
      priceKey: "calc_price_snow",
      label: "Уборка снега с кровли",
      unit: "m2",
      unitLabel: "м²",
      placeholder: "Площадь кровли",
    },
    {
      id: "scaffolding",
      priceKey: "calc_price_scaffolding",
      label: "Установка строительных лесов",
      unit: "m2",
      unitLabel: "м²",
      placeholder: "Площадь фасада под леса",
    },
    {
      id: "montage",
      priceKey: "calc_price_montage",
      label: "Монтажные работы",
      unit: "pcs",
      unitLabel: "шт.",
      placeholder: "Количество точек",
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

  function renderLines(container) {
    container.innerHTML = SERVICES.map((service) => {
      const price = getPrice(service.priceKey);
      const unitText = service.unit === "pcs" ? "₽/шт." : "₽/м²";

      return `
        <div class="calculator__line" data-service="${service.id}">
          <label class="calculator__check">
            <input type="checkbox" class="calculator__toggle" data-service="${service.id}">
            <span class="calculator__name">${service.label}</span>
            <span class="calculator__rate">${formatMoney(price)} ${unitText}</span>
          </label>
          <div class="calculator__fields">
            <label class="calculator__qty">
              <span class="visually-hidden">${service.placeholder}</span>
              <input
                type="number"
                class="calculator__input"
                data-service="${service.id}"
                min="0"
                step="1"
                inputmode="decimal"
                placeholder="${service.placeholder}"
                disabled
              >
              <span class="calculator__unit">${service.unitLabel}</span>
            </label>
            <output class="calculator__line-total" data-service="${service.id}">0 ₽</output>
          </div>
        </div>
      `;
    }).join("");
  }

  function updateRates(container) {
    SERVICES.forEach((service) => {
      const line = container.querySelector(`.calculator__line[data-service="${service.id}"]`);
      if (!line) return;
      const rate = line.querySelector(".calculator__rate");
      if (!rate) return;
      const unitText = service.unit === "pcs" ? "₽/шт." : "₽/м²";
      rate.textContent = `${formatMoney(getPrice(service.priceKey))} ${unitText}`;
    });
  }

  function calculate(container, totalEl) {
    let total = 0;

    SERVICES.forEach((service) => {
      const line = container.querySelector(`.calculator__line[data-service="${service.id}"]`);
      const toggle = line?.querySelector(".calculator__toggle");
      const input = line?.querySelector(".calculator__input");
      const output = line?.querySelector(".calculator__line-total");
      const enabled = Boolean(toggle?.checked);
      const qty = enabled ? parseNumber(input?.value) : 0;
      const sum = qty * getPrice(service.priceKey);

      if (output) {
        output.textContent = formatMoney(sum);
        output.classList.toggle("is-active", enabled && qty > 0);
      }

      if (input) {
        input.disabled = !enabled;
        line?.classList.toggle("is-active", enabled);
      }

      total += sum;
    });

    if (totalEl) {
      totalEl.textContent = formatMoney(total);
    }
  }

  function bindEvents(container, totalEl) {
    container.addEventListener("input", (event) => {
      if (event.target.matches(".calculator__input, .calculator__toggle")) {
        calculate(container, totalEl);
      }
    });

    container.addEventListener("change", (event) => {
      if (event.target.matches(".calculator__toggle")) {
        const serviceId = event.target.dataset.service;
        const input = container.querySelector(`.calculator__input[data-service="${serviceId}"]`);
        if (event.target.checked && input) {
          input.focus();
        } else if (input) {
          input.value = "";
        }
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
