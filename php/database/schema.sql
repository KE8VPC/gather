-- Gather 1.0: import once into an EMPTY MySQL 8.0+ / MariaDB 10.6+ database.
CREATE TABLE users (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 email VARCHAR(254) NOT NULL,
 name VARCHAR(80) NOT NULL,
 password_hash VARCHAR(255) NOT NULL,
 session_version INT NOT NULL DEFAULT 1,
 created_at BIGINT NOT NULL,
 UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE creators (
 id CHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 name VARCHAR(500) NOT NULL,
 platform VARCHAR(20) NOT NULL,
 url TEXT NOT NULL,
 feed TEXT NOT NULL,
 synced_at BIGINT NOT NULL DEFAULT 0,
 error VARCHAR(500) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE subscriptions (
 user_id BIGINT UNSIGNED NOT NULL,
 creator_id CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 created_at BIGINT NOT NULL,
 PRIMARY KEY(user_id, creator_id),
 KEY idx_subscriptions_creator (creator_id),
 CONSTRAINT fk_sub_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
 CONSTRAINT fk_sub_creator FOREIGN KEY(creator_id) REFERENCES creators(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE items (
 id CHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 creator_id CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 title VARCHAR(500) NOT NULL,
 url TEXT NOT NULL,
 image TEXT NULL,
 media TEXT NULL,
 published BIGINT NOT NULL DEFAULT 0,
 live TINYINT NOT NULL DEFAULT 0,
 checked_at BIGINT NOT NULL DEFAULT 0,
 KEY idx_items_creator_date (creator_id,published),
 CONSTRAINT fk_item_creator FOREIGN KEY(creator_id) REFERENCES creators(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE auth_limits (
 bucket CHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 attempts INT UNSIGNED NOT NULL DEFAULT 0,
 expires_at BIGINT NOT NULL,
 KEY idx_auth_limits_expiry(expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
