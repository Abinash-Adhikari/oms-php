<?php
/**
 * SB-Tech — shared JS includes (Smart-School stack ported wholesale).
 * jQuery + Bootstrap + AdminLTE + plugins loaded from local
 * admin/assets + admin/theme2 copies.
 */
?>
<!-- Organization name for JS -->
<script>var APP_ORG_NAME = <?= json_encode(office_display_name()) ?>;</script>

<!-- jQuery and jQuery UI -->
<script src="./assets/plugins/jquery/jquery.min.js"></script>
<script src="./assets/plugins/jquery-ui/jquery-ui.min.js"></script>
<script>
  $.widget.bridge('uibutton', $.ui.button);
</script>

<!-- Bootstrap -->
<script src="./assets/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>

<!-- AdminLTE -->
<script src="./assets/dist/js/adminlte.min.js"></script>
<script src="./assets/js/cms-theme.js?v=<?= @filemtime(__DIR__ . '/../assets/js/cms-theme.js') ?: '1' ?>"></script>
<script src="./assets/js/cms-translate.js?v=<?= @filemtime(__DIR__ . '/../assets/js/cms-translate.js') ?: '1' ?>"></script>
<script src="https://translate.google.com/translate_a/element.js?cb=googleTranslateElementInit" defer></script>
<script src="./assets/js/cms-spa.js"></script>

<!-- Plugins -->
<script src="./assets/plugins/moment/moment.min.js"></script>
<script src="./assets/plugins/overlayScrollbars/js/jquery.overlayScrollbars.min.js"></script>
<script>
  /**
   * AdminLTE Layout._fixHeight() applies OverlayScrollbars to .sidebar (and re-applies
   * on resize). That breaks our flex + native .cms-sidebar-scroll. Block OS on the
   * sidebar host permanently; keep native overflow on .cms-sidebar-scroll.
   */
  (function ($) {
    var nativeOs = $.fn.overlayScrollbars;
    if (typeof nativeOs === 'function') {
      $.fn.overlayScrollbars = function () {
        if (this.is('.main-sidebar > .sidebar, .main-sidebar .sidebar.cms-sidebar') ||
            this.closest('.main-sidebar > .sidebar').length) {
          return this;
        }
        return nativeOs.apply(this, arguments);
      };
      $.fn.overlayScrollbars.defaults = nativeOs.defaults;
    }

    function cmsEnableSidebarNativeScroll() {
      var $sidebar = $('.main-sidebar > .sidebar');
      if (!$sidebar.length) {
        return;
      }
      $sidebar.each(function () {
        var $el = $(this);
        try {
          if (typeof nativeOs === 'function') {
            var instance = nativeOs.call($el);
            if (instance && typeof instance.destroy === 'function') {
              instance.destroy();
            }
          }
        } catch (e) { /* ignore */ }
        // Drop AdminLTE inline height so CSS flex height wins
        $el.css({ height: '', maxHeight: '' });
      });
    }

    $(function () {
      cmsEnableSidebarNativeScroll();
      setTimeout(cmsEnableSidebarNativeScroll, 0);
      setTimeout(cmsEnableSidebarNativeScroll, 200);
    });
    $(window).on('resize', function () {
      cmsEnableSidebarNativeScroll();
    });
    $(document).on('click', '[data-widget="pushmenu"]', function () {
      setTimeout(cmsEnableSidebarNativeScroll, 50);
      setTimeout(cmsEnableSidebarNativeScroll, 250);
    });
  })(jQuery);
</script>
<script src="./assets/plugins/select2/js/select2.full.min.js"></script>
<script src="./assets/plugins/inputmask/jquery.inputmask.min.js"></script>
<script src="./assets/plugins/daterangepicker/daterangepicker.js"></script>
<script src="./assets/plugins/tempusdominus-bootstrap-4/js/tempusdominus-bootstrap-4.min.js"></script>
<script src="./assets/plugins/dropzone/min/dropzone.min.js"></script>
<script src="./assets/plugins/fullcalendar/main.js"></script>
<script src="./assets/plugins/sweetalert2/sweetalert2.min.js"></script>
<script src="./assets/plugins/toastr/toastr.min.js"></script>
<script src="./assets/plugins/summernote/summernote-bs4.min.js"></script>
<script src="theme2/assets/plugins/tinymce/tinymce.min.js"></script>

<!-- Date/Time Pickers -->
<script src="theme2/assets/plugins/bootstrap-datepicker/bootstrap-datepicker.js"></script>
<script src="theme2/assets/plugins/bootstrap-daterangepicker/daterangepicker.js"></script>
<script src="theme2/assets/plugins/bootstrap-datetimepicker/bootstrap-datetimepicker.min.js"></script>
<!-- Nepali B.S. picker v3.7 (local; NepaliFunctions BS2AD / AD2BS) -->
<script src="theme2/assets/js/nepali.datepicker.v3.7.min.js"></script>

<!-- DataTables -->
<script src="./assets/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="./assets/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="./assets/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="./assets/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>

<!-- Utility Libraries -->
<script src="./assets/javascript/axios.min.js"></script>
<script src="./assets/javascript/sha512.js"></script>
<script src="./functions/formValidate.js"></script>

<script>
  /**
   * Global CSRF protection for the admin UI.
   * - Exposes the server token as window.CMS_CSRF_TOKEN.
   * - Injects a hidden "csrf_token" input into every form[method=POST] so no
   *   individual form markup needs editing.
   * - Adds the token to every Axios POST/PUT/PATCH request via an interceptor.
   * - Adds the token to $.ajax POST/PUT/PATCH and fetch() POST requests.
   * The server enforces the token in operation.php and ajax.php (verifyCsrf).
   */
  (function () {
    var TOKEN = <?php echo json_encode(csrfToken()); ?>;
    window.CMS_CSRF_TOKEN = TOKEN;
    var FIELD = '<input type="hidden" name="csrf_token" value="' + TOKEN + '">';

    function injectFormToken(form) {
      if (form && form.getAttribute('method') && form.getAttribute('method').toUpperCase() === 'POST' &&
          !form.querySelector('input[name="csrf_token"]')) {
        var wrap = document.createElement('div');
        wrap.style.display = 'none';
        wrap.innerHTML = FIELD;
        form.appendChild(wrap.firstChild);
      }
    }

    function onReady() {
      document.querySelectorAll('form').forEach(injectFormToken);
    }
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', onReady);
    } else {
      onReady();
    }
    // Cover forms injected dynamically after DOM ready.
    document.addEventListener('submit', function (e) {
      var f = e.target && e.target.closest ? e.target.closest('form') : null;
      if (f) { injectFormToken(f); }
    });

    // Axios interceptor (global default instance used across modules).
    if (typeof axios !== 'undefined') {
      axios.interceptors.request.use(function (config) {
        if (config.method && ['post', 'put', 'patch'].indexOf(String(config.method).toLowerCase()) !== -1) {
          config.headers = config.headers || {};
          if (!config.headers['X-CSRF-Token']) {
            config.headers['X-CSRF-Token'] = TOKEN;
          }
          if (config.data && typeof config.data === 'object' && !(config.data instanceof FormData) &&
              !(config.data instanceof URLSearchParams) && config.data.csrf_token === undefined) {
            config.data['csrf_token'] = TOKEN;
          }
        }
        return config;
      }, function (error) { return Promise.reject(error); });
    }

    // jQuery $.ajax interceptor.
    if (typeof jQuery !== 'undefined') {
      jQuery(document).ajaxSend(function (event, xhr, settings) {
        var m = String(settings.type || 'GET').toUpperCase();
        if (['POST', 'PUT', 'PATCH'].indexOf(m) !== -1) {
          xhr.setRequestHeader('X-CSRF-Token', TOKEN);
          if (settings.data && typeof settings.data === 'string' && settings.data.indexOf('csrf_token=') === -1 &&
              settings.contentType && String(settings.contentType).indexOf('json') === -1) {
            settings.data = settings.data + (settings.data ? '&' : '') + 'csrf_token=' + encodeURIComponent(TOKEN);
          }
        }
      });
    }

    // fetch() interceptor (no-op when Headers polyfill is unavailable).
    var nativeFetch = window.fetch;
    if (typeof nativeFetch === 'function' && typeof Headers === 'function') {
      window.fetch = function (input, init) {
        init = init || {};
        var method = String(init.method || (typeof input !== 'string' && input && input.method) || 'GET').toUpperCase();
        if (['POST', 'PUT', 'PATCH'].indexOf(method) !== -1) {
          var headers = new Headers(init.headers || (typeof input !== 'string' && input && input.headers) || {});
          if (!headers.has('X-CSRF-Token')) {
            headers.set('X-CSRF-Token', TOKEN);
          }
          init.headers = headers;
        }
        return nativeFetch.call(this, input, init);
      };
    }
  })();
</script>

<script>
  /**
   * Select2 + browser fullscreen: dropdowns default to body; use the .wrapper
   * as dropdownParent and raise z-index (cms-theme.css + select2:open) so lists stay usable.
   */
  function cmsSelect2DropdownParent() {
    if (typeof jQuery === 'undefined') {
      return null;
    }
    var $w = jQuery('.wrapper');
    return $w.length ? $w : jQuery(document.body);
  }

  function cmsSelect2BaseOptions(extra) {
    if (typeof jQuery === 'undefined') {
      return extra || {};
    }
    return jQuery.extend({ dropdownParent: cmsSelect2DropdownParent() }, extra || {});
  }

  /** Resolve placeholder from data-placeholder / placeholder attr before falling back. */
  function cmsSelect2Placeholder($el, fallback) {
    var ph = $el.attr('data-placeholder') || $el.data('placeholder') || $el.attr('placeholder');
    if (ph === undefined || ph === null || String(ph).trim() === '') {
      return fallback;
    }
    return String(ph);
  }

  /** Init .select-single (incl. multiple) with full placeholder text visible. */
  function cmsInitSelectSingle($root) {
    if (typeof jQuery === 'undefined' || !jQuery.fn.select2) {
      return;
    }
    var $scope = ($root && $root.length) ? $root : jQuery(document);
    $scope.find('select.select-single').addBack('select.select-single').each(function() {
      var $el = jQuery(this);
      // Skip already-initialized instances (page-specific ajax/custom configs)
      if ($el.hasClass('select2-hidden-accessible')) {
        return;
      }
      $el.select2(
        cmsSelect2BaseOptions({
          theme: 'classic',
          width: '100%',
          placeholder: cmsSelect2Placeholder($el, 'Select'),
        })
      );
    });
  }

  window.cmsSelect2BaseOptions = cmsSelect2BaseOptions;
  window.cmsSelect2DropdownParent = cmsSelect2DropdownParent;
  window.cmsSelect2Placeholder = cmsSelect2Placeholder;
  window.cmsInitSelectSingle = cmsInitSelectSingle;

  /** Normalize YYYY-M-D / YYYY-MM-DD to zero-padded YYYY-MM-DD for string compare. */
  function appNormalizeYmd(v) {
    v = String(v || '').trim();
    var m = v.match(/^(\d{4})-(\d{1,2})-(\d{1,2})$/);
    if (!m) {
      return '';
    }
    return m[1] + '-' + ('0' + m[2]).slice(-2) + '-' + ('0' + m[3]).slice(-2);
  }

  /**
   * True when picker value is after "today" in the office calendar (A.D. or B.S.).
   * Uses APP_TODAY_PICKER from head (same format as datepicker fields).
   */
  function appIsPickerDateInFuture(dateStr) {
    var norm = appNormalizeYmd(dateStr);
    if (!norm) {
      return false;
    }
    var today = appNormalizeYmd(window.APP_TODAY_PICKER) || appNormalizeYmd(new Date().toISOString().slice(0, 10));
    return today !== '' && norm > today;
  }

  function appAssertVoucherDateNotFuture(inputId) {
    var el = document.getElementById(inputId || 'date');
    if (!el) {
      return true;
    }
    if (appIsPickerDateInFuture(el.value)) {
      alert('Voucher date cannot be in the future. Please select a valid date.');
      el.focus();
      return false;
    }
    return true;
  }

  window.appNormalizeYmd = appNormalizeYmd;
  window.appIsPickerDateInFuture = appIsPickerDateInFuture;
  window.appAssertVoucherDateNotFuture = appAssertVoucherDateNotFuture;

  function appDestroyFieldDatepicker($el) {
    var node = $el && $el[0];
    if (!node) return;
    if ($el.data('datepicker')) {
      try {
        $el.bootstrapdatepicker('destroy');
      } catch (e) {}
    }
    if (typeof node.nepaliDatePicker === 'function') {
      try {
        node.nepaliDatePicker('remove');
      } catch (e) {}
    }
    $el.removeData('app-dp-inited');
  }

  function appPrimeBsFieldFromAd($el) {
    // Intentionally no-op. Server already fills B.S. via adToBs()
    // from tbl_calendar. NepaliFunctions.AD2BS can be ±1 day vs that table.
    return;
  }

  function appBsPickerValueToAdYmd(v) {
    if (typeof NepaliFunctions === 'undefined') return v;
    v = (v || '').trim();
    if (!/^\d{4}-\d{1,2}-\d{1,2}$/.test(v)) return v;
    var o = NepaliFunctions.ConvertToDateObject(v, 'YYYY-MM-DD');
    if (!o || !NepaliFunctions.ValidateBsDate(o)) return v;
    var ad = NepaliFunctions.BS2AD(o);
    return NepaliFunctions.ConvertDateFormat(ad, 'YYYY-MM-DD');
  }

  /**
   * Before POST (A.D. office only): convert any compact B.S. Y-m-d left in pickers to A.D.
   * B.S. office: do NOT convert here — post the picker B.S. value and let PHP
   * normalizeDateInput()/bsToAd use tbl_calendar (source of truth).
   */
  function appConvertNepaliCalendarInputsToAd($form) {
    if (typeof NepaliFunctions === 'undefined') return;
    if ((window.APP_CALENDAR_MODE || 'BS') !== 'AD') {
      return;
    }
    $form.find('input.ndp-nepali-calendar, input.datepicker').each(function() {
      var $el = $(this);
      var raw = ($el.val() || '').trim();
      if (!raw) return;
      var ad = appBsPickerValueToAdYmd(raw);
      if (ad !== raw) {
        $el.val(ad);
      }
    });
  }

  window.initAppDatepickers = function($root) {
    var useAd = (window.APP_CALENDAR_MODE || 'BS') === 'AD';
    var $scope = ($root && $root.length) ? $root : $(document);
    $scope.find('input.datepicker').each(function() {
      var el = this;
      var $el = $(el);
      if ($el.data('app-dp-inited')) return;
      appDestroyFieldDatepicker($el);

      if (window.APP_DATE_INPUT_PLACEHOLDER && !$el.is('[data-datepicker-placeholder-off]')) {
        $el.attr('placeholder', window.APP_DATE_INPUT_PLACEHOLDER);
      }

      if (useAd) {
        $el.bootstrapdatepicker({
          format: 'yyyy-mm-dd',
          autoclose: true,
          todayHighlight: true
        });
      } else {
        if (typeof NepaliFunctions === 'undefined' || typeof el.nepaliDatePicker !== 'function') return;
        appPrimeBsFieldFromAd($el);
        // A stray value the plugin can't parse as a valid B.S. date (e.g. an
        // A.D. date left behind by browser form-autofill matching this
        // field's shared name="date[]" across re-renders) crashes the
        // plugin's init with an uncaught exception, which aborts this whole
        // .each() loop and leaves every field after it uninitialized. Clear
        // anything that doesn't actually validate as a B.S. date before init.
        var curVal = ($el.val() || '').trim();
        if (curVal) {
          try {
            var bsObj = NepaliFunctions.ConvertToDateObject(curVal, 'YYYY-MM-DD');
            if (!bsObj || !NepaliFunctions.ValidateBsDate(bsObj)) {
              $el.val('');
            }
          } catch (e) {
            $el.val('');
          }
        }
        try {
          el.nepaliDatePicker({
            dateFormat: 'YYYY-MM-DD',
            ndpYear: true,
            ndpMonth: true,
            language: 'nepali',
            readOnlyInput: !!$el.prop('readonly'),
            onChange: function() {
              $el.trigger('change');
              $el.trigger('input');
            }
          });
        } catch (e) {
          console.warn('nepaliDatePicker init failed for', el.id, e);
          $el.val('');
        }
      }
      $el.data('app-dp-inited', true);
    });
  };

  $(function() {
    // Initialize DataTables
    $('.dataTable, #table_responsive').DataTable({
      "paging": true,
      "lengthChange": false,
      "searching": false,
      "ordering": true,
      "info": true,
      "autoWidth": false,
      "responsive": true
    });

    // Initialize Summernote
    $('.summernote').summernote({
      height: 300,
      codeviewFilter: false,
      codeviewIframeFilter: true
    });

    // Initialize TinyMCE
    tinymce.init({
      mode: "specific_textareas",
      editor_selector: "texteditor",
      deprecation_warnings: false,
      content_style: 'body { font-size: 12px; }',
      width: '100%',
      height: 250,
      forced_root_block: 'div',
      convert_urls: false,
      plugins: [
        "advlist autolink link image lists charmap print preview hr anchor pagebreak",
        "searchreplace wordcount visualblocks visualchars insertdatetime media nonbreaking",
        "table contextmenu directionality emoticons paste textcolor responsivefilemanager code"
      ],
      toolbar1: "undo redo | bold italic underline | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | styleselect | fontsizeselect",
    });

    // Initialize Select2
    $('.select2').select2(cmsSelect2BaseOptions());
    $('.select2bs4').select2(
      cmsSelect2BaseOptions({
        theme: 'bootstrap4',
      })
    );
    cmsInitSelectSingle($(document));
    $('.select-single-tag').select2(
      cmsSelect2BaseOptions({
        theme: 'classic',
        tags: true,
        width: '100%',
        placeholder: 'Select or type to Add',
      })
    );

    // Date Range Picker (Gregorian only; B.S. ranges use paired nepali datepickers in markup)
    if ((window.APP_CALENDAR_MODE || 'BS') === 'AD') {
      $('.daterange, input.daterangepicker').daterangepicker({
        ranges: {
          'Today': [moment(), moment()],
          'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
          'Last 7 Days': [moment().subtract(6, 'days'), moment()],
          'Last 30 Days': [moment().subtract(29, 'days'), moment()],
          'This Month': [moment().startOf('month'), moment().endOf('month')],
          'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
        },
        startDate: moment().subtract(29, 'days'),
        endDate: moment()
      });
    }

    // DateTime Pickers
    $('.datetimepicker').attr('data-toggle', 'datetimepicker').datetimepicker({
      format: 'YYYY-MM-DD h:mm A',
      useCurrent: true,
      stepping: 5
    });

    window.initAppDatepickers($(document));

    // Select2: put caret in search box as soon as dropdown opens (all instances)
    $(document).on('select2:open', function() {
      setTimeout(function() {
        var field = document.querySelector(
          '.select2-container--open .select2-search__field, .select2-container--open .select2-search input'
        );
        if (field) {
          field.focus();
        }
      }, 0);
    });

    $(document).on('submit', 'form', function() {
      appConvertNepaliCalendarInputsToAd($(this));
    });

    $('.timepicker, .timepicker-from, .timepicker-to').datetimepicker({
      useCurrent: true,
      format: 'h:mm A'
    });

    // Disable modal focus enforcement
    $.fn.modal.Constructor.prototype._enforceFocus = function() {};
  });

  /**
   * No-op stub: voucher pages call renderBSCalendar on fiscal-year-datepicker show.
   */
  function renderBSCalendar() {}

  // Utility Functions
  function arrayToString(array, separatedWidth = ',') {
    return array.map((val, ind) =>
      ind === array.length - 1 ? val : val + separatedWidth
    ).join('');
  }

  function getNepaliDate({
    inputId,
    outputId
  }) {
    const engDate = document.getElementById(inputId);
    const nepDate = document.getElementById(outputId);
    if (!engDate || !nepDate) return;

    const raw = (engDate.value || '').trim();
    if ((window.APP_CALENDAR_MODE || 'BS') === 'BS' && typeof NepaliFunctions !== 'undefined' && raw) {
      const o = NepaliFunctions.ConvertToDateObject(raw, 'YYYY-MM-DD');
      if (o && NepaliFunctions.ValidateBsDate(o)) {
        nepDate.value = NepaliFunctions.GetBsFullDate(o, true);
        return;
      }
    }

    let dateForApi = raw;
    if ((window.APP_CALENDAR_MODE || 'BS') === 'BS' && typeof NepaliFunctions !== 'undefined' && raw) {
      const o2 = NepaliFunctions.ConvertToDateObject(raw, 'YYYY-MM-DD');
      if (o2 && NepaliFunctions.ValidateBsDate(o2)) {
        const ad = NepaliFunctions.BS2AD(o2);
        dateForApi = NepaliFunctions.ConvertDateFormat(ad, 'YYYY-MM-DD');
      }
    }

    $.ajax({
      type: "POST",
      url: "ajax.php",
      data: {
        action: 'getNepaliDate',
        date: dateForApi,
        nepaliFormat: true,
      },
      cache: false,
      success: function(response) {
        nepDate.value = response;
      }
    });
  }

  function formhashChangePassword() {
    const getNewPassword = document.getElementById('password');
    const sendNewPassword = document.getElementById('new_p');
    sendNewPassword.value = hex_sha512(getNewPassword.value);
    getNewPassword.value = "";
  }

  function getNepaliDateInput(input) {
    const englishDate = input.value;
    const data = {
      action: 'get_nepali_date',
      english_date: englishDate
    };
    const url = 'operation.php?module=global&page=axios_operation';
    axios.post(url, data)
      .then(function(response) {
        input.value = response.data.nepali_date;
      })
      .catch(function(error) {
        console.error('Error in Axios request:', error);
      });
  }
  // initialize select2 and select2-tag function
  function initSelect2() {
    if (typeof cmsInitSelectSingle === 'function') {
      cmsInitSelectSingle($(document));
    } else {
      $('.select-single').select2(
        cmsSelect2BaseOptions({
          theme: 'classic',
          width: '100%',
          placeholder: 'Select',
        })
      );
    }
    $('.select-single-tag').select2(
      cmsSelect2BaseOptions({
        tags: true,
        width: '100%',
        placeholder: 'Select or type to Add',
        inputColor: '#007bff',
      })
    );
  }

  function initTextEditor() {
    tinymce.init({
      mode: "specific_textareas",
      editor_selector: "texteditor",
      deprecation_warnings: false,
      content_style: 'body { font-size: 12px; }',
      width: '100%',
      height: 250,
      forced_root_block: 'div',
      convert_urls: false,
      plugins: [
        "advlist autolink link image lists charmap print preview hr anchor pagebreak",
        "searchreplace wordcount visualblocks visualchars insertdatetime media nonbreaking",
        "table contextmenu directionality emoticons paste textcolor responsivefilemanager code"
      ],
      toolbar1: "undo redo | bold italic underline | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | styleselect | fontsizeselect",
    });
  }
</script>

<!-- SB-Tech module helpers (kept from the original codegenexis shell) -->
<script src="<?= assetUrl('assets/js/admin.js') ?>"></script>
<script src="<?= assetUrl('assets/js/file-upload-preview.js') ?>"></script>
<?php if (($_GET['module'] ?? '') === 'dashboard'): ?>
<!-- Chart.js — dashboard only -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="<?= assetUrl('assets/js/dashboard-charts.js') ?>"></script>
<?php endif; ?>
<?php if (useBsDates() && bsCalendarAvailable()): ?>
<!-- Nepali (BS) native date-input upgrade — active when Calendar mode = BS -->
<script src="<?= assetUrl('assets/js/bs-calendar-data.js') ?>"></script>
<script src="<?= assetUrl('assets/js/bs-datepicker.js') ?>"></script>
<?php endif; ?>