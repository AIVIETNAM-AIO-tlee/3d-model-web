USE ecommerce_db;

START TRANSACTION;

SET @has_is_premium := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'Products'
      AND COLUMN_NAME = 'is_premium'
);

SET @ddl_add_is_premium := IF(
    @has_is_premium = 0,
    'ALTER TABLE Products ADD COLUMN is_premium TINYINT(1) NOT NULL DEFAULT 0 AFTER price',
    'SELECT "is_premium already exists"'
);

PREPARE stmt_add_is_premium FROM @ddl_add_is_premium;
EXECUTE stmt_add_is_premium;
DEALLOCATE PREPARE stmt_add_is_premium;

-- Mark premium products based on existing tags containing "premium"
UPDATE Products
SET is_premium = 1
WHERE LOWER(COALESCE(tags, '')) LIKE '%premium%';

-- Optional safety: non-premium products should have price 0
UPDATE Products
SET price = 0
WHERE is_premium = 0;

COMMIT;
