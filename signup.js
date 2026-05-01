document.addEventListener("DOMContentLoaded", function() {
    const form = document.getElementById("reg-form");
    const errorMsg = document.getElementById("errorMsg");
    const loadingOverlay = document.getElementById("loadingOverlay");

    form.addEventListener("submit", function(e) {
        e.preventDefault();

        // Clear previous errors
        errorMsg.classList.add("hidden");
        errorMsg.textContent = "";

        // Get form data
        let formData = {
            name: document.getElementById("name").value.trim(),
            email: document.getElementById("email").value.trim(),
            phone: document.getElementById("phone").value.trim(),
            pincode: document.getElementById("pincode").value.trim(),
            address: document.getElementById("address").value.trim(),
            password: document.getElementById("password").value.trim()
        };

        // Client-side validation (same as PHP)
        if (!/^[0-9]{10}$/.test(formData.phone)) {
            errorMsg.textContent = "Phone number must be exactly 10 digits.";
            errorMsg.classList.remove("hidden");
            return;
        }

        let pin = parseInt(formData.pincode);
        if (pin < 421201 || pin > 421405) {
            errorMsg.textContent = "Delivery available only between 421201 - 421405.";
            errorMsg.classList.remove("hidden");
            return;
        }

        if (formData.address.length < 10) {
            errorMsg.textContent = "Please enter complete delivery address.";
            errorMsg.classList.remove("hidden");
            return;
        }

        if (formData.password.length < 8) {
            errorMsg.textContent = "Password must be at least 8 characters.";
            errorMsg.classList.remove("hidden");
            return;
        }

        // Show loading
        loadingOverlay.style.display = "flex";

        // Send to PHP API
        fetch("register.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(formData)
        })
        .then(res => res.json())
        .then(data => {
            loadingOverlay.style.display = "none";
            
            if (data.status === "success") {
                Swal.fire({
                    icon: 'success',
                    title: 'Account Created!',
                    text: data.message,
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    window.location.href = "verify_otp.php";
                });
            } else {
                errorMsg.textContent = data.message;
                errorMsg.classList.remove("hidden");
            }
        })
        .catch(err => {
            loadingOverlay.style.display = "none";
            console.error(err);
            errorMsg.textContent = "Server error. Try again later.";
            errorMsg.classList.remove("hidden");
        });
    });
});
