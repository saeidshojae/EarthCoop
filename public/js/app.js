document.addEventListener('DOMContentLoaded', function () {
    const agreementCheckbox = document.getElementById('agreement');
    const registrationForm = document.getElementById('registrationForm');
    const agreementError = document.getElementById('agreementError');

    // registrationForm.addEventListener('submit', function (event) {
    //     if (!agreementCheckbox.checked) {
    //         event.preventDefault();
    //         agreementError.style.display = 'block';
    //     }
    // });

    // window.openTerms = function () {
    //     const termsWindow = window.open('/terms', '_blank');
    //     const interval = setInterval(function () {
    //         if (termsWindow.closed) {
    //             clearInterval(interval);
    //             if (localStorage.getItem('termsAccepted') === 'true') {
    //                 agreementCheckbox.checked = true;
    //             }
    //         }
    //     }, 1000);
    // };
});
