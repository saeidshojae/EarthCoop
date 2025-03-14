// filepath: c:\Users\saeed\EarthCoop\earthcoop\resources\js\app.js
import './bootstrap';
import { createApp } from 'vue';
import $ from 'jquery';

// تنظیم jQuery به صورت global
window.$ = $;
window.jQuery = $;

// ایجاد اپلیکیشن Vue
const app = createApp({
    mounted() {
        // بررسی بارگذاری jQuery
        console.log("jQuery version:", window.$ ? $.fn.jquery : "jQuery not loaded!");

        // در اینجا می‌توانید کدهای اختصاصی دیگر را قرار دهید.
        // مثال: مدیریت دکمه‌های آکاردئونی
        const accordionButtons = document.querySelectorAll('.accordion-button');
        accordionButtons.forEach(button => {
            button.addEventListener('click', () => {
                console.log('Accordion button clicked!');
            });
        });
    }
});

import ExampleComponent from './components/ExampleComponent.vue';
app.component('example-component', ExampleComponent);
app.mount('#app');