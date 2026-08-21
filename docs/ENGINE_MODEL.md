# Game2 Reusable Story Engine Model

Game2 separates **story content** from **causal mechanics**.

## 1. World Factors

Every numeric story factor should map to one of five reusable semantic types. A plot may use its own visible label.

| Type | Meaning | Typical character effects | Typical world effects |
|---|---|---|---|
| SAFETY | Physical, operational, or environmental stability | urgency, fear pressure, risk-taking, protection priorities | hazards, access, injury, evacuation, failure |
| INFORMATION | Reliable usable truth | beliefs, confidence, investigation, disclosure | proof, secrecy, discovery, evidence preservation |
| CONTROL | Ability to influence or impose outcomes | confidence, resistance, self-preservation | authority, possession, access, movement, leverage |
| TENSION | Conflict, suspicion, and dramatic pressure | caution, confrontation, fear | escalation, attention, accusations, conflict intensity |
| COHESION | Ability to cooperate and rely on others | loyalty, disclosure, willingness to help | coordination, fragmentation, collective effectiveness |

Examples: Evidence, Clues, Intelligence, and Certainty can all map to INFORMATION. Farah Control, Political Influence, Command Authority, or Family Power can all map to CONTROL.

## 2. Character State

Every character has:

- Location
- Goal
- Fear
- Stats
- Knowledge
- Relationships

Location, Goal, Fear, Stats, Knowledge, and Relationships may change during play. Initial values remain preserved through plot-role data and history.

## 3. Objective State

Concrete story facts are stored as finite states in `simulation_state`.

Examples:

- an injured person's condition
- who possesses an object
- whether a route is open
- whether a door is locked
- whether a witness has escaped
- whether a machine is operational

These keys are plot-specific. The engine does not need to know their narrative meaning.

## 4. Triggered Transitions

`plot_triggered_transitions` provides reusable causal rules:

WHEN all conditions are true
THEN apply one or more effects.

Effects can change:

- world factors
- world/objective state
- character location
- character goal
- character fear
- character stats
- character knowledge
- relationships

Triggered transitions are evaluated independently of a specific decision menu. This prevents a logically necessary consequence from disappearing merely because no prewritten option exists at the exact next story beat.

## 5. Decision Logic

Story randomness chooses among **valid plausible behavior**. State rules decide what is possible.

A decision option may only appear when its prerequisites are true. NPC weights determine preference among the remaining valid choices.

## 6. Design Rule For New Stories

A new story should define:

1. characters and initial character state;
2. plot-specific objective states;
3. up to five meaningful world factors mapped to the reusable factor types;
4. events/perceptions;
5. choices and their effects;
6. prerequisites for choices;
7. triggered transitions for causal consequences that should occur whenever conditions become true.

The PHP engine should not contain story names, locations, objects, or bespoke plot branches unless they are generic engine concepts.
