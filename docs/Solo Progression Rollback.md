# Solo Progression Rollback

The optional **Skip Quiet Time** control advances a one-person hosted game to
the next public or personal update, open player decision, or completion. Each
request performs at most 120 normal engine steps of at most 30 story seconds.
A long silent interval can therefore require another request. It is a way to
skip waiting, not a promise to pause decision deadlines or replace timed play.

The server requires the host to be the only registered participant and the only
human-controlled character. It checks ownership and open decisions under the
same per-game lock as choices and polling. It reconciles opportunities before
advancing so a new choice can stop the skip. Other players cannot use it.

Migration 141 adds `games.solo_time_offset_seconds`, default zero. Both the
engine clock and displayed elapsed time include this offset. The real start
timestamp remains unchanged, and normal polling continues after a skip. The
feature uses pinned content through the normal engine; it does not read new
draft events into an existing game.

## Source Rollback

Restore the code revision before this feature if needed. Keep the new column:
older code ignores it, and dropping it is unnecessary for a code rollback.
An older release does not understand skipped time, so an in-progress advanced
game can wait for real elapsed time to catch up after rollback. Finish active
solo games before rollback where practical. Do not rewrite start timestamps or
completed game history to hide this difference.

Studio draft repairs have separate rollback documents and are not reversed by
a code deployment. No schema migration in this batch edits story content.

## Verification

`tools/solo_advance_test.php` exercises an isolated seeded database: lobby and
non-host rejection, positive advancement, preserved start time, recorded offset,
continued polling time, stopping for an open choice, multiplayer exclusion,
released locks, and a real engine completion path. The polling fixture also
checks the continue button's progress state. These checks passed locally on
PHP 8.4 and MariaDB 11.4. Live browser and published-story checks remain pending.
