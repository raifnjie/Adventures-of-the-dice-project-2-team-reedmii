<?php
#smoke test: accounts and the leaderboard against a throwaway database
declare(strict_types=1);

$file = sys_get_temp_dir() . '/aod_smoke_' . bin2hex(random_bytes(4)) . '.sqlite';
putenv('AOD_DB=' . $file);

require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/game_engine.php';

$failed = 0;

#print one check result
function check(string $label, bool $ok): void
{
    global $failed;
    if (!$ok) {
        $failed++;
    }
    echo ($ok ? 'ok   ' : 'FAIL ') . $label . PHP_EOL;
}

$pdo = db();

check('short username rejected', auth_register($pdo, 'ab', 'passw0rd1', 'passw0rd1') !== []);
check('username with symbols rejected', auth_register($pdo, 'bad name!', 'passw0rd1', 'passw0rd1') !== []);
check('password without a number rejected', auth_register($pdo, 'dice_ace', 'password', 'password') !== []);
check('mismatched confirmation rejected', auth_register($pdo, 'dice_ace', 'passw0rd1', 'passw0rd2') !== []);
check('valid registration accepted', auth_register($pdo, 'dice_ace', 'passw0rd1', 'passw0rd1') === []);
check('duplicate username rejected', auth_register($pdo, 'DICE_ACE', 'passw0rd1', 'passw0rd1') !== []);

$row = db_find_user($pdo, 'dice_ace');
check('password stored as a hash', $row !== null && $row['password_hash'] !== 'passw0rd1' && password_verify('passw0rd1', $row['password_hash']));
check('login with the right password', auth_attempt($pdo, 'dice_ace', 'passw0rd1') !== null);
check('login with the wrong password fails', auth_attempt($pdo, 'dice_ace', 'wrong') === null);
check('login with an unknown user fails', auth_attempt($pdo, 'nobody', 'passw0rd1') === null);
check('login with empty input fails', auth_attempt($pdo, '', '') === null);
check('injection text is treated as data', auth_attempt($pdo, "' OR 1=1 --", 'x') === null);

$userId = (int)$row['id'];
db_save_score($pdo, $userId, 'slow', 'standard', 30, 100);
db_save_score($pdo, $userId, 'quick', 'beginner', 12, 90);
db_save_score($pdo, $userId, 'quicker', 'expert', 12, 50);
for ($i = 0; $i < 12; $i++) {
    db_save_score($pdo, $userId, 'filler', 'standard', 40 + $i, 200);
}
$top = db_top_scores($pdo, 10);
check('leaderboard returns the top 10', count($top) === 10);
check('leaderboard sorts by rolls then time', $top[0]['player_name'] === 'quicker' && $top[1]['player_name'] === 'quick' && $top[2]['player_name'] === 'slow');
db_save_score($pdo, $userId, 'storyteller', 'standard', 99, 99, [['turn' => 4, 'type' => 'bonus', 'cell' => 15]]);
$saved = $pdo->query("SELECT events_json FROM scores WHERE player_name = 'storyteller'")->fetchColumn();
check('event log is saved as JSON on the score row', json_decode((string)$saved, true)[0]['cell'] === 15);

check('same player names rejected', game_validate_names(['Ada', 'ada']) !== []);
check('empty player name rejected', game_validate_names(['', 'Bo']) !== []);
check('valid player names accepted', game_validate_names(['Ada', 'Bo']) === []);
check('time formats as mm:ss', game_format_time(185) === '03:05');
check('cell 1 is bottom left', board_cell_xy(1) === ['col' => 0, 'row' => 9]);
check('cell 100 is top left', board_cell_xy(100) === ['col' => 0, 'row' => 0]);
check('event pick repeats for the same turn and cell', events_pick(4, 15) === events_pick(4, 15));

#the source must never reseed the generator or roll outside the engine
$srand = 's' . 'rand(';
$dice = 'rand(1, 6)';
$sources = array_merge(glob(__DIR__ . '/../src/*.php'), glob(__DIR__ . '/../public/*.php'), glob(__DIR__ . '/../public/partials/*.php'));
$reseeds = 0;
$strayDice = 0;
foreach ($sources as $source) {
    $code = file_get_contents($source);
    if (preg_match('/(?<![a-z_])' . preg_quote($srand, '/') . '/i', $code)) {
        $reseeds++;
    }
    if (basename($source) !== 'game_engine.php' && str_contains($code, $dice)) {
        $strayDice++;
    }
}
check('no reseeding call in the code', $reseeds === 0);
check('dice roll lives only in game_engine.php', $strayDice === 0);

$pdo = null;
@unlink($file);

echo $failed === 0 ? 'PASS' . PHP_EOL : 'FAIL (' . $failed . ')' . PHP_EOL;
exit($failed === 0 ? 0 : 1);
