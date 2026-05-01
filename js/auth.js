/**
 * auth.js — Shared header auth state for Elegance Bakes
 * Include this on every page. It checks the PHP session via
 * user-profile.php and updates the header + localStorage.
 */
(function () {
    'use strict';

    // ── Detect which "root" prefix to use based on current path ──
    // Pages in root use 'user-profile.php'; pages in subdirs use '../user-profile.php'
    const isSubDir = window.location.pathname.includes('/api/') || window.location.pathname.includes('/js/');
    const BASE = isSubDir ? '../' : '';

    // ── Elements present on every page header (may be null on some pages) ──
    function getEl(id) { return document.getElementById(id); }

    function showLoggedIn(name) {
        localStorage.setItem('userName', name);

        const loginIcon = getEl('loginIcon');
        const userMenu = getEl('userMenu');
        const userNameHeader = getEl('userNameHeader');

        if (loginIcon) loginIcon.style.display = 'none';
        if (userMenu) userMenu.style.display = 'flex';
        if (userNameHeader) userNameHeader.textContent = '\uD83D\uDC64 ' + name;
    }

    function showLoggedOut() {
        localStorage.removeItem('userName');

        const loginIcon = getEl('loginIcon');
        const userMenu = getEl('userMenu');

        if (loginIcon) loginIcon.style.display = '';   // restore default
        if (userMenu) userMenu.style.display = 'none';
    }

    // ── Fast path: paint header instantly from localStorage ──
    const cached = localStorage.getItem('userName');
    if (cached) showLoggedIn(cached);

    // ── Slow path: verify with PHP session (runs after DOM ready) ──
    document.addEventListener('DOMContentLoaded', function () {
        fetch(BASE + 'user-profile.php', { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    showLoggedIn(data.name);
                    // Store user_id as InfinityFree session fallback
                    if (data.user_id) localStorage.setItem('userId', data.user_id);
                } else {
                    // PHP session gone — clear stale localStorage
                    showLoggedOut();
                    localStorage.removeItem('userId');
                }
            })
            .catch(function () {
                // Network error — keep whatever localStorage says
                if (cached) showLoggedIn(cached);
            });
    });

    // ── Global helpers pages can call ──
    window.logoutHeader = function () {
        localStorage.removeItem('userName');
        fetch(BASE + 'logout.php', { method: 'POST', credentials: 'same-origin' })
            .then(function () { window.location.href = BASE + 'index.html'; });
    };

    window.goToDashboard = function () {
        window.location.href = BASE + 'dashboard.html';
    };

    window.openAuth = window.openAuth || function () {
        var m = getEl('authModal');
        if (m) { m.classList.add('active'); document.body.style.overflow = 'hidden'; }
    };

    window.closeAuth = window.closeAuth || function () {
        var m = getEl('authModal');
        if (m) { m.classList.remove('active'); document.body.style.overflow = 'auto'; }
    };
})();
