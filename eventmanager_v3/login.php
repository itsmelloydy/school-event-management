<?php
require_once 'config.php';

if (!empty($_SESSION['user'])) {
    header('Location: dashboard.php');
    exit;
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $message = 'Please enter your email and password.';
    } else {
        // Find the account using the submitted email.
        $stmt = $pdo->prepare('SELECT id, full_name, email, password, role FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $account = $stmt->fetch();

        // Verify the password against the stored password hash.
        if (
        $account &&
        (
        password_verify($password, $account['password']) ||
        $password === $account['password']
        )
        ) 
        {
            session_regenerate_id(true);
            unset($account['password']);
            $_SESSION['user'] = $account;
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            header('Location: dashboard.php');
            exit;
        } else {
            $message = 'Incorrect email or password.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login | CampusConnect</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/style.css" rel="stylesheet">
</head>
<body>
<div class="login-shell">
    <div class="login-card">
        <div class="brand-mark mb-3">CC</div>
        <p class="eyebrow">CAMPUSCONNECT</p>
        <h1 class="h3 fw-bold">Welcome back</h1>
        <p class="text-secondary">Sign in to manage and discover school events.</p>

        <?php if ($message !== ''): ?>
            <div class="alert alert-danger"><?= e($message) ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <label class="form-label" for="email">Email address</label>
            <input class="form-control form-control-lg mb-3" id="email" type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" required>

            <label class="form-label" for="password">Password</label>

<div class="input-group mb-4">
    <input
        class="form-control form-control-lg"
        id="password"
        type="password"
        name="password"
        required
    >

    <button
        class="btn btn-outline-secondary"
        type="button"
        onclick="togglePassword()">
        Show
    </button>
</div>

<script>
function togglePassword() {
    const password = document.getElementById('password');

    if (password.type === 'password') {
        password.type = 'text';
    } else {
        password.type = 'password';
    }
}
</script>

            <button class="btn btn-primary btn-lg w-100" type="submit">Sign in →</button>
        </form>
        <p class="mt-4 mb-0">No account? <a href="register.php">Create an account</a></p>
    </div>
    <div class="login-aside">
        <div class="aside-content">
            <span class="pill-light">YOUR CAMPUS, CONNECTED</span>
            <h2>Every great school memory starts with an event.</h2>
            <p>Discover campus activities, reserve your place, and keep every school event organized.</p>
        </div>
    </div>
</div>
</body>
</html>
