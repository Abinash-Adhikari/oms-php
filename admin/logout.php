<?php
/**
 * SB-Tech — logout: destroy the session and return to the login page.
 */
include __DIR__ . '/../config/setup.php';

Auth::logout();
redirect('login.php');
