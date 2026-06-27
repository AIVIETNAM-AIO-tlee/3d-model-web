<?php
$adminActivePage = 'orders';
require '../components/header.php';

authRequireAdmin();

$pdo = authGetPDO();
$adminUser = authCurrentAdmin();

$statusCounts = [
    'Queued' => 0,
    'Preparing Download' => 0,
    'Delivered' => 0,
    'Cancelled' => 0,
];

try {
    $stmt = $pdo->query('SELECT delivery_status, COUNT(*) AS total FROM Orders GROUP BY delivery_status');
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $status = authDigitalOrderStatus((string) ($row['delivery_status'] ?? ''));
        $statusCounts[$status['label']] = (int) ($row['total'] ?? 0);
    }
} catch (Throwable $e) {
    // Keep zero counts when analytics data is unavailable.
}

try {
    $stmt = $pdo->query("SELECT o.id, o.total_amount, o.delivery_status, o.created_at, u.full_name AS customer_name, u.email AS customer_email FROM Orders o LEFT JOIN Users u ON u.id = o.user_id ORDER BY o.created_at DESC LIMIT 50");
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $orders = [];
}
?>

<main class="admin-shell">
  <div class="admin-layout">
    <?php require '../components/admin_sidebar.php'; ?>

    <section class="admin-main">
      <div class="admin-topbar align-items-end">
        <div>
          <h1 class="admin-title">Orders</h1>
        </div>
        <div class="admin-products-toolbar">
          <span class="badge text-bg-warning text-dark">Queued: <?php echo (int) $statusCounts['Queued']; ?></span>
          <span class="badge text-bg-info text-dark">Preparing: <?php echo (int) $statusCounts['Preparing Download']; ?></span>
          <span class="badge text-bg-success">Delivered: <?php echo (int) $statusCounts['Delivered']; ?></span>
        </div>
      </div>

      <div class="admin-table-card">
        <div class="p-3 p-lg-4 border-bottom">
          <h2 class="admin-panel-title mb-1">Recent Orders</h2>
        </div>
        <div class="table-responsive">
          <table class="table align-middle admin-products-table mb-0">
            <thead class="table-light">
              <tr>
                <th>Order ID</th>
                <th>Customer</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Created</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($orders)): ?>
                <?php foreach ($orders as $order): ?>
                  <?php $status = authDigitalOrderStatus((string) ($order['delivery_status'] ?? 'pending')); ?>
                  <tr>
                    <td>#<?php echo (int) $order['id']; ?></td>
                    <td>
                      <div class="fw-semibold"><?php echo htmlspecialchars((string) ($order['customer_name'] ?? 'Guest'), ENT_QUOTES, 'UTF-8'); ?></div>
                      <div class="small text-secondary"><?php echo htmlspecialchars((string) ($order['customer_email'] ?? 'No email'), ENT_QUOTES, 'UTF-8'); ?></div>
                    </td>
                    <td><?php echo formatCurrencyVND($order['total_amount']); ?></td>
                    <td><span class="admin-status-badge <?php echo htmlspecialchars($status['class'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($status['label'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                    <td class="text-secondary small"><?php echo htmlspecialchars(date('M d, Y H:i', strtotime((string) $order['created_at'])), ENT_QUOTES, 'UTF-8'); ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="5" class="text-center text-secondary py-5">No orders found yet.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>
  </div>
</main>

</body>
</html>
