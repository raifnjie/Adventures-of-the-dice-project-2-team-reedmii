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


