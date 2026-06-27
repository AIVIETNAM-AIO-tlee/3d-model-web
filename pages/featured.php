<?php
require_once '../config/database.php';

$pdo = getDBConnection();
$featuredProducts = array_slice(getProducts($pdo, 'All', 'default'), 0, 8);

require '../components/header.php';
require '../components/navBar.php';
?>

<main class="catalog-page">
  <section class="container py-4 py-lg-5">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-2 mb-4">
      <div>
        <h1 class="catalog-title mb-1">Featured Products</h1>
      </div>
      <a class="btn btn-outline-dark" href="index.php?p=products">Browse Full Catalog</a>
    </div>

    <?php if (empty($featuredProducts)): ?>
      <div class="alert alert-info">No featured products available right now.</div>
    <?php else: ?>
      <div class="catalog-grid">
        <?php foreach ($featuredProducts as $product): ?>
          <article class="catalog-card h-100">
            <div class="catalog-model-wrap">
              <model-viewer
                class="preview-static"
                src="<?php echo htmlspecialchars($product['modelPath'] ?? 'assets/models/model-placeholder.glb', ENT_QUOTES, 'UTF-8'); ?>"
                poster="<?php echo htmlspecialchars($product['thumbPath'] ?? 'images/model-placeholder.svg', ENT_QUOTES, 'UTF-8'); ?>"
                alt="<?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?> 3D model"
                interaction-prompt="none"
                shadow-intensity="1"
                style="width: 100%; height: 100%;"
              ></model-viewer>
            </div>
            <div class="catalog-card-body">
              <div class="catalog-tag-slot">
                <?php if (!empty($product['isPremium'])): ?>
                  <span class="catalog-tag catalog-tag-premium">Premium</span>
                <?php else: ?>
                  <span class="catalog-tag-placeholder" aria-hidden="true"></span>
                <?php endif; ?>
              </div>
              <h3 class="catalog-product-name mb-2"><?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?></h3>
              <div class="catalog-card-footer d-flex justify-content-between align-items-center gap-2">
                <?php if (!empty($product['isPremium'])): ?>
                  <span class="catalog-price"><?php echo formatCurrencyVND($product['price'] ?? 0); ?></span>
                <?php else: ?>
                  <span class="catalog-price catalog-price-free">Free</span>
                <?php endif; ?>
                <a class="btn btn-outline-dark btn-sm" href="index.php?p=product_detail&id=<?php echo (int) ($product['id'] ?? 0); ?>">View Details</a>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</main>

<?php require '../components/footer.php'; ?>
