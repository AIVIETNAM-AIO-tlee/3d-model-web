<?php
require_once '../config/auth.php';

$pdo = authGetPDO();
$requestedId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$requestedSlug = $_GET['product'] ?? null;
$product = null;

if ($requestedId > 0) {
  $product = getProductById($pdo, $requestedId);
}

if ($product === null && $requestedSlug) {
    $product = getProductBySlug($pdo, $requestedSlug);
}

if ($product === null) {
  header('Location: index.php?p=products&error=product_not_found');
  exit;
}

$customerUser = authCurrentUser();
$currentUser = $customerUser ?: authCurrentAdmin();
$canComment = $currentUser !== null;
$commentError = null;
$commentSuccess = null;
$currentUrl = 'index.php?p=product_detail&id=' . (int) $product['id'];
$isPremiumProduct = !empty($product['isPremium']);
$isOwnedByCustomer = $isPremiumProduct && $customerUser ? userOwnsProduct($pdo, (int) $customerUser['id'], (int) $product['id']) : false;

function respondCommentJson(array $payload, int $statusCode = 200): void {
  http_response_code($statusCode);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($payload);
  exit;
}

if (!empty($_SESSION['comment_error'])) {
  $commentError = (string) $_SESSION['comment_error'];
  unset($_SESSION['comment_error']);
}

if (!empty($_SESSION['comment_success'])) {
  $commentSuccess = (string) $_SESSION['comment_success'];
  unset($_SESSION['comment_success']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = (string) ($_POST['comment_action'] ?? 'save_comment');
  $isAjaxRequest = (
    (isset($_POST['ajax']) && (string) $_POST['ajax'] === '1') ||
    (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
  );
  $isAjaxLikeRequest = $action === 'toggle_like' && $isAjaxRequest;
  $isAjaxSaveCommentRequest = $action === 'save_comment' && $isAjaxRequest;

  if (!authVerifyCSRF($_POST['csrf_token'] ?? null)) {
    if ($isAjaxLikeRequest) {
      respondCommentJson(['ok' => false, 'message' => 'Invalid request token. Please refresh and try again.'], 400);
    }
    if ($isAjaxSaveCommentRequest) {
      respondCommentJson(['ok' => false, 'message' => 'Invalid request token. Please refresh and try again.'], 400);
    }
    $commentError = 'Invalid request token. Please refresh and try again.';
  } elseif (!$canComment) {
    if ($isAjaxLikeRequest) {
      respondCommentJson(['ok' => false, 'message' => 'Please sign in to like comments.'], 401);
    }
    if ($isAjaxSaveCommentRequest) {
      respondCommentJson(['ok' => false, 'message' => 'Please sign in to post a comment or reply.'], 401);
    }
    $commentError = 'Please sign in to post a comment or reply.';
  } else {
    if ($action === 'save_comment') {
      $content = trim((string) ($_POST['content'] ?? ''));
      $parentCommentId = (int) ($_POST['parent_comment_id'] ?? 0);
      $parentCommentId = $parentCommentId > 0 ? $parentCommentId : null;

      $result = createProductComment($pdo, (int) $product['id'], (int) $currentUser['id'], $content, $parentCommentId);

      if (!empty($result['ok'])) {
        if ($isAjaxSaveCommentRequest) {
          respondCommentJson([
            'ok' => true,
            'message' => $parentCommentId ? 'Reply posted successfully.' : 'Comment posted successfully.',
          ]);
        }
        $_SESSION['comment_success'] = $parentCommentId ? 'Reply posted successfully.' : 'Comment posted successfully.';
        header('Location: ' . $currentUrl . '#product-comments');
        exit;
      }

      if ($isAjaxSaveCommentRequest) {
        respondCommentJson(['ok' => false, 'message' => $result['message'] ?? 'Unable to save your comment right now.'], 400);
      }
      $commentError = $result['message'] ?? 'Unable to save your comment right now.';
    } elseif ($action === 'toggle_like') {
      $commentId = (int) ($_POST['comment_id'] ?? 0);
      $result = toggleProductCommentLike($pdo, (int) $product['id'], (int) $currentUser['id'], $commentId);

      if (!empty($result['ok'])) {
        if ($isAjaxLikeRequest) {
          respondCommentJson([
            'ok' => true,
            'comment_id' => $commentId,
            'liked' => !empty($result['liked']),
            'like_count' => (int) ($result['like_count'] ?? 0),
          ]);
        }
        header('Location: ' . $currentUrl . '#product-comments');
        exit;
      }

      if ($isAjaxLikeRequest) {
        respondCommentJson(['ok' => false, 'message' => $result['message'] ?? 'Unable to update like right now.'], 400);
      }
      $commentError = $result['message'] ?? 'Unable to update like right now.';
    }
  }
}

$viewerUserId = !empty($currentUser['id']) ? (int) $currentUser['id'] : null;
$comments = getProductComments($pdo, (int) $product['id'], $viewerUserId);
$commentTree = [];
foreach ($comments as $comment) {
  $parentKey = $comment['parent_comment_id'] ?? 0;
  $parentKey = $parentKey ? (int) $parentKey : 0;
  if (!isset($commentTree[$parentKey])) {
    $commentTree[$parentKey] = [];
  }
  $commentTree[$parentKey][] = $comment;
}

function formatProductCommentTime($timestamp) {
  $timeValue = strtotime((string) $timestamp);
  if ($timeValue === false) {
    return (string) $timestamp;
  }

  return date('M d, Y \a\t g:i A', $timeValue);
}

function renderProductCommentThread(array $tree, int $parentId, int $depth, bool $canComment, string $currentUrl): void {
  if (empty($tree[$parentId])) {
    return;
  }

  foreach ($tree[$parentId] as $comment) {
    $commentId = (int) ($comment['id'] ?? 0);
    $indentStyle = $depth > 0 ? ' style="margin-left: ' . ($depth * 1.25) . 'rem;"' : '';
    $nodeClass = $depth > 0 ? 'comment-node comment-node-reply' : 'comment-node';
    $avatarChar = mb_strtoupper(mb_substr((string) ($comment['user_name'] ?? 'A'), 0, 1));
    $likeCount = (int) ($comment['like_count'] ?? 0);
    $isLiked = !empty($comment['is_liked']);
    $likeButtonLabel = $isLiked ? 'Unlike' : 'Like';
    $replyCount = !empty($tree[$commentId]) ? count($tree[$commentId]) : 0;
    $viewReplyLabel = 'View ' . $replyCount . ' ' . ($replyCount === 1 ? 'reply' : 'replies');

    echo '<div class="' . $nodeClass . '"' . $indentStyle . '>';
    echo '  <article class="comment-card">';
    echo '    <div class="comment-header">';
    echo '      <span class="comment-avatar" aria-hidden="true">' . htmlspecialchars($avatarChar, ENT_QUOTES, 'UTF-8') . '</span>';
    echo '      <div class="comment-main">';
    echo '        <div class="comment-author-row">';
    echo '          <strong class="comment-author">' . htmlspecialchars((string) ($comment['user_name'] ?? 'Anonymous'), ENT_QUOTES, 'UTF-8') . '</strong>';
    echo '        </div>';
    echo '        <div class="comment-body">' . nl2br(htmlspecialchars((string) ($comment['content'] ?? ''), ENT_QUOTES, 'UTF-8')) . '</div>';
    echo '        <div class="comment-meta-row">';
    echo '          <span class="comment-time">' . htmlspecialchars(formatProductCommentTime((string) ($comment['created_at'] ?? '')), ENT_QUOTES, 'UTF-8') . '</span>';

    if ($canComment || $likeCount > 0) {
      echo '          <div class="comment-actions">';
    }

    if ($canComment) {
      echo '          <form method="post" action="' . htmlspecialchars($currentUrl, ENT_QUOTES, 'UTF-8') . '#product-comments" class="comment-action-form js-like-form" data-comment-id="' . $commentId . '">';
      echo '            <input type="hidden" name="csrf_token" value="' . htmlspecialchars(authCSRFToken(), ENT_QUOTES, 'UTF-8') . '">';
      echo '            <input type="hidden" name="comment_action" value="toggle_like">';
      echo '            <input type="hidden" name="comment_id" value="' . $commentId . '">';
      echo '            <button type="submit" class="comment-action-link comment-like-link js-like-button' . ($isLiked ? ' active' : '') . '">' . htmlspecialchars($likeButtonLabel, ENT_QUOTES, 'UTF-8') . '</button>';
      echo '          </form>';
      echo '          <button type="button" class="comment-action-link comment-reply-toggle" data-reply-target="reply-form-' . $commentId . '">Reply</button>';
    }

    $likeCountClass = 'comment-like-count js-like-count' . ($likeCount > 0 ? '' : ' d-none');
    echo '          <span class="' . $likeCountClass . '" data-comment-id="' . $commentId . '">' . $likeCount . ' like' . ($likeCount === 1 ? '' : 's') . '</span>';

    if ($canComment || $likeCount > 0) {
      echo '          </div>';
    }

    echo '        </div>';
    echo '      </div>';
    echo '    </div>';

    if ($canComment) {
      echo '    <div id="reply-form-' . $commentId . '" class="comment-reply-wrapper d-none mt-3">';
      echo '      <form method="post" action="' . htmlspecialchars($currentUrl, ENT_QUOTES, 'UTF-8') . '#product-comments" class="comment-reply-form js-comment-form">';
      echo '        <input type="hidden" name="csrf_token" value="' . htmlspecialchars(authCSRFToken(), ENT_QUOTES, 'UTF-8') . '">';
      echo '        <input type="hidden" name="comment_action" value="save_comment">';
      echo '        <input type="hidden" name="parent_comment_id" value="' . $commentId . '">';
      echo '        <textarea name="content" class="form-control" rows="3" maxlength="2000" placeholder="Write your reply..."></textarea>';
      echo '        <div class="d-flex justify-content-end mt-2">';
      echo '          <button type="submit" class="btn btn-dark btn-sm">Post Reply</button>';
      echo '        </div>';
      echo '      </form>';
      echo '    </div>';
    }

    if ($replyCount > 0) {
      echo '    <div class="comment-replies-entry mt-2">';
      echo '      <button type="button" class="comment-view-replies-toggle" data-replies-target="replies-' . $commentId . '" data-view-label="' . htmlspecialchars($viewReplyLabel, ENT_QUOTES, 'UTF-8') . '">';
      echo htmlspecialchars($viewReplyLabel, ENT_QUOTES, 'UTF-8');
      echo '      </button>';
      echo '    </div>';
      echo '    <div id="replies-' . $commentId . '" class="comment-children d-none mt-2">';
      renderProductCommentThread($tree, $commentId, $depth + 1, $canComment, $currentUrl);
      echo '    </div>';
    }

    echo '  </article>';
    echo '</div>';
  }
}

require '../components/header.php';
require '../components/navBar.php';
?>

<main class="product-detail-page">
  <section class="product-detail-shell container py-4 py-lg-5">
    <div class="mb-4">
      <a class="text-decoration-none detail-back-link" href="index.php?p=products">&larr; Back to products</a>
    </div>

    <div class="row g-4 g-lg-5 align-items-start">
      <div class="col-12 col-lg-7">
        <div class="detail-model-card">
          <model-viewer
            class="detail-model"
            src="<?php echo htmlspecialchars((string) $product['modelPath'], ENT_QUOTES, 'UTF-8'); ?>"
            poster="images/model-placeholder.svg"
            alt="<?php echo htmlspecialchars((string) $product['name'], ENT_QUOTES, 'UTF-8'); ?> 3D model"
            camera-controls
            auto-rotate
            shadow-intensity="1"
            tone-mapping="neutral"
            exposure="1"
            interaction-prompt="none"
          ></model-viewer>
        </div>
      </div>

      <div class="col-12 col-lg-5">
        <div class="detail-info-card">
          <h1 class="detail-title mb-3"><?php echo htmlspecialchars((string) $product['name'], ENT_QUOTES, 'UTF-8'); ?></h1>
          <?php if (!empty($product['isPremium'])): ?>
            <p class="detail-price mb-4"><?php echo formatCurrencyVND($product['price']); ?></p>
            <span class="catalog-tag catalog-tag-premium mb-4">Premium</span>
          <?php else: ?>
            <p class="detail-price detail-price-free mb-4">Free</p>
          <?php endif; ?>
          <div class="d-flex gap-2 mb-4">
            <?php if (!empty($product['isPremium'])): ?>
              <?php if ($isOwnedByCustomer): ?>
                <a href="index.php?p=inventory" class="btn btn-dark">Open Inventory</a>
              <?php else: ?>
                <button
                  type="button"
                  id="add-to-cart-btn"
                  class="btn btn-dark"
                  data-product-id="<?php echo (int) $product['id']; ?>"
                  data-product-name="<?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?>"
                  data-product-price="<?php echo (float) $product['price']; ?>"
                  data-product-image="<?php echo htmlspecialchars($product['thumbPath'] ?? 'images/product-placeholder.svg', ENT_QUOTES, 'UTF-8'); ?>"
                  data-product-description="<?php echo htmlspecialchars($product['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                  data-product-premium="1"
                >
                  Add to Cart
                </button>
                <button
                  type="button"
                  id="buy-now-btn"
                  class="btn btn-outline-dark"
                  data-product-id="<?php echo (int) $product['id']; ?>"
                  data-auth="<?php echo $customerUser ? '1' : '0'; ?>"
                  data-csrf="<?php echo htmlspecialchars(authCSRFToken(), ENT_QUOTES, 'UTF-8'); ?>"
                >
                  Buy Now
                </button>
                <button
                  type="button"
                  id="remove-from-cart-btn"
                  class="btn btn-outline-danger d-none"
                >
                  Remove from Cart
                </button>
              <?php endif; ?>
            <?php else: ?>
              <a href="<?php echo htmlspecialchars((string) ($product['modelPath'] ?? '#'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-dark" download>Download Free Model</a>
            <?php endif; ?>
          </div>
          <p class="detail-copy mb-4">
            <?php echo htmlspecialchars($product['description'] ?? 'No description available for this product.', ENT_QUOTES, 'UTF-8'); ?>
          </p>

          <div class="detail-meta-grid">
            <div>
              <span class="detail-meta-label">Mode</span>
              <strong>Interactive 3D</strong>
            </div>
            <div>
              <span class="detail-meta-label">Controls</span>
              <strong>Hover + Drag</strong>
            </div>
          </div>
        </div>
      </div>
    </div>

    <section id="product-comments" class="product-comments-section mt-5">
      <div class="d-flex justify-content-between align-items-end gap-3 flex-wrap mb-3">
        <div>
          <h2 class="section-title mb-1">Comments</h2>
        </div>
        <div class="text-secondary small">
          <?php echo count($comments); ?> comment<?php echo count($comments) === 1 ? '' : 's'; ?>
        </div>
      </div>

      <?php if ($commentSuccess): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($commentSuccess, ENT_QUOTES, 'UTF-8'); ?></div>
      <?php endif; ?>

      <?php if ($commentError): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($commentError, ENT_QUOTES, 'UTF-8'); ?></div>
      <?php endif; ?>

      <div class="comments-panel">
        <?php if ($canComment): ?>
          <div class="comment-compose-card mb-4">
            <form method="post" action="<?php echo htmlspecialchars($currentUrl, ENT_QUOTES, 'UTF-8'); ?>#product-comments" class="d-grid gap-3 js-comment-form">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(authCSRFToken(), ENT_QUOTES, 'UTF-8'); ?>">
              <input type="hidden" name="comment_action" value="save_comment">
              <input type="hidden" name="parent_comment_id" value="0">
              <textarea name="content" class="form-control" rows="4" maxlength="2000" placeholder="Share your thoughts about this 3D model..."></textarea>
              <div class="d-flex justify-content-end">
                <button type="submit" class="btn btn-dark">Post Comment</button>
              </div>
            </form>
          </div>
        <?php else: ?>
          <div class="comment-login-card mb-4">
            <strong class="d-block mb-1">Sign in to comment</strong>
            <p class="text-secondary mb-3 mb-lg-0">Only signed-in users can post comments and replies.</p>
            <a href="index.php?p=login" class="btn btn-dark btn-sm">Go to Login</a>
          </div>
        <?php endif; ?>

        <div class="comments-thread">
          <?php if (!empty($comments)): ?>
            <?php renderProductCommentThread($commentTree, 0, 0, $canComment, $currentUrl); ?>
          <?php else: ?>
            <div class="comment-empty-state">
              <h3 class="h5 mb-2">No comments yet</h3>
              <p class="text-secondary mb-0">Be the first to start the discussion for this product.</p>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </section>
  </section>
</main>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    var viewer = document.querySelector('.detail-model');
    var addToCartBtn = document.getElementById('add-to-cart-btn');
    var buyNowBtn = document.getElementById('buy-now-btn');
    var removeFromCartBtn = document.getElementById('remove-from-cart-btn');
    if (!viewer) {
      return;
    }

    viewer.addEventListener('mouseenter', function () {
      viewer.setAttribute('auto-rotate', '');
      viewer.setAttribute('auto-rotate-delay', '0');
    });

    viewer.addEventListener('mouseleave', function () {
      viewer.removeAttribute('auto-rotate');
    });

    viewer.addEventListener('focusin', function () {
      viewer.setAttribute('auto-rotate', '');
      viewer.setAttribute('auto-rotate-delay', '0');
    });

    viewer.addEventListener('focusout', function () {
      viewer.removeAttribute('auto-rotate');
    });

    if (addToCartBtn && window.CartUtil) {
      var productId = Number(addToCartBtn.dataset.productId || 0);
      function syncCartButtons() {
        var alreadyInCart = window.CartUtil.getCartItems().some(function (item) {
          return Number(item.id) === productId;
        });

        if (alreadyInCart) {
          addToCartBtn.textContent = 'In Cart';
          addToCartBtn.disabled = true;
          if (removeFromCartBtn) {
            removeFromCartBtn.classList.remove('d-none');
          }
        } else {
          addToCartBtn.textContent = 'Add to Cart';
          addToCartBtn.disabled = false;
          if (removeFromCartBtn) {
            removeFromCartBtn.classList.add('d-none');
          }
        }
      }

      syncCartButtons();

      addToCartBtn.addEventListener('click', function () {
        var product = {
          id: productId,
          name: addToCartBtn.dataset.productName || 'Unknown Product',
          price: Number(addToCartBtn.dataset.productPrice || 0),
          is_premium: true,
          image_url: addToCartBtn.dataset.productImage || 'images/product-placeholder.svg',
          description: addToCartBtn.dataset.productDescription || '',
          quantity: 1
        };

        try {
          window.CartUtil.addToCart(product);
          syncCartButtons();
        } catch (error) {
          console.error('Failed to add item to cart:', error);
          alert('Could not add product to cart. Please try again.');
        }
      });

      if (removeFromCartBtn) {
        removeFromCartBtn.addEventListener('click', function () {
          window.CartUtil.removeFromCart(productId);
          syncCartButtons();
        });
      }

      window.addEventListener('cart:updated', syncCartButtons);
    }

    if (buyNowBtn && window.CartUtil && addToCartBtn) {
      buyNowBtn.addEventListener('click', function () {
        var product = {
          id: Number(addToCartBtn.dataset.productId || 0),
          name: addToCartBtn.dataset.productName || 'Unknown Product',
          price: Number(addToCartBtn.dataset.productPrice || 0),
          is_premium: true,
          image_url: addToCartBtn.dataset.productImage || 'images/product-placeholder.svg',
          description: addToCartBtn.dataset.productDescription || '',
          quantity: 1
        };

        buyNowBtn.disabled = true;

        try {
          window.CartUtil.addToCart(product);
          window.location.href = 'index.php?p=cart';
        } catch (error) {
          console.error('Failed to process Buy Now:', error);
          alert('Could not continue to checkout. Please try again.');
          buyNowBtn.disabled = false;
        }
      });
    }

    function showCommentFeedback(message, tone) {
      var commentsSection = document.getElementById('product-comments');
      if (!commentsSection) {
        return;
      }

      var oldFeedback = commentsSection.querySelector('.js-comment-ajax-feedback');
      if (oldFeedback) {
        oldFeedback.remove();
      }

      var feedback = document.createElement('div');
      feedback.className = 'alert js-comment-ajax-feedback ' + (tone === 'success' ? 'alert-success' : 'alert-danger');
      feedback.textContent = message || '';
      commentsSection.insertBefore(feedback, commentsSection.querySelector('.comments-panel'));
    }

    function getOpenedReplies() {
        var opened = [];
        document.querySelectorAll('.comment-children').forEach(function (el) {
        if (!el.classList.contains('d-none')) {
          opened.push(el.id);
        }
      });
      return opened;
    }

    async function refreshCommentsPanel() {

var commentsSection = document.getElementById('product-comments');
if (!commentsSection) {
  return;
}

// lưu replies đang mở
var openedReplies = getOpenedReplies();

var response = await fetch(window.location.href.split('#')[0], {
  headers: { 'X-Requested-With': 'XMLHttpRequest' }
});

if (!response.ok) {
  throw new Error('Unable to refresh comments.');
}

var html = await response.text();

var parser = new DOMParser();
var doc = parser.parseFromString(html, 'text/html');

var incomingPanel = doc.querySelector('#product-comments .comments-panel');
var currentPanel = commentsSection.querySelector('.comments-panel');

if (!incomingPanel || !currentPanel) {
  throw new Error('Unable to refresh comments.');
}

currentPanel.replaceWith(incomingPanel);

bindCommentInteractions();

openedReplies.forEach(function(id){
  var el = document.getElementById(id);
  if(el){
    el.classList.remove('d-none');
  }
});

}

    function bindCommentInteractions() {
      var replyButtons = document.querySelectorAll('.comment-reply-toggle');
      replyButtons.forEach(function (button) {
        button.addEventListener('click', function () {
          var targetId = button.getAttribute('data-reply-target');
          if (!targetId) {
            return;
          }
          var target = document.getElementById(targetId);
          if (!target) {
            return;
          }
          target.classList.toggle('d-none');
        });
      });

      var viewReplyButtons = document.querySelectorAll('.comment-view-replies-toggle');
      viewReplyButtons.forEach(function (button) {
        button.addEventListener('click', function () {
          var targetId = button.getAttribute('data-replies-target');
          if (!targetId) {
            return;
          }
          var target = document.getElementById(targetId);
          if (!target) {
            return;
          }
          var isHidden = target.classList.contains('d-none');
          target.classList.toggle('d-none');
          button.textContent = isHidden ? 'Hide replies' : (button.getAttribute('data-view-label') || button.textContent);
        });
      });

      var commentForms = document.querySelectorAll('.js-comment-form');

commentForms.forEach(function (form) {

  form.addEventListener('submit', async function (event) {

    event.preventDefault();

    var submitButton = form.querySelector('button[type="submit"]');
    var textarea = form.querySelector('textarea');

    if (submitButton) submitButton.disabled = true;

    try {

      var formData = new FormData(form);
      formData.set('ajax', '1');

      var response = await fetch(form.action.split('#')[0], {
        method: 'POST',
        body: formData,
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json'
        }
      });

      var result = await response.json();

      if (!response.ok || !result.ok) {
        throw new Error(result.message || 'Unable to save your comment.');
      }

      await refreshCommentsPanel();

      showCommentFeedback(result.message || 'Comment posted successfully.', 'success');

      // reset textarea
      if (textarea) textarea.value = '';

      // hide reply form after submit
      var wrapper = form.closest('.comment-reply-wrapper');
      if (wrapper) {
        wrapper.classList.add('d-none');
      }

    } catch (error) {

      showCommentFeedback(error.message || 'Unable to save your comment.', 'error');

    } finally {

      if (submitButton) submitButton.disabled = false;

    }

  });

});

      var likeForms = document.querySelectorAll('.js-like-form');
      likeForms.forEach(function (form) {
        form.addEventListener('submit', async function (event) {
          event.preventDefault();

          var likeButton = form.querySelector('.js-like-button');
          var commentId = form.getAttribute('data-comment-id');
          if (!commentId || !likeButton) {
            return;
          }

          var likeCountEl = document.querySelector('.js-like-count[data-comment-id="' + commentId + '"]');
          var formData = new FormData(form);
          formData.set('ajax', '1');

          likeButton.disabled = true;

          try {
            var response = await fetch(form.action.split('#')[0], {
              method: 'POST',
              body: formData,
              headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
              }
            });

            var result = await response.json();
            if (!response.ok || !result.ok) {
              throw new Error(result.message || 'Unable to update like right now.');
            }

            var liked = !!result.liked;
            var likeCount = Number(result.like_count || 0);

            likeButton.textContent = liked ? 'Unlike' : 'Like';
            likeButton.classList.toggle('active', liked);

            if (likeCountEl) {
              likeCountEl.textContent = likeCount + (likeCount === 1 ? ' like' : ' likes');
              likeCountEl.classList.toggle('d-none', likeCount <= 0);
            }
          } catch (error) {
            alert(error.message || 'Unable to update like right now.');
          } finally {
            likeButton.disabled = false;
          }
        });
      });
    }

    bindCommentInteractions();
  });
</script>

<?php require '../components/footer.php'; ?>