<?php
require_once 'config.php';

// Public registration is ONLY for student/staff accounts.
// Administrator accounts must be inserted directly into MySQL by an authorized person.
if (!empty($_SESSION['user'])) {
    header('Location: dashboard.php');
    exit;
}

$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $yearLevel = trim($_POST['year_level'] ?? '');
    $block = trim($_POST['block'] ?? '');

    $allowedYearLevels = ['1st Year', '2nd Year', '3rd Year', '4th Year'];
    $allowedBlocks = ['CS-A', 'CS-B', 'IS-A', 'IS-B'];

    if ($fullName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
        $message = 'Enter your full name, a valid email, and a password with at least 8 characters.';
    } elseif (
    !in_array($yearLevel, $allowedYearLevels, true) ||
    !in_array($block, $allowedBlocks, true)
    ) {
    $message = 'Please select a valid year level and block.';
    } else {
        try {
            $check = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $check->execute([$email]);

            if ($check->fetch()) {
                $message = 'That email address is already registered.';
            } else {
                $hash = $password;
                // role is deliberately fixed to staff; the public form cannot create admins.
                $insert = $pdo->prepare(
                    'INSERT INTO users (full_name, email, password, role, year_level, block)
                     VALUES (?, ?, ?, \'staff\', ?, ?)'
                );
                $insert->execute([$fullName, $email, $hash, $yearLevel, $block]);
                $success = true;
                $message = 'Student/staff account created successfully. You can now sign in.';
            }
        } catch (PDOException $e) {
            $message = 'Unable to create the account. Please try again.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Register | CampusConnect</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/style.css" rel="stylesheet">
</head>
<body>
<div class="login-shell">
    <div class="login-card register-card">
        <div class="brand-mark mb-3">CC</div>
        <p class="eyebrow">CAMPUSCONNECT</p>
        <h1 class="h3 fw-bold">Create a student/staff account</h1>
        <p class="text-secondary">Register to discover and join school events.</p>

        <?php if ($message !== ''): ?>
            <div class="alert alert-<?= $success ? 'success' : 'danger' ?>"><?= e($message) ?></div>
        <?php endif; ?>

        <form method="POST" action="register.php">
            <label class="form-label" for="full_name">Full name</label>
            <input class="form-control mb-3" id="full_name" name="full_name" maxlength="120" value="<?= e($_POST['full_name'] ?? '') ?>" required>

            <label class="form-label" for="email">Email</label>
            <input class="form-control mb-3" id="email" type="email" name="email" maxlength="150" value="<?= e($_POST['email'] ?? '') ?>" required>

            <label class="form-label" for="password">
    Password (at least 8 characters)
</label>

<div class="input-group mb-3">
    <input
        class="form-control"
        id="password"
        type="password"
        name="password"
        minlength="8"
        required
    >

    <button
        class="btn btn-outline-secondary"
        type="button"
        onclick="toggleRegisterPassword()">
        Show
    </button>
</div>

<script>
function toggleRegisterPassword() {
    const password = document.getElementById('password');

    password.type =
        password.type === 'password' ? 'text' : 'password';
}
</script>

            <label class="form-label" for="year_level">Year Level</label>
<select class="form-select mb-3" id="year_level" name="year_level" required>
    <option value="">Select Year Level</option>

    <?php foreach (['1st Year', '2nd Year', '3rd Year', '4th Year'] as $year): ?>
        <option value="<?= e($year) ?>"
            <?= (($_POST['year_level'] ?? '') === $year) ? 'selected' : '' ?>>
            <?= e($year) ?>
        </option>
    <?php endforeach; ?>
</select>

<label class="form-label" for="block">Block / Section</label>
<select class="form-select mb-4" id="block" name="block" required>
    <option value="">Select Block</option>

    <?php foreach (['CS-A', 'CS-B', 'IS-A', 'IS-B'] as $section): ?>
        <option value="<?= e($section) ?>"
            <?= (($_POST['block'] ?? '') === $section) ? 'selected' : '' ?>>
            <?= e($section) ?>
        </option>
    <?php endforeach; ?>
</select>

            <button class="btn btn-primary w-100 btn-lg" type="submit">Create account</button>
        </form>
        <p class="mt-4 mb-0">Already registered? <a href="login.php">Sign in</a></p>
        <small class="text-secondary d-block mt-3">Administrator accounts are created by an authorized administrator directly in the database.</small>
    </div>
    <div class="login-aside">
        <div class="aside-content">
            <span class="pill-light">JOIN CAMPUSCONNECT</span>
            <h2>Be part of every campus moment.</h2>
            <p>Create your account to discover and register for school events.</p>
        </div>
    </div>
</div>
</body>
</html>
