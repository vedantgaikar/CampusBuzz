    </main>
    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-section about">
                    <h3><?= SITE_NAME ?></h3>
                    <p>Your one-stop platform for managing and discovering campus events.</p>
                    <div class="contact">
                        <span><i class="fas fa-envelope"></i> contact@campusbuzz.com</span>
                    </div>
                    <div class="socials">
                        <a href="#"><i class="fab fa-facebook"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
                <div class="footer-section links">
                    <h3>Quick Links</h3>
                    <ul>
                        <li><a href="<?= BASE_URL ?>">Home</a></li>
                        <li><a href="<?= BASE_URL ?>pages/events.php">Events</a></li>
                        <li><a href="<?= BASE_URL ?>pages/about.php">About Us</a></li>
                        <li><a href="<?= BASE_URL ?>pages/contact.php">Contact</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                &copy; <span id="current-year"><?= date('Y') ?></span> <?= SITE_NAME ?> | All rights reserved
            </div>
        </div>
    </footer>
    <script src="<?= BASE_URL ?>js/main.js"></script>
</body>
</html> 