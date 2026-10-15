<?php
/**
 * SB-Tech — Payroll data access.
 *
 * Loads what one staff member's pay depends on (salary structure, fund
 * enrollments, tax year + slabs) and shapes it into the input of the pure
 * calculator payroll_compute_line() in functions/payroll.php. Pages and the
 * (later) payroll run both go through here, so the estimate on Salary Setup
 * and the real run can never disagree.
 */
class PayrollService
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::instance();
    }

    /**
     * The tax year covering $date (Y-m-d), with its slabs attached under 'slabs'.
     *
     * @return array<string, mixed>|null
     */
    public function taxYearFor(string $date): ?array
    {
        $year = $this->db->selectOne(
            'SELECT * FROM `tbl_payroll_tax_years` WHERE ? BETWEEN `start_date` AND `end_date` ORDER BY `start_date` DESC LIMIT 1',
            [$date]
        );
        if (!$year) {
            return null;
        }
        $year['slabs'] = $this->slabs((int) $year['id']);

        return $year;
    }

    /** @return list<array<string, mixed>> */
    public function slabs(int $taxYearId): array
    {
        return $this->db->select(
            'SELECT * FROM `tbl_payroll_tax_slabs` WHERE `tax_year_id` = ? ORDER BY `filing_status`, `lower_limit`, `position`',
            [$taxYearId]
        );
    }

    /**
     * The salary structure in force on $date (latest effective_from ≤ date),
     * with its component items under 'items'.
     *
     * @return array<string, mixed>|null
     */
    public function structureFor(int $staffId, string $date): ?array
    {
        $structure = $this->db->selectOne(
            'SELECT * FROM `tbl_staff_salary_structures` WHERE `staff_id` = ? AND `effective_from` <= ?
             ORDER BY `effective_from` DESC LIMIT 1',
            [$staffId, $date]
        );
        if (!$structure) {
            return null;
        }
        $structure['items'] = $this->db->select(
            'SELECT c.`id` AS component_id, c.`code`, c.`name`, c.`kind`, c.`calc`, c.`taxable`, c.`prorate`, i.`value`
             FROM `tbl_staff_salary_structure_items` i
             JOIN `tbl_payroll_components` c ON c.`id` = i.`component_id`
             WHERE i.`structure_id` = ? AND c.`is_active` = 1
             ORDER BY c.`kind`, c.`position`, c.`name`',
            [(int) $structure['id']]
        );

        return $structure;
    }

    /**
     * Active fund enrollments on $date as calculator input:
     * list of ['fund' => tbl_payroll_funds row, 'enrollment' => tbl_staff_fund_enrollments row].
     *
     * @return list<array{fund: array<string, mixed>, enrollment: array<string, mixed>}>
     */
    public function enrollmentsFor(int $staffId, string $date): array
    {
        $rows = $this->db->select(
            'SELECT e.*, f.`code`, f.`name`, f.`employee_rate` AS f_employee_rate, f.`employer_rate` AS f_employer_rate,
                    f.`contribution_base`, f.`max_base`, f.`tax_deductible`, f.`waives_social_security_tax`
             FROM `tbl_staff_fund_enrollments` e
             JOIN `tbl_payroll_funds` f ON f.`id` = e.`fund_id`
             WHERE e.`staff_id` = ? AND f.`is_active` = 1
               AND (e.`start_date` IS NULL OR e.`start_date` <= ?)
               AND (e.`end_date` IS NULL OR e.`end_date` >= ?)
             ORDER BY f.`code`',
            [$staffId, $date, $date]
        );

        return array_map(static fn ($r) => [
            'fund' => [
                'code' => $r['code'], 'name' => $r['name'],
                'employee_rate' => $r['f_employee_rate'], 'employer_rate' => $r['f_employer_rate'],
                'contribution_base' => $r['contribution_base'], 'max_base' => $r['max_base'],
                'tax_deductible' => $r['tax_deductible'], 'waives_social_security_tax' => $r['waives_social_security_tax'],
            ],
            'enrollment' => [
                'member_no' => $r['member_no'], 'employee_rate' => $r['employee_rate'],
                'employee_fixed' => $r['employee_fixed'], 'employer_rate' => $r['employer_rate'],
            ],
        ], $rows);
    }

    /**
     * A monthly estimate for one staff member on $date: a full month worked,
     * annualised tax (12 months, nothing deducted yet). Returns null when the
     * staff member has no salary structure.
     *
     * @param array<string, mixed> $staff row of tbl_users_login (needs id, gender, tax_filing_status)
     * @return array<string, mixed>|null
     */
    public function estimate(array $staff, string $date, ?array $taxYear = null): ?array
    {
        $structure = $this->structureFor((int) $staff['id'], $date);
        if (!$structure) {
            return null;
        }
        $taxYear = $taxYear ?? $this->taxYearFor($date);

        return payroll_compute_line([
            'pay_type'           => $structure['pay_type'],
            'basic'              => (float) $structure['basic'],
            'tax_applicable'     => (bool) $structure['tax_applicable'] && $taxYear !== null,
            'deduct_unpaid_days' => (bool) $structure['deduct_unpaid_days'],
            'items'              => $structure['items'],
            'funds'              => $this->enrollmentsFor((int) $staff['id'], $date),
            'period_days'        => 30,
            'unpaid_days'        => 0,
            'tax'                => [
                'slabs'                 => payroll_slabs_for($taxYear['slabs'] ?? [], (string) ($staff['tax_filing_status'] ?? 'Single')),
                'retirement_cap'        => (float) ($taxYear['retirement_cap'] ?? 0),
                'retirement_fraction'   => (float) ($taxYear['retirement_fraction'] ?? 0),
                'female_rebate_percent' => (float) ($taxYear['female_rebate_percent'] ?? 0),
                'is_female'             => ($staff['gender'] ?? '') === 'Female',
                'months_remaining'      => 12,
            ],
        ]) + ['structure' => $structure];
    }
}
