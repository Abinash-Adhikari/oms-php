/* SB-Tech — Nepali (Bikram Sambat) datepicker.
 *
 * Pure-vanilla, dependency-free. Active only when the office uses BS dates
 * (use_date = BS). It transparently upgrades every <input type="date">:
 *   - the visible input becomes a BS text field with a click-to-pick popup,
 *   - the original field NAME is moved to a hidden AD input that carries the
 *     Gregorian date, so the backend always receives/stores AD (single source
 *     of truth), regardless of how the form is built (static or dynamic rows).
 *
 * Exposes: window.BsDatepicker = { init(), refresh(root) }
 */
(function () {
    'use strict';

    if (!window.BS_CAL_DATA) {
        return;
    }

    var DATA = window.BS_CAL_DATA;
    var YEARS = Object.keys(DATA).map(Number).sort(function (a, b) { return a - b; });
    var MIN_YEAR = YEARS[0];
    var MAX_YEAR = YEARS[YEARS.length - 1];
    var ANCHOR = window.BS_ANCHOR || { bsYear: 2000, bsMonth: 1, ad: '1943-04-14' };
    var ANCHOR_UTC = parseAdUtc(ANCHOR.ad);

    var MONTHS = ['Baisakh', 'Jestha', 'Asar', 'Shrawan', 'Bhadra', 'Ashwin', 'Kartik', 'Mangsir', 'Poush', 'Magh', 'Falgun', 'Chaitra'];
    var WEEKDAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

    var POPUP = null;      // single popup instance
    var active = null;     // { input, hidden, onchange }
    var viewY = 0, viewM = 0; // currently rendered month
    var selBs = null;      // current selection [y, m, d]

    /* ------------------------------------------------------------------ */
    /* Date math (all in UTC to stay DST-proof)                            */
    /* ------------------------------------------------------------------ */

    function parseAdUtc(adStr) {
        var p = /^(\d{4})-(\d{1,2})-(\d{1,2})$/.exec(String(adStr).trim());
        if (!p) { return NaN; }
        return Date.UTC(+p[1], +p[2] - 1, +p[3]);
    }

    function pad(n) { return n < 10 ? '0' + n : '' + n; }

    function formatAd(utc) {
        var d = new Date(utc);
        return d.getUTCFullYear() + '-' + pad(d.getUTCMonth() + 1) + '-' + pad(d.getUTCDate());
    }

    function monthLen(y, m) {
        var row = DATA[y];
        return row ? row[m - 1] : 0;
    }

    function sumYear(y) {
        var r = 0, row = DATA[y];
        if (!row) { return 0; }
        for (var i = 0; i < 12; i++) { r += row[i]; }
        return r;
    }

    /* Days from BS 2000/1/1 (negative = before). Returns null if out of range. */
    function bsEpochDay(y, m, d) {
        if (!DATA[y] || m < 1 || m > 12) { return null; }
        var len = monthLen(y, m);
        if (d < 1 || d > len) { return null; }
        var total = 0;
        if (y > ANCHOR.bsYear) {
            for (var yy = ANCHOR.bsYear; yy < y; yy++) { total += sumYear(yy); }
        } else if (y < ANCHOR.bsYear) {
            for (var yy = y; yy < ANCHOR.bsYear; yy++) { total -= sumYear(yy); }
        }
        for (var mm = 1; mm < m; mm++) { total += monthLen(y, mm); }
        return total + (d - 1);
    }

    /* Days from AD anchor date (== epoch day of BS 2000/1/1). */
    function adEpochDay(adStr) {
        var utc = parseAdUtc(adStr);
        if (isNaN(utc)) { return null; }
        return Math.round((utc - ANCHOR_UTC) / 86400000);
    }

    /* AD 'YYYY-MM-DD' -> BS 'YYYY-MM-DD' (or null). */
    function adToBs(adStr) {
        var epoch = adEpochDay(adStr);
        if (epoch === null) { return null; }
        var y, m, acc;
        if (epoch >= 0) {
            y = ANCHOR.bsYear; m = ANCHOR.bsMonth; acc = 0;
            while (y < MAX_YEAR || (y === MAX_YEAR && m <= 12)) {
                var len = monthLen(y, m);
                if (epoch < acc + len) {
                    return yearPad(y) + '-' + pad(m) + '-' + pad(epoch - acc + 1);
                }
                acc += len;
                m++; if (m > 12) { m = 1; y++; }
            }
            return null;
        }
        y = ANCHOR.bsYear; m = ANCHOR.bsMonth - 1; acc = 0;
        if (m === 0) { m = 12; y--; }
        while (y > MIN_YEAR || (y === MIN_YEAR && m >= 1)) {
            acc -= monthLen(y, m);
            if (epoch >= acc) {
                return yearPad(y) + '-' + pad(m) + '-' + pad(epoch - acc + 1);
            }
            m--; if (m === 0) { m = 12; y--; }
        }
        return null;
    }

    /* BS (y, m, d) -> AD 'YYYY-MM-DD' (or null). */
    function bsToAd(y, m, d) {
        var e = bsEpochDay(y, m, d);
        if (e === null) { return null; }
        return formatAd(ANCHOR_UTC + e * 86400000);
    }

    function yearPad(y) { y = String(y); while (y.length < 4) { y = '0' + y; } return y; }

    /* Is BS (y,m,d) inside the enforced AD window? */
    function withinMinMax(bsAd, boundAd) {
        if (!boundAd) { return true; }
        return bsAd <= boundAd;
    }
    function minOk(bsAd, minAd) {
        return !minAd || bsAd >= minAd;
    }

    /* ------------------------------------------------------------------ */
    /* Popup UI                                                             */
    /* ------------------------------------------------------------------ */

    function ensurePopup() {
        if (POPUP) { return POPUP; }
        POPUP = document.createElement('div');
        POPUP.className = 'bs-datepicker-popup';
        POPUP.style.display = 'none';

        var header = document.createElement('div');
        header.className = 'bs-datepicker-popup__header';

        var prev = document.createElement('button');
        prev.type = 'button'; prev.className = 'bs-datepicker-popup__nav';
        prev.innerHTML = '&#8249;'; prev.setAttribute('aria-label', 'Previous month');
        var title = document.createElement('div');
        title.className = 'bs-datepicker-popup__title';
        var next = document.createElement('button');
        next.type = 'button'; next.className = 'bs-datepicker-popup__nav';
        next.innerHTML = '&#8250;'; next.setAttribute('aria-label', 'Next month');
        header.appendChild(prev); header.appendChild(title); header.appendChild(next);

        var gridWrap = document.createElement('div');
        gridWrap.className = 'bs-datepicker-popup__grid';

        var wk = document.createElement('div');
        wk.className = 'bs-datepicker-popup__week';
        for (var i = 0; i < 7; i++) {
            var w = document.createElement('span'); w.textContent = WEEKDAYS[i];
            wk.appendChild(w);
        }

        var footer = document.createElement('div');
        footer.className = 'bs-datepicker-popup__footer';
        var todayBtn = document.createElement('button');
        todayBtn.type = 'button'; todayBtn.className = 'bs-datepicker-popup__today';
        todayBtn.textContent = 'Today';
        var adHint = document.createElement('span');
        adHint.className = 'bs-datepicker-popup__ad-hint';
        footer.appendChild(todayBtn); footer.appendChild(adHint);

        POPUP.appendChild(header);
        POPUP.appendChild(wk);
        POPUP.appendChild(gridWrap);
        POPUP.appendChild(footer);
        document.body.appendChild(POPUP);

        prev.addEventListener('click', function () { stepMonth(-1); });
        next.addEventListener('click', function () { stepMonth(1); });
        todayBtn.addEventListener('click', function () {
            var todayBs = adToBs(formatAd(Date.now()));
            if (!todayBs) { return; }
            var p = todayBs.split('-');
            viewY = +p[0]; viewM = +p[1];
            if (active) { select(+p[0], +p[1], +p[2], true); }
            render();
        });

        document.addEventListener('mousedown', function (e) {
            if (POPUP.style.display !== 'none' && !POPUP.contains(e.target) && !(active && active.input === e.target)) {
                closePopup();
            }
        }, true);
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { closePopup(); }
        });

        return POPUP;
    }

    function stepMonth(dir) {
        viewM += dir;
        if (viewM < 1) { viewM = 12; viewY--; }
        if (viewM > 12) { viewM = 1; viewY++; }
        render();
    }

    function render() {
        var pop = ensurePopup();
        var len = monthLen(viewY, viewM);
        if (!len) {
            pop.style.display = 'none';
            return;
        }
        pop.querySelector('.bs-datepicker-popup__title').textContent = yearPad(viewY) + ' ' + MONTHS[viewM - 1];

        var prev = pop.querySelector('.bs-datepicker-popup__nav');
        var next = pop.querySelectorAll('.bs-datepicker-popup__nav')[1];
        prev.disabled = (viewY === MIN_YEAR && viewM === 1);
        next.disabled = (viewY === MAX_YEAR && viewM === 12);

        var adMin = active && active.input.getAttribute('data-ad-min');
        var adMax = active && active.input.getAttribute('data-ad-max');

        var grid = pop.querySelector('.bs-datepicker-popup__grid');
        grid.innerHTML = '';

        var firstAd = bsToAd(viewY, viewM, 1);
        var lead = firstAd ? new Date(parseAdUtc(firstAd)).getUTCDay() : 0;

        var todayBs = adToBs(formatAd(Date.now()));
        var todayKey = todayBs ? todayBs.slice(0, 7) : null;
        var selKey = selBs ? yearPad(selBs[0]) + '-' + pad(selBs[1]) : null;

        var cells = [];
        for (var i = 0; i < lead; i++) { cells.push(null); }
        for (var da = 1; da <= len; da++) { cells.push(da); }
        while (cells.length % 7 !== 0) { cells.push(null); }

        for (var c = 0; c < cells.length; c++) {
            var day = cells[c];
            var cell = document.createElement('button');
            cell.type = 'button';
            if (!day) {
                cell.className = 'bs-datepicker-popup__cell is-blank';
                cell.tabIndex = -1;
            } else {
                var ad = bsToAd(viewY, viewM, day);
                var off = (adMin && ad < adMin) || (adMax && ad > adMax);
                cell.className = 'bs-datepicker-popup__cell' + (off ? ' is-disabled' : '');
                cell.textContent = day;
                if (!off) {
                    cell.addEventListener('click', function (dd) {
                        return function () { select(viewY, viewM, dd, true); };
                    }(day));
                }
                cell.addEventListener('mouseenter', function (dd) {
                    return function () { showAdHint(dd); };
                }(day));
                cell.addEventListener('mouseleave', function () { showAdHint(null); });
            }
            if (day && todayKey === yearPad(viewY) + '-' + pad(viewM)) {
                cell.classList.add('is-today');
                if (todayBs && +todayBs.split('-')[2] === day) { cell.classList.add('is-today-day'); }
            }
            if (day && selKey === yearPad(viewY) + '-' + pad(viewM) && selBs && selBs[2] === day) {
                cell.classList.add('is-selected');
            }
            grid.appendChild(cell);
        }
    }

    function showAdHint(day) {
        if (!day) { adHintText(''); return; }
        var ad = bsToAd(viewY, viewM, day);
        adHintText(ad ? 'AD ' + ad : '');
    }
    function adHintText(t) {
        ensurePopup().querySelector('.bs-datepicker-popup__ad-hint').textContent = t;
    }

    function select(y, m, d, closeAfter) {
        if (!active) { return; }
        var ad = bsToAd(y, m, d);
        if (!ad) { return; }
        selBs = [y, m, d];
        active.hidden.value = ad;
        active.input.value = yearPad(y) + '-' + pad(m) + '-' + pad(d);
        /* Hidden carries the AD payload — notify name-based listeners (jQuery etc.). */
        active.hidden.dispatchEvent(new Event('change', { bubbles: false }));
        /* Visible input fires its inline onchange (e.g. filter auto-submit) exactly once. */
        active.input.dispatchEvent(new Event('change', { bubbles: true }));
        render();
        if (closeAfter) { closePopup(); }
    }

    function openPopup(input, hidden, onchange) {
        if (active && active.input === input) { togglePopup(); return; }
        active = { input: input, hidden: hidden, onchange: onchange };
        selBs = null;
        var cur = input.value;
        if (/^\d{4}-\d{2}-\d{2}$/.test(cur)) {
            var p = cur.split('-');
            if (DATA[+p[0]] && +p[1] >= 1 && +p[1] <= 12) {
                viewY = +p[0]; viewM = +p[1]; selBs = [+p[0], +p[1], +p[2]];
            }
        } else {
            var today = adToBs(formatAd(Date.now()));
            if (today) { var t = today.split('-'); viewY = +t[0]; viewM = +t[1]; }
        }
        render();
        positionPopup();
        ensurePopup().style.display = 'block';
    }

    function togglePopup() {
        var pop = ensurePopup();
        if (pop.style.display === 'none') { positionPopup(); pop.style.display = 'block'; }
        else { closePopup(); }
    }

    /* Battery of sizing helpers. */
    function positionPopup() {
        if (!active) { return; }
        var pop = ensurePopup();
        var r = active.input.getBoundingClientRect();
        var top = r.bottom + 6;
        var left = r.left;
        if (left + 260 > window.innerWidth - 8) { left = Math.max(8, window.innerWidth - 268); }
        pop.style.left = left + 'px';
        pop.style.top = top + 'px';
    }

    function closePopup() {
        if (!POPUP) { return; }
        POPUP.style.display = 'none';
        active = null;
    }

    /* ------------------------------------------------------------------ */
    /* Input upgrade                                                        */
    /* ------------------------------------------------------------------ */

    function upgrade(input) {
        if (!input || input.dataset.bsUpgraded || input.dataset.bsSkip === '1') { return; }
        var name = input.getAttribute('name');
        var required = input.hasAttribute('required');
        var formAttr = input.getAttribute('form');
        var adVal = /^\d{4}-\d{2}-\d{2}$/.test(input.value) ? input.value : '';
        var adMin = input.min || '';
        var adMax = input.max || '';
        var oldOnchange = input.onchange;

        var hidden = document.createElement('input');
        hidden.type = 'hidden';
        if (name) { hidden.setAttribute('name', name); }
        if (required) { hidden.setAttribute('required', 'required'); }
        if (formAttr) { hidden.setAttribute('form', formAttr); }
        hidden.value = adVal;

        input.setAttribute('type', 'text');
        input.removeAttribute('name');
        input.removeAttribute('required');
        input.removeAttribute('min');
        input.removeAttribute('max');
        if (adMin) { input.setAttribute('data-ad-min', adMin); }
        if (adMax) { input.setAttribute('data-ad-max', adMax); }
        input.className += ' bs-date-input';
        input.setAttribute('readonly', 'readonly');
        input.setAttribute('autocomplete', 'off');
        input.title = (input.title ? input.title + ' — ' : '') + 'Nepali (BS) date';
        input.value = adVal ? adToBs(adVal) : '';
        input.dataset.bsUpgraded = '1';

        input.insertAdjacentElement('afterend', hidden);

        input.addEventListener('click', function () { openPopup(input, hidden, oldOnchange); });
        input.addEventListener('focus', function () { if (!POPUP || POPUP.style.display === 'none') { openPopup(input, hidden, oldOnchange); } });
    }

    function scan(root) {
        if (!root || (root.nodeType !== 1 && root.nodeType !== 9)) { return; }
        if (root.nodeType === 1 && root.matches && root.matches('input[type="date"]')) { upgrade(root); }
        var list = root.querySelectorAll ? root.querySelectorAll('input[type="date"]') : [];
        for (var i = 0; i < list.length; i++) { upgrade(list[i]); }
    }

    var observer = null;
    function init() {
        /* Initial pass over everything already in the DOM. */
        scan(document);

        /* Upgrade dates added later (dynamic form rows, reopened modals). */
        if ('MutationObserver' in window && !observer) {
            observer = new MutationObserver(function (mutations) {
                for (var m = 0; m < mutations.length; m++) {
                    var added = mutations[m].addedNodes;
                    for (var i = 0; i < added.length; i++) {
                        if (added[i].nodeType === 1) { scan(added[i]); }
                    }
                }
            });
            observer.observe(document.body, { childList: true, subtree: true });
        }
    }

    if (document.readyState !== 'loading') {
        init();
    } else {
        document.addEventListener('DOMContentLoaded', init);
    }
    /* Even if DOMContentLoaded already fired and body is still being patched,
       a short retry window catches the few stragglers. */
    setTimeout(function () { viewY = 0; scan(document); }, 250);

    window.BsDatepicker = { init: init, refresh: scan, adToBs: adToBs, bsToAd: bsToAd };
})();