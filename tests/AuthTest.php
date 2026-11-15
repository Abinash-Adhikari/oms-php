<?php
/**
 * SB-Tech — PHPUnit tests for Auth::isSuperAdmin() identity rules
 * (classes/Auth.php). DB-free: the user row is passed directly, so no
 * connection is needed (TEST-03/TEST-05).
 *
 * Run: vendor/bin/phpunit tests/AuthTest.php
 */
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../classes/Auth.php';

class AuthTest extends TestCase
{
    public function testStaffTypeAdminIsSuperAdmin(): void
    {
        $this->assertTrue(Auth::isSuperAdmin(['staff_type' => 'Admin']));
    }

    public function testStaffTypeServiceWithOrdinaryGrantsIsNotSuperAdmin(): void
    {
        $this->assertFalse(Auth::isSuperAdmin([
            'staff_type'        => 'Service',
            'role'              => 'Accountant',
            'permitted_modules' => '["my_office","payroll"]',
        ]));
    }

    public function testLegacyAllModulesAndRoleVariantsStillBypass(): void
    {
        $this->assertTrue(Auth::isSuperAdmin(['permitted_modules' => 'All', 'staff_type' => 'Service']));
        $this->assertTrue(Auth::isSuperAdmin(['role' => 'Super Admin', 'staff_type' => 'Service']));
        $this->assertTrue(Auth::isSuperAdmin(['role' => 'superadmin', 'staff_type' => 'Service']));
    }

    public function testEmptyRowIsNotSuperAdmin(): void
    {
        $this->assertFalse(Auth::isSuperAdmin([]));
    }
}
