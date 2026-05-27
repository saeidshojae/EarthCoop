import "../css/app.css";
import "bootstrap/dist/css/bootstrap.min.css";
import "bootstrap";
import "./bootstrap";
import $ from "jquery";
import "./najm-bahar.js";
import { register } from "swiper/element/bundle";

register();

// Preserve CDN jQuery with Select2 when present.
if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.select2) {
	window.$ = $;
	window.jQuery = $;
}

console.log("Application initialized successfully");
