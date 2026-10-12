<?php

/**
 * SB-Tech — public API helpers (api/v1/*).
 *
 * Pure functions (no globals, session or DB) so the response contract is
 * unit-testable (TEST-05). Loaded by config/bootstrap.php and tests/bootstrap.php.
 */

/** Success envelope: { ok: true, data: … } (matches the app's unwrap()). */
function api_ok(array $data): array
{
    return ['ok' => true, 'data' => $data];
}

/** Error envelope: { ok: false, error: { code, message } }. */
function api_error(string $code, string $message): array
{
    return ['ok' => false, 'error' => ['code' => $code, 'message' => $message]];
}

/**
 * App status of a client project, or null when it must not be listed.
 *
 * Completed/Cancelled projects are hidden. A passed end date wins over the
 * stored status (expired), On Hold is suspended, everything else (including
 * demo/trial rows) is active; trials expose their end date as expiryDate.
 *
 * @param string      $status  tbl_client_projects.status
 * @param string|null $endDate Y-m-d or null
 * @param string      $today   Y-m-d in the app timezone
 */
function school_api_status(string $status, ?string $endDate, string $today): ?string
{
    if ($status === 'Completed' || $status === 'Cancelled') {
        return null;
    }
    if ($endDate !== null && $endDate !== '' && $endDate < $today) {
        return 'expired';
    }
    return $status === 'On Hold' ? 'suspended' : 'active';
}

/**
 * Validate an app slug: 2–32 chars of a-z, 0-9 and '-', starting with a
 * letter or digit. Input is lower-cased; empty input means "not listed".
 *
 * @throws InvalidArgumentException when the slug is malformed.
 */
function normalize_app_slug(string $raw): ?string
{
    $slug = strtolower(trim($raw));
    if ($slug === '') {
        return null;
    }
    if (!preg_match('/^[a-z0-9][a-z0-9-]{1,31}$/', $slug)) {
        throw new InvalidArgumentException('App id must be 2–32 characters: a-z, 0-9 and "-".');
    }
    return $slug;
}

/**
 * Validate an accent colour as #RRGGBB (upper-cased); empty means none.
 *
 * @throws InvalidArgumentException when the colour is not #RRGGBB.
 */
function normalize_accent(string $raw): ?string
{
    $accent = trim($raw);
    if ($accent === '') {
        return null;
    }
    if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $accent)) {
        throw new InvalidArgumentException('Accent must be a colour like #2563EB.');
    }
    return strtoupper($accent);
}

/**
 * Whether a project URL may be listed in the school app: https always;
 * plain http only when $allowHttp (local/dev setting app_listing_allow_http).
 */
function app_listing_url_ok(?string $url, bool $allowHttp): bool
{
    if ($url === null || $url === '') {
        return false;
    }
    return stripos($url, 'https://') === 0 || ($allowHttp && stripos($url, 'http://') === 0);
}

/**
 * Change-detection version of a list: a CRC32 of its JSON. Changes whenever
 * any listed value changes (including removals); compare with !==.
 */
function api_list_version(array $items): int
{
    return crc32((string) json_encode($items, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}
