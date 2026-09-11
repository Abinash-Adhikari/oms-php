/**
 * SB-Tech — Dashboard charts (Chart.js).
 * Reads window.SB_DASH payloads rendered by modules/dashboard/home.php and
 * re-colors charts whenever the theme switcher dispatches `themechange`.
 *
 * When a chart's dataset is entirely zero (no data yet), it renders a centered
 * empty-state message over the canvas instead of a flat zero line / empty
 * donut — so the card always looks intentional rather than broken.
 */
(function () {
    'use strict';

    if (typeof window.SB_DASH === 'undefined' || typeof window.Chart === 'undefined') {
        return;
    }

    var reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function token(name, fallback) {
        var v = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
        return v || fallback;
    }

    function gridColor() {
        // Subtle grid that adapts to light/dark via the border token.
        var hex = token('--border-color', '#E2E8F0');
        // Convert #RRGGBB to rgba with low alpha for a hairline grid.
        var m = hex.replace('#', '');
        if (m.length === 6) {
            var r = parseInt(m.slice(0, 2), 16), g = parseInt(m.slice(2, 4), 16), b = parseInt(m.slice(4, 6), 16);
            return 'rgba(' + r + ',' + g + ',' + b + ',0.8)';
        }
        return hex;
    }

    function baseOptions() {
        return {
            responsive: true,
            maintainAspectRatio: false,
            animation: reducedMotion ? false : { duration: 450, easing: 'easeOutQuart' },
            plugins: {
                legend: { labels: { color: token('--text-secondary', '#475569'), usePointStyle: true, boxWidth: 8 } },
                tooltip: {
                    backgroundColor: token('--bg-card', '#FFFFFF'),
                    titleColor: token('--text-primary', '#0F172A'),
                    bodyColor: token('--text-secondary', '#475569'),
                    borderColor: token('--border-color', '#E2E8F0'),
                    borderWidth: 1,
                    padding: 10,
                    cornerRadius: 8,
                    displayColors: true
                }
            }
        };
    }

    /**
     * All-datasets-zero detector. For bar charts a single zero bar looks like
     * "nothing happened" — we show the empty state instead. For doughnuts an
     * all-zero state would crash Chart.js (empty dataset), so we also catch it.
     */
    function allZero(values) {
        for (var i = 0; i < values.length; i++) {
            if (values[i] > 0) {
                return false;
            }
        }
        return true;
    }

    /**
     * Overlay a centered empty-state message on top of a chart canvas.
     * The message inherits theme text colors so it stays readable in both modes.
     */
    function attachEmptyState(canvas, message) {
        var existing = canvas._tmsEmptyState;
        if (existing) {
            existing.remove();
        }
        var wrap = document.createElement('div');
        wrap.className = 'tms-chart-empty';
        wrap.setAttribute('role', 'status');
        wrap.setAttribute('aria-label', message);
        var node = document.createElement('div');
        node.className = 'tms-chart-empty__msg';
        node.innerHTML = '<span class="tms-chart-empty__icon"><i class="fas fa-inbox"></i></span>' +
            '<span class="tms-chart-empty__text">' + message + '</span>';
        wrap.appendChild(node);
        canvas.parentElement.appendChild(wrap);
        canvas._tmsEmptyState = wrap;
    }

    function clearEmptyState(canvas) {
        if (canvas._tmsEmptyState) {
            canvas._tmsEmptyState.remove();
            canvas._tmsEmptyState = null;
        }
    }

    var charts = [];

    function buildCharts() {
        // Destroy previous instances on theme rebuild.
        charts.forEach(function (c) { c.destroy(); });
        charts = [];
        // Clear any lingering empty-state overlays.
        var nodes = document.querySelectorAll('.tms-chart-empty');
        for (var i = 0; i < nodes.length; i++) {
            nodes[i].remove();
        }
        Array.prototype.forEach.call(
            document.querySelectorAll('canvas[id^="leadsByStageChart"], canvas[id^="attendanceChart"]'),
            function (c) { c._tmsEmptyState = null; }
        );

        var leadsEl = document.getElementById('leadsByStageChart');

        if (leadsEl && window.SB_DASH.leads) {
            var d = window.SB_DASH.leads;
            leadsEl.style.height = '240px';
            clearEmptyState(leadsEl);
            if (allZero(d.values)) {
                attachEmptyState(leadsEl, 'No leads yet — the pipeline starts here.');
            } else {
                charts.push(new Chart(leadsEl.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: d.labels,
                        datasets: [{
                            data: d.values,
                            backgroundColor: d.colors,
                            borderColor: token('--bg-card', '#FFFFFF'),
                            borderWidth: 2,
                            hoverOffset: 6
                        }]
                    },
                    options: Object.assign(baseOptions(), {
                        cutout: '62%',
                        plugins: Object.assign(baseOptions().plugins, {
                            legend: { position: 'bottom', labels: { color: token('--text-secondary', '#475569'), usePointStyle: true, boxWidth: 8, padding: 14 } }
                        })
                    })
                }));
            }
        }

        var attEl = document.getElementById('attendanceChart');
        if (attEl && window.SB_DASH.attendance) {
            var a = window.SB_DASH.attendance;
            attEl.style.height = '240px';
            clearEmptyState(attEl);
            if (allZero(a.values)) {
                attachEmptyState(attEl, 'No attendance recorded for today.');
            } else {
                charts.push(new Chart(attEl.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: a.labels,
                        datasets: [{
                            data: a.values,
                            backgroundColor: a.colors.map(function (c) { return c; }),
                            borderRadius: 8,
                            maxBarThickness: 56
                        }]
                    },
                    options: Object.assign(baseOptions(), {
                        scales: {
                            x: { grid: { display: false }, ticks: { color: token('--text-secondary', '#475569') } },
                            y: {
                                beginAtZero: true,
                                ticks: { color: token('--text-muted', '#94A3B8'), precision: 0 },
                                grid: { color: gridColor() },
                                border: { display: false }
                            }
                        },
                        plugins: Object.assign(baseOptions().plugins, { legend: { display: false } })
                    })
                }));
            }
        }
    }

    // Rebuild when the user switches mode or accent.
    document.addEventListener('themechange', function () {
        // Wait a tick so the data-mode attribute has been applied.
        window.setTimeout(buildCharts, 30);
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', buildCharts);
    } else {
        buildCharts();
    }
})();
