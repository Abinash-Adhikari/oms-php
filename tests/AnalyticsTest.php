<?php
/**
 * SB-Tech — PHPUnit tests for functions/analytics.php (pure, no DB).
 *
 * Run: vendor/bin/phpunit tests/AnalyticsTest.php
 */
use PHPUnit\Framework\TestCase;

class AnalyticsTest extends TestCase
{
    public function testPercentOfHappyPath()
    {
        $this->assertSame(25.0, percent_of(1, 4));
        $this->assertSame(33.3, percent_of(1, 3, 1));
    }

    public function testPercentOfZeroWholeIsZero()
    {
        $this->assertSame(0.0, percent_of(5, 0));
        $this->assertSame(0.0, percent_of(5, -2));
    }

    public function testPercentChangeUpAndDown()
    {
        $this->assertSame(50.0, percent_change(4, 6));
        $this->assertSame(-75.0, percent_change(4, 1));
    }

    public function testPercentChangeFromZeroIsNull()
    {
        $this->assertNull(percent_change(0, 7));
        $this->assertSame(0.0, percent_change(0, 0));
    }

    public function testTrendBadgeToneFollowsDirectionAndPolarity()
    {
        $this->assertSame(['dir' => 'up', 'tone' => 'good', 'text' => '+12%'], trend_badge(12.0));
        $this->assertSame(['dir' => 'up', 'tone' => 'bad', 'text' => '+12%'], trend_badge(12.0, false));
        $this->assertSame(['dir' => 'down', 'tone' => 'bad', 'text' => '−7.5 pp'], trend_badge(-7.5, true, ' pp'));
    }

    public function testTrendBadgeNullAndFlat()
    {
        $this->assertSame('new', trend_badge(null)['text']);
        $this->assertSame('neutral', trend_badge(0.0)['tone']);
        $this->assertSame('flat', trend_badge(0.01)['dir']);
    }

    public function testFormatCompactMoneyScales()
    {
        $this->assertSame('NPR 950', format_compact_money(950));
        $this->assertSame('NPR 10.0K', format_compact_money(10000));
        $this->assertSame('NPR 1.9L', format_compact_money(190000));
        $this->assertSame('NPR 2.5Cr', format_compact_money(25000000));
    }

    public function testFormatCompactMoneyEmptyAndNegative()
    {
        $this->assertSame('—', format_compact_money(null));
        $this->assertSame('—', format_compact_money(0.0));
        $this->assertSame('NPR −1.5L', format_compact_money(-150000));
        $this->assertSame('NPR 0', format_compact_money(0.0, 'NPR', 'NPR 0'));
    }

    public function testMonthKeysCrossYearBoundary()
    {
        $this->assertSame(['2025-11', '2025-12', '2026-01', '2026-02'], month_keys('2026-02', 4));
    }

    public function testMonthKeysFromMonthEndDoNotSkip()
    {
        // "-1 month" from a 31st overflows in naive date maths; keys are month-anchored.
        $this->assertSame(['2026-02', '2026-03'], month_keys('2026-03', 2));
        $this->assertSame([], month_keys('bad', 3));
    }

    public function testDayKeys()
    {
        $this->assertSame(['2026-02-27', '2026-02-28', '2026-03-01'], day_keys('2026-03-01', 3));
        $this->assertSame([], day_keys('2026-03-01', 0));
    }

    public function testWeekKeysStartOnMonday()
    {
        // 2026-10-13 is a Tuesday → current week starts Monday 12th.
        $this->assertSame(['2026-09-28', '2026-10-05', '2026-10-12'], week_keys('2026-10-13', 3));
        $this->assertSame(['2026-10-12'], week_keys('2026-10-18', 1)); // Sunday belongs to the same week
    }

    public function testFillSeriesFillsGaps()
    {
        $this->assertSame([0.0, 4.0, 0.0], fill_series(['a', 'b', 'c'], ['b' => 4]));
        $this->assertSame([], fill_series([], ['b' => 4]));
    }

    public function testRowsToMap()
    {
        $rows = [['m' => '2026-09', 'c' => '4'], ['m' => '2026-10', 'c' => null]];
        $this->assertSame(['2026-09' => 4.0, '2026-10' => 0.0], rows_to_map($rows, 'm', 'c'));
        $this->assertSame([], rows_to_map([], 'm', 'c'));
    }

    public function testWeightedPipeline()
    {
        $values = ['New' => 1000, 'Contacted' => 1000, 'Qualified' => 1000, 'Proposal' => 1000, 'Won' => 99999];
        // 100 + 200 + 400 + 600; Won is closed and carries no weight.
        $this->assertSame(1300.0, weighted_pipeline($values));
    }

    public function testWeightedPipelineMissingStagesAndCustomProbabilities()
    {
        $this->assertSame(0.0, weighted_pipeline([]));
        $this->assertSame(50.0, weighted_pipeline(['Proposal' => 100], ['Proposal' => 0.5]));
    }

    public function testAgingBucketBoundaries()
    {
        $this->assertSame('0–7 days', aging_bucket(0));
        $this->assertSame('0–7 days', aging_bucket(7));
        $this->assertSame('8–14 days', aging_bucket(8));
        $this->assertSame('15–30 days', aging_bucket(30));
        $this->assertSame('30+ days', aging_bucket(31));
    }

    public function testAgingBucketLabelsCoverEveryBucket()
    {
        foreach ([0, 10, 20, 400] as $d) {
            $this->assertContains(aging_bucket($d), aging_bucket_labels());
        }
    }

    public function testMonthToDateWindows()
    {
        $this->assertSame(
            ['cur_start' => '2026-10-01', 'cur_end' => '2026-10-13', 'prev_start' => '2026-09-01', 'prev_end' => '2026-09-13'],
            month_to_date_windows('2026-10-13')
        );
    }

    public function testMonthToDateWindowsClampsShortPreviousMonth()
    {
        $w = month_to_date_windows('2026-03-31');
        $this->assertSame('2026-02-01', $w['prev_start']);
        $this->assertSame('2026-02-28', $w['prev_end']);
        $this->assertSame('2025-12-31', month_to_date_windows('2026-01-31')['prev_end']);
    }

    public function testMonthToDateWindowsRejectsBadDate()
    {
        $this->expectException(InvalidArgumentException::class);
        month_to_date_windows('13/10/2026');
    }

    public function testSparklineDrawsPolyline()
    {
        $svg = sparkline_svg([1, 3, 2], 100, 20);
        $this->assertStringContainsString('<polyline points="3,17 50,3 97,10"', $svg);
        $this->assertStringContainsString('aria-hidden="true"', $svg);
    }

    public function testSparklineEdgeCases()
    {
        $this->assertSame('', sparkline_svg([5]));
        // A flat series draws a mid-line instead of dividing by zero.
        $this->assertStringContainsString('points="3,16 117,16"', sparkline_svg([2, 2]));
    }

    public function testStatTileEscapesAndLinks()
    {
        $html = stat_tile(['label' => '<b>Leads</b>', 'value' => 5, 'href' => '?a=1&b=2', 'tone' => 'danger']);
        $this->assertStringStartsWith('<a class="ana-tile ana-tone-danger" href="?a=1&amp;b=2">', $html);
        $this->assertStringContainsString('&lt;b&gt;Leads&lt;/b&gt;', $html);
        $this->assertStringEndsWith('</a>', $html);
    }

    public function testStatTileDeltaAndFallbackTone()
    {
        $html = stat_tile(['label' => 'Spend', 'value' => '1', 'tone' => 'bogus', 'delta' => 20.0, 'higher_is_better' => false, 'delta_label' => 'vs last month']);
        $this->assertStringStartsWith('<div class="ana-tile ana-tone-primary">', $html);
        $this->assertStringContainsString('ana-delta--bad', $html);
        $this->assertStringContainsString('+20%', $html);
        $this->assertStringContainsString('vs last month', $html);
        $this->assertStringContainsString('>new<', stat_tile(['label' => 'x', 'value' => 1, 'delta' => null]));
    }

    public function testStatTileSparklineOnlyWhenThereIsSignal()
    {
        $this->assertStringContainsString('<svg class="ana-spark"', stat_tile(['label' => 'x', 'value' => 1, 'spark' => [0, 2, 1]]));
        $this->assertStringNotContainsString('<svg', stat_tile(['label' => 'x', 'value' => 0, 'spark' => [0, 0, 0]]));
    }

    public function testChartAttrIsAttributeSafe()
    {
        $attr = chart_attr(['labels' => ['A"B', '<x>']]);
        $this->assertStringNotContainsString('"', $attr);
        $this->assertStringNotContainsString('<', $attr);
        $this->assertSame(['labels' => ['A"B', '<x>']], json_decode(html_entity_decode($attr, ENT_QUOTES), true));
    }

    public function testMeterBarRendersValueAndTone()
    {
        $html = meter_bar(42.5, 'good');
        $this->assertStringContainsString('ana-meter ana-meter--good', $html);
        $this->assertStringContainsString('width:42.5%', $html);
        $this->assertStringContainsString('>42.5%<', $html);
    }

    public function testMeterBarClampsWidthButShowsRealValue()
    {
        $html = meter_bar(130, 'bogus');
        $this->assertStringContainsString('width:100%', $html);
        $this->assertStringContainsString('>130%<', $html);
        $this->assertStringNotContainsString('ana-meter--', $html);
        $this->assertStringContainsString('width:0%', meter_bar(-5));
    }
}
