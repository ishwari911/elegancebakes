<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login</title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="tailwind.config.js"></script>
<link rel="stylesheet" href="css/custom.css">
<script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
<link href="https://fonts.googleapis.com/css2?family=Great+Vibes&family=Playfair+Display:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.2.0/remixicon.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
:root {
  --gold-50: #f8e8d9;
  --gold-100: #f5ecea;
  --gold-300: #d4a574;
  --gold-400: #8b735f;
  --gold-500: #e6c7a3;
}

body {
  font-family: 'Playfair Display', serif;
}

.login__title {
  font-family: 'Great Vibes', cursive;
  font-size: clamp(2.5rem, 6vw, 4rem);
  color: var(--gold-300);
  margin-bottom: 2rem;
  text-align: center;
  text-shadow: 0 2px 4px rgba(212, 165, 116, 0.3);
}

.login__input {
  width: 100%;
  padding: 1rem 1rem 1rem 3rem;
  border: 2px solid var(--gold-300);
  border-radius: 12px;
  font-size: 1rem;
  font-family: 'Playfair Display', serif;
  background: rgba(255,255,255,0.9);
  transition: all 0.3s ease;
  outline: none;
}

.login__input:focus {
  border-color: var(--gold-300);
  box-shadow: 0 0 0 3px rgba(212, 165, 116, 0.2);
  transform: translateY(-2px);
}

.login__input::placeholder {
  color: transparent;
}

.login__label {
  position: absolute;
  left: 3rem;
  top: 1rem;
  font-size: 1rem;
  font-family: 'Playfair Display', serif;
  color: var(--gold-400);
  pointer-events: none;
  transition: all 0.3s ease;
}

.login__input:focus + .login__label,
.login__input:valid + .login__label {
  top: 0.5rem;
  left: 2rem;
  font-size: 0.85rem;
  color: var(--gold-300);
  background: white;
  padding: 0 0.5rem;
  font-weight: 600;
}

.login__icon {
  position: absolute;
  left: 1rem;
  top: 50%;
  transform: translateY(-50%);
  color: var(--gold-400);
  font-size: 1.2rem;
  transition: color 0.3s ease;
}

.login__input:focus ~ .login__icon {
  color: var(--gold-300);
}

.login__button {
  width: 100%;
  padding: 1.2rem;
  background: linear-gradient(135deg, var(--gold-300), var(--gold-500));
  color: white;
  border: none;
  border-radius: 12px;
  font-size: 1.1rem;
  font-weight: 600;
  font-family: 'Playfair Display', serif;
  cursor: pointer;
  transition: all 0.3s ease;
  margin-top: 1rem;
  box-shadow: 0 10px 25px rgba(212, 165, 116, 0.4);
}

.login__button:hover {
  transform: translateY(-3px);
  box-shadow: 0 20px 40px rgba(212, 165, 116, 0.6);
}
</style>
</head>
<body class="bg-gradient-to-br from-rose-50 via-pink-50 to-orange-50">
<?php
session_start();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = "Please fill in all fields.";
    } else {
        // Database connection
        try {
            $pdo = new PDO("mysql:host=localhost;dbname=elegance_bakes", 'root', '');
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $stmt = $pdo->prepare("SELECT id, first_name, last_name, email, password FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
                header('Location: index.php');
                exit;
            } else {
                $error = "Invalid email or password.";
            }
        } catch (PDOException $e) {
            $error = "Database connection failed.";
        }
    }
}
?>

<section class="flex flex-col items-center justify-center px-6 py-8 mx-auto md:h-screen lg:py-0 min-h-screen">
<div class="w-full bg-white/95 backdrop-blur-xl rounded-xl shadow-2xl border border-gold-300/20 md:mt-0 sm:max-w-md xl:p-0 max-w-lg mx-auto relative z-10">
<div class="p-8 space-y-6">
<h1 class="login__title">Welcome Back</h1>

<?php if ($error): ?>
<div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6">
❌ <?php echo htmlspecialchars($error); ?>
</div>
<?php endif; ?>

<form method="POST" id="login-form" class="space-y-6">
<div class="relative">
<input type="email" name="email" id="email" class="login__input peer" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" placeholder=" " required>
<label for="email" class="login__label">Your email</label>
<i class="ri-mail-fill login__icon"></i>
</div>

<div class="relative">
<input type="password" name="password" id="password" class="login__input peer" placeholder=" " required>
<label for="password" class="login__label">Password</label>
<i class="ri-eye-off-fill login__icon login__password absolute right-4 top-1/2 -translate-y-1/2 cursor-pointer" id="togglePassword"></i>
</div>

<div class="flex items-center justify-between">
<div class="flex items-start">
<div class="flex items-center h-5 mt-1">
<input id="remember" type="checkbox" class="w-4 h-4 border border-gold-300 rounded bg-white/80 focus:ring-2 focus:ring-gold-300">
</div>
<label for="remember" class="ml-3 text-sm text-gold-400">Remember me</label>
</div>
<a href="#" class="text-sm font-medium text-gold-300 hover:text-gold-500 hover:underline transition-colors">Forgot password?</a>
</div>

<button type="submit" class="login__button w-full focus:ring-4 focus:outline-none focus:ring-gold-500/50 font-medium rounded-lg text-sm px-5 py-2.5 text-center">
Sign in
</button>

<p class="text-sm text-gold-400 text-center mt-6">
Don't have an account? <a href="register.php" class="font-semibold text-gold-300 hover:text-gold-500 hover:underline transition-colors">Sign up</a>
</p>
</form>
</div>
</div>
</section>

<script>
document.getElementById('togglePassword').addEventListener('click', function() {
    const password = document.getElementById('password');
    const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
    password.setAttribute('type', type);
    this.classList.toggle('ri-eye-off-fill');
    this.classList.toggle('ri-eye-fill');
});
</script>

</body>
</html>
