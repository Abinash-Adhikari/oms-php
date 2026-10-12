/**
 * SB-Tech — restore submitted form values after a failed submit.
 *
 * Server-side capture lives in functions/helpers.php (capture_old_input / old /
 * old_scoped) and the values arrive here as window.CMS_OLD_INPUT and
 * window.CMS_OLD_SCOPED (passwords, CSRF tokens and files are never included
 * server-side). This script is the safety net: it repopulates every POST form
 * by field name and reopens the drawer/modal that contains the failed form, so
 * JS-driven forms work too.
 *
 * Lookup is scope-aware: each form's values are restored only from the slot of
 * the form that actually failed (hidden `_scope`, or `action` + `:id`), so
 * same-named fields on different forms of one page never leak into each other.
 */
(function () {
    'use strict';

    var old = window.CMS_OLD_INPUT;
    var oldScoped = window.CMS_OLD_SCOPED;
    if (!old || typeof old !== 'object' || !Object.keys(old).length) {
        return;
    }

    function valueFor(name) {
        name = name.replace(/\[\]$/, '');
        return Object.prototype.hasOwnProperty.call(old, name) ? old[name] : undefined;
    }

    /** Resolve the form's restore scope exactly like capture_old_input() does. */
    function formScope(form) {
        var scopeField = form.querySelector('input[name="_scope"]');
        if (scopeField && (scopeField.value || '') !== '') {
            return scopeField.value;
        }
        var actionField = form.querySelector('input[name="action"]');
        if (actionField && (actionField.value || '') !== '') {
            var scope = actionField.value;
            var idField = form.querySelector('input[name="id"]');
            if (idField && (idField.value || '') !== '') {
                scope = scope + ':' + idField.value;
            }
            return scope;
        }
        return null;
    }

    function scopedValue(form, name) {
        name = name.replace(/\[\]$/, '');
        var scope = formScope(form);
        if (scope && oldScoped && typeof oldScoped === 'object' && Object.prototype.hasOwnProperty.call(oldScoped, scope)) {
            var bag = oldScoped[scope];
            if (bag && typeof bag === 'object' && Object.prototype.hasOwnProperty.call(bag, name)) {
                return bag[name];
            }
            return undefined;
        }
        return valueFor(name);
    }

    function isSkippable(el) {
        var name = (el.name || '').toLowerCase();
        if (!name) { return true; }
        if (['file', 'password', 'submit', 'button', 'reset', 'image', 'hidden'].indexOf(el.type) !== -1) {
            return true;
        }
        return ['password', 'passwd', 'pwd', 'secret', 'csrf', 'token', 'captcha'].some(function (needle) {
            return name.indexOf(needle) !== -1;
        });
    }

    function asArray(v) {
        return Array.isArray(v) ? v.map(String) : [String(v)];
    }

    function restoreField(el, form) {
        if (isSkippable(el)) { return false; }
        var v = scopedValue(form, el.name);
        if (v === undefined) { return false; }

        if (el.type === 'checkbox') {
            var arr = asArray(v);
            if (el.value === '' || el.value === '1' || el.value === 'on') {
                el.checked = !(v === '' || v === null || v === '0' || v === 0 || v === false || v === 'false');
            } else {
                el.checked = arr.indexOf(String(el.value)) !== -1;
            }
            return true;
        }

        if (el.type === 'radio') {
            el.checked = String(v) === String(el.value);
            return true;
        }

        if (el.tagName === 'SELECT') {
            if (Array.isArray(v)) {
                var selected = v.map(String);
                Array.prototype.forEach.call(el.options, function (o) {
                    o.selected = selected.indexOf(String(o.value)) !== -1;
                });
            } else {
                el.value = v;
            }
            if (window.jQuery && window.jQuery(el).data('select2')) {
                window.jQuery(el).trigger('change.select2');
            }
            return true;
        }

        if (el.tagName === 'TEXTAREA') {
            el.value = Array.isArray(v) ? v.join('\n') : v;
            return true;
        }

        if (Array.isArray(v)) { return false; }
        el.value = v;
        return true;
    }

    function openContainer(form) {
        var drawer = form.closest ? form.closest('.cms-drawer') : null;
        if (drawer) {
            drawer.classList.add('open');
            var backdrop = document.getElementById('drawerBackdrop');
            if (backdrop) { backdrop.classList.add('active'); }
            document.body.style.overflow = 'hidden';
            return;
        }
        var modal = form.closest ? form.closest('.modal') : null;
        if (modal && window.jQuery && window.jQuery.fn.modal) {
            window.jQuery(modal).modal('show');
        }
    }

    function run() {
        var restoredAny = false;

        Array.prototype.forEach.call(document.querySelectorAll('form'), function (form) {
            if ((form.getAttribute('method') || 'get').toLowerCase() !== 'post') { return; }

            var restored = false;
            Array.prototype.forEach.call(
                form.querySelectorAll('input[name], select[name], textarea[name]'),
                function (el) { if (restoreField(el, form)) { restored = true; } }
            );

            if (restored) {
                restoredAny = true;
                openContainer(form);
            }
        });

        if (restoredAny && window.jQuery) {
            window.jQuery(document).trigger('cms:oldinputrestored');
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run);
    } else {
        run();
    }
})();