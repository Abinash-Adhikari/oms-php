<?php
/**
 * SB-Tech — PHPUnit tests for the public API helpers (functions/api.php).
 *
 * Run: vendor/bin/phpunit tests/ApiTest.php
 */
use PHPUnit\Framework\TestCase;

class ApiTest extends TestCase
{
    public function testEnvelopes(): void
    {
        $this->assertSame(['ok' => true, 'data' => ['a' => 1]], api_ok(['a' => 1]));
        $this->assertSame(
            ['ok' => false, 'error' => ['code' => 'not_found', 'message' => 'Nope']],
            api_error('not_found', 'Nope')
        );
    }

    public function testSchoolStatusActiveAndSuspended(): void
    {
        $this->assertSame('active', school_api_status('Active', null, '2026-10-11'));
        $this->assertSame('active', school_api_status('Active', '2026-10-11', '2026-10-11'));
        $this->assertSame('suspended', school_api_status('On Hold', '2027-01-01', '2026-10-11'));
    }

    public function testSchoolStatusExpiredWinsAndHiddenStatuses(): void
    {
        $this->assertSame('expired', school_api_status('Active', '2026-10-10', '2026-10-11'));
        $this->assertSame('expired', school_api_status('On Hold', '2026-01-01', '2026-10-11'));
        $this->assertNull(school_api_status('Completed', null, '2026-10-11'));
        $this->assertNull(school_api_status('Cancelled', '2027-01-01', '2026-10-11'));
    }

    public function testNormalizeAppSlug(): void
    {
        $this->assertSame('mlebs', normalize_app_slug('  MLEBS '));
        $this->assertSame('st-xavier-2', normalize_app_slug('st-xavier-2'));
        $this->assertNull(normalize_app_slug(''));
    }

    public function testNormalizeAppSlugRejectsBadInput(): void
    {
        foreach (['a', '-abc', 'has space', 'ünïcode', str_repeat('a', 33)] as $bad) {
            try {
                normalize_app_slug($bad);
                $this->fail('Accepted bad slug: ' . $bad);
            } catch (InvalidArgumentException $e) {
                $this->assertNotSame('', $e->getMessage());
            }
        }
    }

    public function testNormalizeAccent(): void
    {
        $this->assertSame('#2563EB', normalize_accent('#2563eb'));
        $this->assertNull(normalize_accent(' '));
        $this->expectException(InvalidArgumentException::class);
        normalize_accent('2563EB');
    }

    public function testAppListingUrlRequiresHttpsByDefault(): void
    {
        $this->assertTrue(app_listing_url_ok('https://ss.mlebs.edu.np', false));
        $this->assertFalse(app_listing_url_ok('http://localhost/school', false));
        $this->assertFalse(app_listing_url_ok(null, false));
        $this->assertFalse(app_listing_url_ok('', true));
    }

    public function testAppListingUrlAllowsHttpWhenEnabled(): void
    {
        $this->assertTrue(app_listing_url_ok('http://localhost/school', true));
        $this->assertTrue(app_listing_url_ok('HTTPS://ss.mlebs.edu.np', true));
        $this->assertFalse(app_listing_url_ok('ftp://files.example.com', true));
    }

    public function testListVersionChangesOnlyWithData(): void
    {
        $a = [['id' => 'mlebs', 'status' => 'active']];
        $b = [['id' => 'mlebs', 'status' => 'expired']];
        $this->assertSame(api_list_version($a), api_list_version($a));
        $this->assertNotSame(api_list_version($a), api_list_version($b));
        $this->assertNotSame(api_list_version($a), api_list_version([]));
    }
}
