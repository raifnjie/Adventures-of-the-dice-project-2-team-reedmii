<?php
#login form
declare(strict_types=1);

require_once __DIR__ . '/../src/auth.php';

auth_start();
if (auth_check()) {
    redirect('game.php');
}

$error = null;
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $username = post_string('username');
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    if ($username === '' || $password === '') {
        #empty input never reaches the database
        $error = 'Enter your username and password.';
    } else {
        $user = auth_attempt(db(), $username, $password);
        if ($user === null) {
            #one message for both cases, so it never reveals whether the name exists
            $error = 'Invalid username or password.';
        } else {
            auth_login($user);
            redirect('game.php');
        }
    }
}

$notice = flash_take();
$pageTitle = 'Log in';
$headerLinks = [['href' => 'register.php', 'label' => 'Sign up']];
require __DIR__ . '/partials/header.php';
?>
<section class="panel form-panel">
    <h1>Log in</h1>
    <?php if ($notice !== null): ?>
        <p class="notice" role="status"><?= h($notice) ?></p>
    <?php endif; ?>
    <?php if ($error !== null): ?>
        <p class="error" role="alert"><?= h($error) ?></p>
    <?php endif; ?>
    <form method="post" action="login.php" novalidate>
        <?= csrf_field() ?>
        <label for="username">Username</label>
        <input type="text" id="username" name="username" value="<?= h($username) ?>" maxlength="16" autocomplete="username" required>
        <label for="password">Password</label>
        <input type="password" id="password" name="password" autocomplete="current-password" required>
        <button type="submit" class="btn btn-primary btn-block">Log in</button>
    </form>
    <p class="form-footer">New here? <a href="register.php">Create account</a></p>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
