/**
 * SB-Tech — Declarative analytics charts (Chart.js 4).
 *
 * Any <canvas data-chart='{…}'> on the page is rendered from its JSON config
 * (built server-side by chart_attr() in functions/analytics.php):
 *
 *   type    bar | hbar | line | doughnut
 *   labels  category / time labels
 *   series  [{label, data, slot?: 1-6, status?: good|warning|serious|critical|neutral}]
 *   stacked bool         format  int | money | pct | hours
 *   empty   message shown instead of a chart when every value is zero
 *
 * Colours come from the --viz-* CSS tokens, so light/dark and the theme
 * switcher (`themechange`) re-skin every chart without page reloads.
 */
(function () {
    'use strict';

    if (typeof window.Chart === 'undefined') {
        return;
    }

    var reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var instances = [];

    function token(name, fallback) {
        var v = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
        return v || fallback;
    }

    function seriesColor(s, i) {
        if (s.status) {
            return token('--viz-' + s.status, '#94A3B8');
        }
        return token('--viz-' + (s.slot || (i % 6) + 1), '#2a78d6');
    }

    function withAlpha(hex, alpha) {
        var m = hex.replace('#', '');
        if (m.length !== 6) {
            return hex;
        }
        return 'rgba(' + parseInt(m.slice(0, 2), 16) + ',' + parseInt(m.slice(2, 4), 16) + ',' +
            parseInt(m.slice(4, 6), 16) + ',' + alpha + ')';
    }

    // Mirrors format_compact_money() in PHP (South-Asian lakh / crore).
    function compactMoney(v) {
        var a = Math.abs(v), sign = v < 0 ? '−' : '';
        if (a >= 10000000) { return 'NPR ' + sign + (a / 10000000).toFixed(1) + 'Cr'; }
        if (a >= 100000) { return 'NPR ' + sign + (a / 100000).toFixed(1) + 'L'; }
        if (a >= 1000) { return 'NPR ' + sign + (a / 1000).toFixed(1) + 'K'; }
        return 'NPR ' + sign + Math.round(a);
    }

    function formatter(kind) {
        switch (kind) {
            case 'money': return compactMoney;
            case 'pct': return function (v) { return Math.round(v * 10) / 10 + '%'; };
            case 'hours': return function (v) { return (Math.round(v * 10) / 10) + ' h'; };
            default: return function (v) { return Number(v).toLocaleString('en-IN'); };
        }
    }

    function allZero(series) {
        for (var i = 0; i < series.length; i++) {
            var d = series[i].data || [];
            for (var j = 0; j < d.length; j++) {
                if (Number(d[j]) !== 0) {
                    return false;
                }
            }
        }
        return true;
    }

    function showEmpty(canvas, message) {
        var wrap = document.createElement('div');
        wrap.className = 'tms-chart-empty';
        wrap.setAttribute('role', 'status');
        var msg = document.createElement('div');
        msg.className = 'tms-chart-empty__msg';
        var icon = document.createElement('span');
        icon.className = 'tms-chart-empty__icon';
        icon.innerHTML = '<i class="fas fa-chart-bar" aria-hidden="true"></i>';
        var text = document.createElement('span');
        text.className = 'tms-chart-empty__text';
        text.textContent = message || 'No data for this period yet.';
        msg.appendChild(icon);
        msg.appendChild(text);
        wrap.appendChild(msg);
        canvas.parentElement.appendChild(wrap);
        canvas.style.visibility = 'hidden';
    }

    function build(canvas) {
        var cfg;
        try {
            cfg = JSON.parse(canvas.getAttribute('data-chart'));
        } catch (e) {
            return;
        }
        var series = cfg.series || [];
        if (!series.length || allZero(series)) {
            showEmpty(canvas, cfg.empty);
            return;
        }
        canvas.style.visibility = '';

        var fmt = formatter(cfg.format);
        var surface = token('--bg-card', '#FFFFFF');
        var ink = token('--text-secondary', '#475569');
        var muted = token('--text-muted', '#64748B');
        var grid = token('--viz-grid', '#EEF2F6');
        var isDoughnut = cfg.type === 'doughnut';
        var isLine = cfg.type === 'line';
        var horizontal = cfg.type === 'hbar';
        var multi = series.length > 1;

        var datasets = series.map(function (s, i) {
            if (isDoughnut) {
                var colors = (s.colors || []).map(function (c, k) {
                    return seriesColor(typeof c === 'object' ? c : { slot: c }, k);
                });
                return {
                    label: s.label,
                    data: s.data,
                    backgroundColor: colors.length ? colors : s.data.map(function (_, k) { return seriesColor({}, k); }),
                    borderColor: surface,
                    borderWidth: 2,
                    hoverOffset: 4
                };
            }
            var color = seriesColor(s, i);
            if (isLine) {
                return {
                    label: s.label,
                    data: s.data,
                    borderColor: color,
                    backgroundColor: multi ? color : withAlpha(color, 0.10),
                    fill: !multi,
                    borderWidth: 2,
                    cubicInterpolationMode: 'monotone', // never overshoots between points
                    pointRadius: 0,
                    pointHoverRadius: 4,
                    pointHitRadius: 12
                };
            }
            return {
                label: s.label,
                data: s.data,
                backgroundColor: color,
                hoverBackgroundColor: color,
                // A surface-coloured stroke gives the 2px gap between stacked
                // segments and adjacent bars without drawing borders.
                borderColor: surface,
                borderWidth: cfg.stacked ? { top: 2, right: 2 } : 0,
                borderRadius: 4,
                borderSkipped: 'start',
                maxBarThickness: horizontal ? 22 : 32,
                categoryPercentage: 0.7,
                barPercentage: 0.9
            };
        });

        var valueAxis = {
            beginAtZero: true,
            stacked: !!cfg.stacked,
            grid: { color: grid, drawTicks: false },
            border: { display: false },
            ticks: { color: muted, padding: 6, maxTicksLimit: 5, precision: 0, callback: function (v) { return fmt(v); } }
        };
        var categoryAxis = {
            stacked: !!cfg.stacked,
            grid: { display: false },
            border: { color: grid },
            ticks: { color: ink, autoSkip: true, maxRotation: 0, padding: 4 }
        };

        var options = {
            responsive: true,
            maintainAspectRatio: false,
            animation: reducedMotion ? false : { duration: 400, easing: 'easeOutQuart' },
            indexAxis: horizontal ? 'y' : 'x',
            interaction: isDoughnut ? { mode: 'nearest', intersect: true } : { mode: 'index', intersect: false, axis: horizontal ? 'y' : 'x' },
            layout: { padding: { top: 4 } },
            plugins: {
                legend: {
                    display: multi || isDoughnut,
                    position: isDoughnut ? 'right' : 'top',
                    align: isDoughnut ? 'center' : 'end',
                    labels: { color: ink, usePointStyle: true, pointStyle: 'circle', boxWidth: 8, boxHeight: 8, padding: 12, font: { size: 12 } }
                },
                tooltip: {
                    backgroundColor: surface,
                    titleColor: token('--text-primary', '#0F172A'),
                    bodyColor: ink,
                    borderColor: token('--border-color', '#E2E8F0'),
                    borderWidth: 1,
                    padding: 10,
                    cornerRadius: 8,
                    usePointStyle: true,
                    boxPadding: 4,
                    callbacks: {
                        label: function (ctx) {
                            var raw = isDoughnut ? ctx.raw : (horizontal ? ctx.parsed.x : ctx.parsed.y);
                            var label = isDoughnut ? ctx.label : ctx.dataset.label;
                            var line = (label ? label + ': ' : '') + fmt(raw);
                            if (isDoughnut) {
                                var sum = ctx.dataset.data.reduce(function (a, b) { return a + Number(b); }, 0);
                                if (sum > 0) {
                                    line += ' (' + Math.round((raw / sum) * 100) + '%)';
                                }
                            }
                            return line;
                        }
                    }
                }
            }
        };
        if (isDoughnut) {
            options.cutout = '68%';
        } else {
            options.scales = horizontal ? { x: valueAxis, y: categoryAxis } : { x: categoryAxis, y: valueAxis };
        }

        instances.push(new Chart(canvas.getContext('2d'), {
            type: isDoughnut ? 'doughnut' : (isLine ? 'line' : 'bar'),
            data: { labels: cfg.labels || [], datasets: datasets },
            options: options
        }));
    }

    function buildAll() {
        instances.forEach(function (c) { c.destroy(); });
        instances = [];
        Array.prototype.forEach.call(document.querySelectorAll('.tms-chart-empty'), function (n) { n.remove(); });
        Array.prototype.forEach.call(document.querySelectorAll('canvas[data-chart]'), build);
    }

    document.addEventListener('themechange', function () {
        // Wait a tick so the new data-mode attribute has been applied.
        window.setTimeout(buildAll, 30);
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', buildAll);
    } else {
        buildAll();
    }
})();
