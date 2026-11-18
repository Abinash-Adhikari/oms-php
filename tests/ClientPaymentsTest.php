<?php
/**
 * SB-Tech — client payment & balance helpers (functions/sales.php).
 *
 * Pure-function tests (TEST-05) for invoice_status_for_payments(),
 * validate_client_payment(), client_balance_status(),
 * client_overdue_amount() and client_statement(). No database needed.
 */

use PHPUnit\Framework\TestCase;

class ClientPaymentsTest extends TestCase
{
    private const TODAY = '2026-11-17';

    // ── invoice_status_for_payments ─────────────────────────────────────

    public function testFullPaymentMarksPaid(): void
    {
        $this->assertSame('Paid', invoice_status_for_payments('Sent', 10000.0, 10000.0, '2026-12-01', self::TODAY));
    }

    public function testPaymentWithinToleranceMarksPaid(): void
    {
        $this->assertSame('Paid', invoice_status_for_payments('Sent', 10000.0, 9999.995, null, self::TODAY));
    }

    public function testPartPaymentMarksPartiallyPaid(): void
    {
        $this->assertSame('Partially Paid', invoice_status_for_payments('Overdue', 10000.0, 2500.0, '2026-10-01', self::TODAY));
    }

    public function testRemovingLastPaymentRevertsToOverdueWhenPastDue(): void
    {
        $this->assertSame('Overdue', invoice_status_for_payments('Paid', 10000.0, 0.0, '2026-10-01', self::TODAY));
    }

    public function testRemovingLastPaymentRevertsToSentWhenNotDue(): void
    {
        $this->assertSame('Sent', invoice_status_for_payments('Partially Paid', 10000.0, 0.0, '2026-12-01', self::TODAY));
    }

    public function testUnpaidDraftKeepsItsStatus(): void
    {
        $this->assertSame('Draft', invoice_status_for_payments('Draft', 10000.0, 0.0, '2026-10-01', self::TODAY));
    }

    // ── validate_client_payment ─────────────────────────────────────────

    private function input(array $over = []): array
    {
        return $over + ['payment_date' => '2026-11-10', 'amount' => '5000', 'method' => 'Cash', 'reference_no' => ' CHQ-1 ', 'notes' => ''];
    }

    public function testValidAdvancePaymentPasses(): void
    {
        $r = validate_client_payment($this->input(), null, 0.0, self::TODAY);
        $this->assertTrue($r['ok']);
        $this->assertSame(5000.0, $r['data']['amount']);
        $this->assertSame('CHQ-1', $r['data']['reference_no']);
        $this->assertNull($r['data']['notes']);
    }

    public function testZeroOrNegativeAmountRejected(): void
    {
        $this->assertFalse(validate_client_payment($this->input(['amount' => '0']), null, 0.0, self::TODAY)['ok']);
        $this->assertFalse(validate_client_payment($this->input(['amount' => '-5']), null, 0.0, self::TODAY)['ok']);
        $this->assertFalse(validate_client_payment($this->input(['amount' => 'abc']), null, 0.0, self::TODAY)['ok']);
    }

    public function testFutureOrInvalidDateRejected(): void
    {
        $this->assertFalse(validate_client_payment($this->input(['payment_date' => '2026-11-18']), null, 0.0, self::TODAY)['ok']);
        $this->assertFalse(validate_client_payment($this->input(['payment_date' => '2026-02-30']), null, 0.0, self::TODAY)['ok']);
    }

    public function testUnknownMethodRejected(): void
    {
        $this->assertFalse(validate_client_payment($this->input(['method' => 'Bitcoin']), null, 0.0, self::TODAY)['ok']);
    }

    public function testOverpayingInvoiceRejected(): void
    {
        $invoice = ['document_number' => 'INV-2026-0001', 'total' => '10000.0000', 'status' => 'Partially Paid'];
        $r = validate_client_payment($this->input(['amount' => '6000']), $invoice, 5000.0, self::TODAY);
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('5,000.00', $r['error']);
    }

    public function testPayingExactRemainderAccepted(): void
    {
        $invoice = ['document_number' => 'INV-2026-0001', 'total' => '10000.0000', 'status' => 'Partially Paid'];
        $this->assertTrue(validate_client_payment($this->input(['amount' => '5000']), $invoice, 5000.0, self::TODAY)['ok']);
    }

    public function testCancelledInvoiceRejected(): void
    {
        $invoice = ['document_number' => 'INV-2026-0002', 'total' => '10000', 'status' => 'Cancelled'];
        $this->assertFalse(validate_client_payment($this->input(), $invoice, 0.0, self::TODAY)['ok']);
    }

    // ── client_balance_status / client_overdue_amount ───────────────────

    public function testBalanceStatusLabels(): void
    {
        $this->assertSame('Overdue', client_balance_status(10000, 6000, 6000));
        $this->assertSame('Due', client_balance_status(10000, 6000, 0));
        $this->assertSame('Advance', client_balance_status(10000, -2000, 0));
        $this->assertSame('Settled', client_balance_status(10000, 0.004, 0));
        $this->assertSame('No invoices', client_balance_status(0, 0, 0));
    }

    public function testOverdueAmountCountsOnlyUnpaidPastDueIssuedInvoices(): void
    {
        $invoices = [
            ['total' => 10000, 'paid' => 4000, 'due_date' => '2026-10-01', 'status' => 'Partially Paid'], // 6000 overdue
            ['total' => 5000, 'paid' => 0, 'due_date' => '2026-12-01', 'status' => 'Sent'],             // not due yet
            ['total' => 3000, 'paid' => 0, 'due_date' => '2026-09-01', 'status' => 'Draft'],            // not issued
            ['total' => 2000, 'paid' => 2000, 'due_date' => '2026-09-01', 'status' => 'Paid'],          // settled
            ['total' => 1000, 'paid' => 0, 'due_date' => null, 'status' => 'Sent'],                     // no due date
        ];
        $this->assertSame(6000.0, client_overdue_amount($invoices, self::TODAY));
    }

    // ── client_statement ────────────────────────────────────────────────

    public function testStatementRunsBalanceInDateOrder(): void
    {
        $invoices = [
            ['id' => 2, 'document_number' => 'INV-2', 'document_date' => '2026-03-01', 'total' => '3000', 'status' => 'Sent', 'subject' => 'AMC'],
            ['id' => 1, 'document_number' => 'INV-1', 'document_date' => '2026-01-10', 'total' => '10000', 'status' => 'Paid', 'subject' => 'ERP'],
            ['id' => 3, 'document_number' => 'INV-3', 'document_date' => '2026-02-01', 'total' => '999', 'status' => 'Draft', 'subject' => null],
        ];
        $payments = [
            ['id' => 7, 'payment_date' => '2026-01-10', 'amount' => '10000', 'method' => 'Cheque', 'reference_no' => 'C1', 'document_number' => 'INV-1'],
            ['id' => 8, 'payment_date' => '2026-04-01', 'amount' => '5000', 'method' => 'Cash', 'reference_no' => null, 'document_number' => null],
        ];
        $rows = client_statement($invoices, $payments);

        $this->assertCount(4, $rows); // the Draft invoice is left out
        $this->assertSame(['INV-1', 'C1', 'INV-2', ''], array_column($rows, 'ref'));
        $this->assertSame([10000.0, 0.0, 3000.0, -2000.0], array_column($rows, 'balance'));
        $this->assertSame('Cash · Advance', $rows[3]['detail']);
    }

    public function testEmptyStatement(): void
    {
        $this->assertSame([], client_statement([], []));
    }
}
