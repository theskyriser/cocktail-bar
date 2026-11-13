# Handoff: Cocktail Ingredient Calculator

## Overview
An app where users keep an inventory of their home bar (with amounts), store cocktail recipes, and get a computed list of drinks they can make right now. Drinks one ingredient short are shown as a secondary list, and the app suggests what to buy. Phone first, with a desktop/web layout.

## About the Design Files
The files in this bundle are **design references created in HTML**: wireframes showing structure, content and flow. They are not production code. Recreate them in the target codebase's environment (React, SwiftUI, React Native, etc.) using its established patterns. If no codebase exists yet, pick a suitable stack (React Native or Expo for phone plus web is a reasonable default).

## Fidelity
**Low-fidelity wireframes.** They define layout, information hierarchy and behavior. They are styled loosely with the Classical design system's tokens (below), so treat those as the intended visual direction, but don't treat the wireframe measurements as pixel specs. Each screen has 3 alternative directions (ids like `1a`, `1b`, `1c`). **No direction has been chosen yet.** Confirm with the designer which option to build per screen, or treat the lists below as candidates.

Handwritten notes (Caveat font) under each wireframe are designer annotations, not UI.

## Core concepts / data model
- **Ingredient**: `id, name, category (spirit | liqueur | vermouth | fresh | mixer | bitters | syrup), substituteGroup? (e.g. "Gin (any)", "Campari-style bitter"), unit (ml | count | dash), amountOnHand, bottleSize?`
- **Recipe**: `id, name, baseSpirit, method (stirred | shaken | built), glass, garnish, instructions, photo?, starred?, items: RecipeItem[]`
- **RecipeItem**: `ingredientId, quantity, unit, subRule: "exact" | "any-in-group" | "optional"`
- **Shopping item**: `ingredientId | freeText, reason (missing | low-stock | manual), bought`

### Matching logic (the "calculator")
For each recipe and each required (non-optional) item:
1. If the exact ingredient is on hand with amount ≥ quantity, the item is OK.
2. Otherwise, if `subRule = any-in-group`, look for another on-hand ingredient in the same `substituteGroup` with enough amount. If one is found, the item is OK via substitution, and the substitute is recorded for display (e.g. "rye for bourbon").
3. Otherwise the item is missing.

- **Ready now**: 0 missing. **Servings possible** = min over items of `floor(amountOnHand / quantity)`. The **limiting ingredient** is the one producing that min.
- **One away**: exactly 1 missing (shown as a secondary list).
- **Best next buy** (shopping): for each missing ingredient, count how many recipes would move to ready if it were added. Rank by that count.
- **Running low**: an on-hand ingredient that supports ≤ 2–3 servings of any recipe using it, or is below a threshold % of its bottle.
- **Unit conversion**: support ml ⇄ oz (1 oz = 30 ml for display; the wireframes round to 22 ml for ¾ oz). Fresh fruit maps to juice (≈ 25 ml per lime, configurable).

## Navigation
Phone: bottom tabs **Bar · Recipes · Make · Shop**. Recipe detail and add/edit are pushed screens or sheets.
Desktop: see section H.

## Screens / Views (phone, 280×560 frames in the wireframes)

### A · My Bar (inventory)
Purpose: see and adjust what you own and how much.
- **1a Grouped list with level meters**: header "My Bar" + outlined "+ Add". Category kickers (uppercase, small, neutral-600). Rows: name left; amount (e.g. "520 ml") + 50×6 fill meter right. Countables show "× 4". Tapping a row edits the amount.
- **1b Shelf grid**: filter chips (All 14 / Spirits / Liqueurs / Fresh). 3-column tile grid: bottle image placeholder with a gold horizontal line marking fill level (draggable), label below. Low-stock tiles get an accent border. Last tile is a dashed "+" add tile.
- **1c Running-low first**: search/add field at the top. Accent-bordered "Running low · N" box listing items with "N drinks left" and the link "Add to shopping list". Then the full A–Z list with amounts.

### B · Add ingredient (type + autocomplete is the chosen input method)
- **1d Autocomplete → amount sheet**: bottom sheet over the Bar. Autocomplete dropdown includes exact matches, substitute groups (labelled "group") and "+ Create '…'". Then a bottle-size segmented control (375 / 750 ml / 1 L) and a "How full?" slider. Button: "Add · unlocks N drinks".
- **1e One-line smart entry**: free text (e.g. "half a bottle of campari") parsed into editable fields: Ingredient, Amount, Category, Counts as. "Recently added" quick chips. Button "Add & next".
- **1f Search with inline expand**: full-screen search results. Tapping one expands an inline panel with substitute info, a ± amount stepper and "Add to bar". Items already owned show "in bar ✓".

### C · Recipe list
- **1g A–Z with status marks**: search ("Search 32 recipes"). Rows show a status on the right: "● ready" (accent-700), "○ need rum" / "○ need 2" (neutral-600), "◐ via rye" (makeable with a substitute).
- **1h Cards + base-spirit filter**: chips (All / Gin / Whiskey / Rum). 2-column cards: photo placeholder, name (heading font), status line.
- **1i Grouped by base**: sections such as "Gin · 9". Each row shows the name plus an ingredient line, with missing ingredients struck through.

### D · Add / edit recipe
- **1j Structured form**: Cancel / "New recipe" / Save. Optional photo. Name. Ingredient rows in a grid: qty | unit | ingredient (with sub rule tag: "any" / "exact" / "optional") | ⋮ menu. "+ ingredient". Method textarea. Glass chips (Rocks / Coupe / Highball).
- **1k Paste & confirm**: monospace textarea for pasted recipe text, then a parsed list "Check what we found". Each line shows a match status: "✓ in bar", "new", or "≈ Limes" (fuzzy match). Unit toggle (oz → ml / keep oz). "Save recipe".
- **1l Build-as-you-go**: a 3-step wizard (name → ingredients → method & glass). Shows the running ingredient list, the current ingredient input, amount presets (15/30/45/60 ml) and a substitution choice ("Any bitter aperitivo" / exact).

### E · Recipe detail
- **1m Classic page**: matted plate image, name, meta line ("Stirred · Rocks glass · Orange peel"), ingredient list with ✓ / "✓ low" marks, justified method text.
- **1n "Make it" + deduct**: subtitle "You can make 3 · limited by bourbon → using rye". Each ingredient row shows the amount on hand. A substituted row shows "↳ swap to Rye (700 ml) ⇄", which toggles the sub. Primary button "I made one · deduct from bar", with a preview line ("Rye 700 → 640 ml · Syrup 250 → 243 ml").
- **1o Batch / scale servings**: servings stepper (max is capped by inventory, with the note "Max 4 — limes run out first"). Amounts scale and show "/ on hand". Unit segmented control (ml / oz / parts). "Made 4 · deduct".

### F · What can I make (results)
- **1p Ready now / one away**: title "Tonight", subtitle "From 14 ingredients · 32 recipes". "Ready now · 6" list with servings (× N) and substitution notes. A collapsed "One away · 5 ▸" section below.
- **1q Hero count + filters**: large display numeral (64px heading font, normal weight, accent-700) + "drinks you can make right now". Chips: Any / Stirred / Sour / No subs. 2-column cards with "makes N". Sort dropdown. A link to the one-away list.
- **1r Pick a spirit first**: "What are you in the mood for?" 2×2 tiles with counts (zero-count tiles are disabled), including "Surprise me". Filtered results with a flavor/method line and servings left. A single "Almost: …" line.

### G · Shopping list
- **1s Ranked by unlocks**: checkbox rows with the ingredient, the recipes it unlocks, and "+N" in the heading font (accent-700).
- **1t By target recipe**: grouped under starred or planned recipes ("Margarita · for Saturday", "Negroni × 6"). Shows "or any triple sec", owned items greyed as "have", and top-up amounts ("need 120 ml more"). "Share list".
- **1u Plain list → restock bar**: sections "Restock · running low" and "To try something new". Checked items are struck through as "bought". Button "Move N bought items to bar" adds them to the inventory (it should prompt for amounts).

### H · Desktop / web (960×540 frames)
- **1v Three panes**: left 240px Bar inventory (editable, recalculates live) | centre: "You can make 6" table (Drink / Servings / Notes) with tabs Ready / One away / All recipes / Shop | right 340px: detail of the selected recipe with "Made one · deduct" and Edit.
- **1w Top nav + recipe table**: nav "The Home Bar" · Bar (14) / Recipes (32) / Shop (4) · "+ New recipe". Left filter column (Show: Ready now, With substitutes, Missing 1, Missing 2+; Base; Style). Table columns: Recipe | Ingredients (subs underlined) | Can make | Missing (with "+ shop" link).
- **1x Kitchen-counter mode**: a large-type two-column view for mixing (tablet/desktop): plate image left; ingredient amounts at 24px, instructions at 17px and large buttons right. "Made it · deduct" / "Next →", with arrow-key navigation through the makeable drinks.

## Interactions & Behavior
- Any inventory change recomputes ready / one-away / servings / shopping rankings immediately.
- "Made / deduct" subtracts recipe quantities × servings from inventory (using the substitute actually chosen). Offer undo via a toast.
- Substitution toggle (⇄) on a recipe detail switches between eligible on-hand group members.
- The "No subs" filter excludes recipes that are only makeable via substitution.
- Autocomplete matches ingredient names and substitute-group names. Offer "Create …" when nothing matches.
- Empty states (not drawn): no ingredients yet → prompt to add the first bottles; no recipes → prompt to add or paste one; nothing makeable → show one-away and best next buy.

## State Management
- `ingredients[]`, `recipes[]`, `shoppingList[]` (persisted locally; sync is optional).
- Derived (memoized): `matchResults` per recipe `{status: ready|sub|missing, missingIds[], subsUsed{}, servings, limitingIngredientId}`, `readyList`, `oneAwayList`, `bestNextBuys`, `lowStock`.
- UI state: active filters, sort, servings stepper, selected substitute per recipe item, unit preference (ml/oz).

## Design Tokens (Classical design system — `styles.css` included)
- Colors: bg `#f3f2f2`, surface `#eae9e9`, text `#201f1d`, accent `#b68235`, divider = text at 16% alpha. Neutral ramp 100–900: `#f8f4f4 #eae7e7 #d7d3d3 #bab6b6 #9b9797 #7d7979 #605d5d #444141 #2d2b2b`. Accent ramp 100–900: `#fff3e4 #ffe3bf #facb8d #e1ad66 #c28d41 #a06f24 #7d5411 #5a3b0a #3a270d`.
- Use accent-700 for accent-colored text at body size. Use the accent as stroke/border only, never as a solid fill.
- Type: headings Cormorant Garamond (600 max; display sizes at 400), body Lora. Use tabular numerals for figures and counts.
- Spacing: 4.6 / 9.2 / 13.8 / 18.4 / 27.6 / 36.8 px (`--space-1…8`).
- Radius: 2 / 4 / 7 px. Shadows: `--shadow-sm/md/lg` (subtle only).
- Buttons are outlined (1px accent border, transparent fill). Images use the `.plate` mat (6px surface border + hairline outline, slight sepia).
- Icons: Lucide.
- Focus ring: 2px solid accent, 2px offset.

## Assets
None. All images are striped placeholders (drink photos, bottle photos). Real photography is TBD.

## Files
- `Cocktail App Wireframes.dc.html`: all wireframes (sections A–H, options 1a–1x). Open it in a browser alongside `support.js` and the `_ds/` folder.
- `support.js`: runtime required to open the HTML file.
- `_ds/classical-…/styles.css`: design-system tokens and component classes.
