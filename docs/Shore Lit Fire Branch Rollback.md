# Shore Lit Fire Branch Rollback

Studio inventory November 12, 2026, FORGOTTEN_SHORE plot 5, GROUPS_FATE:

- Order 10: SIGNAL_FIRE EQ Spotted.
- Order 20: SIGNAL_FIRE NOT_IN Spotted AND SAFETY GTE 30.
- Orders 30, 40, 50: SIGNAL_FIRE NOT_IN Lit,Spotted AND SAFETY LT 30,
  further partitioned by VESSEL and SPIRIT_POWER.

All five existing variants have priority 100. Therefore SIGNAL_FIRE Lit AND
SAFETY below 30 selects none of them. The new fragment addresses only that gap,
using SIGNAL_FIRE EQ Lit AND SAFETY LT 30, priority 100, section order 20.
It describes waiting without rescue, not an untracked death or departure.

The new fragment did not exist before this batch. Deactivate it through the
studio to roll back; preserve the original five variants and their conditions.
A new version and fresh low-safety lit-fire path are required for verification.
