<?php
require_once __DIR__ . '/../config/database.php';

$pdo = null;
$categories = [];
$filteredProducts = [];
$dbError = null;

try {
    $pdo = getDBConnection();
    $selectedCategory = $_GET['category'] ?? 'All';
    $selectedSort = $_GET['sort'] ?? 'default';
  $selectedSearch = trim((string) ($_GET['q'] ?? ''));
    
    $categories = getCategories($pdo);
  $filteredProducts = getProducts($pdo, $selectedCategory, $selectedSort, $selectedSearch);
} catch (Exception $e) {
    $dbError = 'Database Error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    error_log('Catalog Error: ' . $e->getMessage());
}
?>

<main class="catalog-page">
  <section class="catalog-shell container py-4 py-lg-5">
    <?php if ($dbError): ?>
      <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <strong>Unable to load products.</strong> <?php echo $dbError; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>
    <div class="catalog-header d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3 mb-4">
      <div>
        <h1 class="catalog-title mb-1">Explore 3D Products</h1>
      </div>
      <form method="get" action="index.php" class="catalog-search-form w-100 w-lg-auto">
        <input type="hidden" name="p" value="products">
        <input type="hidden" name="category" value="<?php echo htmlspecialchars((string) $selectedCategory, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="sort" value="<?php echo htmlspecialchars((string) $selectedSort, ENT_QUOTES, 'UTF-8'); ?>">
        <div class="input-group">
          <input
            type="search"
            class="form-control"
            name="q"
            value="<?php echo htmlspecialchars((string) $selectedSearch, ENT_QUOTES, 'UTF-8'); ?>"
            placeholder="Search products..."
            aria-label="Search products"
          >
          <button class="btn btn-dark" type="submit">Search</button>
        </div>
      </form>
      <button class="btn btn-dark d-lg-none filter-toggle" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileFilterSheet" aria-controls="mobileFilterSheet">
        Filter & Sort
      </button>
    </div>

    <div class="row g-4">
      <aside class="col-12 col-lg-3 d-none d-lg-block">
        <div class="catalog-sidebar">
          <h2 class="sidebar-title">Filters</h2>
          <form method="get" action="index.php" class="d-grid gap-3">
            <input type="hidden" name="p" value="products">
            <input type="hidden" name="q" value="<?php echo htmlspecialchars((string) $selectedSearch, ENT_QUOTES, 'UTF-8'); ?>">
            <div>
              <label class="form-label" for="category-desktop">Category</label>
              <select id="category-desktop" class="form-select" name="category">
                <?php foreach ($categories as $category): ?>
                  <option value="<?php echo $category; ?>" <?php echo $selectedCategory === $category ? 'selected' : ''; ?>>
                    <?php echo $category; ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label class="form-label" for="sort-desktop">Sort By Price</label>
              <select id="sort-desktop" class="form-select" name="sort">
                <option value="default" <?php echo $selectedSort === 'default' ? 'selected' : ''; ?>>Default</option>
                <option value="price_asc" <?php echo $selectedSort === 'price_asc' ? 'selected' : ''; ?>>Low to High</option>
                <option value="price_desc" <?php echo $selectedSort === 'price_desc' ? 'selected' : ''; ?>>High to Low</option>
              </select>
            </div>
            <button class="btn btn-dark" type="submit">Apply</button>
          </form>
        </div>
      </aside>

      <div class="col-12 col-lg-9">
        <div class="catalog-grid" id="product-grid">
          <p class="text-muted" id="products-loading">Loading products...</p>
        </div>
        <nav class="mt-4" aria-label="Catalog pagination" id="catalog-pagination"></nav>
      </div>
    </div>
  </section>

  <div class="offcanvas offcanvas-bottom mobile-filter-sheet" tabindex="-1" id="mobileFilterSheet" aria-labelledby="mobileFilterSheetLabel">
    <div class="offcanvas-header">
      <h2 class="offcanvas-title h5 mb-0" id="mobileFilterSheetLabel">Filter & Sort</h2>
      <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
      <form method="get" action="index.php" class="d-grid gap-3">
        <input type="hidden" name="p" value="products">
        <input type="hidden" name="q" value="<?php echo htmlspecialchars((string) $selectedSearch, ENT_QUOTES, 'UTF-8'); ?>">
        <div>
          <label class="form-label" for="category-mobile">Category</label>
          <select id="category-mobile" class="form-select" name="category">
            <?php foreach ($categories as $category): ?>
              <option value="<?php echo $category; ?>" <?php echo $selectedCategory === $category ? 'selected' : ''; ?>>
                <?php echo $category; ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="form-label" for="sort-mobile">Sort By Price</label>
          <select id="sort-mobile" class="form-select" name="sort">
            <option value="default" <?php echo $selectedSort === 'default' ? 'selected' : ''; ?>>Default</option>
            <option value="price_asc" <?php echo $selectedSort === 'price_asc' ? 'selected' : ''; ?>>Low to High</option>
            <option value="price_desc" <?php echo $selectedSort === 'price_desc' ? 'selected' : ''; ?>>High to Low</option>
          </select>
        </div>
        <button class="btn btn-dark" type="submit" data-bs-dismiss="offcanvas">Apply Filters</button>
      </form>
    </div>
  </div>
</main>

<script>
  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function updateQueryStringPage(page) {
    const query = new URLSearchParams(window.location.search);
    query.set('p', 'products');
    query.set('page', String(page));
    window.history.replaceState({}, '', 'index.php?' + query.toString());
  }

  function renderPagination(pagination) {
    const nav = document.getElementById('catalog-pagination');
    if (!nav) {
      return;
    }

    const totalPages = Number(pagination.total_pages || 1);
    const currentPage = Number(pagination.page || 1);

    if (totalPages <= 1) {
      nav.innerHTML = '';
      return;
    }

    const prevDisabled = currentPage <= 1 ? ' disabled' : '';
    const nextDisabled = currentPage >= totalPages ? ' disabled' : '';
    const startPage = Math.max(1, currentPage - 2);
    const endPage = Math.min(totalPages, currentPage + 2);

    let pageItems = '';
    for (let page = startPage; page <= endPage; page += 1) {
      const activeClass = page === currentPage ? ' active' : '';
      pageItems += `<li class="page-item${activeClass}"><button class="page-link" type="button" data-page="${page}">${page}</button></li>`;
    }

    nav.innerHTML = `
      <ul class="pagination justify-content-center">
        <li class="page-item${prevDisabled}">
          <button class="page-link" type="button" data-page="${currentPage - 1}" ${currentPage <= 1 ? 'disabled' : ''} aria-label="Previous page" title="Previous page">
            <svg class="pagination-arrow-icon" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false">
              <path d="M15 6L9 12L15 18" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"></path>
            </svg>
          </button>
        </li>
        ${pageItems}
        <li class="page-item${nextDisabled}">
          <button class="page-link" type="button" data-page="${currentPage + 1}" ${currentPage >= totalPages ? 'disabled' : ''} aria-label="Next page" title="Next page">
            <svg class="pagination-arrow-icon" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false">
              <path d="M9 6L15 12L9 18" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"></path>
            </svg>
          </button>
        </li>
      </ul>
    `;

    nav.querySelectorAll('button[data-page]').forEach((button) => {
      button.addEventListener('click', () => {
        const targetPage = Number(button.getAttribute('data-page') || currentPage);
        if (Number.isNaN(targetPage) || targetPage < 1 || targetPage > totalPages || targetPage === currentPage) {
          return;
        }

        updateQueryStringPage(targetPage);
        fetchAndRenderProducts();
      });
    });
  }

  async function fetchAndRenderProducts() {
    const grid = document.getElementById('product-grid');
    if (!grid) {
      return;
    }

    const query = new URLSearchParams(window.location.search);
    const category = query.get('category') || 'All';
    const sort = query.get('sort') || 'default';
    const search = query.get('q') || '';
    const page = Math.max(1, Number(query.get('page') || '1'));
    const apiUrl = `index.php?p=api_products&category=${encodeURIComponent(category)}&sort=${encodeURIComponent(sort)}&q=${encodeURIComponent(search)}&page=${encodeURIComponent(String(page))}&per_page=12`;

    try {
      const response = await fetch(apiUrl);
      if (!response.ok) {
        throw new Error('HTTP ' + response.status);
      }

      const payload = await response.json();
      if (!payload || !Array.isArray(payload.items) || typeof payload.pagination !== 'object') {
        throw new Error('Invalid API response format');
      }

      const products = payload.items;
      const pagination = payload.pagination;

      if (products.length === 0) {
        grid.innerHTML = '<p class="text-muted">No products found for the selected filters.</p>';
        renderPagination({ page: 1, total_pages: 1 });
        return;
      }

      const cardsHtml = products.map((product) => {
        const safeName = escapeHtml(product.name || 'Unknown Product');
        const safeCategory = escapeHtml(product.category || 'Uncategorized');
        const safeId = Number(product.id || 0);
        const safeSlug = encodeURIComponent(product.slug || '');
        const detailHref = safeId > 0
          ? `index.php?p=product_detail&id=${safeId}`
          : `index.php?p=product_detail&product=${safeSlug}`;
        const safeModelPath = escapeHtml(product.model_3d_url || 'assets/models/model-placeholder.glb');
        const safeImagePath = escapeHtml(product.image_url || 'images/model-placeholder.svg');
        const price = Number(product.price || 0);
        const isPremium = Boolean(product.is_premium);
        const priceLabel = isPremium ? new Intl.NumberFormat('vi-VN').format(price) + ' ₫' : 'Free';
        const premiumTagHtml = isPremium
          ? '<span class="catalog-tag catalog-tag-premium">Premium</span>'
          : '<span class="catalog-tag-placeholder" aria-hidden="true"></span>';

        return `
          <article class="catalog-card h-100">
            <div class="catalog-model-wrap">
              <model-viewer
                class="preview-static"
                src="${safeModelPath}"
                poster="${safeImagePath}"
                alt="${safeName} 3D model"
                interaction-prompt="none"
                shadow-intensity="1"
                style="width: 100%; height: 100%;"
              ></model-viewer>
            </div>
            <div class="catalog-card-body">
              <div class="catalog-tag-slot">${premiumTagHtml}</div>
              <h3 class="catalog-product-name mb-2">${safeName}</h3>
              <div class="catalog-card-footer d-flex justify-content-between align-items-center gap-2">
                <span class="catalog-price ${isPremium ? '' : 'catalog-price-free'}">${priceLabel}</span>
                <a class="btn btn-outline-dark btn-sm" href="${detailHref}">View Details</a>
              </div>
            </div>
          </article>
        `;
      }).join('');

      grid.innerHTML = cardsHtml;
      renderPagination(pagination);
    } catch (error) {
      console.error('Failed to fetch products:', error);
      grid.innerHTML = '<p class="text-danger">Failed to load products. Please try again later.</p>';
      const nav = document.getElementById('catalog-pagination');
      if (nav) {
        nav.innerHTML = '';
      }
    }
  }

  fetchAndRenderProducts();
</script>

