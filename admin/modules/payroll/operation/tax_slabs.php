<?php
/**
 * SB-Tech — Payroll / Tax Slabs writes (PRG).
 * Actions: save_year (create / update, optional copy-from), save_slabs
 * (replace a year's bands after validation), delete_year.
 */
$back = pageUrl('payroll', 'tax_slabs');
if (!Auth::hasSpecial('manage_payroll')) {
    setFlash('error', 'You need the Manage payroll permission.');
    redirect($back);
    exit;
}
$db = Database::instance();
$action = (string) ($_POST['action'] ?? '');
$me = (int) Auth::id();

if ($action === 'save_year') {
    $id = (int) ($_POST['id'] ?? 0);
    $title = trim((string) ($_POST['title'] ?? ''));
    // Date inputs always post AD (the BS picker swaps in a hidden AD field).
    $isDate = static fn (string $d): bool => (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false;
    $start = (string) ($_POST['start_date'] ?? '');
    $end = (string) ($_POST['end_date'] ?? '');
    if ($title === '' || !$isDate($start) || !$isDate($end) || $end <= $start) {
        setFlash('error', 'A title and a valid start and end date (end after start) are required.');
        redirect($back . ($id ? '&year=' . $id : ''));
        exit;
    }
    $overlap = $db->selectOne(
        'SELECT `title` FROM `tbl_payroll_tax_years` WHERE `id` <> ? AND `start_date` <= ? AND `end_date` >= ? LIMIT 1',
        [$id, $end, $start]
    );
    if ($overlap) {
        setFlash('error', 'Those dates overlap FY ' . $overlap['title'] . '. Each date can belong to one tax year only.');
        redirect($back . ($id ? '&year=' . $id : ''));
        exit;
    }
    $row = ['title' => $title, 'start_date' => $start, 'end_date' => $end];

    try {
        $savedId = $db->transaction(function (Database $db) use ($id, $row, $me): int {
            if ($id > 0) {
                $old = $db->selectOne('SELECT * FROM `tbl_payroll_tax_years` WHERE `id` = ?', [$id]);
                $row += [
                    'retirement_cap'        => max(0.0, (float) ($_POST['retirement_cap'] ?? 0)),
                    'retirement_fraction'   => max(0.0, min(100.0, (float) ($_POST['retirement_percent'] ?? 0))) / 100,
                    'female_rebate_percent' => max(0.0, min(100.0, (float) ($_POST['female_rebate_percent'] ?? 0))),
                    'notes'                 => trim((string) ($_POST['notes'] ?? '')),
                    'updated_by'            => $me,
                ];
                $db->update('tbl_payroll_tax_years', $row, '`id` = ?', [$id]);
                auditLog('payroll', 'tax_year_updated', 'tax_year', $id, $old, $row);

                return $id;
            }
            $copyFrom = (int) ($_POST['copy_from'] ?? 0);
            $source = $copyFrom ? $db->selectOne('SELECT * FROM `tbl_payroll_tax_years` WHERE `id` = ?', [$copyFrom]) : null;
            $row += [
                'retirement_cap'        => $source['retirement_cap'] ?? 0,
                'retirement_fraction'   => $source['retirement_fraction'] ?? 0,
                'female_rebate_percent' => $source['female_rebate_percent'] ?? 0,
                'notes'                 => $source ? 'Copied from FY ' . $source['title'] . ' — update for the new budget.' : null,
                'added_by'              => $me,
            ];
            $newId = $db->insert('tbl_payroll_tax_years', $row);
            if ($source) {
                foreach ($db->select('SELECT * FROM `tbl_payroll_tax_slabs` WHERE `tax_year_id` = ?', [$copyFrom]) as $s) {
                    $db->insert('tbl_payroll_tax_slabs', [
                        'tax_year_id' => $newId, 'filing_status' => $s['filing_status'], 'lower_limit' => $s['lower_limit'],
                        'upper_limit' => $s['upper_limit'], 'rate' => $s['rate'],
                        'is_social_security_tax' => $s['is_social_security_tax'], 'position' => $s['position'],
                    ]);
                }
            }
            auditLog('payroll', 'tax_year_created', 'tax_year', $newId, null, $row);

            return $newId;
        });
    } catch (Throwable $e) {
        setFlash('error', str_contains($e->getMessage(), 'Duplicate') ? 'A tax year with that title already exists.' : 'Could not save the tax year.');
        redirect($back . ($id ? '&year=' . $id : ''));
        exit;
    }
    setFlash('success', $id ? 'Tax year updated.' : 'Tax year created.');
    redirect($back . '&year=' . $savedId);
    exit;
}

if ($action === 'save_slabs') {
    $yearId = (int) ($_POST['tax_year_id'] ?? 0);
    $year = $db->selectOne('SELECT * FROM `tbl_payroll_tax_years` WHERE `id` = ?', [$yearId]);
    if (!$year) {
        setFlash('error', 'Tax year not found.');
        redirect($back);
        exit;
    }
    $statuses = (array) ($_POST['filing_status'] ?? []);
    $lowers = (array) ($_POST['lower_limit'] ?? []);
    $uppers = (array) ($_POST['upper_limit'] ?? []);
    $rates = (array) ($_POST['rate'] ?? []);
    $sst = (array) ($_POST['sst'] ?? []);

    $rows = [];
    foreach ($lowers as $i => $lower) {
        if (trim((string) $lower) === '' && trim((string) ($rates[$i] ?? '')) === '') {
            continue; // blank row
        }
        $upper = trim((string) ($uppers[$i] ?? ''));
        $rows[] = [
            'filing_status'          => in_array($statuses[$i] ?? 'All', ['All', 'Single', 'Couple'], true) ? $statuses[$i] : 'All',
            'lower_limit'            => (float) $lower,
            'upper_limit'            => $upper === '' ? null : (float) $upper,
            'rate'                   => (float) ($rates[$i] ?? 0),
            'is_social_security_tax' => !empty($sst[$i]) ? 1 : 0,
        ];
    }

    // Validate each schedule (Everyone / Single / Couple) before touching the DB.
    $problems = [];
    foreach (array_unique(array_column($rows, 'filing_status')) as $st) {
        foreach (payroll_tax_slab_problems(payroll_slabs_for($rows, $st)) as $p) {
            $problems[] = ($st === 'All' ? '' : $st . ': ') . $p;
        }
    }
    if (!$rows) {
        $problems[] = 'Add at least one band.';
    }
    // Every filing status must be covered: an 'Everyone' schedule, or both a
    // Single and a Couple one — otherwise that status would be taxed at 0.
    $statuses = array_unique(array_column($rows, 'filing_status'));
    if ($rows && !in_array('All', $statuses, true) && !(in_array('Single', $statuses, true) && in_array('Couple', $statuses, true))) {
        $missing = in_array('Single', $statuses, true) ? 'Couple' : 'Single';
        $problems[] = 'No bands cover ' . $missing . ' filers — add an “Everyone” schedule or a ' . $missing . ' schedule.';
    }
    if ($problems) {
        setFlash('error', 'Bands not saved. ' . implode(' ', $problems));
        redirect($back . '&year=' . $yearId);
        exit;
    }

    $db->transaction(function (Database $db) use ($yearId, $rows, $me): void {
        $old = $db->select('SELECT `filing_status`, `lower_limit`, `upper_limit`, `rate`, `is_social_security_tax` FROM `tbl_payroll_tax_slabs` WHERE `tax_year_id` = ?', [$yearId]);
        $db->delete('tbl_payroll_tax_slabs', '`tax_year_id` = ?', [$yearId]);
        foreach ($rows as $pos => $r) {
            $db->insert('tbl_payroll_tax_slabs', $r + ['tax_year_id' => $yearId, 'position' => $pos + 1]);
        }
        $db->update('tbl_payroll_tax_years', ['updated_by' => $me], '`id` = ?', [$yearId]);
        auditLog('payroll', 'tax_slabs_updated', 'tax_year', $yearId, $old, $rows);
    });
    setFlash('success', count($rows) . ' bands saved for FY ' . $year['title'] . '.');
    redirect($back . '&year=' . $yearId);
    exit;
}

if ($action === 'delete_year') {
    $id = (int) ($_POST['id'] ?? 0);
    $year = $db->selectOne('SELECT * FROM `tbl_payroll_tax_years` WHERE `id` = ?', [$id]);
    $usedBy = $year ? (int) ($db->selectOne('SELECT COUNT(*) AS c FROM `tbl_payroll_runs` WHERE `tax_year_id` = ?', [$id])['c'] ?? 0) : 0;
    if ($usedBy > 0) {
        setFlash('error', 'FY ' . $year['title'] . ' is used by ' . $usedBy . ' payroll run' . ($usedBy === 1 ? '' : 's') . ' and can’t be deleted. Edit its bands instead.');
        redirect($back . '&year=' . $id);
        exit;
    }
    if ($year) {
        $db->transaction(function (Database $db) use ($id, $year): void {
            $db->delete('tbl_payroll_tax_years', '`id` = ?', [$id]); // bands cascade
            auditLog('payroll', 'tax_year_deleted', 'tax_year', $id, $year, null);
        });
        setFlash('success', 'FY ' . $year['title'] . ' deleted.');
    }
    redirect($back);
    exit;
}

setFlash('error', 'Unknown action.');
redirect($back);
exit;
