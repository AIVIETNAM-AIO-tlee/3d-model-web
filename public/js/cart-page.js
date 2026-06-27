(function () {
  var checkoutState = {
    couponCode: "",
    discountAmount: 0,
    lastCartSignature: "",
    pendingRedirectUrl: "index.php?p=inventory",
  };

  function currency(value) {
    return new Intl.NumberFormat("vi-VN").format(Number(value || 0)) + " ₫";
  }

  function getCartSignature(items) {
    return items
      .map(function (item) {
        return String(item.id);
      })
      .sort()
      .join(",");
  }

  function getPremiumItems(items) {
    return (items || []).filter(function (item) {
      return Boolean(item.is_premium) && Number(item.price || 0) > 0;
    });
  }

  function getPremiumSubtotal(items) {
    return getPremiumItems(items).reduce(function (sum, item) {
      return sum + Number(item.price || 0) * Number(item.quantity || 1);
    }, 0);
  }

  function setCouponFeedback(message, tone) {
    var feedbackEl = document.getElementById("cart-coupon-feedback");
    if (!feedbackEl) {
      return;
    }

    feedbackEl.textContent = message || "";
    feedbackEl.classList.remove(
      "text-slate-500",
      "text-emerald-600",
      "text-rose-600",
    );

    if (tone === "success") {
      feedbackEl.classList.add("text-emerald-600");
      return;
    }

    if (tone === "error") {
      feedbackEl.classList.add("text-rose-600");
      return;
    }

    feedbackEl.classList.add("text-slate-500");
  }

  function resetCouponState(message) {
    checkoutState.couponCode = "";
    checkoutState.discountAmount = 0;
    if (message) {
      setCouponFeedback(message, "neutral");
    } else {
      setCouponFeedback("", "neutral");
    }
  }

  function updateSummary(items) {
    var subtotalEl = document.getElementById("cart-subtotal");
    var discountEl = document.getElementById("cart-discount");
    var totalEl = document.getElementById("cart-total");

    var subtotal = getPremiumSubtotal(items || []);
    var discount = Math.min(
      subtotal,
      Math.max(0, Number(checkoutState.discountAmount || 0)),
    );
    var total = Math.max(0, subtotal - discount);

    if (subtotalEl) {
      subtotalEl.textContent = currency(subtotal);
    }
    if (discountEl) {
      discountEl.textContent = "-" + currency(discount);
    }
    if (totalEl) {
      totalEl.textContent = currency(total);
    }
  }

  function getSelectedPaymentMethod() {
    var selected = document.querySelector(
      'input[name="payment_method"]:checked',
    );
    if (!selected) {
      return null;
    }
    return String(selected.value || "vietqr");
  }

  function syncPaymentMethodCards() {
    var cards = document.querySelectorAll(".payment-method-card");
    cards.forEach(function (card) {
      var input = card.querySelector('input[name="payment_method"]');
      if (!input) {
        return;
      }

      var isSelected = input.checked;
      card.classList.toggle("border-blue-600", isSelected);
      card.classList.toggle("bg-blue-50", isSelected);
      card.classList.toggle("ring-2", isSelected);
      card.classList.toggle("ring-blue-200", isSelected);
      card.classList.toggle("shadow-md", isSelected);
      card.classList.toggle("border-slate-200", !isSelected);
      card.classList.toggle("border-slate-100", !isSelected);
    });
  }

  function hidePaymentModal() {
    var modal = document.getElementById("payment-sim-modal");
    if (!modal) {
      return;
    }
    modal.classList.add("hidden");
    modal.classList.remove("flex");
  }

  function openPaymentSheetPage(instructions, amount, reference, csrfToken) {
    var payload = {
      payment_instructions: instructions || {},
      total_amount: Number(amount || 0),
      payment_reference: reference || "",
      redirect_url: checkoutState.pendingRedirectUrl || "index.php?p=inventory",
      csrf_token: csrfToken || "",
    };

    try {
      window.sessionStorage.setItem(
        "checkoutPaymentSheet",
        JSON.stringify(payload),
      );
    } catch (error) {
      // If storage is unavailable, continue with redirect using fallback params.
    }

    window.location.href = "index.php?p=payment_sheet";
  }

  function showPaymentModal(instructions, amount, reference) {
    var modal = document.getElementById("payment-sim-modal");
    if (!modal) {
      return;
    }

    var methodEl = document.getElementById("payment-sim-method");
    var amountEl = document.getElementById("payment-sim-amount");
    var referenceEl = document.getElementById("payment-sim-ref");
    var noteEl = document.getElementById("payment-sim-note");
    var qrEl = document.getElementById("payment-sim-qr");
    var extraEl = document.getElementById("payment-sim-extra");
    var stepsEl = document.getElementById("payment-sim-steps");
    var openUrlEl = document.getElementById("payment-sim-open-url");

    if (methodEl) {
      methodEl.textContent =
        (instructions && instructions.display_name) || "Payment method";
    }
    if (amountEl) {
      amountEl.textContent = currency(amount);
    }
    if (referenceEl) {
      referenceEl.textContent = reference || "-";
    }
    if (noteEl) {
      noteEl.textContent = (instructions && instructions.transfer_note) || "-";
    }

    if (qrEl) {
      var qrUrl =
        instructions && instructions.qr_url ? instructions.qr_url : "";
      if (qrUrl) {
        qrEl.src = qrUrl;
        qrEl.classList.remove("hidden");
      } else {
        qrEl.src = "";
        qrEl.classList.add("hidden");
      }
    }

    if (extraEl) {
      var lines = [];
      if (instructions && instructions.sandbox_note) {
        lines.push(String(instructions.sandbox_note));
      }
      if (instructions && instructions.bank_name) {
        lines.push("Bank: " + instructions.bank_name);
      }
      if (instructions && instructions.account_number) {
        lines.push("Account: " + instructions.account_number);
      }
      if (instructions && instructions.wallet_id) {
        lines.push("Wallet ID: " + instructions.wallet_id);
      }
      extraEl.textContent = lines.join(" | ");
    }

    if (stepsEl) {
      var stepItems = [];
      if (instructions && Array.isArray(instructions.steps)) {
        stepItems = instructions.steps;
      } else {
        stepItems = [
          "Open your selected payment app.",
          "Scan the QR code on the left.",
          "Confirm the exact amount and note.",
          "Click Complete Payment after transfer.",
        ];
      }

      stepsEl.innerHTML = stepItems
        .map(function (step, index) {
          return (
            '<li class="flex items-start gap-3 rounded-2xl border border-slate-200 bg-gradient-to-br from-white to-slate-50 p-3">' +
            '<span class="mt-0.5 flex h-7 w-7 flex-none items-center justify-center rounded-full bg-blue-600 text-xs font-bold text-white shadow-sm">' +
            (index + 1) +
            "</span>" +
            '<span class="leading-6 text-slate-700">' +
            step +
            "</span>" +
            "</li>"
          );
        })
        .join("");
    }

    if (openUrlEl) {
      var paymentUrl =
        instructions && instructions.payment_url
          ? String(instructions.payment_url)
          : "";
      if (paymentUrl) {
        openUrlEl.href = paymentUrl;
        openUrlEl.textContent =
          "Open " +
          ((instructions && instructions.display_name) || "payment link");
        openUrlEl.classList.remove("hidden");
      } else {
        openUrlEl.href = "#";
        openUrlEl.textContent = "Open payment link";
        openUrlEl.classList.add("hidden");
      }
    }

    modal.classList.remove("hidden");
    modal.classList.add("flex");
  }

  async function applyCoupon() {
    var applyBtn = document.getElementById("cart-apply-coupon-btn");
    var inputEl = document.getElementById("cart-coupon-code");
    if (!window.CartUtil || !inputEl) {
      return;
    }

    var allItems = window.CartUtil.getCartItems();
    var premiumItems = getPremiumItems(allItems);
    if (premiumItems.length === 0) {
      resetCouponState("No premium products available for coupon.");
      updateSummary(allItems);
      return;
    }

    var rawCode = String(inputEl.value || "")
      .trim()
      .toUpperCase();
    if (rawCode === "") {
      resetCouponState("Coupon removed.");
      updateSummary(allItems);
      return;
    }

    if (applyBtn) {
      applyBtn.disabled = true;
    }

    try {
      var response = await fetch("index.php?p=api_coupon", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
        },
        body: JSON.stringify({
          coupon_code: rawCode,
          items: premiumItems.map(function (item) {
            return { id: item.id };
          }),
        }),
      });

      var payload = await response.json().catch(function () {
        return { ok: false, error: "Invalid coupon response." };
      });

      if (!response.ok || !payload.ok) {
        checkoutState.couponCode = "";
        checkoutState.discountAmount = 0;
        setCouponFeedback(payload.error || "Coupon is not valid.", "error");
        updateSummary(allItems);
        return;
      }

      checkoutState.couponCode = String(
        (payload.coupon && payload.coupon.code) || rawCode,
      );
      checkoutState.discountAmount = Number(payload.discount_amount || 0);
      inputEl.value = checkoutState.couponCode;
      setCouponFeedback("Coupon applied successfully.", "success");
      updateSummary(allItems);
    } catch (error) {
      checkoutState.couponCode = "";
      checkoutState.discountAmount = 0;
      setCouponFeedback(error.message || "Unable to apply coupon.", "error");
      updateSummary(allItems);
    } finally {
      if (applyBtn) {
        applyBtn.disabled = false;
      }
    }
  }

  function priceLabel(item) {
    if (!item || !item.is_premium || Number(item.price || 0) <= 0) {
      return "Free";
    }
    return currency(item.price);
  }

  function renderCart() {
    var root = document.getElementById("cart-items-root");
    var emptyEl = document.getElementById("cart-empty-state");
    var contentEl = document.getElementById("cart-content");

    if (!root || !window.CartUtil) {
      return;
    }

    var items = window.CartUtil.getCartItems();
    var cartSignature = getCartSignature(items);
    if (
      checkoutState.lastCartSignature &&
      checkoutState.lastCartSignature !== cartSignature &&
      checkoutState.couponCode
    ) {
      resetCouponState("Cart changed. Please re-apply coupon.");
      var couponInput = document.getElementById("cart-coupon-code");
      if (couponInput) {
        couponInput.value = "";
      }
    }
    checkoutState.lastCartSignature = cartSignature;

    if (items.length === 0) {
      emptyEl.classList.remove("hidden");
      contentEl.classList.add("hidden");
      root.innerHTML = "";
      updateSummary([]);
      return;
    }

    emptyEl.classList.add("hidden");
    contentEl.classList.remove("hidden");

    root.innerHTML = items
      .map(function (item) {
        var subtotal = item.price * item.quantity;
        var eachLabel = priceLabel(item);
        var subtotalLabel = item.is_premium ? currency(subtotal) : "Free";
        return (
          '\n        <div class="flex items-center gap-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">\n          <img src="' +
          item.image_url +
          '" alt="' +
          item.name +
          '" class="h-16 w-16 rounded-lg object-cover bg-slate-100" />\n          <div class="min-w-0 flex-1">\n            <h3 class="truncate text-sm font-semibold text-slate-900">' +
          item.name +
          '</h3>\n            <p class="mt-1 text-sm text-slate-500">' +
          eachLabel +
          ' each</p>\n            <p class="mt-1 text-sm font-medium text-slate-700">Subtotal: ' +
          subtotalLabel +
          '</p>\n          </div>\n          <button data-action="remove" data-id="' +
          item.id +
          '" class="rounded-lg border border-rose-200 px-3 py-1.5 text-sm font-medium text-rose-600 hover:bg-rose-50" type="button">Remove</button>\n        </div>\n      '
        );
      })
      .join("");
    updateSummary(items);
  }

  function onCartAction(event) {
    var target = event.target;
    if (!(target instanceof HTMLElement)) {
      return;
    }

    var button = target.closest("button[data-action][data-id]");
    if (!button || !window.CartUtil) {
      return;
    }

    var action = button.getAttribute("data-action");
    var id = Number(button.getAttribute("data-id"));
    var item = window.CartUtil.getCartItems().find(function (cartItem) {
      return cartItem.id === id;
    });

    if (!item) {
      return;
    }

    if (action === "remove") {
      window.CartUtil.removeFromCart(id);
      resetCouponState("Cart updated. Apply coupon again if needed.");
      var couponInput = document.getElementById("cart-coupon-code");
      if (couponInput) {
        couponInput.value = "";
      }
    }

    renderCart();
  }

  async function checkoutPremiumItems() {
    var checkoutButton = document.getElementById("cart-checkout-btn");
    if (!checkoutButton || !window.CartUtil) {
      return;
    }

    var isAuthenticated = checkoutButton.getAttribute("data-auth") === "1";
    if (!isAuthenticated) {
      window.location.href = "index.php?p=login";
      return;
    }

    var csrfToken = checkoutButton.getAttribute("data-csrf") || "";
    var allItems = window.CartUtil.getCartItems();
    var premiumItems = getPremiumItems(allItems);

    if (premiumItems.length === 0) {
      alert("No premium products in cart to checkout.");
      return;
    }

    var paymentMethod = getSelectedPaymentMethod();
    if (!paymentMethod) {
      alert("Please choose a payment method first.");
      return;
    }

    checkoutButton.disabled = true;

    try {
      var response = await fetch("index.php?p=api_purchase", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
        },
        body: JSON.stringify({
          mode: "cart",
          csrf_token: csrfToken,
          payment_method: paymentMethod,
          coupon_code: checkoutState.couponCode,
          items: premiumItems.map(function (item) {
            return { id: item.id };
          }),
        }),
      });

      var payload = await response.json().catch(function () {
        return { ok: false, error: "Invalid purchase response." };
      });

      if (!response.ok || !payload.ok) {
        throw new Error(payload.error || "Unable to complete checkout.");
      }

      var purchasedIds = Array.isArray(payload.purchased_ids)
        ? payload.purchased_ids.map(function (id) {
            return Number(id);
          })
        : [];

      if (purchasedIds.length > 0) {
        var remaining = allItems.filter(function (item) {
          return purchasedIds.indexOf(Number(item.id)) === -1;
        });

        if (remaining.length === 0) {
          window.CartUtil.clearCart();
        } else {
          window.CartUtil.clearCart();
          remaining.forEach(function (item) {
            window.CartUtil.addToCart(item);
          });
        }
      }

      checkoutState.couponCode = "";
      checkoutState.discountAmount = 0;
      var couponInput = document.getElementById("cart-coupon-code");
      if (couponInput) {
        couponInput.value = "";
      }
      setCouponFeedback("", "neutral");

      checkoutState.pendingRedirectUrl =
        payload.redirect_url || "index.php?p=inventory";

      if (payload.payment_instructions) {
        openPaymentSheetPage(
          payload.payment_instructions,
          Number(payload.total_amount || 0),
          payload.payment_reference || "",
          payload.csrf_token || "",
        );
        return;
      }

      window.location.href = checkoutState.pendingRedirectUrl;
    } catch (error) {
      alert(error.message || "Unable to complete checkout.");
    } finally {
      checkoutButton.disabled = false;
    }
  }

  document.addEventListener("DOMContentLoaded", function () {
    var root = document.getElementById("cart-items-root");
    if (root) {
      root.addEventListener("click", onCartAction);
      renderCart();
    }

    window.addEventListener("cart:updated", renderCart);

    var applyCouponBtn = document.getElementById("cart-apply-coupon-btn");
    if (applyCouponBtn) {
      applyCouponBtn.addEventListener("click", applyCoupon);
    }

    var couponInput = document.getElementById("cart-coupon-code");
    if (couponInput) {
      couponInput.addEventListener("keydown", function (event) {
        if (event.key === "Enter") {
          event.preventDefault();
          applyCoupon();
        }
      });
    }

    var checkoutButton = document.getElementById("cart-checkout-btn");
    if (checkoutButton) {
      checkoutButton.addEventListener("click", checkoutPremiumItems);
    }

    var paymentInputs = document.querySelectorAll(
      'input[name="payment_method"]',
    );
    paymentInputs.forEach(function (input) {
      input.addEventListener("change", syncPaymentMethodCards);
    });
    syncPaymentMethodCards();

    var closeBtn = document.getElementById("payment-sim-close");
    if (closeBtn) {
      closeBtn.addEventListener("click", hidePaymentModal);
    }

    var doneBtn = document.getElementById("payment-sim-done");
    if (doneBtn) {
      doneBtn.addEventListener("click", function () {
        hidePaymentModal();
        window.location.href = checkoutState.pendingRedirectUrl;
      });
    }
  });
})();
