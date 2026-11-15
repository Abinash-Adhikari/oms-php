<?php
/**
 * SB-Tech — Correspondence helpers (darta / chalani registers).
 *
 * Pure display tests always run; allocation tests need a live DB with the
 * correspondence migrations applied and skip otherwise (TEST-03).
 */

use PHPUnit\Framework\TestCase;

class CorrespondenceTest extends TestCase
{
    // --- correspondence_display_no (pure) ---------------------------------

    public function testDisplayNoDartaHappy(): void
    {
        $this->assertSame('D-12/2082-83', correspondence_display_no('darta', 12, '2082-83'));
    }

    public function testDisplayNoChalaniHappy(): void
    {
        $this->assertSame('C-7/2082-83', correspondence_display_no('chalani', 7, '2082-83'));
    }

    public function testDisplayNoFirstOfFiscalYear(): void
    {
        $this->assertSame('D-1/2082-83', correspondence_display_no('darta', 1, '2082-83'));
        $this->assertSame('C-1/2083-84', correspondence_display_no('chalani', 1, '2083-84'));
    }

    public function testDisplayNoRejectsUnknownRegister(): void
    {
        $this->expectException(InvalidArgumentException::class);
        correspondence_display_no('memo', 1, '2082-83');
    }

    // --- correspondence_can_dispose (pure) --------------------------------

    public function testCanDisposeOpenStatuses(): void
    {
        $this->assertTrue(correspondence_can_dispose('Pending'));
        $this->assertTrue(correspondence_can_dispose('In Progress'));
    }

    public function testCanDisposeRejectsClosedAndUnknown(): void
    {
        $this->assertFalse(correspondence_can_dispose('Disposed'));
        $this->assertFalse(correspondence_can_dispose('Filed'));
        $this->assertFalse(correspondence_can_dispose('Cancelled'));
        $this->assertFalse(correspondence_can_dispose(''));
        $this->assertFalse(correspondence_can_dispose(null));
    }

    // --- correspondence_bodharth (pure) -----------------------------------

    public function testBodharthSplitsLines(): void
    {
        $this->assertSame(
            ['Office of the Registrar', 'Accounts Section'],
            correspondence_bodharth("Office of the Registrar\nAccounts Section")
        );
        $this->assertSame(
            ['A', 'B'],
            correspondence_bodharth("  A  \r\n\r\n B \n")
        );
    }

    public function testBodharthEmptyInput(): void
    {
        $this->assertSame([], correspondence_bodharth(null));
        $this->assertSame([], correspondence_bodharth(''));
        $this->assertSame([], correspondence_bodharth("  \n \n"));
    }

    // --- correspondence_print_columns (pure) ------------------------------

    public function testPrintColumnsDarta(): void
    {
        $cols = correspondence_print_columns('darta');

        $this->assertCount(6, $cols);
        $this->assertSame('दर्ता नं.', $cols[0]['np']);
        $this->assertSame('Darta No.', $cols[0]['en']);
        $this->assertSame('कारबाही', $cols[5]['np']);
    }

    public function testPrintColumnsChalani(): void
    {
        $cols = correspondence_print_columns('chalani');

        $this->assertCount(6, $cols);
        $this->assertSame('चलानी नं.', $cols[0]['np']);
        $this->assertNotSame(correspondence_print_columns('darta'), $cols);
    }

    public function testPrintColumnsRejectsUnknownRegister(): void
    {
        $this->expectException(InvalidArgumentException::class);
        correspondence_print_columns('memo');
    }

    // --- correspondence_devanagari_digits (pure) ---------------------------

    public function testDevanagariDigitsHappy(): void
    {
        $this->assertSame('२०८३-०६-३०', correspondence_devanagari_digits('2083-06-30'));
        $this->assertSame('D-१२/२०८२-८३', correspondence_devanagari_digits('D-12/2082-83'));
    }

    public function testDevanagariDigitsEdges(): void
    {
        $this->assertSame('', correspondence_devanagari_digits(''));
        $this->assertSame('No digits here', correspondence_devanagari_digits('No digits here'));
        // Letters/digits mix converts digits only.
        $this->assertSame('Grade ५', correspondence_devanagari_digits('Grade 5'));
        // Idempotent on already-converted text.
        $this->assertSame('२०८३', correspondence_devanagari_digits('२०८३'));
    }

    // --- correspondence_dispose_recipients (pure) --------------------------

    public function testDisposeRecipientsRegistrarAndHandler(): void
    {
        $ids = correspondence_dispose_recipients(
            ['added_by' => 7, 'assigned_to' => 9],
            1
        );

        $this->assertSame([7, 9], $ids);
    }

    public function testDisposeRecipientsEdges(): void
    {
        // Actor excluded; nulls/zeros excluded; duplicates collapse.
        $this->assertSame([7], correspondence_dispose_recipients(['added_by' => 7, 'assigned_to' => 7], 3));
        $this->assertSame([], correspondence_dispose_recipients(['added_by' => null, 'assigned_to' => null], 1));
        $this->assertSame([], correspondence_dispose_recipients(['added_by' => 5, 'assigned_to' => 5], 5));
        $this->assertSame([], correspondence_dispose_recipients([], 1));
    }

    // --- correspondence_allocate_no (DB-dependent) ------------------------

    private function db(): ?Database
    {
        try {
            if (!class_exists('Database')) {
                return null;
            }
            $db = Database::instance();
            if ($db->selectOne("SHOW TABLES LIKE 'tbl_correspondence_counters'") === null
                || $db->selectOne("SHOW TABLES LIKE 'tbl_fiscal_years'") === null) {
                return null;
            }

            return $db;
        } catch (Throwable $e) {
            return null;
        }
    }

    /** Insert an isolated fiscal year; returns its id, or skips the test. */
    private function makeFy(Database $db): int
    {
        $title = 'TEST-' . substr(md5(uniqid('', true)), 0, 8);
        $id = $db->insert('tbl_fiscal_years', [
            'title'         => $title,
            'starting_date' => '2090-01-01',
            'ending_date'   => '2090-12-31',
            'closing'       => 'Open',
        ]);
        if ($id < 1) {
            $this->markTestSkipped('Could not seed a test fiscal year.');
        }

        return $id;
    }

    private function dropFy(Database $db, int $fyId): void
    {
        $db->delete('tbl_correspondence_counters', '`fiscal_year_id` = ?', [$fyId]);
        $db->delete('tbl_fiscal_years', '`id` = ?', [$fyId]);
    }

    public function testAllocateNoStartsAtOnePerFy(): void
    {
        if (($db = $this->db()) === null) {
            $this->markTestSkipped('Database / correspondence migrations not available.');
        }
        $fyId = $this->makeFy($db);
        try {
            $first = $db->transaction(static fn (Database $db): int => correspondence_allocate_no($db, 'darta', $fyId));
            $second = $db->transaction(static fn (Database $db): int => correspondence_allocate_no($db, 'darta', $fyId));

            $this->assertSame(1, $first);
            $this->assertSame(2, $second);
        } finally {
            $this->dropFy($db, $fyId);
        }
    }

    public function testAllocateNoIndependentAcrossRegistersAndFiscalYears(): void
    {
        if (($db = $this->db()) === null) {
            $this->markTestSkipped('Database / correspondence migrations not available.');
        }
        $fyA = $this->makeFy($db);
        $fyB = $this->makeFy($db);
        try {
            $dartaA = $db->transaction(static fn (Database $db): int => correspondence_allocate_no($db, 'darta', $fyA));
            $chalaniA = $db->transaction(static fn (Database $db): int => correspondence_allocate_no($db, 'chalani', $fyA));
            $dartaB = $db->transaction(static fn (Database $db): int => correspondence_allocate_no($db, 'darta', $fyB));
            $dartaA2 = $db->transaction(static fn (Database $db): int => correspondence_allocate_no($db, 'darta', $fyA));

            $this->assertSame(1, $dartaA, 'First darta number of FY A is 1.');
            $this->assertSame(1, $chalaniA, 'Chalani counter is independent of darta.');
            $this->assertSame(1, $dartaB, 'A new FY restarts at 1.');
            $this->assertSame(2, $dartaA2, 'Sequential within the same FY.');
        } finally {
            $this->dropFy($db, $fyA);
            $this->dropFy($db, $fyB);
        }
    }

    public function testAllocateNoRejectsUnknownRegister(): void
    {
        // Register/fiscal-year validation runs before any query, so the test
        // needs a Database *instance* but no connection: build one without the
        // constructor instead of skipping when no DB is reachable.
        $db = (new ReflectionClass(Database::class))->newInstanceWithoutConstructor();

        $this->expectException(InvalidArgumentException::class);
        correspondence_allocate_no($db, 'memo', 1);
    }

    public function testAllocateNoRejectsInvalidFiscalYear(): void
    {
        $db = (new ReflectionClass(Database::class))->newInstanceWithoutConstructor();

        $this->expectException(InvalidArgumentException::class);
        correspondence_allocate_no($db, 'darta', 0);
    }
}
