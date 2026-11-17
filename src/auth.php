<?php
#sessions, CSRF, accounts, and the output helpers every page shares
declare(strict_types=1);

require_once __DIR__ . '/db.php';

#start the session with a hardened cookie
function auth_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('aod_session');
    session_start();
}

#escape a value for HTML output
function h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

#redirect and stop, used after every POST
function redirect(string $page): never
{
    header('Location: ' . $page);
    exit;
}

#return the session CSRF token, creating it on first use
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

#hidden input carrying the CSRF token
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

#reject the request unless the posted token matches the session token
function csrf_check(): void
{
    $sent = $_POST['csrf_token'] ?? '';
    $known = $_SESSION['csrf'] ?? '';
    if (!is_string($sent) || $known === '' || !hash_equals($known, $sent)) {
        http_response_code(400);
        exit('Invalid form token. Go back, reload the page, and try again.');
    }
}

#store a one-time notice for the next page view
function flash_set(string $message): void
{
    $_SESSION['flash'] = $message;
}

#read and clear the one-time notice
function flash_take(): ?string
{
    $message = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return is_string($message) ? $message : null;
}

#read one posted field as a trimmed string
function post_string(string $key): string
{
    $value = $_POST[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

#validate the sign-up input and return a list of error messages
function auth_validate_registration(string $username, string $password, string $confirm): array
{
    $errors = [];
    if ($username === '' || $password === '' || $confirm === '') {
        return ['Username, password, and confirm password are all required.'];
    }
    if (!preg_match('/^[A-Za-z0-9_]{3,16}$/', $username)) {
        $errors[] = 'Username must be 3 to 16 characters: letters, digits, and underscore.';
    }
    if (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must be at least 8 characters with a letter and a number.';
    }
    if ($password !== $confirm) {
        $errors[] = 'The two passwords do not match.';
    }
    return $errors;
}

#create an account, returns error messages (empty on success)
function auth_register(PDO $pdo, string $username, string $password, string $confirm): array
{
    $errors = auth_validate_registration($username, $password, $confirm);
    if ($errors !== []) {
        return $errors;
    }
    if (db_find_user($pdo, $username) !== null) {
        return ['That username is already taken.'];
    }
    db_create_user($pdo, $username, password_hash($password, PASSWORD_DEFAULT));
    return [];
}

#check a login, returns the user row or null
function auth_attempt(PDO $pdo, string $username, string $password): ?array
{
    if ($username === '' || $password === '') {
        return null;
    }
    $user = db_find_user($pdo, $username);
    if ($user === null || !password_verify($password, $user['password_hash'])) {
        return null;
    }
    return $user;
}

#mark the session as signed in
function auth_login(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['username'] = (string)$user['username'];
}

#true when a user is signed in
function auth_check(): bool
{
    return isset($_SESSION['user_id']);
}

#send guests to the login page
function require_login(): void
{
    if (!auth_check()) {
        redirect('login.php');
    }
}

#destroy the session and its cookie
function auth_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 3600,
            'path' => $params['path'],
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
    session_destroy();
}
