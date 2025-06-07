<?php
$page_title = "Login";
require_once 'includes/auth.php';

// Start session to handle messages
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$auth = new Auth();

// Check for messages from previous redirect
$error = $_SESSION['login_error'] ?? '';
$success = $_SESSION['login_success'] ?? '';
$form_data = $_SESSION['login_form_data'] ?? [];

// Clear messages and form data from session
unset($_SESSION['login_error'], $_SESSION['login_success'], $_SESSION['login_form_data']);

// Get redirect URL if provided
$redirect_url = $_GET['redirect'] ?? '';

// Redirect if already logged in
if ($auth->isLoggedIn()) {
    if (!empty($redirect_url)) {
        header('Location: ' . $redirect_url);
    } elseif ($auth->isAdmin()) {
        header('Location: admin/dashboard.php');
    } else {
        header('Location: index.php');
    }
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['login'])) {
        // Login
        $email = trim($_POST['email']);
        $password = $_POST['password'];

        $result = $auth->login($email, $password);
        if ($result['success']) {
            if (!empty($redirect_url)) {
                header('Location: ' . $redirect_url);
            } elseif ($result['user']['role'] === 'admin') {
                header('Location: admin/dashboard.php');
            } else {
                header('Location: index.php');
            }
            exit();
        } else {
            $_SESSION['login_error'] = $result['message'];
            $_SESSION['login_form_data'] = ['email' => $email, 'form_type' => 'login'];
        }
    } elseif (isset($_POST['register'])) {
        // Registration
        $email = trim($_POST['reg_email']);
        $password = $_POST['reg_password'];
        $confirm_password = $_POST['confirm_password'];
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);

        $form_data_reg = [
            'reg_email' => $email,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'form_type' => 'register'
        ];

        if ($password !== $confirm_password) {
            $_SESSION['login_error'] = 'Passwords do not match';
            $_SESSION['login_form_data'] = $form_data_reg;
        } elseif (strlen($password) < 6) {
            $_SESSION['login_error'] = 'Password must be at least 6 characters long';
            $_SESSION['login_form_data'] = $form_data_reg;
        } else {
            $result = $auth->register($email, $password, $first_name, $last_name);
            if ($result['success']) {
                $_SESSION['login_success'] = $result['message'] . ' You can now log in.';
                // Don't store form data on success
            } else {
                $_SESSION['login_error'] = $result['message'];
                $_SESSION['login_form_data'] = $form_data_reg;
            }
        }
    }

    // Redirect to prevent form resubmission
    $current_url = $_SERVER['PHP_SELF'];
    if (!empty($redirect_url)) {
        $current_url .= '?redirect=' . urlencode($redirect_url);
    }
    header('Location: ' . $current_url);
    exit;
}

include 'includes/header.php';
?>

<main class="main">
    <section class="login-section section">
        <div class="login-container container">
            <div class="login-content grid">
                <!-- Login Form -->
                <div class="login-form-container" id="login-form" style="display: <?php echo ($form_data['form_type'] ?? '') === 'register' ? 'none' : 'block'; ?>;">
                    <form action="" method="POST" class="form">
                        <h2 class="form__title">Sign In</h2>
                        <p class="form__description">Welcome back! Please sign in to your EVSU account.</p>

                        <?php if ($error): ?>
                            <div class="alert alert-error login-error">
                                <i class="ri-error-warning-line"></i>
                                <?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="alert alert-success login-success">
                                <i class="ri-check-line"></i>
                                <?php echo htmlspecialchars($success); ?>
                            </div>
                        <?php endif; ?>

                        <div class="form__group">
                            <label for="email" class="form__label">EVSU Email</label>
                            <input type="email" name="email" id="email" class="form__input"
                                placeholder="your.name@evsu.edu.ph"
                                value="<?php echo htmlspecialchars($form_data['email'] ?? ''); ?>" required>
                        </div>

                        <div class="form__group">
                            <label for="password" class="form__label">Password</label>
                            <input type="password" name="password" id="password" class="form__input"
                                placeholder="Enter your password" required>
                        </div>

                        <button type="submit" name="login" class="form__button button" id="login-btn">
                            <span class="button-text">
                                <i class="ri-login-box-line"></i> Sign In
                            </span>
                            <span class="button-loader" style="display: none;">
                                <i class="ri-loader-4-line"></i> Signing In...
                            </span>
                        </button>

                        <p class="form__switch">
                            Don't have an account?
                            <a href="../joyces/index.php" class="form__link">Sign Up</a>
                            <!-- <a href="#" onclick="toggleForms()" class="form__link">Sign Up</a> -->
                        </p>
                    </form>
                </div>

                <!-- Registration Form -->
                <div class="register-form-container" id="register-form" style="display: <?php echo ($form_data['form_type'] ?? '') === 'register' ? 'block' : 'none'; ?>;">
                    <form action="" method="POST" class="form form-register">
                        <h2 class="form__title">Sign Up</h2>
                        <p class="form__description">Create your EVSU Voice account to get started.</p>

                        <div class="form__group-row">
                            <div class="form__group">
                                <label for="first_name" class="form__label">First Name</label>
                                <input type="text" name="first_name" id="first_name" class="form__input"
                                    placeholder="First Name"
                                    value="<?php echo htmlspecialchars($form_data['first_name'] ?? ''); ?>" required>
                            </div>
                            <div class="form__group">
                                <label for="last_name" class="form__label">Last Name</label>
                                <input type="text" name="last_name" id="last_name" class="form__input"
                                    placeholder="Last Name"
                                    value="<?php echo htmlspecialchars($form_data['last_name'] ?? ''); ?>" required>
                            </div>
                        </div>

                        <div class="form__group">
                            <label for="reg_email" class="form__label">EVSU Email</label>
                            <input type="email" name="reg_email" id="reg_email" class="form__input"
                                placeholder="your.name@evsu.edu.ph"
                                value="<?php echo htmlspecialchars($form_data['reg_email'] ?? ''); ?>" required>
                        </div>

                        <div class="form__group">
                            <label for="reg_password" class="form__label">Password</label>
                            <input type="password" name="reg_password" id="reg_password" class="form__input"
                                placeholder="Create a password (min. 6 characters)" required>
                        </div>

                        <div class="form__group">
                            <label for="confirm_password" class="form__label">Confirm Password</label>
                            <input type="password" name="confirm_password" id="confirm_password" class="form__input"
                                placeholder="Confirm your password" required>
                        </div>

                        <button type="submit" name="register" class="form__button button" id="register-btn">
                            <span class="button-text">
                                <i class="ri-user-add-line"></i> Sign Up
                            </span>
                            <span class="button-loader" style="display: none;">
                                <i class="ri-loader-4-line"></i> Creating Account...
                            </span>
                        </button>

                        <p class="form__switch">
                            Already have an account?
                            <a href="#" onclick="toggleForms()" class="form__link">Sign In</a>
                        </p>
                    </form>
                </div>

                <!-- Illustration -->
                <div class="login-illustration">
                    <img src="assets/img/evsu-logo.png" alt="EVSU Logo" class="login-logo">
                    <h3>EVSU VOICE</h3>
                    <p>Your platform for sharing ideas and feedback to improve our university community.</p>
                </div>
            </div>
        </div>
    </section>
</main>

<script>
    function toggleForms() {
        const loginForm = document.getElementById('login-form');
        const registerForm = document.getElementById('register-form');

        if (loginForm.style.display === 'none') {
            loginForm.style.display = 'block';
            registerForm.style.display = 'none';
        } else {
            loginForm.style.display = 'none';
            registerForm.style.display = 'block';
        }
    }

    function showLoader(buttonId) {
        const button = document.getElementById(buttonId);
        const buttonText = button.querySelector('.button-text');
        const buttonLoader = button.querySelector('.button-loader');

        buttonText.style.display = 'none';
        buttonLoader.style.display = 'inline-flex';

        // Disable button after a delay to allow form submission
        setTimeout(() => {
            button.disabled = true;
        }, 2000);
    }

    // Add form submission handlers
    document.addEventListener('DOMContentLoaded', function() {
        const loginForm = document.querySelector('#login-form form');
        const registerForm = document.querySelector('#register-form form');

        if (loginForm) {
            loginForm.addEventListener('submit', function(e) {
                // Validate form before showing loader
                const email = document.getElementById('email').value.trim();
                const password = document.getElementById('password').value;

                if (email && password) {
                    showLoader('login-btn');
                }
            });
        }

        if (registerForm) {
            registerForm.addEventListener('submit', function(e) {
                // Validate form before showing loader
                const email = document.getElementById('reg_email').value.trim();
                const password = document.getElementById('reg_password').value;
                const confirmPassword = document.getElementById('confirm_password').value;
                const firstName = document.getElementById('first_name').value.trim();
                const lastName = document.getElementById('last_name').value.trim();

                if (email && password && confirmPassword && firstName && lastName && password === confirmPassword) {
                    showLoader('register-btn');
                }
            });
        }
    });
</script>

<style>
    .form-register {
        margin-top: 1rem;
    }

    .form__group-row {
        margin-bottom: 0;
    }

    .alert.alert-error.login-error {
        padding: 0.5rem 1rem;
    }

    .alert.alert-success.login-success {
        padding: 0.5rem 1rem;
    }

    /* Center align alert content horizontally */
    .alert.alert-error.login-error,
    .alert.alert-success.login-success {
        display: flex;
        align-items: center;
        justify-content: left;
        text-align: center;
        gap: 0.5rem;
    }

    .alert.alert-error.login-error i,
    .alert.alert-success.login-success i {
        flex-shrink: 0;
    }

    /* Center align button content horizontally */
    .form__button.button {
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
    }

    .button-text,
    .button-loader {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }

    .button-text i,
    .button-loader i {
        font-size: 1.1rem;
    }
</style>

<?php include 'includes/footer.php'; ?>