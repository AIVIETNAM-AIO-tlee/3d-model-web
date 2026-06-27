(function () {
  var root = document.getElementById("af-chatbot");
  if (!root) {
    return;
  }

  var panel = document.getElementById("af-chatbot-panel");
  var toggleBtn = document.getElementById("af-chatbot-toggle");
  var closeBtn = document.getElementById("af-chatbot-close");
  var messagesRoot = document.getElementById("af-chatbot-messages");
  var form = document.getElementById("af-chatbot-form");
  var input = document.getElementById("af-chatbot-input");
  var sendBtn = document.getElementById("af-chatbot-send");
  var endpoint =
    root.getAttribute("data-endpoint") || "index.php?p=api_chatbot";
  var assistantAvatarSrc = "images/gemini-color.svg";
  var storageKey = "af_chatbot_history_v1";
  var history = [];
  var defaultAssistantGreeting =
    "Hello! I can help you find products, compare options, and answer questions about your order.";

  function renderMessage(role, text) {
    var item = document.createElement("div");
    item.className =
      "af-chatbot-msg " + (role === "assistant" ? "assistant" : "user");

    if (role === "assistant") {
      var avatar = document.createElement("img");
      avatar.className = "af-chatbot-msg-avatar";
      avatar.src = assistantAvatarSrc;
      avatar.alt = "";
      avatar.setAttribute("aria-hidden", "true");
      item.appendChild(avatar);
    }

    var bubble = document.createElement("div");
    bubble.className = "af-chatbot-bubble";
    bubble.textContent = text;

    item.appendChild(bubble);
    messagesRoot.appendChild(item);
    messagesRoot.scrollTop = messagesRoot.scrollHeight;
  }

  function isReloadNavigation() {
    try {
      if (
        window.performance &&
        typeof window.performance.getEntriesByType === "function"
      ) {
        var navEntries = window.performance.getEntriesByType("navigation");
        if (navEntries && navEntries.length > 0) {
          return navEntries[0].type === "reload";
        }
      }

      if (window.performance && window.performance.navigation) {
        return window.performance.navigation.type === 1;
      }
    } catch (err) {
      return false;
    }

    return false;
  }

  function setLoading(isLoading) {
    if (!sendBtn || !input) {
      return;
    }

    sendBtn.disabled = isLoading;
    input.disabled = isLoading;
    sendBtn.classList.toggle("is-loading", isLoading);
  }

  function loadHistory() {
    try {
      var raw = sessionStorage.getItem(storageKey);
      if (!raw) {
        return false;
      }

      var parsed = JSON.parse(raw);
      if (!Array.isArray(parsed)) {
        return false;
      }

      history = parsed.slice(-10);
      history.forEach(function (item) {
        if (!item || !item.role || !item.content) {
          return;
        }
        renderMessage(item.role, item.content);
      });
      return history.length > 0;
    } catch (err) {
      console.warn("Chat history load failed", err);
      return false;
    }
  }

  function saveHistory() {
    try {
      sessionStorage.setItem(storageKey, JSON.stringify(history.slice(-10)));
    } catch (err) {
      console.warn("Chat history save failed", err);
    }
  }

  async function askAssistant(text) {
    setLoading(true);

    try {
      var response = await fetch(endpoint, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          message: text,
          history: history,
        }),
      });

      var payload = await response.json().catch(function () {
        return { ok: false, error: "Invalid AI response format" };
      });

      if (!response.ok || !payload.ok) {
        throw new Error(payload.error || "Unable to get AI response");
      }

      var reply = String(payload.reply || "").trim();
      if (!reply) {
        throw new Error("Assistant returned an empty reply");
      }

      history.push({ role: "assistant", content: reply });
      renderMessage("assistant", reply);
      saveHistory();
    } catch (err) {
      renderMessage(
        "assistant",
        "Sorry, I cannot answer right now. " + err.message,
      );
    } finally {
      setLoading(false);
      input.focus();
    }
  }

  function openPanel() {
    panel.classList.remove("d-none");
    root.classList.add("open");
    toggleBtn.setAttribute("aria-expanded", "true");
    input.focus();
  }

  function closePanel() {
    panel.classList.add("d-none");
    root.classList.remove("open");
    toggleBtn.setAttribute("aria-expanded", "false");
  }

  toggleBtn.addEventListener("click", function () {
    if (panel.classList.contains("d-none")) {
      openPanel();
    } else {
      closePanel();
    }
  });

  closeBtn.addEventListener("click", closePanel);

  form.addEventListener("submit", function (event) {
    event.preventDefault();
    var text = (input.value || "").trim();
    if (!text) {
      return;
    }

    input.value = "";
    history.push({ role: "user", content: text });
    renderMessage("user", text);
    saveHistory();
    askAssistant(text);
  });

  if (isReloadNavigation()) {
    sessionStorage.removeItem(storageKey);
  }

  var hasHistory = loadHistory();

  if (messagesRoot && !hasHistory) {
    history.push({ role: "assistant", content: defaultAssistantGreeting });
    renderMessage("assistant", defaultAssistantGreeting);
    saveHistory();
  }
})();
