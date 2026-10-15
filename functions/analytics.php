<?php

/**
 * SB-Tech — Analytics helpers (dashboard, leads, reports).
 *
 * Pure functions only (RULES §1.2): every input is an argument, nothing reads
 * the DB, the session or the clock. Pages run the queries and hand the rows
 * here for shaping, so the maths behind every KPI is unit-testable.
 */

/** Open (not yet closed) lead stages, in funnel order. */
const LEAD_OPEN_STAGES = ['New', 'Contacted', 'Qualified', 'Proposal'];

/**
 * Win probability per open stage, used for the weighted pipeline forecast.
 * Conventional B2B defaults; tune here if the sales team's history says
 * otherwise. Won/Lost are closed and carry no forecast weight.
 */
const LEAD_STAGE_PROBABILITY = [
    'New'       => 0.10,
    'Contacted' => 0.20,
    'Qualified' => 0.40,
    'Proposal'  => 0.60,
];

/**
 * Share of $part in $whole as a percentage. 0 when $whole is not positive,
 * so an empty denominator never divides by zero or shows "NaN%".
 */
function percent_of(float $part, float $whole, int $precision = 0): float
{
    if ($whole <= 0) {
        return 0.0;
    }

    return round(($part / $whole) * 100, $precision);
}

/**
 * Relative change from $previous to $current in percent, or null when the
 * previous period was zero but this one is not (growth from nothing has no
 * meaningful %). Zero to zero is no change, not "new".
 */
function percent_change(float $previous, float $current, int $precision = 0): ?float
{
    if (abs($previous) < 0.00001) {
        return abs($current) < 0.00001 ? 0.0 : null;
    }

    return round((($current - $previous) / abs($previous)) * 100, $precision);
}

/**
 * Describe a change for display: direction, whether it is good news, and a
 * signed label. $higherIsBetter flips the tone for metrics where growth is bad
 * (overdue tasks, expenses). $unit is appended to the label ("%" or " pp").
 *
 * @return array{dir: string, tone: string, text: string}
 */
function trend_badge(?float $change, bool $higherIsBetter = true, string $unit = '%'): array
{
    if ($change === null) {
        return ['dir' => 'flat', 'tone' => 'neutral', 'text' => 'new'];
    }
    if (abs($change) < 0.05) {
        return ['dir' => 'flat', 'tone' => 'neutral', 'text' => '0' . $unit];
    }
    $up = $change > 0;
    $good = $up === $higherIsBetter;
    $magnitude = abs($change);
    $shown = floor($magnitude) == $magnitude ? number_format($magnitude, 0) : number_format($magnitude, 1);

    return [
        'dir'  => $up ? 'up' : 'down',
        'tone' => $good ? 'good' : 'bad',
        'text' => ($up ? '+' : '−') . $shown . $unit,
    ];
}

/**
 * Compact money in the South-Asian numbering system: 10.0K, 1.9L, 2.5Cr.
 * Null/zero renders as $empty (an em dash by default, so empty table cells
 * read as "no value"); headline tiles pass e.g. 'NPR 0'.
 */
function format_compact_money(?float $amount, string $currency = 'NPR', string $empty = '—'): string
{
    if ($amount === null || abs($amount) < 0.005) {
        return $empty;
    }
    $sign = $amount < 0 ? '−' : '';
    $v = abs($amount);
    if ($v >= 10000000) {
        $body = number_format($v / 10000000, 1) . 'Cr';
    } elseif ($v >= 100000) {
        $body = number_format($v / 100000, 1) . 'L';
    } elseif ($v >= 1000) {
        $body = number_format($v / 1000, 1) . 'K';
    } else {
        $body = number_format($v, 0);
    }

    return trim($currency . ' ' . $sign . $body);
}

/**
 * The last $count calendar months ending at $endYm (inclusive), oldest first.
 *
 * @return list<string> e.g. ['2026-05', …, '2026-10']
 */
function month_keys(string $endYm, int $count): array
{
    $end = DateTimeImmutable::createFromFormat('!Y-m', $endYm);
    if ($end === false || $count < 1) {
        return [];
    }
    $keys = [];
    for ($i = $count - 1; $i >= 0; $i--) {
        $keys[] = $end->modify("-{$i} month")->format('Y-m');
    }

    return $keys;
}

/**
 * The last $count days ending at $endDate (inclusive), oldest first.
 *
 * @return list<string> Y-m-d keys
 */
function day_keys(string $endDate, int $count): array
{
    $end = DateTimeImmutable::createFromFormat('!Y-m-d', $endDate);
    if ($end === false || $count < 1) {
        return [];
    }
    $keys = [];
    for ($i = $count - 1; $i >= 0; $i--) {
        $keys[] = $end->modify("-{$i} day")->format('Y-m-d');
    }

    return $keys;
}

/**
 * Monday-start ISO week keys (Y-m-d of each Monday) for the last $count
 * weeks ending with the week containing $endDate, oldest first.
 *
 * @return list<string>
 */
function week_keys(string $endDate, int $count): array
{
    $end = DateTimeImmutable::createFromFormat('!Y-m-d', $endDate);
    if ($end === false || $count < 1) {
        return [];
    }
    $monday = $end->modify('-' . ((int) $end->format('N') - 1) . ' day');
    $keys = [];
    for ($i = $count - 1; $i >= 0; $i--) {
        $keys[] = $monday->modify("-{$i} week")->format('Y-m-d');
    }

    return $keys;
}

/**
 * Align a sparse `key => value` map onto an ordered key list, filling gaps
 * with 0 — GROUP BY results skip empty buckets, charts must not.
 *
 * @param list<string>                $keys
 * @param array<string, int|float|string|null> $map
 * @return list<float>
 */
function fill_series(array $keys, array $map): array
{
    $out = [];
    foreach ($keys as $k) {
        $out[] = (float) ($map[$k] ?? 0);
    }

    return $out;
}

/**
 * Index rows by one column with another as the value, e.g. turning
 * [['m' => '2026-09', 'c' => 4]] into ['2026-09' => 4].
 *
 * @param list<array<string, mixed>> $rows
 * @return array<string, float>
 */
function rows_to_map(array $rows, string $keyCol, string $valueCol): array
{
    $map = [];
    foreach ($rows as $r) {
        $map[(string) $r[$keyCol]] = (float) ($r[$valueCol] ?? 0);
    }

    return $map;
}

/**
 * Probability-weighted forecast of open pipeline value.
 *
 * @param array<string, float|int> $valueByStage stage => summed estimated value
 * @param array<string, float>     $probabilities stage => 0..1
 */
function weighted_pipeline(array $valueByStage, array $probabilities = LEAD_STAGE_PROBABILITY): float
{
    $total = 0.0;
    foreach ($probabilities as $stage => $p) {
        $total += (float) ($valueByStage[$stage] ?? 0) * $p;
    }

    return round($total, 2);
}

/**
 * Bucket a lead's days-since-last-touch into the aging bands the reports use.
 */
function aging_bucket(int $days): string
{
    if ($days <= 7) {
        return '0–7 days';
    }
    if ($days <= 14) {
        return '8–14 days';
    }
    if ($days <= 30) {
        return '15–30 days';
    }

    return '30+ days';
}

/** @return list<string> */
function aging_bucket_labels(): array
{
    return ['0–7 days', '8–14 days', '15–30 days', '30+ days'];
}

/**
 * Month-to-date window and the same span of the previous month, so a
 * mid-month comparison is like-for-like (13 days vs 13 days, not 13 vs 30).
 * The previous span is clamped to that month's length (31 Mar → 28/29 Feb).
 *
 * @return array{cur_start: string, cur_end: string, prev_start: string, prev_end: string}
 */
function month_to_date_windows(string $today): array
{
    $t = DateTimeImmutable::createFromFormat('!Y-m-d', $today);
    if ($t === false) {
        throw new InvalidArgumentException('Expected Y-m-d, got ' . $today);
    }
    $curStart = $t->modify('first day of this month');
    $prevStart = $curStart->modify('-1 month');
    $day = min((int) $t->format('j'), (int) $prevStart->format('t'));

    return [
        'cur_start'  => $curStart->format('Y-m-d'),
        'cur_end'    => $t->format('Y-m-d'),
        'prev_start' => $prevStart->format('Y-m-d'),
        'prev_end'   => $prevStart->format('Y-m-') . str_pad((string) $day, 2, '0', STR_PAD_LEFT),
    ];
}

/**
 * Inline SVG sparkline (no JS, crisp at any zoom). Colour comes from CSS
 * `currentColor`, so the tile decides the hue and dark mode just works.
 * Returns '' when there are fewer than two points to draw.
 *
 * @param list<int|float> $values
 */
function sparkline_svg(array $values, int $width = 120, int $height = 32): string
{
    $n = count($values);
    if ($n < 2) {
        return '';
    }
    $values = array_map('floatval', $values);
    $min = min($values);
    $max = max($values);
    $range = $max - $min;
    $pad = 3;
    $innerH = $height - $pad * 2;
    $stepX = ($width - $pad * 2) / ($n - 1);

    $points = [];
    foreach ($values as $i => $v) {
        $x = $pad + $i * $stepX;
        $y = $range > 0 ? $pad + $innerH - (($v - $min) / $range) * $innerH : $pad + $innerH / 2;
        $points[] = round($x, 1) . ',' . round($y, 1);
    }
    $line = implode(' ', $points);
    $first = explode(',', $points[0]);
    $last = explode(',', $points[$n - 1]);
    $area = $line . ' ' . $last[0] . ',' . ($height - 1) . ' ' . $first[0] . ',' . ($height - 1);

    return '<svg class="ana-spark" viewBox="0 0 ' . $width . ' ' . $height . '" width="' . $width . '" height="' . $height
        . '" preserveAspectRatio="none" aria-hidden="true" focusable="false">'
        . '<polygon points="' . $area . '" fill="currentColor" opacity=".10"/>'
        . '<polyline points="' . $line . '" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke"/>'
        . '<circle cx="' . $last[0] . '" cy="' . $last[1] . '" r="2.5" fill="currentColor"/>'
        . '</svg>';
}

/**
 * Render one KPI stat tile (label · value · optional delta · optional
 * sparkline). Every user-derived string is escaped here, so callers pass
 * raw values.
 *
 * Keys: label, value, icon (FA class), tone (primary|info|success|warning|
 * danger|teal|neutral), href, sub (muted caption), delta (?float from
 * percent_change; key absent = no delta), higher_is_better (bool, default
 * true), delta_unit ('%' default, ' pp' for percentage points),
 * delta_label ('vs last month'), spark (list of numbers; omitted when all zero).
 *
 * @param array<string, mixed> $t
 */
function stat_tile(array $t): string
{
    $tone = in_array($t['tone'] ?? '', ['primary', 'info', 'success', 'warning', 'danger', 'teal', 'neutral'], true)
        ? $t['tone'] : 'primary';
    $href = (string) ($t['href'] ?? '');
    $tag = $href !== '' ? 'a' : 'div';
    $attrs = ' class="ana-tile ana-tone-' . $tone . '"' . ($href !== '' ? ' href="' . e($href) . '"' : '');

    $html = '<' . $tag . $attrs . '>';
    $html .= '<div class="ana-tile__head">';
    if (!empty($t['icon'])) {
        $html .= '<span class="ana-tile__icon"><i class="' . e($t['icon']) . '" aria-hidden="true"></i></span>';
    }
    $html .= '<span class="ana-tile__label">' . e($t['label'] ?? '') . '</span>';
    if ($href !== '') {
        $html .= '<i class="fas fa-arrow-right ana-tile__go" aria-hidden="true"></i>';
    }
    $html .= '</div>';
    $html .= '<div class="ana-tile__value">' . e($t['value'] ?? '—') . '</div>';

    $foot = '';
    if (array_key_exists('delta', $t)) {
        $b = trend_badge(
            $t['delta'] === null ? null : (float) $t['delta'],
            (bool) ($t['higher_is_better'] ?? true),
            (string) ($t['delta_unit'] ?? '%')
        );
        $icon = ['up' => 'fa-arrow-up', 'down' => 'fa-arrow-down', 'flat' => 'fa-minus'][$b['dir']];
        $foot .= '<span class="ana-delta ana-delta--' . $b['tone'] . '"><i class="fas ' . $icon . '" aria-hidden="true"></i>'
            . e($b['text']) . '</span>';
        if (!empty($t['delta_label'])) {
            $foot .= '<span class="ana-tile__sub">' . e($t['delta_label']) . '</span>';
        }
    }
    if (!empty($t['sub'])) {
        $foot .= '<span class="ana-tile__sub">' . e($t['sub']) . '</span>';
    }
    if ($foot !== '') {
        $html .= '<div class="ana-tile__foot">' . $foot . '</div>';
    }
    // An all-zero history is a flat line that says nothing; leave it out.
    if (!empty($t['spark']) && array_filter($t['spark'], static fn ($v) => (float) $v != 0.0)) {
        $html .= sparkline_svg(array_values($t['spark']));
    }

    return $html . '</' . $tag . '>';
}

/**
 * Inline percentage meter for table cells. The number is always printed, so
 * the bar is a reading aid, never the only signal. $tone: '' (series blue),
 * good, warning or critical. Values are clamped to 0–100 for the bar width.
 */
function meter_bar(float $percent, string $tone = ''): string
{
    $width = max(0.0, min(100.0, $percent));
    $toneClass = in_array($tone, ['good', 'warning', 'critical'], true) ? ' ana-meter--' . $tone : '';
    $shown = floor($percent) == $percent ? number_format($percent, 0) : number_format($percent, 1);

    return '<div class="ana-meter' . $toneClass . '">'
        . '<div class="ana-meter__track"><div class="ana-meter__fill" style="width:' . round($width, 1) . '%"></div></div>'
        . '<span class="ana-meter__val">' . $shown . '%</span></div>';
}

/**
 * JSON for a `<canvas data-chart="…">` consumed by assets/js/analytics-charts.js,
 * already escaped for an HTML attribute.
 *
 * Config: type (bar|hbar|line|doughnut), labels, series [{label, data,
 * slot?: 1-8, status?: good|warning|serious|critical|neutral}], stacked,
 * format (int|money|pct|hours), empty (message when every value is zero).
 *
 * @param array<string, mixed> $config
 */
function chart_attr(array $config): string
{
    return e((string) json_encode($config, JSON_UNESCAPED_UNICODE));
}
