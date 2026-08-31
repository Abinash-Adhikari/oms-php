<?php
/**
 * Login POST handler (PRG pattern).
 * Verifies CSRF, enforces rate limiting, attempts auth,
 * redirects back to login with a flash error on failure,
 * or to the dashboard on success.
 */
include __DIR__ . '/../config/setup.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('login.php');
}

verifyCsrf();

$username = trim((string) ($_POST['userId'] ?? ''));
$password = (string) ($_POST['password'] ?? '');

if ($username === '' || $password === '') {
    $_SESSION['login_err_msg'] = 'Please enter both username and password.';
    redirect('login.php');
}

// --- Rate limiting (brute-force protection) ---
$clientIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$rateCheck = RateLimiter::check($clientIp, $username);

if (!$rateCheck['allowed']) {
    $retryMsg = RateLimiter::formatRetryAfter($rateCheck['retry_after']);
    $_SESSION['login_err_msg'] = 'Too many failed attempts. Please try again in ' . $retryMsg . '.';
    $_SESSION['login_rate_limited'] = true;
    redirect('login.php');
}

$result = Auth::attemptLogin($username, $password);

if (empty($result['ok'])) {
    // Record failed attempt for rate limiting.
    RateLimiter::recordFailure($clientIp, $username);
    $_SESSION['login_err_msg'] = $result['message'] ?? 'Invalid UserId or Password';
    redirect('login.php');
}

// Successful login — clear any rate limit data for this IP/username.
RateLimiter::clear($clientIp, $username);

redirect(pageUrl('dashboard'));
