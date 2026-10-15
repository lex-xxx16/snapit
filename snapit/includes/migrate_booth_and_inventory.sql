-- Add countdown_seconds and mirror_preview columns to guest_sessions
-- Idempotent migration: only add columns if they do not exist

SET @dbname = DATABASE();
SET @tablename = 'guest_sessions';

-- countdown_seconds
SET @colname = 'countdown_seconds';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @dbname
    AND TABLE_NAME = @tablename
    AND COLUMN_NAME = @colname) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE `', @tablename, '` ADD COLUMN `', @colname, '` TINYINT NOT NULL DEFAULT 3 COMMENT ''Countdown seconds per capture: 3, 5, or 10''')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- mirror_preview
SET @colname = 'mirror_preview';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @dbname
    AND TABLE_NAME = @tablename
    AND COLUMN_NAME = @colname) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE `', @tablename, '` ADD COLUMN `', @colname, '` TINYINT(1) NOT NULL DEFAULT 1 COMMENT ''1 = mirror (scaleX -1) preview; 0 = unmirrored''')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- inventory_deducted flag on bookings to prevent double consumption deduction at booking-complete time
SET @tablename = 'bookings';
SET @colname = 'inventory_deducted';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @dbname
    AND TABLE_NAME = @tablename
    AND COLUMN_NAME = @colname) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE `', @tablename, '` ADD COLUMN `', @colname, '` TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''1 = inventory already deducted when completing booking''')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;
