<?php

/**
 * File-based rate limiter for login brute-force protection.
 *
 * Tracks failed login attempts by IP address and username using
 * files in storage/rate_limiter/. No database migration required.
 *
 * Default policy: 5 failed attempts per 15-minute sliding window.
 * After exceeding the limit, the IP/username is locked out for the
 * remainder of the window.
 */
class RateLimiter
{
    /** @var string Directory for rate limiter files. */
    private static string $dir = '';

    /** @var int Max failed attempts allowed per window. */
    private static int $maxAttempts = 5;

    /** @var int Window size in seconds (15 minutes). */
    private static int $windowSeconds = 900;

    /**
     * Initialize the rate limiter directory.
     */
    private static function init(): void
    {
        if (self::$dir === '') {
            self::$dir = dirname(__DIR__) . '/storage/rate_limiter';
            if (!is_dir(self::$dir)) {
                mkdir(self::$dir, 0750, true);
            }
        }
    }

    /**
     * Check whether a login attempt is allowed for the given IP and username.
     *
     * @param string $ip       Client IP address.
     * @param string $username Attempted username (optional, for per-user limiting).
     * @return array{allowed: bool, retry_after: int} retry_after is seconds until the lock expires (0 if allowed).
     */
    public static function check(string $ip, string $username = ''): array
    {
        self::init();

        $ipKey = self::sanitizeKey($ip);
        $ipDir = self::$dir . '/ip_' . $ipKey;

        // Check IP-based rate limit.
        $ipResult = self::checkBucket($ipDir);
        if (!$ipResult['allowed']) {
            return $ipResult;
        }

        // Check username-based rate limit (if provided).
        if ($username !== '') {
            $userKey = self::sanitizeKey(strtolower($username));
            $userDir = self::$dir . '/user_' . $userKey;
            $userResult = self::checkBucket($userDir);
            if (!$userResult['allowed']) {
                return $userResult;
            }
        }

        return ['allowed' => true, 'retry_after' => 0];
    }

    /**
     * Record a failed login attempt for the given IP and username.
     */
    public static function recordFailure(string $ip, string $username = ''): void
    {
        self::init();

        $ipKey = self::sanitizeKey($ip);
        self::addAttempt(self::$dir . '/ip_' . $ipKey);

        if ($username !== '') {
            $userKey = self::sanitizeKey(strtolower($username));
            self::addAttempt(self::$dir . '/user_' . $userKey);
        }
    }

    /**
     * Clear all rate limit data for an IP and username (called on successful login).
     */
    public static function clear(string $ip, string $username = ''): void
    {
        self::init();

        $ipKey = self::sanitizeKey($ip);
        self::clearBucket(self::$dir . '/ip_' . $ipKey);

        if ($username !== '') {
            $userKey = self::sanitizeKey(strtolower($username));
            self::clearBucket(self::$dir . '/user_' . $userKey);
        }
    }

    /**
     * Format a retry_after duration into a human-readable string.
     */
    public static function formatRetryAfter(int $seconds): string
    {
        if ($seconds <= 0) {
            return '';
        }
        if ($seconds < 60) {
            return $seconds . ' second' . ($seconds !== 1 ? 's' : '');
        }
        $minutes = (int) ceil($seconds / 60);
        return $minutes . ' minute' . ($minutes !== 1 ? 's' : '');
    }

    // ----- Internal helpers -----

    /**
     * Check a single rate-limit bucket (directory of attempt files).
     */
    private static function checkBucket(string $dir): array
    {
        if (!is_dir($dir)) {
            return ['allowed' => true, 'retry_after' => 0];
        }

        $now = time();
        $cutoff = $now - self::$windowSeconds;
        $attempts = 0;
        $oldestInWindow = $now;

        $files = glob($dir . '/*.attempt');
        if ($files === false) {
            return ['allowed' => true, 'retry_after' => 0];
        }

        foreach ($files as $file) {
            $ts = (int) filemtime($file);
            if ($ts < $cutoff) {
                // Expired — clean up.
                @unlink($file);
            } else {
                $attempts++;
                if ($ts < $oldestInWindow) {
                    $oldestInWindow = $ts;
                }
            }
        }

        if ($attempts >= self::$maxAttempts) {
            $retryAfter = ($oldestInWindow + self::$windowSeconds) - $now;
            if ($retryAfter < 1) {
                $retryAfter = 1;
            }
            return ['allowed' => false, 'retry_after' => $retryAfter];
        }

        return ['allowed' => true, 'retry_after' => 0];
    }

    /**
     * Add an attempt marker file to a bucket.
     */
    private static function addAttempt(string $dir): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        $filename = $dir . '/' . time() . '_' . bin2hex(random_bytes(4)) . '.attempt';
        @file_put_contents($filename, '');
    }

    /**
     * Clear all attempt files in a bucket.
     */
    private static function clearBucket(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = glob($dir . '/*.attempt');
        if ($files) {
            foreach ($files as $file) {
                @unlink($file);
            }
        }
        @rmdir($dir);
    }

    /**
     * Sanitize a string to be safe for use as a directory name component.
     */
    private static function sanitizeKey(string $input): string
    {
        // Allow only alphanumeric, dashes, underscores, and dots.
        $sanitized = preg_replace('/[^a-zA-Z0-9._-]/', '_', $input);
        // Truncate to prevent extremely long directory names.
        return substr($sanitized, 0, 128);
    }
}
