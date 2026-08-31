/* SB-Tech admin custom JS (jQuery assumed loaded). */

(function ($) {
    'use strict';

    // Auto-enable select2 on elements marked .select2
    $(function () {
        $('.select2').each(function () {
            $(this).select2({
                theme: 'bootstrap4',
                width: '100%',
                placeholder: $(this).data('placeholder') || 'Select...',
                allowClear: $(this).data('allow-clear') === true
            });
        });
    });

    // ── Confirmation helper ──
    // <button class="confirm-submit" data-confirm="..."> inside a form now opens
    // a themed Bootstrap modal instead of the native window.confirm().
    var CONFIRM_HTML =
        '<div class="modal fade" id="sbConfirmModal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="sbConfirmTitle">' +
        '  <div class="modal-dialog modal-dialog-centered modal-sm-custom" role="document">' +
        '    <div class="modal-content">' +
        '      <div class="modal-header border-0 pb-0">' +
        '        <h5 class="modal-title" id="sbConfirmTitle"><i class="fas fa-exclamation-circle text-warning mr-2"></i>Please confirm</h5>' +
        '        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>' +
        '      </div>' +
        '      <div class="modal-body" id="sbConfirmBody"></div>' +
        '      <div class="modal-footer border-0 pt-0">' +
        '        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>' +
        '        <button type="button" class="btn btn-danger" id="sbConfirmOk">Yes, continue</button>' +
        '      </div>' +
        '    </div>' +
        '  </div>' +
        '</div>';

    function askConfirm(message) {
        var $modal = $('#sbConfirmModal');
        if (!$modal.length) {
            $modal = $(CONFIRM_HTML).appendTo('body');
        }
        $('#sbConfirmBody').text(message);
        $modal.modal('show');
        $modal.find('#sbConfirmOk').off('click').on('click', function () {
            $modal.modal('hide');
            $modal.trigger('sb:confirmed');
        });
        return $modal;
    }

    $(document).on('submit', 'form', function (e) {
        var form = this;
        var $btn = $(form).find('.confirm-submit');
        if (!$btn.length || form.dataset.sbConfirmed === '1') {
            return;
        }
        e.preventDefault();
        var $modal = askConfirm($btn.data('confirm') || 'Are you sure?');
        $modal.off('sb:confirmed').one('sb:confirmed', function () {
            form.dataset.sbConfirmed = '1';
            // requestSubmit keeps HTML5 validation; the flag above lets the
            // resubmitted event pass straight through.
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit($btn.is('[type=submit]') ? $btn[0] : undefined);
            } else {
                form.submit();
            }
        });
    });

    // Auto-dismiss alerts after 6 seconds
    $(function () {
        $('.content-wrapper .alert').not('.keep').delay(6000).fadeOut('slow');
    });

    // Sidebar search — filters top-level nav items (and their submodule
    // labels, folded into the same data-search haystack) by substring match,
    // hiding a section header when none of its items match.
    function filterSidebarMenu(q) {
        q = (q || '').toLowerCase().trim();
        var menu = document.querySelector('.cms-sidebar-menu');
        if (!menu) {
            return;
        }

        var items = menu.querySelectorAll(':scope > li.nav-item');
        items.forEach(function (li) {
            var hay = (li.getAttribute('data-search') || '').toLowerCase();
            var show = !q || hay.indexOf(q) !== -1;
            li.style.display = show ? '' : 'none';
        });

        var headers = menu.querySelectorAll(':scope > li.nav-header');
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

    $(function () {
        var searchInput = document.getElementById('cmsSidebarSearch');
        if (!searchInput) {
            return;
        }
        searchInput.addEventListener('input', function () {
            filterSidebarMenu(searchInput.value);
        });

        // "No matches" empty state under the menu while filtering.
        var menu = document.querySelector('.cms-sidebar-menu');
        if (menu) {
            var empty = document.createElement('li');
            empty.className = 'cms-sidebar-no-results nav-header';
            empty.textContent = 'No matching menu items';
            empty.style.display = 'none';
            menu.appendChild(empty);

            var updateEmpty = function () {
                var q = searchInput.value.toLowerCase().trim();
                if (!q) {
                    empty.style.display = 'none';
                    return;
                }
                var any = false;
                menu.querySelectorAll(':scope > li.nav-item').forEach(function (li) {
                    if (li.style.display !== 'none') {
                        any = true;
                    }
                });
                empty.style.display = any ? 'none' : '';
            };

            searchInput.addEventListener('input', updateEmpty);

            // Keyboard shortcut: Ctrl/Cmd+K or "/" focuses the menu search.
            document.addEventListener('keydown', function (ev) {
                var tag = (document.activeElement && document.activeElement.tagName) || '';
                var typing = tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT';
                if (((ev.ctrlKey || ev.metaKey) && ev.key.toLowerCase() === 'k') || (ev.key === '/' && !typing)) {
                    ev.preventDefault();
                    searchInput.focus();
                    searchInput.select();
                }
                if (ev.key === 'Escape' && document.activeElement === searchInput) {
                    searchInput.value = '';
                    searchInput.dispatchEvent(new Event('input'));
                    searchInput.blur();
                }
            });
        }
    });

    // ── Drawer escape: move drawers out of .card containers ──
    // When drawers are rendered inside a .card (e.g. tab pages), the card's
    // CSS transform on hover creates a new containing block that traps
    // position:fixed drawers. Move them to <body> so they escape.
    $(function () {
        $('.card .cms-drawer-backdrop, .card .cms-drawer').each(function () {
            $(this).appendTo('body');
        });
    });
})(jQuery);
