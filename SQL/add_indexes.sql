-- Performance indexes for high-volume data
-- MySQL 8.0 compatible (no IF NOT EXISTS for indexes)

DROP PROCEDURE IF EXISTS add_index_if_missing;
DELIMITER //
CREATE PROCEDURE add_index_if_missing(
    IN tbl VARCHAR(64), IN idx VARCHAR(64), IN cols VARCHAR(255)
)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.statistics
        WHERE table_schema = DATABASE()
          AND table_name = tbl
          AND index_name = idx
        LIMIT 1
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', tbl, '` ADD INDEX `', idx, '` (', cols, ')');
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END //
DELIMITER ;

-- bookings: composite for overlap query (court_id, status, start_datetime)
CALL add_index_if_missing('bookings', 'idx_overlap', 'court_id, status, start_datetime');

-- bookings: date range filter
CALL add_index_if_missing('bookings', 'idx_start_datetime', 'start_datetime');

-- bookings: customer_phone lookup
CALL add_index_if_missing('bookings', 'idx_customer_phone', 'customer_phone(20)');

-- members: status filter
CALL add_index_if_missing('members', 'idx_status', 'status');

-- members: level + status composite
CALL add_index_if_missing('members', 'idx_level_status', 'member_level, status');

-- pricing_rules: price lookup composite
CALL add_index_if_missing('pricing_rules', 'idx_price_lookup', 'group_id, day_type, start_time, end_time');

DROP PROCEDURE IF EXISTS add_index_if_missing;
