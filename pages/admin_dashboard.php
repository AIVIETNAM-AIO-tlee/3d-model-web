<?php
$adminActivePage = 'dashboard';
require '../components/header.php';

authRequireAdmin();

$pdo = authGetPDO();
$adminUser = authCurrentAdmin();

try {
    $totalRevenue = (float) ($pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM Orders WHERE payment_status = 'completed'")->fetchColumn() ?: 0);
} catch (Throwable $e) {
    $totalRevenue = 0.0;
}

try {
    $totalOrders = (int) ($pdo->query('SELECT COUNT(*) FROM Orders')->fetchColumn() ?: 0);
} catch (Throwable $e) {
    $totalOrders = 0;
}

try {
    $totalUsers = (int) ($pdo->query('SELECT COUNT(*) FROM Users')->fetchColumn() ?: 0);
} catch (Throwable $e) {
    $totalUsers = 0;
}

$last7Labels = [];
$last7RevenueMap = [];
for ($offset = 6; $offset >= 0; $offset--) {
    $date = date('Y-m-d', strtotime("-{$offset} days"));
    $last7Labels[] = date('D', strtotime($date));
    $last7RevenueMap[$date] = 0.0;
}

try {
    $stmt = $pdo->query("SELECT DATE(created_at) AS order_date, COALESCE(SUM(total_amount), 0) AS revenue FROM Orders WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(created_at)");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $dateKey = (string) ($row['order_date'] ?? '');
        if (isset($last7RevenueMap[$dateKey])) {
            $last7RevenueMap[$dateKey] = (float) ($row['revenue'] ?? 0);
        }
    }
} catch (Throwable $e) {
    // Keep placeholder zero values when analytics data is unavailable.
}

$last7Revenue = array_values($last7RevenueMap);
$categoryLabels = [];
$categoryRevenue = [];

try {
    $stmt = $pdo->query("SELECT COALESCE(c.name, 'Uncategorized') AS category_name, COALESCE(SUM(oi.quantity * oi.price_at_purchase), 0) AS revenue FROM Order_Items oi INNER JOIN Orders o ON o.id = oi.order_id LEFT JOIN Products p ON p.id = oi.product_id LEFT JOIN Categories c ON c.id = p.category_id GROUP BY COALESCE(c.name, 'Uncategorized') ORDER BY revenue DESC LIMIT 6");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        $categoryLabels[] = (string) ($row['category_name'] ?? 'Category');
        $categoryRevenue[] = (float) ($row['revenue'] ?? 0);
    }
} catch (Throwable $e) {
    // Placeholder data below will be used if there is no order-item analytics yet.
}

if (empty($categoryLabels)) {
    try {
        $stmt = $pdo->query('SELECT COALESCE(c.name, "Uncategorized") AS category_name, COUNT(p.id) AS product_count FROM Products p LEFT JOIN Categories c ON c.id = p.category_id GROUP BY COALESCE(c.name, "Uncategorized") ORDER BY product_count DESC LIMIT 6');
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $categoryLabels[] = (string) ($row['category_name'] ?? 'Category');
            $count = (int) ($row['product_count'] ?? 0);
            $categoryRevenue[] = max($count, 1);
        }
    } catch (Throwable $e) {
        $categoryLabels = ['Vehicles', 'Characters', 'Props'];
        $categoryRevenue = [1, 1, 1];
    }
}

$recentOrders = [];
try {
    $stmt = $pdo->query("SELECT o.id, o.total_amount, o.delivery_status, o.created_at, u.full_name AS customer_name FROM Orders o LEFT JOIN Users u ON u.id = o.user_id ORDER BY o.created_at DESC LIMIT 6");
    $recentOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $recentOrders = [];
}
?>

<main class="admin-shell">
  <div class="admin-layout">
    <?php require '../components/admin_sidebar.php'; ?>

    <section class="admin-main">
      <div class="admin-topbar">
        <div>
          <h1 class="admin-title">Analytics Dashboard </h1>
        </div>
        <div class="text-end">
          <div class="small text-secondary">Welcome back</div>
          <div class="fw-semibold"><?php echo htmlspecialchars($adminUser['full_name'] ?? 'Admin', ENT_QUOTES, 'UTF-8'); ?></div>
        </div>
      </div>

      <div class="admin-kpi-grid mb-4">
        <div class="admin-kpi-card">
          <span class="admin-kpi-label">Total Revenue</span>
          <span class="admin-kpi-value"><?php echo formatCurrencyVND($totalRevenue); ?></span>
        </div>
        <div class="admin-kpi-card">
          <span class="admin-kpi-label">Total Orders</span>
          <span class="admin-kpi-value"><?php echo number_format($totalOrders); ?></span>
        </div>
        <div class="admin-kpi-card">
          <span class="admin-kpi-label">Total Users</span>
          <span class="admin-kpi-value"><?php echo number_format($totalUsers); ?></span>
        </div>
      </div>

      <div class="admin-grid-2 mb-4">
        <div class="admin-panel">
          <div class="admin-panel-header">
            <div>
              <h2 class="admin-panel-title">Sales last 7 days</h2>
            </div>
          </div>
          <div class="admin-chart-wrap">
            <canvas id="salesLast7DaysChart" class="admin-chart-canvas"></canvas>
          </div>
        </div>

        <div class="admin-panel">
          <div class="admin-panel-header">
            <div>
              <h2 class="admin-panel-title">Sales by Category</h2>
            </div>
          </div>
          <div class="admin-chart-wrap">
            <canvas id="salesByCategoryChart" class="admin-chart-canvas"></canvas>
          </div>
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
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($recentOrders)): ?>
                <?php foreach ($recentOrders as $order): ?>
                  <?php $status = authDigitalOrderStatus((string) ($order['delivery_status'] ?? 'pending')); ?>
                  <tr>
                    <td>#<?php echo (int) ($order['id'] ?? 0); ?></td>
                    <td><?php echo htmlspecialchars((string) ($order['customer_name'] ?? 'Guest'), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo formatCurrencyVND($order['total_amount'] ?? 0); ?></td>
                    <td><span class="admin-status-badge <?php echo htmlspecialchars($status['class'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($status['label'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="4" class="text-center text-secondary py-5">No orders yet. Once orders are placed, they will appear here.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>
  </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  const salesLast7DaysCtx = document.getElementById('salesLast7DaysChart');
  const salesByCategoryCtx = document.getElementById('salesByCategoryChart');
  const last7Labels = <?php echo json_encode($last7Labels); ?>;
  const last7Revenue = <?php echo json_encode($last7Revenue); ?>;
  const categoryLabels = <?php echo json_encode($categoryLabels); ?>;
  const categoryRevenue = <?php echo json_encode($categoryRevenue); ?>;

  if (salesLast7DaysCtx) {
    new Chart(salesLast7DaysCtx, {
      type: 'bar',
      data: {
        labels: last7Labels,
        datasets: [{
          label: 'Revenue',
          data: last7Revenue,
          backgroundColor: 'rgba(37, 99, 235, 0.75)',
          borderRadius: 10,
          maxBarThickness: 42,
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        animation: false,
        plugins: {
          legend: { display: false },
        },
        scales: {
          y: {
            beginAtZero: true,
            grid: { color: 'rgba(15, 23, 42, 0.08)' },
            ticks: {
              callback: value => new Intl.NumberFormat('vi-VN').format(value) + ' ₫',
            },
          },
          x: {
            grid: { display: false },
          },
        },
      },
    });
  }

  if (salesByCategoryCtx) {
    new Chart(salesByCategoryCtx, {
      type: 'pie',
      data: {
        labels: categoryLabels,
        datasets: [{
          data: categoryRevenue,
          backgroundColor: [
            '#2563eb',
            '#14b8a6',
            '#f59e0b',
            '#22c55e',
            '#ef4444',
            '#8b5cf6',
          ],
          borderWidth: 0,
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        animation: false,
        plugins: {
          legend: {
            position: 'bottom',
            labels: {
              usePointStyle: true,
              pointStyle: 'circle',
            },
          },
        },
      },
    });
  }
</script>
</body>
</html>
