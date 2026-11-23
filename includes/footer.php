    <!-- Footer -->
    <footer class="site-footer">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 col-md-6 mb-4">
                    <h5><?= htmlspecialchars(get_setting('footer_about_title', 'About SA Central Equip')) ?></h5>
                    <p class="text-white-50">
                        <?= htmlspecialchars(get_setting('footer_about_text', 'A comprehensive resource hub for The Salvation Army, providing materials, tools, and guidance for ministry and mission.')) ?>
                    </p>
                    <!--
                    <div class="social-links mt-3">
                        <?php if (get_setting('social_facebook')): ?>
                        <a href="<?= htmlspecialchars(get_setting('social_facebook')) ?>" target="_blank" title="Facebook">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <?php endif; ?>
                        <?php if (get_setting('social_twitter')): ?>
                        <a href="<?= htmlspecialchars(get_setting('social_twitter')) ?>" target="_blank" title="Twitter">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <?php endif; ?>
                        <?php if (get_setting('social_instagram')): ?>
                        <a href="<?= htmlspecialchars(get_setting('social_instagram')) ?>" target="_blank" title="Instagram">
                            <i class="fab fa-instagram"></i>
                        </a>
                        <?php endif; ?>
                        <?php if (get_setting('social_youtube')): ?>
                        <a href="<?= htmlspecialchars(get_setting('social_youtube')) ?>" target="_blank" title="YouTube">
                            <i class="fab fa-youtube"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                    -->
                </div>

                <div class="col-lg-3 col-md-6 mb-4">
                    <h5>Quick Links</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="https://salarmycentral.org/" target="_blank"><i class="fas fa-chevron-right me-2"></i> Central Connection</a></li>
                        <li class="mb-2"><a href="https://www.salvationarmy.org/" target="_blank"><i class="fas fa-chevron-right me-2"></i> Salvation Army IHQ</a></li>
                        <li class="mb-2"><a href="/admin/login.php"><i class="fas fa-chevron-right me-2"></i> Admin Login</a></li>
                    </ul>
                </div>
<!--
                <div class="col-lg-3 col-md-6 mb-4">
                    <h5>Resource Hub</h5>
                    <ul class="list-unstyled">
                        <?php if (get_setting('nav_link_2_url')): ?>
                        <li class="mb-2"><a href="<?= htmlspecialchars(get_setting('nav_link_2_url')) ?>" target="_blank"><i class="fas fa-chevron-right me-2"></i> <?= htmlspecialchars(get_setting('nav_link_2_text', 'IHQ Resources')) ?></a></li>
                        <?php endif; ?>
                        <li class="mb-2"><a href="<?= htmlspecialchars(get_setting('nav_link_1_url', '/resources.html')) ?>"><i class="fas fa-chevron-right me-2"></i> Browse All Resources</a></li>
                        <li class="mb-2"><a href="/admin/login.php"><i class="fas fa-chevron-right me-2"></i> Admin Login</a></li>
                    </ul>
                </div>
                        -->

                <div class="col-lg-5 col-md-6 mb-4">
                    <h5>Contact</h5>
                    <ul class="list-unstyled text-white-50">
                        <?php if (get_setting('footer_contact_email')): ?>
                        <li class="mb-2">
                            <i class="fas fa-envelope me-2"></i>
                            <a href="mailto:<?= htmlspecialchars(get_setting('footer_contact_email')) ?>"><?= htmlspecialchars(get_setting('footer_contact_email')) ?></a>
                        </li>
                        <?php endif; ?>
                        <?php if (get_setting('footer_contact_website')): ?>
                        <li class="mb-2">
                            <i class="fas fa-globe me-2"></i>
                            <a href="<?= htmlspecialchars(get_setting('footer_contact_website')) ?>" target="_blank"><?= htmlspecialchars(str_replace(['https://', 'http://'], '', get_setting('footer_contact_website'))) ?></a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>

            <div class="footer-bottom">
                <p class="mb-0">
                    &copy; <?= date('Y') ?> <?= htmlspecialchars(get_setting('footer_copyright', 'The Salvation Army')) ?>. All rights reserved.
                    <!--
                    <?php if (get_setting('footer_privacy_url')): ?>
                    | <a href="<?= htmlspecialchars(get_setting('footer_privacy_url')) ?>" target="_blank">Privacy Policy</a>
                    <?php endif; ?>
                    <?php if (get_setting('footer_terms_url')): ?>
                    | <a href="<?= htmlspecialchars(get_setting('footer_terms_url')) ?>" target="_blank">Terms of Use</a>
                    <?php endif; ?>
                    -->
                </p>
            </div>
        </div>
    </footer>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
