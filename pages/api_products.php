<?php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = getDBConnection();

    $category = isset($_GET['category']) ? trim((string) $_GET['category']) : 'All';
    $sort = isset($_GET['sort']) ? trim((string) $_GET['sort']) : 'default';
    $search = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
    $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
    $perPage = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 12;

    if ($page < 1) {
        $page = 1;
    }

    if ($perPage < 1) {
        $perPage = 12;
    }

    if ($perPage > 24) {
        $perPage = 24;
    }

    $products = getProducts($pdo, $category, $sort, $search);
    $totalItems = count($products);
    $totalPages = max(1, (int) ceil($totalItems / $perPage));

    if ($page > $totalPages) {
        $page = $totalPages;
    }

    $offset = ($page - 1) * $perPage;
    $pagedProducts = array_slice($products, $offset, $perPage);

    $responseItems = array_map(static function ($product) {
        return [
            'id' => $product['id'] ?? null,
            'slug' => $product['slug'] ?? '',
            'name' => $product['name'] ?? 'Unknown Product',
            'price' => $product['price'] ?? 0,
            'base_price' => $product['basePrice'] ?? 0,
            'is_premium' => !empty($product['isPremium']),
            'tags' => $product['tags'] ?? [],
            'description' => $product['description'] ?? '',
            'image_url' => $product['thumbPath'] ?? 'images/model-placeholder.svg',
            'model_3d_url' => $product['modelPath'] ?? 'assets/models/model-placeholder.glb',
            'category' => $product['category'] ?? 'Uncategorized',
        ];
    }, $pagedProducts);

    echo json_encode([
        'items' => $responseItems,
        'pagination' => [
            'page' => $page,
            'per_page' => $perPage,
            'total_items' => $totalItems,
            'total_pages' => $totalPages,
            'has_prev' => $page > 1,
            'has_next' => $page < $totalPages,
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Unable to load products.',
        'details' => $e->getMessage(),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}
