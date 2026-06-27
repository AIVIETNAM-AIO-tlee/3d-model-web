<?php
$page = $_GET['p'] ?? 'home';

$routes = [
	'home' => '../pages/home.php',
	'products' => '../pages/products.php',
	'featured' => '../pages/featured.php',
	'about' => '../pages/about.php',
	'product_detail' => '../pages/product_detail.php',
	'cart' => '../pages/cart.php',
	'payment_sheet' => '../pages/payment_sheet.php',
	'inventory' => '../pages/inventory.php',
	'login' => '../pages/login.php',
	'register' => '../pages/register.php',
	'logout' => '../pages/logout.php',
	'auth_google' => '../pages/auth_google.php',
	'admin_login' => '../pages/admin_login.php',
	'admin_logout' => '../pages/admin_logout.php',
	'admin_dashboard' => '../pages/admin_dashboard.php',
	'admin_products' => '../pages/admin_products.php',
	'admin_orders' => '../pages/admin_orders.php',
	'terms' => '../pages/terms.php',
	'privacy' => '../pages/privacy.php',
	'shipping' => '../pages/shipping.php',
	'refund' => '../pages/refund.php',
	'api_products' => '../pages/api_products.php',
	'api_coupon' => '../pages/api_coupon.php',
	'api_purchase' => '../pages/api_purchase.php',
	'api_momo_ipn' => '../pages/api_momo_ipn.php',
	'momo_return' => '../pages/momo_return.php',
	'api_chatbot' => '../pages/api_chatbot.php',
];

if (!isset($routes[$page])) {
	http_response_code(404);
	exit('Page not found');
}

require $routes[$page];