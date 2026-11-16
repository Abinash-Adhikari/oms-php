<?php
/**
 * SB-Tech — sales helpers (lead contact persons).
 *
 * Pure-function tests for normalize_lead_contacts() plus DB tests for
 * sync_lead_contacts() / copy_lead_contacts_to_client() (TEST-05); the DB
 * tests skip themselves when no database is reachable (TEST-03).
 */

use PHPUnit\Framework\TestCase;

class SalesTest extends TestCase
{
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

    private function makeScratchLead(Database $db): int
    {
        return (int) $db->insert('tbl_leads', ['source' => 'Other']);
    }

    private function makeScratchClient(Database $db): int
    {
        return (int) $db->insert('tbl_clients', ['name' => 'PHPUnit Scratch Client']);
    }

    // --- normalize_lead_contacts (pure) -----------------------------------

    public function testNormalizeHappyPathTrimsAndMarksFirstPrimary(): void
    {
        $rows = normalize_lead_contacts(
            ['  Ram Hari  ', 'Sita Devi'],
            ['ram@example.com', '  '],
            ['9841000001', '']
        );

        $this->assertCount(2, $rows);
        $this->assertSame('Ram Hari', $rows[0]['name']);
        $this->assertSame('ram@example.com', $rows[0]['email']);
        $this->assertSame('9841000001', $rows[0]['phone']);
        $this->assertSame(1, $rows[0]['is_primary']);
        $this->assertSame('Sita Devi', $rows[1]['name']);
        $this->assertNull($rows[1]['email']);
        $this->assertSame(0, $rows[1]['is_primary']);
    }

    public function testNormalizeDropsNamelessRowsAndSurvivorBecomesPrimary(): void
    {
        $rows = normalize_lead_contacts(
            ['', '   ', 'Gopal Thapa'],
            ['ghost@example.com', 'also@ghost.example', 'gopal@example.com'],
            ['9841111111', '9841222222', '9841333333']
        );

        $this->assertCount(1, $rows);
        $this->assertSame('Gopal Thapa', $rows[0]['name']);
        $this->assertSame('gopal@example.com', $rows[0]['email']);
        $this->assertSame('9841333333', $rows[0]['phone']);
        $this->assertSame(1, $rows[0]['is_primary']);
    }

    public function testNormalizeAllEmptyReturnsNoRows(): void
    {
        $this->assertSame([], normalize_lead_contacts(['', '  '], [], []));
    }

    public function testNormalizeMissingParallelEntriesBecomeNull(): void
    {
        $rows = normalize_lead_contacts(['Only Name'], [], []);

        $this->assertCount(1, $rows);
        $this->assertNull($rows[0]['email']);
        $this->assertNull($rows[0]['phone']);
        $this->assertSame(1, $rows[0]['is_primary']);
    }

    // --- sync_lead_contacts (DB) ------------------------------------------

    public function testSyncReplacesRowsAndMirrorsPrimaryOntoLead(): void
    {
        if (!$this->dbAvailable()) {
            $this->markTestSkipped('Database not available for lead contact sync.');
        }
        $db = Database::instance();
        $leadId = $this->makeScratchLead($db);
        try {
            sync_lead_contacts($db, $leadId, normalize_lead_contacts(
                ['Ram Hari', 'Sita Devi'],
                ['ram@example.com', ''],
                ['9841000001', '9841000002']
            ));

            $rows = $db->select(
                'SELECT `name`, `email`, `phone`, `is_primary` FROM `tbl_lead_contacts`
                 WHERE `lead_id` = ? ORDER BY `is_primary` DESC, `id` ASC',
                [$leadId]
            );
            $this->assertCount(2, $rows);
            $this->assertSame('Ram Hari', $rows[0]['name']);
            $this->assertSame(1, (int) $rows[0]['is_primary']);
            $this->assertSame(0, (int) $rows[1]['is_primary']);

            $lead = $db->selectOne('SELECT `contact_name`, `email`, `phone` FROM `tbl_leads` WHERE `id` = ?', [$leadId]);
            $this->assertSame('Ram Hari', $lead['contact_name']);
            $this->assertSame('ram@example.com', $lead['email']);
            $this->assertSame('9841000001', $lead['phone']);

            sync_lead_contacts($db, $leadId, normalize_lead_contacts(['Gopal Thapa'], [], []));
            $rows = $db->select('SELECT `name`, `is_primary` FROM `tbl_lead_contacts` WHERE `lead_id` = ?', [$leadId]);
            $this->assertCount(1, $rows);
            $this->assertSame('Gopal Thapa', $rows[0]['name']);

            $lead = $db->selectOne('SELECT `contact_name`, `email` FROM `tbl_leads` WHERE `id` = ?', [$leadId]);
            $this->assertSame('Gopal Thapa', $lead['contact_name']);
            $this->assertNull($lead['email']);
        } finally {
            $db->delete('tbl_leads', '`id` = ?', [$leadId]);
        }
    }

    public function testSyncWithNoContactsClearsRowsAndMirror(): void
    {
        if (!$this->dbAvailable()) {
            $this->markTestSkipped('Database not available for lead contact sync.');
        }
        $db = Database::instance();
        $leadId = $this->makeScratchLead($db);
        try {
            sync_lead_contacts($db, $leadId, normalize_lead_contacts(['First Person'], ['a@example.com'], ['9841111111']));
            sync_lead_contacts($db, $leadId, []);

            $count = $db->selectOne('SELECT COUNT(*) AS c FROM `tbl_lead_contacts` WHERE `lead_id` = ?', [$leadId]);
            $this->assertSame(0, (int) $count['c']);

            $lead = $db->selectOne('SELECT `contact_name`, `email`, `phone` FROM `tbl_leads` WHERE `id` = ?', [$leadId]);
            $this->assertNull($lead['contact_name']);
            $this->assertNull($lead['email']);
            $this->assertNull($lead['phone']);
        } finally {
            $db->delete('tbl_leads', '`id` = ?', [$leadId]);
        }
    }

    // --- copy_lead_contacts_to_client (DB) --------------------------------

    public function testCopyUsesLeadPrimaryWhenNoOverride(): void
    {
        if (!$this->dbAvailable()) {
            $this->markTestSkipped('Database not available for contact copy.');
        }
        $db = Database::instance();
        $leadId = $this->makeScratchLead($db);
        $clientId = $this->makeScratchClient($db);
        try {
            sync_lead_contacts($db, $leadId, normalize_lead_contacts(
                ['Ram Hari', 'Sita Devi'],
                ['ram@example.com', 'sita@example.com'],
                ['9841000001', '9841000002']
            ));
            copy_lead_contacts_to_client($db, $leadId, $clientId);

            $rows = $db->select(
                'SELECT `name`, `is_primary` FROM `tbl_client_contacts`
                 WHERE `client_id` = ? ORDER BY `is_primary` DESC, `id` ASC',
                [$clientId]
            );
            $this->assertCount(2, $rows);
            $this->assertSame('Ram Hari', $rows[0]['name']);
            $this->assertSame(1, (int) $rows[0]['is_primary']);
            $this->assertSame('Sita Devi', $rows[1]['name']);
            $this->assertSame(0, (int) $rows[1]['is_primary']);
        } finally {
            $db->delete('tbl_clients', '`id` = ?', [$clientId]);
            $db->delete('tbl_leads', '`id` = ?', [$leadId]);
        }
    }

    public function testCopyInsertsTypedOverrideAsExtraPrimary(): void
    {
        if (!$this->dbAvailable()) {
            $this->markTestSkipped('Database not available for contact copy.');
        }
        $db = Database::instance();
        $leadId = $this->makeScratchLead($db);
        $clientId = $this->makeScratchClient($db);
        try {
            sync_lead_contacts($db, $leadId, normalize_lead_contacts(
                ['Ram Hari', 'Sita Devi'],
                ['ram@example.com', 'sita@example.com'],
                ['9841000001', '9841000002']
            ));
            copy_lead_contacts_to_client($db, $leadId, $clientId, 'Anjali Sharma');

            $rows = $db->select(
                'SELECT `name`, `is_primary` FROM `tbl_client_contacts`
                 WHERE `client_id` = ? ORDER BY `is_primary` DESC, `id` ASC',
                [$clientId]
            );
            $this->assertCount(3, $rows);
            $this->assertSame('Anjali Sharma', $rows[0]['name']);
            $this->assertSame(1, (int) $rows[0]['is_primary']);

            $primaries = $db->selectOne(
                'SELECT COUNT(*) AS c FROM `tbl_client_contacts` WHERE `client_id` = ? AND `is_primary` = 1',
                [$clientId]
            );
            $this->assertSame(1, (int) $primaries['c']);
        } finally {
            $db->delete('tbl_clients', '`id` = ?', [$clientId]);
            $db->delete('tbl_leads', '`id` = ?', [$leadId]);
        }
    }

    // --- normalize_portfolio_clients (pure) --------------------------------

    public function testPortfolioClientsMixSalesAndFreeTextInOrder(): void
    {
        $out = normalize_portfolio_clients(
            ['client:7', '  Ministry of Agriculture  ', 'client:9'],
            [7 => 'Himalayan Bank Ltd.', 9 => 'Nepal Tourism Board']
        );
        $this->assertSame([
            ['client_id' => 7, 'client_name' => 'Himalayan Bank Ltd.'],
            ['client_id' => null, 'client_name' => 'Ministry of Agriculture'],
            ['client_id' => 9, 'client_name' => 'Nepal Tourism Board'],
        ], $out);
    }

    public function testPortfolioClientsEmptyMeansNoClients(): void
    {
        $this->assertSame([], normalize_portfolio_clients([], [7 => 'X']));
        $this->assertSame([], normalize_portfolio_clients(['', '   ', null], [7 => 'X']));
    }

    public function testPortfolioClientsDropsUnknownIdsAndDuplicates(): void
    {
        $out = normalize_portfolio_clients(
            ['client:7', 'client:7', 'client:999', 'Acme', 'ACME ', ['nested']],
            [7 => 'Himalayan Bank Ltd.']
        );
        $this->assertSame([
            ['client_id' => 7, 'client_name' => 'Himalayan Bank Ltd.'],
            ['client_id' => null, 'client_name' => 'Acme'],
        ], $out);
    }

    public function testPortfolioClientsCapsNameLength(): void
    {
        $out = normalize_portfolio_clients([str_repeat('a', 300)], []);
        $this->assertSame(191, mb_strlen($out[0]['client_name']));
    }
}
