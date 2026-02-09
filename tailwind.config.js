/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./resources/views/**/*.blade.php",
    "./resources/js/**/*.{js,vue}",
    "./public/**/*.html",
  ],
  theme: {
    extend: {
      colors: {
        "earth-green": "#10b981",
        "dark-green": "#059669",
        "ocean-blue": "#3b82f6",
        "digital-gold": "#fbbf24",
        "gentle-black": "#0f172a",
        "pure-white": "#ffffff",
        "light-gray": "#f8fafc",
      },
      fontFamily: {
        vazirmatn: ["Vazirmatn", "sans-serif"],
      },
    },
  },
  plugins: [],
  safelist: [
    // Add any dynamic classes here that Tailwind might not detect
  ],
  corePlugins: {
    preflight: true,
  },
};
