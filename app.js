const output = document.getElementById("output");
const refreshButton = document.getElementById("refreshButton");
const timeslotSelect = document.getElementById("timeslot");
const tableWrap = document.getElementById("tableWrap");
const venueFilterInput = document.getElementById("venueFilter");

let latestMatrix = null;

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
      const label = `${slot}`;

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
    <h2 class="h4 mt-2 mb-3">Reservation Matrix</h2>
    <div class="table-responsive mb-4">
    <table class="table table-bordered table-striped align-middle matrix-table">
      <thead class="table-light">
        <tr>
          <th>Tent</th>
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
      const sales = tent.sales?.open ? "Open" : "Closed";
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
    <h2 class="h4 mt-2 mb-3">Ticket Types Per Venue</h2>
    <div class="table-responsive">
    <table class="table table-bordered table-hover align-middle">
      <thead class="table-light">
        <tr>
          <th>Venue</th>
          <th>Guest Groups</th>
          <th>Table Sizes</th>
          <th>Timeslots</th>
          <th>Ticket Sales</th>
          <th>Sales Note</th>
        </tr>
      </thead>
      <tbody>
        ${rows}
      </tbody>
    </table>
    </div>
  `;
}

function filteredTents(matrix) {
  const filter = (venueFilterInput.value || "").trim().toLowerCase();
  if (!filter) return matrix.tents;
  return matrix.tents.filter((tent) => tent.name.toLowerCase().includes(filter));
}

function renderMatrix() {
  if (!latestMatrix) return;

  const tents = filteredTents(latestMatrix);
  tableWrap.innerHTML = `${buildMatrixTable(latestMatrix, tents)}${buildVenueSummary(tents)}`;
  output.textContent = `Loaded: ${tents.length}/${latestMatrix.tents.length} tents, timeslot=${latestMatrix.timeslot}`;
}

async function loadMatrix() {
  const timeslot = timeslotSelect.value;
  output.textContent = "Loading matrix...";

  try {
    const response = await fetch(`/api/matrix/?timeslot=${encodeURIComponent(timeslot)}`);

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }

    latestMatrix = await response.json();
    renderMatrix();
  } catch (error) {
    output.textContent = `Request failed: ${error.message}`;
    tableWrap.textContent = "Could not load matrix.";
  }
}

refreshButton.addEventListener("click", loadMatrix);
timeslotSelect.addEventListener("change", loadMatrix);
venueFilterInput.addEventListener("input", renderMatrix);

loadMatrix();
