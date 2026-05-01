/**
 * mobile-nav.js — Hamburger menu toggle for Elegance Bakes
 * Works with any page that has .header-container > nav > ul
 */
(function () {
    document.addEventListener('DOMContentLoaded', function () {
        const nav = document.querySelector('header nav');
        if (!nav) return;

        // Create hamburger button
        const btn = document.createElement('button');
        btn.className = 'eb-hamburger';
        btn.setAttribute('aria-label', 'Toggle menu');
        btn.innerHTML = `<span></span><span></span><span></span>`;

        // Insert hamburger before nav (inside header-container)
        const headerContainer = document.querySelector('.header-container');
        if (headerContainer) headerContainer.appendChild(btn);

        // Create overlay
        const overlay = document.createElement('div');
        overlay.className = 'eb-nav-overlay';
        document.body.appendChild(overlay);

        function openMenu() {
            nav.classList.add('eb-mobile-open');
            btn.classList.add('open');
            overlay.classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function closeMenu() {
            nav.classList.remove('eb-mobile-open');
            btn.classList.remove('open');
            overlay.classList.remove('show');
            document.body.style.overflow = '';
        }

        btn.addEventListener('click', () => {
            nav.classList.contains('eb-mobile-open') ? closeMenu() : openMenu();
        });

        overlay.addEventListener('click', closeMenu);

        // Close on nav link click
        nav.querySelectorAll('a').forEach(a => a.addEventListener('click', closeMenu));
    });
})();
