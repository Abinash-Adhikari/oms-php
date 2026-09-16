<?php
/**
 * SB-Tech — admin panel directory index.
 * Visiting /admin/ lands here: authenticated users go to the dashboard,
 * everyone else is sent to the login panel (login.php handles Auth::check
 * and redirects to the dashboard itself).
 */
include __DIR__ . '/../config/setup.php';

redirect('login.php');