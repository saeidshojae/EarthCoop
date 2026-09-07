// EarthCoop theme runtime + critical header navigation fallback.
(function () {
  "use strict";

  const THEME_KEY = "theme";
  const savedTheme = localStorage.getItem(THEME_KEY) || "light";

  function dispatchThemeChanged(theme) {
    window.dispatchEvent(new CustomEvent("themeChanged", {
      detail: { theme, isDark: theme === "dark" },
    }));
  }

  function updateIconVisibility(isDark) {
    document.querySelectorAll(".theme-toggle-icon.sun").forEach((icon) => {
      icon.style.opacity = isDark ? "0.3" : "1";
    });
    document.querySelectorAll(".theme-toggle-icon.moon").forEach((icon) => {
      icon.style.opacity = isDark ? "1" : "0.3";
    });
  }

  function updateToggleButtons(isDark) {
    document.querySelectorAll(".theme-toggle").forEach((button) => {
      button.classList.toggle("dark", isDark);
    });
    updateIconVisibility(isDark);
  }

  function applyTheme(theme, withFeedback) {
    const isDark = theme === "dark";
    document.documentElement.classList.toggle("dark-mode", isDark);
    if (document.body) {
      document.body.classList.toggle("dark-mode", isDark);
    }
    localStorage.setItem(THEME_KEY, theme);
    updateToggleButtons(isDark);

    if (withFeedback) {
      document.querySelectorAll(".theme-toggle-slider").forEach((slider) => {
        slider.style.animation = "toggle-spin 0.6s ease-in-out";
        window.setTimeout(() => { slider.style.animation = ""; }, 600);
      });
    }

    dispatchThemeChanged(theme);
  }

  if (savedTheme === "dark") {
    document.documentElement.classList.add("dark-mode");
  }

  window.toggleTheme = function () {
    const current = document.documentElement.classList.contains("dark-mode") ? "dark" : "light";
    applyTheme(current === "dark" ? "light" : "dark", true);
  };

  window.getCurrentTheme = function () {
    return localStorage.getItem(THEME_KEY) || "light";
  };

  window.setTheme = function (theme) {
    if (theme !== "light" && theme !== "dark") {
      console.error('Invalid theme. Use "light" or "dark"');
      return;
    }
    applyTheme(theme, false);
  };

  // Critical navigation must work even when the Vite dev server is unavailable.
  // Use the browser's native history when the current document was reached from
  // another EarthCoop page; otherwise leave the anchor href as the safe fallback.
  function hasInternalReferrer() {
    if (!document.referrer) return false;
    try {
      return new URL(document.referrer, window.location.href).origin === window.location.origin;
    } catch (_) {
      return false;
    }
  }

  window.earthcoopNavigateBack = function (event) {
    if (!hasInternalReferrer()) return true;
    event?.preventDefault?.();
    window.history.back();
    return false;
  };

  function bindHeaderBackControls() {
    document.querySelectorAll('[data-earthcoop-history-back="true"]').forEach((control) => {
      if (control.dataset.earthcoopBackBound === "true") return;
      control.dataset.earthcoopBackBound = "true";
      control.addEventListener("click", window.earthcoopNavigateBack);
    });
  }

  function installHeaderActionRailNudge() {
    if (document.getElementById("earthcoop-header-action-rail-nudge")) return;
    const style = document.createElement("style");
    style.id = "earthcoop-header-action-rail-nudge";
    style.textContent = `
      @media (max-width: 1023px) {
        html[dir="rtl"] header.site-header-unified[data-auth-state="authenticated"] .site-header-mobile-menu-slot {
          transform: translateX(-8px) !important;
        }
        html[dir="ltr"] header.site-header-unified[data-auth-state="authenticated"] .site-header-mobile-menu-slot {
          transform: translateX(8px) !important;
        }
      }
    `;
    document.head.appendChild(style);
  }

  function initialize() {
    if (savedTheme === "dark") {
      document.documentElement.classList.add("dark-mode");
      document.body?.classList.add("dark-mode");
    } else {
      document.documentElement.classList.remove("dark-mode");
      document.body?.classList.remove("dark-mode");
    }

    updateToggleButtons(savedTheme === "dark");

    document.querySelectorAll('.theme-toggle, [onclick*="toggleTheme"]').forEach((button) => {
      if (button.dataset.earthcoopThemeBound === "true") return;
      button.dataset.earthcoopThemeBound = "true";
      if (button.getAttribute("onclick")) button.removeAttribute("onclick");
      button.addEventListener("click", (event) => {
        event.preventDefault();
        window.toggleTheme();
      });
    });

    installHeaderActionRailNudge();
    bindHeaderBackControls();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initialize, { once: true });
  } else {
    initialize();
  }
})();
