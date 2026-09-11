/**
 * When fullscreen mode is preferred (localStorage + cookie), load internal
 * show_page.php links via fetch + swap so the document does not unload — fullscreen stays on.
 */
(function () {
  var navLock = false;

  function resolveUrl(href) {
    try {
      return new URL(href, window.location.href);
    } catch (e) {
      return null;
    }
  }

  function isSpaMode() {
    return window.cmsFullscreen && typeof window.cmsFullscreen.isSpaActive === 'function' && window.cmsFullscreen.isSpaActive();
  }

  function isShowPageUrl(u) {
    if (!u || u.origin !== window.location.origin) return false;
    return u.pathname.indexOf('show_page.php') !== -1;
  }

  function shouldInterceptNav(e, a) {
    if (!a || !isSpaMode()) return false;
    if (e.defaultPrevented) return false;
    if (e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return false;
    if (a.hasAttribute('data-no-spa')) return false;
    if (a.getAttribute('target') === '_blank') return false;
    var href = a.getAttribute('href');
    if (!href || href === '#' || href.indexOf('javascript:') === 0) return false;
    var u = resolveUrl(href);
    if (!u || !isShowPageUrl(u)) return false;
    if (u.pathname.indexOf('operation.php') !== -1) return false;
    if (u.pathname.indexOf('logout.php') !== -1) return false;
    return true;
  }

  function executeScripts(container) {
    if (!container) return;
    var list = container.querySelectorAll('script');
    Array.prototype.slice.call(list).forEach(function (oldScript) {
      var s = document.createElement('script');
      for (var i = 0; i < oldScript.attributes.length; i++) {
        var attr = oldScript.attributes[i];
        s.setAttribute(attr.name, attr.value);
      }
      if (oldScript.src) {
        s.src = oldScript.src;
      } else {
        s.textContent = oldScript.textContent;
      }
      oldScript.parentNode.removeChild(oldScript);
      document.body.appendChild(s);
    });
  }

  function clearSidebarActive() {
    document.querySelectorAll('.main-sidebar .nav-link.active').forEach(function (el) {
      el.classList.remove('active');
    });
    document.querySelectorAll('.main-sidebar .nav-item.menu-open').forEach(function (el) {
      el.classList.remove('menu-is-opening', 'menu-open');
    });
  }

  function applyActiveToLink(matched) {
    matched.classList.add('active');
    var tree = matched.closest('.nav-treeview');
    if (tree) {
      var parentItem = tree.closest('.nav-item');
      if (parentItem) {
        parentItem.classList.add('menu-is-opening', 'menu-open');
        var parentLink = parentItem.querySelector(':scope > a.nav-link');
        if (parentLink) parentLink.classList.add('active');
      }
    }
  }

  function updateSidebarActive(fullUrl) {
    clearSidebarActive();
    var exactHref = '';
    try {
      exactHref = new URL(fullUrl, window.location.href).href;
    } catch (e) {}
    if (exactHref) {
      var exact = null;
      document.querySelectorAll('.main-sidebar a[href*="show_page.php"]').forEach(function (a) {
        try {
          if (a.href === exactHref) exact = a;
        } catch (e) {}
      });
      if (exact) {
        applyActiveToLink(exact);
        if (typeof window.cmsScrollSidebarActiveIntoView === 'function') {
          window.cmsScrollSidebarActiveIntoView();
        }
        return;
      }
    }
    var u;
    try {
      u = new URL(fullUrl, window.location.href);
    } catch (e) {
      return;
    }
    var curModule = (u.searchParams.get('module') || '').toLowerCase();
    if (curModule === 'home') curModule = 'dashboard';
    var curPage = (u.searchParams.get('page') || '').toLowerCase();
    var candidates = [];
    document.querySelectorAll('.main-sidebar a[href*="show_page.php"]').forEach(function (a) {
      var lu;
      try {
        lu = new URL(a.href);
      } catch (e) {
        return;
      }
      var m = (lu.searchParams.get('module') || '').toLowerCase();
      if (m === 'home') m = 'dashboard';
      var p = (lu.searchParams.get('page') || '').toLowerCase();
      if (m === curModule && p === curPage) {
        candidates.push(a);
      }
    });
    if (candidates.length === 0 && curPage) {
      document.querySelectorAll('.main-sidebar a[href*="show_page.php"]').forEach(function (a) {
        try {
          var lu = new URL(a.href);
          var m = (lu.searchParams.get('module') || '').toLowerCase();
          if (m === 'home') m = 'dashboard';
          if (m === curModule && !lu.searchParams.get('page')) {
            candidates.push(a);
          }
        } catch (e) {}
      });
    }
    var matched = candidates.length ? candidates[candidates.length - 1] : null;
    if (matched) {
      applyActiveToLink(matched);
      if (typeof window.cmsScrollSidebarActiveIntoView === 'function') {
        window.cmsScrollSidebarActiveIntoView();
      }
    }
  }

  function destroyDataTables() {
    if (!window.jQuery || !jQuery.fn.DataTable) return;
    try {
      jQuery('.dataTable, #dataTable, table.dataTable').each(function () {
        if (jQuery.fn.DataTable.isDataTable(this)) {
          jQuery(this).DataTable().destroy();
        }
      });
    } catch (e) {}
  }

  function afterSwap() {
    destroyDataTables();
    if (window.jQuery) {
      try {
        if (jQuery('#dataTable').length && jQuery.fn.DataTable) {
          jQuery('#dataTable').DataTable();
        }
      } catch (e) {}
      try {
        if (typeof window.cmsSelect2BaseOptions === 'function') {
          jQuery('.select2').select2(window.cmsSelect2BaseOptions());
          jQuery('.select2bs4').select2(
            window.cmsSelect2BaseOptions({ theme: 'bootstrap4' })
          );
          if (typeof window.cmsInitSelectSingle === 'function') {
            window.cmsInitSelectSingle(jQuery(document));
          } else {
            jQuery('.select-single').select2(
              window.cmsSelect2BaseOptions({
                theme: 'classic',
                width: '100%',
                placeholder: 'Select',
              })
            );
          }
          jQuery('.select-single-tag').select2(
            window.cmsSelect2BaseOptions({
              theme: 'classic',
              tags: true,
              width: '100%',
              placeholder: 'Select or type to Add',
            })
          );
        } else {
          jQuery('.select2').select2({ theme: 'bootstrap4' });
        }
      } catch (e) {}
    }
    window.dispatchEvent(new CustomEvent('cms:contentLoaded', { detail: {} }));
    window.scrollTo({ top: 0, left: 0, behavior: 'auto' });
  }

  function navigateSpa(url, opts) {
    opts = opts || {};
    var doPush = opts.push !== false;
    var wrapper = document.querySelector('.wrapper > .content-wrapper');
    if (!wrapper) {
      window.location.href = url;
      return;
    }
    if (navLock) return;
    navLock = true;
    wrapper.classList.add('cms-spa-loading');

    fetch(url, {
      credentials: 'same-origin',
      headers: {
        Accept: 'text/html',
        'X-Requested-With': 'XMLHttpRequest',
      },
    })
      .then(function (r) {
        if (!r.ok) throw new Error('http');
        return r.text();
      })
      .then(function (html) {
        var doc = new DOMParser().parseFromString(html, 'text/html');
        if (doc.body && doc.body.classList.contains('login-page')) {
          window.location.assign(url);
          return;
        }
        var nw = doc.querySelector('.wrapper > .content-wrapper');
        if (!nw) {
          window.location.assign(url);
          return;
        }
        var nu = resolveUrl(url);
        if (!nu || nu.origin !== window.location.origin) {
          window.location.assign(url);
          return;
        }
        wrapper.replaceWith(nw);
        var inserted = document.querySelector('.wrapper > .content-wrapper');
        executeScripts(inserted);
        if (doPush) {
          history.pushState({ cmsSpa: true }, '', url);
        }
        if (doc.title) {
          document.title = doc.title;
        }
        updateSidebarActive(url);
        afterSwap();
      })
      .catch(function () {
        window.location.assign(url);
      })
      .finally(function () {
        navLock = false;
        var w = document.querySelector('.wrapper > .content-wrapper');
        if (w) w.classList.remove('cms-spa-loading');
      });
  }

  function initSpaNav() {
    if (!document.body || !document.body.classList.contains('cms-admin')) return;
    if (!window.cmsFullscreen) return;

    document.addEventListener(
      'click',
      function (e) {
        var a = e.target.closest && e.target.closest('a');
        if (!shouldInterceptNav(e, a)) return;
        e.preventDefault();
        navigateSpa(a.href, { push: true });
      },
      true
    );

    window.addEventListener('popstate', function () {
      if (!isSpaMode()) return;
      navigateSpa(window.location.href, { push: false });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initSpaNav);
  } else {
    initSpaNav();
  }
})();
