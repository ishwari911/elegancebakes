/**
 * header-auth.js — shared auth state for all pages
 * Shows login icon when logged out, user avatar when logged in.
 * Include this on every page that has the standard header.
 */
document.addEventListener('DOMContentLoaded', function () {
    const userName = localStorage.getItem('userName');
    const loginIcon = document.getElementById('loginIcon');
    const userMenu = document.getElementById('userMenu');
    const userInitial = document.getElementById('userInitial');

    if (userName) {
        if (loginIcon) loginIcon.style.display = 'none';
        if (userMenu) userMenu.style.display = 'flex';
        if (userInitial) userInitial.textContent = userName.charAt(0).toUpperCase();
    } else {
        if (loginIcon) loginIcon.style.display = 'flex';
        if (userMenu) userMenu.style.display = 'none';
    }

    window.logoutHeader = function () {
        localStorage.removeItem('userName');
        if (loginIcon) loginIcon.style.display = 'flex';
        if (userMenu) userMenu.style.display = 'none';
        if (typeof showToast === 'function') showToast('Logged out', 'info');
    };
});
