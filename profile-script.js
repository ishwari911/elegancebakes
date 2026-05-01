function checkSession() {
    fetch('api/check_session.php', { credentials: 'include' })
        .then(response => response.json())
        .then(data => {
            if (data.loggedIn) {
                document.getElementById('loginSection').style.display = 'none';
                document.getElementById('profileInfo').style.display = 'block';
                document.getElementById('userName').textContent = data.name;
                document.getElementById('userAddress').textContent = data.address;
                document.getElementById('userPincode').textContent = data.pincode;
            }
        });
}

function login() {
    const email = document.getElementById('loginEmail').value;
    const pass = document.getElementById('loginPass').value;
    
    fetch('api/login.php', {
        method: 'POST', credentials: 'include',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `email=${email}&password=${pass}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) checkSession();
        else alert(data.message);
    });
}

function logout() {
    fetch('api/logout.php', { credentials: 'include' })
        .then(() => {
            document.getElementById('loginSection').style.display = 'block';
            document.getElementById('profileInfo').style.display = 'none';
        });
}

// Add smooth show/hide animation
document.addEventListener('DOMContentLoaded', function() {
    const userIcon = document.getElementById('userIcon');
    const dropdown = document.getElementById('profileDropdown');
    
    if (userIcon && dropdown) {
        userIcon.addEventListener('mouseenter', () => {
            dropdown.classList.add('show');
            dropdown.style.display = 'block';
            checkSession();
        });
        
        dropdown.addEventListener('mouseenter', () => {
            dropdown.classList.add('show');
        });
        
        dropdown.addEventListener('mouseleave', () => {
            dropdown.classList.remove('show');
            setTimeout(() => {
                if (!dropdown.matches(':hover')) {
                    dropdown.style.display = 'none';
                }
            }, 200);
        });
    }
    checkSession();
});

function sendOTP() {
    const email = document.getElementById('userEmail').value;
    fetch('api/send_otp.php', {
        method: 'POST',
        credentials: 'include',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `email=${email}`
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            document.getElementById('otpSection').style.display = 'block';
            document.getElementById('userEmail').style.display = 'none';
        } else {
            alert(data.message);
        }
    });
}

function verifyOTP() {
    const otp = document.getElementById('otpInput').value;
    fetch('api/verify_otp.php', {
        method: 'POST',
        credentials: 'include',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `otp=${otp}`
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            checkSession(); // Shows profile
        } else {
            alert(data.message);
        }
    });
}

function resendOTP() {
    const email = document.getElementById('userEmail').value;
    sendOTP(); // Reuse same function
}
