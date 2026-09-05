# Reviewing a Toinky Story

The recommended workflow combines authoring rules, structural validation, repeatable simulations, external qualitative review, and a final human decision. A language-model review by itself is not sufficient evidence that a story is playable or structurally sound.

Story Writer authors can use Inky's **Improve Story** menu for bounded,
advice-only reviews before validation. The current actions are **Review Plot**,
**Review Characters**, **Review Opportunities**, **Find Dependency Problems**,
**Quality Check**, and **Prepare Focus Tests**. They read the current authored
story package, keep an action-specific result header, cite stable keys, and do
not save changes. Their prerequisites, busy/cancel behavior, and result rules
are defined in [docs/INKY_ACTION_RULES.md](INKY_ACTION_RULES.md).

## Recommended sequence

1. Define locations, story values, allowed preset values, and ending sections in **Story Writer → Overview**.
2. Author characters, timed world events, decisions, options, effects, conditional opportunities, and endings using those managed definitions.
3. Run **Validate & Submit** to catch blocking references and freeze a numbered revision.
4. In **Admin → Story Reviews**, run **Focused Opportunity (Seeded)** for branches that need local verification, then queue broad batches for **All Human (Random)**, **All Human (Biased)**, and **All AI**.
5. Compare focused choice coverage first, then compare broad outcome families, warning frequency, timed-out choices, choice frequency, and options not observed in the sample.
6. Use Inky's Improve Story reviews or copy the generated reviewer package into a capable external reviewer. Require evidence tied to stable keys.
7. Import only the safe correction file. Treat chronology, ordering, activation, cast, dependencies, and stable-key changes as manual structural advisories.
8. Run validation and simulations again, then approve or publish the numbered revision only after human review.

## What the simulation modes mean

- **All Human (Random)** chooses each available option uniformly. It is useful for broad branch sampling without author weights.
- **All Human (Biased)** chooses according to **AI Choice Weight**. It approximates the paths the authored biases make more likely.
- **All AI** gives every character to the shared AI decision system. It tests knowledge, rationale, and continuity behavior.
- **Focused Opportunity (Seeded)** creates an isolated checkpoint that satisfies one opportunity's active conditions, exercises every active choice an equal number of times, and then lets later events and opportunities continue normally. It proves that the local branch can be delivered and applied from that manufactured state. It does **not** prove organic reachability from story start.

An unobserved option is not automatically unreachable. Increase the run count, compare modes, and inspect its conditions before treating it as a dependency defect.

## Correction safety boundary

Automated correction imports may revise prose, decision windows, and AI weights within validated limits. They cannot change event timing, phases, sort order, stable keys, activation flags, cast structure, section order, or priority. Structural recommendations belong in the JSON `advisories` array and require a writer to make and review the change manually.

Each import is transactional and creates a new numbered revision. A malformed value or protected field rejects the complete package, so partial reviewer edits cannot leak into a story.

## Durable batch worker

Simulation batches are stored in the database and survive browser refreshes. Production should run the worker every minute through cPanel cron:

```bash
/opt/cpanel/ea-php84/root/usr/bin/php /home/toinkyadmin/public_html/tools/process_simulation_review_batches.php --max=5
```

Manual queue processing is disabled to prevent overlapping workers. Configure the cron for unattended batches; a web request is not kept open while every simulation runs.
