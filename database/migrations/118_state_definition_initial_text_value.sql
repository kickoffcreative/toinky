-- Migration 118: initial values for Preset / Free Text story values.
-- plot_state_definitions stored only default_numeric_value, so a Preset value's
-- starting state (e.g. RENEE_REVEALED = Hidden, VESSEL = None) existed nowhere
-- at runtime: simulation_state rows are created only when an effect first sets
-- the key, and condition checks use strict equality, so any condition gated on
-- an initial value could never pass in an organic run. Seeded stories were
-- unaffected because their migrations give them plot_entity_properties
-- initial_value rows instead. This adds the missing half: writers record the
-- initial value, and GenericStateService::initializePlotState seeds it into
-- simulation_state (skipped when a state alias already sources the key).
ALTER TABLE plot_state_definitions
  ADD COLUMN default_text_value VARCHAR(255) NULL AFTER default_numeric_value;
