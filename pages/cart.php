<?php require '../components/header.php'; ?>
<?php require '../components/navBar.php'; ?>

<?php $customerUser = authCurrentUser(); ?>

<script src="https://cdn.tailwindcss.com"></script>
<style>
  .site-navbar .collapse {
    visibility: visible;
  }
</style>

<main class="bg-slate-50 min-h-screen">
  <section class="mx-auto max-w-5xl px-4 py-8 lg:py-10">
    <div class="mb-6">
      <h1 class="mt-2 text-3xl font-extrabold tracking-tight text-slate-900">Shopping Cart</h1>
    </div>

    <div id="cart-empty-state" class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center hidden">
      <h2 class="text-xl font-semibold text-slate-900">Your cart is empty</h2>
      <p class="mt-2 text-sm text-slate-500">Add products from the detail page to see them here.</p>
      <a href="index.php?p=products" class="mt-5 inline-flex rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">Browse Products</a>
    </div>

    <div id="cart-content" class="grid gap-6 lg:grid-cols-[1fr_320px]">
      <div id="cart-items-root" class="space-y-3"></div>

      <aside class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm h-fit">
        <h2 class="text-lg font-bold text-slate-900">Order Summary</h2>
        <div class="mt-4 space-y-2 text-sm">
          <div class="flex items-center justify-between">
            <span class="text-slate-500">Subtotal</span>
            <strong id="cart-subtotal" class="text-slate-700">0 ₫</strong>
          </div>
          <div class="flex items-center justify-between">
            <span class="text-slate-500">Discount</span>
            <strong id="cart-discount" class="text-emerald-600">-0 ₫</strong>
          </div>
          <div class="flex items-center justify-between border-t border-slate-200 pt-2">
            <span class="text-slate-500">Total</span>
            <strong id="cart-total" class="text-xl text-slate-900">0 ₫</strong>
          </div>
        </div>

        <div class="mt-4 rounded-xl border border-slate-200 p-3">
          <label for="cart-coupon-code" class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Coupon code</label>
          <div class="mt-2 flex gap-2">
            <input id="cart-coupon-code" type="text" maxlength="64" placeholder="e.g. WELCOME10" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none" />
            <button id="cart-apply-coupon-btn" type="button" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Apply</button>
          </div>
          <p id="cart-coupon-feedback" class="mt-2 min-h-[1.25rem] text-xs text-slate-500"></p>
        </div>

        <div class="mt-4 rounded-xl border border-slate-200 p-3">
          <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Payment method</p>
          <div class="mt-2 grid gap-2 text-sm">
            <label class="payment-method-card flex items-center gap-3 rounded-2xl border border-slate-200 bg-white px-3 py-3 cursor-pointer transition hover:bg-slate-50">
              <input type="radio" name="payment_method" value="vietqr" class="sr-only">
              <img src="images/vietqr-logo_svgstack_com_74491776584583.png" alt="VietQR logo" class="h-7 w-7 object-contain" loading="lazy">
              <span class="font-medium text-slate-700">VietQR Transfer</span>
            </label>
            <label class="payment-method-card flex items-center gap-3 rounded-2xl border border-slate-200 bg-white px-3 py-3 cursor-pointer transition hover:bg-slate-50">
              <input type="radio" name="payment_method" value="momo" class="sr-only">
              <img src="images/MOMO-Logo-App.png" alt="MoMo logo" class="h-7 w-7 object-contain" loading="lazy">
              <span class="font-medium text-slate-700">MoMo Wallet <span class="text-xs text-slate-400">(coming soon)</span></span>
            </label>
            <label class="payment-method-card flex items-center gap-3 rounded-2xl border border-slate-200 bg-white px-3 py-3 cursor-pointer transition hover:bg-slate-50">
              <input type="radio" name="payment_method" value="zalopay" class="sr-only">
              <img src="images/logo_zalopay.png" alt="ZaloPay logo" class="h-7 w-7 object-contain" loading="lazy">
              <span class="font-medium text-slate-700">ZaloPay Wallet <span class="text-xs text-slate-400">(coming soon)</span></span>
            </label>
          </div>
        </div>

        <button
          type="button"
          id="cart-checkout-btn"
          data-auth="<?php echo $customerUser ? '1' : '0'; ?>"
          data-csrf="<?php echo htmlspecialchars(authCSRFToken(), ENT_QUOTES, 'UTF-8'); ?>"
          class="mt-5 w-full rounded-xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white hover:bg-slate-700"
        >
          Checkout
        </button>
      </aside>
    </div>
  </section>

</main>

<script src="js/cart-page.js"></script>

<?php require '../components/footer.php'; ?>
