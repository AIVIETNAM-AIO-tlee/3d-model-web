<?php
$currentPage = $_GET['p'] ?? 'home';
$authUser = authCurrentUser();
?>

<nav class="navbar navbar-expand-lg navbar-light site-navbar sticky-top">
	<div class="container py-1">
		<a class="navbar-brand site-brand" href="index.php" aria-label="Asset Forge 3D home">
			<span class="brand-mark"><img src="images/modeling-svgrepo-com.svg" alt="Asset Forge 3D logo" class="header-brand-icon"></span>
			<span class="brand-text">Asset Forge 3D</span>
		</a>

		<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#siteNav" aria-controls="siteNav" aria-expanded="false" aria-label="Toggle navigation">
			<span class="navbar-toggler-icon"></span>
		</button>

		<div class="collapse navbar-collapse" id="siteNav">
			<ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2 mt-3 mt-lg-0">
				<li class="nav-item"><a class="nav-link <?php echo $currentPage === 'home' ? 'active' : ''; ?>" href="index.php">Home</a></li>
				<li class="nav-item"><a class="nav-link <?php echo $currentPage === 'products' ? 'active' : ''; ?>" href="index.php?p=products">Products</a></li>
				<li class="nav-item"><a class="nav-link <?php echo $currentPage === 'featured' ? 'active' : ''; ?>" href="index.php?p=featured">Featured</a></li>
				<li class="nav-item"><a class="nav-link <?php echo $currentPage === 'about' ? 'active' : ''; ?>" href="index.php?p=about">About</a></li>
				<?php if ($authUser): ?>
					<li class="nav-item">
						<span class="nav-link disabled" aria-disabled="true">Hi, <?php echo htmlspecialchars($authUser['full_name'], ENT_QUOTES, 'UTF-8'); ?></span>
					</li>
				<?php else: ?>
					<li class="nav-item"><a class="nav-link <?php echo $currentPage === 'login' ? 'active' : ''; ?>" href="index.php?p=login">Login</a></li>
					<li class="nav-item"><a class="nav-link <?php echo $currentPage === 'register' ? 'active' : ''; ?>" href="index.php?p=register">Register</a></li>
				<?php endif; ?>
				<?php if ($authUser): ?>
					<li class="nav-item">
						<a class="nav-link nav-icon-link <?php echo $currentPage === 'inventory' ? 'active' : ''; ?>" href="index.php?p=inventory" aria-label="Open inventory" title="Inventory">
							<span class="nav-icon" aria-hidden="true">
								<svg viewBox="0 0 24 24" focusable="false" aria-hidden="true">
									<path d="M3 7.2A2.2 2.2 0 0 1 5.2 5h13.6A2.2 2.2 0 0 1 21 7.2v9.6a2.2 2.2 0 0 1-2.2 2.2H5.2A2.2 2.2 0 0 1 3 16.8V7.2Zm2 0v2.1h14V7.2a.2.2 0 0 0-.2-.2H5.2a.2.2 0 0 0-.2.2Zm14 4.1H5v5.5c0 .11.09.2.2.2h13.6a.2.2 0 0 0 .2-.2v-5.5Zm-9 1.2h4a1 1 0 1 1 0 2h-4a1 1 0 1 1 0-2Z"/>
								</svg>
							</span>
							<span class="visually-hidden">Inventory</span>
						</a>
					</li>
				<?php endif; ?>
				<li class="nav-item">
					<a class="nav-link nav-icon-link cart-link <?php echo $currentPage === 'cart' ? 'active' : ''; ?>" href="index.php?p=cart" aria-label="View cart" title="Cart">
						<span class="nav-icon" aria-hidden="true">
							<svg viewBox="0 0 24 24" focusable="false" aria-hidden="true">
								<path d="M7 4H5.2L4.7 2.7A1 1 0 0 0 3.8 2H2a1 1 0 0 0 0 2h1.1l2.1 5.9-1 1.8A2 2 0 0 0 5.9 14H19a1 1 0 0 0 0-2H6l.7-1.2h10.6a2 2 0 0 0 1.9-1.4l1.6-4.6A1 1 0 0 0 19.9 4H7Zm1.7 14a1.7 1.7 0 1 0 0 3.4A1.7 1.7 0 0 0 8.7 18Zm8.6 0a1.7 1.7 0 1 0 0 3.4 1.7 1.7 0 0 0 0-3.4Z"/>
							</svg>
						</span>
						<span id="nav-cart-count" class="cart-count-badge" aria-live="polite">0</span>
						<span class="visually-hidden">Cart</span>
					</a>
				</li>
				<?php if ($authUser): ?>
					<li class="nav-item">
						<form method="post" action="index.php?p=logout" class="m-0">
							<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(authCSRFToken(), ENT_QUOTES, 'UTF-8'); ?>">
							<button type="submit" class="btn btn-outline-dark btn-sm">Logout</button>
						</form>
					</li>
				<?php endif; ?>
			</ul>
		</div>
	</div>
</nav>
