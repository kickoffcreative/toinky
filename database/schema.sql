CREATE TABLE IF NOT EXISTS plots (
  plot_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(100) NOT NULL UNIQUE,
  title VARCHAR(180) NOT NULL,
  archetype VARCHAR(120) NOT NULL,
  genre VARCHAR(120) NOT NULL,
  setting_name VARCHAR(180) NOT NULL,
  max_players TINYINT UNSIGNED NOT NULL DEFAULT 3,
  max_npcs TINYINT UNSIGNED NOT NULL DEFAULT 3,
  status ENUM('draft','active','retired') NOT NULL DEFAULT 'active',
  created_at_utc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plot_factors (
  plot_factor_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  plot_id BIGINT UNSIGNED NOT NULL,
  factor_code VARCHAR(100) NOT NULL,
  label VARCHAR(180) NOT NULL,
  initial_value INT NOT NULL,
  min_value INT NOT NULL DEFAULT 0,
  max_value INT NOT NULL DEFAULT 100,
  description TEXT NULL,
  UNIQUE KEY uq_plot_factor(plot_id,factor_code),
  CONSTRAINT fk_pf_plot FOREIGN KEY(plot_id) REFERENCES plots(plot_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plot_roles (
  plot_role_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  plot_id BIGINT UNSIGNED NOT NULL,
  role_code VARCHAR(100) NOT NULL,
  role_type ENUM('PLAYER','NPC') NOT NULL,
  slot_no TINYINT UNSIGNED NOT NULL,
  name VARCHAR(180) NOT NULL,
  public_description TEXT NOT NULL,
  private_knowledge TEXT NULL,
  starting_location VARCHAR(180) NOT NULL,
  goal_text TEXT NOT NULL,
  fear_text TEXT NULL,
  UNIQUE KEY uq_plot_role(plot_id,role_type,slot_no),
  UNIQUE KEY uq_plot_role_code(plot_id,role_code),
  CONSTRAINT fk_pr_plot FOREIGN KEY(plot_id) REFERENCES plots(plot_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plot_role_stats (
  plot_role_stat_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  plot_role_id BIGINT UNSIGNED NOT NULL,
  stat_code VARCHAR(100) NOT NULL,
  label VARCHAR(180) NOT NULL,
  initial_value INT NOT NULL,
  UNIQUE KEY uq_prs(plot_role_id,stat_code),
  CONSTRAINT fk_prs_role FOREIGN KEY(plot_role_id) REFERENCES plot_roles(plot_role_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS simulations (
  simulation_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  plot_id BIGINT UNSIGNED NOT NULL,
  duration_seconds INT UNSIGNED NOT NULL DEFAULT 300,
  mode ENUM('MANUAL','REALTIME') NOT NULL DEFAULT 'MANUAL',
  status ENUM('READY','RUNNING','COMPLETE') NOT NULL DEFAULT 'READY',
  current_phase ENUM('SETUP','EXPOSITION','INCITING_INCIDENT','RISING_ACTION','CLIMAX','RESOLUTION','COMPLETE') NOT NULL DEFAULT 'SETUP',
  elapsed_seconds INT UNSIGNED NOT NULL DEFAULT 0,
  started_at_utc DATETIME NULL,
  ended_at_utc DATETIME NULL,
  created_at_utc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_sim_plot FOREIGN KEY(plot_id) REFERENCES plots(plot_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS simulation_factors (
  simulation_factor_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  simulation_id BIGINT UNSIGNED NOT NULL,
  factor_code VARCHAR(100) NOT NULL,
  label VARCHAR(180) NOT NULL,
  current_value INT NOT NULL,
  min_value INT NOT NULL,
  max_value INT NOT NULL,
  UNIQUE KEY uq_sim_factor(simulation_id,factor_code),
  CONSTRAINT fk_sf_sim FOREIGN KEY(simulation_id) REFERENCES simulations(simulation_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS characters (
  character_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  simulation_id BIGINT UNSIGNED NOT NULL,
  plot_role_id BIGINT UNSIGNED NOT NULL,
  actor_type ENUM('PLAYER','NPC') NOT NULL,
  slot_no TINYINT UNSIGNED NOT NULL,
  name VARCHAR(180) NOT NULL,
  description TEXT NOT NULL,
  private_knowledge TEXT NULL,
  current_location VARCHAR(180) NOT NULL,
  goal_text TEXT NOT NULL,
  fear_text TEXT NULL,
  status ENUM('ACTIVE','INCAPACITATED','REMOVED') NOT NULL DEFAULT 'ACTIVE',
  UNIQUE KEY uq_sim_actor_slot(simulation_id,actor_type,slot_no),
  CONSTRAINT fk_char_sim FOREIGN KEY(simulation_id) REFERENCES simulations(simulation_id) ON DELETE CASCADE,
  CONSTRAINT fk_char_role FOREIGN KEY(plot_role_id) REFERENCES plot_roles(plot_role_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS character_stats (
  character_stat_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  character_id BIGINT UNSIGNED NOT NULL,
  stat_code VARCHAR(100) NOT NULL,
  label VARCHAR(180) NOT NULL,
  current_value INT NOT NULL,
  UNIQUE KEY uq_char_stat(character_id,stat_code),
  CONSTRAINT fk_cs_char FOREIGN KEY(character_id) REFERENCES characters(character_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS character_knowledge (
  character_knowledge_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  character_id BIGINT UNSIGNED NOT NULL,
  knowledge_key VARCHAR(190) NOT NULL,
  knowledge_text TEXT NOT NULL,
  state ENUM('UNKNOWN','SUSPECTED','HEARSAY','BELIEVED','KNOWN','FALSE_BELIEF') NOT NULL DEFAULT 'KNOWN',
  confidence INT NOT NULL DEFAULT 100,
  learned_at_second INT UNSIGNED NOT NULL DEFAULT 0,
  UNIQUE KEY uq_char_knowledge(character_id,knowledge_key),
  CONSTRAINT fk_ck_char FOREIGN KEY(character_id) REFERENCES characters(character_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS relationships (
  relationship_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  simulation_id BIGINT UNSIGNED NOT NULL,
  from_character_id BIGINT UNSIGNED NOT NULL,
  to_character_id BIGINT UNSIGNED NOT NULL,
  trust INT NOT NULL DEFAULT 50,
  suspicion INT NOT NULL DEFAULT 0,
  loyalty INT NOT NULL DEFAULT 50,
  UNIQUE KEY uq_relationship(simulation_id,from_character_id,to_character_id),
  CONSTRAINT fk_rel_sim FOREIGN KEY(simulation_id) REFERENCES simulations(simulation_id) ON DELETE CASCADE,
  CONSTRAINT fk_rel_from FOREIGN KEY(from_character_id) REFERENCES characters(character_id) ON DELETE CASCADE,
  CONSTRAINT fk_rel_to FOREIGN KEY(to_character_id) REFERENCES characters(character_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS simulation_events (
  event_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  simulation_id BIGINT UNSIGNED NOT NULL,
  event_second INT UNSIGNED NOT NULL,
  phase ENUM('SETUP','EXPOSITION','INCITING_INCIDENT','RISING_ACTION','CLIMAX','RESOLUTION','COMPLETE') NOT NULL,
  event_type ENUM('WORLD','PLAYER','NPC','SYSTEM') NOT NULL,
  actor_character_id BIGINT UNSIGNED NULL,
  event_code VARCHAR(120) NOT NULL,
  headline VARCHAR(255) NOT NULL,
  event_text TEXT NOT NULL,
  visibility ENUM('WORLD','PRIVATE','DIRECTOR') NOT NULL DEFAULT 'WORLD',
  caused_by_event_id BIGINT UNSIGNED NULL,
  created_at_utc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_sim_events(simulation_id,event_second,event_id),
  CONSTRAINT fk_se_sim FOREIGN KEY(simulation_id) REFERENCES simulations(simulation_id) ON DELETE CASCADE,
  CONSTRAINT fk_se_actor FOREIGN KEY(actor_character_id) REFERENCES characters(character_id) ON DELETE SET NULL,
  CONSTRAINT fk_se_cause FOREIGN KEY(caused_by_event_id) REFERENCES simulation_events(event_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS character_history (
  history_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  simulation_id BIGINT UNSIGNED NOT NULL,
  character_id BIGINT UNSIGNED NOT NULL,
  event_id BIGINT UNSIGNED NULL,
  event_second INT UNSIGNED NOT NULL,
  history_type ENUM('EXPERIENCED','OBSERVED','INTERACTION','DECISION','NO_ACTION','LOCATION','STAT_CHANGE','KNOWLEDGE','RELATIONSHIP','CONSEQUENCE') NOT NULL,
  title VARCHAR(255) NOT NULL,
  detail_text TEXT NOT NULL,
  created_at_utc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_char_history(character_id,event_second,history_id),
  CONSTRAINT fk_ch_sim FOREIGN KEY(simulation_id) REFERENCES simulations(simulation_id) ON DELETE CASCADE,
  CONSTRAINT fk_ch_char FOREIGN KEY(character_id) REFERENCES characters(character_id) ON DELETE CASCADE,
  CONSTRAINT fk_ch_event FOREIGN KEY(event_id) REFERENCES simulation_events(event_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS decisions (
  decision_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  simulation_id BIGINT UNSIGNED NOT NULL,
  character_id BIGINT UNSIGNED NOT NULL,
  event_id BIGINT UNSIGNED NOT NULL,
  prompt_text TEXT NOT NULL,
  status ENUM('OPEN','CHOSEN','NO_ACTION','CLOSED') NOT NULL DEFAULT 'OPEN',
  opens_at_second INT UNSIGNED NOT NULL,
  closes_at_second INT UNSIGNED NOT NULL,
  chosen_option_code VARCHAR(100) NULL,
  resolved_text TEXT NULL,
  UNIQUE KEY uq_decision_character_event(character_id,event_id),
  CONSTRAINT fk_dec_sim FOREIGN KEY(simulation_id) REFERENCES simulations(simulation_id) ON DELETE CASCADE,
  CONSTRAINT fk_dec_char FOREIGN KEY(character_id) REFERENCES characters(character_id) ON DELETE CASCADE,
  CONSTRAINT fk_dec_event FOREIGN KEY(event_id) REFERENCES simulation_events(event_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS decision_options (
  decision_option_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  decision_id BIGINT UNSIGNED NOT NULL,
  option_code VARCHAR(100) NOT NULL,
  label VARCHAR(500) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  UNIQUE KEY uq_decision_option(decision_id,option_code),
  CONSTRAINT fk_do_decision FOREIGN KEY(decision_id) REFERENCES decisions(decision_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
