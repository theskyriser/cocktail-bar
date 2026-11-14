# CLAUDE.md — cocktail-bar-api

> **Learning mode is on in this repo.** Explain how and why; do not write the
> implementation. See § *Learning mode* — it governs every other section in this
> file, including the checklists.

## What this project is

The Laravel API behind the cocktail calculator: **you tell it what ingredients
you have, and it works out what cocktails you can make.** It owns the database,
the ingredient identities and every scrap of the matching logic, and serves JSON
to [`cocktail-bar-web`](https://github.com/theskyriser/cocktail-bar-web) — a
standalone React SPA in its own repo.

Two halves, and they are worth keeping separate in your head and in the code:

- **Intake** — getting ingredients in and agreeing on what they *are*. Free text
  in ("gin", "Gordon's", "sweet vermouth", "red vermouth"), a known ingredient
  out, or an honest "I don't recognise this".
- **Interpretation** — taking the stock on hand and a body of recipes, and
  deciding what is makeable, what is makeable with a substitution, and what is
  one bottle away. The near-misses are a feature, not an error case.

### State of play (2026-11-13)

This is a **pure JSON API**, not a part-built product.

What exists: Fortify auth answering JSON, Sanctum stateful cookie auth, the
three settings endpoints, `/api/user`, `/api/auth/config`, and a first pass at
the cocktail schema (bars, ingredients, aliases, substitutes, stock, recipes,
glasses, tags — migrations and models). The interpreter in `app/Cocktails/` is
started but not finished, and the cocktail controllers are still empty artisan
stubs with no routes pointing at them.

**The repo was renamed from `cocktail-bar` to `cocktail-bar-api` on 2026-11-13**
and the front end was split out into `cocktail-bar-web`. **That split is now
finished.** The React app, its components, the Classical theme, the auth and
settings screens and a Sanctum-aware fetch client live in `cocktail-bar-web`;
Inertia, Wayfinder, React, Tailwind, Vite, `resources/js/` and the whole Node
toolchain are gone from here. There is no `routes/web.php`, no Blade view and no
`package.json`.

So: when you add a cocktail endpoint, build it as **JSON** (§ *The API this
serves*). **Nothing in this repo renders HTML**, and every error is JSON —
`bootstrap/app.php` renders JSON unconditionally, because with Fortify's views
disabled `route('login')` does not exist for the framework to redirect to.

---

## Learning mode

**This repo is a learning exercise.** It exists so that I can prepare for a
technical interview by building it myself. Working code is not the deliverable —
**my ability to write it and defend it out loud is.** Code written for me is code
I cannot defend under questioning, so the default here is inverted from a normal
project:

> **Explain how and why. Do not write the implementation.**

This is not a limit on how much you help. It is a change in *what* help means:
the useful contribution is the reasoning, the rule that decides it, the name of
the pattern and the trap I was about to walk into — not the diff.

It matters more on this side of the wire than on the client's. The interpreter is
the part of this product an interviewer will actually dig into — a ranking
algorithm, a purity boundary, a units decision that is wrong in a way tests catch
and eyeballs do not. Handing me `app/Cocktails/` finished would cost me the one
thing worth having.

### What an answer looks like

Ground every answer in the rules and skills **this repo already defines**, not in
generic advice. This file, `AGENTS.md`, the design handoff and `.claude/skills/`
are the syllabus; cite them. For whatever I have asked about, cover:

1. **Where it goes** — migration, model, enum, service in `app/Cocktails/`,
   `FormRequest`, controller, API Resource — and the existing file whose shape I
   should copy (`Settings/SecurityController` and `tests/Feature/Auth/` are the
   worked examples of a controller and its test).
2. **Which rule decides it** — name the section of this file, the `AGENTS.md`
   rule, the design handoff heading, or the skill rule file by path
   (`.claude/skills/laravel-backend/rules/new-service.md`,
   `.claude/skills/testing-anti-patterns/SKILL.md`), so I can read the source
   rather than take your word for it.
3. **Why — the failure mode, concretely.** "The interpreter does no Eloquent
   access" is a rule. "Touch Eloquent in the scorer and every ranking test needs
   a database, so the test that is most likely to catch a real scoring bug is the
   test nobody runs" is an explanation. Always give the second kind.
4. **The name of the idea.** Say *bounded context*, *entity vs value object*,
   *aggregate*, *anti-corruption layer*, *ubiquitous language*, *dependency
   inversion*, *pure function*, *determinism*, *canonical unit*, *N+1*,
   *idempotency*, *eager loading*. The vocabulary is half of what the interview is
   testing; a correct answer in the wrong words still fails. `ddd-strategic-design`
   is in `.claude/skills/` precisely for this.
5. **The trade-off, and what it rules out.** Why canonical millilitres as an
   integer and not a decimal or a unit column; why `substituteGroup` + `subRule`
   and not a pairwise substitution table; why the interpreter takes data handed
   to it rather than loading its own; why this is a pure JSON API and not Inertia.
6. **The follow-up an interviewer would ask**, and a sketch of how I should
   answer it. § *The auth edge, and the six traps in it* is a ready-made supply
   of these — each trap is a question I should be able to answer cold.

Then **stop**, and let me write it.

### How much code is allowed

**Signatures and skeletons only. Never a working body.**

Allowed — shapes, so that I am solving the problem and not guessing an API:

```php
// app/Cocktails/MatchInterpreter.php
/** @param Collection<int, Recipe> $recipes */
public function interpret(StockSnapshot $stock, Collection $recipes): MatchResult
// body: tier each recipe, rank within tier, name the limiting ingredient — I write this
```

Also allowed: a migration's column list in prose or a bare `$table->…` field
roll-call, an enum's cases, a value object's constructor parameters, the JSON
shape a Resource should emit, a list of `it('…')` / `test('…')` titles for the
tests I should write.

**Not** allowed: the filled-in body, the whole class, the finished migration,
anything runnable, or the same implementation smuggled across several messages a
line at a time. If an explanation would be clearer with twenty lines of PHP, it
is clearer with four sentences and a signature — write those instead.

Artisan generators are a grey area with an easy rule: **tell me the command, let
me run it.** `php artisan make:model Ingredient -mf` is something I should know by
heart, and watching you run it teaches me nothing.

### Asking for code anyway

I will say so explicitly — "write it", "code this one", "just show me the file".
That opt-in covers **that one request and nothing else**, and lapses the moment
it is done; the next question goes back to explanation without being told. Do not
infer permission from frustration, from a long back-and-forth, or from my having
asked you to write something an hour ago.

When I do opt in, every normal rule in this file applies in full — § *Project
hard rules*, Pint, PHPStan level 7, a Feature test asserting the JSON shape,
`composer ci:check`. Learning mode does not lower the bar; it moves who clears it.

### Still expected of you

Learning mode removes implementation, not involvement:

- **Review what I wrote.** This is the highest-value thing you can do here. Read
  my code against § *Project hard rules* and the skills, and say what is wrong
  and why — a query inside the interpreter, interpretation leaking into a
  controller, a hardcoded ingredient list, an N+1 in a Resource, a missing
  `auth:sanctum`, a measure stored in oz, a test that asserts a status code and
  not the payload. Point at the defect; let me fix it. If I am stuck twice on the
  same thing, narrow the hint, don't hand over the answer.
- **Run things and read real output.** `composer ci:check`, a scoped
  `php artisan test --compact --filter=…`, `vendor/bin/pint --test`, PHPStan,
  `php artisan route:list`, `tinker`, `git diff`, reading any file. Teaching from
  an actual PHPStan error or a real failing expectation beats teaching from a
  guessed one — and reading a stack trace is itself the skill being practised, so
  walk me through how you located the cause.
- **Ask before you answer.** "Where do you think the normalisation belongs?"
  Retrieval practice is worth more than a clean explanation I merely nodded at.
  Then correct the answer I give.
- **Quiz me.** Interview-shaped follow-ups on what we just built: why is the
  interpreter pure? What does `fortify.views => false` actually delete? Why does
  `Auth::logout()` break under `auth:sanctum`? Why is "parts" not a volume? What
  does `migrate:fresh` cost me right now?
- **Write prose.** Markdown is not code: design notes, plans, schema
  comparisons, ADR-style write-ups and this file are yours to write to disk
  freely. The one exception is `AGENTS.md`, which is generated — § *Project hard
  rules* 4 still stands.

### Where this does not apply

Config and ceremony are not the exercise and should not be made artificially
hard — `.env` values, a composer script, `compose.yaml`, a CI workflow,
`config/cors.php` wiring, a `phpstan.neon` path. Explaining still beats doing,
but if I ask you to fix a Pint config, fix it. The exercise is **the domain and
the architecture**: the schema, the intake boundary, the interpreter, the tiers
and ranking, the service/controller/Resource split, and the tests that pin them.

---

## Read first, and in this order

1. **`AGENTS.md`** — generated by Laravel Boost (see `boost.json`). It carries
   framework-level rules: artisan usage, Pint, Pest, Eloquent resources. **Do
   not hand-edit it** — `php artisan boost:update` rewrites the file and your
   edits vanish. `config/boost.php` pins Boost's output to `AGENTS.md`; without
   it Boost appends its guidelines to this file instead, because it prefers
   `CLAUDE.md` whenever that exists. Note that `AGENTS.md` is gitignored and
   this file is tracked deliberately.
2. **`docs/design/cocktail-calculator/README.md`** — the design handoff: the
   product shape, the data model, the matching rules and the wireframe options.
   It is the authority on *what the product does*; open
   `Cocktail App Wireframes.dc.html` in a browser (it needs `support.js` and
   `_ds/` beside it) to see the screens.
3. **This file** — the domain, the layout, and how work is done here.
4. **`../cocktail-bar-web/CLAUDE.md`** — the other half of the contract: what
   the front end expects from this API and what it refuses to do for itself. Read
   it on GitHub if the two repos are not checked out side by side.
5. **`.claude/skills/`** — the skill set. See § *Skills* for which ones apply to
   this stack and which do not.

On a conflict: `AGENTS.md` wins on framework mechanics, this file wins on domain
and process, and the web repo's `CLAUDE.md` wins on front-end architecture. If a
skill contradicts any of them, this file wins — say so rather than quietly
following the skill.

§ *Learning mode* outranks every other section here, and `AGENTS.md`, on **how
work is done**. The rest of this file is written in the imperative — "migration +
model + factory via artisan", "write the test for the ranking first" — because it
was written for someone implementing. Read those as **what I should do and you
should explain**, never as your licence to do them. `AGENTS.md` is generated by
Boost and knows nothing about this section, so where it tells you to go ahead and
scaffold, this file wins.

---

## Stack

| Concern | Choice |
|---|---|
| Backend | PHP 8.3, Laravel ^13.17, Fortify (auth), Tinker, `laravel/chisel` |
| API auth | **Sanctum stateful cookie** auth for the SPA — `/sanctum/csrf-cookie`, `XSRF-TOKEN`, `SANCTUM_STATEFUL_DOMAINS`. Not bearer tokens |
| Front end | **Not here, and not anywhere in this repo.** `cocktail-bar-web` — React 19 SPA, React Router, TanStack Query, Zod |
| Node | **None.** No `package.json`, no Vite, no TypeScript. Do not reintroduce one without asking |
| Database | **PostgreSQL 18** in Docker via Sail (`compose.yaml` runs `pgsql` + `redis`); session, cache and queue drivers are all `database`. **The Pest suite runs on SQLite `:memory:`** (`phpunit.xml`) and must stay there — CI declares no database service |
| Static analysis | Larastan/PHPStan **level 7** over `app/ bootstrap/app.php config/ database/ routes/` |
| Style | Laravel Pint (`pint.json`) |
| Tests | Pest (`tests/Feature`, `tests/Unit`) |
| CI | GitHub Actions: `composer setup` then `composer ci:check`, on push to `main` and on PRs. PHP only — no Node step |

Do not add or upgrade dependencies without asking.

---

## Commands

```bash
composer run dev        # php artisan serve
composer ci:check       # what CI runs — now just composer test
composer test           # config:clear + pint --test + phpstan + artisan test
composer lint           # pint --parallel (writes)
```

`php artisan dev` is **not** usable here: it shells out to `@laravel/multiplex`
through a Node package manager, and there is no `package.json` any more.

The database runs in Docker, so bring it up before anything that touches it:

```bash
./vendor/bin/sail up -d       # app + Postgres + Redis
./vendor/bin/sail artisan migrate
./vendor/bin/sail down
```

`DB_HOST=pgsql` resolves only **inside** the containers. Host-native `php artisan`
commands that reach the database need `DB_HOST=127.0.0.1` against the forwarded
port; the Pest suite is unaffected, because it uses SQLite `:memory:`.

Scope test runs while iterating, and use the whole gate before calling something
done:

```bash
php artisan test --compact --filter=IngredientMatching
```

Two things to know about these scripts: **`composer test` already includes Pint
and PHPStan**, so a green `composer test` means style and types are clean too;
and `vendor/bin/pint --dirty --format agent` is the quick formatter pass to run
after touching PHP (per `AGENTS.md`).

---

## Layout

```
app/
├── Actions/Fortify/        # CreateNewUser, ResetUserPassword
├── Cocktails/              # the interpreter — started, not finished
├── Concerns/               # shared validation rule traits
├── Enums/                  # IngredientKind, MeasurementUnit, SubstitutionRule, …
├── Http/
│   ├── Controllers/        # UserController, AuthConfigController,
│   │                       # Settings/{Profile,Security}Controller,
│   │                       # and the empty cocktail stubs
│   ├── Requests/Settings/  # FormRequests
│   └── Resources/          # UserResource — API Resources live here
├── Models/                 # User plus the cocktail schema
└── Providers/
database/migrations/        # users, cache, jobs, and the cocktail schema
routes/                     # api.php, console.php — there is no web.php
tests/                      # Pest: Feature/{Auth,Settings}, Unit
```

There is no `resources/` directory and no `app/Http/Middleware/`. Mail templates
resolve from the framework, which is why deleting `resources/views/` was safe —
`tests/Feature/Auth/PasswordResetTest.php` renders the reset mail to keep it
that way.

---

## The API this serves

`cocktail-bar-web` assumes the contract below, and its
`src/common/lib/api/endpoints.ts` is the single place it writes these paths. Keep
the two in step: **changing a path here is a breaking change there.**

| Method | Path | Returns |
|---|---|---|
| `GET` | `/sanctum/csrf-cookie` | `204`, sets `XSRF-TOKEN` |
| `POST` | `/login` | `200` with `{two_factor: false}`, Fortify at the root |
| `POST` | `/logout` | `204` |
| `POST` | `/register` | `201` |
| `POST` | `/forgot-password` · `/reset-password` | `200` with `{message}` |
| `POST` | `/email/verification-notification` | `202`, or `204` when already verified |
| `GET` | `/api/user` | the authenticated user as a **bare object, no `data` wrapper**; `401` when signed out |
| `GET` | `/api/auth/config` | `{ password_rules, can_reset_password, registration_enabled, must_verify_email }` |
| `PATCH` | `/api/settings/profile` | `204` |
| `PUT` | `/api/settings/password` | `204` |
| `DELETE` | `/api/settings/profile` | `204` |

The three status codes above are Fortify's own and are **not** all `204` — the
client ignores these bodies, but a test asserting `204` on login or register
will fail. `tests/Feature/Auth/` pins each one.

`GET /api/auth/config` now exists (`AuthConfigController`). It replaces four
props Inertia used to share on every render; `password_rules` is
`Password::defaults()->toPasswordRulesString()`, which is why the client will not
hardcode it. The client's `fallbackAuthConfig` is now only a cold-start
fallback.

Three rules the client depends on:

- **Errors are JSON, always.** `422` with `{message, errors}` for validation,
  `401` for unauthenticated, `419` for a stale CSRF token. No redirects to an
  HTML login page — the SPA cannot follow them.
- **Snake_case keys and ISO 8601 timestamps.** The client has adapters that
  convert to camelCase and `Date`; it does not want this API guessing at its
  conventions.
- **Cookies need sibling subdomains.** `SESSION_DOMAIN`,
  `SANCTUM_STATEFUL_DOMAINS` and `FRONTEND_URL` must cover wherever the SPA is
  served. Locally that is `SESSION_DOMAIN=localhost` with
  `SANCTUM_STATEFUL_DOMAINS=localhost:5173`; cookies ignore the port, so the
  cross-port setup works without a proxy.

---

## The auth edge, and the six traps in it

The Inertia removal is done. What is worth keeping is the list of things that
bit during it, because each one fails quietly and none of them is guessable from
reading a controller.

1. **CORS has to list Fortify's routes one by one.** `fortify.prefix` is `''`,
   so `/login`, `/logout`, `/register`, `/forgot-password`, `/reset-password`,
   `/email/verification-notification` and `/user/*` are **not** covered by
   `api/*`. Leave them out of `config/cors.php` and the browser blocks login
   while every test still passes.
2. **`fortify.views => false` deletes route *names*, not just pages.** Gone:
   `login`, `password.request`, `password.reset`, `verification.notice`,
   `register` (GET). Anything calling `route('login')` now throws — which
   includes the framework's own unauthenticated redirect. That is why
   `bootstrap/app.php` renders **every** exception as JSON.
3. **The password reset email would 500.** `ResetPassword::resetUrl()` calls
   `route('password.reset')` unconditionally, and that route no longer exists.
   `FortifyServiceProvider::configureNotificationUrls()` points it at the SPA
   instead. The emailed URL shape is the SPA's route, `/reset-password/{token}
   ?email=`, so changing it is a breaking change in the other repo.
4. **Email verification is the one flow that really redirects.** The link is
   opened by a mail client, not by the SPA's fetch client, so it is not a JSON
   request. `fortify.redirects.email-verification` sends it back to the SPA. The
   signed `verification.verify` route survives `views => false`, but it is behind
   `auth`, so a user without a session in that browser gets a JSON `401` in a
   browser tab — a known rough edge, not a bug to patch in the controller.
5. **Sanctum only starts a session when it recognises the request.**
   `EnsureFrontendRequestsAreStateful` checks `Referer`/`Origin` against
   `sanctum.stateful`; with neither header the request runs sessionless and
   anything touching `$request->session()` blows up. Browsers always send one,
   the test client does not, so `Tests\TestCase` sets `Origin` for every test.
6. **`auth:sanctum` changes the default guard mid-request.** `Auth::logout()`
   resolves Sanctum's `RequestGuard`, which has no `logout()` — name the session
   guard (`config('fortify.guard')`) explicitly. The same trap applies to the
   `password_hash_*` session key that Sanctum's `AuthenticateSession` reads:
   written under the wrong guard name it silently does nothing, and the test
   that is supposed to catch it passes anyway. `SecurityController` refreshes
   that key so changing your own password does not sign you out, while a
   password change still invalidates your other sessions.

---

## Where the cocktail domain goes

None of this exists yet, so these are the decisions to make deliberately on the
first feature rather than drift into. Where a choice is open I have given a
recommendation and the reason; disagree with it in a commit message, not by
silently doing something else.

### The design handoff decides the product; read it first

`docs/design/cocktail-calculator/README.md` specifies the data model and the
matching rules, and it goes further than the sketch below in ways that change the
schema — so take its shape, not a guess:

- **Stock carries amounts, not presence.** `amountOnHand` + `bottleSize` per
  ingredient, with `unit` as `ml | count | dash`. That makes **servings
  possible** (`min` over items of `floor(amountOnHand / quantity)`) and the
  **limiting ingredient** first-class, and it means "I made one" **deducts** from
  inventory. A boolean have/have-not schema cannot express any of that.
- **Substitution is a `substituteGroup` on the ingredient** plus a per-recipe-line
  `subRule` of `exact | any-in-group | optional` — not a pairwise substitution
  table. Same intent as below (explicit data, never inferred), different shape.
- **Shopping is ranked by unlocks**: for each missing ingredient, how many
  recipes would become ready if it were added.
- **Running low** is derived (supports ≤ 2–3 servings, or below a % of the
  bottle), so it is a query, not a stored flag.
- **Display units are ml ⇄ oz**, and the design deliberately rounds 1 oz to
  30 ml (22 ml for ¾ oz). Keep the canonical ml storage recommended below and do
  the rounding at the display edge only.
- Fresh fruit maps to a juice volume (≈ 25 ml per lime, configurable).

One thing the handoff leaves open, so do not invent an answer: **no visual
direction has been chosen per screen** (each has three options, `1a`–`1x`). The
design system question *is* settled — the *Classical* system (Cormorant Garamond
/ Lora, `#b68235` accent, 2–7px radii) was adopted in `e6c59dd` and now lives in
`cocktail-bar-web`'s `src/common/styles/app.css`.

Also note the handoff's own advice to "pick a suitable stack (React Native or
Expo)" was written for a greenfield project, and its screens are not this repo's
job at all any more — **they are built in `cocktail-bar-web`.** What this repo
owes them is a JSON payload that already contains every decision (§ *Keep the
layers honest*).

### Ingredients are entities, not strings

An `ingredients` table with a stable id, plus a separate **alias** table mapping
the many names people type ("sweet vermouth", "red vermouth", "rosso") onto one
ingredient. Recipes and stock reference the id. The moment an ingredient is a
string in two places, the same bottle exists twice and matching quietly breaks.

Normalisation happens **once, at the intake boundary** — one service that takes
raw user text and returns a resolved ingredient or an explicit "unrecognised".
Not in a controller, not in React, and not twice.

### Recipes carry structure, not prose

A `recipes` table and a `recipe_ingredient` pivot holding, per line: the
ingredient, the quantity, the unit, whether it is **optional**, and its **role**
(base spirit / modifier / sweetener / bitters / garnish). The roles are what let
the interpreter be sensible — a missing garnish is not a missing base spirit, and
flattening them into one list throws that away.

### Units: pick one and convert at the edges

Store a single canonical unit — **millilitres as an integer** — and convert for
display. Note the two traps before writing the column: `oz` is 29.57 ml and
rounding it repeatedly drifts; and **"parts" is a ratio, not a volume**, so it
cannot live in the same column as a measure. Give ratio-based recipes their own
representation rather than inventing a ml value for them.

### The interpreter is a pure service

Put the matching logic in its own namespace (`app/Cocktails/` or similar) as
plain PHP:

- it takes a value object describing the stock and a set of recipes, and returns
  **ranked, tiered matches**;
- it does **no Eloquent access and no queries of its own** — the caller loads the
  data and hands it over. That is what makes it unit-testable with no database
  and no HTTP, which is the whole point, because the scoring is the part most
  likely to be wrong in a way tests catch and eyeballs do not;
- it is **deterministic**: same stock plus same recipes, same ranking, every
  time. No randomness, no "surprise me" shuffling inside the scorer — do that in
  a caller if the product wants it.

### Match tiers are the product

At minimum: **makeable** · **makeable with a substitution** (say which) ·
**one ingredient short** (say which). The third tier is the shopping list, and it
is the most useful thing the app can tell someone. Do not collapse the tiers into
a single boolean or a bare score.

**Substitutions are data, not inference.** An explicit substitution table
("rye → bourbon, acceptable"; "lime cordial → lime juice, poor") is reviewable
and correctable. Guessing from ingredient categories will produce a Negroni with
no bitters and no explanation.

### Keep the layers honest

Interpretation lives in the service. Controllers load, call the service, and pass
the result into an **API Resource**. The SPA renders what this API computed — it
will not re-score, re-rank or re-filter, by its own hard rule. So if a screen
needs a different answer, **this** is the repo that has to send a different
answer; do not expect the client to patch it up.

That cuts both ways: anything the UI needs in order to decide what to show — the
match tier, which ingredient is missing, which substitution was applied, the
display unit, servings possible, the limiting ingredient — has to be **in the
payload, named and explicit**. A bare score with the reasoning left implicit
pushes interpretation across the wire, which is exactly what this split exists to
prevent.

---

## Skills

The skills from the harness have been copied to `.claude/skills/`. Only the PHP
and cross-cutting set lives here: **`react-frontend`, `vercel-react-best-practices`
and `zod` were deleted on 2026-11-14.** There is no TypeScript in this repo for
them to bind to, and the copies in `cocktail-bar-web` have since been rewritten
for that app's actual stack — so keeping a second, diverging set here would have
been worse than having none. If you need a front-end rule, read it in the web
repo, which is where it is true.

| Skill | Applies here? |
|---|---|
| `no-workarounds` | **Yes, always.** Load it for any debugging or test-failure work. |
| `testing-anti-patterns` | **Yes**, whenever tests are in scope. |
| `clean-code` · `security-review` | **Yes** — `security-review` for anything touching auth, Fortify, policies or user input. |
| `laravel-backend` | **Conventions yes, architecture with care.** Its Pint/PHPStan/migration/test rules all apply. But its Controller → UseCase → Repository layering, `app/Domains/` tree and Spatie Laravel-Data DTOs are a *heavier* architecture than this app has. Adopt it wholesale or not at all — a half-applied layering is worse than either. Note it assumes **PHPStan level 8**; this project is at **level 7**. It also assumes Sail; this project now uses Sail for Postgres and Redis (`compose.yaml`), so that guidance applies — but host PHP 8.3 is installed as well, and the Pest suite runs on the host against SQLite. |
| `react-frontend` · `vercel-react-best-practices` · `zod` | **Gone — they were the other repo's.** Deleted from `.claude/skills/` on 2026-11-14. `cocktail-bar-web` holds the only copies, rewritten for a Vite React SPA. When a change to this API's JSON affects the client, the rule you want (its schemas, adapters and layering) is over there. |
| `ddd-strategic-design` · `ddd-clean-architecture` · `nodejs-ddd-backend` | **Reference only.** Generic (and Java/Node-flavoured) material. `ddd-strategic-design` is useful vocabulary when carving up ingredients/recipes/stock; neither should dictate the folder layout of a Laravel app. |

There is no gate script enforcing any of this — it is discipline, not machinery.
Say in the commit or PR which skills gated the work.

In learning mode these skills are also the **reading list**. When one of them
decides an answer, cite the rule file by path so I can go and read it — and where
a skill's reasoning applies but its specifics do not (`laravel-backend`'s
`app/Domains/` tree and Spatie Data DTOs, the Java/Node flavour of the DDD
skills), say which half you are using and why the other half is wrong *here*.
Telling the two apart is itself an interview skill: it is the difference between
knowing a convention and knowing why it exists. `ddd-strategic-design` earns its
place as vocabulary for carving up ingredients, recipes and stock, even though it
must not dictate the folder layout.

---

## Comments — do not write them unless 100% necessary

(The skills defer to this section by name, so it is load-bearing.)

**Default to no comment.** Working, readable code is the deliverable; narration
is not. Do not restate what the code says, re-describe a test's own name, or
leave notes "for context" — that belongs in the commit message or PR
description.

Write one only when **all** of these hold: the information cannot be carried by
naming, types or structure; getting it wrong would cause a real defect; and it
concerns something outside the file the reader cannot see. In this project that
is most likely a **units or measure trap** (ml vs oz vs parts) or a deliberate
ranking decision in the interpreter.

- **Backend docblocks are expected.** `/** … */` on classes, methods and
  properties is normal PHP practice and PHPStan needs the array shapes and
  generics. Keep writing those. The rule above governs inline `//` in PHP.
- **The stricter frontend variant** (no inline `//` at all, no descriptive
  JSDoc) lives in `cocktail-bar-web`'s `CLAUDE.md` and no longer applies to
  anything here.
- `no-workarounds`' `// WORKAROUND: …` escape valve stays sanctioned where it
  genuinely applies.

---

## Ways of working

- **Work on branches** and let the CI workflow run on the PR.
- **Two repos, one product.** A change to the JSON a client reads is a change to
  `cocktail-bar-web` too. Say so in the PR, and land the API side first so the
  client has something to develop against.
- **Schema changes are always a migration** (`php artisan make:migration`), never
  a hand-edited database. Do not run `migrate`, `migrate:fresh` or a seeder
  without saying what it will do first — `migrate:fresh` against the Sail Postgres
  volume destroys whatever stock and recipes you had been testing with.
- **Scope test runs** while iterating (`--filter`), and run `composer ci:check`
  before reporting work complete. If you did not run it, say so.
- **Small diffs.** No drive-by refactors, no renaming identifiers, no behaviour
  changes that were not asked for, no cleaning up unrelated code.
- **Prefer editing an existing file** over adding a new one; delete dead code
  outright rather than leaving compatibility shims.
- **Surface assumptions.** The domain here is full of judgement calls (is a
  garnish required? is bourbon an acceptable rye?). State the call you made;
  do not bury it in a condition.
- **Verified means verified.** A feature is done when its tests pass and someone
  has watched them pass. In learning mode that someone is normally me; you may
  run the suite to diagnose. Either way, say what was run and what it said.
  "Should work" is not done — and "I explained it correctly" is not done either.

### Project hard rules

1. **The interpreter stays pure and deterministic** — no Eloquent, no queries, no
   randomness inside the scoring, and unit-tested without a database.
2. **No interpretation logic in controllers, and none left to the client.**
   Controllers load, call the service and return a resource; the payload carries
   every decision the UI needs, named explicitly.
3. **Ingredient identity lives in the database**, never in a hardcoded PHP or
   TypeScript list. A hardcoded list is a second source of truth that will drift.
4. **Never hand-edit generated files**: `AGENTS.md` (Boost). `config/boost.php`
   keeps Boost out of this file — do not remove it.
5. **No dependency changes without approval**, backend or frontend.

---

## Adding a feature: checklist — and the order to learn it in

In learning mode this doubles as the curriculum. Walk me through it **one step at
a time**: explain the step, wait while I write it, review what I wrote, then move
on. Do not explain all seven at once — a wall of guidance is as easy to nod along
to as a finished diff, and the whole point is that each step costs me something.
Step 3 is the one worth slowing down on; it is also the one an interviewer will
ask about.

1. Read `AGENTS.md`, this file, and the skills that apply (§ *Skills*).
2. Migration + model + factory via artisan (`php artisan make:model … --help`
   first — factories and seeders matter here, because the matching logic is
   untestable without realistic stock and recipe fixtures).
3. Domain logic in its own service, pure and unit-tested. Write the test for the
   ranking *before* the ranking is "obviously right" — it never is.
4. Controller: load, call the service, return an **API Resource**. Route in
   `routes/api.php` under `/api` with a name, behind `auth:sanctum`.
5. Tell the web repo: add the path to its `API_ENDPOINTS`, a zod schema for the
   response, and an adapter if the shapes differ. The screen is built there, not
   here.
6. Feature test for the HTTP path (assert the JSON shape, not just the status),
   unit tests for the service.
7. `composer ci:check`. Then say what you ran and what it said.
