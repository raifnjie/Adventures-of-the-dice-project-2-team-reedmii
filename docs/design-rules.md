# Design rules: Adventures of the Dice

Use these rules for every wireframe, UI screen, and CSS change in this project. They come from the "Signs of vibe coded UI" checklist (the anti-patterns) and from the Adventures of the Dice spec (the required screens).

## Palette (team palette "Quite clear")

| Token | Hex | Role | Share |
|---|---|---|---|
| `--bg` / `--panel` | `#F2F2F2` | Page and panel surfaces | 70% |
| `--line` / `--tint` | `#CBCBCB` | Dividers, panel borders, event cell fill | 20% |
| `--accent` | `#174D38` | Primary action, active player, ladders | 10% |
| `--ink` | `#4D1717` | Text and snake bodies | Text role |

Rules:
- The accent is deep green `#174D38`. Use it on the primary button, the active player's cell and token, and the active player label. Ladders also use it, so keep the rest of the board in ink.
- Snakes use the rich brown `#4D1717`, the same color as text. That keeps the dangerous tiles visually linked to the dark text.
- Panels sit on the same light surface as the page, so a 1px `#CBCBCB` border separates them. The palette has no white.
- No purple gradients, no muddy color mixes, and no second accent on the same screen.

## Typography

- Font: Smooch Sans (Figma font page, nine weights). Load it with the Google Fonts import in every SVG and in `style.css`:
  `@import url("https://fonts.googleapis.com/css2?family=Smooch+Sans:wght@400;700&display=swap");`
- Fallback stack: `'Smooch Sans', Helvetica, Arial, sans-serif`.
- Use weight 700 for titles and buttons, 400 for body. Use size for hierarchy: page title 22px, section title 16px, body 14px, small labels 12px.
- Smooch Sans is a display-style face, so keep body copy at 14px or larger and avoid long paragraphs at small sizes.
- No serif faces.

## Icons and visual assets

- No icons inside rounded-square tiles. Remove them. Informational content does not need an icon.
- Icons are allowed only on actions (a button or a control), with no tile behind them.
- No floating action buttons and no chat popups.

## Emoji

- No emoji anywhere in the UI, including the AI Narrator. The spec example uses a snake emoji. Use plain text instead ("A serpent coils around cell 87.").
- In the board, use text labels (E for event cells) rather than pictures.

## Surfaces and effects

- No glassmorphism (frosted or semi-transparent panels with light borders).
- No linear gradients on text, buttons, or backgrounds.
- No decorative shadows. A shadow behind a button muddies the section.
- No nested cards. One container level per section. Remove the inner box and keep the text.
- Use one primary button per screen. Secondary actions use an outline with the `#CBCBCB` border.

## Motion

- The only motion is the token slide on the board (CSS `transition` on `transform`, about 400ms).
- No hover bounce, no scale on hover, no appear-on-scroll animations, no looping effects.
- Wrap the transition in `@media (prefers-reduced-motion: reduce)` and turn it off there.

## Hierarchy check (run before every screen is delivered)

1. Within a split second, can a player tell where to look first (the board, or the roll button)?
2. Is there exactly one primary action on the screen?
3. Is every icon on an action, not on information?
4. Is there any gradient, shadow, glass effect, emoji, serif, or nested card? If yes, remove it.
5. Does the only motion serve a purpose?
6. Is body text at least 14px in Smooch Sans?

## Responsive note

The board is a CSS grid of 10 by 10 cells. Use `aspect-ratio: 1` and `width: min(100%, 560px)` so it scales down on tablets and phones. Stack the side panels below the board under 900px.

## Files

- `wireframes/`: annotated low-fidelity screens (01 to 05), same palette and font, with numbered notes.
- `ui/`: high-fidelity screens (01 to 05) with no annotations.
