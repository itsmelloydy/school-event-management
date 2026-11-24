<?php
// Shared database connection and small helper functions.
// Page-specific SQL queries stay inside each page file.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host = 'localhost';
$dbname = 'school_event_db';
$dbuser = 'root';
$dbpass = '';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $dbuser,
        $dbpass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    exit('Database connection failed. Check config.php and make sure MySQL is running.');
}

function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function require_login(): void {
    if (empty($_SESSION['user'])) {
        header('Location: login.php');
        exit;
    }
}

function require_admin(): void {
    require_login();
    if (($_SESSION['user']['role'] ?? '') !== 'admin') {
        http_response_code(403);
        exit('You do not have permission to access this page.');
    }
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(419);
        exit('Your session token expired. Please go back, refresh the page, and try again.');
    }
}
