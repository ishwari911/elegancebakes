/**
 * toast.js — Global toast notification system for Elegance Bakes
 * Usage: showToast('Your message', 'success' | 'error' | 'warning' | 'info')
 */

(function () {
    // Inject styles once
    if (!document.getElementById('eb-toast-styles')) {
        const style = document.createElement('style');
        style.id = 'eb-toast-styles';
        style.textContent = `
      #eb-toast-container {
        position: fixed;
        bottom: 28px;
        right: 28px;
        z-index: 999999;
        display: flex;
        flex-direction: column;
        gap: 12px;
        pointer-events: none;
      }

      .eb-toast {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 14px 20px;
        border-radius: 14px;
        font-family: 'Playfair Display', Georgia, serif;
        font-size: 15px;
        font-weight: 600;
        color: white;
        min-width: 260px;
        max-width: 360px;
        box-shadow: 0 12px 36px rgba(0,0,0,0.18);
        opacity: 0;
        transform: translateX(60px);
        transition: opacity 0.35s ease, transform 0.35s ease;
        pointer-events: all;
        cursor: pointer;
      }

      .eb-toast.show {
        opacity: 1;
        transform: translateX(0);
      }

      .eb-toast.hide {
        opacity: 0;
        transform: translateX(60px);
      }

      .eb-toast.success { background: linear-gradient(135deg, #2e7d32, #43a047); }
      .eb-toast.error   { background: linear-gradient(135deg, #c62828, #e53935); }
      .eb-toast.warning { background: linear-gradient(135deg, #e65100, #fb8c00); }
      .eb-toast.info    { background: linear-gradient(135deg, #6a3c3c, #a0724a); }

      .eb-toast-icon { font-size: 1.3rem; flex-shrink: 0; }
      .eb-toast-text { flex: 1; line-height: 1.4; }

      .eb-progress {
        position: absolute;
        bottom: 0; left: 0;
        height: 3px;
        border-radius: 0 0 14px 14px;
        background: rgba(255,255,255,0.45);
        animation: eb-shrink 3s linear forwards;
      }

      @keyframes eb-shrink {
        from { width: 100%; }
        to   { width: 0%; }
      }
    `;
        document.head.appendChild(style);
    }

    // Create container
    function getContainer() {
        let c = document.getElementById('eb-toast-container');
        if (!c) {
            c = document.createElement('div');
            c.id = 'eb-toast-container';
            document.body.appendChild(c);
        }
        return c;
    }

    const ICONS = {
        success: '✅',
        error: '❌',
        warning: '⚠️',
        info: '🍰'
    };

    window.showToast = function (message, type = 'info', duration = 3500) {
        const container = getContainer();
        const toast = document.createElement('div');
        toast.className = `eb-toast ${type}`;
        toast.style.position = 'relative';
        toast.style.overflow = 'hidden';
        toast.innerHTML = `
      <span class="eb-toast-icon">${ICONS[type] || '🍰'}</span>
      <span class="eb-toast-text">${message}</span>
      <div class="eb-progress" style="animation-duration:${duration}ms"></div>
    `;

        // Click to dismiss
        toast.addEventListener('click', () => dismiss(toast));

        container.appendChild(toast);
        // Trigger animation
        requestAnimationFrame(() => requestAnimationFrame(() => toast.classList.add('show')));

        const timer = setTimeout(() => dismiss(toast), duration);
        toast._timer = timer;

        return toast;
    };

    function dismiss(toast) {
        clearTimeout(toast._timer);
        toast.classList.add('hide');
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 400);
    }
})();
