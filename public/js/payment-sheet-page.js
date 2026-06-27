(function () {
  var pollTimer = null;

  function currency(value) {
    return new Intl.NumberFormat("vi-VN").format(Number(value || 0)) + " ₫";
  }

  function buildStepHtml(step, index) {
    return (
      '<li class="flex items-start gap-3 rounded-2xl border border-slate-200 bg-gradient-to-br from-white to-slate-50 p-3">' +
      '<span class="mt-0.5 flex h-7 w-7 flex-none items-center justify-center rounded-full bg-blue-600 text-xs font-bold text-white shadow-sm">' +
      (index + 1) +
      "</span>" +
      '<span class="leading-6 text-slate-700">' +
      String(step || "") +
      "</span>" +
      "</li>"
    );
  }

  function showWarning(message) {
    var warningEl = document.getElementById("payment-sheet-warning");
    if (!warningEl) {
      return;
    }

    warningEl.textContent = message;
    warningEl.classList.remove("hidden");
  }

  function hideWarning() {
    var warningEl = document.getElementById("payment-sheet-warning");
    if (!warningEl) {
      return;
    }

    warningEl.textContent = "";
    warningEl.classList.add("hidden");
  }

  function showSuccess(orderId) {
    var contentSection = document.getElementById("payment-sheet-content");
    var successSection = document.getElementById("payment-sheet-success");
    var successOrderEl = document.getElementById("payment-sheet-success-order");

    if (successOrderEl) {
      successOrderEl.textContent = "Order ID: " + String(orderId || "-");
    }

    if (contentSection) {
      contentSection.classList.add("hidden");
    }

    if (successSection) {
      successSection.classList.remove("hidden");
    }

    window.scrollTo({ top: 0, behavior: "smooth" });
  }

  async function fetchPaymentStatus(reference, csrfToken) {
    var response = await fetch("index.php?p=api_purchase", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
      },
      body: JSON.stringify({
        mode: "payment_status",
        csrf_token: csrfToken,
        payment_reference: reference,
      }),
    });

    var result = await response.json().catch(function () {
      return { ok: false, error: "Invalid status response." };
    });

    if (!response.ok || !result.ok) {
      throw new Error(result.error || "Unable to check payment status.");
    }

    return result;
  }

  async function confirmPayment(reference, csrfToken) {
    var response = await fetch("index.php?p=api_purchase", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
      },
      body: JSON.stringify({
        mode: "confirm_payment",
        csrf_token: csrfToken,
        payment_reference: reference,
      }),
    });

    var result = await response.json().catch(function () {
      return { ok: false, error: "Invalid confirmation response." };
    });

    if (!response.ok || !result.ok) {
      throw new Error(result.error || "Payment has not been confirmed yet.");
    }

    return result;
  }

  function startStatusPolling(reference, csrfToken, doneBtn) {
    if (pollTimer) {
      window.clearInterval(pollTimer);
    }

    pollTimer = window.setInterval(async function () {
      try {
        var status = await fetchPaymentStatus(reference, csrfToken);
        if (status.is_completed) {
          if (pollTimer) {
            window.clearInterval(pollTimer);
            pollTimer = null;
          }
          hideWarning();
          showSuccess(status.order_id);
          return;
        }

        if (doneBtn && !doneBtn.disabled) {
          doneBtn.textContent = "Check";
        }
      } catch (error) {
        // Keep polling; only show non-blocking warning once.
      }
    }, 4000);
  }

  function loadPayload() {
    try {
      var raw = window.sessionStorage.getItem("checkoutPaymentSheet");
      if (!raw) {
        return null;
      }

      return JSON.parse(raw);
    } catch (error) {
      return null;
    }
  }

  function renderPaymentSheet(payload) {
    var instructions =
      payload && payload.payment_instructions
        ? payload.payment_instructions
        : {};

    var methodEl = document.getElementById("payment-sheet-method");
    var amountEl = document.getElementById("payment-sheet-amount");
    var referenceEl = document.getElementById("payment-sheet-ref");
    var noteEl = document.getElementById("payment-sheet-note");
    var qrEl = document.getElementById("payment-sheet-qr");
    var extraEl = document.getElementById("payment-sheet-extra");
    var stepsEl = document.getElementById("payment-sheet-steps");
    var openUrlEl = document.getElementById("payment-sheet-open-url");
    var doneBtn = document.getElementById("payment-sheet-done");
    var reference = String(
      payload && payload.payment_reference ? payload.payment_reference : "",
    );
    var csrfToken = String(
      payload && payload.csrf_token ? payload.csrf_token : "",
    );

    if (methodEl) {
      methodEl.textContent = String(
        instructions.display_name || "VietQR Bank Transfer",
      );
    }

    if (amountEl) {
      amountEl.textContent = currency(
        payload && payload.total_amount ? payload.total_amount : 0,
      );
    }

    if (referenceEl) {
      referenceEl.textContent = String(
        payload && payload.payment_reference ? payload.payment_reference : "-",
      );
    }

    if (noteEl) {
      noteEl.textContent = String(instructions.transfer_note || "-");
    }

    if (qrEl) {
      var qrUrl = instructions.qr_url ? String(instructions.qr_url) : "";
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
      if (instructions.sandbox_note) {
        lines.push(String(instructions.sandbox_note));
      }
      if (instructions.bank_name) {
        lines.push("Bank: " + String(instructions.bank_name));
      }
      if (instructions.account_number) {
        lines.push("Account: " + String(instructions.account_number));
      }
      if (instructions.wallet_id) {
        lines.push("Wallet ID: " + String(instructions.wallet_id));
      }
      extraEl.textContent = lines.join(" | ");
    }

    if (stepsEl) {
      var stepItems =
        Array.isArray(instructions.steps) && instructions.steps.length > 0
          ? instructions.steps
          : [
              "Open your selected payment app.",
              "Scan the QR code on the left.",
              "Confirm the exact amount and note.",
              "Return and click Complete Payment.",
            ];

      stepsEl.innerHTML = stepItems
        .map(function (step, index) {
          return buildStepHtml(step, index);
        })
        .join("");
    }

    if (openUrlEl) {
      var paymentUrl = instructions.payment_url
        ? String(instructions.payment_url)
        : "";
      if (paymentUrl) {
        openUrlEl.href = paymentUrl;
        openUrlEl.textContent =
          "Open " + String(instructions.display_name || "payment link");
        openUrlEl.classList.remove("hidden");
      } else {
        openUrlEl.href = "#";
        openUrlEl.classList.add("hidden");
      }
    }

    if (doneBtn) {
      doneBtn.addEventListener("click", async function () {
        if (!reference || !csrfToken) {
          showWarning(
            "Missing payment reference. Please go back to cart and checkout again.",
          );
          return;
        }

        doneBtn.disabled = true;
        doneBtn.textContent = "Confirming...";

        try {
          var result = await confirmPayment(reference, csrfToken);
          hideWarning();
          showSuccess(result.order_id);
        } catch (error) {
          showWarning(
            String(
              error && error.message
                ? error.message
                : "Unable to confirm payment right now.",
            ),
          );
          doneBtn.disabled = false;
          doneBtn.textContent = "I have transferred";
        }
      });
    }

    if (reference && csrfToken) {
      startStatusPolling(reference, csrfToken, doneBtn);
    }
  }

  document.addEventListener("DOMContentLoaded", function () {
    var payload = loadPayload();
    if (!payload || !payload.payment_instructions) {
      showWarning(
        "No active checkout payment data found. Please checkout again from cart.",
      );
      return;
    }

    renderPaymentSheet(payload);
  });
})();
