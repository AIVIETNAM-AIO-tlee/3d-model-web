<?php
require_once __DIR__ . '/../config/database.php';

$pdo = getDBConnection();
$featuredProducts = array_slice(getProducts($pdo), 0, 4);

$bundleProducts = [
  [
    'title' => 'Starter Asset Bundle',
    'description' => 'A compact mix of essential models for rapid prototyping, product mockups, and quick scene setup.',
    'price' => 250000,
    'originalPrice' => 350000,
    'items' => ['2 product models', '1 showcase scene', '1 prop pack'],
    'badge' => 'Save 29%',
    'accentClass' => 'bundle-accent-blue',
  ],
  [
    'title' => 'Game Ready Bundle',
    'description' => 'Optimized assets for games and interactive demos with a balanced selection of characters and props.',
    'price' => 400000,
    'originalPrice' => 600000,
    'items' => ['Characters', 'Props', 'Environment pieces'],
    'badge' => 'Save 33%',
    'accentClass' => 'bundle-accent-sand',
  ],
  [
    'title' => 'Commercial Showcase Bundle',
    'description' => 'Polished assets for landing pages, pitches, and visual presentation work that needs a premium look.',
    'price' => 500000,
    'originalPrice' => 750000,
    'items' => ['Presentation assets', 'High-detail model', 'Reusable scenes'],
    'badge' => 'Save 34%',
    'accentClass' => 'bundle-accent-forest',
  ],
];

$heroModelSrc = '';
$heroPosterSrc = '';

foreach ($featuredProducts as $featuredProduct) {
  $candidateModel = $featuredProduct['modelPath'] ?? '';
  if (!empty($candidateModel) && strpos($candidateModel, 'model-placeholder.glb') === false) {
    $heroModelSrc = $candidateModel;

    $candidatePoster = $featuredProduct['thumbPath'] ?? '';
    if (!empty($candidatePoster) && strpos($candidatePoster, 'placeholder') === false) {
      $heroPosterSrc = $candidatePoster;
    }
    break;
  }
}
?>

<main class="homepage">
  <section class="hero-section">
    <div class="container py-4 py-lg-5">
      <div class="row align-items-center g-4 g-lg-5">
        <div class="col-12 col-lg-5 order-2 order-lg-1">
          <h1 class="hero-title">Game-Ready 3D Assets. Try Before You Buy. No Hidden Fees.</h1>
          <p class="hero-copy">
            Discover ready-to-use 3D models for games, apps, and digital products. Preview every asset in
            detail before purchase, compare options quickly, and move from exploration to checkout with confidence.
          </p>

          <div class="d-flex flex-column flex-sm-row gap-3 mt-4">
            <a class="btn btn-primary cta-button" href="index.php?p=featured">Explore featured products</a>
            <a class="btn btn-outline-dark secondary-button" href="index.php?p=about">Learn more</a>
          </div>

          <ul class="hero-stats list-unstyled d-flex flex-wrap gap-3 gap-md-4 mt-4 mb-0">
            <li>
              <strong>360°</strong>
              <span>Rotation</span>
            </li>
            <li>
              <strong>Zoom</strong>
              <span>Touch + mouse</span>
            </li>
            <li>
              <strong>Trusted</strong>
              <span>Commercial use ready</span>
            </li>
          </ul>
        </div>

        <div class="col-12 col-lg-7 order-1 order-lg-2">
          <div class="model-card">
            <div class="model-badge">Interactive 3D preview</div>
            <div class="model-stage">
              <?php if (!empty($heroModelSrc)): ?>
                <model-viewer
                  class="hero-model"
                  src="<?php echo htmlspecialchars($heroModelSrc, ENT_QUOTES, 'UTF-8'); ?>"
                  <?php if (!empty($heroPosterSrc)): ?>poster="<?php echo htmlspecialchars($heroPosterSrc, ENT_QUOTES, 'UTF-8'); ?>"<?php endif; ?>
                  alt="Interactive 3D model of the featured product"
                  tone-mapping="neutral"
                  camera-controls
                  auto-rotate
                  exposure="1"
                  shadow-intensity="1"
                  interaction-prompt="none"
                ></model-viewer>
              <?php else: ?>
                <img src="images/product-placeholder.svg" alt="Featured product preview" class="img-fluid">
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section id="featured-products" class="featured-section">
    <div class="container py-5">
      <div class="section-heading d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-2 mb-4">
        <div>
          <h2 class="section-title mb-0">Featured Products</h2>
        </div>
        <a class="text-decoration-none section-link" href="index.php?p=products">View all products</a>
      </div>

      <div class="row g-3 g-md-4">
        <?php foreach ($featuredProducts as $product): ?>
          <div class="col-12 col-sm-6 col-lg-3">
            <article class="product-card h-100">
              <div class="product-thumb">
                <?php
                  $cardModelPath = $product['modelPath'] ?? '';
                  $hasRealModel = !empty($cardModelPath) && strpos($cardModelPath, 'model-placeholder.glb') === false;
                ?>
                <?php if ($hasRealModel): ?>
                  <model-viewer
                    class="preview-static"
                    src="<?php echo htmlspecialchars($cardModelPath, ENT_QUOTES, 'UTF-8'); ?>"
                    poster="<?php echo htmlspecialchars($product['thumbPath'], ENT_QUOTES, 'UTF-8'); ?>"
                    alt="<?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?> 3D model"
                    shadow-intensity="1"
                    tone-mapping="neutral"
                    exposure="1"
                    interaction-prompt="none"
                    style="width: 100%; height: 220px;"
                  ></model-viewer>
                <?php else: ?>
                  <img src="<?php echo htmlspecialchars($product['thumbPath'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?> product image" loading="lazy">
                <?php endif; ?>
              </div>
              <div class="product-body d-flex flex-column">
                <div class="catalog-tag-slot mb-2">
                  <?php if (!empty($product['isPremium'])): ?>
                    <span class="catalog-tag catalog-tag-premium">Premium</span>
                  <?php else: ?>
                    <span class="catalog-tag-placeholder" aria-hidden="true"></span>
                  <?php endif; ?>
                </div>
                <h3 class="product-name"><?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                <p class="product-description"><?php echo htmlspecialchars($product['description'] ?? 'Explore this product in detail.', ENT_QUOTES, 'UTF-8'); ?></p>
                <div class="mt-auto d-flex justify-content-between align-items-center gap-3">
                  <?php if (!empty($product['isPremium'])): ?>
                    <span class="product-price"><?php echo formatCurrencyVND($product['price'] ?? 0); ?></span>
                  <?php else: ?>
                    <span class="product-price product-price-free">Free</span>
                  <?php endif; ?>
                  <a class="btn btn-dark btn-sm product-button" href="index.php?p=product_detail&id=<?php echo (int) ($product['id'] ?? 0); ?>">View details</a>
                </div>
              </div>
            </article>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section id="bundle-products" class="bundle-section">
    <div class="container pb-5">
      <div class="section-heading d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-2 mb-4">
        <div>
          <h2 class="section-title mb-0">Bundle products</h2>
        </div>
        <a class="text-decoration-none section-link" href="index.php?p=products">Browse all products</a>
      </div>

      <div class="row g-3 g-md-4">
        <?php foreach ($bundleProducts as $bundle): ?>
          <div class="col-12 col-lg-4">
            <article class="bundle-card h-100 <?php echo htmlspecialchars($bundle['accentClass'], ENT_QUOTES, 'UTF-8'); ?>">
              <div class="bundle-card-top d-flex justify-content-between align-items-start gap-3">
                <div>
                  <span class="bundle-badge"><?php echo htmlspecialchars($bundle['badge'], ENT_QUOTES, 'UTF-8'); ?></span>
                  <h3 class="bundle-title"><?php echo htmlspecialchars($bundle['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                </div>
                <div class="bundle-price-wrap text-end">
                  <span class="bundle-price"><?php echo formatCurrencyVND($bundle['price']); ?></span>
                  <span class="bundle-original-price"><?php echo formatCurrencyVND($bundle['originalPrice']); ?></span>
                </div>
              </div>

              <p class="bundle-description"><?php echo htmlspecialchars($bundle['description'], ENT_QUOTES, 'UTF-8'); ?></p>

              <ul class="bundle-items list-unstyled d-flex flex-wrap gap-2 mb-4">
                <?php foreach ($bundle['items'] as $item): ?>
                  <li class="bundle-item-chip"><?php echo htmlspecialchars($item, ENT_QUOTES, 'UTF-8'); ?></li>
                <?php endforeach; ?>
              </ul>

              <div class="mt-auto d-flex justify-content-between align-items-center gap-3">
                <span class="bundle-note">Best for teams and bulk buyers</span>
                <a class="btn btn-dark btn-sm product-button" href="index.php?p=products">View bundle</a>
              </div>
            </article>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
</main>