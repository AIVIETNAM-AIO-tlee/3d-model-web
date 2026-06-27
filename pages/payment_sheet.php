<?php require '../components/header.php'; ?>
<?php require '../components/navBar.php'; ?>

<script src="https://cdn.tailwindcss.com"></script>
<style>
  .site-navbar .collapse {
    visibility: visible;
  }
</style>

<main class="bg-slate-50 min-h-screen">
  <section class="mx-auto max-w-6xl px-4 py-8 lg:py-10">
    <div class="mb-6 flex items-center justify-between gap-3">
      <div>
        <h1 id="payment-sheet-method" class="mt-2 text-2xl font-extrabold tracking-tight text-slate-900">VietQR Bank Transfer</h1>
      </div>
      <a href="index.php?p=cart" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Back to cart</a>
    </div>

    <div id="payment-sheet-content" class="grid gap-8 lg:grid-cols-[minmax(0,1.1fr)_minmax(320px,0.9fr)]">
      <div class="p-2">
        <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Scan to pay</p>
        <div class="mt-4 flex items-center justify-center">
          <img id="payment-sheet-qr" src="" alt="Payment QR" class="hidden h-[22rem] w-[22rem] object-contain sm:h-[30rem] sm:w-[30rem]" />
        </div>
        <p class="mt-4 text-center text-xs text-slate-500">Use your payment app to scan the QR code</p>
      </div>

      <div class="p-2">
        <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Payment details</p>
        <div class="mt-3 grid gap-2 text-sm">
          <p class="flex items-center justify-between py-1 text-slate-700"><span>Amount</span><span id="payment-sheet-amount" class="font-semibold text-slate-900">0 ₫</span></p>
          <p class="flex items-center justify-between py-1 text-slate-700"><span>Reference</span><span id="payment-sheet-ref" class="font-semibold text-slate-900">-</span></p>
          <p class="flex items-center justify-between py-1 text-slate-700"><span>Transfer note</span><span id="payment-sheet-note" class="font-semibold text-slate-900">-</span></p>
        </div>

        <div class="mt-6">
          <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Steps</p>
          <ol id="payment-sheet-steps" class="mt-3 space-y-2 text-sm text-slate-600"></ol>
        </div>

        <div class="mt-6">
          <p id="payment-sheet-extra" class="mt-2 text-sm text-slate-600"></p>
        </div>

        <a id="payment-sheet-open-url" href="#" target="_blank" rel="noopener noreferrer" class="hidden mt-4 inline-flex rounded-xl border border-blue-200 bg-blue-50 px-4 py-2 text-sm font-semibold text-blue-700 hover:bg-blue-100">Open payment link</a>

        <div class="mt-6 grid grid-cols-1 gap-3 sm:grid-cols-2">
          <a href="index.php?p=cart" class="rounded-xl border border-slate-300 px-4 py-3 text-center text-sm font-semibold text-slate-700 hover:bg-slate-100">Back</a>
          <button id="payment-sheet-done" type="button" class="rounded-xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white hover:bg-slate-700">I have transferred</button>
        </div>

        <p id="payment-sheet-warning" class="mt-4 hidden rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-700"></p>
      </div>
    </div>

    <section id="payment-sheet-success" class="hidden text-center py-8">
      <div class="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-emerald-500 text-5xl font-bold text-white">✓</div>
      <h2 class="mt-4 text-5xl font-extrabold tracking-tight text-emerald-700">Order Successful!</h2>
      <p id="payment-sheet-success-order" class="mt-2 text-3xl font-semibold text-emerald-700">Order ID: -</p>
      <ul class="mx-auto mt-8 max-w-4xl space-y-4 text-left text-4xl text-slate-700">
        <li>✓ Check your inventory for purchased models.</li>
        <li>✓ If items are missing, refresh after a few seconds.</li>
        <li>✓ Need support? Contact hotline, chatbot, or email.</li>
      </ul>
      <a href="index.php?p=home" class="mt-8 inline-flex rounded-full border border-slate-300 bg-white px-10 py-3 text-2xl font-semibold text-slate-800 hover:bg-slate-100">Turn back to Home</a>
    </section>
  </section>
</main>

<script src="js/payment-sheet-page.js"></script>

<?php require '../components/footer.php'; ?>
