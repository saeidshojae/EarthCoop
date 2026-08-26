import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";

export default defineConfig({
  plugins: [
    laravel({
      input: ["resources/css/vite.css", "resources/js/app.js"],
      refresh: true,
    }),
  ],
  resolve: {
    alias: {
      "@": "/resources/js",
    },
  },
  server: {
    host: "localhost",
    port: 5173,
  },
});
