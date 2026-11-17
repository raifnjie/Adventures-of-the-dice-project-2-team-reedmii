<?php
#integration test: runs the real pages on a local server against a throwaway data folder
#covers duplicate usernames, empty names, rolls at cells 95 to 100, reload mid-game, logout mid-game
declare(strict_types=1);

require_once __DIR__ . '/test_data_dir.php';
require_once __DIR__ . '/../src/game_engine.php';
require_once __DIR__ . '/../src/storage.php';

const IT_PORT = 8790;
const IT_BASE = 'http://127.0.0.1:' . IT_PORT;

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

#send one request, keep cookies in $jar, never follow redirects
function http(string $method, string $path, array &$jar, array $fields = []): array
{
    $headers = [];
    if ($jar !== []) {
        $pairs = [];
        foreach ($jar as $name => $value) {
            $pairs[] = $name . '=' . $value;
        }
        $headers[] = 'Cookie: ' . implode('; ', $pairs);
    }
    $options = ['method' => $method, 'follow_location' => 0, 'ignore_errors' => true, 'timeout' => 10];
    if ($method === 'POST') {
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        $options['content'] = http_build_query($fields);
    }
    $options['header'] = implode("\r\n", $headers);
    $body = file_get_contents(IT_BASE . '/' . $path, false, stream_context_create(['http' => $options]));
    $responseHeaders = $http_response_header ?? [];
    $status = 0;
    $location = '';
    foreach ($responseHeaders as $line) {
        if (preg_match('#^HTTP/\S+ (\d{3})#', $line, $m)) {
            $status = (int)$m[1];
        }
        if (stripos($line, 'Location:') === 0) {
            $location = trim(substr($line, 9));
        }
        if (preg_match('/^Set-Cookie: ([^=]+)=([^;]*)/i', $line, $m)) {
            if ($m[2] === '' || $m[2] === 'deleted') {
                unset($jar[$m[1]]);
            } else {
                $jar[$m[1]] = $m[2];
            }
        }
    }
    return ['status' => $status, 'location' => $location, 'body' => (string)$body];
}

#read the CSRF token from a page
function token_from(string $html): string
{
    return preg_match('/name="csrf_token" value="([^"]+)"/', $html, $m) ? $m[1] : '';
}

#read one value from the Players panel, such as the turn count
function turns_from(string $html): string
{
    return preg_match('/Turns taken: (\d+)/', $html, $m) ? $m[1] : '';
}

#read the roll history line
function history_from(string $html): string
{
    return preg_match('/<p class="roll-history">(.*?)<\/p>/s', $html, $m) ? preg_replace('/\s+/', ' ', strip_tags($m[1])) : '';
}

#engine edge cases: rolls from cells 95 to 99 on every difficulty
foreach (array_keys(board_difficulties()) as $difficulty) {
    $board = board_get($difficulty);
    $bad = 0;
    for ($start = 95; $start <= 99; $start++) {
        for ($i = 0; $i < 300; $i++) {
            $state = game_new($difficulty, ['Ada', 'Bo']);
            $state['positions'][0] = $start;
            $state = take_turn($state);
            $cell = $state['positions'][0];
            $roll = $state['last_roll'];
            if ($start + $roll >= 100) {
                #reach-or-exceed: the roll wins and the token sits on 100
                $bad += ($state['winner'] === 0 && $cell === 100) ? 0 : 1;
            } else {
                #below 100 the game goes on and the turn never ends on a snake head or ladder base
                $bad += ($state['winner'] === null && $cell < 100 && !isset($board['snakes'][$cell]) && !isset($board['ladders'][$cell])) ? 0 : 1;
            }
        }
    }
    check("$difficulty: rolls from cells 95 to 99 follow reach-or-exceed", $bad === 0);
}

#start the server on a fresh data folder
$dataDir = test_data_dir_create('it');
$command = [PHP_BINARY, '-S', '127.0.0.1:' . IT_PORT, '-t', dirname(__DIR__) . '/public'];
$env = getenv();
$env['DATA_DIR'] = $dataDir;
$null = DIRECTORY_SEPARATOR === '\\' ? 'NUL' : '/dev/null';
$server = proc_open($command, [0 => ['file', $null, 'r'], 1 => ['file', $null, 'w'], 2 => ['file', $null, 'w']], $pipes, null, $env);
if (!is_resource($server)) {
    echo 'FAIL could not start the PHP server' . PHP_EOL;
    exit(1);
}

#wait for the server to answer
$ready = false;
for ($i = 0; $i < 50 && !$ready; $i++) {
    usleep(100000);
    $socket = @fsockopen('127.0.0.1', IT_PORT, $errno, $errstr, 0.2);
    if ($socket !== false) {
        fclose($socket);
        $ready = true;
    }
}

try {
    check('server started', $ready);
    if (!$ready) {
        throw new RuntimeException('server did not start');
    }

    $jar = [];
    $page = http('GET', 'register.php', $jar);
    $token = token_from($page['body']);
    $account = ['username' => 'it_user', 'password' => 'passw0rd1', 'confirm' => 'passw0rd1'];

    #duplicate usernames
    $first = http('POST', 'register.php', $jar, $account + ['csrf_token' => $token]);
    check('register redirects to login', $first['status'] === 302 && str_ends_with($first['location'], 'login.php'));
    $second = http('POST', 'register.php', $jar, ['username' => 'IT_USER'] + $account + ['csrf_token' => $token]);
    check('duplicate username is rejected, any letter case', $second['status'] === 200 && str_contains($second['body'], 'already taken'));

    #log in
    $page = http('GET', 'login.php', $jar);
    $token = token_from($page['body']);
    $login = http('POST', 'login.php', $jar, ['username' => 'it_user', 'password' => 'passw0rd1', 'csrf_token' => $token]);
    check('login redirects to the game', $login['status'] === 302 && str_ends_with($login['location'], 'game.php'));

    #empty player names
    $page = http('GET', 'game.php', $jar);
    $token = token_from($page['body']);
    $start = ['action' => 'start', 'difficulty' => 'standard', 'csrf_token' => $token];
    http('POST', 'game.php', $jar, $start + ['player1' => '', 'player2' => '   ']);
    $page = http('GET', 'game.php', $jar);
    check('empty player names are rejected', str_contains($page['body'], 'Player 1 name must be') && str_contains($page['body'], 'Start game'));

    #reload mid-game
    http('POST', 'game.php', $jar, $start + ['player1' => 'Ada', 'player2' => 'Bo']);
    for ($i = 0; $i < 3; $i++) {
        http('POST', 'game.php', $jar, ['action' => 'roll', 'csrf_token' => $token]);
    }
    $before = http('GET', 'game.php', $jar)['body'];
    $after = http('GET', 'game.php', $jar)['body'];
    check('three rolls are counted', turns_from($before) === '3');
    check('reload keeps the turn count', turns_from($before) !== '' && turns_from($before) === turns_from($after));
    check('reload keeps the roll history', history_from($before) !== '' && history_from($before) === history_from($after));

    #a roll without the token is refused and changes nothing
    $forged = http('POST', 'game.php', $jar, ['action' => 'roll']);
    check('roll without a CSRF token is refused', $forged['status'] === 400);
    check('refused roll leaves the game alone', turns_from(http('GET', 'game.php', $jar)['body']) === '3');

    #logout mid-game
    $logout = http('POST', 'logout.php', $jar, ['csrf_token' => $token]);
    check('logout redirects to the landing page', $logout['status'] === 302 && str_ends_with($logout['location'], 'index.php'));
    $guard = http('GET', 'game.php', $jar);
    check('game page is closed after logout', $guard['status'] === 302 && str_ends_with($guard['location'], 'login.php'));
    $guard = http('GET', 'leaderboard.php', $jar);
    check('leaderboard is closed after logout', $guard['status'] === 302 && str_ends_with($guard['location'], 'login.php'));

    #log back in: the old game is gone, then play a new one to the end
    $page = http('GET', 'login.php', $jar);
    $token = token_from($page['body']);
    http('POST', 'login.php', $jar, ['username' => 'it_user', 'password' => 'passw0rd1', 'csrf_token' => $token]);
    $page = http('GET', 'game.php', $jar);
    check('a new login starts with the setup form', str_contains($page['body'], 'Start game'));
    $token = token_from($page['body']);
    http('POST', 'game.php', $jar, ['action' => 'start', 'difficulty' => 'beginner', 'player1' => 'Ada', 'player2' => 'Bo', 'csrf_token' => $token]);
    $won = false;
    for ($i = 0; $i < 2000 && !$won; $i++) {
        $roll = http('POST', 'game.php', $jar, ['action' => 'roll', 'csrf_token' => $token]);
        $won = str_ends_with($roll['location'], 'leaderboard.php');
    }
    check('a full game ends on the leaderboard', $won);
    $board = http('GET', 'leaderboard.php', $jar)['body'];
    check('leaderboard shows the winner', str_contains($board, 'Winner:'));
    $extra = http('POST', 'game.php', $jar, ['action' => 'roll', 'csrf_token' => $token]);
    check('rolling after the win is ignored', $extra['status'] === 302 && str_ends_with($extra['location'], 'game.php'));
} catch (Throwable $e) {
    check('integration run: ' . $e->getMessage(), false);
} finally {
    proc_terminate($server);
    proc_close($server);
}

#the files the server wrote
$users = read_json('users.json');
check('users.json holds the one account', array_keys($users) === ['it_user']);
$entries = read_json('leaderboard.json');
check('exactly one leaderboard entry saved for the win', count($entries) === 1);
check('leaderboard entry holds the event log', isset($entries[0]['events']) && is_array($entries[0]['events']));

test_data_dir_remove($dataDir);
check('temp data folder removed', !is_dir($dataDir));
echo $failed === 0 ? 'PASS' . PHP_EOL : 'FAIL (' . $failed . ')' . PHP_EOL;
exit($failed === 0 ? 0 : 1);
