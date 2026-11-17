# Adventures of the Dice

CSC 4370/6370 Project 2. A PHP Snakes & Ladders game for two players in one browser. Players register, log in, pick a difficulty, roll the die on the server, trigger story events on special cells, and climb a shared leaderboard.

Live site on CODD: TODO add the link after deployment.

## Collaborators

Team reedmii:

- TODO name, role
- TODO name, role
- TODO name, role

## Repository naming rule

TODO: state the course naming rule. This repository is named `Adventures-of-the-dice-project-2-team-reedmii`, and the CODD folder must use the same name.

## Requirements

- PHP 8.2 or newer
- The `pdo_sqlite` extension

No frameworks, no Composer, and no build step.

## Set-up

1. Clone the repository.
2. Make sure `pdo_sqlite` is enabled. Run `php -m` and look for `pdo_sqlite`. If it is missing, copy `php.ini-development` to `php.ini` in your PHP folder, then uncomment `extension_dir = "ext"` and `extension=pdo_sqlite`.
3. Start the server from the repository root:

   ```
   php -S localhost:8000 -t public
   ```

4. Open http://localhost:8000, create an account, and log in.

The database file `data/game.sqlite` is created on the first request. The `data/` folder is gitignored. On a shared host, the web server user needs write access to `data/`.

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
| `src/` | `db.php` (SQLite), `auth.php` (sessions, CSRF, accounts), `board.php`, `game_engine.php`, `events.php` |
| `assets/css/style.css` | All styles |
| `tests/` | Simulation, smoke, and integration tests |
| `docs/design-rules.md` | Visual rules for every screen |
| `wireframes/`, `ui/` | Reference SVG screens |

## Tests

Run these from the repository root:

```
php tests/simulate.php
php tests/smoke.php
php tests/integration.php
```

- `simulate.php` checks the board arrays and plays 1000 full games. No game may stall, and no turn may end on a snake head or ladder base.
- `smoke.php` checks registration rules, login, leaderboard order, and that dice code appears only in `game_engine.php`.
- `integration.php` runs the real pages on a local server. It covers duplicate usernames, empty names, rolls from cells 95 to 100, reload mid-game, and logout mid-game.

`smoke.php` and `integration.php` need `pdo_sqlite`. They use a temporary database and never touch `data/`.

## Security

- Every form posts with a CSRF token, checked with `hash_equals()`.
- Every successful POST redirects (Post/Redirect/Get).
- Passwords are stored with `password_hash()` and checked with `password_verify()`.
- The session id is regenerated on login. Session cookies are httponly and SameSite=Lax.
- All queries use prepared statements, and all output is escaped with `htmlspecialchars()`.
