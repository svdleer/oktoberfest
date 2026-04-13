const output = document.getElementById("output");
const refreshButton = document.getElementById("refreshButton");
const timeslotSelect = document.getElementById("timeslot");
const tableWrap = document.getElementById("tableWrap");
const venueSelect = document.getElementById("venueSelect");
const langEnButton = document.getElementById("langEn");
const langDeButton = document.getElementById("langDe");

let latestMatrix = null;
let currentLang = "en";

const I18N = {
  en: {
    title: "Oktoberfest Reservation Matrix",
    subtitle: "Live status grid by tent, date, and timeslot",
    timeslotLabel: "Timeslot",
    venueLabel: "Venue",
    refresh: "Refresh",
    legendGreen: "Green = Available",
    legendRed: "Red = No products",
    legendGray: "Unavail = Not offered",
    allTents: "All tents",
    matrixTitle: "Reservation Matrix",
    matrixTent: "Tent",
    venueTitle: "Ticket Types Per Venue",
    venue: "Venue",
    guestGroups: "Guest Groups",
    tableSizes: "Table Sizes",
    timeslots: "Timeslots",
    ticketSales: "Ticket Sales",
    salesNote: "Sales Note",
    open: "Open",
    closed: "Closed",
    loaded: "Loaded",
    loading: "Loading matrix...",
    requestFailed: "Request failed",
    matrixUnavailable: "Could not load matrix.",
    all: "All",
    mittag: "Lunch",
    abend: "Evening",
  },
  de: {
    title: "Oktoberfest Reservierungs-Matrix",
    subtitle: "Live-Status nach Zelt, Datum und Zeitslot",
    timeslotLabel: "Zeitslot",
    venueLabel: "Zelt",
    refresh: "Aktualisieren",
    legendGreen: "Gruen = Verfuegbar",
    legendRed: "Rot = Keine Produkte",
    legendGray: "Unavail = Nicht angeboten",
    allTents: "Alle Zelte",
    matrixTitle: "Reservierungs-Matrix",
    matrixTent: "Zelt",
    venueTitle: "Ticketarten pro Zelt",
    venue: "Zelt",
    guestGroups: "Gaestegruppen",
    tableSizes: "Tischgroessen",
    timeslots: "Zeitslots",
    ticketSales: "Ticketverkauf",
    salesNote: "Hinweis",
    open: "Offen",
    closed: "Geschlossen",
    loaded: "Geladen",
    loading: "Matrix wird geladen...",
    requestFailed: "Anfrage fehlgeschlagen",
    matrixUnavailable: "Matrix konnte nicht geladen werden.",
    all: "Alle",
    mittag: "Mittag",
    abend: "Abend",
  },
};

function t(key) {
  return I18N[currentLang][key] || key;
}

function applyLanguage(lang) {
  currentLang = lang;
  document.documentElement.lang = lang;
  localStorage.setItem("oktoberfest-lang", lang);

  document.querySelectorAll("[data-i18n]").forEach((el) => {
    const key = el.getAttribute("data-i18n");
    el.textContent = t(key);
  });

  document.querySelectorAll("[data-i18n-option]").forEach((option) => {
    const key = option.getAttribute("data-i18n-option");
    option.textContent = t(key);
  });

  langEnButton.classList.toggle("active", lang === "en");
  langDeButton.classList.toggle("active", lang === "de");

  if (latestMatrix) {
    renderMatrix();
  }
}

function statusBadgeClass(status) {
  if (status === "green") return "text-bg-success";
  if (status === "red") return "text-bg-danger";
  return "text-bg-secondary";
}

function renderTimeslotBadges(cell) {
  const slots = ["mittag", "abend"];
  return slots
    .map((slot) => {
      const slotStatus = cell.slotStatus?.[slot] || "unavail";
      const slotUrl = cell.slotLinks?.[slot] || "#";
      const badge = `slot-link slot-badge badge ${statusBadgeClass(slotStatus)}`;
      const label = t(slot);

      if (slotStatus === "unavail") {
        return "";
      }

      if (slotStatus === "green") {
        return `<a class="${badge}" href="${slotUrl}" target="_blank" rel="noreferrer">${label}</a>`;
      }

      return `<span class="${badge}">${label}</span>`;
    })
    .join("");
}

function renderStatusCell(cell) {
  const slotLinks = renderTimeslotBadges(cell);

  return `
    <td class="cell">
      <div class="slot-links">${slotLinks || ""}</div>
    </td>
  `;
}

function buildMatrixTable(matrix, tents) {
  const headerCells = matrix.dates.map((date) => `<th>${date}</th>`).join("");

  const rows = tents
    .map((tent) => {
      const statusCells = matrix.dates
        .map((date) => {
          const cell = tent.matrix[date] || {
            status: "unavail",
            slotStatus: { mittag: "unavail", abend: "unavail" },
            slotLinks: {},
          };
          return renderStatusCell(cell);
        })
        .join("");

      return `<tr><th><a href="${tent.reservationUrl}" target="_blank" rel="noreferrer">${tent.name}</a></th>${statusCells}</tr>`;
    })
    .join("");

  return `
    <h2 class="h4 mt-2 mb-3">${t("matrixTitle")}</h2>
    <div class="table-responsive mb-4">
    <table class="table table-bordered table-striped align-middle matrix-table">
      <thead class="table-light">
        <tr>
          <th>${t("matrixTent")}</th>
          ${headerCells}
        </tr>
      </thead>
      <tbody>
        ${rows}
      </tbody>
    </table>
    </div>
  `;
}

function buildVenueSummary(tents) {
  const rows = tents
    .map((tent) => {
      const guestGroups = (tent.ticketTypes?.guestGroups || []).join(", ");
      const tableSizes = (tent.ticketTypes?.tableSizes || []).join(", ");
      const timeslots = (tent.ticketTypes?.timeslots || []).join(", ");
      const sales = tent.sales?.open ? t("open") : t("closed");
      const salesNote = tent.sales?.note || "";

      return `
        <tr>
          <th><a href="${tent.reservationUrl}" target="_blank" rel="noreferrer">${tent.name}</a></th>
          <td>${guestGroups}</td>
          <td>${tableSizes}</td>
          <td>${timeslots}</td>
          <td>${sales}</td>
          <td>${salesNote}</td>
        </tr>
      `;
    })
    .join("");

  return `
    <h2 class="h4 mt-2 mb-3">${t("venueTitle")}</h2>
    <div class="table-responsive">
    <table class="table table-bordered table-hover align-middle">
      <thead class="table-light">
        <tr>
          <th>${t("venue")}</th>
          <th>${t("guestGroups")}</th>
          <th>${t("tableSizes")}</th>
          <th>${t("timeslots")}</th>
          <th>${t("ticketSales")}</th>
          <th>${t("salesNote")}</th>
        </tr>
      </thead>
      <tbody>
        ${rows}
      </tbody>
    </table>
    </div>
  `;
}

function renderVenueOptions(matrix) {
  const selected = venueSelect.value || "all";
  const options = [`<option value="all" data-i18n-option="allTents">${t("allTents")}</option>`]
    .concat(
      matrix.tents.map((tent) => `<option value="${tent.slug}">${tent.name}</option>`)
    )
    .join("");

  venueSelect.innerHTML = options;
  venueSelect.value = matrix.tents.some((tent) => tent.slug === selected) ? selected : "all";
}

function filteredTents(matrix) {
  const selected = venueSelect.value || "all";
  if (selected === "all") {
    return matrix.tents;
  }
  return matrix.tents.filter((tent) => tent.slug === selected);
}

function renderMatrix() {
  if (!latestMatrix) return;

  renderVenueOptions(latestMatrix);
  const tents = filteredTents(latestMatrix);
  tableWrap.innerHTML = `${buildMatrixTable(latestMatrix, tents)}${buildVenueSummary(tents)}`;
  output.textContent = `${t("loaded")}: ${tents.length}/${latestMatrix.tents.length} tents, timeslot=${latestMatrix.timeslot}`;
}

async function loadMatrix() {
  const timeslot = timeslotSelect.value;
  output.textContent = t("loading");

  try {
    const response = await fetch(`/api/matrix/?timeslot=${encodeURIComponent(timeslot)}`);

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }

    latestMatrix = await response.json();
    renderMatrix();
  } catch (error) {
    output.textContent = `${t("requestFailed")}: ${error.message}`;
    tableWrap.textContent = t("matrixUnavailable");
  }
}

refreshButton.addEventListener("click", loadMatrix);
timeslotSelect.addEventListener("change", loadMatrix);
venueSelect.addEventListener("change", renderMatrix);
langEnButton.addEventListener("click", () => applyLanguage("en"));
langDeButton.addEventListener("click", () => applyLanguage("de"));

const preferredLang = localStorage.getItem("oktoberfest-lang");
applyLanguage(preferredLang === "de" ? "de" : "en");
loadMatrix();
