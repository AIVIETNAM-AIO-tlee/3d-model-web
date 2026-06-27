(function () {
  var STORAGE_KEY = "shopping_cart_items";

  function toNumber(value, fallback) {
    var n = Number(value);
    return Number.isFinite(n) ? n : fallback;
  }

  function normalizeItem(item) {
    return {
      id: toNumber(item.id, 0),
      name: String(item.name || "Unknown Product"),
      price: toNumber(item.price, 0),
      is_premium: Boolean(item.is_premium),
      image_url: String(item.image_url || "images/product-placeholder.svg"),
      quantity: Math.max(1, Math.floor(toNumber(item.quantity, 1))),
      description: String(item.description || ""),
    };
  }

  function saveCart(items) {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(items));
    window.dispatchEvent(
      new CustomEvent("cart:updated", { detail: { items: items } }),
    );
  }

  function getCartItems() {
    try {
      var raw = localStorage.getItem(STORAGE_KEY);
      if (!raw) {
        return [];
      }
      var parsed = JSON.parse(raw);
      if (!Array.isArray(parsed)) {
        return [];
      }
      return parsed.map(normalizeItem).filter(function (item) {
        return item.id > 0;
      });
    } catch (error) {
      console.error("Failed to parse cart data:", error);
      return [];
    }
  }

  function addToCart(productObject) {
    var product = normalizeItem(productObject || {});
    if (product.id <= 0) {
      throw new Error("Product id is required to add to cart.");
    }

    var items = getCartItems();
    var existing = items.find(function (item) {
      return item.id === product.id;
    });

    if (existing) {
      return items;
    }

    product.quantity = 1;
    items.push(product);

    saveCart(items);
    return items;
  }

  function removeFromCart(productId) {
    var id = toNumber(productId, 0);
    var items = getCartItems().filter(function (item) {
      return item.id !== id;
    });
    saveCart(items);
    return items;
  }

  function updateQuantity(productId, newQuantity) {
    var id = toNumber(productId, 0);
    var quantity = Math.floor(toNumber(newQuantity, 0));
    var items = getCartItems();
    var item = items.find(function (cartItem) {
      return cartItem.id === id;
    });

    if (!item) {
      return items;
    }

    if (quantity <= 0) {
      items = items.filter(function (cartItem) {
        return cartItem.id !== id;
      });
    } else {
      item.quantity = 1;
    }

    saveCart(items);
    return items;
  }

  function getCartTotal() {
    return getCartItems().reduce(function (sum, item) {
      return sum + item.price * item.quantity;
    }, 0);
  }

  function getCartCount() {
    return getCartItems().reduce(function (sum, item) {
      return sum + item.quantity;
    }, 0);
  }

  function clearCart() {
    saveCart([]);
    return [];
  }

  window.CartUtil = {
    addToCart: addToCart,
    removeFromCart: removeFromCart,
    updateQuantity: updateQuantity,
    getCartItems: getCartItems,
    getCartTotal: getCartTotal,
    getCartCount: getCartCount,
    clearCart: clearCart,
  };
})();
