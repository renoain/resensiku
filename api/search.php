<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - Resensiku</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Infant:wght@400;600&family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="sign up CSS.css"> 
</head>
<body>
    <header class="logo">
        <h1>Resensiku</h1>
    </header>

    <div class="login-container"> 
        <form id="signupForm" class="login-box"> 
            <h2>Sign Up</h2>
            <p class="welcome">Create your Account</p>

            <div class="name-group">
                <div class="input-group name-input">
                    <label for="firstName">First Name</label>
                    <input type="text" id="firstName" name="firstName" required>
                </div>
                <div class="input-group name-input">
                    <label for="lastName">Last Name</label>
                    <input type="text" id="lastName" name="lastName" required>
                </div>
            </div>

            <div class="input-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" placeholder="Enter your email id" required>
            </div>

            <div class="input-group">
                <label for="password">Password</label>
                <div class="password-wrapper">
                    <input type="password" id="password" name="password" placeholder="Enter your password" required>
                    <span class="toggle-password" onclick="togglePasswordVisibility()">
                        <i class="fas fa-eye-slash" id="eyeIcon"></i>
                    </span>
                </div>
            </div>

            <div class="options">
                <label>
                    <input type="checkbox" name="remember">
                    Remember Me
                </label>
                <a href="#" class="forgot-password">Forgot Password?</a>
            </div>

            <button type="submit" class="btn-login">Create Account</button> 
            <p class="signup-link">
                Already A Member? <a href="login.html">Log in</a>
            </p>
        </form>
    </div>

    <div id="rememberMeModal" class="modal">
    <div class="modal-content">
        <p>Apakah Anda ingin browser mengingat data Anda?</p>
        <div class="modal-actions">
            <button id="modalSaveBtn" class="modal-btn save-btn">Save</button>
            <button id="modalCancelBtn" class="modal-btn cancel-btn">Cancel</button>
        </div>
    </div>
</div>

    <script src="sign up .js"></script> 

    
    </body>
</html>