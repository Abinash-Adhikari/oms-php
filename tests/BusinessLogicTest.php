<?php
/**
 * SB-Tech — PHPUnit tests for core business logic.
 * Tests: leave balance math, attendance late/early computation, voucher line balancing.
 *
 * Run: vendor/bin/phpunit tests/BusinessLogicTest.php
 */
use PHPUnit\Framework\TestCase;

class BusinessLogicTest extends TestCase
{
    // =====================================================================
    // Attendance computation tests (functions/hr.php)
    // =====================================================================

    public function testOnTimeCheckin()
    {
        $result = computeAttendanceMetrics('09:00:00', '17:00:00', '09:00:00', '17:00:00');
        $this->assertEquals(0, $result['checkin_delay']);
        $this->assertEquals(0, $result['late_checkin']);
        $this->assertEquals(0, $result['late_checkin_minutes']);
        $this->assertEquals(0, $result['checkout_early']);
        $this->assertEquals(0, $result['early_checkout']);
        $this->assertEqualsWithDelta(8.0, $result['working_hours'], 0.01);
    }

    public function testLateCheckin()
    {
        // 15 minutes late
        $result = computeAttendanceMetrics('09:15:00', '17:00:00', '09:00:00', '17:00:00');
        $this->assertEquals(15, $result['checkin_delay']);
        $this->assertEquals(1, $result['late_checkin']);
        $this->assertEquals(15, $result['late_checkin_minutes']);
        $this->assertEquals(0, $result['checkout_early']);
        $this->assertEqualsWithDelta(7.75, $result['working_hours'], 0.01);
    }

    public function testEarlyCheckout()
    {
        // 30 minutes early
        $result = computeAttendanceMetrics('09:00:00', '16:30:00', '09:00:00', '17:00:00');
        $this->assertEquals(0, $result['checkin_delay']);
        $this->assertEquals(0, $result['late_checkin']);
        $this->assertEquals(30, $result['checkout_early']);
        $this->assertEquals(1, $result['early_checkout']);
        $this->assertEqualsWithDelta(7.5, $result['working_hours'], 0.01);
    }

    public function testLateCheckinAndEarlyCheckout()
    {
        // 10 min late + 20 min early
        $result = computeAttendanceMetrics('09:10:00', '16:40:00', '09:00:00', '17:00:00');
        $this->assertEquals(10, $result['checkin_delay']);
        $this->assertEquals(1, $result['late_checkin']);
        $this->assertEquals(20, $result['checkout_early']);
        $this->assertEquals(1, $result['early_checkout']);
        $this->assertEqualsWithDelta(7.5, $result['working_hours'], 0.01);
    }

    public function testNoCheckout()
    {
        $result = computeAttendanceMetrics('09:00:00', null, '09:00:00', '17:00:00');
        $this->assertEquals(0, $result['checkin_delay']);
        $this->assertNull($result['checkout_early']);
        $this->assertNull($result['working_hours']);
    }

    public function testNoCheckin()
    {
        $result = computeAttendanceMetrics(null, '17:00:00', '09:00:00', '17:00:00');
        $this->assertNull($result['checkin_delay']);
        $this->assertEquals(0, $result['checkout_early']);
        $this->assertNull($result['working_hours']);
    }

    public function testNoConfigTimes()
    {
        $result = computeAttendanceMetrics('09:00:00', '17:00:00', null, null);
        $this->assertNull($result['checkin_delay']);
        $this->assertNull($result['checkout_early']);
        $this->assertEqualsWithDelta(8.0, $result['working_hours'], 0.01);
    }

    // =====================================================================
    // Leave day counting tests (functions/hr.php)
    // =====================================================================

    public function testHalfDayLeave()
    {
        $days = countLeaveDays('2025-01-06', '2025-01-10', true, null);
        $this->assertEquals(0.5, $days);
    }

    private function dbAvailable(): bool
    {
        try {
            if (class_exists('Database')) {
                Database::instance();
                return true;
            }
        } catch (Throwable $e) {
            // DB not available.
        }
        return false;
    }

    public function testFullWeekLeave()
    {
        if (!$this->dbAvailable()) {
            $this->markTestSkipped('Database not available for holiday check.');
        }
        // Mon-Fri, no holidays
        $days = countLeaveDays('2025-01-06', '2025-01-10', false, null);
        $this->assertEquals(5.0, $days);
    }

    public function testSingleDayLeave()
    {
        if (!$this->dbAvailable()) {
            $this->markTestSkipped('Database not available for holiday check.');
        }
        $days = countLeaveDays('2025-01-06', '2025-01-06', false, null);
        $this->assertEquals(1.0, $days);
    }

    public function testLeaveRangeIncludesWeekends()
    {
        if (!$this->dbAvailable()) {
            $this->markTestSkipped('Database not available for holiday check.');
        }
        // Sat-Sun are not holidays (unless configured), so they count as leave days
        $days = countLeaveDays('2025-01-04', '2025-01-07', false, null);
        // Sat(4), Sun(5), Mon(6), Tue(7) = 4 days
        $this->assertEquals(4.0, $days);
    }

    public function testInvalidDateRange()
    {
        $days = countLeaveDays('2025-01-10', '2025-01-06', false, null);
        $this->assertEquals(0.0, $days);
    }

    // =====================================================================
    // Voucher line balancing tests (functions/accounting.php)
    // =====================================================================

    public function testBalancedVoucherLines()
    {
        $post = [
            'account_terminal_id' => [1, 2],
            'debit'               => [1000.00, 0],
            'credit'              => [0, 1000.00],
            'line_remarks'        => ['Cash in', 'Revenue'],
        ];
        $result = accountingParseVoucherLines($post);
        $this->assertTrue($result['ok']);
        $this->assertEqualsWithDelta(1000.00, $result['debit'], 0.01);
        $this->assertEqualsWithDelta(1000.00, $result['credit'], 0.01);
        $this->assertCount(2, $result['lines']);
    }

    public function testUnbalancedVoucherLines()
    {
        $post = [
            'account_terminal_id' => [1, 2],
            'debit'               => [1000.00, 0],
            'credit'              => [0, 800.00],
            'line_remarks'        => ['', ''],
        ];
        $result = accountingParseVoucherLines($post);
        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('does not balance', $result['error']);
    }

    public function testEmptyVoucherLines()
    {
        $post = [
            'account_terminal_id' => [],
            'debit'               => [],
            'credit'              => [],
            'line_remarks'        => [],
        ];
        $result = accountingParseVoucherLines($post);
        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('At least one', $result['error']);
    }

    public function testNegativeAmountBlocked()
    {
        $post = [
            'account_terminal_id' => [1, 2],
            'debit'               => [-100, 0],
            'credit'              => [0, 100],
            'line_remarks'        => ['', ''],
        ];
        $result = accountingParseVoucherLines($post);
        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('Negative', $result['error']);
    }

    public function testMultiLineBalancedVoucher()
    {
        $post = [
            'account_terminal_id' => [1, 2, 3],
            'debit'               => [500, 300, 200],
            'credit'              => [0, 0, 1000],
            'line_remarks'        => ['', '', ''],
        ];
        $result = accountingParseVoucherLines($post);
        $this->assertTrue($result['ok']);
        $this->assertEqualsWithDelta(1000.00, $result['debit'], 0.01);
        $this->assertEqualsWithDelta(1000.00, $result['credit'], 0.01);
    }

    public function testSkipsZeroAmountLines()
    {
        $post = [
            'account_terminal_id' => [1, 2, 3],
            'debit'               => [500, 0, 0],
            'credit'              => [0, 0, 500],
            'line_remarks'        => ['', '', ''],
        ];
        $result = accountingParseVoucherLines($post);
        $this->assertTrue($result['ok']);
        $this->assertCount(2, $result['lines']); // Line with 0 debit and 0 credit is skipped
    }

    public function testTinyImbalanceWithinTolerance()
    {
        // 0.005 difference is within the 0.01 tolerance
        $post = [
            'account_terminal_id' => [1, 2],
            'debit'               => [1000.005, 0],
            'credit'              => [0, 1000.00],
            'line_remarks'        => ['', ''],
        ];
        $result = accountingParseVoucherLines($post);
        $this->assertTrue($result['ok']);
    }

    // =====================================================================
    // Format minutes helper tests
    // =====================================================================

    public function testFormatMinutes()
    {
        $this->assertEquals('5 min', formatMinutes(5));
        $this->assertEquals('1 hr 0 min', formatMinutes(60));
        $this->assertEquals('2 hr 30 min', formatMinutes(150));
        $this->assertEquals('0 min', formatMinutes(0));
        $this->assertEquals('-1 hr 0 min', formatMinutes(-60));
    }

    // =====================================================================
    // BS calendar conversion tests
    // =====================================================================

    public function testBsMonthName()
    {
        $this->assertEquals('Baisakh', bsMonthName(1));
        $this->assertEquals('Chaitra', bsMonthName(12));
        $this->assertEquals('', bsMonthName(0));
        $this->assertEquals('', bsMonthName(13));
    }

    private function requireCalendar(): void
    {
        if (!$this->dbAvailable() || !bsCalendarAvailable()) {
            $this->markTestSkipped('tbl_calendar is not seeded / DB not available.');
        }
    }

    public function testAdToBs()
    {
        $this->requireCalendar();
        $this->assertSame('2000-01-01', adToBs('1943-04-14'));
        $this->assertSame('2083-01-01', adToBs('2026-04-14'));
        $this->assertSame('2083-12-30', adToBs('2027-04-13'));
        $this->assertNull(adToBs('1918-04-12'));
        $this->assertNull(adToBs('not-a-date'));
    }

    public function testBsToAd()
    {
        $this->requireCalendar();
        $this->assertSame('1943-04-14', bsToAd('2000-01-01'));
        $this->assertSame('2026-04-14', bsToAd('2083-01-01'));
        // Month-length aware (Jestha has 31 days, Asar 32 days in BS 2083).
        $this->assertSame('2026-06-14', bsToAd('2083-02-31'));
        $this->assertSame('2026-07-16', bsToAd('2083-03-32'));
        // Invalid day, out-of-range and malformed inputs.
        $this->assertNull(bsToAd('2083-02-32'));
        $this->assertNull(bsToAd('1974-01-01'));
        $this->assertNull(bsToAd('2101-01-01'));
        $this->assertNull(bsToAd('garbage'));
        $this->assertNull(bsToAd(''));
    }

    public function testBsAdRoundTrip()
    {
        $this->requireCalendar();
        foreach (['1943-04-14', '1918-04-13', '2026-04-14', '2030-12-25'] as $ad) {
            $bs = adToBs($ad);
            $this->assertNotNull($bs, "adToBs($ad)");
            $this->assertSame($ad, bsToAd($bs), "bsToAd(adToBs($ad))");
        }
    }

    public function testFormatBsDate()
    {
        $this->assertSame('2083 Baisakh 1', formatBsDate('2083-01-01'));
        $this->assertSame('2091 Bhadra 30', formatBsDate('2091-05-30'));
        $this->assertSame('not-a-date', formatBsDate('not-a-date'));
    }

    public function testFormatDateViewRespectsCalendarMode()
    {
        $this->requireCalendar();
        if (useBsDates()) {
            $this->assertSame('2083-01-01', formatDateView('2026-04-14'));
            $this->assertSame('2083 Baisakh 1', formatDateViewLong('2026-04-14'));
        } else {
            // AD mode: value passes through with Y-m-d normalization.
            $this->assertSame('2026-04-14', formatDateView('2026-04-14'));
            $this->assertSame('2026-04-14', formatDateViewLong('2026-04-14'));
        }
    }

    public function testNormalizeDateInput()
    {
        $this->requireCalendar();
        if (useBsDates()) {
            $this->assertSame('2026-04-18', normalizeDateInput('2083-01-05'));
            $this->assertNull(normalizeDateInput('2083-13-01'));
            $this->assertNull(normalizeDateInput('1900-01-01'));
            $this->assertSame('', normalizeDateInput(''));
        } else {
            $this->assertSame('2026-04-18', normalizeDateInput('2026-04-18'));
        }
    }
}
