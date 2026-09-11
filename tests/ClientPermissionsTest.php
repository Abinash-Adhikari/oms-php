<?php

/**
 * SB-Tech — PHPUnit tests for ClientPermissions (client-project module &
 * database entitlements). Pure logic: no DB connection required.
 *
 * Run: vendor/bin/phpunit tests/ClientPermissionsTest.php
 */
require_once __DIR__ . '/../classes/ClientPermissions.php';

use PHPUnit\Framework\TestCase;

class ClientPermissionsTest extends TestCase
{
    public function testProjectWithNoGrantsHasNoModules(): void
    {
        $project = ['permitted_modules' => null, 'permitted_submodules' => null, 'db_name' => null];
        $this->assertFalse(ClientPermissions::can($project, 'leads'));
        $this->assertFalse(ClientPermissions::hasModule($project, 'leads'));
        $this->assertNull(ClientPermissions::databaseFor($project, 'leads'));
        $this->assertNull(ClientPermissions::defaultDatabase($project));
    }

    public function testModuleGrant(): void
    {
        $project = ['permitted_modules' => json_encode(['leads', 'accounts'])];
        $this->assertTrue(ClientPermissions::hasModule($project, 'leads'));
        $this->assertTrue(ClientPermissions::can($project, 'leads'));
        $this->assertFalse(ClientPermissions::can($project, 'inventory'));
        $this->assertFalse(ClientPermissions::can($project, 'leads', 'clients')); // submodule not granted
    }

    public function testSubmoduleGrantCaseInsensitive(): void
    {
        $project = ['permitted_modules' => json_encode(['leads']), 'permitted_submodules' => json_encode(['leads' => ['leads', 'Projects']])];
        $this->assertTrue(ClientPermissions::can($project, 'leads', 'projects'));
        $this->assertTrue(ClientPermissions::can($project, 'leads', 'leads'));
        $this->assertFalse(ClientPermissions::can($project, 'leads', 'quotations'));
        // Module-level pages still pass without a submodule grant.
        $this->assertTrue(ClientPermissions::can($project, 'leads', ''));
        $this->assertTrue(ClientPermissions::can($project, 'leads', 'home'));
    }

    public function testDatabaseForUsesDbName(): void
    {
        $project = ['permitted_modules' => json_encode(['leads']), 'permitted_submodules' => json_encode([]), 'db_name' => 'client_main_db'];
        $this->assertSame('client_main_db', ClientPermissions::databaseFor($project, 'leads'));
        $this->assertSame('client_main_db', ClientPermissions::defaultDatabase($project));
        // Every granted module shares the project's single database.
        $project['permitted_modules'] = json_encode(['leads', 'accounts']);
        $this->assertSame('client_main_db', ClientPermissions::databaseFor($project, 'accounts'));
        // Ungranted module never resolves a database.
        $this->assertNull(ClientPermissions::databaseFor($project, 'inventory'));
    }

    public function testKnownDatabasesCollectsAll(): void
    {
        $project = [
            'permitted_modules'    => json_encode(['leads']),
            'permitted_submodules' => json_encode([]),
            'db_name'              => 'client_main_db',
        ];
        $this->assertEquals(['client_main_db'], ClientPermissions::knownDatabases($project));
        $this->assertSame([], ClientPermissions::knownDatabases(null));
        $this->assertSame([], ClientPermissions::knownDatabases(['db_name' => null]));
    }
}
