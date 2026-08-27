import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";

export default defineConfig({
  plugins: [
    laravel({
      input: [
        "resources/css/vite.css",
        "resources/js/app.js",
        "resources/js/group-chat-page.js",
      ],
      refresh: true,
    }),
  ],
  resolve: {
    alias: {
      "@": "/resources/js",
    },
  },
  optimizeDeps: {
    include: ["bootstrap", "axios", "jquery", "select2", "laravel-echo", "pusher-js"],
  },
  server: {
    host: "localhost",
    port: 5173,
  },
});
