# Bellweather Rescue State Rollback

Captured in the studio November 12, 2026 before the state repair.
Story CONCEALED_FAILURE, plot 1, 15 existing version records.

## Confirmed Path

PRESSURE_RISE / WREN / EVAC_GUEST sets GUEST_STATUS EVACUATING and
GUEST_EVACUATION_STATUS MOVING, moves Wren to West Corridor, and changes
Safety by 10. Its result describes starting the carry with Leila.

WREN_FINAL_RESCUE_CHOICE / COMPLETE_WEST_CARRY describes completing the
carry beyond the failing east wing. Its existing effects are:

- WORLD_STATE GUEST_EVACUATION_STATUS = EVACUATED.
- LOCATION SELF = West Evacuation Point (two rows; target-role selections
  must be preserved; summaries alone do not identify every recipient).
- FACTOR SAFETY +4.

It has no GUEST_STATUS assignment. Leila's later main-event options test
GUEST_STATUS NEQ EVACUATED, so the two rescue state keys can disagree.

## Batch Repair And Rollback

Add active WORLD_STATE GUEST_STATUS effects to COMPLETE_WEST_CARRY:
EVACUATING at order 1, then EVACUATED at order 5. The first is a no-op on an
already-moving route and starts the legal transition on a prepared route.
Add GUEST_STATUS AT_RISK at order 5 to SHELTER_AT_FIRE_DOOR and
BREAK_FOR_WIDER_WARNING, matching their existing evacuation-status assignment.
Add an opportunity condition GUEST_STATUS IN STABILIZED,EVACUATING,AT_RISK.
Existing state assignments, character targets and factor effects are preserved.

Original opportunity prompt:

The stretcher has reached the west corridor, but the floor moves again beneath your feet. Leila can finish the carry with you now. You could instead shelter the guest at the fire door, or leave the stretcher long enough to drive everyone else farther from the east wing.

The revised prompt must cover both a prepared carry and an already-moving one.
Before this batch, SHELTER_AT_FIRE_DOOR and BREAK_FOR_WIDER_WARNING each set
only GUEST_EVACUATION_STATUS AT_RISK, not canonical GUEST_STATUS.

To roll back the additions, deactivate the new GUEST_STATUS effects and new
opportunity condition, restore the prompt above, and submit/review a fixed
revision, or restore the preserved earlier published revision. Do not delete
existing effects or modify finished game history. Fresh-version gameplay
verification remains required.
