# Game2 Reusable Story Engine Model

Game2 separates **story content** from **causal mechanics**. Bellweather is a test story, not part of the engine.

## Core Rule

A new story must be installable through data/migrations without adding story-specific PHP.

The engine understands only:

- entities;
- properties and values;
- locations;
- numeric factors;
- character stats;
- knowledge and relationships;
- conditions;
- effects;
- goals/fears and their lifecycle;
- decisions, triggered transitions, dynamic opportunities, and resolution fragments.

It does not understand the narrative meaning of a report, crown, reactor, witness, injured guest, kingdom, resort, or spaceship.

## 1. World Factors

Numeric story factors map to five reusable semantic types. A plot may use any visible label.

| Type | Meaning | Typical effects |
|---|---|---|
| SAFETY | Physical, operational, or environmental stability | hazards, urgency, injury, access, evacuation |
| INFORMATION | Reliable usable truth | clues, evidence, certainty, disclosure, proof |
| CONTROL | Ability to influence or impose outcomes | authority, leverage, possession, access |
| TENSION | Conflict, suspicion, and dramatic pressure | escalation, scrutiny, confrontation |
| COHESION | Ability to cooperate and rely on others | trust, loyalty, coordination, fragmentation |

World factors are continuous measurements, normally 0–100. They describe the condition of the story but are not substitutes for concrete facts.

## 2. Entities And Properties

Concrete story facts are represented as:

`ENTITY.PROPERTY = VALUE`

Entity types are intentionally broad:

- PERSON
- OBJECT
- PLACE
- INFORMATION
- TASK
- ABSTRACT

Examples:

- `GUEST.status = EVACUATED`
- `FAILED_REPORT.holder = FARAH`
- `WEST_ROUTE.status = USABLE`
- `PRINCE.status = EXILED`
- `CROWN.holder = PLAYER_2`
- `REACTOR.status = CRITICAL`

A story defines the allowed values and, when needed, legal transitions for each property. PHP contains no story-specific state lists.

## 3. Locations

Character location remains a first-class character state. Stories may define their own location hierarchy and labels.

Location conditions may be used by actions, goals, fears, triggered transitions, and dynamic opportunities.

## 4. Character State

Every character has:

- Location
- Goal
- Fear
- Stats
- Knowledge
- Relationships

Initial values remain preserved. Current values may change during play.

Stats are numeric and should represent traits, capabilities, situational conditions, or relationships that meaningfully affect choices/outcomes.

## 5. Goals And Fears

Goals and fears are evaluated from resulting state, not from the name of an action.

A goal may be satisfied by one or more state combinations. Design logic should remain intentionally limited to condition groups built from simple comparisons rather than arbitrary scripts.

Example:

`Goal: Remove the evidence`

Satisfied by ANY:

1. `FAILED_REPORT.status = DESTROYED`
2. `FAILED_REPORT.holder = SELF` AND `SELF.location_region = OUTSIDE`

Goal lifecycle:

`ACTIVE → ACHIEVED / FAILED / ABANDONED → replacement ACTIVE`

Fear lifecycle:

`ACTIVE → INTENSIFIED / REDUCED → RESOLVED / REALIZED → replacement ACTIVE`

## 6. Conditions

Reusable condition operators are deliberately small:

- EQ
- NEQ
- IN
- NOT_IN
- EXISTS
- NOT_EXISTS
- LT / LTE / GT / GTE where numeric comparison is supported

Conditions may examine entity/property state, world factors, location, goal/fear, knowledge, phase, stats, relationships, or prior action counts when supported by the calling subsystem.

Avoid turning conditions into a general programming language.

## 7. Effects

Actions and transitions may change:

- world factors;
- entity/property state;
- character location;
- goal;
- fear;
- stats;
- knowledge;
- relationships.

Actions do not directly declare goals achieved. They change the world; the goal lifecycle evaluates the resulting state.

## 8. Triggered Transitions

Triggered transitions implement:

`WHEN conditions are true → THEN effects occur`

They handle consequences that should happen regardless of whether a matching prewritten menu happens to appear at that moment.

## 9. Dynamic Opportunities

Fixed story beats provide pacing and shared orientation. Dynamic opportunities respond to unresolved state between beats.

The opportunity layer decides **when** a character should receive another meaningful decision. Once created, the normal decision/effect engine handles it.

## 10. Data-Driven Resolution

Endings are assembled from plot-defined resolution fragments selected by current state conditions. The engine does not contain Bellweather-specific ending prose.

A new story defines its own resolution sections and alternatives in data.

## 11. Story Design Boundary

The engine should remain boring and rigid. The story may be creative and complex.

If a future story requires PHP such as `if ($princeIsDead)` or `if ($reactorIsCritical)`, the design is wrong. Those conditions belong in story data.

The practical architecture is:

`Story Definition → Entity/Character State → Valid Actions → Decisions → Effects → State Changes → Goal/Fear Evaluation → New Opportunities → Resolution`
