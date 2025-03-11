// app.js
import './bootstrap';  
import { createApp } from 'vue';  

const app = createApp({});  

import ExampleComponent from './components/ExampleComponent.vue';  
app.component('example-component', ExampleComponent);  

app.mount('#app');  

document.addEventListener("DOMContentLoaded", function () {  
    // مدیریت دکمه‌های آکاردئون بوت‌استرپ  
    const accordionButtons = document.querySelectorAll('.accordion-button');  
    accordionButtons.forEach(button => {  
        button.addEventListener('click', () => {  
            button.classList.toggle('collapsed');  
            const target = document.querySelector(button.getAttribute('data-bs-target'));
            if (target) {
                target.classList.toggle('show');
            }
        });  
    });  
});
