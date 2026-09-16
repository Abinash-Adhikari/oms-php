<?php

/**
 * SB-Tech — shared helpers. Loaded by config/bootstrap.php.
 *
 * Security posture (X-01, X-02, X-10): every helper used for output or
 * redirect is HTML-escaped / validated; all DB writes go through
 * Database::* prepared statements.
 */

/**
 * Sanitize an embed code (e.g. Google Maps iframe) for safe HTML output.
 * Allows only <iframe> tags with safe attributes from trusted sources;
 * strips everything else (scripts, event handlers, javascript: URIs).
 */
function sanitizeEmbed(string $html): string
{
    // Remove everything except iframe tags with src attribute.
    // Step 1: strip script/style/noscript tags and their content.
    $html = preg_replace('#<\s*(script|style|noscript)[^>]*>.*?</\s*\1\s*>#is', '', $html);
    // Step 2: strip event handler attributes (onclick, onerror, onload, etc.).
    $html = preg_replace('#\s+on\w+\s*=\s*["\'][^"\']*["\']#i', '', $html);
    $html = preg_replace('#\s+on\w+\s*=\s*\S+#i', '', $html);
    // Step 3: strip javascript: and data: URIs.
    $html = preg_replace('#(src|href|action)\s*=\s*["\']?\s*\w+script:#i', '$1="#"', $html);
    $html = preg_replace('#(src|href|action)\s*=\s*["\']?\s*data:#i', '$1="#"', $html);
    // Step 4: only allow iframe tags — strip everything else.
    if (preg_match_all('#<iframe\b[^>]*>.*?</iframe>#is', $html, $m)) {
        return implode('', $m[0]);
    }
    // Also match self-closing iframes.
    if (preg_match_all('#<iframe\b[^>]*/\s*>#is', $html, $m)) {
        return implode('', $m[0]);
    }
    return '';
}

/** Escape output for HTML contexts (use everywhere you echo user data). */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * Sanitize rich-text HTML (e.g. Summernote output) for safe storage/display.
 * Keeps basic formatting tags that the screen + print/PDF (Dompdf) renderers
 * can handle; removes scripts, event handlers, javascript:/data: URIs and any
 * non-whitelisted tags.
 */
function sanitizeRichHtml(?string $html): string
{
    $html = (string) $html;
    if (trim($html) === '') {
        return '';
    }
    // Remove whole blocks of unsafe elements (including their content).
    $html = preg_replace('#<\s*(script|style|noscript|iframe|object|embed|form|input|button|textarea|select|option|link|meta)\b[^>]*>.*?</\s*\1\s*>#is', '', $html);
    // Remove standalone / self-closing unsafe elements.
    $html = preg_replace('#<\s*(script|style|noscript|iframe|object|embed|form|input|button|textarea|select|option|link|meta)\b[^>]*/?>#i', '', $html);
    // Strip event handler attributes.
    $html = preg_replace('#\s+on\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $html);
    // Neutralize javascript:/data: URIs.
    $html = preg_replace('#(href|src)\s*=\s*["\']?\s*(javascript|data)\s*:#i', '$1="#"', $html);
    // Allow only safe formatting tags.
    $allowed = '<p><br><b><strong><i><em><u><s><strike><ul><ol><li><a><span><div><blockquote><pre><code><hr><table><thead><tbody><tfoot><tr><td><th><h1><h2><h3><h4><h5><h6>';
    return trim(strip_tags($html, $allowed));
}

/**
 * Render a notes-style value safely. Legacy plain text (no markup) keeps its
 * line breaks; rich HTML content is sanitized and rendered as markup.
 */
function renderRichText(?string $content): string
{
    $content = (string) $content;
    if (trim($content) === '') {
        return '';
    }
    if (strpos($content, '<') === false) {
        return nl2br(e($content));
    }
    return sanitizeRichHtml($content);
}

/**
 * Alias used by the reference codebase.
 * Delegates to e() for consistent behavior (no trim — trimming should
 * be done explicitly before calling escape functions).
 */
function escape_data($value)
{
    return e($value);
}

/**
 * Safe redirect (X-01 PRG pattern); exit after calling.
 * Falls back to a meta refresh if output was already sent (a stray
 * warning/notice must never break the PRG flow).
 */
function redirect(string $url): void
{
    if (!headers_sent()) {
        header('Location: ' . $url);
    } else {
        echo '<meta http-equiv="refresh" content="0;url=' . e($url) . '">';
    }
    exit;
}

/** Flash message helpers (one-shot session messages). */
function setFlash(string $type, string $message): void
{
    $_SESSION['flash'][$type][] = $message;
}

function flashMessages(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

/** CSRF token: generate once per session, verify on every POST (X-01). */
function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">';
}

/** Verify a submitted CSRF token; abort the request on mismatch. */
function verifyCsrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals(csrfToken(), $token)) {
        http_response_code(419);
        die('Invalid or expired CSRF token. Please go back, refresh the page, and try again.');
    }
}

/**
 * Admin asset URL helper (respects config server_path).
 * Appends the file's mtime as a cache-busting query param so edited
 * CSS/JS is picked up on next load instead of serving a stale browser cache.
 */
function assetUrl(string $path): string
{
    $base = rtrim((string) config('server_path', ''), '/');
    $relative = ltrim($path, '/');
    $absolute = dirname(__DIR__) . '/' . $relative;
    $version = is_file($absolute) ? '?v=' . filemtime($absolute) : '';
    return $base . '/' . $relative . $version;
}

/** Admin page URL builder: /admin/show_page.php?module=X&page=Y */
function pageUrl(string $module, string $page = ''): string
{
    $url = './show_page.php?module=' . urlencode($module);
    if ($page !== '' && $page !== 'home' && $page !== $module) {
        $url .= '&page=' . urlencode($page);
    }
    return $url;
}

/**
 * Format a date (AD) for display. In AD mode returns Y-m-d as-is; in BS mode
 * renders the BS equivalent from tbl_calendar (falls back to AD if that date
 * is outside the seeded range).
 */
function formatDateView($date): string
{
    if (empty($date)) {
        return '';
    }
    $ts = is_numeric($date) ? (int) $date : strtotime((string) $date);
    $ad = $ts ? date('Y-m-d', $ts) : (string) $date;
    if (useBsDates()) {
        $bs = adToBs($ad);
        if ($bs !== null) {
            return $bs;
        }
    }
    return $ad;
}

/**
 * Convert an AD date (Y-m-d) to BS date string (YYYY-MM-DD).
 * Returns null if the calendar is not seeded or the date is out of range.
 */
function adToBs(string $adDate): ?string
{
    static $cache = [];
    $adDate = trim($adDate);
    if (array_key_exists($adDate, $cache)) {
        return $cache[$adDate];
    }
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $adDate)) {
        $cache[$adDate] = null;
        return null;
    }
    $cal = Database::instance()->selectOne(
        'SELECT `nepali_year`, `month_code`, `eng_start_date`
         FROM `tbl_calendar`
         WHERE `eng_start_date` <= ? AND `eng_end_date` >= ? LIMIT 1',
        [$adDate, $adDate]
    );
    if (!$cal) {
        $cache[$adDate] = null;
        return null;
    }
    // Calculate day within the BS month.
    $startTs = strtotime($cal['eng_start_date']);
    $dateTs = strtotime($adDate);
    $dayInMonth = (int) floor(($dateTs - $startTs) / 86400) + 1;
    $result = sprintf('%04d-%02d-%02d', (int) $cal['nepali_year'], (int) $cal['month_code'], $dayInMonth);
    $cache[$adDate] = $result;
    return $result;
}

/**
 * Convert a BS date string (YYYY-MM-DD) to its AD equivalent (Y-m-d).
 * Returns null on invalid format, out of range, or if the calendar is not
 * seeded. Reverse of adToBs(); same single source of truth (tbl_calendar).
 */
function bsToAd(string $bsDate): ?string
{
    static $cache = [];
    $bsDate = trim($bsDate);
    if (array_key_exists($bsDate, $cache)) {
        return $cache[$bsDate];
    }
    $result = null;
    if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $bsDate, $m)) {
        $cal = Database::instance()->selectOne(
            'SELECT `eng_start_date`, `no_days` FROM `tbl_calendar`
             WHERE `nepali_year` = ? AND `month_code` = ? LIMIT 1',
            [(int) $m[1], (int) $m[2]]
        );
        if ($cal && (int) $m[3] >= 1 && (int) $m[3] <= (int) $cal['no_days']) {
            $result = date('Y-m-d', strtotime($cal['eng_start_date']) + ((int) $m[3] - 1) * 86400);
        }
    }
    $cache[$bsDate] = $result;
    return $result;
}

/**
 * Whether the active office profile uses BS dates in the UI
 * (`use_date` = 'BS' in tbl_office_profiles). Memoized per request.
 */
function useBsDates(): bool
{
    static $mode = null;
    if ($mode === null) {
        try {
            $profile = Database::instance()->selectOne(
                'SELECT `use_date` FROM `tbl_office_profiles` WHERE `id` = 1'
            );
            $mode = strtoupper((string) ($profile['use_date'] ?? 'AD')) === 'BS';
        } catch (Throwable $e) {
            $mode = false;
        }
    }
    return $mode;
}

/**
 * Short "(BS)" / "(AD)" suffix describing the active date system — use on
 * calendar/date labels so users can tell which calendar a value belongs to.
 */
function date_system_label(): string
{
    return useBsDates() ? '(BS)' : '(AD)';
}

/**
 * Resolve the display names to use for the organization: the active office
 * profile in `tbl_office_profiles` (id=1) when one is set up, otherwise the
 * hardcoded `organization_name` / `organization_short_name` from setup.php.
 * Memoized per request and DB-guarded (falls back to config on any error).
 *
 * The short name prefers the profile accronym, then the profile name, then
 * the config short name — so a profile with a name but no accronym still
 * replaces the hardcoded branding.
 *
 * @return array{name:string, short:string}
 */
function office_display_names(): array
{
    static $names = null;
    if ($names === null) {
        $name  = trim((string) config('organization_name', 'Office'));
        $short = trim((string) config('organization_short_name', 'Office'));
        try {
            $profile = Database::instance()->selectOne(
                'SELECT `name`, `accronym` FROM `tbl_office_profiles` WHERE `id` = 1'
            );
            if (is_array($profile)) {
                $dbName  = trim((string) ($profile['name'] ?? ''));
                $dbShort = trim((string) ($profile['accronym'] ?? ''));
                if ($dbName !== '') {
                    $name = $dbName;
                }
                if ($dbShort !== '') {
                    $short = $dbShort;
                } elseif ($dbName !== '') {
                    $short = $dbName;
                }
            }
        } catch (Throwable $e) {
            // keep the setup.php fallback
        }
        if ($name === '') {
            $name = 'Office';
        }
        if ($short === '') {
            $short = $name;
        }
        $names = ['name' => $name, 'short' => $short];
    }
    return $names;
}

/** Organization display name (office profile `name`, else setup.php fallback). */
function office_display_name(): string
{
    return office_display_names()['name'];
}

/** Organization short display name (profile accronym → name → config short). */
function office_display_short_name(): string
{
    return office_display_names()['short'];
}

/**
 * Write-edge contract: convert a submitted date value to its canonical AD
 * (Y-m-d) form before storing. In AD mode the value passes through untouched;
 * in BS mode every form date is interpreted as BS and converted here.
 * Returns null only when a BS value cannot be resolved (caller should reject).
 */
function normalizeDateInput(string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    if (!useBsDates()) {
        return $value;
    }
    return bsToAd($value);
}

/** Human-readable BS date: "2083-01-01" → "2083 Baisakh 1". */
function formatBsDate(string $bsDate): string
{
    if (!preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', trim($bsDate), $m)) {
        return $bsDate;
    }
    return $m[1] . ' ' . bsMonthName((int) $m[2]) . ' ' . (int) $m[3];
}

/** Verbose date view: BS month name shown when BS mode is active. */
function formatDateViewLong($date): string
{
    if (empty($date)) {
        return '';
    }
    $ts = is_numeric($date) ? (int) $date : strtotime((string) $date);
    if (useBsDates()) {
        $bs = $ts ? adToBs(date('Y-m-d', $ts)) : adToBs((string) $date);
        if ($bs !== null) {
            return formatBsDate($bs);
        }
    }
    return formatDateView($date);
}

/**
 * Check if BS calendar is available (tbl_calendar has data).
 */
function bsCalendarAvailable(): bool
{
    static $available = null;
    if ($available === null) {
        $row = Database::instance()->selectOne('SELECT 1 AS c FROM `tbl_calendar` LIMIT 1');
        $available = (bool) $row;
    }
    return $available;
}

/** BS month names. */
function bsMonthName(int $month): string
{
    $names = [
        1 => 'Baisakh', 2 => 'Jeth', 3 => 'Asar', 4 => 'Shrawan',
        5 => 'Bhadra', 6 => 'Ashwin', 7 => 'Kartik', 8 => 'Mangsir',
        9 => 'Poush', 10 => 'Magh', 11 => 'Falgun', 12 => 'Chaitra',
    ];
    return $names[$month] ?? '';
}

/**
 * Resolve a "YYYY-MM" month selection into the office's active calendar.
 *
 * In AD mode the value passes through and covers the natural calendar month.
 * In BS mode (office profile use_date = 'BS' + seeded tbl_calendar) the value
 * is treated as a BS year-month; the AD window the BS month covers is computed
 * from tbl_calendar so queries can keep filtering the AD-stored dates.
 *
 * Returns:
 *   ym       canonical YYYY-MM in the active calendar (default = current month)
 *   label    human label ("2026-09" or "2083 Baisakh")
 *   mode     'AD' | 'BS'
 *   ad_start AD date start of the window (Y-m-d)
 *   ad_end   AD date end of the window (Y-m-d)
 *   days     days in the month
 *
 * DB-dependent — falls back to AD on any calendar error.
 */
function resolve_calendar_month(string $rawYm, string $today = ''): array
{
    $today = $today !== '' ? $today : date('Y-m-d');
    $ymOk = preg_match('/^\d{4}-\d{2}$/', $rawYm) === 1;
    $bsMode = false;
    try {
        $bsMode = useBsDates() && bsCalendarAvailable();
    } catch (Throwable $e) {
        $bsMode = false;
    }

    if ($bsMode) {
        try {
            // Default to the current BS year-month.
            $todayBs = adToBs($today);
            if (!$ymOk || bsToAd($rawYm . '-01') === null) {
                $rawYm = $todayBs ? substr($todayBs, 0, 7) : date('Y-m', strtotime($today));
            }
            if (preg_match('/^(\d{4})-(\d{2})$/', $rawYm, $m)) {
                $year  = (int) $m[1];
                $month = (int) $m[2];
                $cal = Database::instance()->selectOne(
                    'SELECT `no_days`, `eng_start_date` FROM `tbl_calendar`
                     WHERE `nepali_year` = ? AND `month_code` = ? LIMIT 1',
                    [$year, $month]
                );
                if ($cal) {
                    $days   = (int) $cal['no_days'];
                    $start  = $cal['eng_start_date'];
                    $end    = date('Y-m-d', strtotime($start) + max(0, $days - 1) * 86400);
                    return [
                        'ym'       => sprintf('%04d-%02d', $year, $month),
                        'label'    => $year . ' ' . bsMonthName($month),
                        'mode'     => 'BS',
                        'ad_start' => $start,
                        'ad_end'   => $end,
                        'days'     => $days,
                    ];
                }
            }
        } catch (Throwable $e) {
            // fall through to AD window
        }
    }

    $ym = $ymOk ? $rawYm : date('Y-m', strtotime($today));
    $start = $ym . '-01';
    return [
        'ym'       => $ym,
        'label'    => $ym,
        'mode'     => 'AD',
        'ad_start' => $start,
        'ad_end'   => date('Y-m-t', strtotime($start)),
        'days'     => (int) date('t', strtotime($start)),
    ];
}

/** Nepali rupee formatting for money (X-07: DECIMAL(18,4) stored). */
function formatMoney($amount): string
{
    $amount = (float) $amount;
    return number_format($amount, 2);
}

/** Pagination helper: slice params for a list page (X-04, 50/page default). */
function paginationParams(int $total, int $page = 1): array
{
    $perPage = (int) config('pagination', 50);
    $page = max(1, $page);
    $pages = max(1, (int) ceil($total / $perPage));
    $offset = ($page - 1) * $perPage;
    return ['per_page' => $perPage, 'page' => $page, 'pages' => $pages, 'offset' => $offset, 'total' => $total];
}

/** Validate an upload against the whitelist + size cap (X-03). */
function validateUpload(array $file, array $allowedTypes = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'pptx', 'txt']): array
{
    $maxBytes = (int) config('upload_max_bytes', 10485760);
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'message' => 'No file uploaded'];
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'message' => 'File upload failed (error ' . $file['error'] . ')'];
    }
    if ((int) $file['size'] > $maxBytes) {
        return ['ok' => false, 'message' => 'File exceeds the 10 MB size limit'];
    }
    $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedTypes, true)) {
        return ['ok' => false, 'message' => 'File type .' . $ext . ' is not allowed'];
    }
    return ['ok' => true, 'extension' => $ext];
}

/** Move an uploaded file into user_uploads/<module>/ with a safe name. */
function storeUpload(array $file, string $module, string $ext): ?string
{
    // Sanitize module to prevent path traversal (e.g. '../../etc/passwd').
    $module = preg_replace('#[^a-zA-Z0-9_/]#', '', $module);
    $module = trim($module, '/');
    if ($module === '' || strpos($module, '..') !== false) {
        return null;
    }
    $dir = dirname(__DIR__) . '/user_uploads/' . $module;
    if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
        return null;
    }
    $name = date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
        return null;
    }
    return $module . '/' . $name;
}

/**
 * Audit log entry (X-08). Records any significant system action.
 * Never throws — a logging failure must never block the main flow.
 */
function auditLog(string $module, string $action, ?string $entityType = null, ?int $entityId = null, $oldData = null, $newData = null, ?string $description = null): void
{
    try {
        $actorId = null;
        if (class_exists('Auth', false) && Auth::check()) {
            $actorId = Auth::id();
        }
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        Database::instance()->insert('tbl_audit_log', [
            'module'      => $module,
            'action'      => $action,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'old_data'    => $oldData === null ? null : json_encode($oldData, JSON_UNESCAPED_UNICODE),
            'new_data'    => $newData === null ? null : json_encode($newData, JSON_UNESCAPED_UNICODE),
            'description' => $description,
            'actor_id'    => $actorId,
            'actor_ip'    => $ip,
        ]);
    } catch (Throwable $e) {
        // Never let logging failure break the workflow.
    }
}

/**
 * Per-client module catalog, read from the client's deployment database
 * (php_smart_school_mlebs-style tbl_modules / tbl_submodules tables, chosen
 * by the project's db_name). IS-active PRO/ALL plan modules are returned with
 * their active submodules, names, icons and sidebar sections.
 *
 * When the deployment DB is unset or the tables are missing (Excel/greenfield
 * client), it falls back to the hardcoded nav map in varriables.php so the
 * page keeps working. Returns a list of modules:
 *   [ ['key'=>.., 'name'=>.., 'icon'=>.., 'section'=>.., 'subs'=>[key=>name,..]], ... ]
 *
 * @param array      $project  tbl_client_projects row (uses db_name)
 * @param string|null $preferDb Optional override for db_name (POST-time)
 */
function client_module_catalog(array $project, ?string $preferDb = null): array
{
    $dbName = trim((string) ($preferDb !== null ? $preferDb : ($project['db_name'] ?? '')));
    if ($dbName === '' || !preg_match('/^[A-Za-z0-9_\-]+$/', $dbName)) {
        $dbName = 'php_smart_school_mlebs';
    }

    try {
        $db = Database::instance();
        $modules = $db->select(
            "SELECT `id`, `module_key`, `module_name`, `icon_class`, `sidebar_section`
             FROM `{$dbName}`.`tbl_modules`
             WHERE `plan` IN ('PRO','ALL')
             ORDER BY `sort_order` ASC, `id` ASC"
        );
        $subs = $db->select(
            "SELECT `module_id`, `submodule_key`, `submodule_name`
             FROM `{$dbName}`.`tbl_submodules`
             ORDER BY `sort_order` ASC, `id` ASC"
        );
    } catch (Throwable $e) {
        // Master catalog DB unavailable → fall back to the hardcoded nav map.
        $modules = $subs = [];
    }

    if (!$modules) {
        // Fallback: derive from varriables.php globals so greenfield clients
        // without deployment tables still get a usable catalog.
        $sections = $GLOBALS['navSidebarSections'] ?? [];
        $icons = $GLOBALS['icons'] ?? [];
        foreach (($GLOBALS['modules'] ?? []) as $modKey) {
            $subsList = [];
            foreach (($GLOBALS['subNavBars'][$modKey] ?? []) as $subKey => $subName) {
                $subsList[$subKey] = $subName;
            }
            $modules[] = [
                'id'       => 0,
                'module_key'=> $modKey,
                'key'      => $modKey,
                'module_name'=> $GLOBALS['navBars'][$modKey] ?? ucfirst($modKey),
                'name'     => $GLOBALS['navBars'][$modKey] ?? ucfirst($modKey),
                'icon_class'=> $icons[$modKey] ?? 'nav-icon fas fa-circle',
                'icon'     => $icons[$modKey] ?? 'nav-icon fas fa-circle',
                'sidebar_section'=> $sections[$modKey] ?? 'MODULES',
                'section'  => $sections[$modKey] ?? 'MODULES',
                'subs'     => $subsList,
            ];
        }
        return $modules;
    }

    $byModule = [];
    foreach ($subs as $s) {
        $byModule[(int) $s['module_id']][] = ['key' => $s['submodule_key'], 'name' => $s['submodule_name']];
    }

    $out = [];
    foreach ($modules as $m) {
        $subsList = [];
        foreach (($byModule[(int) $m['id']] ?? []) as $s) {
            $subsList[$s['key']] = $s['name'];
        }
        $out[] = [
            'id'       => (int) $m['id'],
            'key'      => $m['module_key'],
            'name'     => $m['module_name'],
            'icon'     => $m['icon_class'] ?: 'fas fa-circle',
            'section'  => $m['sidebar_section'] ?: 'MODULES',
            'subs'     => $subsList,
        ];
    }
    return $out;
}

/**
 * Push client_permissions grants into the client's deployment DB so the
 * smart-school sidebar actually reflects them.
 *
 * Syncs `is_active` on tbl_modules/tbl_submodules (plan PRO/ALL only):
 *   - granted module keys  → is_active = 1
 *   - non-granted modules  → is_active = 0  (hidden from that deployment)
 *   - dashboard            → always kept on
 *   - submodules of granted modules → enabled only for granted sub-keys
 *   - WEBSITE-plan rows    → never touched
 *
 * @param string $dbName        Deployment database (validated [A-Za-z0-9_-])
 * @param array  $grantedModules List of granted module keys
 * @param array  $grantedSubs    Map module_key => list of granted submodule keys
 * @throws RuntimeException when the deployment DB is missing or unreadable.
 */
function sync_deployment_module_visibility(string $dbName, array $grantedModules, array $grantedSubs): void
{
    if ($dbName === '' || !preg_match('/^[A-Za-z0-9_\-]+$/', $dbName)) {
        throw new RuntimeException('Invalid deployment database name.');
    }
    $db = Database::instance();

    $modules = $db->select(
        "SELECT `id`, `module_key` FROM `{$dbName}`.`tbl_modules`
         WHERE `plan` IN ('PRO','ALL')"
    );
    foreach ($modules as $m) {
        $isActive = ($m['module_key'] === 'dashboard' || in_array($m['module_key'], $grantedModules, true)) ? 1 : 0;
        $db->execute("UPDATE `{$dbName}`.`tbl_modules` SET `is_active` = ? WHERE `id` = ?", [$isActive, (int) $m['id']]);
    }

    $subs = $db->select(
        "SELECT s.`id`, s.`module_id`, s.`submodule_key`, m.`module_key`, m.`module_name`
         FROM `{$dbName}`.`tbl_submodules` s
         JOIN `{$dbName}`.`tbl_modules` m ON m.`id` = s.`module_id`
         WHERE m.`plan` IN ('PRO','ALL')"
    );
    foreach ($subs as $s) {
        $modKey = $s['module_key'];
        $allowed = (in_array($modKey, $grantedModules, true) && !empty($grantedSubs[$modKey]) && is_array($grantedSubs[$modKey]))
            ? $grantedSubs[$modKey]
            : [];
        $isActive = ($modKey === 'dashboard' || in_array($s['submodule_key'], $allowed, true)) ? 1 : 0;
        $db->execute("UPDATE `{$dbName}`.`tbl_submodules` SET `is_active` = ? WHERE `id` = ?", [$isActive, (int) $s['id']]);
    }
}

/** Render flash messages (call inside the content area). */
function renderFlash(): string
{
    $html = '';
    $types = [
        'success' => 'alert-success',
        'error'   => 'alert-danger',
        'info'    => 'alert-info',
        'warning' => 'alert-warning',
    ];
    foreach (flashMessages() as $type => $messages) {
        $cls = $types[$type] ?? 'alert-info';
        foreach ($messages as $msg) {
            $html .= '<div class="alert ' . $cls . ' alert-dismissible fade show" role="alert" aria-live="assertive">'
                . e($msg)
                . '<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>'
                . '</div>';
        }
    }
    return $html;
}
