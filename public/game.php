<?php
#game screen: setup, roll, board, narrator, players, event log
declare(strict_types=1);

require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/game_engine.php';
require_once __DIR__ . '/../src/leaderboard.php';

const DIFFICULTY_COOKIE = 'aod_difficulty';

auth_start();
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post_string('action');

    if ($action === 'start') {
        $difficulty = post_string('difficulty');
        $names = [post_string('player1'), post_string('player2')];
        $errors = board_is_difficulty($difficulty) ? [] : ['Choose a difficulty.'];
        $errors = array_merge($errors, game_validate_names($names));
        if ($errors !== []) {
            #keep the input for the re-rendered form
            $_SESSION['setup_errors'] = $errors;
            $_SESSION['setup_input'] = ['difficulty' => $difficulty, 'player1' => $names[0], 'player2' => $names[1]];
            redirect('game.php');
        }
        game_clear();
        game_save(game_new($difficulty, $names));
        #remember the last difficulty for the next visit
        setcookie(DIFFICULTY_COOKIE, $difficulty, [
            'expires' => time() + 60 * 60 * 24 * 30,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        redirect('game.php');
    }

    if ($action === 'roll') {
        $state = game_load();
        if ($state !== null && $state['winner'] === null) {
            $state = take_turn($state);
            if ($state['winner'] !== null && !$state['score_saved']) {
                $winner = $state['winner'];
                $entry = leaderboard_entry(
                    $state['players'][$winner],
                    $state['difficulty'],
                    $state['rolls'][$winner],
                    game_time_played($state),
                    $state['events_log']
                );
                leaderboard_add($entry);
                #course wording: store scores with $_SESSION['scores'][]
                $_SESSION['scores'][] = $entry;
                $state['score_saved'] = true;
            }
            game_save($state);
            if ($state['winner'] !== null) {
                redirect('leaderboard.php');
            }
        }
        redirect('game.php');
    }

    if ($action === 'reset') {
        game_clear();
        redirect('game.php');
    }

    redirect('game.php');
}

$state = game_load();
$setupErrors = $_SESSION['setup_errors'] ?? [];
$setupInput = $_SESSION['setup_input'] ?? [];
unset($_SESSION['setup_errors'], $_SESSION['setup_input']);

#the token slides once, a reload shows it at rest
if ($state !== null) {
    $_SESSION['prev_positions'] = $state['positions'];
}

$cookieDifficulty = filter_input(INPUT_COOKIE, DIFFICULTY_COOKIE, FILTER_UNSAFE_RAW);
$chosenDifficulty = $setupInput['difficulty'] ?? (is_string($cookieDifficulty) ? $cookieDifficulty : '');
if (!board_is_difficulty($chosenDifficulty)) {
    $chosenDifficulty = 'standard';
}
$player1 = $setupInput['player1'] ?? (string)$_SESSION['username'];
$player2 = $setupInput['player2'] ?? 'Player 2';

$playing = $state !== null && $state['winner'] === null;
$finished = $state !== null && $state['winner'] !== null;
$boardData = $state !== null ? board_get($state['difficulty']) : board_get($chosenDifficulty);
$boardState = $state;

$pageTitle = 'Game';
$headerLinks = [
    ['href' => 'leaderboard.php', 'label' => 'Leaderboard'],
    ['href' => 'logout.php', 'label' => 'Log out', 'method' => 'post'],
];
require __DIR__ . '/partials/header.php';
?>
<script>document.documentElement.classList.add('js');</script>
<div class="game-layout">
    <section class="panel game-controls">
        <?php if ($state === null): ?>
            <h2>Setup</h2>
            <?php foreach ($setupErrors as $error): ?>
                <p class="error" role="alert">Error: <?= h($error) ?></p>
            <?php endforeach; ?>
            <form method="post" action="game.php" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="start">
                <fieldset>
                    <legend>Difficulty</legend>
                    <?php foreach (array_keys(board_difficulties()) as $key): ?>
                        <label class="radio">
                            <input type="radio" name="difficulty" value="<?= h($key) ?>"<?= $key === $chosenDifficulty ? ' checked' : '' ?>>
                            <?= h(board_summary($key)) ?>
                        </label>
                    <?php endforeach; ?>
                </fieldset>
                <label for="player1">Player 1 name</label>
                <input type="text" id="player1" name="player1" value="<?= h($player1) ?>" maxlength="16" required>
                <label for="player2">Player 2 name</label>
                <input type="text" id="player2" name="player2" value="<?= h($player2) ?>" maxlength="16" required>
                <button type="submit" class="btn btn-primary btn-block">Start game</button>
            </form>
        <?php else: ?>
            <h2>Setup</h2>
            <p><?= h(board_summary($state['difficulty'])) ?></p>
            <p><?= h($state['players'][0]) ?> against <?= h($state['players'][1]) ?></p>
            <?php if ($playing): ?>
                <form method="post" action="game.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="reset">
                    <button type="submit" class="btn btn-outline btn-block">New game</button>
                </form>
            <?php endif; ?>
        <?php endif; ?>

        <hr>

        <h2>Turn</h2>
        <?php if ($playing): ?>
            <p class="turn-label"><?= h($state['players'][$state['current_turn']]) ?> to roll</p>
            <form method="post" action="game.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="roll">
                <button type="submit" class="btn btn-primary btn-block btn-roll">Roll die</button>
            </form>
            <?php if ($state['last_roll'] !== null): ?>
                <p>Last roll: <?= h($state['last_roll']) ?></p>
                <p><?= h($state['last_move']) ?></p>
            <?php endif; ?>
        <?php elseif ($finished): ?>
            <p class="turn-label"><?= h($state['players'][$state['winner']]) ?> won the game</p>
            <form method="post" action="game.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="reset">
                <button type="submit" class="btn btn-primary btn-block">Play again</button>
            </form>
            <p>Last roll: <?= h($state['last_roll']) ?></p>
        <?php else: ?>
            <p>Start a game to roll the die.</p>
        <?php endif; ?>
    </section>

    <section class="game-board">
        <?php require __DIR__ . '/partials/board.php'; ?>
        <p class="roll-history">
            Roll history:
            <?php if ($state === null || $state['roll_history'] === []): ?>
                no rolls yet
            <?php else: ?>
                <?php foreach ($state['roll_history'] as $roll): ?>
                    <span><?= h($roll) ?></span>
                <?php endforeach; ?>
            <?php endif; ?>
        </p>
    </section>

    <div class="game-side">
        <section class="panel">
            <h2>AI Narrator</h2>
            <?php if ($state === null): ?>
                <p>Pick a difficulty and two names. The story begins with the first roll.</p>
            <?php else: ?>
                <?php foreach ($state['last_event'] as $line): ?>
                    <p><?= h($line) ?></p>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

        <section class="panel">
            <h2>Players</h2>
            <?php if ($state === null): ?>
                <p>No game in progress.</p>
            <?php else: ?>
                <?php foreach ($state['players'] as $index => $name): ?>
                    <?php $isActive = $playing && $index === $state['current_turn']; ?>
                    <p class="<?= $isActive ? 'player-active' : '' ?>">
                        <?= h($name) ?>: cell <?= h($state['positions'][$index]) ?><?= $isActive ? ' (active)' : '' ?>
                    </p>
                <?php endforeach; ?>
                <p class="small">Turns taken: <?= h($state['turns_taken']) ?></p>
            <?php endif; ?>
        </section>

        <section class="panel">
            <h2>Event log</h2>
            <?php if ($state === null || $state['events_log'] === []): ?>
                <p class="small">No events yet. Land on a cell marked E.</p>
            <?php else: ?>
                <?php foreach (array_slice($state['events_log'], -5) as $entry): ?>
                    <p class="small">Turn <?= h($entry['turn']) ?>: <?= h($entry['summary']) ?></p>
                <?php endforeach; ?>
            <?php endif; ?>
            <p><a class="text-link" href="leaderboard.php">View Adventure Recap</a></p>
        </section>
    </div>
</div>
<script>
//start the token slide, the positions themselves come from PHP
requestAnimationFrame(function () {
    requestAnimationFrame(function () {
        document.querySelectorAll('.token-pending').forEach(function (token) {
            token.classList.remove('token-pending');
        });
    });
});
</script>
<?php require __DIR__ . '/partials/footer.php'; ?>
