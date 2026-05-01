document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('loginForm');
    const errorMsg = document.getElementById('errorMsg');
    const loadingOverlay = document.getElementById('loadingOverlay');
    const rememberCheckbox = form.querySelector('input[type="checkbox"]');

    // Remember me functionality
    if (localStorage.getItem('remember_email')) {
        document.getElementById('email').value = localStorage.getItem('remember_email');
        rememberCheckbox.checked = true;
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        const email = document.getElementById('email').value.trim();
        const password = document.getElementById('password').value;

        if (!email || !password) {
            showError('Please enter email and password');
            return;
        }

        if (!email.includes('@')) {
            showError('Please enter valid email');
            return;
        }

        // Show loading
        loadingOverlay.style.display = 'flex';
        form.querySelector('button').disabled = true;

        // Send to API
        fetch('api/login.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email, password })
        })
            .then(res => res.json())
            .then(data => {
                loadingOverlay.style.display = 'none';
                form.querySelector('button').disabled = false;

                if (data.status === 'success') {
                    // Store name so every page header can detect login
                    localStorage.setItem('userName', data.user_name || email.split('@')[0]);

                    // Remember me
                    if (rememberCheckbox.checked) {
                        localStorage.setItem('remember_email', email);
                    } else {
                        localStorage.removeItem('remember_email');
                    }

                    Swal.fire({
                        icon: 'success',
                        title: 'Welcome Back!',
                        text: 'Redirecting...',
                        timer: 1200,
                        showConfirmButton: false
                    }).then(() => {
                        window.location.href = 'index.html';
                    });
                } else {
                    showError(data.message);
                    document.getElementById('password').focus();
                }
            })
            .catch(err => {
                loadingOverlay.style.display = 'none';
                form.querySelector('button').disabled = false;
                showError('Network error. Please try again.');
                console.error(err);
            });
    });

    function showError(message) {
        errorMsg.textContent = message;
        errorMsg.classList.remove('hidden');
        errorMsg.scrollIntoView({ behavior: 'smooth' });
    }
});
