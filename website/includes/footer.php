<?php
$setup = siteSetup();
$socials = ['facebook' => 'fab fa-facebook', 'instagram' => 'fab fa-instagram', 'linkedin' => 'fab fa-linkedin', 'twitter' => 'fab fa-twitter'];
?>
</main>

<footer class="site-footer">
    <div class="container">
        <div class="row">
            <!-- Brand Column -->
            <div class="col-lg-5 col-md-6 mb-4 mb-lg-0">
                <div class="d-flex align-items-center mb-3">
                    <?php if (!empty($setup['logo'])): ?>
                        <img src="<?= e(siteUrl('user_uploads/' . $setup['logo'])) ?>" alt="<?= e($setup['site_title'] ?? config('organization_name', 'Office')) ?>" height="32" style="margin-right: 0.75rem;">
                    <?php endif; ?>
                    <h5 class="mb-0" style="font-family: var(--font-display); font-weight: 700; color: #fff;"><?= e($setup['site_title'] ?? config('organization_name', 'Office')) ?></h5>
                </div>
                <p class="mb-3" style="color: var(--text-muted); max-width: 300px;"><?= e($setup['tagline'] ?? 'Your technology partner') ?></p>

                <?php if (!empty($setup['contact_email']) || !empty($setup['contact_phone'])): ?>
                    <div class="mb-3">
                        <?php if (!empty($setup['contact_email'])): ?>
                            <p class="mb-2"><i class="fas fa-envelope mr-2" style="color: var(--accent);"></i><a href="mailto:<?= e($setup['contact_email']) ?>"><?= e($setup['contact_email']) ?></a></p>
                        <?php endif; ?>
                        <?php if (!empty($setup['contact_phone'])): ?>
                            <p class="mb-0"><i class="fas fa-phone mr-2" style="color: var(--accent);"></i><a href="tel:<?= e($setup['contact_phone']) ?>"><?= e($setup['contact_phone']) ?></a></p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($setup['address'])): ?>
                    <p class="mb-0" style="color: var(--text-muted); font-size: 0.875rem;">
                        <i class="fas fa-map-marker-alt mr-2" style="color: var(--accent);"></i><?= e($setup['address']) ?>
                    </p>
                <?php endif; ?>
            </div>

            <!-- Quick Links -->
            <div class="col-lg-3 col-md-6 mb-4 mb-lg-0">
                <h6 class="text-uppercase" style="color: #fff; font-weight: 600; letter-spacing: 0.1em; margin-bottom: 1.25rem;">Quick Links</h6>
                <ul class="list-unstyled" style="padding: 0;">
                    <li style="padding: 0.5rem 0; border: none;"><a href="<?= siteUrl('about') ?>" style="color: var(--text-muted); transition: color 0.2s;"><i class="fas fa-chevron-right mr-2" style="font-size: 0.75rem;"></i>About Us</a></li>
                    <li style="padding: 0.5rem 0; border: none;"><a href="<?= siteUrl('services') ?>" style="color: var(--text-muted); transition: color 0.2s;"><i class="fas fa-chevron-right mr-2" style="font-size: 0.75rem;"></i>Services</a></li>
                    <li style="padding: 0.5rem 0; border: none;"><a href="<?= siteUrl('projects') ?>" style="color: var(--text-muted); transition: color 0.2s;"><i class="fas fa-chevron-right mr-2" style="font-size: 0.75rem;"></i>Projects</a></li>
                    <li style="padding: 0.5rem 0; border: none;"><a href="<?= siteUrl('careers') ?>" style="color: var(--text-muted); transition: color 0.2s;"><i class="fas fa-chevron-right mr-2" style="font-size: 0.75rem;"></i>Careers</a></li>
                    <li style="padding: 0.5rem 0; border: none;"><a href="<?= siteUrl('contact') ?>" style="color: var(--text-muted); transition: color 0.2s;"><i class="fas fa-chevron-right mr-2" style="font-size: 0.75rem;"></i>Contact</a></li>
                </ul>
            </div>

            <!-- Services Column -->
            <div class="col-lg-2 col-md-6 mb-4 mb-lg-0">
                <h6 class="text-uppercase" style="color: #fff; font-weight: 600; letter-spacing: 0.1em; margin-bottom: 1.25rem;">Services</h6>
                <ul class="list-unstyled" style="padding: 0;">
                    <li style="padding: 0.5rem 0; border: none;"><a href="<?= siteUrl('services') ?>#web" style="color: var(--text-muted); transition: color 0.2s; font-size: 0.875rem;">Web Development</a></li>
                    <li style="padding: 0.5rem 0; border: none;"><a href="<?= siteUrl('services') ?>#mobile" style="color: var(--text-muted); transition: color 0.2s; font-size: 0.875rem;">Mobile Apps</a></li>
                    <li style="padding: 0.5rem 0; border: none;"><a href="<?= siteUrl('services') ?>#cloud" style="color: var(--text-muted); transition: color 0.2s; font-size: 0.875rem;">Cloud Solutions</a></li>
                    <li style="padding: 0.5rem 0; border: none;"><a href="<?= siteUrl('services') ?>#consulting" style="color: var(--text-muted); transition: color 0.2s; font-size: 0.875rem;">IT Consulting</a></li>
                </ul>
            </div>

            <!-- Social Column -->
            <div class="col-lg-2 col-md-6">
                <h6 class="text-uppercase" style="color: #fff; font-weight: 600; letter-spacing: 0.1em; margin-bottom: 1.25rem;">Follow Us</h6>
                <div class="d-flex flex-wrap">
                    <?php foreach ($socials as $key => $icon): ?>
                        <?php if (!empty($setup[$key])): ?>
                            <a href="<?= e($setup[$key]) ?>" class="social-link" target="_blank" rel="noopener noreferrer" aria-label="<?= ucfirst($key) ?>">
                                <i class="<?= $icon ?>"></i>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>

                <?php if (!empty($setup['contact_email'])): ?>
                    <div class="mt-4">
                        <a href="mailto:<?= e($setup['contact_email']) ?>" class="btn btn-outline-light btn-sm" style="border-radius: var(--radius-full); font-size: 0.8125rem;">
                            <i class="fas fa-paper-plane mr-2"></i>Get in Touch
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Copyright Bar -->
    <div class="site-footer-bottom">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6 text-center text-md-left mb-2 mb-md-0">
                    &copy; <?= date('Y') ?> <?= e($setup['site_title'] ?? config('organization_name', 'Office')) ?>. All rights reserved.
                </div>
                <div class="col-md-6 text-center text-md-right">
                    <a href="<?= siteUrl('privacy.php') ?>" class="mr-3" style="color: var(--text-muted);">Privacy Policy</a>
                    <a href="<?= siteUrl('terms.php') ?>" style="color: var(--text-muted);">Terms of Service</a>
                </div>
            </div>
        </div>
    </div>
</footer>

<!-- Social Link Styles -->
<style>
.social-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: var(--radius-full);
    color: var(--text-muted);
    margin-right: 0.5rem;
    margin-bottom: 0.5rem;
    transition: all var(--transition-fast);
}

.social-link:hover {
    background: var(--accent);
    border-color: var(--accent);
    color: #fff;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
}
</style>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- Theme Switcher -->
<script src="<?= siteAsset('js/theme-switcher.js') ?>"></script>

<!-- Navbar light/dark toggle + scroll-reveal -->
<script>
(function () {
    'use strict';

    // ── Mode toggle ──
    var toggle = document.getElementById('siteModeToggle');
    function paintIcons() {
        if (!toggle) { return; }
        var mode = document.documentElement.getAttribute('data-mode') || 'light';
        toggle.querySelectorAll('[data-mode-icon]').forEach(function (icon) {
            icon.hidden = icon.getAttribute('data-mode-icon') === mode;
        });
    }
    if (toggle && window.SBTechTheme) {
        toggle.addEventListener('click', function () {
            var next = (document.documentElement.getAttribute('data-mode') === 'dark') ? 'light' : 'dark';
            window.SBTechTheme.applyMode(next);
        });
        document.addEventListener('themechange', paintIcons);
        paintIcons();
    } else if (toggle) {
        toggle.hidden = true;
    }

    // ── Scroll-reveal with stagger ──
    var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var targets = document.querySelectorAll('.reveal, [data-reveal-group]');
    if (!targets.length) { return; }
    if (reduced || !('IntersectionObserver' in window)) {
        targets.forEach(function (el) {
            el.classList.add('is-visible');
            el.querySelectorAll('.reveal').forEach(function (c) { c.classList.add('is-visible'); });
        });
        return;
    }
    var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (!entry.isIntersecting) { return; }
            var el = entry.target;
            var kids = el.matches('[data-reveal-group]') ? el.querySelectorAll('.reveal') : [el];
            kids.forEach(function (child, i) {
                child.style.transitionDelay = Math.min(i * 90, 450) + 'ms';
                child.classList.add('is-visible');
            });
            io.unobserve(el);
        });
    }, { threshold: 0.15, rootMargin: '0px 0px -40px 0px' });
    targets.forEach(function (el) { io.observe(el); });
})();
</script>

<!-- Hidden Admin Access (Double-click or Long-press) -->
<script src="<?= siteAsset('js/admin-gesture.js') ?>"></script>

</body>
</html>
