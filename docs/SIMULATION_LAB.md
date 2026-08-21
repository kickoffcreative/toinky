# Game2 Simulation Lab

The Simulation Lab runs the real Game2 PHP engine against a disposable MariaDB database. It never needs the production Game2 database.

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
- Empty Throne resolution claiming the abdication letter survives when authoritative state says it was destroyed

Add future integrity rules only for actual contradictions or engine invariants. Story preferences should not be encoded as engine failures.
