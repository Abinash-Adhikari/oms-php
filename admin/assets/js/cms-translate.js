/**
 * Google Website Translator + custom language picker in the top navbar.
 * Preference persists via the googtrans cookie Google Translate already reads.
 */
(function () {
  var PAGE_LANG = 'en';

  function readGoogTrans() {
    try {
      var m = document.cookie.match(/(?:^|; )googtrans=([^;]*)/);
      if (!m) return PAGE_LANG;
      var parts = decodeURIComponent(m[1].trim()).split('/');
      return parts[2] || PAGE_LANG;
    } catch (e) {
      return PAGE_LANG;
    }
  }

  function clearGoogTrans() {
    var host = location.hostname;
    var expires = 'expires=Thu, 01 Jan 1970 00:00:00 GMT';
    var paths = ['/', ''];
    var domains = ['', host, '.' + host];
    // Parent domain (e.g. .example.com) — Google sometimes sets cookie there
    var parts = host.split('.');
    if (parts.length > 2) {
      domains.push('.' + parts.slice(-2).join('.'));
    }
    paths.forEach(function (path) {
      domains.forEach(function (domain) {
        var c = 'googtrans=;' + expires + ';path=' + (path || '/');
        if (domain) c += ';domain=' + domain;
        document.cookie = c;
      });
    });
  }

  function setGoogTrans(lang) {
    if (!lang || lang === PAGE_LANG) {
      clearGoogTrans();
      return;
    }
    var value = '/' + PAGE_LANG + '/' + lang;
    document.cookie =
      'googtrans=' + value + ';path=/;max-age=' + 86400 * 365 + ';SameSite=Lax';
  }

  function markActive(lang) {
    var $items = document.querySelectorAll('.cms-lang-item');
    for (var i = 0; i < $items.length; i++) {
      var el = $items[i];
      var isActive = el.getAttribute('data-lang') === lang;
      el.classList.toggle('selected', isActive);
      el.setAttribute('aria-pressed', isActive ? 'true' : 'false');
      var check = el.querySelector('.cms-lang-check');
      if (check) check.classList.toggle('d-none', !isActive);
    }
  }

  function switchLanguage(lang) {
    lang = lang || PAGE_LANG;
    if (lang === readGoogTrans()) return;
    setGoogTrans(lang);
    location.reload();
  }

  window.googleTranslateElementInit = function () {
    if (!window.google || !google.translate || !google.translate.TranslateElement) return;
    new google.translate.TranslateElement(
      {
        pageLanguage: PAGE_LANG,
        includedLanguages: 'en,ne,hi,zh-CN,ar,es,fr,de',
        autoDisplay: false,
        multilanguagePage: true,
      },
      'google_translate_element'
    );
  };

  function bindUi() {
    markActive(readGoogTrans());
    document.addEventListener('click', function (e) {
      var btn = e.target.closest && e.target.closest('.cms-lang-item');
      if (!btn) return;
      e.preventDefault();
      switchLanguage(btn.getAttribute('data-lang') || PAGE_LANG);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bindUi);
  } else {
    bindUi();
  }
})();
