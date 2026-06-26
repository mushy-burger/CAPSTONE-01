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

<script src="<?= baseUrl('assets/js/main.js?v=' . filemtime(__DIR__ . '/../assets/js/main.js')) ?>"></script>
</body>
</html>
