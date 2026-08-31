/**
 * SB-Tech — Hidden Admin Access
 * Double-click or long-press on specific elements to reveal admin panel link.
 * Security: Only shows admin link when gesture is performed.
 */

(function () {
    'use strict';

    // Configuration
    const CONFIG = {
        longPressDuration: 800, // ms for long press
        adminUrl: 'admin/login.php',
        showDuration: 5000, // ms to show admin link before hiding
        targets: [
            { selector: '.site-hero img', type: 'hero-image' },
            { selector: '.site-footer .navbar-brand, .site-footer h5', type: 'footer-brand' }
        ]
    };

    // State
    let longPressTimer = null;
    let adminLinkVisible = false;

    /**
     * Create admin floating link element
     */
    function createAdminLink() {
        if (document.getElementById('admin-gesture-link')) return;

        const link = document.createElement('a');
        link.id = 'admin-gesture-link';
        link.href = CONFIG.adminUrl;
        link.innerHTML = '<i class="fas fa-lock mr-2"></i>Admin Panel';
        link.className = 'admin-gesture-link';
        link.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: linear-gradient(135deg, #1E293B 0%, #0F172A 100%);
            color: #FFFFFF;
            padding: 12px 20px;
            border-radius: 50px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            z-index: 9999;
            display: flex;
            align-items: center;
            gap: 8px;
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            cursor: pointer;
            border: 1px solid rgba(255, 255, 255, 0.1);
        `;

        link.addEventListener('mouseenter', function () {
            this.style.transform = 'translateY(-2px)';
            this.style.boxShadow = '0 6px 25px rgba(37, 99, 235, 0.4)';
        });

        link.addEventListener('mouseleave', function () {
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = '0 4px 20px rgba(0, 0, 0, 0.3)';
        });

        document.body.appendChild(link);

        // Auto-hide after duration
        setTimeout(() => {
            hideAdminLink();
        }, CONFIG.showDuration);
    }

    /**
     * Show admin link with animation
     */
    function showAdminLink() {
        if (adminLinkVisible) return;

        createAdminLink();
        const link = document.getElementById('admin-gesture-link');

        requestAnimationFrame(() => {
            link.style.opacity = '1';
            link.style.transform = 'translateY(0)';
        });

        adminLinkVisible = true;

        // Vibrate on mobile for feedback
        if (navigator.vibrate) {
            navigator.vibrate(50);
        }
    }

    /**
     * Hide admin link with animation
     */
    function hideAdminLink() {
        const link = document.getElementById('admin-gesture-link');
        if (!link) return;

        link.style.opacity = '0';
        link.style.transform = 'translateY(20px)';

        setTimeout(() => {
            if (link.parentNode) {
                link.parentNode.removeChild(link);
            }
            adminLinkVisible = false;
        }, 300);
    }

    /**
     * Handle double-click/tap
     */
    function handleDoubleClick(e) {
        e.preventDefault();
        showAdminLink();
    }

    /**
     * Handle long press start
     */
    function handleLongPressStart(e) {
        longPressTimer = setTimeout(() => {
            showAdminLink();
            longPressTimer = null;
        }, CONFIG.longPressDuration);
    }

    /**
     * Handle long press end/cancel
     */
    function handleLongPressEnd() {
        if (longPressTimer) {
            clearTimeout(longPressTimer);
            longPressTimer = null;
        }
    }

    function redirectToAdmin(element) {
        const adminUrl = element.dataset.adminUrl || CONFIG.adminUrl;
        if (adminUrl) {
            window.location.href = adminUrl;
        }
    }

    function bindLogoRedirect() {
        const logo = document.querySelector('.navbar-brand[data-admin-url]');
        if (!logo) return;

        logo.addEventListener('dblclick', function (e) {
            e.preventDefault();
            e.stopPropagation();
            redirectToAdmin(this);
        });

        logo.addEventListener('touchstart', function (e) {
            const self = this;
            longPressTimer = setTimeout(() => {
                e.preventDefault();
                redirectToAdmin(self);
                longPressTimer = null;
            }, CONFIG.longPressDuration);
        }, { passive: true });

        logo.addEventListener('touchend', handleLongPressEnd, { passive: true });
        logo.addEventListener('touchmove', handleLongPressEnd, { passive: true });
        logo.addEventListener('mousedown', function () {
            const self = this;
            longPressTimer = setTimeout(() => {
                redirectToAdmin(self);
                longPressTimer = null;
            }, CONFIG.longPressDuration);
        });
        logo.addEventListener('mouseup', handleLongPressEnd);
        logo.addEventListener('mouseleave', handleLongPressEnd);
        logo.addEventListener('contextmenu', (e) => {
            if (longPressTimer) {
                e.preventDefault();
            }
        });
    }

    /**
     * Initialize gesture listeners
     */
    function init() {
        bindLogoRedirect();

        CONFIG.targets.forEach(target => {
            const elements = document.querySelectorAll(target.selector);

            elements.forEach(element => {
                // Double-click (desktop)
                element.addEventListener('dblclick', handleDoubleClick);

                // Long press (mobile/desktop)
                element.addEventListener('touchstart', handleLongPressStart, { passive: true });
                element.addEventListener('touchend', handleLongPressEnd, { passive: true });
                element.addEventListener('touchmove', handleLongPressEnd, { passive: true });
                element.addEventListener('mousedown', handleLongPressStart);
                element.addEventListener('mouseup', handleLongPressEnd);
                element.addEventListener('mouseleave', handleLongPressEnd);

                // Prevent context menu on long press
                element.addEventListener('contextmenu', (e) => {
                    if (longPressTimer) {
                        e.preventDefault();
                    }
                });

                // Visual hint on hover (subtle)
                element.style.cursor = 'default';
            });
        });

        // Close admin link when clicking outside
        document.addEventListener('click', (e) => {
            if (adminLinkVisible && !e.target.closest('#admin-gesture-link')) {
                hideAdminLink();
            }
        });

        // Close on Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && adminLinkVisible) {
                hideAdminLink();
            }
        });
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
