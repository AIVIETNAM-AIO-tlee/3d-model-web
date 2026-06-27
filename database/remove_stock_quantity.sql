USE ecommerce_db;

SET @has_is_premium := (
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'Products'
            AND COLUMN_NAME = 'is_premium'
);

SET @ddl_is_premium := IF(
        @has_is_premium = 0,
        'ALTER TABLE Products ADD COLUMN is_premium TINYINT(1) NOT NULL DEFAULT 0 AFTER price',
        'SELECT "is_premium already exists"'
);

PREPARE stmt_is_premium FROM @ddl_is_premium;
EXECUTE stmt_is_premium;
DEALLOCATE PREPARE stmt_is_premium;

UPDATE Products
SET is_premium = 1
WHERE is_premium = 0
    AND LOWER(COALESCE(tags, '')) LIKE '%premium%';

SET @has_stock_quantity := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'Products'
      AND COLUMN_NAME = 'stock_quantity'
);

SET @ddl := IF(
    @has_stock_quantity > 0,
    'ALTER TABLE Products DROP COLUMN stock_quantity',
    'SELECT "stock_quantity already removed"'
);

PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
