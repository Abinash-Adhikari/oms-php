<?php

/**
 * SB-Tech — authentication, session, and role-based access control.
 *
 * Session keys (matches reference + PRD X-01): loginStatus, userId,
 * username, fullname. Permission data is read from the DB on every check
 * so that permission changes apply on the user's next request
 * (AC-AUTH-02.2). Super Admin (permitted_modules = 'All' or role
 * 'Super Admin'/'Superadmin') bypasses all checks.
 *
 * Password scheme: bcrypt (modern) with auto-upgrade from legacy sha512+salt.
 */

class Auth
{
    /** @var array|null Cached user row for the current request. */
    private static $cachedUser = null;
    /** @var int|null Cached user id to validate the cache. */
    private static $cachedUserId = null;

    /** Is a valid session active? */
    public static function check(): bool
    {
        return isset($_SESSION['loginStatus'])
            && $_SESSION['loginStatus'] === true
            && isset($_SESSION['userId'])
            && isset($_SESSION['username'])
            && isset($_SESSION['fullname']);
    }

    /** Current user id (or null). */
    public static function id()
    {
        return $_SESSION['userId'] ?? null;
    }

    /** Load the current user row from the DB (cached per request to avoid
     *  redundant queries when hasModule/hasSubmodule/can are called multiple times).
     *  Call clearUserCache() after updating permissions to force a reload. */
    public static function user()
    {
        if (!self::check()) {
            return null;
        }
        $userId = (int) $_SESSION['userId'];
        if (self::$cachedUser !== null && self::$cachedUserId === $userId) {
            return self::$cachedUser;
        }
        self::$cachedUser = Database::instance()->selectOne(
            'SELECT * FROM `tbl_users_login` WHERE `id` = ? LIMIT 1',
            [$userId]
        );
        self::$cachedUserId = $userId;
        return self::$cachedUser;
    }

    /** Attempt login; returns ['ok' => bool, 'message' => string]. */
    public static function attemptLogin(string $username, string $password): array
    {
        $db = Database::instance();
        $user = $db->selectOne(
            'SELECT * FROM `tbl_users_login` WHERE BINARY `username` = ? LIMIT 1',
            [$username]
        );

        if (!$user) {
            self::recordFailedAttempt($username);
            return ['ok' => false, 'message' => 'Invalid UserId or Password'];
        }

        if ($user['status'] === 'Terminated') {
            return ['ok' => false, 'message' => 'Your account has been terminated. Please contact the administrator.'];
        }
        if ($user['status'] === 'Block') {
            return ['ok' => false, 'message' => 'Your account has been blocked. Please contact the administrator.'];
        }

        // IP allow-list enforcement (office profile allow_ips field).
        $clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
        // Always allow localhost addresses — never block dev/admin access.
        $alwaysAllowed = ['127.0.0.1', '::1', '0.0.0.0'];
        if (!in_array($clientIp, $alwaysAllowed, true)) {
            $allowIps = self::getAllowIps();
            if (!empty($allowIps)) {
                // '*' means allow all IPs.
                if (!in_array('*', $allowIps, true) && !in_array($clientIp, $allowIps, true)) {
                    self::recordFailedAttempt($username);
                    $msg = 'Access denied from your IP address ' . $clientIp . '. Contact the administrator to be whitelisted.';
                    return ['ok' => false, 'message' => $msg];
                }
            }
        }

        // Support both legacy sha512+salt and modern bcrypt.
        $storedPassword = (string) $user['password'];
        $valid = false;

        // Check if stored hash is bcrypt (starts with $2y$ or $2a$).
        if (strlen($storedPassword) === 60 && ($storedPassword[0] === '$')) {
            $valid = password_verify($password, $storedPassword);
        } else {
            // Legacy sha512+salt path.
            $hash = hash('sha512', hash('sha512', $password) . $user['salt']);
            $valid = hash_equals($storedPassword, $hash);
            // Auto-upgrade to bcrypt on successful legacy login.
            if ($valid) {
                try {
                    Database::instance()->update('tbl_users_login', [
                        'password' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
                        'salt'     => '',
                    ], '`id` = ?', [(int) $user['id']]);
                } catch (Throwable $e) {
                    // Non-critical — login still succeeds.
                }
            }
        }

        if (!$valid) {
            self::recordFailedAttempt($username);
            return ['ok' => false, 'message' => 'Invalid UserId or Password'];
        }

        // Regenerate session id on privilege change (session fixation guard).
        session_regenerate_id(true);
        $_SESSION['loginStatus'] = true;
        $_SESSION['userId'] = (int) $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['fullname'] = $user['fullname'];

        // Audit log (X-08).
        if (function_exists('auditLog')) {
            auditLog('auth', 'login', 'user', (int) $user['id'], null, ['username' => $user['username']], 'Login successful');
        }

        return ['ok' => true, 'message' => 'Login successful'];
    }

    public static function logout(): void
    {
        $userId = $_SESSION['userId'] ?? null;
        $username = $_SESSION['username'] ?? null;
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
        if (function_exists('auditLog') && $userId) {
            auditLog('auth', 'logout', 'user', (int) $userId, null, ['username' => $username]);
        }
    }

    // ---------------------------------------------------------------- RBAC

    /** Is this user a super admin (full bypass)? */
    public static function isSuperAdmin(?array $user = null): bool
    {
        $user = $user ?? self::user();
        if (!$user) {
            return false;
        }
        return ($user['permitted_modules'] ?? '') === 'All'
            || strcasecmp((string) ($user['role'] ?? ''), 'superadmin') === 0
            || strcasecmp((string) ($user['role'] ?? ''), 'super admin') === 0;
    }

    /** Module-level permission (AC-AUTH-01.4). */
    public static function hasModule(string $module): bool
    {
        if (!self::check()) {
            return false;
        }
        $user = self::user();
        if (!$user || self::isSuperAdmin($user)) {
            return (bool) $user;
        }
        $modules = self::decodePermission((string) $user['permitted_modules']);
        return in_array($module, $modules, true);
    }

    /** Submodule-level permission within a module. */
    public static function hasSubmodule(string $module, string $submodule): bool
    {
        if (!self::check()) {
            return false;
        }
        $user = self::user();
        if (!$user || self::isSuperAdmin($user)) {
            return (bool) $user;
        }
        $subs = self::decodePermission((string) $user['permitted_submodules']);
        if (!is_array($subs)) {
            return false;
        }
        // Case-insensitive module key match (stored vs lookup variants).
        $list = null;
        foreach ($subs as $modKey => $subList) {
            if (strcasecmp((string) $modKey, $module) === 0 && is_array($subList)) {
                $list = $subList;
                break;
            }
        }
        if (!is_array($list)) {
            return false;
        }
        foreach ($list as $sub) {
            if (strcasecmp((string) $sub, $submodule) === 0) {
                return true;
            }
        }
        return false;
    }

    /** Granular action permission (e.g. approve_vouchers). */
    public static function hasSpecial(string $permissionKey): bool
    {
        if (!self::check()) {
            return false;
        }
        $user = self::user();
        if (!$user || self::isSuperAdmin($user)) {
            return (bool) $user;
        }
        $keys = self::decodePermission((string) ($user['special_permission'] ?? ''));
        return is_array($keys) && in_array($permissionKey, $keys, true);
    }

    /**
     * Combined page access: module grant + (when a submodule page is
     * requested) submodule grant. A page equal to the module itself or
     * 'home' only needs the module grant.
     */
    public static function can(string $module, string $page = ''): bool
    {
        if (!self::hasModule($module)) {
            return false;
        }
        $page = (string) $page;
        if ($page === '' || $page === 'home' || strcasecmp($page, $module) === 0) {
            return true;
        }
        return self::hasSubmodule($module, $page);
    }

    // ---------------------------------------------------------------- misc

    private static function recordFailedAttempt(string $username): void
    {
        try {
            Database::instance()->insert('tbl_login_attempts', [
                'username'    => $username,
                'date'        => date('Y-m-d'),
                'time'        => date('H:i:s'),
                'user_agent'  => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
                'executed_on' => date('Y-m-d H:i:s'),
            ]);
        } catch (Throwable $e) {
            // Never let a logging failure block the login flow.
        }
    }

    /**
     * Force-reload the cached user row on the next user() call.
     * Call this after updating permissions, role, or permitted_modules
     * to ensure the in-request cache reflects the latest DB state.
     */
    public static function clearUserCache(): void
    {
        self::$cachedUser = null;
        self::$cachedUserId = null;
    }

    /** Decode a JSON permission column; 'All' is handled by isSuperAdmin. */
    private static function decodePermission(string $json)
    {
        $json = html_entity_decode($json, ENT_QUOTES, 'UTF-8');
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Get the IP allow-list from the office profile.
     * Returns an array of allowed IPs, or empty array if not configured.
     */
    private static function getAllowIps(): array
    {
        static $ips = null;
        if ($ips !== null) {
            return $ips;
        }
        $ips = [];
        try {
            $profile = Database::instance()->selectOne(
                'SELECT `allow_ips` FROM `tbl_office_profiles` WHERE `id` = 1'
            );
            if ($profile && !empty($profile['allow_ips'])) {
                $raw = trim($profile['allow_ips']);
                // 'All' means every IP is allowed.
                if (strcasecmp($raw, 'All') === 0) {
                    $ips = ['*'];
                } else {
                    // Support comma-separated or newline-separated IPs.
                    $ips = array_filter(array_map('trim', preg_split('/[,\n]+/', $raw)));
                    $ips = array_values($ips);
                }
            }
        } catch (Throwable $e) {
            // If the table doesn't exist yet, allow all IPs.
        }
        return $ips;
    }
}
