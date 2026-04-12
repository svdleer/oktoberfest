const output = document.getElementById("output");
const button = document.getElementById("healthButton");

const isLocalFrontend =
  window.location.hostname === "localhost" ||
  window.location.hostname === "127.0.0.1";

// Local frontend (python server) calls PHP dev server; deployed frontend calls same-origin PHP endpoint.
const healthUrl = isLocalFrontend
  ? "http://localhost:8000/api/health"
  : "/api/health/";

button.addEventListener("click", async () => {
  output.textContent = "Loading...";

  try {
    const response = await fetch(healthUrl);

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }

    const data = await response.json();
    output.textContent = JSON.stringify(data, null, 2);
  } catch (error) {
    output.textContent = `Request failed: ${error.message}`;
  }
});
