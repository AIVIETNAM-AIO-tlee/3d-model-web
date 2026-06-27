<?php
$adminActivePage = 'products';
require_once '../config/auth.php';

authRequireAdmin();

$pdo = authGetPDO();
ensureProductsPremiumColumn($pdo);
$message = null;
$success = null;
$error = null;
$productsPerPage = 10;
$currentPage = max(1, (int) ($_GET['page'] ?? 1));

if (!empty($_GET['status'])) {
    $status = (string) $_GET['status'];
    if ($status === 'created') {
        $success = 'Product created successfully.';
    } elseif ($status === 'updated') {
        $success = 'Product updated successfully.';
    } elseif ($status === 'deleted') {
        $success = 'Product deleted successfully.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedPage = max(1, (int) ($_POST['page'] ?? $currentPage));
    $requestContentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    $isRejectedBeforePhpParsing = $requestContentLength > 0 && empty($_POST) && empty($_FILES);

    if ($isRejectedBeforePhpParsing) {
        $error = 'Upload request was rejected by server before processing. Please restart your web server to apply upload settings, then try again.';
    } elseif (!authVerifyCSRF($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid request token. Please refresh and try again.';
    } else {
        $action = (string) ($_POST['action'] ?? 'save');

        try {
            if ($action === 'delete') {
                $productId = (int) ($_POST['product_id'] ?? 0);
                if ($productId > 0) {
                    $modelUrl = null;
                    $modelStmt = $pdo->prepare('SELECT model_3d_url FROM Products WHERE id = ? LIMIT 1');
                    $modelStmt->execute([$productId]);
                    $modelRow = $modelStmt->fetch(PDO::FETCH_ASSOC);
                    if ($modelRow) {
                        $modelUrl = (string) ($modelRow['model_3d_url'] ?? '');
                    }

                    $stmt = $pdo->prepare('DELETE FROM Products WHERE id = ?');
                    $stmt->execute([$productId]);

                    if ($modelUrl) {
                        $normalizedModelUrl = str_replace('\\', '/', $modelUrl);
                        if (strpos($normalizedModelUrl, 'assets/models/') === 0) {
                            $relativeModelPath = substr($normalizedModelUrl, strlen('assets/models/'));
                            if ($relativeModelPath !== '' && strpos($relativeModelPath, '..') === false) {
                                $modelsBaseDir = realpath(__DIR__ . '/../public/assets/models');
                                if ($modelsBaseDir !== false) {
                                    $targetFile = $modelsBaseDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeModelPath);
                                    $resolvedTarget = realpath($targetFile);
                                    if ($resolvedTarget !== false && strpos($resolvedTarget, $modelsBaseDir . DIRECTORY_SEPARATOR) === 0 && is_file($resolvedTarget)) {
                                        @unlink($resolvedTarget);
                                    }
                                }
                            }
                        }
                    }
                }
                authRedirect('index.php?p=admin_products&page=' . $postedPage . '&status=deleted');
            }

            $productId = (int) ($_POST['product_id'] ?? 0);
            $name = trim((string) ($_POST['name'] ?? ''));
            $description = trim((string) ($_POST['description'] ?? ''));
            $price = (float) ($_POST['price'] ?? 0);
            $tags = trim((string) ($_POST['tags'] ?? ''));
            $categoryId = (int) ($_POST['category_id'] ?? 0);
            $isPremium = !empty($_POST['is_premium']);

            if ($name === '') {
                throw new RuntimeException('Product name is required.');
            }

            if (!$isPremium || $price <= 0) {
              $isPremium = false;
              $price = 0;
            }

            $categoryValue = $categoryId > 0 ? $categoryId : null;
            $existingModelUrl = null;
            $modelFilePath = null;

            if ($productId > 0) {
                $existingStmt = $pdo->prepare('SELECT model_3d_url FROM Products WHERE id = ? LIMIT 1');
                $existingStmt->execute([$productId]);
                $existingProduct = $existingStmt->fetch(PDO::FETCH_ASSOC);
                $existingModelUrl = (string) ($existingProduct['model_3d_url'] ?? '');
            }

            if (isset($_FILES['model_file']) && is_array($_FILES['model_file'])) {
                $uploadError = (int) ($_FILES['model_file']['error'] ?? UPLOAD_ERR_NO_FILE);
                if ($uploadError !== UPLOAD_ERR_NO_FILE) {
                    if ($uploadError !== UPLOAD_ERR_OK) {
                        if ($uploadError === UPLOAD_ERR_INI_SIZE || $uploadError === UPLOAD_ERR_FORM_SIZE) {
                            throw new RuntimeException('Model file is larger than server upload limit. Increase PHP upload settings and restart the web server.');
                        }
                        throw new RuntimeException('Model upload failed. Please try again.');
                    }

                    $originalName = (string) ($_FILES['model_file']['name'] ?? '');
                    $tmpPath = (string) ($_FILES['model_file']['tmp_name'] ?? '');
                    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                    $allowedExtensions = ['glb', 'gltf'];

                    if (!in_array($extension, $allowedExtensions, true)) {
                        throw new RuntimeException('Only .glb or .gltf files are supported.');
                    }

                    if (!is_uploaded_file($tmpPath)) {
                        throw new RuntimeException('Invalid uploaded file.');
                    }

                    $modelsDir = realpath(__DIR__ . '/../public/assets');
                    if ($modelsDir === false) {
                        throw new RuntimeException('Assets directory is not available.');
                    }

                    $targetDir = $modelsDir . DIRECTORY_SEPARATOR . 'models';
                    if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true)) {
                        throw new RuntimeException('Cannot create model storage directory.');
                    }

                    $safeBase = preg_replace('/[^a-zA-Z0-9_-]/', '-', pathinfo($originalName, PATHINFO_FILENAME));
                    $safeBase = trim((string) $safeBase, '-');
                    if ($safeBase === '') {
                        $safeBase = 'model';
                    }

                    $fileName = $safeBase . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $extension;
                    $targetPath = $targetDir . DIRECTORY_SEPARATOR . $fileName;

                    if (!move_uploaded_file($tmpPath, $targetPath)) {
                        if (!copy($tmpPath, $targetPath)) {
                            throw new RuntimeException('Failed to save uploaded model file. Please check folder write permission for public/assets/models.');
                        }
                        @unlink($tmpPath);
                    }

                    $modelFilePath = 'assets/models/' . $fileName;
                }
            }

            if ($modelFilePath === null) {
                $modelFilePath = $existingModelUrl !== '' ? $existingModelUrl : null;
            }

            if ($modelFilePath === null) {
                throw new RuntimeException('Please upload a 3D model file (.glb or .gltf).');
            }

            if ($productId > 0) {
              $stmt = $pdo->prepare('UPDATE Products SET category_id = ?, name = ?, description = ?, price = ?, is_premium = ?, image_url = NULL, model_3d_url = ?, tags = ? WHERE id = ?');
              $stmt->execute([$categoryValue, $name, $description, $price, $isPremium ? 1 : 0, $modelFilePath, $tags !== '' ? $tags : null, $productId]);
                authRedirect('index.php?p=admin_products&page=' . $postedPage . '&status=updated');
            } else {
              $stmt = $pdo->prepare('INSERT INTO Products (category_id, name, description, price, is_premium, image_url, model_3d_url, tags) VALUES (?, ?, ?, ?, ?, NULL, ?, ?)');
              $stmt->execute([$categoryValue, $name, $description, $price, $isPremium ? 1 : 0, $modelFilePath, $tags !== '' ? $tags : null]);
                authRedirect('index.php?p=admin_products&page=' . $postedPage . '&status=created');
            }
        } catch (Throwable $e) {
            error_log('Admin product save error: ' . $e->getMessage());
            $error = $e->getMessage();
        }
    }
}

$categories = [];
try {
  $stmt = $pdo->query("SELECT id, name FROM Categories WHERE LOWER(name) <> 'premium' ORDER BY name ASC");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $categories = [];
}

$editProduct = null;
if (!empty($_GET['edit'])) {
    try {
  $stmt = $pdo->prepare('SELECT id, category_id, name, description, price, is_premium, image_url, model_3d_url, tags, created_at FROM Products WHERE id = ? LIMIT 1');
        $stmt->execute([(int) $_GET['edit']]);
        $editProduct = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) {
        $editProduct = null;
    }
}

try {
    $countStmt = $pdo->query('SELECT COUNT(*) FROM Products');
    $totalProducts = (int) ($countStmt->fetchColumn() ?: 0);
} catch (Throwable $e) {
    $totalProducts = 0;
}

$totalPages = max(1, (int) ceil($totalProducts / $productsPerPage));
if ($currentPage > $totalPages) {
    $currentPage = $totalPages;
}
$offset = ($currentPage - 1) * $productsPerPage;

try {
    $stmt = $pdo->prepare('SELECT p.id, p.category_id, p.name, p.description, p.price, p.is_premium, p.image_url, p.model_3d_url, p.tags, p.created_at, c.name AS category_name FROM Products p LEFT JOIN Categories c ON c.id = p.category_id ORDER BY p.created_at DESC LIMIT :limit OFFSET :offset');
    $stmt->bindValue(':limit', $productsPerPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $products = [];
}

require '../components/header.php';
?>

<main class="admin-shell">
  <div class="admin-layout">
    <?php require '../components/admin_sidebar.php'; ?>

    <section class="admin-main">
      <div class="admin-topbar align-items-end">
        <div>
          <h1 class="admin-title">Products</h1>
        </div>
        <a href="index.php?p=admin_products&page=<?php echo (int) $currentPage; ?>" class="btn btn-outline-primary">Add New Product</a>
      </div>

      <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
      <?php endif; ?>

      <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
      <?php endif; ?>

      <div class="admin-grid-2">
        <div class="admin-panel">
          <div class="admin-panel-header">
            <div>
              <h2 class="admin-panel-title"><?php echo $editProduct ? 'Edit Product' : 'Create Product'; ?></h2>
            </div>
          </div>

          <form method="post" action="index.php?p=admin_products<?php echo $editProduct ? '&amp;edit=' . (int) $editProduct['id'] : ''; ?>&amp;page=<?php echo (int) $currentPage; ?>" class="d-grid gap-3" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(authCSRFToken(), ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="product_id" value="<?php echo (int) ($editProduct['id'] ?? 0); ?>">
            <input type="hidden" name="page" value="<?php echo (int) $currentPage; ?>">

            <div>
              <label class="form-label">Product Name</label>
              <input type="text" name="name" class="form-control" required value="<?php echo htmlspecialchars((string) ($_POST['name'] ?? ($editProduct['name'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>">
            </div>

            <div>
              <label class="form-label">Category</label>
              <select name="category_id" class="form-select">
                <option value="0">Uncategorized</option>
                <?php foreach ($categories as $category): ?>
                  <option value="<?php echo (int) $category['id']; ?>" <?php echo ((int) ($_POST['category_id'] ?? ($editProduct['category_id'] ?? 0)) === (int) $category['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8'); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div>
              <label class="form-label">Description</label>
              <textarea name="description" class="form-control" rows="4"><?php echo htmlspecialchars((string) ($_POST['description'] ?? ($editProduct['description'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>

            <div class="row g-3">
              <div class="col-12">
                <label class="form-label">Price (VND)</label>
                <input type="number" step="1000" min="0" name="price" class="form-control" required value="<?php echo htmlspecialchars((string) ($_POST['price'] ?? ($editProduct['price'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>">
              </div>
            </div>

            <div class="form-check">
              <?php $premiumChecked = !empty($_POST) ? !empty($_POST['is_premium']) : !empty($editProduct['is_premium']); ?>
              <input type="checkbox" class="form-check-input" id="is_premium" name="is_premium" value="1" <?php echo $premiumChecked ? 'checked' : ''; ?>>
              <label class="form-check-label" for="is_premium">Premium product (paid)</label>
            </div>

            <div>
              <label class="form-label">3D Model File (.glb / .gltf)</label>
              <input type="file" name="model_file" class="form-control" accept=".glb,.gltf" <?php echo $editProduct ? '' : 'required'; ?>>
              <?php if ($editProduct && !empty($editProduct['model_3d_url'])): ?>
                <div class="form-text">Current model: <?php echo htmlspecialchars((string) $editProduct['model_3d_url'], ENT_QUOTES, 'UTF-8'); ?>. Upload a new file only if you want to replace it.</div>
              <?php endif; ?>
            </div>

            <div>
              <label class="form-label">Tags</label>
              <input type="text" name="tags" class="form-control" value="<?php echo htmlspecialchars((string) ($_POST['tags'] ?? ($editProduct['tags'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>">
            </div>

            <div class="d-flex gap-2 flex-wrap">
              <button type="submit" class="btn btn-primary"><?php echo $editProduct ? 'Update Product' : 'Save Product'; ?></button>
              <?php if ($editProduct): ?>
                <a href="index.php?p=admin_products&amp;page=<?php echo (int) $currentPage; ?>" class="btn btn-outline-secondary">Cancel</a>
              <?php endif; ?>
            </div>
          </form>
        </div>

        <div class="admin-table-card">
          <div class="p-3 p-lg-4 border-bottom">
            <h2 class="admin-panel-title mb-1">Product Inventory</h2>
          </div>
          <div class="table-responsive">
            <table class="table align-middle admin-products-table mb-0">
              <thead class="table-light">
                <tr>
                  <th>ID</th>
                  <th>Name</th>
                  <th>Category</th>
                  <th>Price</th>
                  <th>Model</th>
                  <th class="text-end">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!empty($products)): ?>
                  <?php foreach ($products as $product): ?>
                    <tr>
                      <td>#<?php echo (int) $product['id']; ?></td>
                      <td>
                        <div class="fw-semibold admin-product-name"><?php echo htmlspecialchars((string) ($product['name'] ?? 'Unnamed Product'), ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="small text-secondary admin-product-description"><?php echo htmlspecialchars((string) ($product['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                      </td>
                      <td><?php echo htmlspecialchars((string) ($product['category_name'] ?? 'Uncategorized'), ENT_QUOTES, 'UTF-8'); ?></td>
                      <td><?php echo formatCurrencyVND($product['price']); ?></td>
                      <td><span class="badge text-bg-light border text-secondary">3D Asset</span></td>
                      <td class="text-end">
                        <div class="d-inline-flex gap-2 flex-wrap justify-content-end">
                          <a
                            href="index.php?p=admin_products&amp;edit=<?php echo (int) $product['id']; ?>&amp;page=<?php echo (int) $currentPage; ?>"
                            class="btn btn-sm btn-outline-primary admin-icon-btn"
                            title="Edit"
                            aria-label="Edit product #<?php echo (int) $product['id']; ?>"
                          >
                            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                              <path d="M3 17.25V21h3.75L19.81 7.94l-3.75-3.75L3 17.25Zm2.92 2.33H5v-.92l11.06-11.06.92.92L5.92 19.58ZM20.71 6.04a1 1 0 0 0 0-1.42L19.37 3.3a1 1 0 0 0-1.42 0l-1.13 1.13 3.75 3.75 1.14-1.14Z"/>
                            </svg>
                          </a>
                          <form method="post" action="index.php?p=admin_products&amp;page=<?php echo (int) $currentPage; ?>" onsubmit="return confirm('Delete this product?');" class="m-0">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(authCSRFToken(), ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="product_id" value="<?php echo (int) $product['id']; ?>">
                            <input type="hidden" name="page" value="<?php echo (int) $currentPage; ?>">
                            <button
                              type="submit"
                              class="btn btn-sm btn-outline-danger admin-icon-btn"
                              title="Delete"
                              aria-label="Delete product #<?php echo (int) $product['id']; ?>"
                            >
                              <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <path d="M9 3h6l1 2h4v2H4V5h4l1-2Zm1 6h2v9h-2V9Zm4 0h2v9h-2V9ZM7 9h2v9H7V9Zm-1 12h12a1 1 0 0 0 1-1V8H5v12a1 1 0 0 0 1 1Z"/>
                              </svg>
                            </button>
                          </form>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="6" class="text-center text-secondary py-5">No products found. Add the first product to start your catalog.</td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
          <?php if ($totalPages > 1): ?>
            <div class="p-3 p-lg-4 border-top">
              <nav aria-label="Product inventory pagination" id="admin-products-pagination">
                <ul class="pagination pagination-sm mb-0 justify-content-center">
                  <li class="page-item <?php echo $currentPage <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link" href="index.php?p=admin_products&amp;page=<?php echo max(1, $currentPage - 1); ?>" aria-label="Previous page" title="Previous page">
                      <svg class="pagination-arrow-icon" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false">
                        <path d="M15 6L9 12L15 18" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"></path>
                      </svg>
                    </a>
                  </li>
                  <?php for ($page = 1; $page <= $totalPages; $page++): ?>
                    <li class="page-item <?php echo $page === $currentPage ? 'active' : ''; ?>">
                      <a class="page-link" href="index.php?p=admin_products&amp;page=<?php echo $page; ?>"><?php echo $page; ?></a>
                    </li>
                  <?php endfor; ?>
                  <li class="page-item <?php echo $currentPage >= $totalPages ? 'disabled' : ''; ?>">
                    <a class="page-link" href="index.php?p=admin_products&amp;page=<?php echo min($totalPages, $currentPage + 1); ?>" aria-label="Next page" title="Next page">
                      <svg class="pagination-arrow-icon" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false">
                        <path d="M9 6L15 12L9 18" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"></path>
                      </svg>
                    </a>
                  </li>
                </ul>
              </nav>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </section>
  </div>
</main>

</body>
</html>
