Adventures of the Dice

CSC 4370/6370 Project 2. A PHP Snakes & Ladders game for two players in one browser. Players register, log in, pick a difficulty, roll the die on the server, trigger story events on special cells, and climb a shared leaderboard.

How to play
1. Choose a difficulty: Beginner (3 snakes, 3 ladders), Standard (6 and 5), or Expert (9 and 4).
2. Enter two player names and start the game.
3. Players take turns pressing Roll die. The roll happens in PHP with `rand(1, 6)`.
4. A snake head sends you down, a ladder base lifts you up. Each applies once per landing.
5. Cells 15, 42 and 67, marked E, trigger an event: a bonus of 5 cells, a skipped next turn, or a warp. The AI Narrator describes each move.
6. Reaching or passing cell 100 wins. The result goes to the leaderboard, ranked by fewest rolls and then shortest time.
A page reload keeps positions, the current turn, and the roll history, because the whole game lives in the PHP session.


Tests


- `simulate.php` checks the board arrays and plays 1000 full games. No game may stall, and no turn may end on a snake head or ladder base.
- `smoke.php` checks registration rules, login, leaderboard order, and that dice code appears only in `game_engine.php`.
- `integration.php` runs the real pages on a local server. It covers duplicate usernames, empty names, rolls from cells 95 to 100, reload mid-game, and logout mid-game.

`smoke.php` and `integration.php` point `DATA_DIR` at a fresh temporary folder, delete its JSON files when they finish, and never touch `data/`.

Security
- Every form posts with a CSRF token, checked with `hash_equals()`.
- Every successful POST redirects (Post/Redirect/Get).
- Passwords are stored with `password_hash()` and checked with `password_verify()`.
- The session id is regenerated on login. Session cookies are HttpOnly and SameSite=Lax.
- A username is checked against `/^[A-Za-z0-9_]{3,16}$/` before it is used as a key in `users.json`, and all output is escaped with `htmlspecialchars()`.

Setup

Run locally
Open http://localhost:8000, create an account, log in, and start a game. PHP creates `data/` on the first sign-up.

Run on CODD server
Upload files, then make data writable using chmod777

Features

- **Accounts:** sign up, log in, and log out. Passwords are hashed, and the login page shows one generic error so it never reveals which usernames exist.
- **Three difficulties:** Beginner (3 snakes, 3 ladders), Standard (6 and 5), and Expert (9 and 4). The last choice is remembered in a cookie for 30 days.
- **Two players, one browser:** players take turns on the same screen, and the active player is highlighted.
- **Server-side dice:** every roll is `rand(1, 6)` in `src/game_engine.php`. The browser never computes a roll, a position, or a turn.
- **Snakes and ladders:** each applies once per landing, right after the roll.
- **Event cells:** cells 15, 42 and 67 (marked E) trigger a bonus of 5 cells, a skipped next turn, or a warp.
- **AI Narrator:** a short story line for every roll, snake, ladder, event, and win. See [In-app AI rule](#in-app-ai-rule).
- **Roll history and event log:** both stay on screen and survive a page reload.
- **Leaderboard and Adventure Recap:** the top 10 games ranked by fewest rolls, then shortest time, plus a recap of the events in your last game.
- **Accessible motion:** the token slides to its new cell, and the slide is turned off when the system asks for reduced motion.
- **Responsive layout:** the panels stack under the board on narrow screens.

 In-app AI rule

The AI Narrator and the event system run entirely in PHP, inside `src/events.php`. No external AI service or API is called, nothing leaves the server, and no API key is needed.

- **Narrator text:** each kind of move has a small set of written templates, such as `{player} rolls a {roll} and marches to cell {cell}.` PHP picks one with `crc32()` on the turn number and fills in the player, roll, and cell.
- **Event choice:** the event on a cell comes from `crc32("turn:$turn:$cell")`, so the same turn and cell always give the same event. `srand()` is never called, because it would reset PHP's random generator and make the dice predictable.
- **Output rules:** narrator lines contain no emoji, and every line is escaped with `htmlspecialchars()` before it reaches the page.
