const output = document.getElementById("output");
const button = document.getElementById("healthButton");

button.addEventListener("click", async () => {
  output.textContent = "Loading...";

  try {
    const response = await fetch("http://localhost:8000/api/health");
    const data = await response.json();
    output.textContent = JSON.stringify(data, null, 2);
  } catch (error) {
    output.textContent = `Request failed: ${error.message}`;
  }
});
