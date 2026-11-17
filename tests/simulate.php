<?php
#simulation test: checks the board arrays, then plays 1000 full games
declare(strict_types=1);

require_once __DIR__ . '/../src/game_engine.php';

const SIM_GAMES = 1000;
const SIM_MAX_TURNS = 5000;

$failures = [];

#record a failed check
function sim_fail(array &$failures, string $message): void
{
    if (count($failures) < 20) {
        $failures[] = $message;
    }
}

#board checks
$expected = ['beginner' => [3, 3], 'standard' => [6, 5], 'expert' => [9, 4]];
foreach (board_difficulties() as $key => $board) {
    $snakes = $board['snakes'];
    $ladders = $board['ladders'];
    if ([count($snakes), count($ladders)] !== $expected[$key]) {
        sim_fail($failures, "$key: wrong snake or ladder count");
    }
    if (array_intersect_key($snakes, $ladders) !== []) {
        sim_fail($failures, "$key: a cell is both a snake head and a ladder base");
    }
    $starts = array_merge(array_keys($snakes), array_keys($ladders));
    $ends = array_merge(array_values($snakes), array_values($ladders));
    foreach (array_merge($starts, $ends) as $cell) {
        if ($cell <= 1 || $cell >= 100) {
            sim_fail($failures, "$key: cell $cell is out of range");
        }
    }
    foreach ($snakes as $head => $tail) {
        if ($tail >= $head) {
            sim_fail($failures, "$key: snake $head does not go down");
        }
    }
    foreach ($ladders as $base => $top) {
        if ($top <= $base) {
            sim_fail($failures, "$key: ladder $base does not go up");
        }
    }
    #a snake tail or ladder top must be a plain cell, so nothing chains
    foreach ($ends as $cell) {
        if (in_array($cell, $starts, true) || events_is_cell($cell)) {
            sim_fail($failures, "$key: destination $cell is not a plain cell");
        }
    }
    foreach (EVENT_CELLS as $cell) {
        if (in_array($cell, $starts, true)) {
            sim_fail($failures, "$key: event cell $cell holds a snake or ladder");
        }
    }
}

#full games
$difficulties = array_keys(board_difficulties());
$totalTurns = 0;
$longest = 0;
$eventCounts = ['bonus' => 0, 'skip' => 0, 'warp' => 0];
$rollCounts = array_fill(1, 6, 0);

for ($game = 0; $game < SIM_GAMES; $game++) {
    $difficulty = $difficulties[$game % count($difficulties)];
    $board = board_get($difficulty);
    $state = game_new($difficulty, ['Ada', 'Bo']);

    while ($state['winner'] === null && $state['turns_taken'] < SIM_MAX_TURNS) {
        $mover = $state['current_turn'];
        $state = take_turn($state);
        $roll = $state['last_roll'];
        if (!is_int($roll) || $roll < 1 || $roll > 6) {
            sim_fail($failures, "game $game: roll out of range");
            break;
        }
        $rollCounts[$roll]++;
        $cell = $state['positions'][$mover];
        if ($cell < 1 || $cell > 100) {
            sim_fail($failures, "game $game: position $cell is off the board");
        }
        if (isset($board['snakes'][$cell]) || isset($board['ladders'][$cell])) {
            sim_fail($failures, "game $game: turn ended on snake head or ladder base $cell");
        }
    }

    if ($state['winner'] === null) {
        sim_fail($failures, "game $game: did not finish in " . SIM_MAX_TURNS . ' turns');
        continue;
    }
    if ($state['positions'][$state['winner']] !== 100) {
        sim_fail($failures, "game $game: winner is not on cell 100");
    }
    if (count($state['roll_history']) !== $state['turns_taken']) {
        sim_fail($failures, "game $game: roll history does not match the turn count");
    }
    foreach ($state['events_log'] as $entry) {
        $eventCounts[$entry['type']]++;
    }
    $totalTurns += $state['turns_taken'];
    $longest = max($longest, $state['turns_taken']);
}

echo 'Games played: ' . SIM_GAMES . PHP_EOL;
echo 'Average turns: ' . round($totalTurns / SIM_GAMES, 1) . ', longest: ' . $longest . PHP_EOL;
echo 'Rolls by face: ' . implode(' ', $rollCounts) . PHP_EOL;
echo 'Events: bonus ' . $eventCounts['bonus'] . ', skip ' . $eventCounts['skip'] . ', warp ' . $eventCounts['warp'] . PHP_EOL;

if ($failures !== []) {
    echo 'FAIL' . PHP_EOL;
    foreach ($failures as $failure) {
        echo ' - ' . $failure . PHP_EOL;
    }
    exit(1);
}
echo 'PASS' . PHP_EOL;
