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
    // Organization display name tests (functions/helpers.php)
    // =====================================================================

    public function testOfficeDisplayNamesShape()
    {
        $names = office_display_names();
        $this->assertArrayHasKey('name', $names);
        $this->assertArrayHasKey('short', $names);
        $this->assertNotSame('', trim($names['name']));
        $this->assertNotSame('', trim($names['short']));
    }

    public function testOfficeDisplayNamePrefersProfileThenConfig()
    {
        $expected = trim((string) config('organization_name', 'Office'));
        if ($this->dbAvailable()) {
            $profile = Database::instance()->selectOne(
                'SELECT `name` FROM `tbl_office_profiles` WHERE `id` = 1'
            );
            if (!empty($profile['name'])) {
                $expected = trim((string) $profile['name']);
            }
        }
        $this->assertSame($expected, office_display_name());
    }

    public function testOfficeDisplayShortNameFallsBackToProfileNameWithoutAccronym()
    {
        if (!$this->dbAvailable()) {
            $this->markTestSkipped('Database not available for office profile check.');
        }
        $profile = Database::instance()->selectOne(
            'SELECT `name`, `accronym` FROM `tbl_office_profiles` WHERE `id` = 1'
        );
        if (empty($profile['name'])) {
            $this->markTestSkipped('No office profile row to exercise the name fallback.');
        }
        $accronym = trim((string) ($profile['accronym'] ?? ''));
        $expected = $accronym !== '' ? $accronym : trim((string) $profile['name']);
        $this->assertSame($expected, office_display_short_name());
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

    public function testResolveCalendarMonthAdMode()
    {
        if (!useBsDates()) {
            $this->requireCalendar();
            $info = resolve_calendar_month('2026-04');
            $this->assertSame('AD', $info['mode']);
            $this->assertSame('2026-04', $info['ym']);
            $this->assertSame('2026-04', $info['label']);
            $this->assertSame('2026-04-01', $info['ad_start']);
            $this->assertSame('2026-04-30', $info['ad_end']);
            $this->assertSame(30, $info['days']);
        } else {
            $this->markTestSkipped('Office uses BS dates; AD branch not exercised.');
        }
    }

    public function testResolveCalendarMonthBsMode()
    {
        $this->requireCalendar();
        if (useBsDates()) {
            $info = resolve_calendar_month('2083-01'); // Baisakh 2083
            $this->assertSame('BS', $info['mode']);
            $this->assertSame('2083-01', $info['ym']);
            $this->assertSame('2083 Baisakh', $info['label']);
            $this->assertSame('2026-04-14', $info['ad_start']);
            $this->assertSame('2026-05-14', $info['ad_end']);
            $this->assertSame(31, $info['days']);
        } else {
            $this->markTestSkipped('Office uses AD dates; BS branch not exercised.');
        }
    }

    public function testResolveCalendarMonthFallsBackToCurrent()
    {
        $this->requireCalendar();
        $info = resolve_calendar_month('garbage');
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}$/', $info['ym']);
        $this->assertGreaterThan(0, $info['days']);
        $this->assertTrue(strtotime($info['ad_end']) >= strtotime($info['ad_start']));
        // Empty input behaves like invalid: resolves to the current month.
        $cur = resolve_calendar_month('');
        $this->assertSame($cur['ym'], $info['ym']);
    }

    // =====================================================================
    // Client module catalog — deployment-DB is_active drives permission checks
    // =====================================================================

    private function requireDeploymentCatalog(): void
    {
        if (!$this->dbAvailable()) {
            $this->markTestSkipped('DB not available; client deployment catalog not tested.');
        }
        try {
            Database::instance()->selectOne(
                "SELECT 1 FROM `php_smart_school_mlebs`.`tbl_modules` LIMIT 1"
            );
        } catch (Throwable $e) {
            $this->markTestSkipped('php_smart_school_mlebs deployment catalog not reachable.');
        }
    }

    public function testClientModuleCatalogCarriesIsActiveFlags()
    {
        $this->requireDeploymentCatalog();
        $catalog = client_module_catalog(['db_name' => 'php_smart_school_mlebs']);
        $this->assertNotEmpty($catalog);
        foreach ($catalog as $m) {
            $this->assertArrayHasKey('is_active', $m);
            $this->assertArrayHasKey('subs_active', $m);
            $this->assertArrayHasKey('subs', $m);
            // Every submodule must have an activation flag too.
            foreach (array_keys($m['subs']) as $subKey) {
                $this->assertArrayHasKey($subKey, $m['subs_active']);
                $this->assertIsBool($m['subs_active'][$subKey]);
            }
        }
    }

    public function testClientModuleCatalogIsActiveReflectsDeploymentDb()
    {
        $this->requireDeploymentCatalog();
        // dashboard is always left on in the deployment DB.
        $catalog = client_module_catalog(['db_name' => 'php_smart_school_mlebs']);
        $byKey = [];
        foreach ($catalog as $m) {
            $byKey[$m['key']] = $m;
        }
        $this->assertArrayHasKey('dashboard', $byKey);
        $this->assertTrue($byKey['dashboard']['is_active']);
        // The flags must match the raw is_active column read from the DB.
        $db = Database::instance();
        $rows = $db->select(
            "SELECT `module_key`, `is_active` FROM `php_smart_school_mlebs`.`tbl_modules`
             WHERE `plan` IN ('PRO','ALL')"
        );
        foreach ($rows as $r) {
            if (!isset($byKey[$r['module_key']])) {
                continue;
            }
            $this->assertSame(
                (int) $r['is_active'] === 1,
                $byKey[$r['module_key']]['is_active'],
                'module ' . $r['module_key'] . ' is_active mismatch'
            );
        }
    }

    // =====================================================================
    // Notification wiring regression (functions/hr.php + CommunicationService)
    // =====================================================================

    public function testNotifyUserDoesNotRecurseIntoWorkflow()
    {
        // Regression (TEST-02): notifyUser() used to call
        // CommunicationService::sendWorkflowNotification(), which called
        // notifyUser() back — infinite mutual recursion until PHP memory
        // exhaustion. This surfaced as an OOM fatal at Database::insert()
        // while adding a meeting from the calendar modal. The recursion is
        // DB-independent (the in-app insert fails silently without a DB),
        // so the test is meaningful with or without one.
        if (!class_exists('CommunicationService')) {
            require_once __DIR__ . '/../classes/CommunicationService.php';
        }
        $details = 'regression: notification wiring must not recurse';
        $start = microtime(true);
        $mem = memory_get_usage();
        notifyUser(1, $details, 'Meeting', '0', 1);
        $elapsed = microtime(true) - $start;
        $grown = memory_get_usage() - $mem;

        $this->assertLessThan(1.0, $elapsed, 'notifyUser must complete without infinite recursion');
        $this->assertLessThan(5 * 1024 * 1024, $grown, 'notifyUser memory must stay bounded');

        if ($this->dbAvailable()) {
            Database::instance()->delete('tbl_notifications', '`details` = ?', [$details]);
        }
    }
}
