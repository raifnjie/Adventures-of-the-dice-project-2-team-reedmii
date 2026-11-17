<?php
#smoke test: accounts, JSON storage and the leaderboard against a throwaway data folder
declare(strict_types=1);

require_once __DIR__ . '/test_data_dir.php';

$dataDir = test_data_dir_create('smoke');

require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/game_engine.php';
require_once __DIR__ . '/../src/leaderboard.php';

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

try {
    #storage
    check('storage uses the DATA_DIR override', storage_path('users.json') === $dataDir . '/users.json');
    check('a missing file returns the default', read_json('missing.json', ['x']) === ['x']);
    write_json('sample.json', ['a' => 1]);
    check('write then read returns the same data', read_json('sample.json') === ['a' => 1]);
    file_put_contents(storage_path('broken.json'), '{not json');
    check('an invalid file returns the default', read_json('broken.json', []) === []);
    check('the data folder blocks web access', is_file($dataDir . '/.htaccess'));

    #accounts
    check('short username rejected', auth_register('ab', 'passw0rd1', 'passw0rd1') !== []);
    check('username with symbols rejected', auth_register('bad name!', 'passw0rd1', 'passw0rd1') !== []);
    check('password without a number rejected', auth_register('dice_ace', 'password', 'password') !== []);
    check('mismatched confirmation rejected', auth_register('dice_ace', 'passw0rd1', 'passw0rd2') !== []);
    check('valid registration accepted', auth_register('dice_ace', 'passw0rd1', 'passw0rd1') === []);
    check('duplicate username rejected', auth_register('dice_ace', 'passw0rd1', 'passw0rd1') !== []);
    check('duplicate in another letter case rejected', auth_register('DICE_ACE', 'passw0rd1', 'passw0rd1') !== []);

    $users = read_json('users.json');
    check('users.json is keyed by username', array_keys($users) === ['dice_ace']);
    check('password stored as a hash', $users['dice_ace']['hash'] !== 'passw0rd1' && password_verify('passw0rd1', $users['dice_ace']['hash']));
    check('account has a created date', strtotime((string)($users['dice_ace']['created'] ?? '')) !== false);
    check('login with the right password', auth_attempt('dice_ace', 'passw0rd1') !== null);
    check('login in another letter case', (auth_attempt('Dice_Ace', 'passw0rd1')['username'] ?? '') === 'dice_ace');
    check('login with the wrong password fails', auth_attempt('dice_ace', 'wrong') === null);
    check('login with an unknown user fails', auth_attempt('nobody', 'passw0rd1') === null);
    check('login with empty input fails', auth_attempt('', '') === null);
    check('an invalid username is never used as a key', auth_attempt('../users', 'x') === null);

    #leaderboard
    leaderboard_add(leaderboard_entry('slow', 'standard', 30, 100, []));
    leaderboard_add(leaderboard_entry('quick', 'beginner', 12, 90, []));
    leaderboard_add(leaderboard_entry('quicker', 'expert', 12, 50, [['turn' => 4, 'type' => 'bonus', 'cell' => 15]]));
    for ($i = 0; $i < 12; $i++) {
        leaderboard_add(leaderboard_entry('filler', 'standard', 40 + $i, 200, []));
    }
    $top = leaderboard_top(10);
    check('leaderboard returns the top 10', count($top) === 10);
    check('leaderboard sorts by rolls then time', $top[0]['username'] === 'quicker' && $top[1]['username'] === 'quick' && $top[2]['username'] === 'slow');
    check('leaderboard entry keeps its events', ($top[0]['events'][0]['cell'] ?? null) === 15);
    check('leaderboard.json keeps every entry', count(read_json('leaderboard.json')) === 15);
    $expectedKeys = ['username', 'difficulty', 'rolls', 'time_played', 'finished_at', 'events'];
    check('leaderboard entry has the agreed fields', array_keys($top[0]) === $expectedKeys);

    #game helpers
    check('same player names rejected', game_validate_names(['Ada', 'ada']) !== []);
    check('empty player name rejected', game_validate_names(['', 'Bo']) !== []);
    check('valid player names accepted', game_validate_names(['Ada', 'Bo']) === []);
    check('time formats as mm:ss', game_format_time(185) === '03:05');
    check('cell 1 is bottom left', board_cell_xy(1) === ['col' => 0, 'row' => 9]);
    check('cell 100 is top left', board_cell_xy(100) === ['col' => 0, 'row' => 0]);
    check('event pick repeats for the same turn and cell', events_pick(4, 15) === events_pick(4, 15));

    #source rules: no reseeding, dice only in the engine, no database code
    $srand = 's' . 'rand(';
    $dice = 'rand(1, 6)';
    $sources = array_merge(glob(__DIR__ . '/../src/*.php'), glob(__DIR__ . '/../public/*.php'), glob(__DIR__ . '/../public/partials/*.php'));
    $reseeds = 0;
    $strayDice = 0;
    $database = 0;
    foreach ($sources as $source) {
        $code = file_get_contents($source);
        if (preg_match('/(?<![a-z_])' . preg_quote($srand, '/') . '/i', $code)) {
            $reseeds++;
        }
        if (basename($source) !== 'game_engine.php' && str_contains($code, $dice)) {
            $strayDice++;
        }
        if (preg_match('/\bPDO\b|sqlite|db\.php/i', $code)) {
            $database++;
        }
    }
    check('no reseeding call in the code', $reseeds === 0);
    check('dice roll lives only in game_engine.php', $strayDice === 0);
    check('no database code is left', $database === 0 && !is_file(__DIR__ . '/../src/db.php'));
} finally {
    test_data_dir_remove($dataDir);
}

echo $failed === 0 ? 'PASS' . PHP_EOL : 'FAIL (' . $failed . ')' . PHP_EOL;
exit($failed === 0 ? 0 : 1);
