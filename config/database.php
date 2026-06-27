<?php

if (!defined('DB_HOST')) {
	define('DB_HOST', 'localhost');
}
if (!defined('DB_NAME')) {
	define('DB_NAME', 'ecommerce_db');
}
if (!defined('DB_USER')) {
	define('DB_USER', 'root');
}
if (!defined('DB_PASS')) {
	define('DB_PASS', '');
}
if (!defined('DB_CHARSET')) {
	define('DB_CHARSET', 'utf8mb4');
}

function getDBConnection(): PDO {
	static $pdo = null;
	if ($pdo instanceof PDO) {
		return $pdo;
	}

	$dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
	$pdo = new PDO($dsn, DB_USER, DB_PASS, [
		PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
		PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
		PDO::ATTR_EMULATE_PREPARES => false,
	]);

	return $pdo;
}

function parseProductTags($rawTags): array {
	if (is_array($rawTags)) {
		$parts = $rawTags;
	} else {
		$parts = preg_split('/[,;|]+/', (string) $rawTags);
	}

	$normalized = [];
	foreach ((array) $parts as $part) {
		$tag = strtolower(trim((string) $part));
		if ($tag !== '') {
			$normalized[$tag] = true;
		}
	}

	return array_keys($normalized);
}

function productHasTag($rawTags, $tag): bool {
	$tag = strtolower(trim((string) $tag));
	if ($tag === '') {
		return false;
	}

	return in_array($tag, parseProductTags($rawTags), true);
}

function isProductPremiumFlag($rawValue): bool {
	if (is_bool($rawValue)) {
		return $rawValue;
	}

	if (is_numeric($rawValue)) {
		return (int) $rawValue === 1;
	}

	$normalized = strtolower(trim((string) $rawValue));
	return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
}

function isProductEffectivelyPremium($isPremium, $storedPrice): bool {
	return isProductPremiumFlag($isPremium) && (float) $storedPrice > 0;
}

function resolveProductEffectivePrice($storedPrice, $isPremium): float {
	if (!isProductEffectivelyPremium($isPremium, $storedPrice)) {
		return 0.0;
	}
	return max(0.0, (float) $storedPrice);
}

function ensureProductsPremiumColumn(PDO $pdo): void {
	static $ready = false;
	if ($ready) {
		return;
	}

	ensureTableColumn($pdo, 'Products', 'is_premium', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER price');

	try {
		$pdo->exec("UPDATE Products SET is_premium = 1 WHERE is_premium = 0 AND LOWER(COALESCE(tags, '')) LIKE '%premium%'");
	} catch (Throwable $e) {
		error_log('Error migrating legacy premium tags: ' . $e->getMessage());
	}

	$ready = true;
}

function formatCurrencyVND($amount): string {
	return number_format((float) $amount, 0, ',', '.') . ' ₫';
}

function getCategories(PDO $pdo): array {
	$categories = ['All'];
	try {
		$stmt = $pdo->query('SELECT name FROM Categories ORDER BY name ASC');
		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
		foreach ((array) $rows as $row) {
			$name = trim((string) ($row['name'] ?? ''));
			if ($name !== '') {
				$categories[] = $name;
			}
		}
	} catch (Throwable $e) {
		error_log('Error fetching categories: ' . $e->getMessage());
	}

	return array_values(array_unique($categories));
}

function getProducts(PDO $pdo, $category = 'All', $sort = 'default', $search = ''): array {
	ensureProductsPremiumColumn($pdo);

	$sql = 'SELECT p.id AS id, p.name, p.description, p.price, p.is_premium, p.tags, p.image_url, p.model_3d_url, COALESCE(c.name, "Uncategorized") AS category
			FROM Products p
			LEFT JOIN Categories c ON c.id = p.category_id';

	$conditions = [];
	$params = [];

	$category = trim((string) $category);
	if ($category !== '' && strtolower($category) !== 'all') {
		$conditions[] = 'COALESCE(c.name, "Uncategorized") = ?';
		$params[] = $category;
	}

	$search = trim((string) $search);
	if ($search !== '') {
		$conditions[] = '(p.name LIKE ? OR COALESCE(p.description, "") LIKE ? OR COALESCE(p.tags, "") LIKE ?)';
		$like = '%' . $search . '%';
		$params[] = $like;
		$params[] = $like;
		$params[] = $like;
	}

	if ($conditions !== []) {
		$sql .= ' WHERE ' . implode(' AND ', $conditions);
	}

	if ($sort === 'price_asc') {
		$sql .= ' ORDER BY p.price ASC, p.id DESC';
	} elseif ($sort === 'price_desc') {
		$sql .= ' ORDER BY p.price DESC, p.id DESC';
	} else {
		$sql .= ' ORDER BY p.id DESC';
	}

	try {
		$stmt = $pdo->prepare($sql);
		$stmt->execute($params);
		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
	} catch (Throwable $e) {
		error_log('Error fetching products: ' . $e->getMessage());
		return [];
	}

	$formatted = [];
	foreach ((array) $rows as $product) {
		$id = (int) ($product['id'] ?? 0);
		$name = (string) ($product['name'] ?? 'Unknown Product');
		$slug = strtolower(trim(preg_replace('/\s+/', '-', $name)));

		$formatted[] = [
			'id' => $id,
			'slug' => $slug,
			'name' => $name,
			'price' => resolveProductEffectivePrice($product['price'] ?? 0, $product['is_premium'] ?? 0),
			'basePrice' => (float) ($product['price'] ?? 0),
			'tags' => parseProductTags($product['tags'] ?? ''),
			'isPremium' => isProductEffectivelyPremium($product['is_premium'] ?? 0, $product['price'] ?? 0),
			'category' => (string) ($product['category'] ?? 'Uncategorized'),
			'modelPath' => (string) ($product['model_3d_url'] ?? 'assets/models/model-placeholder.glb'),
			'thumbPath' => (string) ($product['image_url'] ?? 'images/model-placeholder.svg'),
			'description' => (string) ($product['description'] ?? 'Product description not available'),
		];
	}

	return $formatted;
}

function getProductById(PDO $pdo, $id): ?array {
	ensureProductsPremiumColumn($pdo);

	$stmt = $pdo->prepare('SELECT p.id AS id, p.name, p.description, p.price, p.is_premium, p.tags, p.image_url, p.model_3d_url, COALESCE(c.name, "Uncategorized") AS category
						   FROM Products p
						   LEFT JOIN Categories c ON c.id = p.category_id
						   WHERE p.id = ?
						   LIMIT 1');
	$stmt->execute([(int) $id]);
	$row = $stmt->fetch(PDO::FETCH_ASSOC);
	if (!$row) {
		return null;
	}

	$name = (string) ($row['name'] ?? 'Unknown Product');
	$slug = strtolower(trim(preg_replace('/\s+/', '-', $name)));

	return [
		'id' => (int) $row['id'],
		'slug' => $slug,
		'name' => $name,
		'price' => resolveProductEffectivePrice($row['price'] ?? 0, $row['is_premium'] ?? 0),
		'basePrice' => (float) ($row['price'] ?? 0),
		'tags' => parseProductTags($row['tags'] ?? ''),
		'isPremium' => isProductEffectivelyPremium($row['is_premium'] ?? 0, $row['price'] ?? 0),
		'category' => (string) ($row['category'] ?? 'Uncategorized'),
		'modelPath' => (string) ($row['model_3d_url'] ?? 'assets/models/model-placeholder.glb'),
		'thumbPath' => (string) ($row['image_url'] ?? 'images/model-placeholder.svg'),
		'description' => (string) ($row['description'] ?? ''),
	];
}

function getProductBySlug(PDO $pdo, $slug): ?array {
	ensureProductsPremiumColumn($pdo);

	$slug = strtolower(trim((string) $slug));
	if ($slug === '') {
		return null;
	}

	$stmt = $pdo->prepare('SELECT p.id AS id, p.name, p.description, p.price, p.is_premium, p.tags, p.image_url, p.model_3d_url, COALESCE(c.name, "Uncategorized") AS category
						   FROM Products p
						   LEFT JOIN Categories c ON c.id = p.category_id
						   WHERE LOWER(REPLACE(p.name, " ", "-")) = ?
						   LIMIT 1');
	$stmt->execute([$slug]);
	$row = $stmt->fetch(PDO::FETCH_ASSOC);
	if (!$row) {
		return null;
	}

	return [
		'id' => (int) $row['id'],
		'slug' => $slug,
		'name' => (string) ($row['name'] ?? 'Unknown Product'),
		'price' => resolveProductEffectivePrice($row['price'] ?? 0, $row['is_premium'] ?? 0),
		'basePrice' => (float) ($row['price'] ?? 0),
		'tags' => parseProductTags($row['tags'] ?? ''),
		'isPremium' => isProductEffectivelyPremium($row['is_premium'] ?? 0, $row['price'] ?? 0),
		'category' => (string) ($row['category'] ?? 'Uncategorized'),
		'modelPath' => (string) ($row['model_3d_url'] ?? 'assets/models/model-placeholder.glb'),
		'thumbPath' => (string) ($row['image_url'] ?? 'images/model-placeholder.svg'),
		'description' => (string) ($row['description'] ?? ''),
	];
}

function ensureProductCommentsTable(PDO $pdo): void {
	static $ready = false;
	if ($ready) {
		return;
	}

	$pdo->exec("CREATE TABLE IF NOT EXISTS Product_Comments (
		id INT AUTO_INCREMENT PRIMARY KEY,
		product_id INT NOT NULL,
		user_id INT NOT NULL,
		parent_comment_id INT NULL,
		content TEXT NOT NULL,
		created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
		updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		INDEX idx_product_comments_product (product_id),
		INDEX idx_product_comments_user (user_id),
		INDEX idx_product_comments_parent (parent_comment_id),
		CONSTRAINT fk_product_comments_product FOREIGN KEY (product_id) REFERENCES Products(id) ON DELETE CASCADE,
		CONSTRAINT fk_product_comments_user FOREIGN KEY (user_id) REFERENCES Users(id) ON DELETE CASCADE,
		CONSTRAINT fk_product_comments_parent FOREIGN KEY (parent_comment_id) REFERENCES Product_Comments(id) ON DELETE CASCADE
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

	$pdo->exec("CREATE TABLE IF NOT EXISTS Product_Comment_Likes (
		comment_id INT NOT NULL,
		user_id INT NOT NULL,
		created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (comment_id, user_id),
		CONSTRAINT fk_comment_likes_comment FOREIGN KEY (comment_id) REFERENCES Product_Comments(id) ON DELETE CASCADE,
		CONSTRAINT fk_comment_likes_user FOREIGN KEY (user_id) REFERENCES Users(id) ON DELETE CASCADE
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

	$ready = true;
}

function createProductComment(PDO $pdo, int $productId, int $userId, string $content, ?int $parentCommentId = null): array {
	ensureProductCommentsTable($pdo);

	$content = trim($content);
	if ($content === '' || mb_strlen($content) < 2) {
		return ['ok' => false, 'message' => 'Comment must be at least 2 characters long.'];
	}
	if (mb_strlen($content) > 2000) {
		return ['ok' => false, 'message' => 'Comment is too long.'];
	}

	$productCheck = $pdo->prepare('SELECT id FROM Products WHERE id = ? LIMIT 1');
	$productCheck->execute([$productId]);
	if (!$productCheck->fetch()) {
		return ['ok' => false, 'message' => 'Product not found.'];
	}

	$parentId = null;
	if (!empty($parentCommentId)) {
		$parentId = (int) $parentCommentId;
		$parentCheck = $pdo->prepare('SELECT id, product_id FROM Product_Comments WHERE id = ? LIMIT 1');
		$parentCheck->execute([$parentId]);
		$parentRow = $parentCheck->fetch(PDO::FETCH_ASSOC);
		if (!$parentRow || (int) $parentRow['product_id'] !== $productId) {
			return ['ok' => false, 'message' => 'Reply target is invalid.'];
		}
	}

	$stmt = $pdo->prepare('INSERT INTO Product_Comments (product_id, user_id, parent_comment_id, content) VALUES (?, ?, ?, ?)');
	$stmt->execute([$productId, $userId, $parentId, $content]);

	return ['ok' => true, 'comment_id' => (int) $pdo->lastInsertId()];
}

function getProductComments(PDO $pdo, int $productId, ?int $viewerUserId = null): array {
	ensureProductCommentsTable($pdo);

	$sql = 'SELECT c.id, c.product_id, c.user_id, c.parent_comment_id, c.content, c.created_at,
				   u.full_name AS user_name,
				   COUNT(cl.user_id) AS like_count,
				   MAX(CASE WHEN cl.user_id = :viewer_id THEN 1 ELSE 0 END) AS is_liked
			FROM Product_Comments c
			INNER JOIN Users u ON u.id = c.user_id
			LEFT JOIN Product_Comment_Likes cl ON cl.comment_id = c.id
			WHERE c.product_id = :product_id
			GROUP BY c.id, c.product_id, c.user_id, c.parent_comment_id, c.content, c.created_at, u.full_name
			ORDER BY c.created_at ASC';

	$stmt = $pdo->prepare($sql);
	$stmt->execute([
		':viewer_id' => (int) ($viewerUserId ?? 0),
		':product_id' => $productId,
	]);

	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
	if (!is_array($rows)) {
		return [];
	}

	return array_map(static function ($row) {
		return [
			'id' => (int) ($row['id'] ?? 0),
			'product_id' => (int) ($row['product_id'] ?? 0),
			'user_id' => (int) ($row['user_id'] ?? 0),
			'parent_comment_id' => isset($row['parent_comment_id']) ? (int) $row['parent_comment_id'] : null,
			'content' => (string) ($row['content'] ?? ''),
			'created_at' => (string) ($row['created_at'] ?? ''),
			'user_name' => (string) ($row['user_name'] ?? 'Anonymous'),
			'like_count' => (int) ($row['like_count'] ?? 0),
			'is_liked' => !empty($row['is_liked']),
		];
	}, $rows);
}

function toggleProductCommentLike(PDO $pdo, int $productId, int $userId, int $commentId): array {
	ensureProductCommentsTable($pdo);

	$commentStmt = $pdo->prepare('SELECT id FROM Product_Comments WHERE id = ? AND product_id = ? LIMIT 1');
	$commentStmt->execute([$commentId, $productId]);
	if (!$commentStmt->fetch()) {
		return ['ok' => false, 'message' => 'Comment not found.'];
	}

	$checkStmt = $pdo->prepare('SELECT 1 FROM Product_Comment_Likes WHERE comment_id = ? AND user_id = ? LIMIT 1');
	$checkStmt->execute([$commentId, $userId]);
	$liked = (bool) $checkStmt->fetchColumn();

	if ($liked) {
		$pdo->prepare('DELETE FROM Product_Comment_Likes WHERE comment_id = ? AND user_id = ?')->execute([$commentId, $userId]);
		$liked = false;
	} else {
		$pdo->prepare('INSERT INTO Product_Comment_Likes (comment_id, user_id) VALUES (?, ?)')->execute([$commentId, $userId]);
		$liked = true;
	}

	$countStmt = $pdo->prepare('SELECT COUNT(*) FROM Product_Comment_Likes WHERE comment_id = ?');
	$countStmt->execute([$commentId]);
	$count = (int) ($countStmt->fetchColumn() ?: 0);

	return ['ok' => true, 'liked' => $liked, 'like_count' => $count];
}

function ensureTableColumn(PDO $pdo, string $tableName, string $columnName, string $definitionSql): void {
	if (!preg_match('/^[A-Za-z0-9_]+$/', $tableName) || !preg_match('/^[A-Za-z0-9_]+$/', $columnName)) {
		return;
	}

	// MariaDB can fail on placeholders in SHOW ... LIKE, so quote literal explicitly.
	$quotedColumn = $pdo->quote((string) $columnName);
	$stmt = $pdo->query('SHOW COLUMNS FROM `' . $tableName . '` LIKE ' . $quotedColumn);
	if ($stmt && $stmt->fetch(PDO::FETCH_ASSOC)) {
		return;
	}

	$pdo->exec('ALTER TABLE `' . $tableName . '` ADD COLUMN `' . $columnName . '` ' . $definitionSql);
}

function ensureOrdersTable(PDO $pdo): void {
	static $ready = false;
	if ($ready) {
		return;
	}

	$pdo->exec("CREATE TABLE IF NOT EXISTS Orders (
		id INT AUTO_INCREMENT PRIMARY KEY,
		user_id INT NOT NULL,
		total_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
		original_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
		discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
		coupon_code VARCHAR(64) NULL,
		payment_method VARCHAR(32) NOT NULL DEFAULT 'vietqr',
		payment_reference VARCHAR(100) NULL,
		payment_status VARCHAR(32) NOT NULL DEFAULT 'completed',
		delivery_status VARCHAR(32) NOT NULL DEFAULT 'Delivered',
		created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
		updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		INDEX idx_orders_user (user_id),
		INDEX idx_orders_created (created_at),
		CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES Users(id) ON DELETE CASCADE
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

	ensureOrdersMetadataColumns($pdo);
	$ready = true;
}

function ensureOrdersMetadataColumns(PDO $pdo): void {
	ensureTableColumn($pdo, 'Orders', 'original_amount', "DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER total_amount");
	ensureTableColumn($pdo, 'Orders', 'discount_amount', "DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER original_amount");
	ensureTableColumn($pdo, 'Orders', 'coupon_code', "VARCHAR(64) NULL AFTER discount_amount");
	ensureTableColumn($pdo, 'Orders', 'payment_method', "VARCHAR(32) NOT NULL DEFAULT 'vietqr' AFTER coupon_code");
	ensureTableColumn($pdo, 'Orders', 'payment_reference', "VARCHAR(100) NULL AFTER payment_method");

	try {
		$pdo->exec("ALTER TABLE `Orders` MODIFY COLUMN `payment_method` VARCHAR(32) NOT NULL DEFAULT 'vietqr'");
		$pdo->exec("UPDATE `Orders` SET `payment_method` = 'vietqr' WHERE `payment_method` IS NULL OR TRIM(`payment_method`) = '' OR `payment_method` <> 'vietqr'");
		$pdo->exec("ALTER TABLE `Orders` MODIFY COLUMN `payment_status` VARCHAR(32) NOT NULL DEFAULT 'completed'");
		$pdo->exec("ALTER TABLE `Orders` MODIFY COLUMN `delivery_status` VARCHAR(32) NOT NULL DEFAULT 'Delivered'");

		$checkShipping = $pdo->query("SHOW COLUMNS FROM `Orders` LIKE 'shipping_address'");
		if ($checkShipping && $checkShipping->fetch(PDO::FETCH_ASSOC)) {
			$pdo->exec('ALTER TABLE `Orders` DROP COLUMN `shipping_address`');
		}
	} catch (Throwable $e) {
		error_log('Error normalizing Orders schema: ' . $e->getMessage());
	}
}

function ensureCouponCodesTable(PDO $pdo): void {
	static $ready = false;
	if ($ready) {
		return;
	}

	$pdo->exec("CREATE TABLE IF NOT EXISTS Coupon_Codes (
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
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

	$seedStmt = $pdo->prepare("INSERT INTO Coupon_Codes (code, discount_type, discount_value, min_order_amount, max_discount_amount, usage_limit, is_active)
							   VALUES (?, ?, ?, ?, ?, ?, 1)
							   ON DUPLICATE KEY UPDATE
								 discount_type = VALUES(discount_type),
								 discount_value = VALUES(discount_value),
								 min_order_amount = VALUES(min_order_amount),
								 max_discount_amount = VALUES(max_discount_amount),
								 usage_limit = VALUES(usage_limit)");

	$seedStmt->execute(['WELCOME10', 'percent', 10, 200000, 500000, null]);
	$seedStmt->execute(['SAVE5', 'fixed', 50000, 100000, null, null]);
	$seedStmt->execute(['PREMIUM15', 'percent', 15, 400000, 1000000, null]);

	$ready = true;
}

function ensureOrderItemsTable(PDO $pdo): void {
	static $ready = false;
	if ($ready) {
		return;
	}

	ensureOrdersTable($pdo);
	$pdo->exec("CREATE TABLE IF NOT EXISTS Order_Items (
		id INT AUTO_INCREMENT PRIMARY KEY,
		order_id INT NOT NULL,
		product_id INT NOT NULL,
		quantity INT NOT NULL DEFAULT 1,
		price_at_purchase DECIMAL(10,2) NOT NULL DEFAULT 0,
		created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
		UNIQUE KEY uq_order_items_order_product (order_id, product_id),
		INDEX idx_order_items_product (product_id),
		CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES Orders(id) ON DELETE CASCADE,
		CONSTRAINT fk_order_items_product FOREIGN KEY (product_id) REFERENCES Products(id) ON DELETE CASCADE
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

	$ready = true;
}

function ensureUserInventoryTable(PDO $pdo): void {
	static $ready = false;
	if ($ready) {
		return;
	}

	ensureOrdersTable($pdo);
	$pdo->exec("CREATE TABLE IF NOT EXISTS User_Inventory (
		user_id INT NOT NULL,
		product_id INT NOT NULL,
		source_order_id INT NULL,
		acquired_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (user_id, product_id),
		INDEX idx_inventory_product (product_id),
		INDEX idx_inventory_order (source_order_id),
		CONSTRAINT fk_inventory_user FOREIGN KEY (user_id) REFERENCES Users(id) ON DELETE CASCADE,
		CONSTRAINT fk_inventory_product FOREIGN KEY (product_id) REFERENCES Products(id) ON DELETE CASCADE,
		CONSTRAINT fk_inventory_order FOREIGN KEY (source_order_id) REFERENCES Orders(id) ON DELETE SET NULL
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

	$ready = true;
}

function ensureCommercePurchaseSchema(PDO $pdo): void {
	ensureOrdersTable($pdo);
	ensureOrderItemsTable($pdo);
	ensureUserInventoryTable($pdo);
	ensureCouponCodesTable($pdo);
}

function normalizePaymentMethod($paymentMethod): string {
	$method = strtolower(trim((string) $paymentMethod));
	return $method === 'vietqr' ? 'vietqr' : 'vietqr';
}

function findActiveCouponByCode(PDO $pdo, $couponCode): ?array {
	ensureCouponCodesTable($pdo);

	$code = strtoupper(trim((string) $couponCode));
	if ($code === '') {
		return null;
	}

	$stmt = $pdo->prepare("SELECT id, code, discount_type, discount_value, min_order_amount, max_discount_amount, usage_limit, used_count
						   FROM Coupon_Codes
						   WHERE code = ?
							 AND is_active = 1
							 AND (starts_at IS NULL OR starts_at <= NOW())
							 AND (expires_at IS NULL OR expires_at >= NOW())
							 AND (usage_limit IS NULL OR used_count < usage_limit)
						   LIMIT 1");
	$stmt->execute([$code]);
	$row = $stmt->fetch(PDO::FETCH_ASSOC);
	return $row ?: null;
}

function computeCouponDiscountAmount($subtotal, $couponRow): float {
	$subtotalValue = max(0.0, (float) $subtotal);
	if ($subtotalValue <= 0 || !is_array($couponRow)) {
		return 0.0;
	}

	$type = (string) ($couponRow['discount_type'] ?? 'percent');
	$value = max(0.0, (float) ($couponRow['discount_value'] ?? 0));
	$discount = $type === 'fixed' ? $value : ($subtotalValue * ($value / 100));

	$maxDiscount = isset($couponRow['max_discount_amount']) ? (float) $couponRow['max_discount_amount'] : 0.0;
	if ($maxDiscount > 0) {
		$discount = min($discount, $maxDiscount);
	}

	return min($subtotalValue, max(0.0, $discount));
}

function buildPremiumCheckoutQuote(PDO $pdo, $userId, $productIds, $couponCode = ''): array {
	ensureCommercePurchaseSchema($pdo);
	ensureProductsPremiumColumn($pdo);

	$userId = (int) $userId;
	if ($userId < 0) {
		$userId = 0;
	}

	$ids = [];
	foreach ((array) $productIds as $id) {
		$intId = (int) $id;
		if ($intId > 0) {
			$ids[$intId] = true;
		}
	}
	$ids = array_keys($ids);
	if ($ids === []) {
		return ['ok' => false, 'message' => 'No products selected.'];
	}

	$placeholders = implode(',', array_fill(0, count($ids), '?'));
	$stmt = $pdo->prepare('SELECT id, name, price, is_premium FROM Products WHERE id IN (' . $placeholders . ')');
	$stmt->execute($ids);
	$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
	if (!is_array($products) || $products === []) {
		return ['ok' => false, 'message' => 'Selected products were not found.'];
	}

	$owned = [];
	if ($userId > 0) {
		$ownedStmt = $pdo->prepare('SELECT product_id FROM User_Inventory WHERE user_id = ? AND product_id IN (' . $placeholders . ')');
		$ownedStmt->execute(array_merge([$userId], $ids));
		foreach ((array) $ownedStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
			$owned[(int) ($row['product_id'] ?? 0)] = true;
		}
	}

	$purchasable = [];
	$alreadyOwnedIds = [];
	$nonPremiumIds = [];
	$subtotalAmount = 0.0;

	foreach ($products as $product) {
		$productId = (int) ($product['id'] ?? 0);
		if ($productId <= 0) {
			continue;
		}

		$isPremium = isProductEffectivelyPremium($product['is_premium'] ?? 0, $product['price'] ?? 0);
		if (!$isPremium) {
			$nonPremiumIds[] = $productId;
			continue;
		}

		if (isset($owned[$productId])) {
			$alreadyOwnedIds[] = $productId;
			continue;
		}

		$price = max(0.0, (float) ($product['price'] ?? 0));
		$subtotalAmount += $price;
		$purchasable[$productId] = [
			'id' => $productId,
			'name' => (string) ($product['name'] ?? 'Product'),
			'price' => $price,
		];
	}

	$coupon = null;
	$couponError = null;
	$discountAmount = 0.0;
	$normalizedCode = strtoupper(trim((string) $couponCode));
	if ($normalizedCode !== '' && $subtotalAmount > 0) {
		$couponRow = findActiveCouponByCode($pdo, $normalizedCode);
		if (!$couponRow) {
			$couponError = 'Coupon code is invalid or expired.';
		} else {
			$minOrder = max(0.0, (float) ($couponRow['min_order_amount'] ?? 0));
			if ($subtotalAmount < $minOrder) {
				$couponError = 'Order does not meet minimum amount for this coupon.';
			} else {
				$discountAmount = computeCouponDiscountAmount($subtotalAmount, $couponRow);
				$coupon = [
					'id' => (int) ($couponRow['id'] ?? 0),
					'code' => (string) ($couponRow['code'] ?? $normalizedCode),
					'discount_type' => (string) ($couponRow['discount_type'] ?? 'percent'),
					'discount_value' => (float) ($couponRow['discount_value'] ?? 0),
					'min_order_amount' => (float) ($couponRow['min_order_amount'] ?? 0),
					'max_discount_amount' => isset($couponRow['max_discount_amount']) ? (float) $couponRow['max_discount_amount'] : null,
				];
			}
		}
	}

	$totalAmount = max(0.0, $subtotalAmount - $discountAmount);

	return [
		'ok' => true,
		'purchasable_products' => $purchasable,
		'purchasable_ids' => array_map('intval', array_keys($purchasable)),
		'already_owned_ids' => array_values(array_unique(array_map('intval', $alreadyOwnedIds))),
		'skipped_non_premium_ids' => array_values(array_unique(array_map('intval', $nonPremiumIds))),
		'subtotal_amount' => $subtotalAmount,
		'discount_amount' => $discountAmount,
		'total_amount' => $totalAmount,
		'coupon' => $coupon,
		'coupon_error' => $couponError,
	];
}

function previewPremiumPurchase(PDO $pdo, $userId, $productIds, $couponCode = ''): array {
	$quote = buildPremiumCheckoutQuote($pdo, $userId, $productIds, $couponCode);
	if (empty($quote['ok'])) {
		return $quote;
	}

	return [
		'ok' => true,
		'subtotal_amount' => (float) ($quote['subtotal_amount'] ?? 0),
		'discount_amount' => (float) ($quote['discount_amount'] ?? 0),
		'total_amount' => (float) ($quote['total_amount'] ?? 0),
		'coupon' => $quote['coupon'] ?? null,
		'coupon_error' => $quote['coupon_error'] ?? null,
		'already_owned_ids' => $quote['already_owned_ids'] ?? [],
		'skipped_non_premium_ids' => $quote['skipped_non_premium_ids'] ?? [],
		'purchasable_ids' => $quote['purchasable_ids'] ?? [],
	];
}

function purchasePremiumProducts(PDO $pdo, $userId, $productIds, $options = []): array {
	ensureCommercePurchaseSchema($pdo);

	$userId = (int) $userId;
	if ($userId <= 0) {
		return ['ok' => false, 'message' => 'Invalid user.'];
	}

	$couponCode = strtoupper(trim((string) ($options['coupon_code'] ?? '')));
	$paymentMethod = normalizePaymentMethod($options['payment_method'] ?? 'vietqr');
	$quote = buildPremiumCheckoutQuote($pdo, $userId, $productIds, $couponCode);
	if (empty($quote['ok'])) {
		return $quote;
	}

	if (($quote['coupon_error'] ?? null) && $couponCode !== '') {
		return ['ok' => false, 'message' => (string) $quote['coupon_error']];
	}

	$purchasable = $quote['purchasable_products'] ?? [];
	$ownedIds = array_values($quote['already_owned_ids'] ?? []);
	$nonPremiumIds = array_values($quote['skipped_non_premium_ids'] ?? []);

	if ($purchasable === []) {
		return [
			'ok' => true,
			'order_id' => null,
			'purchased_ids' => [],
			'already_owned_ids' => $ownedIds,
			'skipped_non_premium_ids' => $nonPremiumIds,
			'total_amount' => 0,
			'subtotal_amount' => 0,
			'discount_amount' => 0,
			'coupon' => $quote['coupon'] ?? null,
			'payment_method' => $paymentMethod,
			'message' => 'All selected premium products are already in your inventory.',
		];
	}

	$pdo->beginTransaction();
	try {
		$subtotalAmount = max(0.0, (float) ($quote['subtotal_amount'] ?? 0));
		$discountAmount = max(0.0, (float) ($quote['discount_amount'] ?? 0));
		$totalAmount = max(0.0, (float) ($quote['total_amount'] ?? 0));
		$coupon = $quote['coupon'] ?? null;
		$couponCodeToSave = is_array($coupon) ? (string) ($coupon['code'] ?? '') : null;

		$orderStmt = $pdo->prepare('INSERT INTO Orders (user_id, total_amount, original_amount, discount_amount, coupon_code, payment_method, payment_status, delivery_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
		$orderStmt->execute([$userId, $totalAmount, $subtotalAmount, $discountAmount, $couponCodeToSave, $paymentMethod, 'completed', 'Delivered']);
		$orderId = (int) $pdo->lastInsertId();

		$paymentReference = 'SIM-VIETQR-' . str_pad((string) $orderId, 6, '0', STR_PAD_LEFT);
		$pdo->prepare('UPDATE Orders SET payment_reference = ? WHERE id = ?')->execute([$paymentReference, $orderId]);

		$itemStmt = $pdo->prepare('INSERT INTO Order_Items (order_id, product_id, quantity, price_at_purchase) VALUES (?, ?, 1, ?)');
		$inventoryStmt = $pdo->prepare('INSERT IGNORE INTO User_Inventory (user_id, product_id, source_order_id) VALUES (?, ?, ?)');

		foreach ($purchasable as $productId => $product) {
			$priceAtPurchase = max(0, (float) ($product['price'] ?? 0));
			$itemStmt->execute([$orderId, (int) $productId, $priceAtPurchase]);
			$inventoryStmt->execute([$userId, (int) $productId, $orderId]);
		}

		if (is_array($coupon) && (int) ($coupon['id'] ?? 0) > 0 && $discountAmount > 0) {
			$pdo->prepare('UPDATE Coupon_Codes SET used_count = used_count + 1 WHERE id = ?')->execute([(int) $coupon['id']]);
		}

		$pdo->commit();

		return [
			'ok' => true,
			'order_id' => $orderId,
			'purchased_ids' => array_map('intval', array_keys($purchasable)),
			'already_owned_ids' => $ownedIds,
			'skipped_non_premium_ids' => $nonPremiumIds,
			'subtotal_amount' => $subtotalAmount,
			'discount_amount' => $discountAmount,
			'total_amount' => $totalAmount,
			'coupon' => $coupon,
			'payment_method' => $paymentMethod,
			'payment_reference' => $paymentReference,
		];
	} catch (Throwable $e) {
		if ($pdo->inTransaction()) {
			$pdo->rollBack();
		}
		error_log('Error purchasing premium products: ' . $e->getMessage());
		return ['ok' => false, 'message' => 'Unable to complete your purchase right now.'];
	}
}

function createPendingPremiumOrder(PDO $pdo, int $userId, array $productIds, array $options = []): array {
	ensureCommercePurchaseSchema($pdo);

	$quote = buildPremiumCheckoutQuote($pdo, $userId, $productIds, $options['coupon_code'] ?? '');
	if (empty($quote['ok'])) {
		return $quote;
	}

	$purchasable = $quote['purchasable_products'] ?? [];
	if ($purchasable === []) {
		return [
			'ok' => true,
			'order_id' => null,
			'payment_reference' => null,
			'purchased_ids' => [],
			'already_owned_ids' => array_values($quote['already_owned_ids'] ?? []),
			'skipped_non_premium_ids' => array_values($quote['skipped_non_premium_ids'] ?? []),
			'subtotal_amount' => 0,
			'discount_amount' => 0,
			'total_amount' => 0,
			'coupon' => $quote['coupon'] ?? null,
			'payment_method' => 'vietqr',
			'message' => 'All selected premium products are already in your inventory.',
		];
	}

	$pdo->beginTransaction();
	try {
		$subtotalAmount = max(0.0, (float) ($quote['subtotal_amount'] ?? 0));
		$discountAmount = max(0.0, (float) ($quote['discount_amount'] ?? 0));
		$totalAmount = max(0.0, (float) ($quote['total_amount'] ?? 0));
		$coupon = $quote['coupon'] ?? null;
		$couponCodeToSave = is_array($coupon) ? (string) ($coupon['code'] ?? '') : null;

		$stmt = $pdo->prepare('INSERT INTO Orders (user_id, total_amount, original_amount, discount_amount, coupon_code, payment_method, payment_status, delivery_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
		$stmt->execute([$userId, $totalAmount, $subtotalAmount, $discountAmount, $couponCodeToSave, 'vietqr', 'pending', 'pending']);
		$orderId = (int) $pdo->lastInsertId();
		$paymentReference = 'AF3D-MO-' . $orderId;

		$pdo->prepare('UPDATE Orders SET payment_reference = ? WHERE id = ?')->execute([$paymentReference, $orderId]);

		$itemStmt = $pdo->prepare('INSERT INTO Order_Items (order_id, product_id, quantity, price_at_purchase) VALUES (?, ?, 1, ?)');
		foreach ($purchasable as $productId => $product) {
			$itemStmt->execute([$orderId, (int) $productId, max(0, (float) ($product['price'] ?? 0))]);
		}

		$pdo->commit();

		return [
			'ok' => true,
			'order_id' => $orderId,
			'payment_reference' => $paymentReference,
			'purchased_ids' => array_map('intval', array_keys($purchasable)),
			'already_owned_ids' => array_values($quote['already_owned_ids'] ?? []),
			'skipped_non_premium_ids' => array_values($quote['skipped_non_premium_ids'] ?? []),
			'subtotal_amount' => $subtotalAmount,
			'discount_amount' => $discountAmount,
			'total_amount' => $totalAmount,
			'coupon' => $coupon,
			'payment_method' => 'vietqr',
		];
	} catch (Throwable $e) {
		if ($pdo->inTransaction()) {
			$pdo->rollBack();
		}
		error_log('Error creating pending premium order: ' . $e->getMessage());
		return ['ok' => false, 'message' => 'Unable to create the payment order right now.'];
	}
}

function getOrderByPaymentReference(PDO $pdo, string $paymentReference): ?array {
	$reference = trim($paymentReference);
	if ($reference === '') {
		return null;
	}

	$stmt = $pdo->prepare('SELECT * FROM Orders WHERE payment_reference = ? LIMIT 1');
	$stmt->execute([$reference]);
	$row = $stmt->fetch(PDO::FETCH_ASSOC);
	return $row ?: null;
}

function updateOrderPaymentStatusByReference(PDO $pdo, string $paymentReference, string $paymentStatus, string $deliveryStatus): bool {
	$order = getOrderByPaymentReference($pdo, $paymentReference);
	if (!$order) {
		return false;
	}

	try {
		$stmt = $pdo->prepare('UPDATE Orders SET payment_status = ?, delivery_status = ? WHERE id = ?');
		$stmt->execute([$paymentStatus, $deliveryStatus, (int) $order['id']]);
		return true;
	} catch (Throwable $e) {
		error_log('Error updating order payment status: ' . $e->getMessage());
		return false;
	}
}

function finalizePendingPremiumOrder(PDO $pdo, string $paymentReference, array $gatewayData = []): array {
	ensureCommercePurchaseSchema($pdo);

	$order = getOrderByPaymentReference($pdo, $paymentReference);
	if (!$order) {
		return ['ok' => false, 'message' => 'Order not found.'];
	}

	$orderId = (int) ($order['id'] ?? 0);
	if ($orderId <= 0) {
		return ['ok' => false, 'message' => 'Invalid order.'];
	}

	$status = strtolower(trim((string) ($order['payment_status'] ?? 'pending')));
	if ($status === 'completed') {
		return [
			'ok' => true,
			'order_id' => $orderId,
			'already_finalized' => true,
			'payment_reference' => (string) ($order['payment_reference'] ?? ''),
		];
	}

	$itemsStmt = $pdo->prepare('SELECT product_id FROM Order_Items WHERE order_id = ?');
	$itemsStmt->execute([$orderId]);
	$items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
	if (!is_array($items) || $items === []) {
		return ['ok' => false, 'message' => 'Order items not found.'];
	}

	$userId = (int) ($order['user_id'] ?? 0);
	$couponCode = strtoupper(trim((string) ($order['coupon_code'] ?? '')));
	$discountAmount = max(0.0, (float) ($order['discount_amount'] ?? 0));

	$pdo->beginTransaction();
	try {
		$inventoryStmt = $pdo->prepare('INSERT IGNORE INTO User_Inventory (user_id, product_id, source_order_id) VALUES (?, ?, ?)');
		foreach ($items as $item) {
			$productId = (int) ($item['product_id'] ?? 0);
			if ($productId > 0) {
				$inventoryStmt->execute([$userId, $productId, $orderId]);
			}
		}

		if ($couponCode !== '' && $discountAmount > 0) {
			$pdo->prepare('UPDATE Coupon_Codes SET used_count = used_count + 1 WHERE code = ?')->execute([$couponCode]);
		}

		$pdo->prepare('UPDATE Orders SET payment_status = ?, delivery_status = ? WHERE id = ?')->execute(['completed', 'Delivered', $orderId]);
		$pdo->commit();

		return [
			'ok' => true,
			'order_id' => $orderId,
			'payment_reference' => (string) ($order['payment_reference'] ?? ''),
			'gateway_data' => $gatewayData,
		];
	} catch (Throwable $e) {
		if ($pdo->inTransaction()) {
			$pdo->rollBack();
		}
		error_log('Error finalizing pending premium order: ' . $e->getMessage());
		return ['ok' => false, 'message' => 'Unable to finalize payment right now.'];
	}
}

function userOwnsProduct(PDO $pdo, int $userId, int $productId): bool {
	try {
		ensureUserInventoryTable($pdo);
		$stmt = $pdo->prepare('SELECT 1 FROM User_Inventory WHERE user_id = ? AND product_id = ? LIMIT 1');
		$stmt->execute([$userId, $productId]);
		return (bool) $stmt->fetchColumn();
	} catch (Throwable $e) {
		error_log('Error checking product ownership: ' . $e->getMessage());
		return false;
	}
}

function getUserInventoryProducts(PDO $pdo, int $userId): array {
	try {
		ensureUserInventoryTable($pdo);
		ensureProductsPremiumColumn($pdo);
		$stmt = $pdo->prepare('SELECT ui.product_id, ui.acquired_at, p.name, p.description, p.image_url, p.model_3d_url, p.tags, p.price, p.is_premium,
									  COALESCE(c.name, "Uncategorized") AS category_name
							   FROM User_Inventory ui
							   INNER JOIN Products p ON p.id = ui.product_id
							   LEFT JOIN Categories c ON c.id = p.category_id
							   WHERE ui.user_id = ?
							   ORDER BY ui.acquired_at DESC');
		$stmt->execute([$userId]);
		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
		if (!is_array($rows)) {
			return [];
		}

		return array_map(static function ($row) {
			return [
				'product_id' => (int) ($row['product_id'] ?? 0),
				'name' => (string) ($row['name'] ?? 'Unknown Product'),
				'description' => (string) ($row['description'] ?? ''),
				'thumbPath' => (string) ($row['image_url'] ?? 'images/model-placeholder.svg'),
				'modelPath' => (string) ($row['model_3d_url'] ?? ''),
				'category' => (string) ($row['category_name'] ?? 'Uncategorized'),
				'tags' => parseProductTags($row['tags'] ?? ''),
				'isPremium' => isProductEffectivelyPremium($row['is_premium'] ?? 0, $row['price'] ?? 0),
				'basePrice' => (float) ($row['price'] ?? 0),
				'acquired_at' => (string) ($row['acquired_at'] ?? ''),
			];
		}, $rows);
	} catch (Throwable $e) {
		error_log('Error fetching inventory products: ' . $e->getMessage());
		return [];
	}
}
