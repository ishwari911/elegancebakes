/**
 * loader.js — Page loading animation for Elegance Bakes
 * Professional minimal spinner overlay shown on every page navigation.
 */

(function () {
  const CSS = `
    #eb-loader {
      position: fixed;
      inset: 0;
      z-index: 9999998;
      background: #f5ecea;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 18px;
      opacity: 1;
      transition: opacity 0.35s ease;
      pointer-events: all;
    }

    #eb-loader.fade-out {
      opacity: 0;
      pointer-events: none;
    }

    .eb-loader-ring {
      width: 44px;
      height: 44px;
      border: 3px solid rgba(106, 60, 60, 0.15);
      border-top-color: #6a3c3c;
      border-radius: 50%;
      animation: eb-spin 0.75s linear infinite;
    }

    @keyframes eb-spin {
      to { transform: rotate(360deg); }
    }

    .eb-loader-text {
      font-family: 'Playfair Display', Georgia, serif;
      font-size: 13px;
      font-weight: 600;
      color: #b09080;
      letter-spacing: 2px;
      text-transform: uppercase;
    }
  `;

  function injectStyles() {
    if (document.getElementById('eb-loader-styles')) return;
    const s = document.createElement('style');
    s.id = 'eb-loader-styles';
    s.textContent = CSS;
    document.head.appendChild(s);
  }

  function createLoader() {
    injectStyles();
    const el = document.createElement('div');
    el.id = 'eb-loader';
    el.innerHTML = `
      <div class="eb-loader-ring"></div>
      <span class="eb-loader-text">Elegance Bakes</span>
    `;
    return el;
  }

  // Show on start
  let loader = createLoader();
  document.documentElement.appendChild(loader);

  // Hide once DOM fully loaded
  function hideLoader() {
    loader.classList.add('fade-out');
    setTimeout(() => loader.remove(), 450);
  }

  if (document.readyState === 'complete') {
    hideLoader();
  } else {
    window.addEventListener('load', hideLoader);
  }

  // Show on page navigation (anchor clicks)
  document.addEventListener('click', function (e) {
    const a = e.target.closest('a[href]');
    if (!a) return;
    const href = a.getAttribute('href');
    if (!href || href.startsWith('#') || href.startsWith('javascript') ||
      href.startsWith('http') || href.startsWith('mailto') ||
      a.target === '_blank') return;

    // Show loader for same-origin navigation
    loader = createLoader();
    document.documentElement.appendChild(loader);
  });
})();
