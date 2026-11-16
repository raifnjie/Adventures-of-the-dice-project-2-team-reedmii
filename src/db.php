<?php
#database access: one SQLite file reached through PDO
declare(strict_types=1);

#return the path of the SQLite file, AOD_DB overrides it for tests
function db_path(): string
{
    $override = getenv('AOD_DB');
    if (is_string($override) && $override !== '') {
        return $override;
    }
    return dirname(__DIR__) . '/data/game.sqlite';
}

#open the shared connection and make sure the tables exist
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
        throw new RuntimeException('The pdo_sqlite extension is not enabled in php.ini.');
    }
    $path = db_path();
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $pdo = new PDO('sqlite:' . $path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    db_migrate($pdo);
    return $pdo;
}

#create the tables on first run
function db_migrate(PDO $pdo): void
{
    $users = $pdo->prepare(
        'CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE COLLATE NOCASE,
            password_hash TEXT NOT NULL,
            created_at TEXT NOT NULL
        )'
    );
    $users->execute();
    $scores = $pdo->prepare(
        'CREATE TABLE IF NOT EXISTS scores (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL REFERENCES users(id),
            player_name TEXT NOT NULL,
            difficulty TEXT NOT NULL,
            rolls INTEGER NOT NULL,
            time_played INTEGER NOT NULL,
            created_at TEXT NOT NULL
        )'
    );
    $scores->execute();
}

#find one user row by name, or null
function db_find_user(PDO $pdo, string $username): ?array
{
    $stmt = $pdo->prepare('SELECT id, username, password_hash FROM users WHERE username = :username');
    $stmt->execute([':username' => $username]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

#insert a user and return the new id
function db_create_user(PDO $pdo, string $username, string $hash): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO users (username, password_hash, created_at) VALUES (:username, :hash, :created)'
    );
    $stmt->execute([
        ':username' => $username,
        ':hash' => $hash,
        ':created' => gmdate('Y-m-d H:i:s'),
    ]);
    return (int)$pdo->lastInsertId();
}

#store one finished game for the leaderboard
function db_save_score(PDO $pdo, int $userId, string $playerName, string $difficulty, int $rolls, int $seconds): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO scores (user_id, player_name, difficulty, rolls, time_played, created_at)
         VALUES (:user_id, :player_name, :difficulty, :rolls, :time_played, :created)'
    );
    $stmt->execute([
        ':user_id' => $userId,
        ':player_name' => $playerName,
        ':difficulty' => $difficulty,
        ':rolls' => $rolls,
        ':time_played' => $seconds,
        ':created' => gmdate('Y-m-d H:i:s'),
    ]);
}

#top scores across all users: fewest rolls first, then shortest time
function db_top_scores(PDO $pdo, int $limit = 10): array
{
    $stmt = $pdo->prepare(
        'SELECT player_name, difficulty, rolls, time_played
         FROM scores
         ORDER BY rolls ASC, time_played ASC, id ASC
         LIMIT :limit'
    );
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}
