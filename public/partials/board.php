<?php
#draws the 100 cell board, expects $boardData and optional $boardState
if (!function_exists('h')) {
    http_response_code(404);
    exit;
}
$boardState = $boardState ?? null;
$activeCell = null;
if ($boardState !== null && $boardState['winner'] === null) {
    $activeCell = $boardState['positions'][$boardState['current_turn']];
}
?>
<div class="board">
    <div class="board-grid">
        <?php foreach (board_draw_order() as $cell): ?>
            <?php
            $classes = 'cell';
            if (events_is_cell($cell)) {
                $classes .= ' cell-event';
            }
            if ($cell === $activeCell) {
                $classes .= ' cell-active';
            }
            ?>
            <div class="<?= h($classes) ?>">
                <span class="cell-number"><?= h($cell) ?></span>
                <?php if (events_is_cell($cell)): ?>
                    <span class="cell-mark">E</span>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <svg class="board-overlay" viewBox="0 0 100 100" aria-hidden="true">
        <?php foreach ($boardData['snakes'] as $head => $tail): ?>
            <?php
            $a = board_cell_center($head);
            $b = board_cell_center($tail);
            $dx = $b['x'] - $a['x'];
            $dy = $b['y'] - $a['y'];
            #bend the body sideways by a share of its length
            $cx = ($a['x'] + $b['x']) / 2 - $dy * 0.18;
            $cy = ($a['y'] + $b['y']) / 2 + $dx * 0.18;
            $path = sprintf('M%.1f %.1f Q%.1f %.1f %.1f %.1f', $a['x'], $a['y'], $cx, $cy, $b['x'], $b['y']);
            ?>
            <path class="snake-body" d="<?= h($path) ?>"/>
            <circle class="snake-head" cx="<?= h($a['x']) ?>" cy="<?= h($a['y']) ?>" r="1.2"/>
        <?php endforeach; ?>
        <?php foreach ($boardData['ladders'] as $base => $top): ?>
            <?php
            $a = board_cell_center($base);
            $b = board_cell_center($top);
            $dx = $b['x'] - $a['x'];
            $dy = $b['y'] - $a['y'];
            $length = sqrt($dx * $dx + $dy * $dy);
            #unit vector across the ladder, used to offset the two rails
            $nx = -$dy / $length * 1.2;
            $ny = $dx / $length * 1.2;
            $rungs = max(2, (int)floor($length / 4));
            ?>
            <line class="ladder" x1="<?= h(round($a['x'] + $nx, 1)) ?>" y1="<?= h(round($a['y'] + $ny, 1)) ?>" x2="<?= h(round($b['x'] + $nx, 1)) ?>" y2="<?= h(round($b['y'] + $ny, 1)) ?>"/>
            <line class="ladder" x1="<?= h(round($a['x'] - $nx, 1)) ?>" y1="<?= h(round($a['y'] - $ny, 1)) ?>" x2="<?= h(round($b['x'] - $nx, 1)) ?>" y2="<?= h(round($b['y'] - $ny, 1)) ?>"/>
            <?php for ($i = 1; $i <= $rungs; $i++): ?>
                <?php
                $t = $i / ($rungs + 1);
                $rx = $a['x'] + $dx * $t;
                $ry = $a['y'] + $dy * $t;
                ?>
                <line class="ladder" x1="<?= h(round($rx + $nx, 1)) ?>" y1="<?= h(round($ry + $ny, 1)) ?>" x2="<?= h(round($rx - $nx, 1)) ?>" y2="<?= h(round($ry - $ny, 1)) ?>"/>
            <?php endfor; ?>
        <?php endforeach; ?>
    </svg>
    <?php if ($boardState !== null): ?>
        <?php foreach ($boardState['positions'] as $index => $position): ?>
            <?php
            $from = board_cell_xy($boardState['prev_positions'][$index] ?? $position);
            $to = board_cell_xy($position);
            $style = sprintf(
                '--from: translate(%d%%, %d%%); --to: translate(%d%%, %d%%);',
                $from['col'] * 100,
                $from['row'] * 100,
                $to['col'] * 100,
                $to['row'] * 100
            );
            $moved = ($boardState['prev_positions'][$index] ?? $position) !== $position;
            ?>
            <div class="token token-<?= h($index + 1) ?><?= $moved ? ' token-pending' : '' ?>" style="<?= h($style) ?>">
                <span class="token-dot"><?= h($index + 1) ?></span>
                <span class="visually-hidden"><?= h($boardState['players'][$index]) ?> on cell <?= h($position) ?></span>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
