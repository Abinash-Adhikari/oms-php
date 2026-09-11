(function () {
  var STORAGE_MODE = 'cmsThemeMode';
  var STORAGE_ACCENT = 'cmsThemeAccent';
  var DEFAULT_MODE = 'light';
  var DEFAULT_ACCENT = 'emerald';

  var PALETTES = {
    blue: '#3b82f6',
    emerald: '#10b981',
    purple: '#8b5cf6',
    rose: '#f43f5e',
    amber: '#f59e0b',
    indigo: '#6366f1',
  };

  function getStored(key, fallback) {
    try {
      var v = localStorage.getItem(key);
      return v || fallback;
    } catch (e) {
      return fallback;
    }
  }

  function setStored(key, val) {
    try {
      localStorage.setItem(key, val);
    } catch (e) {}
  }

  var STORAGE_FULLSCREEN = 'cmsFullscreenPreferred';

  function readFullscreenCookie() {
    try {
      var m = document.cookie.match(/(?:^|; )cmsFullscreenPreferred=([^;]*)/);
      return m ? decodeURIComponent(m[1].trim()) : '';
    } catch (e) {
      return '';
    }
  }

  function getFullscreenPreference() {
    try {
      var ls = localStorage.getItem(STORAGE_FULLSCREEN);
      if (ls === '1' || ls === '0') return ls;
    } catch (e) {}
    var c = readFullscreenCookie();
    if (c === '1' || c === '0') return c;
    return '0';
  }

  function setFullscreenPreference(val) {
    var v = val === '1' ? '1' : '0';
    try {
      localStorage.setItem(STORAGE_FULLSCREEN, v);
    } catch (e) {}
    try {
      document.cookie =
        'cmsFullscreenPreferred=' +
        v +
        '; path=/; max-age=' +
        86400 * 365 +
        '; SameSite=Lax';
    } catch (e) {}
  }

  function applyToDocument(mode, accent) {
    var m = mode === 'dark' ? 'dark' : 'light';
    var a = PALETTES[accent] ? accent : DEFAULT_ACCENT;
    document.documentElement.setAttribute('data-theme', m);
    document.documentElement.setAttribute('data-accent', a);
    setStored(STORAGE_MODE, m);
    setStored(STORAGE_ACCENT, a);

    // Sync the legacy SB-Tech CSS system on the same attributes + notify
    // chart/widget listeners so both theme engines stay in lockstep.
    document.documentElement.setAttribute('data-mode', m);
    document.documentElement.setAttribute('data-accent', a);
    try {
      document.dispatchEvent(new CustomEvent('themechange', {
        detail: { mode: m, accent: a }
      }));
    } catch (e) {}

    var body = document.body;
    if (body) {
      if (m === 'dark') body.classList.add('dark-mode');
      else body.classList.remove('dark-mode');
    }

    document.querySelectorAll('.cms-mode-btn').forEach(function (btn) {
      btn.classList.toggle('active', btn.getAttribute('data-mode') === m);
    });
    document.querySelectorAll('.cms-palette-item').forEach(function (item) {
      var sel = item.getAttribute('data-accent') === a;
      item.classList.toggle('selected', sel);
      var chk = item.querySelector('.cms-palette-check');
      if (chk) chk.classList.toggle('d-none', !sel);
    });
  }

  function initThemeControls() {
    var modeLight = document.getElementById('cmsThemeModeLight');
    var modeDark = document.getElementById('cmsThemeModeDark');
    if (modeLight) {
      modeLight.addEventListener('click', function () {
        applyToDocument('light', document.documentElement.getAttribute('data-accent') || DEFAULT_ACCENT);
      });
    }
    if (modeDark) {
      modeDark.addEventListener('click', function () {
        applyToDocument('dark', document.documentElement.getAttribute('data-accent') || DEFAULT_ACCENT);
      });
    }

    document.querySelectorAll('.cms-palette-item').forEach(function (item) {
      item.addEventListener('click', function () {
        var acc = item.getAttribute('data-accent');
        if (acc) {
          applyToDocument(document.documentElement.getAttribute('data-theme') || DEFAULT_MODE, acc);
        }
      });
    });
  }

  function filterSidebarMenu(q) {
    q = (q || '').toLowerCase().trim();

    var menu = document.querySelector('.cms-sidebar-menu');
    if (!menu) return;

    var items = menu.querySelectorAll(':scope > li.nav-item');
    items.forEach(function (li) {
      var hay = (li.getAttribute('data-search') || '').toLowerCase();
      var show = !q || hay.indexOf(q) !== -1;
      li.style.display = show ? '' : 'none';
    });

    var headers = menu.querySelectorAll(':scope > li.nav-header.cms-sidebar-section');
    headers.forEach(function (header) {
      var el = header.nextElementSibling;
      var any = false;
      while (el && !el.classList.contains('nav-header')) {
        if (el.classList.contains('nav-item') && el.style.display !== 'none') {
          any = true;
          break;
        }
        el = el.nextElementSibling;
      }
      header.style.display = q && !any ? 'none' : '';
    });
  }

  function initSidebarSearch() {
    var input = document.getElementById('cmsSidebarSearch');
    if (!input) return;
    input.addEventListener('input', function () {
      filterSidebarMenu(input.value);
    });
  }

  function initCollapsedSidebarActiveMenuOnly() {
    var sidebar = document.querySelector('.main-sidebar');
    var menu = document.querySelector('.cms-sidebar-menu');
    if (!sidebar || !menu) return;

    function syncActiveBranchOnly(e) {
      if (!document.body.classList.contains('sidebar-collapse')) return;
      var activeBranch = menu.querySelector(':scope > .nav-item[data-cms-active-branch="1"]');
      var eventTarget = e && e.target && e.target.nodeType === 1 ? e.target : null;

      // Clicking / focusing a submodule must not collapse its parent before navigation.
      // focusin previously ran this sync and removed menu-open from School Management
      // (etc.), so the child link disappeared and the click hit the parent javascript:void(0) toggle.
      if (
        eventTarget &&
        eventTarget.closest &&
        eventTarget.closest('.nav-treeview a.nav-link, .nav-treeview .nav-link')
      ) {
        return;
      }

      menu.querySelectorAll(':scope > .nav-item.menu-open').forEach(function (li) {
        if (activeBranch && li === activeBranch) return;
        if (eventTarget && li.contains(eventTarget)) return;
        if (activeBranch && li !== activeBranch) {
          li.classList.remove('menu-open', 'menu-is-opening');
        }
      });
      if (activeBranch) {
        activeBranch.classList.add('menu-open');
      }
    }

    // mouseenter only: re-peek active branch when opening the collapsed rail.
    // Do not use focusin — that fires on submodule clicks and closes the wrong menu.
    sidebar.addEventListener('mouseenter', syncActiveBranchOnly);
  }

  function initSidebarCollapseSync() {
    var body = document.body;
    if (!body || typeof MutationObserver === 'undefined') return;
    var hadCollapse = body.classList.contains('sidebar-collapse');
    var obs = new MutationObserver(function () {
      var now = body.classList.contains('sidebar-collapse');
      if (now && !hadCollapse) {
        var input = document.getElementById('cmsSidebarSearch');
        if (input && input.value) {
          input.value = '';
          filterSidebarMenu('');
        }
      }
      hadCollapse = now;
    });
    obs.observe(body, { attributes: true, attributeFilter: ['class'] });
  }

  /* ---------- Fullscreen preference (localStorage + cookie; persists across pages & refresh) ---------- */

  function getFullscreenElement() {
    return (
      document.fullscreenElement ||
      document.webkitFullscreenElement ||
      document.msFullscreenElement ||
      null
    );
  }

  function isAdminAppShell() {
    return document.body && document.body.classList.contains('cms-admin');
  }

  function requestAppFullscreen() {
    var el = document.documentElement;
    var req =
      el.requestFullscreen ||
      el.webkitRequestFullscreen ||
      el.msRequestFullscreen;
    if (!req) {
      return Promise.reject(new Error('no-api'));
    }
    try {
      return Promise.resolve(req.call(el));
    } catch (err) {
      return Promise.reject(err);
    }
  }

  function armFirstInteractionFullscreen() {
    if (!isAdminAppShell()) return;
    if (getFullscreenPreference() !== '1') return;
    if (getFullscreenElement()) return;
    var once = function () {
      document.removeEventListener('pointerdown', once, true);
      requestAppFullscreen().catch(function () {});
    };
    document.addEventListener('pointerdown', once, true);
  }

  function tryRestoreFullscreenOnLoad() {
    if (!isAdminAppShell()) return;
    if (getFullscreenPreference() !== '1') return;
    requestAppFullscreen()
      .then(function () {})
      .catch(function () {
        armFirstInteractionFullscreen();
      });
  }

  function persistFullscreenPreferenceFromClick() {
    setTimeout(function () {
      var on = !!getFullscreenElement();
      setFullscreenPreference(on ? '1' : '0');
    }, 400);
  }

  function initFullscreenPersistence() {
    document.addEventListener(
      'keydown',
      function (e) {
        if (e.key !== 'Escape') return;
        if (getFullscreenElement()) {
          setFullscreenPreference('0');
        }
      },
      true
    );

    document.addEventListener('click', function (e) {
      var t = e.target.closest && e.target.closest('[data-widget="fullscreen"]');
      if (!t) return;
      persistFullscreenPreferenceFromClick();
    });

    tryRestoreFullscreenOnLoad();

    window.addEventListener('pageshow', function () {
      if (!isAdminAppShell()) return;
      if (getFullscreenPreference() !== '1') return;
      if (!getFullscreenElement()) {
        tryRestoreFullscreenOnLoad();
      }
    });
  }

  /** Select2 dropdown z-index when document is in fullscreen (complements cmsSelect2BaseOptions). */
  function initSelect2FullscreenFix() {
    if (typeof window.jQuery === 'undefined' || !jQuery.fn.select2) return;
    jQuery(document).on('select2:open', function () {
      if (!getFullscreenElement()) return;
      jQuery('.select2-dropdown').css('z-index', '2147483646');
    });
  }

  /**
   * AdminLTE Layout applies OverlayScrollbars (or overflow-y:auto) on `.sidebar`.
   * Our shell scrolls `.cms-sidebar-scroll` instead (search + user stay fixed),
   * so unwrap OS and clear the inline overflow so CSS can win.
   */
  function restoreCmsSidebarScroll() {
    var el = document.querySelector('.main-sidebar .sidebar');
    if (!el) return;

    if (typeof window.jQuery !== 'undefined' && typeof jQuery.fn.overlayScrollbars === 'function') {
      try {
        var osInstance = jQuery(el).overlayScrollbars();
        if (osInstance && typeof osInstance.destroy === 'function') {
          osInstance.destroy();
        }
      } catch (e) {}
    }

    el.style.overflow = '';
    el.style.overflowY = '';
    el.style.overflowX = '';
  }

  function initCmsSidebarScrollGuard() {
    function run() {
      restoreCmsSidebarScroll();
    }

    if (typeof window.jQuery !== 'undefined') {
      // Run after AdminLTE Layout's own document-ready handler
      jQuery(function () {
        setTimeout(run, 0);
        jQuery(document).on(
          'collapsed.lte.treeview expanded.lte.treeview shown.lte.pushmenu collapsed.lte.pushmenu',
          function () {
            setTimeout(run, 0);
          }
        );
      });
    } else {
      setTimeout(run, 0);
    }
  }

  /**
   * Keep the active sidebar link in view inside .cms-sidebar-scroll.
   * Without this, deep modules (Library, Transport, …) land off-screen on refresh.
   */
  function scrollSidebarActiveIntoView() {
    var scroller = document.querySelector('.cms-sidebar-scroll');
    if (!scroller) return;

    var active =
      scroller.querySelector('.nav-treeview a.nav-link.active') ||
      scroller.querySelector('.cms-sidebar-menu > .nav-item > a.nav-link.active');
    if (!active) return;

    var branch = active.closest('.cms-nav-row');
    if (branch) {
      branch.classList.add('menu-open', 'menu-is-opening');
    }

    var scrollerRect = scroller.getBoundingClientRect();
    var activeRect = active.getBoundingClientRect();
    var offsetWithin = activeRect.top - scrollerRect.top + scroller.scrollTop;
    var target = offsetWithin - scroller.clientHeight / 2 + activeRect.height / 2;
    var max = Math.max(0, scroller.scrollHeight - scroller.clientHeight);
    scroller.scrollTop = Math.min(max, Math.max(0, target));
  }

  function initSidebarActiveScroll() {
    // Only on initial paint — do NOT re-scroll on unrelated treeview expands
    // (e.g. opening Academics while Library is active would yank the sidebar).
    requestAnimationFrame(function () {
      scrollSidebarActiveIntoView();
      setTimeout(scrollSidebarActiveIntoView, 50);
      setTimeout(scrollSidebarActiveIntoView, 200);
    });
  }

  window.cmsFullscreen = {
    getPreference: getFullscreenPreference,
    getElement: getFullscreenElement,
    request: requestAppFullscreen,
    isSpaActive: function () {
      return getFullscreenPreference() === '1' || !!getFullscreenElement();
    },
  };

  window.cmsApplyTheme = applyToDocument;
  window.cmsThemePalettes = PALETTES;
  window.cmsScrollSidebarActiveIntoView = scrollSidebarActiveIntoView;

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      applyToDocument(
        getStored(STORAGE_MODE, DEFAULT_MODE),
        getStored(STORAGE_ACCENT, DEFAULT_ACCENT)
      );
      initThemeControls();
      initSidebarSearch();
      initSidebarCollapseSync();
      initCollapsedSidebarActiveMenuOnly();
      initFullscreenPersistence();
      initSelect2FullscreenFix();
      initCmsSidebarScrollGuard();
      initSidebarActiveScroll();
    });
  } else {
    applyToDocument(
      getStored(STORAGE_MODE, DEFAULT_MODE),
      getStored(STORAGE_ACCENT, DEFAULT_ACCENT)
    );
    initThemeControls();
    initSidebarSearch();
    initSidebarCollapseSync();
    initCollapsedSidebarActiveMenuOnly();
    initFullscreenPersistence();
    initSelect2FullscreenFix();
    initCmsSidebarScrollGuard();
    initSidebarActiveScroll();
  }
})();
