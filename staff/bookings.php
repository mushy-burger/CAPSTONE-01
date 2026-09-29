<?php
$pageTitle = 'Bookings';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/TechnicianService.php';
require_once __DIR__ . '/../includes/NotificationService.php';
require_once __DIR__ . '/../includes/BookingDeposit.php';
require_once __DIR__ . '/../includes/PartsReservationService.php';
requireStaff();

/** Short, honest summary of what actually reached the customer. */
function bkDeliveryNote(array $delivery): string {
    $parts = [];
    foreach (['sms' => 'SMS', 'email' => 'Email'] as $channel => $label) {
        switch ($delivery[$channel] ?? '') {
            case 'sent':      $parts[] = "$label sent"; break;
            case 'failed':    $parts[] = "$label failed"; break;
            case 'skipped':   $parts[] = "$label skipped"; break;
            case 'duplicate': $parts[] = "$label already sent"; break;
        }
    }
    return $parts ? 'Customer notification — ' . implode(', ', $parts) . '.' : '';
}

/**
 * Notify the customer that their appointment is confirmed.
 * Wrapped so a provider outage can never undo a confirmed booking.
 */
function bkNotifyConfirmed(int $bookingId): string {
    try {
        return bkDeliveryNote(notifyAppointmentConfirmed($bookingId));
    } catch (Throwable $e) {
        smsLogLine("Confirmation notification failed for booking {$bookingId}: " . $e->getMessage());
        return 'Customer notification could not be sent (logged).';
    }
}

/** Human-readable schedule distance based on the same DB date used for tab classification. */
function bkScheduleOffsetLabel(int $days): string {
    if ($days === 0) return 'Today';
    if ($days === 1) return 'Tomorrow';
    if ($days > 1) return $days . ' days from now';
    if ($days === -1) return 'Yesterday';
    return abs($days) . ' days ago';
}

/** Active, on-duty technicians remain eligible for Manual Assign without a skills restriction. */
function bkManualAssignableTechnician(int $technicianId): ?array {
    if ($technicianId <= 0) return null;
    return fetchOne(
        "SELECT id, name, availability_status
         FROM users
         WHERE id = ? AND role = 'technician' AND is_active = 1",
        [$technicianId]
    );
}

/** Safely change an already-confirmed future appointment without invoking auto assignment. */
function bkReassignUpcomingBooking(int $bookingId, int $technicianId): array {
    $db = getDB();
    try {
        $db->beginTransaction();
        $booking = fetchOne(
            "SELECT b.*, u.name AS customer_name
             FROM bookings b
             JOIN users u ON u.id = b.user_id
             WHERE b.id = ? FOR UPDATE",
            [$bookingId]
        );
        if (!$booking || $booking['status'] !== 'confirmed' || $booking['scheduled_date'] <= (fetchOne("SELECT CURDATE() AS today")['today'] ?? '')) {
            throw new RuntimeException('Only confirmed upcoming bookings can have their technician changed.');
        }

        $update = $db->prepare(
            "UPDATE bookings
             SET technician_id = ?, assigned_at = NOW()
             WHERE id = ? AND status = 'confirmed'"
        );
        $update->execute([$technicianId, $bookingId]);
        if ($update->rowCount() !== 1) {
            throw new RuntimeException('This booking changed before the technician could be reassigned.');
        }
        $db->commit();
        return $booking;
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
}

/** Confirm a paid future appointment without assigning a technician. */
function bkConfirmUpcomingBooking(int $bookingId): array {
    if (!depositIsSettled($bookingId)) {
        throw new RuntimeException('Reservation deposit must be paid before this booking can be confirmed.');
    }

    $db = getDB();
    try {
        $db->beginTransaction();
        $booking = fetchOne(
            "SELECT b.*, u.name AS customer_name
             FROM bookings b
             JOIN users u ON u.id = b.user_id
             WHERE b.id = ? FOR UPDATE",
            [$bookingId]
        );
        $today = fetchOne("SELECT CURDATE() AS today")['today'] ?? '';
        if (!$booking || $booking['status'] !== 'pending' || $booking['scheduled_date'] <= $today) {
            throw new RuntimeException('Only pending upcoming bookings can be confirmed here.');
        }
        if (depositIsRequired() && depositPaidRow($bookingId) === null) {
            throw new RuntimeException('Reservation deposit must be paid before this booking can be confirmed.');
        }

        $confirm = $db->prepare("UPDATE bookings SET status = 'confirmed' WHERE id = ? AND status = 'pending'");
        $confirm->execute([$bookingId]);
        if ($confirm->rowCount() !== 1) {
            throw new RuntimeException('This booking changed before it could be confirmed.');
        }
        $db->commit();
        return $booking;
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
}

/** Assign a technician to an already-confirmed future appointment without changing its status. */
function bkAssignUpcomingBooking(int $bookingId, int $technicianId): array {
    $db = getDB();
    try {
        $db->beginTransaction();
        $booking = fetchOne(
            "SELECT b.*, u.name AS customer_name
             FROM bookings b
             JOIN users u ON u.id = b.user_id
             WHERE b.id = ? FOR UPDATE",
            [$bookingId]
        );
        $today = fetchOne("SELECT CURDATE() AS today")['today'] ?? '';
        if (!$booking || $booking['status'] !== 'confirmed' || $booking['scheduled_date'] <= $today || !empty($booking['technician_id'])) {
            throw new RuntimeException('Only unassigned confirmed upcoming bookings can be assigned here.');
        }

        $assign = $db->prepare(
            "UPDATE bookings
             SET technician_id = ?, assigned_at = NOW()
             WHERE id = ? AND status = 'confirmed' AND technician_id IS NULL"
        );
        $assign->execute([$technicianId, $bookingId]);
        if ($assign->rowCount() !== 1) {
            throw new RuntimeException('This booking changed before the technician could be assigned.');
        }
        $db->commit();
        return $booking;
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
}

$currentUser = getCurrentUser();

$validStatuses = ['pending', 'confirmed', 'in_progress', 'completed', 'cancelled'];

// Notifications stay unread until explicitly marked via the bell's
// "Mark all read" — keeps the unread-count badge meaningful.

// ---------- POST HANDLER ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action    = $_POST['action'] ?? '';
    $bookingId = (int)($_POST['booking_id'] ?? 0);

    // UPCOMING CONFIRMATION — payment and technician assignment stay separate.
    if ($action === 'confirm_upcoming_booking' && $bookingId > 0) {
        try {
            $booking = bkConfirmUpcomingBooking($bookingId);
            $note = bkNotifyConfirmed($bookingId);
            flashMessage('bk_success', "Booking #$bookingId confirmed. Technician assignment can be completed separately." . ($note !== '' ? ' ' . $note : ''));
        } catch (Throwable $e) {
            error_log("Upcoming booking confirmation failed for booking {$bookingId}: " . $e->getMessage());
            flashMessage('bk_error', $e instanceof RuntimeException ? $e->getMessage() : 'Unable to confirm this booking. Please try again.');
        }
        redirect(baseUrl('staff/bookings.php?view=upcoming'));
    }

    // MANUAL ASSIGN — the staff-selected technician is the only assignment source.
    if ($action === 'manual_assign' && $bookingId > 0) {
        $techId = (int)($_POST['technician_id'] ?? 0);

        // Manual assignment is a staff choice: active, on-duty technicians only.
        $tech = bkManualAssignableTechnician($techId);

        if (!$tech) {
            flashMessage('bk_error', 'Please select a valid technician before confirming.');
            redirect(baseUrl('staff/bookings.php'));
        }

        if (($tech['availability_status'] ?? 'off_duty') === 'off_duty') {
            flashMessage('bk_error', "{$tech['name']} is off duty and cannot be manually assigned.");
            redirect(baseUrl('staff/bookings.php'));
        }

        $booking = fetchOne("SELECT b.*, u.name AS customer_name FROM bookings b JOIN users u ON u.id = b.user_id WHERE b.id = ?", [$bookingId]);

        if (!$booking || $booking['status'] !== 'pending') {
            flashMessage('bk_error', 'This booking cannot be confirmed (it may have already been processed).');
            redirect(baseUrl('staff/bookings.php'));
        }

        // The reservation deposit must be settled before a booking is confirmed.
        if (!depositIsSettled($bookingId)) {
            flashMessage('bk_error', "Booking #$bookingId cannot be confirmed — the customer's reservation deposit has not been paid.");
            redirect(baseUrl('staff/bookings.php'));
        }

        // Keep the staff's explicit choice atomic. A concurrent status change must
        // fail visibly instead of reporting a successful assignment that did not persist.
        $db = getDB();
        try {
            $db->beginTransaction();
            $lockedBooking = fetchOne(
                "SELECT b.*, u.name AS customer_name
                 FROM bookings b
                 JOIN users u ON u.id = b.user_id
                 WHERE b.id = ? FOR UPDATE",
                [$bookingId]
            );

            if (!$lockedBooking || $lockedBooking['status'] !== 'pending') {
                throw new RuntimeException('This booking cannot be confirmed because it was already processed.');
            }

            $assignment = $db->prepare(
                "UPDATE bookings
                 SET status = 'confirmed', technician_id = ?, assigned_at = NOW()
                 WHERE id = ? AND status = 'pending'"
            );
            $assignment->execute([$techId, $bookingId]);
            if ($assignment->rowCount() !== 1) {
                throw new RuntimeException('This booking changed before the manual assignment could be saved.');
            }

            $db->commit();
            $booking = $lockedBooking;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Manual technician assignment failed for booking {$bookingId}: " . $e->getMessage());
            $message = $e instanceof RuntimeException
                ? $e->getMessage()
                : 'Unable to save the manual assignment. Please try again.';
            flashMessage('bk_error', $message);
            redirect(baseUrl('staff/bookings.php'));
        }

        // Notify the assigned technician
        $scheduledDate = date('M j, Y', strtotime($booking['scheduled_date']));
        createNotification(
            $techId,
            "New job assigned to you: Booking #$bookingId for {$booking['customer_name']} on $scheduledDate.",
            'assignment',
            $bookingId
        );

        $note = bkNotifyConfirmed($bookingId);
        flashMessage('bk_success', "Booking #$bookingId confirmed and manually assigned to {$tech['name']}." . ($note !== '' ? ' ' . $note : ''));
        redirect(baseUrl('staff/bookings.php'));
    }

    // AUTO ASSIGN — this is the only action permitted to invoke the ranking algorithm.
    if ($action === 'auto_assign' && $bookingId > 0) {
        $booking = fetchOne("SELECT b.*, u.name AS customer_name FROM bookings b JOIN users u ON u.id = b.user_id WHERE b.id = ?", [$bookingId]);

        if (!$booking || $booking['status'] !== 'pending') {
            flashMessage('bk_error', 'This booking cannot be confirmed (it may have already been processed).');
            redirect(baseUrl('staff/bookings.php'));
        }

        if (!depositIsSettled($bookingId)) {
            flashMessage('bk_error', "Booking #$bookingId cannot be confirmed — the customer's reservation deposit has not been paid.");
            redirect(baseUrl('staff/bookings.php'));
        }

        $tech = techAutoAssignCandidate($bookingId);
        if (!$tech) {
            flashMessage('bk_error', "No technician is currently Ready/On Site and qualified for all of booking #$bookingId's services. Use Manual Assign, or update technician availability/skills.");
            redirect(baseUrl('staff/bookings.php'));
        }

        getDB()->prepare("UPDATE bookings SET status = 'confirmed', technician_id = ?, assigned_at = NOW() WHERE id = ? AND status = 'pending'")->execute([$tech['id'], $bookingId]);

        $scheduledDate = date('M j, Y', strtotime($booking['scheduled_date']));
        createNotification(
            $tech['id'],
            "New job assigned to you: Booking #$bookingId for {$booking['customer_name']} on $scheduledDate.",
            'assignment',
            $bookingId
        );

        $note = bkNotifyConfirmed($bookingId);
        flashMessage('bk_success', "Booking #$bookingId confirmed — auto-assigned to {$tech['name']}." . ($note !== '' ? ' ' . $note : ''));
        redirect(baseUrl('staff/bookings.php'));
    }

    // FUTURE REASSIGN (MANUAL) — explicit staff choice; never calls auto assignment.
    if ($action === 'upcoming_manual_reassign' && $bookingId > 0) {
        $techId = (int)($_POST['technician_id'] ?? 0);
        $tech = bkManualAssignableTechnician($techId);
        if (!$tech) {
            flashMessage('bk_error', 'Please select a valid technician.');
            redirect(baseUrl('staff/bookings.php?view=upcoming'));
        }
        if (($tech['availability_status'] ?? 'off_duty') === 'off_duty') {
            flashMessage('bk_error', "{$tech['name']} is off duty and cannot be manually assigned.");
            redirect(baseUrl('staff/bookings.php?view=upcoming'));
        }
        try {
            $booking = bkReassignUpcomingBooking($bookingId, $techId);
            $scheduledDate = date('M j, Y', strtotime($booking['scheduled_date']));
            createNotification($techId, "Future job reassigned to you: Booking #$bookingId for {$booking['customer_name']} on $scheduledDate.", 'assignment', $bookingId);
            flashMessage('bk_success', "Booking #$bookingId reassigned to {$tech['name']}.");
        } catch (Throwable $e) {
            error_log("Upcoming manual reassignment failed for booking {$bookingId}: " . $e->getMessage());
            flashMessage('bk_error', $e instanceof RuntimeException ? $e->getMessage() : 'Unable to reassign this upcoming booking.');
        }
        redirect(baseUrl('staff/bookings.php?view=upcoming'));
    }

    // FUTURE ASSIGN (MANUAL) — exact staff selection after appointment confirmation.
    if ($action === 'upcoming_manual_assign' && $bookingId > 0) {
        $techId = (int)($_POST['technician_id'] ?? 0);
        $tech = bkManualAssignableTechnician($techId);
        if (!$tech || ($tech['availability_status'] ?? 'off_duty') === 'off_duty') {
            flashMessage('bk_error', $tech ? "{$tech['name']} is off duty and cannot be manually assigned." : 'Please select a valid technician.');
            redirect(baseUrl('staff/bookings.php?view=upcoming'));
        }
        try {
            $booking = bkAssignUpcomingBooking($bookingId, $techId);
            $scheduledDate = date('M j, Y', strtotime($booking['scheduled_date']));
            createNotification($techId, "Future job assigned to you: Booking #$bookingId for {$booking['customer_name']} on $scheduledDate.", 'assignment', $bookingId);
            flashMessage('bk_success', "Booking #$bookingId assigned to {$tech['name']}.");
        } catch (Throwable $e) {
            error_log("Upcoming manual assignment failed for booking {$bookingId}: " . $e->getMessage());
            flashMessage('bk_error', $e instanceof RuntimeException ? $e->getMessage() : 'Unable to assign this upcoming booking.');
        }
        redirect(baseUrl('staff/bookings.php?view=upcoming'));
    }

    // FUTURE ASSIGN (AUTO) — the existing algorithm is only run on this explicit action.
    if ($action === 'upcoming_auto_assign' && $bookingId > 0) {
        $tech = techAutoAssignCandidate($bookingId);
        if (!$tech) {
            flashMessage('bk_error', "No Ready/On Site technician is qualified for all of booking #$bookingId's services.");
            redirect(baseUrl('staff/bookings.php?view=upcoming'));
        }
        try {
            $booking = bkAssignUpcomingBooking($bookingId, (int)$tech['id']);
            $scheduledDate = date('M j, Y', strtotime($booking['scheduled_date']));
            createNotification((int)$tech['id'], "Future job assigned to you: Booking #$bookingId for {$booking['customer_name']} on $scheduledDate.", 'assignment', $bookingId);
            flashMessage('bk_success', "Booking #$bookingId auto-assigned to {$tech['name']}.");
        } catch (Throwable $e) {
            error_log("Upcoming auto assignment failed for booking {$bookingId}: " . $e->getMessage());
            flashMessage('bk_error', $e instanceof RuntimeException ? $e->getMessage() : 'Unable to assign this upcoming booking.');
        }
        redirect(baseUrl('staff/bookings.php?view=upcoming'));
    }

    // FUTURE REASSIGN (AUTO) — explicit modal choice; retains the existing ranking algorithm.
    if ($action === 'upcoming_auto_reassign' && $bookingId > 0) {
        $tech = techAutoAssignCandidate($bookingId);
        if (!$tech) {
            flashMessage('bk_error', "No Ready/On Site technician is qualified for all of booking #$bookingId's services.");
            redirect(baseUrl('staff/bookings.php?view=upcoming'));
        }
        try {
            $booking = bkReassignUpcomingBooking($bookingId, (int)$tech['id']);
            $scheduledDate = date('M j, Y', strtotime($booking['scheduled_date']));
            createNotification((int)$tech['id'], "Future job reassigned to you: Booking #$bookingId for {$booking['customer_name']} on $scheduledDate.", 'assignment', $bookingId);
            flashMessage('bk_success', "Booking #$bookingId reassigned by Auto Assign to {$tech['name']}.");
        } catch (Throwable $e) {
            error_log("Upcoming auto reassignment failed for booking {$bookingId}: " . $e->getMessage());
            flashMessage('bk_error', $e instanceof RuntimeException ? $e->getMessage() : 'Unable to reassign this upcoming booking.');
        }
        redirect(baseUrl('staff/bookings.php?view=upcoming'));
    }

    // CANCEL BOOKING
    if ($action === 'cancel_booking' && $bookingId > 0) {
        $booking = fetchOne("SELECT * FROM bookings WHERE id = ?", [$bookingId]);
        if ($booking && in_array($booking['status'], ['pending', 'confirmed'], true)) {
            getDB()->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ?")->execute([$bookingId]);
            try { partsReleaseForBooking($bookingId); } catch (Throwable $e) {}
            flashMessage('bk_success', "Booking #$bookingId has been cancelled.");
        } else {
            flashMessage('bk_error', 'Only pending or confirmed bookings can be cancelled.');
        }
        redirect(baseUrl('staff/bookings.php'));
    }

    // GENERIC STATUS UPDATE (in_progress / completed)
    if ($action === 'update_status' && $bookingId > 0) {
        $newStatus = $_POST['status'] ?? '';
        if (in_array($newStatus, $validStatuses, true)) {
            if (!depositIsSettled($bookingId)) {
                flashMessage('bk_error', "Booking #$bookingId cannot enter the service workflow until its reservation deposit is paid and verified.");
                redirect(baseUrl('staff/bookings.php'));
            }

            $db = getDB();
            $db->beginTransaction();
            try {
                if ($newStatus === 'completed') {
                    partsConsumeForBooking($bookingId);
                } elseif ($newStatus === 'cancelled') {
                    partsReleaseForBooking($bookingId);
                }
                if ($newStatus === 'completed') {
                    $db->prepare("UPDATE bookings SET status = ?, completed_at = COALESCE(completed_at, NOW()) WHERE id = ?")->execute([$newStatus, $bookingId]);
                } else {
                    $db->prepare("UPDATE bookings SET status = ? WHERE id = ?")->execute([$newStatus, $bookingId]);
                }
                $db->commit();
                flashMessage('bk_success', 'Booking status updated.');
            } catch (Throwable $e) {
                if ($db->inTransaction()) $db->rollBack();
                flashMessage('bk_error', $e->getMessage());
            }
        } else {
            flashMessage('bk_error', 'Invalid status.');
        }
        redirect(baseUrl('staff/bookings.php'));
    }
}

// ---------- CONFIRM QUICK-ACTION FROM DASHBOARD ----------
$preloadConfirmId = 0;
if (isset($_GET['action']) && $_GET['action'] === 'confirm' && isset($_GET['id'])) {
    $preloadConfirmId = (int)$_GET['id'];
}

// ---------- FILTERS ----------
$flash    = getFlash('bk_success');
$flashErr = getFlash('bk_error');
$statusFilter = $_GET['status'] ?? '';
$statusFilter = in_array($statusFilter, $validStatuses, true) ? $statusFilter : '';
$search = trim($_GET['q'] ?? '');
$bookingView = $_GET['view'] ?? 'today';
$bookingView = in_array($bookingView, ['today', 'upcoming', 'history'], true) ? $bookingView : 'today';

$depositRequired = depositIsRequired();
$paidBookingVisibility = $depositRequired
    ? "EXISTS (SELECT 1 FROM booking_deposits bd WHERE bd.booking_id = b.id AND bd.status = 'paid')"
    : '1=1';

$activeBookingSql = "b.status NOT IN ('completed', 'cancelled')";
$viewDefinitions = [
    'today' => [
        'label' => 'Today',
        'heading' => "Today's Bookings",
        'description' => 'Appointments scheduled for today that still need operational attention.',
        'where' => "b.scheduled_date = CURDATE() AND $activeBookingSql",
        'order' => 'b.scheduled_time ASC, b.id ASC',
    ],
    'upcoming' => [
        'label' => 'Upcoming',
        'heading' => 'Upcoming Bookings',
        'description' => 'Planned appointments, ordered from the nearest schedule first.',
        'where' => "b.scheduled_date > CURDATE() AND $activeBookingSql",
        'order' => 'b.scheduled_date ASC, b.scheduled_time ASC, b.id ASC',
    ],
    'history' => [
        'label' => 'History',
        'heading' => 'Booking History',
        'description' => 'Past appointments and completed or cancelled booking records.',
        'where' => "(b.scheduled_date < CURDATE() OR b.status IN ('completed', 'cancelled'))",
        'order' => 'b.scheduled_date DESC, b.scheduled_time DESC, b.id DESC',
    ],
];
$currentView = $viewDefinitions[$bookingView];

$viewCounts = ['today' => 0, 'upcoming' => 0, 'history' => 0];
$viewCountRow = fetchOne(
    "SELECT
        COALESCE(SUM(CASE WHEN b.scheduled_date = CURDATE() AND $activeBookingSql THEN 1 ELSE 0 END), 0) AS today_count,
        COALESCE(SUM(CASE WHEN b.scheduled_date > CURDATE() AND $activeBookingSql THEN 1 ELSE 0 END), 0) AS upcoming_count,
        COALESCE(SUM(CASE WHEN b.scheduled_date < CURDATE() OR b.status IN ('completed', 'cancelled') THEN 1 ELSE 0 END), 0) AS history_count
     FROM bookings b
     WHERE $paidBookingVisibility"
);
$viewCounts['today'] = (int)($viewCountRow['today_count'] ?? 0);
$viewCounts['upcoming'] = (int)($viewCountRow['upcoming_count'] ?? 0);
$viewCounts['history'] = (int)($viewCountRow['history_count'] ?? 0);

$where  = [$paidBookingVisibility, $currentView['where']];
$params = [];
if ($statusFilter !== '') {
    $where[]  = 'b.status = ?';
    $params[] = $statusFilter;
}
if ($search !== '') {
    $where[]  = '(u.name LIKE ? OR u.email LIKE ? OR b.id = ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = (int)$search;
}

$bookings = fetchAllRows(
    "SELECT
        b.*,
        u.name AS customer_name,
        u.email AS customer_email,
        u.phone AS customer_phone,
        CONCAT(mb.name, ' ', mm.name) AS vehicle_name,
        mt.name AS type_name,
        cv.cc,
        cv.plate_number,
        svc.services,
        prod.products,
        tech.name AS technician_name,
        EXISTS (SELECT 1 FROM booking_deposits bd WHERE bd.booking_id = b.id AND bd.status = 'paid') AS deposit_paid,
        DATEDIFF(b.scheduled_date, CURDATE()) AS schedule_offset_days
     FROM bookings b
     JOIN users u ON u.id = b.user_id
     LEFT JOIN customer_vehicles cv ON cv.id = b.vehicle_id
     LEFT JOIN motorcycle_brands mb ON mb.id = cv.brand_id
     LEFT JOIN motorcycle_models mm ON mm.id = cv.model_id
     LEFT JOIN motorcycle_types mt ON mt.id = cv.type_id
     LEFT JOIN users tech ON tech.id = b.technician_id
     LEFT JOIN (
       SELECT booking_id, GROUP_CONCAT(service_name ORDER BY id SEPARATOR ', ') AS services
       FROM booking_services GROUP BY booking_id
     ) svc ON svc.booking_id = b.id
     LEFT JOIN (
       SELECT booking_id, GROUP_CONCAT(product_name ORDER BY id SEPARATOR ', ') AS products
       FROM booking_products GROUP BY booking_id
     ) prod ON prod.booking_id = b.id
     " . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . "
     ORDER BY {$currentView['order']}",
    $params
);

// Technicians for assign dropdown (with availability for the manual-assign markers)
$technicians = fetchAllRows(
    "SELECT id, name, availability_status FROM users WHERE role = 'technician' AND is_active = 1 ORDER BY name"
);

// Qualification data for manual-assign warning markers: which services each tech
// can do, and which services each listed booking requires.
$techQualifications = techQualificationMap();
$bookingServiceIds = [];
foreach (fetchAllRows("SELECT booking_id, service_id FROM booking_services") as $bsRow) {
    $bookingServiceIds[(int)$bsRow['booking_id']][] = (int)$bsRow['service_id'];
}

$statusColor = [
    'pending'     => '#6b7280',
    'confirmed'   => '#2563eb',
    'in_progress' => '#d97706',
    'completed'   => '#15803d',
    'cancelled'   => '#b91c1c',
];

// KPI cards: overall booking counts by status (independent of the list filters)
$statusCounts = ['pending' => 0, 'confirmed' => 0, 'in_progress' => 0, 'completed' => 0, 'cancelled' => 0];
foreach (fetchAllRows(
    "SELECT b.status, COUNT(*) AS n
     FROM bookings b
     WHERE $paidBookingVisibility
     GROUP BY b.status"
) as $scRow) {
    if (isset($statusCounts[$scRow['status']])) {
        $statusCounts[$scRow['status']] = (int)$scRow['n'];
    }
}
$totalBookings = array_sum($statusCounts);
$statusPct = static fn(int $n): int => $totalBookings > 0 ? (int)round($n / $totalBookings * 100) : 0;

$bookingStatCards = [
    ['label' => 'Total Bookings', 'count' => $totalBookings,                'icon' => 'fa-layer-group',     'color' => '#d71920', 'desc' => 'All service bookings'],
    ['label' => 'Pending',        'count' => $statusCounts['pending'],      'icon' => 'fa-hourglass-half',  'color' => '#e8b93c', 'desc' => 'Awaiting confirmation'],
    ['label' => 'Confirmed',      'count' => $statusCounts['confirmed'],    'icon' => 'fa-calendar-check',  'color' => '#4f8df9', 'desc' => 'Assigned & scheduled'],
    ['label' => 'In Progress',    'count' => $statusCounts['in_progress'],  'icon' => 'fa-wrench',          'color' => '#f0883e', 'desc' => 'Being serviced now'],
    ['label' => 'Completed',      'count' => $statusCounts['completed'],    'icon' => 'fa-flag-checkered',  'color' => '#2fbf71', 'desc' => 'Finished jobs'],
    ['label' => 'Cancelled',      'count' => $statusCounts['cancelled'],    'icon' => 'fa-ban',             'color' => '#f16a6a', 'desc' => 'Called off'],
];

$bookingViewUrl = static function (string $view) use ($search, $statusFilter): string {
    $query = ['view' => $view];
    if ($search !== '') $query['q'] = $search;
    if ($statusFilter !== '') $query['status'] = $statusFilter;
    $url = baseUrl('staff/bookings.php');
    return $url . (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
};

require_once __DIR__ . '/../includes/staff-sidebar.php';
?>

<div class="mtx-shell">

<header class="mtx-page-head">
  <div class="mtx-page-head-copy">
    <span class="eyebrow">Staff Panel</span>
    <h1>Bookings</h1>
    <p>Confirm appointments, assign technicians, and track active service visits.</p>
  </div>
  <div class="mtx-head-actions">
    <a href="<?= baseUrl('staff/new-booking.php') ?>" class="mtx-btn mtx-btn--primary"><i class="fas fa-plus"></i> New Booking</a>
  </div>
</header>

<!-- Booking status KPI cards -->
<section class="mtx-kpi-grid mtx-kpi-grid--6" aria-label="Booking status">
  <?php foreach ($bookingStatCards as $card): ?>
    <article class="mtx-kpi" style="--kpi-color: <?= $card['color'] ?>;">
      <div class="mtx-kpi-top">
        <span class="mtx-kpi-label"><?= $card['label'] ?></span>
        <span class="mtx-kpi-icon"><i class="fas <?= $card['icon'] ?>"></i></span>
      </div>
      <span class="mtx-kpi-value"><?= $card['count'] ?></span>
      <span class="mtx-kpi-sub">
        <?= $card['desc'] ?><?php if ($card['label'] !== 'Total Bookings'): ?> · <strong><?= $statusPct($card['count']) ?>%</strong><?php endif; ?>
      </span>
    </article>
  <?php endforeach; ?>
</section>

<?php if ($flash): ?><div class="alert success"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
<?php if ($flashErr): ?><div class="alert error"><?= htmlspecialchars($flashErr) ?></div><?php endif; ?>

<nav class="mtx-booking-tabs" aria-label="Booking schedule views">
  <?php foreach ($viewDefinitions as $viewKey => $view): ?>
    <a href="<?= htmlspecialchars($bookingViewUrl($viewKey)) ?>" class="mtx-booking-tab <?= $bookingView === $viewKey ? 'is-active' : '' ?>" <?= $bookingView === $viewKey ? 'aria-current="page"' : '' ?>>
      <i class="fas fa-<?= $viewKey === 'today' ? 'calendar-day' : ($viewKey === 'upcoming' ? 'calendar-plus' : 'clock-rotate-left') ?>" aria-hidden="true"></i>
      <span><?= htmlspecialchars($view['label']) ?></span>
      <?php if ($viewKey !== 'history' || $viewCounts[$viewKey] > 0): ?><strong><?= $viewCounts[$viewKey] ?></strong><?php endif; ?>
    </a>
  <?php endforeach; ?>
</nav>

<section class="mtx-card mtx-card--flush">
  <div class="mtx-card-head">
    <div>
      <h2><i class="fas fa-<?= $bookingView === 'today' ? 'calendar-day' : ($bookingView === 'upcoming' ? 'calendar-plus' : 'clock-rotate-left') ?>"></i> <?= htmlspecialchars($currentView['heading']) ?></h2>
      <p><?= htmlspecialchars($currentView['description']) ?></p>
    </div>
    <form method="get" class="mtx-toolbar">
      <?= authContextField() ?>
      <input type="hidden" name="view" value="<?= htmlspecialchars($bookingView) ?>">
      <div class="mtx-field-search">
        <i class="fas fa-magnifying-glass"></i>
        <input type="search" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Customer or #ID">
      </div>
      <select name="status">
        <option value="">All statuses</option>
        <?php foreach ($validStatuses as $s): ?>
          <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>>
            <?= ucfirst(str_replace('_', ' ', $s)) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="mtx-btn mtx-btn--dark">Filter</button>
      <?php if ($search || $statusFilter): ?>
        <a href="<?= htmlspecialchars($bookingViewUrl($bookingView)) ?>" class="mtx-btn mtx-btn--ghost">Reset</a>
      <?php endif; ?>
    </form>
  </div>

  <?php if ($bookings): ?>
    <div class="mtx-table-wrap">
      <table class="mtx-table">
        <thead>
          <tr>
            <th>Schedule</th>
            <th>Customer</th>
            <th>Motorcycle</th>
            <th>Services / Products</th>
            <th class="num">Total</th>
            <th>Status</th>
            <th>Technician</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($bookings as $b):
            $bid    = (int)$b['id'];
            $color  = $statusColor[$b['status']] ?? '#6b7280';
            $isPending   = $b['status'] === 'pending';
            $isCancellable = in_array($b['status'], ['pending', 'confirmed'], true);
            $highlightRow = ($preloadConfirmId === $bid) ? 'style="background:#eff6ff;"' : '';
            $scheduleOffset = (int)($b['schedule_offset_days'] ?? 0);
            $scheduleLabel = bkScheduleOffsetLabel($scheduleOffset);
            $historyScheduleLabel = in_array($b['status'], ['completed', 'cancelled'], true)
                ? ucfirst($b['status'])
                : $scheduleLabel;
            $needsTodayAttention = $bookingView === 'today' && $isPending && empty($b['technician_id']);
            $serviceList = $b['services'] ? array_map('trim', explode(',', $b['services'])) : [];
            $shownServices = array_slice($serviceList, 0, 2);
            $moreServices = count($serviceList) - count($shownServices);
          ?>
            <tr class="<?= $needsTodayAttention ? 'mtx-booking-row--attention' : '' ?>" <?= $highlightRow ?>>
              <td>
                <div class="mtx-cell-main">
                  <strong><?= htmlspecialchars(date('M j, Y', strtotime($b['scheduled_date']))) ?></strong>
                  <span class="mtx-cell-sub"><?= $b['scheduled_time'] ? htmlspecialchars(date('g:i A', strtotime($b['scheduled_time']))) : 'No time set' ?> · #<?= $bid ?></span>
                  <?php if ($bookingView !== 'today'): ?>
                    <span class="mtx-schedule-context <?= $bookingView === 'upcoming' ? 'is-upcoming' : '' ?>"><i class="fas fa-<?= $bookingView === 'upcoming' ? 'calendar-plus' : 'clock-rotate-left' ?>" aria-hidden="true"></i> <?= htmlspecialchars($bookingView === 'history' ? $historyScheduleLabel : $scheduleLabel) ?></span>
                  <?php endif; ?>
                </div>
              </td>
              <td>
                <div class="mtx-cell-main">
                  <strong><?= htmlspecialchars($b['customer_name']) ?></strong>
                  <span class="mtx-cell-sub"><?= htmlspecialchars($b['customer_email']) ?></span>
                  <?php if ($b['customer_phone']): ?>
                    <span class="mtx-cell-sub"><?= htmlspecialchars($b['customer_phone']) ?></span>
                  <?php endif; ?>
                </div>
              </td>
              <td>
                <div class="mtx-cell-main">
                  <strong><?= htmlspecialchars($b['vehicle_name'] ?: 'No vehicle') ?></strong>
                  <span class="mtx-cell-sub">
                    <?= $b['type_name'] ? htmlspecialchars($b['type_name']) . ' · ' . (int)$b['cc'] . 'cc' : '' ?><?= $b['plate_number'] ? ' · ' . htmlspecialchars($b['plate_number']) : '' ?>
                  </span>
                </div>
              </td>
              <td>
                <div class="mtx-cell-main">
                  <?php if ($shownServices): ?>
                    <span style="display:flex;gap:5px;flex-wrap:wrap;">
                      <?php foreach ($shownServices as $svcName): ?>
                        <span class="mtx-pill" style="--pill-color:#2563eb;"><?= htmlspecialchars($svcName) ?></span>
                      <?php endforeach; ?>
                      <?php if ($moreServices > 0): ?>
                        <span class="mtx-pill" style="--pill-color:#6b7280;" title="<?= htmlspecialchars(implode(', ', array_slice($serviceList, 2))) ?>">+<?= $moreServices ?> more</span>
                      <?php endif; ?>
                    </span>
                  <?php else: ?>
                    <span class="mtx-cell-sub">—</span>
                  <?php endif; ?>
                  <?php if ($b['products']): ?>
                    <span class="mtx-cell-sub" title="<?= htmlspecialchars($b['products']) ?>"><i class="fas fa-box" style="color:#d97706;"></i> <?= htmlspecialchars(mb_strlen($b['products']) > 48 ? mb_substr($b['products'], 0, 48) . '…' : $b['products']) ?></span>
                  <?php endif; ?>
                </div>
              </td>
              <td class="num"><span class="mtx-money"><?= formatPrice((float)$b['total_amount']) ?></span></td>
              <td>
                <span class="mtx-pill" style="--pill-color:<?= $color ?>;">
                  <?= ucfirst(str_replace('_', ' ', $b['status'])) ?>
                </span>
              </td>

              <!-- Technician column — Feature 5: skill-match indicator -->
              <td>
                <?php if ($b['technician_name']): ?>
                  <?php
                    $tid      = (int)($b['technician_id'] ?? 0);
                    $reqIds   = $bookingServiceIds[$bid] ?? [];
                    $techQual = $techQualifications[$tid] ?? [];
                    $fullyQualified = !$reqIds
                      || count(array_intersect(array_keys($techQual), $reqIds)) === count($reqIds);
                  ?>
                  <div style="display:flex;flex-direction:column;gap:4px;">
                    <span class="mtx-pill" style="--pill-color:#15803d;">
                      <i class="fas fa-user-cog"></i> <?= htmlspecialchars($b['technician_name']) ?>
                    </span>
                    <span style="font-size:.72rem;font-weight:700;padding:2px 7px;border-radius:20px;width:fit-content;
                      background:<?= $fullyQualified ? 'rgba(21,128,61,.1)' : 'rgba(217,119,6,.1)' ?>;
                      color:<?= $fullyQualified ? '#15803d' : '#d97706' ?>;">
                      <i class="fas fa-<?= $fullyQualified ? 'circle-check' : 'triangle-exclamation' ?>" aria-hidden="true"></i>
                      <?= $fullyQualified ? 'Qualified' : 'Partial Match' ?>
                    </span>
                  </div>
                <?php else: ?>
                  <span class="mtx-cell-sub">Unassigned</span>
                <?php endif; ?>
              </td>

              <!-- Actions column -->
              <td>
                <div class="mtx-booking-actions">
                <?php if ($bookingView === 'today' && $isPending): ?>
                  <div class="mtx-booking-assignment-actions">
                    <!-- AUTO ASSIGN = the existing fairness / qualification algorithm -->
                    <form method="post" id="auto-assign-form-<?= $bid ?>">
                    <?= authContextField() ?>
                    <input type="hidden" name="action" value="auto_assign">
                    <input type="hidden" name="booking_id" value="<?= $bid ?>">
                    <button type="submit" class="mtx-btn mtx-btn--primary mtx-btn--sm">
                      <i class="fas fa-bolt"></i> Auto Assign
                    </button>
                  </form>

                    <!-- MANUAL ASSIGN never calls the automatic selection algorithm. -->
                    <button type="button" class="mtx-btn mtx-btn--ghost mtx-btn--sm"
                            id="manual-assign-trigger-<?= $bid ?>"
                            data-open-manual-assign
                            data-booking-id="<?= $bid ?>"
                            data-booking-label="Booking #<?= $bid ?>">
                      <i class="fas fa-user-cog"></i> Manual Assign
                    </button>
                  </div>
                <?php endif; ?>

                <?php if ($bookingView === 'upcoming' && $isPending && (!$depositRequired || !empty($b['deposit_paid']))): ?>
                  <button type="button" class="mtx-btn mtx-btn--primary mtx-btn--sm"
                          data-open-upcoming-confirmation
                          data-booking-id="<?= $bid ?>"
                          data-customer-name="<?= htmlspecialchars($b['customer_name']) ?>"
                          data-scheduled-date="<?= htmlspecialchars(date('F j, Y', strtotime($b['scheduled_date']))) ?>"
                          data-scheduled-time="<?= htmlspecialchars($b['scheduled_time'] ? date('g:i A', strtotime($b['scheduled_time'])) : 'No time set') ?>"
                          data-deposit-label="<?= $depositRequired ? 'Paid' : 'Not required' ?>">
                    <i class="fas fa-calendar-check"></i> Confirm Booking
                  </button>
                <?php endif; ?>

                <?php if ($bookingView === 'upcoming' && $b['status'] === 'confirmed' && empty($b['technician_id'])): ?>
                  <button type="button" class="mtx-btn mtx-btn--primary mtx-btn--sm"
                          data-open-upcoming-assignment
                          data-assignment-mode="assign"
                          data-booking-id="<?= $bid ?>"
                          data-booking-label="Booking #<?= $bid ?>"
                          data-scheduled-date="<?= htmlspecialchars(date('F j, Y', strtotime($b['scheduled_date']))) ?>"
                          data-scheduled-time="<?= htmlspecialchars($b['scheduled_time'] ? date('g:i A', strtotime($b['scheduled_time'])) : 'No time set') ?>"
                          data-schedule-offset="<?= $scheduleOffset ?>">
                    <i class="fas fa-user-plus"></i> Assign Technician
                  </button>
                <?php endif; ?>

                <?php if ($bookingView === 'upcoming' && $b['status'] === 'confirmed' && !empty($b['technician_id'])): ?>
                  <button type="button" class="mtx-btn mtx-btn--ghost mtx-btn--sm"
                          data-open-upcoming-assignment
                          data-assignment-mode="reassign"
                          data-booking-id="<?= $bid ?>"
                          data-booking-label="Booking #<?= $bid ?>"
                          data-current-technician="<?= htmlspecialchars($b['technician_name']) ?>"
                          data-scheduled-date="<?= htmlspecialchars(date('F j, Y', strtotime($b['scheduled_date']))) ?>"
                          data-scheduled-time="<?= htmlspecialchars($b['scheduled_time'] ? date('g:i A', strtotime($b['scheduled_time'])) : 'No time set') ?>"
                          data-schedule-offset="<?= $scheduleOffset ?>">
                    <i class="fas fa-user-pen"></i> Change Technician
                  </button>
                <?php endif; ?>

                <?php if ($isCancellable): ?>
                  <form method="post" onsubmit="return confirm('Cancel booking #<?= $bid ?>?');">
                    <?= authContextField() ?>
                    <input type="hidden" name="action" value="cancel_booking">
                    <input type="hidden" name="booking_id" value="<?= $bid ?>">
                    <button type="submit" class="mtx-btn mtx-btn--ghost mtx-btn--sm" style="width:100%;color:#b91c1c;border-color:#f3c1c1;">
                      <i class="fas fa-times"></i> Cancel
                    </button>
                  </form>
                <?php endif; ?>

                <!-- Always show View Details -->
                <a href="<?= baseUrl('staff/booking-detail.php?id=' . $bid) ?>" class="mtx-btn mtx-btn--ghost mtx-btn--sm" style="width:100%;">
                  <i class="fas fa-eye"></i> View Details
                </a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="mtx-card-foot">
      <span>Showing <?= count($bookings) ?> booking<?= count($bookings) !== 1 ? 's' : '' ?><?= ($search || $statusFilter) ? ' (filtered)' : '' ?></span>
    </div>
  <?php else: ?>
    <div style="padding:24px;">
      <div class="mtx-empty">
        <i class="fas fa-calendar-xmark"></i>
        <strong>No bookings found.</strong>
        <span>Try a different search or status filter.</span>
      </div>
    </div>
  <?php endif; ?>
</section>

</div><!-- /.mtx-shell -->

<div class="mtx-modal mtx-upcoming-confirm-modal" id="upcomingConfirmationModal" role="dialog" aria-modal="true" aria-labelledby="upcomingConfirmationTitle" aria-describedby="upcomingConfirmationDescription" hidden>
  <div class="mtx-modal__backdrop" data-close-upcoming-confirmation></div>
  <div class="mtx-modal__dialog mtx-upcoming-confirm-modal__dialog" role="document">
    <button type="button" class="mtx-modal__close" data-close-upcoming-confirmation aria-label="Close confirmation dialog"><i class="fas fa-xmark" aria-hidden="true"></i></button>
    <div class="mtx-upcoming-confirm-modal__icon" aria-hidden="true"><i class="fas fa-calendar-check"></i></div>
    <h2 class="mtx-modal__title" id="upcomingConfirmationTitle">Confirm Appointment?</h2>
    <p class="mtx-modal__meta" id="upcomingConfirmationDescription">Confirming this appointment tells the customer that MotoTrack has accepted their reservation.</p>
    <dl class="mtx-upcoming-confirm-summary">
      <div><dt>Customer</dt><dd id="upcomingConfirmationCustomer"></dd></div>
      <div><dt>Schedule</dt><dd><span id="upcomingConfirmationDate"></span><small id="upcomingConfirmationTime"></small></dd></div>
      <div><dt>Reservation deposit</dt><dd class="is-paid" id="upcomingConfirmationDeposit"><i class="fas fa-circle-check" aria-hidden="true"></i> Paid</dd></div>
    </dl>
    <form method="post" id="upcomingConfirmationForm" class="mtx-upcoming-confirm-form">
      <?= authContextField() ?>
      <input type="hidden" name="action" value="confirm_upcoming_booking">
      <input type="hidden" name="booking_id" id="upcomingConfirmationBookingId" value="">
      <div class="mtx-assignment-actions">
        <button type="button" class="mtx-btn mtx-btn--ghost" data-close-upcoming-confirmation>Cancel</button>
        <button type="submit" class="mtx-btn mtx-btn--primary" id="upcomingConfirmationSubmit"><i class="fas fa-calendar-check" aria-hidden="true"></i> Confirm Appointment</button>
      </div>
    </form>
  </div>
</div>

<div class="mtx-modal mtx-upcoming-assignment-modal" id="upcomingAssignmentModal" role="dialog" aria-modal="true" aria-labelledby="upcomingAssignmentTitle" aria-describedby="upcomingAssignmentDescription" hidden>
  <div class="mtx-modal__backdrop" data-close-upcoming-assignment></div>
  <div class="mtx-modal__dialog mtx-upcoming-assignment-modal__dialog" role="document">
    <button type="button" class="mtx-modal__close" data-close-upcoming-assignment aria-label="Close upcoming assignment dialog"><i class="fas fa-xmark" aria-hidden="true"></i></button>
    <h2 class="mtx-modal__title" id="upcomingAssignmentTitle"><i class="fas fa-calendar-plus" aria-hidden="true"></i> Assign Upcoming Appointment</h2>
    <p class="mtx-modal__meta" id="upcomingAssignmentDescription">Choose how this future appointment should be assigned.</p>
    <form method="post" id="upcomingAssignmentForm" class="mtx-upcoming-assignment-form">
      <?= authContextField() ?>
      <input type="hidden" name="action" id="upcomingAssignmentAction" value="auto_assign">
      <input type="hidden" name="booking_id" id="upcomingAssignmentBookingId" value="">
      <div class="mtx-upcoming-warning">
        <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
        <div><strong>Upcoming appointment</strong><span id="upcomingAssignmentSchedule"></span><small id="upcomingAssignmentDistance"></small></div>
      </div>
      <fieldset class="mtx-assignment-methods">
        <legend>Assignment method</legend>
        <label class="mtx-assignment-method is-selected"><input type="radio" name="assignment_method" value="auto" checked><span><i class="fas fa-bolt" aria-hidden="true"></i><strong>Auto Assign</strong><small>Use the current availability, qualification, workload, and fairness ranking.</small></span></label>
        <label class="mtx-assignment-method"><input type="radio" name="assignment_method" value="manual"><span><i class="fas fa-user-cog" aria-hidden="true"></i><strong>Manual Assign</strong><small>Select the exact on-duty technician yourself.</small></span></label>
      </fieldset>
      <label class="mtx-assignment-field" id="upcomingManualTechnicianField" for="upcomingAssignmentTechnician" hidden>
        <span>Technician</span>
        <select name="technician_id" id="upcomingAssignmentTechnician">
          <option value="">Select a technician</option>
          <?php foreach ($technicians as $t): ?>
            <?php $availability = (string)($t['availability_status'] ?? 'off_duty'); $offDuty = $availability === 'off_duty'; $availabilityLabel = ucwords(str_replace('_', ' ', $availability)); ?>
            <option value="<?= (int)$t['id'] ?>" <?= $offDuty ? 'disabled' : '' ?>><?= htmlspecialchars($t['name'] . ' — ' . $availabilityLabel . ($offDuty ? ' (unavailable)' : '')) ?></option>
          <?php endforeach; ?>
        </select>
        <small class="mtx-assignment-field-note"><i class="fas fa-circle-info" aria-hidden="true"></i> Manual Assign saves your chosen technician directly; it does not run Auto Assign.</small>
      </label>
      <div class="mtx-assignment-actions">
        <button type="button" class="mtx-btn mtx-btn--ghost" data-close-upcoming-assignment>Cancel</button>
        <button type="submit" class="mtx-btn mtx-btn--primary" id="upcomingAssignmentSubmit"><i class="fas fa-user-check" aria-hidden="true"></i> Confirm Assignment</button>
      </div>
    </form>
  </div>
</div>

<div class="mtx-modal mtx-assignment-modal" id="manualAssignModal" role="dialog" aria-modal="true" aria-labelledby="manualAssignTitle" aria-describedby="manualAssignDescription" hidden>
  <div class="mtx-modal__backdrop" data-close-manual-assign></div>
  <div class="mtx-modal__dialog mtx-assignment-modal__dialog" role="document">
    <button type="button" class="mtx-modal__close" data-close-manual-assign aria-label="Close manual assignment dialog"><i class="fas fa-xmark" aria-hidden="true"></i></button>
    <h2 class="mtx-modal__title" id="manualAssignTitle"><i class="fas fa-user-cog" aria-hidden="true"></i> Assign technician</h2>
    <p class="mtx-modal__meta" id="manualAssignDescription">Choose a technician for this booking. The selected technician will be assigned directly.</p>

    <form method="post" id="manualAssignForm" class="mtx-assignment-form">
      <?= authContextField() ?>
      <input type="hidden" name="action" value="manual_assign">
      <input type="hidden" name="booking_id" id="manualAssignBookingId" value="">
      <label class="mtx-assignment-field" for="manualAssignTechnician">
        <span>Technician</span>
        <select name="technician_id" id="manualAssignTechnician" required>
          <option value="">Select a technician</option>
          <?php foreach ($technicians as $t): ?>
            <?php
              $availability = (string)($t['availability_status'] ?? 'off_duty');
              $offDuty = $availability === 'off_duty';
              $availabilityLabel = ucwords(str_replace('_', ' ', $availability));
            ?>
            <option value="<?= (int)$t['id'] ?>" <?= $offDuty ? 'disabled' : '' ?>><?= htmlspecialchars($t['name'] . ' — ' . $availabilityLabel . ($offDuty ? ' (unavailable)' : '')) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <p class="mtx-assignment-hint"><i class="fas fa-circle-info" aria-hidden="true"></i> Manual Assign preserves your selected technician. Off-duty technicians cannot be assigned.</p>
      <div class="mtx-assignment-actions">
        <button type="button" class="mtx-btn mtx-btn--ghost" data-close-manual-assign>Cancel</button>
        <button type="submit" class="mtx-btn mtx-btn--primary"><i class="fas fa-user-check" aria-hidden="true"></i> Assign Technician</button>
      </div>
    </form>
  </div>
</div>

<?php if ($preloadConfirmId > 0): ?>
<script>
  // Bring the dashboard-linked booking into view without choosing an assignment method.
  document.addEventListener('DOMContentLoaded', function () {
    var trigger = document.getElementById('manual-assign-trigger-<?= $preloadConfirmId ?>');
    if (trigger) {
      trigger.scrollIntoView({ behavior: 'smooth', block: 'center' });
      trigger.focus();
    }
  });
</script>
<?php endif; ?>

<?= authContextScriptTag() ?>
<script>
(function () {
  var modal = document.getElementById('manualAssignModal');
  var form = document.getElementById('manualAssignForm');
  var bookingId = document.getElementById('manualAssignBookingId');
  var description = document.getElementById('manualAssignDescription');
  var technician = document.getElementById('manualAssignTechnician');
  var activeTrigger = null;
  if (!modal || !form || !bookingId || !description || !technician) return;

  function openModal() {
    modal.hidden = false;
    modal.classList.remove('is-closing');
    modal.classList.add('is-opening');
    requestAnimationFrame(function () {
      modal.classList.remove('is-opening');
      modal.classList.add('is-open');
    });
  }
  function closeModal() {
    modal.classList.remove('is-opening', 'is-open');
    modal.classList.add('is-closing');
    document.body.classList.remove('mtx-modal-open');
    window.setTimeout(function () {
      if (!modal.classList.contains('is-closing')) return;
      modal.classList.remove('is-closing');
      modal.hidden = true;
      if (activeTrigger) activeTrigger.focus();
    }, 220);
  }

  document.querySelectorAll('[data-open-manual-assign]').forEach(function (trigger) {
    trigger.addEventListener('click', function () {
      activeTrigger = trigger;
      bookingId.value = trigger.dataset.bookingId || '';
      description.textContent = 'Choose a technician for ' + (trigger.dataset.bookingLabel || 'this booking') + '. The selected technician will be assigned directly.';
      technician.value = '';
      openModal();
      document.body.classList.add('mtx-modal-open');
      technician.focus();
    });
  });

  modal.querySelectorAll('[data-close-manual-assign]').forEach(function (control) {
    control.addEventListener('click', closeModal);
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && !modal.hidden) closeModal();
  });

  form.addEventListener('submit', function () {
    var submit = form.querySelector('button[type="submit"]');
    if (submit && form.checkValidity()) {
      submit.disabled = true;
      submit.setAttribute('aria-busy', 'true');
    }
  });
})();
</script>
<script>
(function () {
  var modal = document.getElementById('upcomingAssignmentModal');
  var form = document.getElementById('upcomingAssignmentForm');
  var action = document.getElementById('upcomingAssignmentAction');
  var bookingId = document.getElementById('upcomingAssignmentBookingId');
  var title = document.getElementById('upcomingAssignmentTitle');
  var description = document.getElementById('upcomingAssignmentDescription');
  var schedule = document.getElementById('upcomingAssignmentSchedule');
  var distance = document.getElementById('upcomingAssignmentDistance');
  var technicianField = document.getElementById('upcomingManualTechnicianField');
  var technician = document.getElementById('upcomingAssignmentTechnician');
  var submit = document.getElementById('upcomingAssignmentSubmit');
  var activeTrigger = null;
  var mode = 'assign';
  var technicianFieldHideTimer = null;
  if (!modal || !form || !action || !bookingId || !title || !description || !schedule || !distance || !technicianField || !technician || !submit) return;

  function distanceLabel(value) {
    var days = Number(value || 0);
    if (days === 1) return 'Tomorrow';
    return days + ' day' + (days === 1 ? '' : 's') + ' from now';
  }

  function updateMethod() {
    var selected = form.querySelector('input[name="assignment_method"]:checked');
    var method = selected ? selected.value : 'auto';
    var isManual = method === 'manual';
    window.clearTimeout(technicianFieldHideTimer);
    if (isManual) {
      technicianField.classList.add('is-motion-hidden');
      technicianField.hidden = false;
      requestAnimationFrame(function () { technicianField.classList.remove('is-motion-hidden'); });
    } else if (!technicianField.hidden) {
      technicianField.classList.add('is-motion-hidden');
      technicianFieldHideTimer = window.setTimeout(function () {
        if (!technicianField.classList.contains('is-motion-hidden')) return;
        technicianField.hidden = true;
        technicianField.classList.remove('is-motion-hidden');
      }, 120);
    }
    technician.required = isManual;
    if (!isManual) technician.value = '';
    form.querySelectorAll('.mtx-assignment-method').forEach(function (label) {
      label.classList.toggle('is-selected', label.querySelector('input').checked);
    });
    action.value = mode === 'reassign'
      ? (isManual ? 'upcoming_manual_reassign' : 'upcoming_auto_reassign')
      : (isManual ? 'upcoming_manual_assign' : 'upcoming_auto_assign');
  }

  function openModal() {
    modal.hidden = false;
    modal.classList.remove('is-closing');
    modal.classList.add('is-opening');
    requestAnimationFrame(function () {
      modal.classList.remove('is-opening');
      modal.classList.add('is-open');
    });
  }
  function closeModal() {
    modal.classList.remove('is-opening', 'is-open');
    modal.classList.add('is-closing');
    document.body.classList.remove('mtx-modal-open');
    window.setTimeout(function () {
      if (!modal.classList.contains('is-closing')) return;
      modal.classList.remove('is-closing');
      modal.hidden = true;
      if (activeTrigger) activeTrigger.focus();
    }, 220);
  }

  document.querySelectorAll('[data-open-upcoming-assignment]').forEach(function (trigger) {
    trigger.addEventListener('click', function () {
      activeTrigger = trigger;
      mode = trigger.dataset.assignmentMode === 'reassign' ? 'reassign' : 'assign';
      bookingId.value = trigger.dataset.bookingId || '';
      schedule.textContent = (trigger.dataset.scheduledDate || '') + ' · ' + (trigger.dataset.scheduledTime || '');
      distance.textContent = distanceLabel(trigger.dataset.scheduleOffset);
      title.innerHTML = '<i class="fas fa-' + (mode === 'reassign' ? 'user-pen' : 'calendar-plus') + '" aria-hidden="true"></i> ' + (mode === 'reassign' ? 'Change Upcoming Technician' : 'Assign Upcoming Appointment');
      description.textContent = mode === 'reassign'
        ? 'This appointment is currently assigned to ' + (trigger.dataset.currentTechnician || 'a technician') + '. Confirm any change deliberately.'
        : 'Choose how this future appointment should be assigned.';
      submit.innerHTML = '<i class="fas fa-user-check" aria-hidden="true"></i> ' + (mode === 'reassign' ? 'Confirm Change' : 'Confirm Assignment');
      var defaultMethod = mode === 'reassign' ? 'manual' : 'auto';
      form.querySelector('input[value="' + defaultMethod + '"]').checked = true;
      technician.value = '';
      updateMethod();
      openModal();
      document.body.classList.add('mtx-modal-open');
      (defaultMethod === 'manual' ? technician : form.querySelector('input[value="auto"]')).focus();
    });
  });

  form.querySelectorAll('input[name="assignment_method"]').forEach(function (radio) {
    radio.addEventListener('change', updateMethod);
  });
  modal.querySelectorAll('[data-close-upcoming-assignment]').forEach(function (control) { control.addEventListener('click', closeModal); });
  document.addEventListener('keydown', function (event) { if (event.key === 'Escape' && !modal.hidden) closeModal(); });
  form.addEventListener('submit', function () {
    if (form.checkValidity()) {
      submit.disabled = true;
      submit.setAttribute('aria-busy', 'true');
    }
  });
})();
</script>
<script>
(function () {
  var modal = document.getElementById('upcomingConfirmationModal');
  var form = document.getElementById('upcomingConfirmationForm');
  var bookingId = document.getElementById('upcomingConfirmationBookingId');
  var customer = document.getElementById('upcomingConfirmationCustomer');
  var date = document.getElementById('upcomingConfirmationDate');
  var time = document.getElementById('upcomingConfirmationTime');
  var deposit = document.getElementById('upcomingConfirmationDeposit');
  var submit = document.getElementById('upcomingConfirmationSubmit');
  var activeTrigger = null;
  if (!modal || !form || !bookingId || !customer || !date || !time || !deposit || !submit) return;

  function openModal() {
    modal.hidden = false;
    modal.classList.remove('is-closing');
    modal.classList.add('is-opening');
    document.body.classList.add('mtx-modal-open');
    requestAnimationFrame(function () {
      modal.classList.remove('is-opening');
      modal.classList.add('is-open');
      submit.focus();
    });
  }

  function closeModal() {
    if (modal.hidden || modal.classList.contains('is-closing')) return;
    modal.classList.remove('is-opening', 'is-open');
    modal.classList.add('is-closing');
    document.body.classList.remove('mtx-modal-open');
    window.setTimeout(function () {
      if (!modal.classList.contains('is-closing')) return;
      modal.classList.remove('is-closing');
      modal.hidden = true;
      if (activeTrigger) activeTrigger.focus();
    }, 170);
  }

  document.querySelectorAll('[data-open-upcoming-confirmation]').forEach(function (trigger) {
    trigger.addEventListener('click', function () {
      activeTrigger = trigger;
      bookingId.value = trigger.dataset.bookingId || '';
      customer.textContent = trigger.dataset.customerName || 'Customer';
      date.textContent = trigger.dataset.scheduledDate || 'Schedule to be confirmed';
      time.textContent = trigger.dataset.scheduledTime || '';
      deposit.innerHTML = '<i class="fas fa-circle-check" aria-hidden="true"></i> ' + (trigger.dataset.depositLabel || 'Paid');
      submit.disabled = false;
      submit.removeAttribute('aria-busy');
      submit.innerHTML = '<i class="fas fa-calendar-check" aria-hidden="true"></i> Confirm Appointment';
      openModal();
    });
  });
  modal.querySelectorAll('[data-close-upcoming-confirmation]').forEach(function (control) {
    control.addEventListener('click', closeModal);
  });
  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && !modal.hidden) closeModal();
  });
  form.addEventListener('submit', function () {
    if (!form.checkValidity() || submit.disabled) return;
    submit.disabled = true;
    submit.setAttribute('aria-busy', 'true');
    submit.innerHTML = '<i class="fas fa-spinner fa-spin" aria-hidden="true"></i> Confirming...';
  });
})();
</script>
</main></div></div></body></html>
