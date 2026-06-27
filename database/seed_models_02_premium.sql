USE ecommerce_db;

START TRANSACTION;

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

SET @lifestyle_id = (SELECT id FROM Categories WHERE name = 'Lifestyle' ORDER BY id LIMIT 1);
SET @character_id = (SELECT id FROM Categories WHERE name = 'Character' ORDER BY id LIMIT 1);

INSERT INTO Products (category_id, name, description, price, is_premium, image_url, model_3d_url, tags)
SELECT @character_id, 'Bonnie', 'Premium stylized character model Bonnie.', 150000, 1, 'images/product-placeholder.svg', 'assets/models_02/bonnie.glb', 'character,premium'
WHERE NOT EXISTS (
    SELECT 1 FROM Products WHERE model_3d_url = 'assets/models_02/bonnie.glb'
);

INSERT INTO Products (category_id, name, description, price, is_premium, image_url, model_3d_url, tags)
SELECT @character_id, 'Detective Conan', 'Premium anime-style Detective Conan character model.', 190000, 1, 'images/product-placeholder.svg', 'assets/models_02/detective_conan.glb', 'character,anime,premium'
WHERE NOT EXISTS (
    SELECT 1 FROM Products WHERE model_3d_url = 'assets/models_02/detective_conan.glb'
);

INSERT INTO Products (category_id, name, description, price, is_premium, image_url, model_3d_url, tags)
SELECT @character_id, 'Freddy Fazzbear', 'Premium mascot-style character model for games.', 180000, 1, 'images/product-placeholder.svg', 'assets/models_02/freddy_fazzbear.glb', 'character,mascot,premium'
WHERE NOT EXISTS (
    SELECT 1 FROM Products WHERE model_3d_url = 'assets/models_02/freddy_fazzbear.glb'
);

INSERT INTO Products (category_id, name, description, price, is_premium, image_url, model_3d_url, tags)
SELECT @character_id, 'Goku', 'Premium hero character model inspired by anime style.', 200000, 1, 'images/product-placeholder.svg', 'assets/models_02/goku.glb', 'character,hero,premium'
WHERE NOT EXISTS (
    SELECT 1 FROM Products WHERE model_3d_url = 'assets/models_02/goku.glb'
);

INSERT INTO Products (category_id, name, description, price, is_premium, image_url, model_3d_url, tags)
SELECT @character_id, 'Low Poly Character Rigged', 'Premium rigged low-poly character ready for animation.', 120000, 1, 'images/product-placeholder.svg', 'assets/models_02/low_poly_character_rigged.glb', 'character,rigged,lowpoly,premium'
WHERE NOT EXISTS (
    SELECT 1 FROM Products WHERE model_3d_url = 'assets/models_02/low_poly_character_rigged.glb'
);

INSERT INTO Products (category_id, name, description, price, is_premium, image_url, model_3d_url, tags)
SELECT @character_id, 'Male Full Body Ecorche', 'Premium anatomical male full-body character model.', 170000, 1, 'images/product-placeholder.svg', 'assets/models_02/male_full_body_ecorche.glb', 'character,anatomy,premium'
WHERE NOT EXISTS (
    SELECT 1 FROM Products WHERE model_3d_url = 'assets/models_02/male_full_body_ecorche.glb'
);

INSERT INTO Products (category_id, name, description, price, is_premium, image_url, model_3d_url, tags)
SELECT @lifestyle_id, 'Maple Tree', 'Premium lifestyle environment asset: maple tree.', 90000, 1, 'images/product-placeholder.svg', 'assets/models_02/maple_tree.glb', 'lifestyle,environment,tree,premium'
WHERE NOT EXISTS (
    SELECT 1 FROM Products WHERE model_3d_url = 'assets/models_02/maple_tree.glb'
);

INSERT INTO Products (category_id, name, description, price, is_premium, image_url, model_3d_url, tags)
SELECT @character_id, 'Matilda', 'Premium stylized character model Matilda.', 140000, 1, 'images/product-placeholder.svg', 'assets/models_02/matilda.glb', 'character,premium'
WHERE NOT EXISTS (
    SELECT 1 FROM Products WHERE model_3d_url = 'assets/models_02/matilda.glb'
);

INSERT INTO Products (category_id, name, description, price, is_premium, image_url, model_3d_url, tags)
SELECT @character_id, 'Naruto Sage', 'Premium anime-style sage character model.', 195000, 1, 'images/product-placeholder.svg', 'assets/models_02/naruto_sage.glb', 'character,anime,premium'
WHERE NOT EXISTS (
    SELECT 1 FROM Products WHERE model_3d_url = 'assets/models_02/naruto_sage.glb'
);

INSERT INTO Products (category_id, name, description, price, is_premium, image_url, model_3d_url, tags)
SELECT @lifestyle_id, 'Patch Of Heaven - The White Tree', 'Premium environment tree asset for lifestyle scenes.', 110000, 1, 'images/product-placeholder.svg', 'assets/models_02/patch_of_heaven_-_the_white_tree.glb', 'lifestyle,environment,tree,premium'
WHERE NOT EXISTS (
    SELECT 1 FROM Products WHERE model_3d_url = 'assets/models_02/patch_of_heaven_-_the_white_tree.glb'
);

INSERT INTO Products (category_id, name, description, price, is_premium, image_url, model_3d_url, tags)
SELECT @character_id, 'Susan', 'Premium stylized character model Susan.', 130000, 1, 'images/product-placeholder.svg', 'assets/models_02/susan.glb', 'character,premium'
WHERE NOT EXISTS (
    SELECT 1 FROM Products WHERE model_3d_url = 'assets/models_02/susan.glb'
);

INSERT INTO Products (category_id, name, description, price, is_premium, image_url, model_3d_url, tags)
SELECT @character_id, 'Arnold Schwarzenegger Conan The Barbarian', 'Premium barbarian-inspired warrior character model.', 200000, 1, 'images/product-placeholder.svg', 'assets/models_02/arnold_schwarzenegger_conan_the_barbarian.glb', 'character,hero,premium'
WHERE NOT EXISTS (
    SELECT 1 FROM Products WHERE model_3d_url = 'assets/models_02/arnold_schwarzenegger_conan_the_barbarian.glb'
);

INSERT INTO Products (category_id, name, description, price, is_premium, image_url, model_3d_url, tags)
SELECT @character_id, 'Doraemon', 'Premium cartoon-style robot cat character model.', 120000, 1, 'images/product-placeholder.svg', 'assets/models_02/doraemon.glb', 'character,cartoon,premium'
WHERE NOT EXISTS (
    SELECT 1 FROM Products WHERE model_3d_url = 'assets/models_02/doraemon.glb'
);

INSERT INTO Products (category_id, name, description, price, is_premium, image_url, model_3d_url, tags)
SELECT @character_id, 'Luffy One Piece', 'Premium anime pirate character model inspired by One Piece.', 180000, 1, 'images/product-placeholder.svg', 'assets/models_02/luffy_one_piece.glb', 'character,anime,premium'
WHERE NOT EXISTS (
    SELECT 1 FROM Products WHERE model_3d_url = 'assets/models_02/luffy_one_piece.glb'
);

INSERT INTO Products (category_id, name, description, price, is_premium, image_url, model_3d_url, tags)
SELECT @character_id, 'Roronoa Zoro', 'Premium swordsman anime character model.', 185000, 1, 'images/product-placeholder.svg', 'assets/models_02/roronoa_zoro.glb', 'character,anime,premium'
WHERE NOT EXISTS (
    SELECT 1 FROM Products WHERE model_3d_url = 'assets/models_02/roronoa_zoro.glb'
);

INSERT INTO Products (category_id, name, description, price, is_premium, image_url, model_3d_url, tags)
SELECT @character_id, 'Shin Chan', 'Premium stylized comedy character model.', 100000, 1, 'images/product-placeholder.svg', 'assets/models_02/shin_chan.glb', 'character,cartoon,premium'
WHERE NOT EXISTS (
    SELECT 1 FROM Products WHERE model_3d_url = 'assets/models_02/shin_chan.glb'
);

INSERT INTO Products (category_id, name, description, price, is_premium, image_url, model_3d_url, tags)
SELECT @character_id, 'Yuji Itadori Free Fire Skin', 'Premium anime-inspired fighter skin character model.', 175000, 1, 'images/product-placeholder.svg', 'assets/models_02/yuji_itadori_free_fire_skin.glb', 'character,anime,skin,premium'
WHERE NOT EXISTS (
    SELECT 1 FROM Products WHERE model_3d_url = 'assets/models_02/yuji_itadori_free_fire_skin.glb'
);

COMMIT;
