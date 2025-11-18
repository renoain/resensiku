<?php
require_once 'config/constants.php';
require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $database = new Database();
    $db = $database->getConnection();
    
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    $errors = [];
    
    // Validasi
    if (empty($first_name)) $errors[] = "First name is required";
    if (empty($last_name)) $errors[] = "Last name is required";
    if (empty($email)) $errors[] = "Email is required";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format";
    if (empty($password)) $errors[] = "Password is required";
    if (strlen($password) < 6) $errors[] = "Password must be at least 6 characters";
    if ($password !== $confirm_password) $errors[] = "Passwords do not match";
    
    // Check if email exists
    if (empty($errors)) {
        $check_email = $db->prepare("SELECT id FROM users WHERE email = ?");
        $check_email->execute([$email]);
        if ($check_email->fetch()) {
            $errors[] = "Email already registered";
        }
    }
    
    // Register user
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $query = "INSERT INTO users (first_name, last_name, email, password) VALUES (?, ?, ?, ?)";
        $stmt = $db->prepare($query);
        
        if ($stmt->execute([$first_name, $last_name, $email, $hashed_password])) {
            $_SESSION['success'] = "Registration successful! Please login.";
            header("Location: login.php");
            exit();
        } else {
            $errors[] = "Registration failed. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Resensiku</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/auth.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <img src="assets/images/logo/Resensiku.png" alt="Resensiku" class="auth-logo">
                <h1>Daftar Akun Baru</h1>
                <p>Bergabunglah dengan komunitas pembaca Indonesia</p>
            </div>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo htmlspecialchars($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" class="auth-form">
                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name">Nama Depan</label>
                        <input type="text" id="first_name" name="first_name" required 
                               value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="last_name">Nama Belakang</label>
                        <input type="text" id="last_name" name="last_name" required
                               value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="password-wrapper">
                        <input type="password" id="password" name="password" placeholder="Masukkan password" required>
                        <button type="button" class="toggle-password" onclick="togglePasswordVisibility('password', 'passwordIcon')">
                            <i class="fas fa-eye-slash" id="passwordIcon"></i>
                        </button>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Konfirmasi Password</label>
                    <div class="password-wrapper">
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="Konfirmasi password" required>
                        <button type="button" class="toggle-password" onclick="togglePasswordVisibility('confirm_password', 'confirmPasswordIcon')">
                            <i class="fas fa-eye-slash" id="confirmPasswordIcon"></i>
                        </button>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary btn-full">Daftar</button>
            </form>
            
            <div class="auth-footer">
                <p>Sudah punya akun? <a href="login.php">Login di sini</a></p>
            </div>
        </div>
    </div>

    <script>
        function togglePasswordVisibility(inputId, iconId) {
            const passwordInput = document.getElementById(inputId);
            const eyeIcon = document.getElementById(iconId);
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.classList.remove('fa-eye-slash');
                eyeIcon.classList.add('fa-eye');
            } else {
                passwordInput.type = 'password';
                eyeIcon.classList.remove('fa-eye');
                eyeIcon.classList.add('fa-eye-slash');
            }
        }

        // Enhanced version with better UX
        function togglePasswordVisibilityEnhanced(inputId, iconId) {
            const passwordInput = document.getElementById(inputId);
            const eyeIcon = document.getElementById(iconId);
            const passwordWrapper = passwordInput.parentElement;
            
            // Toggle input type
            const isPassword = passwordInput.type === 'password';
            passwordInput.type = isPassword ? 'text' : 'password';
    
            // Toggle icon
            if (isPassword) {
                eyeIcon.classList.remove('fa-eye-slash');
                eyeIcon.classList.add('fa-eye');
                passwordWrapper.classList.add('show-password');
            } else {
                eyeIcon.classList.remove('fa-eye');
                eyeIcon.classList.add('fa-eye-slash');
                passwordWrapper.classList.remove('show-password');
            }
            
            // Maintain focus on input
            passwordInput.focus();
        }

        // Alternative without Font Awesome (using CSS only)
        function togglePasswordVisibilityCSS(inputId) {
            const passwordInput = document.getElementById(inputId);
            const passwordWrapper = passwordInput.parentElement;
            
            // Toggle input type
            const isPassword = passwordInput.type === 'password';
            passwordInput.type = isPassword ? 'text' : 'password';
            
            // Toggle CSS class for styling
            if (isPassword) {
                passwordWrapper.classList.add('show-password');
            } else {
                passwordWrapper.classList.remove('show-password');
            }
            
            // Maintain focus
            passwordInput.focus();
        }
    </script>
</body>
</html>