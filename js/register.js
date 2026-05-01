document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('registerForm');
    const errorMsg = document.getElementById('errorMsg');
    const loadingOverlay = document.getElementById('loadingOverlay');
    const pincodeInput = document.getElementById('pincode');

    // Pincode validation (Nashik area only)
    pincodeInput.addEventListener('input', function () {
        let pin = this.value.replace(/[^0-9]/g, '').slice(0, 6);
        this.value = pin;

        if (pin.length === 6) {
            let pinNum = parseInt(pin);
            if (pinNum < 421201 || pinNum > 421405) {
                errorMsg.textContent = 'Delivery available only in Nashik (421201-421405)';
                errorMsg.classList.remove('hidden');
            } else {
                errorMsg.classList.add('hidden');
            }
        }
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        // Get form data
        const formData = {
            name: document.getElementById('name').value.trim(),
            email: document.getElementById('email').value.trim(),
            phone: document.getElementById('phone').value.trim(),
            pincode: document.getElementById('pincode').value.trim(),
            address: document.getElementById('address').value.trim(),
            password: document.getElementById('password').value.trim()
        };

        // Client-side validation
        if (!formData.name || !formData.email || !formData.phone || !formData.pincode || !formData.address || !formData.password) {
            showError('Please fill all fields');
            return;
        }

        if (!/^[0-9]{10}$/.test(formData.phone)) {
            showError('Phone must be 10 digits');
            return;
        }

        let pinNum = parseInt(formData.pincode);
        if (pinNum < 421201 || pinNum > 421405) {
            showError('Delivery only available in Nashik (421201-421405)');
            return;
        }

        if (formData.address.length < 10) {
            showError('Please enter complete address');
            return;
        }

        if (formData.password.length < 8) {
            showError('Password must be 8+ characters');
            return;
        }

        // Show loading
        loadingOverlay.style.display = 'flex';
        form.querySelector('button').disabled = true;

        // Send to API
        fetch('api/register.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(formData)
        })
            .then(res => res.json())
            .then(data => {
                loadingOverlay.style.display = 'none';
                form.querySelector('button').disabled = false;

                if (data.status === 'success') {
                    // Save email so verify page can send it to PHP (sessions unreliable on shared hosting)
                    localStorage.setItem('pendingVerifyEmail', formData.email);
                    Swal.fire({
                        icon: 'success',
                        title: 'Account Created!',
                        text: data.message,
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        window.location.href = 'verify.html';
                    });
                } else {
                    showError(data.message);
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
        errorMsg.style.display = 'block';   // errorMsg uses display:none, not a hidden class
        errorMsg.scrollIntoView({ behavior: 'smooth' });
    }
});
