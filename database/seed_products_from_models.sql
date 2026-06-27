USE ecommerce_db;

START TRANSACTION;

INSERT INTO Categories (name, description)
SELECT 'Vehicle', 'Cars, trucks, and transport-related 3D products'
WHERE NOT EXISTS (
    SELECT 1 FROM Categories WHERE name = 'Vehicle'
);

INSERT INTO Categories (name, description)
SELECT 'Lifestyle', 'Daily life objects and environment assets'
WHERE NOT EXISTS (
    SELECT 1 FROM Categories WHERE name = 'Lifestyle'
);

INSERT INTO Categories (name, description)
SELECT 'Character', 'Character-focused 3D products and avatar assets'
WHERE NOT EXISTS (
    SELECT 1 FROM Categories WHERE name = 'Character'
);

SET @vehicle_id = (SELECT id FROM Categories WHERE name = 'Vehicle' ORDER BY id LIMIT 1);
SET @lifestyle_id = (SELECT id FROM Categories WHERE name = 'Lifestyle' ORDER BY id LIMIT 1);
SET @character_id = (SELECT id FROM Categories WHERE name = 'Character' ORDER BY id LIMIT 1);

INSERT INTO Products (category_id, name, description, price, is_premium, image_url, model_3d_url, tags)
SELECT @vehicle_id, 'Free Cyberpunk Hovercar', 'Futuristic hovercar concept model for sci-fi scenes.', 1490000, 0, 'images/hovercar-thumb.svg', 'assets/models/free_cyberpunk_hovercar.glb', 'vehicle,cyberpunk,hovercar'
WHERE NOT EXISTS (
    SELECT 1 FROM Products WHERE model_3d_url = 'assets/models/free_cyberpunk_hovercar.glb'
);

INSERT INTO Products (category_id, name, description, price, is_premium, image_url, model_3d_url, tags)
SELECT @character_id, 'Free Fire 3D Model', 'Character-style fire-themed 3D asset.', 990000, 1, 'images/product-placeholder.svg', 'assets/models/free_fire_3d_model.glb', 'character,fire,premium'
WHERE NOT EXISTS (
    SELECT 1 FROM Products WHERE model_3d_url = 'assets/models/free_fire_3d_model.glb'
);

INSERT INTO Products (category_id, name, description, price, is_premium, image_url, model_3d_url, tags)
SELECT @character_id, 'Free Fire Tattoo Bundle', 'Tattoo accessory bundle designed for stylized avatars.', 790000, 1, 'images/product-placeholder.svg', 'assets/models/free_fire_tatto_bundle.glb', 'bundle,tattoo,avatar,premium'
WHERE NOT EXISTS (
    SELECT 1 FROM Products WHERE model_3d_url = 'assets/models/free_fire_tatto_bundle.glb'
);

INSERT INTO Products (category_id, name, description, price, is_premium, image_url, model_3d_url, tags)
SELECT @lifestyle_id, 'Free iPhone 13 Pro 2021', 'Detailed smartphone model for product showcase scenes.', 1190000, 0, 'images/product-placeholder.svg', 'assets/models/free_iphone_13_pro_2021.glb', 'smartphone,tech,lifestyle'
WHERE NOT EXISTS (
    SELECT 1 FROM Products WHERE model_3d_url = 'assets/models/free_iphone_13_pro_2021.glb'
);

INSERT INTO Products (category_id, name, description, price, is_premium, image_url, model_3d_url, tags)
SELECT @character_id, 'Hero Model', 'Hero-grade character model for high-impact scenes.', 1590000, 1, 'images/product-placeholder.svg', 'assets/models/hero_model.glb', 'hero,character,premium'
WHERE NOT EXISTS (
    SELECT 1 FROM Products WHERE model_3d_url = 'assets/models/hero_model.glb'
);

UPDATE Products p
LEFT JOIN Categories c ON c.id = p.category_id
SET p.category_id = @character_id,
    p.is_premium = 1,
    p.tags = CASE
        WHEN LOWER(COALESCE(p.tags, '')) LIKE '%premium%' THEN p.tags
        WHEN p.tags IS NULL OR TRIM(p.tags) = '' THEN 'premium'
        ELSE CONCAT(TRIM(p.tags), ',premium')
    END
WHERE p.model_3d_url IN (
    'assets/models/free_fire_3d_model.glb',
    'assets/models/free_fire_tatto_bundle.glb'
);

DELETE FROM Categories
WHERE name = 'Premium'
    AND NOT EXISTS (
        SELECT 1 FROM Products p WHERE p.category_id = Categories.id
    );

INSERT INTO Products (category_id, name, description, price, is_premium, image_url, model_3d_url, tags)
SELECT @lifestyle_id, 'Log Cabin Free Download', 'Rustic log cabin structure model for environment builds.', 1290000, 0, 'images/product-placeholder.svg', 'assets/models/log_cabin_free_download.glb', 'cabin,environment,lifestyle'
WHERE NOT EXISTS (
    SELECT 1 FROM Products WHERE model_3d_url = 'assets/models/log_cabin_free_download.glb'
);

INSERT INTO Products (category_id, name, description, price, is_premium, image_url, model_3d_url, tags)
SELECT @vehicle_id, 'Rank 3 Police Unit', 'Police unit vehicle model for urban scenarios.', 1090000, 0, 'images/truck-thumb.svg', 'assets/models/rank_3_police_unit.glb', 'vehicle,police,urban'
WHERE NOT EXISTS (
    SELECT 1 FROM Products WHERE model_3d_url = 'assets/models/rank_3_police_unit.glb'
);

INSERT INTO Products (category_id, name, description, price, is_premium, image_url, model_3d_url, tags)
SELECT @vehicle_id, 'Rusty Old Truck Free Raw Scan', 'Raw-scanned old truck model with realistic wear details.', 1390000, 0, 'images/truck-thumb.svg', 'assets/models/rusty_old_truck_free_raw_scan.glb', 'truck,vehicle,rawscan'
WHERE NOT EXISTS (
    SELECT 1 FROM Products WHERE model_3d_url = 'assets/models/rusty_old_truck_free_raw_scan.glb'
);

CREATE TABLE IF NOT EXISTS Coupon_Codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(64) NOT NULL,
    discount_type ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
    discount_value DECIMAL(10,2) NOT NULL DEFAULT 0,
    min_order_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    max_discount_amount DECIMAL(10,2) NULL,
    usage_limit INT NULL,
    used_count INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    starts_at DATETIME NULL,
    expires_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_coupon_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO Coupon_Codes (code, discount_type, discount_value, min_order_amount, max_discount_amount, usage_limit, is_active)
VALUES
    ('WELCOME10', 'percent', 10, 200000, 500000, NULL, 1),
    ('SAVE5', 'fixed', 50000, 100000, NULL, NULL, 1),
    ('PREMIUM15', 'percent', 15, 400000, 1000000, NULL, 1)
ON DUPLICATE KEY UPDATE
    discount_type = VALUES(discount_type),
    discount_value = VALUES(discount_value),
    min_order_amount = VALUES(min_order_amount),
    max_discount_amount = VALUES(max_discount_amount),
    usage_limit = VALUES(usage_limit),
    is_active = VALUES(is_active);

COMMIT;
