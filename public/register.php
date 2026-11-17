<?php
#sign-up form
declare(strict_types=1);

require_once __DIR__ . '/../src/auth.php';

auth_start();
if (auth_check()) {
    redirect('game.php');
}

$errors = [];
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $username = post_string('username');
    #passwords are not trimmed, spaces may be part of them
    $password = post_raw('password');
    $confirm = post_raw('confirm');
    $errors = auth_register($username, $password, $confirm);
    if ($errors === []) {
        flash_set('Account created. Log in to start playing.');
        redirect('login.php');
    }
}

$pageTitle = 'Create account';
$headerLinks = [['href' => 'login.php', 'label' => 'Log in']];
require __DIR__ . '/partials/header.php';
?>
<section class="panel form-panel">
    <h1>Create account</h1>
    <?php if ($errors !== []): ?>
        <?php #one message at a time, the first rule that failed ?>
        <p class="error" role="alert">Error: <?= h($errors[0]) ?></p>
    <?php endif; ?>
    <form method="post" action="register.php" novalidate>
        <?= csrf_field() ?>
        <label for="username">Username</label>
        <input type="text" id="username" name="username" value="<?= h($username) ?>" maxlength="16" autocomplete="username" required>
        <p class="hint">3 to 16 characters: letters, digits, and underscore.</p>
        <label for="password">Password</label>
        <input type="password" id="password" name="password" autocomplete="new-password" required>
        <p class="hint">At least 8 characters with a letter and a number.</p>
        <label for="confirm">Confirm password</label>
        <input type="password" id="confirm" name="confirm" autocomplete="new-password" required>
        <button type="submit" class="btn btn-primary btn-block">Create account</button>
    </form>
    <p class="form-footer">Already have an account? <a href="login.php">Log in</a></p>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
