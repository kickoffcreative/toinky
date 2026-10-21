-- Per-player game archive: a player can archive a completed game out of
-- their own Your Games list without deleting the game record.
ALTER TABLE game_players
  ADD COLUMN archived_at_utc DATETIME NULL;
