-- Migration 117: authoring support for two runtime-supported features.
-- 1) No-action effects gain GOAL and FEAR scopes. CausalService::applyEffect already
--    applies GOAL/FEAR effects (setMotive); only this ENUM, the authoring service
--    whitelist, and the studio form excluded them, which made designed inaction
--    fallbacks (e.g. a climax goal defaulting on timeout) unauthorable.
-- 2) Option conditions (plot_option_conditions) already exist and are enforced by
--    the runtime; this migration only widens the no-action ENUM — the option
--    condition authoring itself is service/UI work in the same commit.
ALTER TABLE plot_no_action_effects
  MODIFY effect_scope ENUM('FACTOR','STAT','LOCATION','KNOWLEDGE','WORLD_STATE','GOAL','FEAR') NOT NULL;
