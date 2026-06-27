<?php require '../components/header.php'; ?>
<?php require '../components/navBar.php'; ?>

<main class="policy-page">
  <section class="container py-4 py-lg-5">
    <h1 class="section-title mb-3">Built for Interactive Product Discovery</h1>
    <p class="text-secondary mb-4">
      Asset Forge 3D is designed to help users browse, inspect, and compare products in a modern 3D-first shopping experience.
      The platform combines a clean storefront with interactive model previews and detail-driven product pages.
    </p>

    <div class="row g-4">
      <div class="col-12 col-lg-4">
        <div class="policy-card h-100">
          <h2 class="h5 mb-3">Our Mission</h2>
          <p class="text-secondary mb-0">Make product exploration more intuitive through real-time 3D interaction and clear shopping flows.</p>
        </div>
      </div>

      <div class="col-12 col-lg-4">
        <div class="policy-card h-100">
          <h2 class="h5 mb-3">What We Focus On</h2>
          <ol class="policy-list">
            <li>Fast product browsing with responsive design.</li>
            <li>Interactive model preview for better decision-making.</li>
            <li>Simple cart and checkout-ready foundation.</li>
          </ol>
        </div>
      </div>

      <div class="col-12 col-lg-4">
        <div class="policy-card h-100">
          <h2 class="h5 mb-3">Technology Stack</h2>
          <ol class="policy-list">
            <li>PHP + MySQL backend.</li>
            <li>Bootstrap-based layout system.</li>
            <li>Model Viewer for 3D interactions.</li>
            <li>LocalStorage cart utility for quick UX.</li>
          </ol>
        </div>
      </div>
    </div>

    <div class="policy-card mt-4">
      <h2 class="h5 mb-3">Need More Information?</h2>
      <p class="text-secondary mb-2">Visit our legal pages for terms, privacy, shipping, and refund information.</p>
      <div class="d-flex flex-wrap gap-2">
        <a href="index.php?p=terms" class="btn btn-outline-dark btn-sm">Terms</a>
        <a href="index.php?p=privacy" class="btn btn-outline-dark btn-sm">Privacy</a>
        <a href="index.php?p=shipping" class="btn btn-outline-dark btn-sm">Shipping</a>
        <a href="index.php?p=refund" class="btn btn-outline-dark btn-sm">Refund</a>
      </div>
    </div>
  </section>
</main>

<?php require '../components/footer.php'; ?>
