const output = document.getElementById("output");
const refreshButton = document.getElementById("refreshButton");
const timeslotSelect = document.getElementById("timeslot");
const tableWrap = document.getElementById("tableWrap");

function statusBadgeClass(status) {
  if (status === "green") return "text-bg-success";
  if (status === "red") return "text-bg-danger";
  return "text-bg-secondary";
}

function buildTable(matrix) {
  const headerCells = matrix.dates.map((date) => `<th>${date}</th>`).join("");

  const rows = matrix.tents
    .map((tent) => {
      const statusCells = matrix.dates
        .map((date) => {
          const cell = tent.matrix[date] || { status: "unavail", slots: [] };
          const status = cell.status || "unavail";
          const slotLinks = (cell.slots || [])
            .map(
              (slot) =>
                `<a class="slot-link badge text-bg-light border" href="${slot.url}" target="_blank" rel="noreferrer">${slot.name}</a>`
            )
            .join("");

          return `
            <td class="cell">
              <div><span class="badge ${statusBadgeClass(status)} status-label">${status}</span></div>
              <div class="slot-links">${slotLinks || ""}</div>
            </td>
          `;
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

function buildVenueSummary(matrix) {
  const rows = matrix.tents
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

async function loadMatrix() {
  const timeslot = timeslotSelect.value;
  output.textContent = "Loading matrix...";

  try {
    const response = await fetch(`/api/matrix/?timeslot=${encodeURIComponent(timeslot)}`);

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }

    const data = await response.json();
    tableWrap.innerHTML = `${buildTable(data)}${buildVenueSummary(data)}`;
    output.textContent = `Loaded: ${data.tents.length} tents, timeslot=${data.timeslot}`;
  } catch (error) {
    output.textContent = `Request failed: ${error.message}`;
    tableWrap.textContent = "Could not load matrix.";
  }
}

refreshButton.addEventListener("click", loadMatrix);
timeslotSelect.addEventListener("change", loadMatrix);

loadMatrix();
