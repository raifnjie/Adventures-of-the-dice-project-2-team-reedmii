<?php
#leaderboard and the Adventure Recap for the current game
declare(strict_types=1);

require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/game_engine.php';

auth_start();
require_login();

$state = game_load();
$scores = db_top_scores(db(), 10);
$finished = $state !== null && $state['winner'] !== null;

$pageTitle = 'Leaderboard';
$headerLinks = [];
if ($finished) {
    $headerLinks[] = ['href' => 'game.php', 'label' => 'Play again', 'method' => 'post', 'class' => 'btn-primary', 'fields' => ['action' => 'reset']];
} else {
    $headerLinks[] = ['href' => 'game.php', 'label' => 'Back to game', 'class' => 'btn-primary'];
}
$headerLinks[] = ['href' => 'logout.php', 'label' => 'Log out', 'method' => 'post'];
require __DIR__ . '/partials/header.php';
?>
<div class="leaderboard-layout">
    <section class="panel">
        <h1>Leaderboard</h1>
        <?php if ($scores === []): ?>
            <p>No finished games yet. Win a game to claim rank 1.</p>
        <?php else: ?>
            <table class="score-table">
                <thead>
                    <tr>
                        <th scope="col">Rank</th>
                        <th scope="col">Player</th>
                        <th scope="col">Difficulty</th>
                        <th scope="col">Rolls</th>
                        <th scope="col">Time played</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($scores as $index => $score): ?>
                        <tr>
                            <td><?= h($index + 1) ?></td>
                            <td><?= h($score['player_name']) ?></td>
                            <td><?= h(board_get($score['difficulty'])['label']) ?></td>
                            <td><?= h($score['rolls']) ?></td>
                            <td><?= h(game_format_time((int)$score['time_played'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <p class="note">Sorted by fewest rolls, then shortest time played. Top 10 shown.</p>
    </section>

    <section class="panel">
        <h2>Adventure Recap</h2>
        <?php if ($state === null): ?>
            <p>No game in progress. Start one to write a new adventure.</p>
        <?php else: ?>
            <?php if ($finished): ?>
                <?php $winner = $state['winner']; ?>
                <p class="player-active">
                    Winner: <?= h($state['players'][$winner]) ?>,
                    <?= h(board_get($state['difficulty'])['label']) ?>,
                    <?= h($state['rolls'][$winner]) ?> rolls,
                    <?= h(game_format_time(game_time_played($state))) ?>
                </p>
            <?php else: ?>
                <p>
                    Game in progress: <?= h(board_get($state['difficulty'])['label']) ?>,
                    <?= h($state['turns_taken']) ?> turns taken.
                </p>
            <?php endif; ?>
            <?php if ($state['events_log'] === []): ?>
                <p class="small">No story events were triggered.</p>
            <?php else: ?>
                <?php foreach ($state['events_log'] as $entry): ?>
                    <p class="recap-title"><?= h(ucfirst($entry['type'])) ?>: Turn <?= h($entry['turn']) ?>, cell <?= h($entry['cell']) ?>, <?= h($entry['player']) ?></p>
                    <p class="small"><?= h(events_recap_text($entry)) ?></p>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
