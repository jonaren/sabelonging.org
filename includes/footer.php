    <footer class="site-footer">
        <div class="fold-rule" aria-hidden="true">
            <span style="background: var(--youth)"></span><span style="background: var(--spirit)"></span><span style="background: var(--lead)"></span>
        </div>
        <div class="container">
            <div class="footer-main">
                <div>
                    <h2><?= htmlspecialchars(get_setting('footer_about_title', 'About SA Central Equip')) ?></h2>
                    <p><?= htmlspecialchars(get_setting('footer_about_text', 'A comprehensive resource hub for The Salvation Army, providing materials, tools, and guidance for ministry and mission.')) ?></p>
                </div>
                <nav aria-label="Footer">
                    <h2>Explore</h2>
                    <ul>
                        <li><a href="/youth.php">Youth</a></li>
                        <li><a href="/spiritual-life.php">Spiritual Life</a></li>
                        <li><a href="/leadership-development.php">Leadership Development</a></li>
                        <li><a href="https://salarmycentral.org/" target="_blank" rel="noopener">Central Connection</a></li>
                        <li><a href="https://www.salvationarmy.org/" target="_blank" rel="noopener">Salvation Army IHQ</a></li>
                    </ul>
                </nav>
                <div class="contact">
                    <h2>Contact</h2>
                    <ul>
                        <?php if (get_setting('footer_contact_email')): ?>
                        <li><a href="mailto:<?= htmlspecialchars(get_setting('footer_contact_email')) ?>"><?= htmlspecialchars(get_setting('footer_contact_email')) ?></a></li>
                        <?php endif; ?>
                        <?php if (get_setting('footer_contact_website')): ?>
                        <li><a href="<?= htmlspecialchars(get_setting('footer_contact_website')) ?>" target="_blank" rel="noopener"><?= htmlspecialchars(str_replace(['https://', 'http://'], '', rtrim(get_setting('footer_contact_website'), '/'))) ?></a></li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom d-flex flex-wrap justify-content-between gap-2">
                <span>&copy; <?= date('Y') ?> <?= htmlspecialchars(get_setting('footer_copyright', 'The Salvation Army')) ?>. All rights reserved.</span>
                <a href="/admin/login.php">Admin login</a>
            </div>
        </div>
    </footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/site.js?v=<?= @filemtime(__DIR__ . '/../assets/js/site.js') ?>"></script>
</body>
</html>
