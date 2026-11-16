<?php
#board data: difficulty arrays and the geometry of the 10 by 10 grid
declare(strict_types=1);

const BOARD_SIZE = 100;
const BOARD_START = 1;

#snakes map head to tail, ladders map base to top
function board_difficulties(): array
{
    return [
        'beginner' => [
            'label' => 'Beginner',
            'snakes' => [95 => 56, 87 => 24, 62 => 19],
            'ladders' => [4 => 14, 9 => 31, 28 => 84],
        ],
        'standard' => [
            'label' => 'Standard',
            'snakes' => [98 => 78, 95 => 56, 87 => 24, 64 => 60, 62 => 19, 54 => 34],
            'ladders' => [2 => 38, 4 => 14, 9 => 31, 28 => 84, 36 => 44],
        ],
        'expert' => [
            'label' => 'Expert',
            'snakes' => [98 => 78, 95 => 56, 93 => 73, 87 => 24, 64 => 60, 62 => 19, 54 => 34, 49 => 11, 17 => 7],
            'ladders' => [4 => 14, 9 => 31, 28 => 84, 36 => 44],
        ],
    ];
}

#true when the key names a difficulty
function board_is_difficulty(string $key): bool
{
    return array_key_exists($key, board_difficulties());
}

#return the snakes and ladders for one difficulty
function board_get(string $difficulty): array
{
    $all = board_difficulties();
    return $all[$difficulty] ?? $all['standard'];
}

#short description such as "Standard: 6 snakes, 5 ladders"
function board_summary(string $difficulty): string
{
    $board = board_get($difficulty);
    return $board['label'] . ': ' . count($board['snakes']) . ' snakes, ' . count($board['ladders']) . ' ladders';
}

#apply a snake or ladder once, returns the kind (or null) and the new cell
function board_apply(array $board, int $cell): array
{
    if (isset($board['snakes'][$cell])) {
        return ['kind' => 'snake', 'to' => $board['snakes'][$cell]];
    }
    if (isset($board['ladders'][$cell])) {
        return ['kind' => 'ladder', 'to' => $board['ladders'][$cell]];
    }
    return ['kind' => null, 'to' => $cell];
}

#column and row of a cell counted from the top left, the path snakes upward from the bottom left
function board_cell_xy(int $cell): array
{
    $cell = max(1, min(BOARD_SIZE, $cell));
    $index = $cell - 1;
    $rowFromBottom = intdiv($index, 10);
    $col = $index % 10;
    if ($rowFromBottom % 2 === 1) {
        $col = 9 - $col;
    }
    return ['col' => $col, 'row' => 9 - $rowFromBottom];
}

#cell numbers in the order the grid draws them, top row first
function board_draw_order(): array
{
    $cells = [];
    for ($row = 9; $row >= 0; $row--) {
        for ($col = 0; $col < 10; $col++) {
            $offset = ($row % 2 === 0) ? $col : 9 - $col;
            $cells[] = $row * 10 + $offset + 1;
        }
    }
    return $cells;
}

#center of a cell in the 0 to 100 overlay coordinate space
function board_cell_center(int $cell): array
{
    $xy = board_cell_xy($cell);
    return ['x' => $xy['col'] * 10 + 5, 'y' => $xy['row'] * 10 + 5];
}
