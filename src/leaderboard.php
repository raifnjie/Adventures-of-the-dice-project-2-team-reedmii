<?php
#leaderboard entries kept in data/leaderboard.json
declare(strict_types=1);

require_once __DIR__ . '/storage.php';

const LEADERBOARD_FILE = 'leaderboard.json';

#build one leaderboard entry for a finished game
function leaderboard_entry(string $username, string $difficulty, int $rolls, int $seconds, array $events): array
{
    return [
        'username' => $username,
        'difficulty' => $difficulty,
        'rolls' => $rolls,
        'time_played' => $seconds,
        'finished_at' => gmdate('c'),
        'events' => array_values($events),
    ];
}

#append one entry to the file
function leaderboard_add(array $entry): void
{
    update_json(LEADERBOARD_FILE, [], function (array $entries) use ($entry): array {
        $entries[] = $entry;
        return $entries;
    });
}

#fewest rolls first, then shortest time played, then the earliest finish
function leaderboard_compare(array $a, array $b): int
{
    return [(int)$a['rolls'], (int)$a['time_played'], (string)$a['finished_at']]
        <=> [(int)$b['rolls'], (int)$b['time_played'], (string)$b['finished_at']];
}

#the top entries in rank order
function leaderboard_top(int $limit = 10): array
{
    $entries = array_values(array_filter(
        read_json(LEADERBOARD_FILE, []),
        fn ($entry) => is_array($entry) && isset($entry['username'], $entry['difficulty'], $entry['rolls'], $entry['time_played'], $entry['finished_at'])
    ));
    usort($entries, 'leaderboard_compare');
    return array_slice($entries, 0, $limit);
}
