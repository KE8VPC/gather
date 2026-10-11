-- Gather 1.2: safe to rerun on an existing Gather database.
CREATE TABLE IF NOT EXISTS linked_accounts (
 user_id BIGINT UNSIGNED NOT NULL,
 provider VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 external_id VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 display_name VARCHAR(200) NOT NULL,
 access_token TEXT NOT NULL,
 refresh_token TEXT NOT NULL,
 expires_at BIGINT NOT NULL,
 generation CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 connected_at BIGINT NOT NULL,
 last_sync_at BIGINT NOT NULL DEFAULT 0,
 PRIMARY KEY(user_id,provider),
 UNIQUE KEY uq_linked_identity(provider,external_id),
 CONSTRAINT fk_linked_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS linked_imports (
 user_id BIGINT UNSIGNED NOT NULL,
 provider VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 creator_id CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 PRIMARY KEY(user_id,provider,creator_id),
 CONSTRAINT fk_import_account FOREIGN KEY(user_id,provider) REFERENCES linked_accounts(user_id,provider) ON DELETE CASCADE,
 CONSTRAINT fk_import_subscription FOREIGN KEY(user_id,creator_id) REFERENCES subscriptions(user_id,creator_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
