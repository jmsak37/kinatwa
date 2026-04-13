let enterCount = 0;
let lastPressTime = 0;

document.addEventListener("keydown", function(event) {
    if (event.key === "Enter") {
        const now = Date.now();

        if (now - lastPressTime > 2000) {
            enterCount = 0;
        }

        enterCount++;
        lastPressTime = now;

        if (enterCount >= 10) {
            window.location.href = "/kinatwa/login.html";
        }
    }
});