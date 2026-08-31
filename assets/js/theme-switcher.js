/**
 * SB-Tech — Theme Switcher
 * Two dimensions: Light/Dark mode + Accent color.
 * Persisted in localStorage. Instant switching, no page reload.
 */
(function () {
    'use strict';

    const MODE_KEY = 'app_color_mode';
    const ACCENT_KEY = 'app_accent';
    const DEFAULT_MODE = 'light';
    const DEFAULT_ACCENT = 'blue';

    /** Accent color definitions — MUST match [data-accent=...] tokens in
     *  theme-variables.css (hex shown in the picker + colors broadcast to charts). */
    const ACCENTS = {
        blue:   { name: 'Blue',   hex: '#2563EB', primary: '#2563EB', secondary: '#DBEAFE' },
        emerald:{ name: 'Emerald', hex: '#059669', primary: '#059669', secondary: '#D1FAE5' },
        purple: { name: 'Purple', hex: '#7C3AED', primary: '#7C3AED', secondary: '#EDE9FE' },
        rose:   { name: 'Rose',   hex: '#E11D48', primary: '#E11D48', secondary: '#FFE4E6' },
        amber:  { name: 'Amber',  hex: '#D97706', primary: '#D97706', secondary: '#FEF3C7' },
        indigo: { name: 'Indigo', hex: '#4F46E5', primary: '#4F46E5', secondary: '#E0E7FF' }
    };
    /** Get stored or default values */
    function getMode() {
        try { return localStorage.getItem(MODE_KEY) || DEFAULT_MODE; }
        catch (e) { return DEFAULT_MODE; }
    }

    function getAccent() {
        try { return localStorage.getItem(ACCENT_KEY) || DEFAULT_ACCENT; }
        catch (e) { return DEFAULT_ACCENT; }
    }

    /** Apply mode (light/dark) to <html> */
    function applyMode(mode) {
        if (mode !== 'light' && mode !== 'dark') { mode = DEFAULT_MODE; }
        document.documentElement.setAttribute('data-mode', mode);
        try { localStorage.setItem(MODE_KEY, mode); } catch (e) { /* quota */ }

        // Update tab active states
        document.querySelectorAll('.ts-mode-tab').forEach(function (el) {
            el.classList.toggle('active', el.getAttribute('data-mode') === mode);
        });
    }

    /** Apply accent color to <html> */
    function applyAccent(accentId) {
        if (!ACCENTS[accentId]) { accentId = DEFAULT_ACCENT; }
        document.documentElement.setAttribute('data-accent', accentId);
        try { localStorage.setItem(ACCENT_KEY, accentId); } catch (e) { /* quota */ }

        // Update accent card active states
        document.querySelectorAll('.ts-accent-card').forEach(function (el) {
            el.classList.toggle('active', el.getAttribute('data-accent') === accentId);
        });

        // Dispatch custom event for charts/widgets
        document.dispatchEvent(new CustomEvent('themechange', {
            detail: { mode: getMode(), accent: accentId, accentData: ACCENTS[accentId] }
        }));
    }

    /** Build the theme switcher dropdown HTML */
    function buildDropdown() {
        const currentMode = getMode();
        const currentAccent = getAccent();

        let html = '';

        // Header
        html += '<div class="ts-header">';
        html += '  <h6>Theme</h6>';
        html += '  <p>Color mode and accent palette</p>';
        html += '</div>';

        // Mode tabs (Light / Dark)
        html += '<div class="ts-mode-tabs">';
        html += '  <button class="ts-mode-tab' + (currentMode === 'light' ? ' active' : '') + '" data-mode="light">';
        html += '    <i class="fas fa-sun"></i> Light';
        html += '  </button>';
        html += '  <button class="ts-mode-tab' + (currentMode === 'dark' ? ' active' : '') + '" data-mode="dark">';
        html += '    <i class="fas fa-moon"></i> Dark';
        html += '  </button>';
        html += '</div>';

        // Accent color grid (2 columns x 3 rows)
        html += '<div class="ts-accent-grid">';
        for (const [id, meta] of Object.entries(ACCENTS)) {
            const isActive = id === currentAccent;
            html += '<div class="ts-accent-card' + (isActive ? ' active' : '') + '" data-accent="' + id + '">';
            html += '  <div class="ts-accent-dots">';
            html += '    <span style="background:' + meta.primary + ';"></span>';
            html += '    <span style="background:' + meta.secondary + ';"></span>';
            html += '  </div>';
            html += '  <div class="ts-accent-info">';
            html += '    <div class="ts-accent-name">' + meta.name + '</div>';
            html += '    <div class="ts-accent-hex">' + meta.hex + '</div>';
            html += '  </div>';
            html += '  <i class="fas fa-check ts-accent-check"></i>';
            html += '</div>';
        }
        html += '</div>';

        return html;
    }

    /** Initialize the theme switcher */
    function init() {
        var container = document.getElementById('theme-switcher-container');
        if (!container) { return; }

        container.innerHTML = buildDropdown();

        // Mode tab clicks
        container.addEventListener('click', function (e) {
            var tab = e.target.closest('.ts-mode-tab');
            if (tab) {
                e.preventDefault();
                e.stopPropagation();
                applyMode(tab.getAttribute('data-mode'));
                return;
            }

            // Accent card clicks
            var card = e.target.closest('.ts-accent-card');
            if (card) {
                e.preventDefault();
                e.stopPropagation();
                applyAccent(card.getAttribute('data-accent'));
                return;
            }
        });
    }

    // Run on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Expose for programmatic use
    window.SBTechTheme = {
        getMode: getMode,
        getAccent: getAccent,
        applyMode: applyMode,
        applyAccent: applyAccent
    };
})();
