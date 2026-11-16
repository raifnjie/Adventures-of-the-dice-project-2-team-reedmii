<?php
#shared page head and top bar, expects $pageTitle and optional $headerLinks
if (!function_exists('h')) {
    http_response_code(404);
    exit;
}
$headerLinks = $headerLinks ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($pageTitle) ?> | Adventures of the Dice</title>
<link rel="stylesheet" href="style.php">
</head>
<body>
<header class="topbar">
    <a class="brand" href="index.php">Adventures of the Dice</a>
    <nav class="topbar-links">
        <?php foreach ($headerLinks as $link): ?>
            <?php if (($link['method'] ?? 'get') === 'post'): ?>
                <form method="post" action="<?= h($link['href']) ?>">
                    <?= csrf_field() ?>
                    <?php foreach (($link['fields'] ?? []) as $name => $value): ?>
                        <input type="hidden" name="<?= h($name) ?>" value="<?= h($value) ?>">
                    <?php endforeach; ?>
                    <button type="submit" class="btn <?= h($link['class'] ?? 'btn-outline') ?>"><?= h($link['label']) ?></button>
                </form>
            <?php else: ?>
                <a class="btn <?= h($link['class'] ?? 'btn-outline') ?>" href="<?= h($link['href']) ?>"><?= h($link['label']) ?></a>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>
</header>
<main class="page">
