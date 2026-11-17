<?php
#game rules: state, dice, movement, turns, and the session bridge
declare(strict_types=1);

require_once __DIR__ . '/board.php';
require_once __DIR__ . '/events.php';

#session keys that belong to one game, cleared by Play again
const GAME_KEYS = [
    'difficulty', 'players', 'positions', 'prev_positions', 'current_turn', 'turns_taken',
    'rolls', 'roll_history', 'skip', 'events_log', 'last_event', 'last_roll', 'last_move',
    'winner', 'started_at', 'finished_at', 'score_saved',
];

#validate the two player names, returns error messages
function game_validate_names(array $names): array
{
    $errors = [];
    foreach ($names as $index => $name) {
        if (!preg_match('/^[A-Za-z0-9_ ]{1,16}$/', $name) || trim($name) === '') {
            $errors[] = 'Player ' . ($index + 1) . ' name must be 1 to 16 letters, digits, spaces, or underscores.';
        }
    }
    if ($errors === [] && strcasecmp($names[0], $names[1]) === 0) {
        $errors[] = 'The two players need different names.';
    }
    return $errors;
}

#build a fresh game state
function game_new(string $difficulty, array $names): array
{
    return [
        'difficulty' => $difficulty,
        'players' => array_values($names),
        'positions' => [BOARD_START, BOARD_START],
        'prev_positions' => [BOARD_START, BOARD_START],
        'current_turn' => 0,
        'turns_taken' => 0,
        'rolls' => [0, 0],
        'roll_history' => [],
        'skip' => [false, false],
        'events_log' => [],
        'last_event' => ['The board is set. ' . $names[0] . ' rolls first.'],
        'last_roll' => null,
        'last_move' => null,
        'winner' => null,
        'started_at' => time(),
        'finished_at' => null,
        'score_saved' => false,
    ];
}

#play one turn for the current player and return the new state
function take_turn(array $state): array
{
    if ($state['winner'] !== null) {
        return $state;
    }
    $board = board_get($state['difficulty']);
    $player = $state['current_turn'];
    $name = $state['players'][$player];
    $state['turns_taken']++;
    $turn = $state['turns_taken'];
    $state['prev_positions'] = $state['positions'];

    #roll the die
    $roll = rand(1, 6);
    $state['rolls'][$player]++;
    $state['last_roll'] = $roll;
    array_unshift($state['roll_history'], $roll);

    $cell = $state['positions'][$player] + $roll;
    $lines = [];

    #reach-or-exceed: 100 or more wins immediately
    if ($cell >= BOARD_SIZE) {
        return game_finish($state, $player, $roll);
    }
    $lines[] = narrate_roll($name, $roll, $cell, $turn);

    #snake or ladder on the landing cell
    $cell = game_apply_hazard($board, $cell, $name, $turn, $lines);

    #event cell, at most one per turn
    if (events_is_cell($cell)) {
        $type = events_pick($turn, $cell);
        $effect = events_apply($type, $cell);
        $lines[] = narrate_event($type, $name, $cell, $effect['to']);
        $state['events_log'][] = [
            'turn' => $turn,
            'player' => $name,
            'type' => $type,
            'cell' => $cell,
            'to' => $effect['to'],
            'summary' => $effect['summary'],
        ];
        if ($effect['skip']) {
            $state['skip'][$player] = true;
        }
        if ($effect['to'] !== $cell) {
            #an event move is a new landing, so a snake or ladder there applies once
            $cell = game_apply_hazard($board, $effect['to'], $name, $turn, $lines);
        }
    }

    if ($cell >= BOARD_SIZE) {
        return game_finish($state, $player, $roll);
    }

    $state['positions'][$player] = $cell;
    $state['last_move'] = $name . ' landed on cell ' . $cell;
    $state['last_event'] = $lines;
    return game_advance_turn($state);
}

#apply a snake or ladder once and add the narrator lines
function game_apply_hazard(array $board, int $cell, string $name, int $turn, array &$lines): int
{
    $hazard = board_apply($board, $cell);
    if ($hazard['kind'] === 'snake') {
        array_push($lines, ...narrate_snake($name, $cell, $hazard['to'], $turn));
    }
    if ($hazard['kind'] === 'ladder') {
        array_push($lines, ...narrate_ladder($name, $cell, $hazard['to'], $turn));
    }
    return $hazard['to'];
}

#record the win
function game_finish(array $state, int $player, int $roll): array
{
    $name = $state['players'][$player];
    $state['positions'][$player] = BOARD_SIZE;
    $state['winner'] = $player;
    $state['finished_at'] = time();
    $state['last_move'] = $name . ' reached cell ' . BOARD_SIZE;
    $state['last_event'] = [
        $name . ' rolls a ' . $roll . '.',
        narrate_win($name, $state['rolls'][$player]),
    ];
    return $state;
}

#pass the turn, honoring any pending skip
function game_advance_turn(array $state): array
{
    $next = 1 - $state['current_turn'];
    if ($state['skip'][$next]) {
        $state['skip'][$next] = false;
        $state['last_event'][] = narrate_skipped($state['players'][$next]);
        $next = 1 - $next;
    }
    $state['current_turn'] = $next;
    return $state;
}

#seconds between the start and the end (or now)
function game_time_played(array $state): int
{
    $end = $state['finished_at'] ?? time();
    return max(0, (int)$end - (int)$state['started_at']);
}

#format seconds as mm:ss
function game_format_time(int $seconds): string
{
    return sprintf('%02d:%02d', intdiv($seconds, 60), $seconds % 60);
}

#read the game state from the session, or null when no game exists
function game_load(): ?array
{
    if (!isset($_SESSION['positions'], $_SESSION['players'], $_SESSION['difficulty'])) {
        return null;
    }
    $state = [];
    foreach (GAME_KEYS as $key) {
        $state[$key] = $_SESSION[$key] ?? null;
    }
    return $state;
}

#write the game state to the session
function game_save(array $state): void
{
    foreach (GAME_KEYS as $key) {
        $_SESSION[$key] = $state[$key] ?? null;
    }
}

#clear only the game keys, the login stays
function game_clear(): void
{
    foreach (GAME_KEYS as $key) {
        unset($_SESSION[$key]);
    }
}
