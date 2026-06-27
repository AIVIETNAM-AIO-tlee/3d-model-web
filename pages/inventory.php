<?php
require_once '../config/auth.php';

authRequireLogin();

$pdo = authGetPDO();
$currentUser = authCurrentUser();
$inventoryItems = getUserInventoryProducts($pdo, (int) ($currentUser['id'] ?? 0));

function formatInventoryDate($timestamp) {
    $value = strtotime((string) $timestamp);
    if ($value === false) {
        return (string) $timestamp;
    }
    return date('M d, Y', $value);
}

require '../components/header.php';
require '../components/navBar.php';
  ?>

<main class="catalog-page">
  <section class="container py-4 py-lg-5">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-2 mb-4">
      <div>
        <h1 class="catalog-title mb-1">My Inventory</h1>
      </div>
      <a class="btn btn-outline-dark" href="index.php?p=products">Explore Products</a>
    </div>

    <?php if (empty($inventoryItems)): ?>
      <div class="alert alert-info">You do not own any premium products yet.</div>
    <?php else: ?>
      <div class="catalog-grid">
        <?php foreach ($inventoryItems as $item): ?>
          <article class="catalog-card h-100">
            <div class="catalog-model-wrap">
              <model-viewer
                class="preview-static"
                src="<?php echo htmlspecialchars((string) ($item['modelPath'] ?? 'assets/models/model-placeholder.glb'), ENT_QUOTES, 'UTF-8'); ?>"
                poster="<?php echo htmlspecialchars((string) ($item['thumbPath'] ?? 'images/model-placeholder.svg'), ENT_QUOTES, 'UTF-8'); ?>"
                alt="<?php echo htmlspecialchars((string) ($item['name'] ?? 'Product'), ENT_QUOTES, 'UTF-8'); ?> 3D model"
                interaction-prompt="none"
                shadow-intensity="1"
                style="width: 100%; height: 100%;"
              ></model-viewer>
            </div>
            <div class="catalog-card-body">
              <div class="catalog-tag-slot">
                <span class="catalog-tag catalog-tag-premium">Owned</span>
              </div>
              <h3 class="catalog-product-name mb-1"><?php echo htmlspecialchars((string) ($item['name'] ?? 'Unknown Product'), ENT_QUOTES, 'UTF-8'); ?></h3>
              <p class="small text-secondary mb-2">Added: <?php echo htmlspecialchars(formatInventoryDate((string) ($item['acquired_at'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></p>
              <div class="catalog-card-footer d-flex justify-content-between align-items-center gap-2">
                <span class="catalog-price catalog-price-free">Owned</span>
                <a class="btn btn-outline-dark btn-sm" href="<?php echo htmlspecialchars((string) ($item['modelPath'] ?? '#'), ENT_QUOTES, 'UTF-8'); ?>" download>Download</a>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</main>

<?php require '../components/footer.php'; ?>
