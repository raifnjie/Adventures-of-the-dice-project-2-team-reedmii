# Adventures of the Dice

CSC 4370/6370 Project 2. A PHP Snakes & Ladders game for two players in one browser. Players register, log in, pick a difficulty, roll the die on the server, trigger story events on special cells, and climb a shared leaderboard.

## Storage

There is no database. Data lives in flat JSON files in `data/`, and JSON support is built into PHP.

- `data/users.json`: accounts keyed by username, each with a password hash and a created date.
- `data/leaderboard.json`: one entry per finished game with the username, difficulty, rolls, time played, finished time, and event log.

`src/storage.php` reads with a shared lock and writes with an exclusive lock (`flock`). Each finished game is also appended to `$_SESSION['scores']`. The leaderboard page reads `data/leaderboard.json`.

PHP creates `data/` on the first write, with a `.htaccess` file that blocks web access on Apache. The folder is gitignored.

### Writable data folder on CODD

PHP must be able to write to `data/`. After uploading, run this from the project folder:

```
mkdir -p data
chmod 755 data
```

If registration then fails with "Cannot open users.json", PHP runs as a different user from your account. Use `chmod 775 data` and give the folder to the web server's group, or ask the CODD admins. Avoid `777`.

If the whole project folder is public, check that `data/users.json` does not load in a browser.

## How to play

1. Choose a difficulty: Beginner (3 snakes, 3 ladders), Standard (6 and 5), or Expert (9 and 4).
2. Enter two player names and start the game.
3. Players take turns pressing Roll die. The roll happens in PHP with `rand(1, 6)`.
4. A snake head sends you down, a ladder base lifts you up. Each applies once per landing.
5. Cells 15, 42 and 67, marked E, trigger an event: a bonus of 5 cells, a skipped next turn, or a warp. The AI Narrator describes each move.
6. Reaching or passing cell 100 wins. The result goes to the leaderboard, ranked by fewest rolls and then shortest time.

A page reload keeps positions, the current turn, and the roll history, because the whole game lives in the PHP session.

## Project layout

| Path | Contents |
|---|---|
| `public/` | Pages: index, register, login, game, leaderboard, logout, and shared partials |
| `src/` | `storage.php` (JSON files), `auth.php` (sessions, CSRF, accounts), `leaderboard.php`, `board.php`, `game_engine.php`, `events.php` |
| `data/` | `users.json` and `leaderboard.json`, created at run time and gitignored |
| `assets/css/style.css` | All styles |
| `tests/` | Simulation, smoke, and integration tests |
| `docs/design-rules.md` | Visual rules for every screen |
| `wireframes/`, `ui/` | Reference SVG screens |

## Tests

```
php tests/simulate.php
php tests/smoke.php
php tests/integration.php
```

- `simulate.php` checks the board arrays and plays 1000 full games. No game may stall, and no turn may end on a snake head or ladder base.
- `smoke.php` checks registration rules, login, leaderboard order, and that dice code appears only in `game_engine.php`.
- `integration.php` runs the real pages on a local server. It covers duplicate usernames, empty names, rolls from cells 95 to 100, reload mid-game, and logout mid-game.

`smoke.php` and `integration.php` point `DATA_DIR` at a fresh temporary folder, delete its JSON files when they finish, and never touch `data/`.

## Security
- Every form posts with a CSRF token, checked with `hash_equals()`.
- Every successful POST redirects (Post/Redirect/Get).
- Passwords are stored with `password_hash()` and checked with `password_verify()`.
- The session id is regenerated on login. Session cookies are httponly and SameSite=Lax.
- A username is checked against `/^[A-Za-z0-9_]{3,16}$/` before it is used as a key in `users.json`, and all output is escaped with `htmlspecialchars()`.
