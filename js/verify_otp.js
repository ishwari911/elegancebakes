document.addEventListener('DOMContentLoaded', function () {
    // NOTE: This file is NOT currently loaded by verify.html (logic is inline there).
    // Kept here as a reference / backup. If you want to use this file instead,
    // remove the inline <script> block from verify.html and add:
    // <script src="js/verify_otp.js"></script>

    const form = document.getElementById('verifyForm');
    const otpInput = document.getElementById('otp');
    const errorMsg = document.getElementById('errorMsg');
    const loadingOverlay = document.getElementById('loadingOverlay');
    const resendBtn = document.getElementById('resendBtn');
    const resendTimer = document.getElementById('resendTimer');
    let cooldownInterval = null;

    // Auto-format: digits only, max 6
    otpInput.addEventListener('input', function () {
        this.value = this.value.replace(/[^0-9]/g, '').slice(0, 6);
        errorMsg.classList.add('hidden');
    });

    // Auto-submit on 6 digits
    otpInput.addEventListener('keyup', function (e) {
        if (this.value.length === 6 && e.key !== 'Backspace') {
            form.dispatchEvent(new Event('submit'));
        }
    });

    // Verify OTP submit
    form.addEventListener('submit', function (e) {
        e.preventDefault();

        const otp = otpInput.value.trim();
        if (!/^\d{6}$/.test(otp)) {
            showError('Please enter a valid 6-digit OTP');
            return;
        }

        loadingOverlay.style.display = 'flex';
        form.querySelector('button[type=submit]').disabled = true;

        const pendingEmail = localStorage.getItem('pendingVerifyEmail') || '';

        fetch('api/verify_otp.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ otp, email: pendingEmail })
        })
            .then(res => res.json())
            .then(data => {
                loadingOverlay.style.display = 'none';
                form.querySelector('button[type=submit]').disabled = false;

                if (data.status === 'success') {
                    localStorage.removeItem('pendingVerifyEmail');
                    if (data.user_name) localStorage.setItem('userName', data.user_name);
                    Swal.fire({
                        icon: 'success', title: '🎉 Account Verified!',
                        text: 'Welcome to Elegance Bakes!', timer: 1800, showConfirmButton: false
                    }).then(() => { window.location.href = 'index.html'; });
                } else {
                    showError(data.message);
                    if (data.message && data.message.toLowerCase().includes('expired')) {
                        resendTimer.textContent = '👆 Click Resend OTP above to get a new code';
                        resendTimer.style.display = 'inline';
                    }
                    otpInput.focus();
                    otpInput.select();
                }
            })
            .catch(err => {
                loadingOverlay.style.display = 'none';
                form.querySelector('button[type=submit]').disabled = false;
                showError('Network error. Please try again.');
                console.error(err);
            });
    });

    // Resend OTP with real API call + cooldown timer
    resendBtn.addEventListener('click', function () {
        const pendingEmail = localStorage.getItem('pendingVerifyEmail') || '';
        if (!pendingEmail) {
            showError('Session lost. Please go back and register again.');
            return;
        }

        resendBtn.disabled = true;
        resendBtn.textContent = '⏳ Sending...';

        fetch('api/resend_otp.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email: pendingEmail })
        })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    Swal.fire({
                        icon: 'success', title: 'OTP Sent!',
                        text: data.message, timer: 2500, showConfirmButton: false
                    });
                    startCooldown(180); // 3-minute cooldown
                } else {
                    const wait = data.wait_sec || 0;
                    if (wait > 0) {
                        startCooldown(wait);
                    } else {
                        resendBtn.disabled = false;
                        resendBtn.textContent = '🔄 Resend OTP';
                    }
                    showError(data.message);
                }
            })
            .catch(() => {
                resendBtn.disabled = false;
                resendBtn.textContent = '🔄 Resend OTP';
                showError('Network error. Could not resend OTP.');
            });
    });

    function startCooldown(seconds) {
        clearInterval(cooldownInterval);
        let remaining = seconds;
        resendBtn.disabled = true;
        updateCooldownUI(remaining);
        cooldownInterval = setInterval(() => {
            remaining--;
            if (remaining <= 0) {
                clearInterval(cooldownInterval);
                resendBtn.textContent = '🔄 Resend OTP';
                resendBtn.disabled = false;
                resendTimer.style.display = 'none';
            } else {
                updateCooldownUI(remaining);
            }
        }, 1000);
    }

    function updateCooldownUI(remaining) {
        const m = String(Math.floor(remaining / 60)).padStart(2, '0');
        const s = String(remaining % 60).padStart(2, '0');
        resendBtn.textContent = '🔄 Resend OTP';
        resendTimer.textContent = `(wait ${m}:${s})`;
        resendTimer.style.display = 'inline';
    }

    function showError(message) {
        errorMsg.textContent = message;
        errorMsg.classList.remove('hidden');
    }
});
