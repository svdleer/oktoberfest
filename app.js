const output = document.getElementById("output");
const refreshButton = document.getElementById("refreshButton");
const timeslotSelect = document.getElementById("timeslot");
const tableWrap = document.getElementById("tableWrap");

function buildTable(matrix) {
  const headerCells = matrix.dates.map((date) => `<th>${date}</th>`).join("");

  const rows = matrix.tents
    .map((tent) => {
      const statusCells = matrix.dates
        .map((date) => {
          const status = tent.status[date] || "unavail";
          return `<td class="cell status-${status}">${status}</td>`;
        })
        .join("");

      return `<tr><th>${tent.name}</th>${statusCells}</tr>`;
    })
    .join("");

  return `
    <table>
      <thead>
        <tr>
          <th>Tent</th>
          ${headerCells}
        </tr>
      </thead>
      <tbody>
        ${rows}
      </tbody>
    </table>
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
    tableWrap.innerHTML = buildTable(data);
    output.textContent = `Loaded: ${data.tents.length} tents, timeslot=${data.timeslot}`;
  } catch (error) {
    output.textContent = `Request failed: ${error.message}`;
    tableWrap.textContent = "Could not load matrix.";
  }
}

refreshButton.addEventListener("click", loadMatrix);
timeslotSelect.addEventListener("change", loadMatrix);

loadMatrix();
