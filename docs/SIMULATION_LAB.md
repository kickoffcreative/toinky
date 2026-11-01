# Game2 Simulation Lab

> Note (Nov 1, 2026): this file documents the Simulation Lab tool for
> engineering use. It is NOT a verification route for the Writer's
> Guide or the Story Auditor. Guide and Auditor verification is by
> live games, finished play pages, and the studio inventory only —
> Lab batches and Lab recaps are never cited as audit evidence.


The Simulation Lab runs the real Game2 PHP engine against a disposable MariaDB database. It never needs the production Game2 database.

## Web interface

The live **Story Engine Lab** is an administrator-only tool. Its header links back to the Admin dashboard, Simulation View, Story Reviews, Theme Manager, Security, Story Writer, and the public portal so administrators can move between operational areas without losing context.

Opening `lab.php` directly requires an authenticated administrator. The interface may create simulations, choose human or AI control per character, advance the story clock, make human-controlled decisions, and inspect the complete world and character audit trail.

## GitHub Actions

Open **Actions → Game2 Simulation Lab → Run workflow**.

Inputs:

- `plot`: story code, e.g. `EMPTY_THRONE`
- `runs`: number of simulations, e.g. `50`, `100`, `1000`
- `strategy`:
  - `no-action` — all players deliberately let decisions expire
  - `random` — every player selects a random currently valid option
  - `first` — every player selects the first currently valid option (useful for deterministic smoke tests)

Each run creates a temporary MariaDB service, resets it from `database/schema.sql`, `database/seed.sql`, and every migration, then runs the actual application services until simulations reach `COMPLETE`.

The workflow uploads `simulation-report.json` containing:

- outcome signature frequencies
- final factor ranges
- player/NPC action counts
- final world state
- final character goals, fears, locations and stats
- final resolution text
- integrity warnings

The job fails when the harness detects an integrity warning.

## Local disposable test

If Docker is installed:

```bash
docker compose -f docker-compose.test.yml up -d
GAME2_DATABASE_CONFIG="$PWD/config/database.test.php.example" php tools/simulate.php --reset --plot=EMPTY_THRONE --runs=50 --strategy=no-action
```

Random player test:

```bash
GAME2_DATABASE_CONFIG="$PWD/config/database.test.php.example" php tools/simulate.php --plot=EMPTY_THRONE --runs=100 --strategy=random
```

Full JSON report:

```bash
GAME2_DATABASE_CONFIG="$PWD/config/database.test.php.example" php tools/simulate.php --plot=EMPTY_THRONE --runs=100 --strategy=random --json > simulation-report.json
```

Stop the disposable database:

```bash
docker compose -f docker-compose.test.yml down
```

## Current integrity checks

The harness automatically flags:

- simulations that do not reach `COMPLETE`
- factors outside configured min/max values
- active NPC counts above the Game2 cast budget
- an NPC producing more than one world action at the same simulation second
- missing final resolution text
- database-configured story contradictions and engine invariants

Add future integrity rules only for actual contradictions or engine invariants. Story preferences should not be encoded as engine failures, and no PHP rule may be specific to a single story.

## Web Batch Run and player-view recaps (Oct 31–Nov 1, 2026)

The live Story Engine Lab also has a web Batch Run form (admin only):
Story, runs 1–5, strategy, duration, step, optional version override
(blank = published), recaps on, and a choice-script JSON textarea.
Results show simulation id, outcome, player / NPC / no-action counts,
scripted fired / never-fired entries, warnings, and collapsible
per-character recaps. Never refresh a batch results page — refreshing
re-runs the batch.

Player-view recaps carry six checks: placeholder leaks, empty beats,
label artifacts, delivery counts, endings present, and sidebar drift
(heuristic warning). Lab per-character recaps do not carry ending
fragment prose; check endings in the World storyboard (Lab) or on the
finished play page (live). Focused (seeded) runs are fragments —
scripts but no recaps or checks — and are never full-story evidence.
Lab cast, factors, and display values are copied from the live draft
at creation, even for pinned sims.

A single blank results page is not a finding. Reproduce the exact
spec once, check the Simulations list for the created run, and
capture the HTTP status and server error log if it recurs. A
headlines-with-zero-decisions game means check the pinned snapshot
sections first: a section added after that version published fails
closed before decision creation, and re-submit plus publish to
re-pin is the fix.
