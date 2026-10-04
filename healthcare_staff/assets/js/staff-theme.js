document.addEventListener("DOMContentLoaded", function () {

    const toggle = document.getElementById("darkModeToggle");

    const html = document.documentElement;
    const body = document.body;

    /* ==========================================
       APPLY THEME
    ========================================== */

    function applyTheme(theme) {

        const isDark = theme === "dark";

        /* Keep BOTH html and body synchronized */
        html.classList.toggle("dark-mode", isDark);
        body.classList.toggle("dark-mode", isDark);

        /* Also keep data-theme synchronized */
        html.setAttribute("data-theme", isDark ? "dark" : "light");
        body.setAttribute("data-theme", isDark ? "dark" : "light");

        /* ==========================================
           UPDATE BUTTON
        ========================================== */

        if (toggle) {

            const icon = toggle.querySelector("i");
            const text = toggle.querySelector("span");

            if (isDark) {

                if (icon) {
                    icon.className = "bi bi-sun-fill";
                }

                if (text) {
                    text.textContent = "Light Mode";
                }

            } else {

                if (icon) {
                    icon.className = "bi bi-moon-stars-fill";
                }

                if (text) {
                    text.textContent = "Dark Mode";
                }
            }
        }
    }


    /* ==========================================
       LOAD SAVED THEME
    ========================================== */

    let savedTheme = localStorage.getItem("theme");

    if (savedTheme !== "dark" && savedTheme !== "light") {
        savedTheme = "light";
        localStorage.setItem("theme", "light");
    }

    applyTheme(savedTheme);


    /* ==========================================
       DARK MODE BUTTON
    ========================================== */

    if (toggle) {

        toggle.addEventListener("click", function () {

            const isCurrentlyDark =
                html.classList.contains("dark-mode") ||
                body.classList.contains("dark-mode");

            const newTheme = isCurrentlyDark
                ? "light"
                : "dark";

            /* Save */
            localStorage.setItem("theme", newTheme);

            /* Apply everywhere */
            applyTheme(newTheme);

        });

    }

});