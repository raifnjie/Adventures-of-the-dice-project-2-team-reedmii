<?php
#event cells and the AI Narrator, all text comes from PHP templates
declare(strict_types=1);

const EVENT_CELLS = [15, 42, 67];
const EVENT_TYPES = ['bonus', 'skip', 'warp'];
const EVENT_BONUS_STEPS = 5;
#warp targets are plain cells in every difficulty
const EVENT_WARP_TARGETS = [15 => 30, 42 => 22, 67 => 53];

#true when the cell triggers an event
function events_is_cell(int $cell): bool
{
    return in_array($cell, EVENT_CELLS, true);
}

#pick the event for this landing, repeatable for the same turn and cell
function events_pick(int $turn, int $cell): string
{
    return EVENT_TYPES[crc32("turn:$turn:$cell") % count(EVENT_TYPES)];
}

#work out what an event does: new cell, skip flag, and a log line
function events_apply(string $type, int $cell): array
{
    if ($type === 'bonus') {
        $to = $cell + EVENT_BONUS_STEPS;
        return ['to' => $to, 'skip' => false, 'summary' => 'Bonus on cell ' . $cell . ', moved up ' . EVENT_BONUS_STEPS];
    }
    if ($type === 'warp') {
        $to = EVENT_WARP_TARGETS[$cell] ?? $cell;
        return ['to' => $to, 'skip' => false, 'summary' => 'Warp on cell ' . $cell . ', sent to cell ' . $to];
    }
    return ['to' => $cell, 'skip' => true, 'summary' => 'Skip on cell ' . $cell . ', turn lost'];
}

#choose one template from a list, repeatable for the same key
function narrator_choose(array $templates, string $key): string
{
    return $templates[crc32($key) % count($templates)];
}

#fill the {name} style placeholders in a template
function narrator_fill(string $template, array $values): string
{
    $pairs = [];
    foreach ($values as $name => $value) {
        $pairs['{' . $name . '}'] = (string)$value;
    }
    return strtr($template, $pairs);
}

#narrator line for a plain roll
function narrate_roll(string $player, int $roll, int $cell, int $turn): string
{
    $templates = [
        '{player} rolls a {roll} and marches to cell {cell}.',
        'The die shows {roll}. {player} steps onto cell {cell}.',
        '{player} throws a {roll} and reaches cell {cell}.',
    ];
    return narrator_fill(narrator_choose($templates, "roll:$turn"), ['player' => $player, 'roll' => $roll, 'cell' => $cell]);
}

#narrator lines for a snake
function narrate_snake(string $player, int $from, int $to, int $turn): array
{
    $templates = [
        'A serpent coils around cell {from}.',
        'Something hisses beneath cell {from}.',
        'The floor of cell {from} gives way to scales.',
    ];
    return [
        narrator_fill(narrator_choose($templates, "snake:$turn"), ['from' => $from]),
        narrator_fill('{player} slides down to cell {to}.', ['player' => $player, 'to' => $to]),
    ];
}

#narrator lines for a ladder
function narrate_ladder(string $player, int $from, int $to, int $turn): array
{
    $templates = [
        'A ladder leans against cell {from}.',
        'Rungs rise out of the mist on cell {from}.',
        'Cell {from} hides a sturdy ladder.',
    ];
    return [
        narrator_fill(narrator_choose($templates, "ladder:$turn"), ['from' => $from]),
        narrator_fill('{player} climbs up to cell {to}.', ['player' => $player, 'to' => $to]),
    ];
}

#narrator line for an event cell
function narrate_event(string $type, string $player, int $cell, int $to): string
{
    $templates = [
        'bonus' => 'Cell {cell} reveals a shortcut. {player} moves up to cell {to}.',
        'skip' => 'A fog settles over cell {cell}. {player} will lose the next turn.',
        'warp' => 'Cell {cell} bends the path. {player} is warped to cell {to}.',
    ];
    return narrator_fill($templates[$type], ['player' => $player, 'cell' => $cell, 'to' => $to]);
}

#narrator line for a skipped turn
function narrate_skipped(string $player): string
{
    return narrator_fill('{player} waits out the fog and misses this turn.', ['player' => $player]);
}

#narrator line for the win
function narrate_win(string $player, int $rolls): string
{
    return narrator_fill('{player} reaches cell 100 in {rolls} rolls. The adventure is complete.', ['player' => $player, 'rolls' => $rolls]);
}

#recap text for one logged event
function events_recap_text(array $entry): string
{
    if ($entry['type'] === 'bonus') {
        return 'Found a shortcut and moved up ' . EVENT_BONUS_STEPS . ' cells';
    }
    if ($entry['type'] === 'warp') {
        return 'Warped to cell ' . $entry['to'];
    }
    return 'Lost the next turn';
}
