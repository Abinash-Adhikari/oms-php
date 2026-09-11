<?php
/**
 * SB-Tech — Clients Module Home.
 * Standalone module for managing clients (companies/individuals).
 */
$db = Database::instance();
$canManage = Auth::isSuperAdmin() || Auth::hasSpecial('manage_leads');

redirect(pageUrl('clients', 'clients'));
exit;
