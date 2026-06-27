<?php
$adminActivePage = $adminActivePage ?? 'dashboard';
$adminUser = authCurrentAdmin();

function adminNavClass(string $pageKey, string $activePage): string {
    return 'admin-nav-link' . ($pageKey === $activePage ? ' active' : '');
}
?>
<aside class="admin-sidebar">
  <div>
    <a class="admin-brand" href="index.php?p=admin_dashboard" aria-label="Asset Forge 3D Admin">
      <span class="admin-brand-mark">AF</span>
      <span>
        <strong class="d-block">Asset Forge 3D</strong>
        <small>Admin Portal</small>
      </span>
    </a>

    <nav class="admin-nav mt-4">
      <a class="<?php echo adminNavClass('dashboard', $adminActivePage); ?>" href="index.php?p=admin_dashboard">Dashboard</a>
      <a class="<?php echo adminNavClass('products', $adminActivePage); ?>" href="index.php?p=admin_products">Products</a>
      <a class="<?php echo adminNavClass('orders', $adminActivePage); ?>" href="index.php?p=admin_orders">Orders</a>
    </nav>
  </div>

  <div class="admin-sidebar-footer">
    <?php if ($adminUser): ?>
      <div class="admin-user-card">
        <span class="admin-user-label">Signed in as</span>
        <strong><?php echo htmlspecialchars($adminUser['full_name'], ENT_QUOTES, 'UTF-8'); ?></strong>
        <small><?php echo htmlspecialchars($adminUser['email'], ENT_QUOTES, 'UTF-8'); ?></small>
      </div>
      <form method="post" action="index.php?p=admin_logout" class="mt-3">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(authCSRFToken(), ENT_QUOTES, 'UTF-8'); ?>">
        <button type="submit" class="btn btn-light w-100">Logout</button>
      </form>
    <?php endif; ?>
  </div>
</aside>
