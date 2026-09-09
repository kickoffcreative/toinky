CREATE TABLE IF NOT EXISTS email_templates (
  template_key VARCHAR(80) PRIMARY KEY,
  label_text VARCHAR(140) NOT NULL,
  subject_template VARCHAR(255) NOT NULL,
  html_template LONGTEXT NOT NULL,
  text_template LONGTEXT NOT NULL,
  css_template TEXT NOT NULL,
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  updated_by_user_id BIGINT UNSIGNED NULL,
  updated_at_utc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_email_template_user FOREIGN KEY(updated_by_user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
