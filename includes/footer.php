</main>

<footer class="site-footer">
  <div class="container footer-grid">
    <div class="footer-brand">
      <a href="<?= baseUrl('index.php') ?>" class="footer-logo">
        <i class="fas fa-motorcycle"></i> MotoTrack
      </a>
      <p>Parts, accessories, and maintenance bookings for everyday motorcycle owners.</p>
      <div class="footer-socials">
        <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
        <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
        <a href="#" aria-label="TikTok"><i class="fab fa-tiktok"></i></a>
        <a href="#" aria-label="YouTube"><i class="fab fa-youtube"></i></a>
      </div>
    </div>

    <div class="footer-links">
      <h4>Pages</h4>
      <ul>
        <li><a href="<?= baseUrl('index.php') ?>">Home</a></li>
        <li><a href="<?= baseUrl('about.php') ?>">About Us</a></li>
        <li><a href="<?= baseUrl('shop.php') ?>">Shop</a></li>
        <li><a href="<?= baseUrl('book-service.php') ?>">Book Service</a></li>
      </ul>
    </div>

    <div class="footer-contact">
      <h4>Contact Us</h4>
      <p><i class="fas fa-phone"></i> 0900 500 1234</p>
      <p><i class="fas fa-envelope"></i> company@mototrack.com</p>
      <p><i class="fas fa-map-marker-alt"></i> Bambang City</p>
      <p><i class="fas fa-clock"></i> Mon - Sun; 8 am - 7 pm</p>
    </div>
  </div>

  <div class="footer-bottom">
    <span>&copy; <?= date('Y') ?> MotoTrack. All rights reserved.</span>
    <span><a href="#">Terms of Use</a> <a href="#">Privacy Notice</a></span>
  </div>
</footer>

<?php if (!$currentUser || !in_array($currentUser['role'], ['admin', 'staff', 'technician'], true)): ?>
<?php require_once __DIR__ . '/qa-banner.php'; ?>
<style>
/* ── MotoTrack Chatbot — fully inlined so it always loads ── */
.chatbot-launcher{position:fixed;right:24px;bottom:24px;width:60px;height:60px;border-radius:50%;background:#d71920;color:#fff;border:none;display:grid;place-items:center;font-size:1.5rem;box-shadow:0 8px 24px rgba(215,25,32,.45);cursor:pointer;z-index:9999;transition:transform .2s,box-shadow .2s}
.chatbot-launcher:hover{transform:scale(1.1);box-shadow:0 12px 32px rgba(215,25,32,.55)}
.chatbot-panel{position:fixed;right:24px;bottom:100px;width:min(380px,calc(100vw - 32px));height:min(540px,calc(100vh - 140px));background:#1a1a1a;border:1px solid rgba(255,255,255,.08);border-radius:20px;box-shadow:0 24px 60px rgba(0,0,0,.6);display:none;flex-direction:column;overflow:hidden;z-index:9999}
.chatbot-panel.is-open{display:flex;animation:cbSlideUp .25s ease}
@keyframes cbSlideUp{from{opacity:0;transform:translateY(20px) scale(.97)}to{opacity:1;transform:translateY(0) scale(1)}}
.chatbot-header{background:linear-gradient(135deg,#d71920 0%,#a5141a 100%);color:#fff;padding:14px 16px;display:flex;align-items:center;justify-content:space-between;flex-shrink:0}
.chatbot-header-info{display:flex;align-items:center;gap:10px}
.chatbot-avatar{width:36px;height:36px;border-radius:50%;background:rgba(255,255,255,.2);display:grid;place-items:center;font-size:1rem;flex-shrink:0}
.chatbot-header strong{display:block;font-size:.95rem}
.chatbot-header span{display:block;font-size:.72rem;opacity:.85;margin-top:1px}
.chatbot-status-dot{width:8px;height:8px;border-radius:50%;background:#4ade80;box-shadow:0 0 0 2px rgba(74,222,128,.3);display:inline-block;margin-right:4px;vertical-align:middle}
.chatbot-close{background:rgba(255,255,255,.15);border:none;color:#fff;font-size:1rem;cursor:pointer;width:30px;height:30px;border-radius:50%;display:grid;place-items:center;transition:background .15s;flex-shrink:0}
.chatbot-close:hover{background:rgba(255,255,255,.3)}
.chatbot-messages{flex:1;overflow-y:auto;padding:16px;display:flex;flex-direction:column;gap:12px;background:#111;scrollbar-width:thin;scrollbar-color:rgba(255,255,255,.1) transparent}
.chatbot-messages::-webkit-scrollbar{width:4px}
.chatbot-messages::-webkit-scrollbar-thumb{background:rgba(255,255,255,.12);border-radius:4px}
.chatbot-bubble{max-width:82%;padding:10px 14px;border-radius:16px;font-size:.875rem;line-height:1.55;white-space:pre-wrap;word-break:break-word}
.chatbot-bubble.user{align-self:flex-end;background:linear-gradient(135deg,#d71920,#a5141a);color:#fff;border-bottom-right-radius:4px;box-shadow:0 4px 12px rgba(215,25,32,.3)}
.chatbot-bubble.bot{align-self:flex-start;background:#242424;color:#e5e5e5;border:1px solid rgba(255,255,255,.07);border-bottom-left-radius:4px}
.chatbot-bubble.bot.is-error{color:#fca5a5;border-color:rgba(215,25,32,.4);background:rgba(215,25,32,.1)}
.chatbot-bubble.typing{color:#888;font-style:italic}
.chatbot-input-row{display:flex;gap:8px;padding:12px 14px;border-top:1px solid rgba(255,255,255,.07);background:#1a1a1a;flex-shrink:0;align-items:flex-end}
.chatbot-input-row textarea{flex:1;resize:none;border:1px solid rgba(255,255,255,.1);border-radius:12px;padding:10px 12px;font:inherit;font-size:.875rem;max-height:100px;color:#e5e5e5;background:#242424;line-height:1.4;transition:border-color .2s,box-shadow .2s}
.chatbot-input-row textarea::placeholder{color:#666}
.chatbot-input-row textarea:focus{outline:none;border-color:#d71920;box-shadow:0 0 0 3px rgba(215,25,32,.2)}
.chatbot-input-row button{border:none;background:linear-gradient(135deg,#d71920,#a5141a);color:#fff;border-radius:12px;width:42px;height:42px;display:grid;place-items:center;font-size:.95rem;cursor:pointer;flex-shrink:0;transition:transform .15s,opacity .15s;box-shadow:0 4px 12px rgba(215,25,32,.4)}
.chatbot-input-row button:hover{transform:scale(1.08)}
.chatbot-input-row button:disabled{opacity:.45;cursor:not-allowed;transform:none}
@media(max-width:480px){.chatbot-panel{right:12px;bottom:88px}.chatbot-launcher{right:12px;bottom:12px}}
</style>
<div id="chatbotWidget"
  data-base-url="<?= htmlspecialchars(baseUrl('')) ?>"
  data-relay-url="<?= htmlspecialchars(envValue('GEMINI_RELAY_URL', '')) ?>"
  data-relay-secret="<?= htmlspecialchars(envValue('GEMINI_RELAY_SECRET', '')) ?>">
  <button type="button" class="chatbot-launcher" id="chatbotLauncher" aria-label="Open MotoTrack Assistant" aria-expanded="false">
    <i class="fas fa-comment-dots"></i>
  </button>

  <div class="chatbot-panel" id="chatbotPanel">
    <div class="chatbot-header">
      <div class="chatbot-header-info">
        <div class="chatbot-avatar"><i class="fas fa-robot"></i></div>
        <div>
          <strong>MotoTrack Assistant</strong>
          <span><span class="chatbot-status-dot"></span>Online · Ask about motorcycles</span>
        </div>
      </div>
      <button type="button" class="chatbot-close" id="chatbotClose" aria-label="Close chat">&times;</button>
    </div>
    <div class="chatbot-messages" id="chatbotMessages">
      <div class="chatbot-bubble bot">Hi<?= $currentUser ? ' ' . htmlspecialchars(explode(' ', $currentUser['name'])[0]) : '' ?>! I'm the MotoTrack Assistant. Ask me anything about motorcycles, our parts, or our services.</div>
    </div>
    <form class="chatbot-input-row" id="chatbotForm">
      <textarea id="chatbotInput" rows="1" placeholder="Type your question..." required></textarea>
      <button type="submit" id="chatbotSend"><i class="fas fa-paper-plane"></i></button>
    </form>
  </div>
</div>
<script>
(function () {
  var root     = document.getElementById('chatbotWidget');
  if (!root) return;

  var baseUrl      = root.dataset.baseUrl || '';
  var relayUrl     = root.dataset.relayUrl || '';
  var relaySecret  = root.dataset.relaySecret || '';

  var launcher = document.getElementById('chatbotLauncher');
  var panel    = document.getElementById('chatbotPanel');
  var closeBtn = document.getElementById('chatbotClose');
  var messages = document.getElementById('chatbotMessages');
  var form     = document.getElementById('chatbotForm');
  var input    = document.getElementById('chatbotInput');
  var sendBtn  = document.getElementById('chatbotSend');

  var history = []; // [{role:'user'|'model', text:'...'}]

  function addBubble(text, role, isError) {
    var bubble = document.createElement('div');
    bubble.className = 'chatbot-bubble ' + (role === 'user' ? 'user' : 'bot') + (isError ? ' is-error' : '');
    bubble.textContent = text;
    messages.appendChild(bubble);
    messages.scrollTop = messages.scrollHeight;
    return bubble;
  }

  launcher.addEventListener('click', function () {
    panel.classList.add('is-open');
    launcher.setAttribute('aria-expanded', 'true');
    input.focus();
  });

  closeBtn.addEventListener('click', function () {
    panel.classList.remove('is-open');
    launcher.setAttribute('aria-expanded', 'false');
  });

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var text = input.value.trim();
    if (!text) return;

    addBubble(text, 'user');
    input.value = '';
    sendBtn.disabled = true;

    var typingBubble = addBubble('Typing…', 'bot');
    typingBubble.classList.add('typing');

    // Step 1: try to get shop context (optional — if it fails, chatbot still works)
    var ctxPromise = fetch(baseUrl + 'api/chatbot-context.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ message: text })
    })
    .then(function(r) { return r.ok ? r.json() : { ok: false }; })
    .catch(function() { return { ok: false }; }); // silently ignore context errors

    ctxPromise.then(function(ctx) {
      // Step 2: call Cloudflare Worker directly from browser
      var workerUrl = relayUrl || baseUrl + 'api/chatbot.php';
      var headers   = { 'Content-Type': 'application/json' };
      if (relayUrl && relaySecret) headers['X-Relay-Secret'] = relaySecret;

      return fetch(workerUrl, {
        method: 'POST',
        headers: headers,
        body: JSON.stringify({
          message:      text,
          history:      history,
          systemPrompt: ctx.systemPrompt || '',
          matchLines:   ctx.matchLines   || []
        })
      })
      .then(function(r) {
        return r.text().then(function(raw) {
          try { return JSON.parse(raw); }
          catch(e) { throw new Error('Worker HTTP ' + r.status + ': ' + raw.substring(0, 150)); }
        });
      });
    })
    .then(function (data) {
      typingBubble.remove();
      if (data.ok) {
        addBubble(data.reply, 'bot');
        history.push({ role: 'user',  text: text });
        history.push({ role: 'model', text: data.reply });
        if (history.length > 20) history = history.slice(-20);
      } else {
        addBubble(data.error || data.message || 'Something went wrong.', 'bot', true);
      }
    })
    .catch(function (err) {
      typingBubble.remove();
      addBubble('Error: ' + (err.message || 'Unknown error'), 'bot', true);
    })
    .finally(function () { sendBtn.disabled = false; });
  });

  input.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      form.requestSubmit ? form.requestSubmit() : form.dispatchEvent(new Event('submit'));
    }
  });
})();
</script>
<?php endif; ?>

<script src="<?= baseUrl('assets/js/main.js?v=' . filemtime(__DIR__ . '/../assets/js/main.js')) ?>"></script>
</body>
</html>
