// assets/js/main.js
document.addEventListener('DOMContentLoaded', () => {
    // Auto-scroll smooth saat klik tombol tertentu
    const scrollButtons = document.querySelectorAll('[data-scroll]');
    scrollButtons.forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const target = document.querySelector(btn.dataset.scroll);
            if (target) {
                target.scrollIntoView({ behavior: 'smooth' });
            }
        });
    });
});
