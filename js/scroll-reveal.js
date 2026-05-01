/**
 * scroll-reveal.js — Elegant scroll-triggered reveal animations for Elegance Bakes
 * Usage: add class "reveal-up", "reveal-left", "reveal-right", or "reveal-fade"
 * to any element you want to animate in on scroll.
 */
(function () {
    const CSS = `
    .reveal-up,
    .reveal-left,
    .reveal-right,
    .reveal-fade {
      opacity: 0;
      transition: opacity 0.7s ease, transform 0.7s cubic-bezier(0.16, 1, 0.3, 1);
      will-change: opacity, transform;
    }

    .reveal-up    { transform: translateY(36px); }
    .reveal-left  { transform: translateX(-40px); }
    .reveal-right { transform: translateX(40px); }
    .reveal-fade  { transform: scale(0.96); }

    .reveal-up.is-visible,
    .reveal-left.is-visible,
    .reveal-right.is-visible,
    .reveal-fade.is-visible {
      opacity: 1;
      transform: none;
    }
  `;

    if (!document.getElementById('eb-reveal-styles')) {
        const s = document.createElement('style');
        s.id = 'eb-reveal-styles';
        s.textContent = CSS;
        document.head.appendChild(s);
    }

    function init() {
        const els = document.querySelectorAll('.reveal-up, .reveal-left, .reveal-right, .reveal-fade');

        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry, i) => {
                if (entry.isIntersecting) {
                    // Stagger children that share a parent
                    const delay = entry.target.dataset.delay || 0;
                    setTimeout(() => {
                        entry.target.classList.add('is-visible');
                    }, Number(delay));
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });

        els.forEach(el => observer.observe(el));

        // Auto-stagger siblings inside grid/flex parents
        document.querySelectorAll('[data-stagger]').forEach(parent => {
            const children = parent.children;
            Array.from(children).forEach((child, i) => {
                child.dataset.delay = i * 80;
                child.classList.add('reveal-up');
                observer.observe(child);
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
