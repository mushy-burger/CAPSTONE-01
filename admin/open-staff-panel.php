<?php
/**
 * Admin → Staff Panel handoff.
 *
 * Opens the Staff Panel signed in as the dedicated staff account
 * (staff@mototrack.com) in a NEW auth context (browser-tab session), so the
 * admin can manage bookings through the exact interface staff use — without
 * touching the admin's own session. Admin-only.
 *
 * Optional: ?booking_id=N jumps straight to that booking's staff detail page.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
requireAdminOnly();

const STAFF_PANEL_ACCOUNT_EMAIL = 'staff@mototrack.com';

$staffUser = fetchOne(
    "SELECT id, name, email, role FROM users WHERE email = ? AND role = 'staff' AND is_active = 1",
    [STAFF_PANEL_ACCOUNT_EMAIL]
);

if (!$staffUser) {
    flashMessage('bk_admin_error', 'The dedicated staff account (' . STAFF_PANEL_ACCOUNT_EMAIL . ') was not found or is disabled.');
    redirect(baseUrl('admin/bookings.php'));
}

// Seed a fresh, separate auth context for the staff session. This mirrors what
// loginUser() stores, but never touches the admin's own context key.
$staffCtx = 'tab_' . bin2hex(random_bytes(16));
$_SESSION['auth_contexts'][$staffCtx] = [
    'user_id'    => (int)$staffUser['id'],
    'user_name'  => $staffUser['name'],
    'user_role'  => $staffUser['role'],
    'user_email' => $staffUser['email'],
];

$bookingId = (int)($_GET['booking_id'] ?? 0);
$target = $bookingId > 0
    ? baseUrl('staff/booking-detail.php?id=' . $bookingId . '&ctx=' . $staffCtx)
    : baseUrl('staff/bookings.php?ctx=' . $staffCtx);

redirect($target);
