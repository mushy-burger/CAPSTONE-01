</main>

<footer class="site-footer">
  <div class="container footer-shell">
    <div class="footer-grid">
      <div class="footer-brand">
        <a href="<?= baseUrl('index.php') ?>" class="footer-logo">
          <span class="footer-logo-mark"><i class="fas fa-motorcycle" aria-hidden="true"></i></span>
          <span class="footer-logo-copy"><strong>MotoTrack</strong><small>Built for the ride</small></span>
        </a>
        <p>Parts, accessories, and maintenance bookings for everyday motorcycle owners.</p>
        <div class="footer-socials" aria-label="MotoTrack social media">
          <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f" aria-hidden="true"></i></a>
          <a href="#" aria-label="Instagram"><i class="fab fa-instagram" aria-hidden="true"></i></a>
          <a href="#" aria-label="TikTok"><i class="fab fa-tiktok" aria-hidden="true"></i></a>
          <a href="#" aria-label="YouTube"><i class="fab fa-youtube" aria-hidden="true"></i></a>
        </div>
      </div>

      <nav class="footer-links" aria-label="Footer navigation">
        <h2>Pages</h2>
        <a href="<?= baseUrl('index.php') ?>">Home</a>
        <a href="<?= baseUrl('about.php') ?>">About Us</a>
        <a href="<?= baseUrl('shop.php') ?>">Shop</a>
        <a href="<?= baseUrl('book-service.php') ?>">Book Service</a>
      </nav>

      <div class="footer-contact">
        <h2>Contact Us</h2>
        <a href="tel:09005001234"><i class="fas fa-phone" aria-hidden="true"></i><span>0900 500 1234</span></a>
        <a href="mailto:company@mototrack.com"><i class="fas fa-envelope" aria-hidden="true"></i><span>company@mototrack.com</span></a>
        <span class="footer-contact-item"><i class="fas fa-map-marker-alt" aria-hidden="true"></i><span>Bambang City</span></span>
        <span class="footer-contact-item"><i class="fas fa-clock" aria-hidden="true"></i><span>Mon - Sun; 8 am - 7 pm</span></span>
      </div>
    </div>

    <div class="footer-bottom">
      <span>&copy; <?= date('Y') ?> MotoTrack. All rights reserved.</span>
      <span class="footer-legal"><a href="#">Terms of Use</a><a href="#">Privacy Notice</a></span>
    </div>
  </div>
</footer>

<?php if (!$currentUser || !in_array($currentUser['role'], ['admin', 'staff', 'technician'], true)): ?>
<?php require_once __DIR__ . '/qa-banner.php'; ?>
<link rel="stylesheet" href="<?= baseUrl('assets/css/chatbot.css?v=' . filemtime(__DIR__ . '/../assets/css/chatbot.css')) ?>">
<div id="chatbotWidget" data-base-url="<?= htmlspecialchars(baseUrl('')) ?>">
  <button type="button" class="chatbot-launcher" id="chatbotLauncher" aria-label="Open MotoTrack Assistant" aria-expanded="false">
    <i class="fas fa-comment-dots"></i>
  </button>

  <div class="chatbot-panel" id="chatbotPanel">
    <div class="chatbot-header">
      <div>
        <strong>MotoTrack Assistant</strong>
        <span>Ask about motorcycles, parts, or services</span>
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
<script src="<?= baseUrl('assets/js/chatbot.js?v=' . filemtime(__DIR__ . '/../assets/js/chatbot.js')) ?>"></script>
<?php endif; ?>

<script src="<?= baseUrl('assets/js/main.js?v=' . filemtime(__DIR__ . '/../assets/js/main.js')) ?>"></script>
</body>
</html>
