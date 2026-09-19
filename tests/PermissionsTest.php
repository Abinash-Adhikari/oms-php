<?php
/**
 * SB-Tech — PHPUnit tests for RBAC permission normalization
 * (functions/permissions.php, staff permissions editor).
 *
 * Run: vendor/bin/phpunit tests/PermissionsTest.php
 */
use PHPUnit\Framework\TestCase;

class PermissionsTest extends TestCase
{
    /** @var array list of valid module keys (mirrors nav $modules) */
    private static array $modules = ['my_office', 'staff_management', 'sales', 'leads', 'accounts'];

    /** @var array module => [submodule => label] (mirrors nav $subNavBars) */
    private static array $subNavBars = [
        'my_office'        => ['office_calendar' => 'Office Calendar', 'documents' => 'Documents'],
        'staff_management' => ['add_staff' => 'Employees', 'permissions' => 'Permissions', 'hr_care' => 'HR Care'],
        'sales'            => ['documents' => 'Documents'],
    ];

    /** @var array list of valid special keys (mirrors nav $specialPermissions) */
    private static array $specialKeys = ['manage_office', 'manage_leads', 'approve_vouchers', 'audit'];

    private static function normalize(array $postedModules, array $postedSubmodules, array $postedSpecial, array $nonGrantable = ['permissions']): array
    {
        return normalize_permission_grants(
            $postedModules,
            $postedSubmodules,
            $postedSpecial,
            self::$modules,
            self::$subNavBars,
            self::$specialKeys,
            $nonGrantable
        );
    }

    public function testHappyPathKeepsValidGrantsAndSortsDeterministically(): void
    {
        $result = self::normalize(
            ['sales', 'my_office', 'sales'],
            [
                'sales'     => ['documents'],
                'my_office' => ['documents', 'office_calendar'],
            ],
            ['manage_office', 'manage_office', 'audit']
        );

        $this->assertSame(['my_office', 'sales'], $result['permitted_modules']);
        $this->assertSame(
            ['my_office' => ['documents', 'office_calendar'], 'sales' => ['documents']],
            $result['permitted_submodules']
        );
        $this->assertSame(['audit', 'manage_office'], $result['special_permission']);
    }

    public function testSubmoduleGrantImpliesModuleGrant(): void
    {
        // Only a submodule box was ticked (no module box) — the module grant
        // is implied, mirroring Auth::hasModule() (AC-AUTH-02.4).
        $result = self::normalize([], ['staff_management' => ['hr_care']], []);

        $this->assertSame(['staff_management'], $result['permitted_modules']);
        $this->assertSame(['staff_management' => ['hr_care']], $result['permitted_submodules']);
    }

    public function testRogueAndMalformedInputIsDropped(): void
    {
        $result = self::normalize(
            ['evil_module', 123, 'sales'],
            [
                'sales'             => ['nope', 5],
                'staff_management'  => ['permissions'], // admin-managed → blocked
                'hacker'            => ['documents'],   // module not in nav
            ],
            ['hack', 'manage_leads', 9]
        );

        $this->assertSame(['sales'], $result['permitted_modules']);
        $this->assertSame([], $result['permitted_submodules']);
        $this->assertSame(['manage_leads'], $result['special_permission']);
    }

    public function testAdminManagedSubmoduleIsNeverGrantable(): void
    {
        $result = self::normalize(
            ['staff_management'],
            ['staff_management' => ['add_staff', 'permissions']],
            []
        );

        $this->assertSame(['staff_management'], $result['permitted_modules']);
        $this->assertSame(['staff_management' => ['add_staff']], $result['permitted_submodules']);
    }

    public function testEmptySubmissionProducesEmptyArrays(): void
    {
        $result = self::normalize([], [], []);

        $this->assertSame([], $result['permitted_modules']);
        $this->assertSame([], $result['permitted_submodules']);
        $this->assertSame([], $result['special_permission']);
    }

    public function testUnknownSubmoduleUnderValidModuleIsDropped(): void
    {
        $result = self::normalize(
            ['my_office'],
            ['my_office' => ['office_calendar', 'does_not_exist']],
            []
        );

        $this->assertSame(['my_office'], $result['permitted_modules']);
        $this->assertSame(['my_office' => ['office_calendar']], $result['permitted_submodules']);
    }
}