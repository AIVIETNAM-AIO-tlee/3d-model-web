USE ecommerce_db;

START TRANSACTION;

UPDATE Products
SET price = 1490000
WHERE model_3d_url = 'assets/models/free_cyberpunk_hovercar.glb';

UPDATE Products
SET price = 990000
WHERE model_3d_url = 'assets/models/free_fire_3d_model.glb';

UPDATE Products
SET price = 790000
WHERE model_3d_url = 'assets/models/free_fire_tatto_bundle.glb';

UPDATE Products
SET price = 1190000
WHERE model_3d_url = 'assets/models/free_iphone_13_pro_2021.glb';

UPDATE Products
SET price = 1590000
WHERE model_3d_url = 'assets/models/hero_model.glb';

UPDATE Products
SET price = 1290000
WHERE model_3d_url = 'assets/models/log_cabin_free_download.glb';

UPDATE Products
SET price = 1090000
WHERE model_3d_url = 'assets/models/rank_3_police_unit.glb';

UPDATE Products
SET price = 1390000
WHERE model_3d_url = 'assets/models/rusty_old_truck_free_raw_scan.glb';

UPDATE Coupon_Codes
SET discount_value = 10,
	min_order_amount = 200000,
	max_discount_amount = 500000
WHERE code = 'WELCOME10';

UPDATE Coupon_Codes
SET discount_value = 50000,
	min_order_amount = 100000,
	max_discount_amount = NULL
WHERE code = 'SAVE5';

UPDATE Coupon_Codes
SET discount_value = 15,
	min_order_amount = 400000,
	max_discount_amount = 1000000
WHERE code = 'PREMIUM15';

COMMIT;
