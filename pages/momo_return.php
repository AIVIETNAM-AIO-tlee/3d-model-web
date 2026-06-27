<?php require '../components/header.php'; ?>
<?php require '../components/navBar.php'; ?>
<?php
$pdo = authGetPDO();

$payload = array_merge($_GET ?? [], $_POST ?? []);
$callbackResult = null;
$order = null;
$paymentReference = trim((string) ($payload['orderId'] ?? $payload['order_id'] ?? ''));
$requestedOrderId = (int) ($payload['order_id'] ?? 0);

if (!empty($payload['resultCode']) || !empty($payload['signature']) || !empty($payload['transId'])) {
    $callbackResult = paymentMomoFinalizeCallback($pdo, $payload);
    if (!empty($callbackResult['ok'])) {
        $order = getOrderByPaymentReference($pdo, (string) ($callbackResult['payment_reference'] ?? $paymentReference));
    }
}

if (!$order && $requestedOrderId > 0) {
    $order = getOrderByPaymentReference($pdo, 'AF3D-MO-' . $requestedOrderId);
}

if (!$order && $paymentReference !== '') {
    $order = getOrderByPaymentReference($pdo, $paymentReference);
}

$paymentStatus = (string) ($order['payment_status'] ?? 'pending');
$orderId = (int) ($order['id'] ?? $requestedOrderId);
$amount = (float) ($order['total_amount'] ?? 0);
$statusText = 'Waiting for payment confirmation.';
$statusTone = 'warning';
$success = false;

if ($callbackResult && !empty($callbackResult['ok'])) {
    $statusText = 'Payment confirmed and inventory updated successfully.';
    $statusTone = 'success';
    $success = true;
} elseif ($paymentStatus === 'completed') {
    $statusText = 'Payment completed and inventory updated.';
    $statusTone = 'success';
    $success = true;
} elseif (!empty($payload['resultCode']) && (int) $payload['resultCode'] !== 0) {
    $statusText = (string) ($payload['message'] ?? 'Payment was not completed.');
    $statusTone = 'danger';
} elseif ($paymentStatus === 'failed') {
    $statusText = 'Payment failed or was cancelled.';
    $statusTone = 'danger';
}
?>

<main class="bg-slate-50 min-h-screen">
  <section class="mx-auto max-w-3xl px-4 py-10">
    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
      <p class="text-xs font-semibold uppercase tracking-[0.18em] text-blue-600">MoMo Sandbox Return</p>
      <h1 class="mt-2 text-3xl font-extrabold tracking-tight text-slate-900">Payment status</h1>
      <p class="mt-3 text-sm text-slate-600"><?php echo htmlspecialchars($statusText, ENT_QUOTES, 'UTF-8'); ?></p>

      <div class="mt-6 grid gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-5 text-sm text-slate-700">
        <p><strong>Order ID:</strong> <?php echo $orderId > 0 ? '#' . (int) $orderId : '-'; ?></p>
        <p><strong>Reference:</strong> <?php echo htmlspecialchars((string) ($order['payment_reference'] ?? $paymentReference), ENT_QUOTES, 'UTF-8'); ?></p>
        <p><strong>Amount:</strong> <?php echo formatCurrencyVND($amount); ?></p>
        <p><strong>Status:</strong> <?php echo htmlspecialchars(ucfirst($paymentStatus), ENT_QUOTES, 'UTF-8'); ?></p>
      </div>

      <div class="mt-6 flex flex-wrap gap-3">
        <a href="index.php?p=inventory" class="rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white hover:bg-slate-700">Go to Inventory</a>
        <a href="index.php?p=products" class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-100">Continue Shopping</a>
      </div>
    </div>
  </section>
</main>

<script>
  (function () {
    var success = <?php echo $success ? 'true' : 'false'; ?>;
    if (success && window.CartUtil) {
      window.CartUtil.clearCart();
    } else if (success) {
      localStorage.removeItem('shopping_cart_items');
      window.dispatchEvent(new CustomEvent('cart:updated', { detail: { items: [] } }));
    }
  })();
</script>

<?php require '../components/footer.php'; ?>
