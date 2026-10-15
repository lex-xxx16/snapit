-- Adds users.avatar (profile picture filename). Idempotent.
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'avatar');
SET @sql := IF(@c = 0, 'ALTER TABLE users ADD COLUMN avatar VARCHAR(255) NULL AFTER status', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
