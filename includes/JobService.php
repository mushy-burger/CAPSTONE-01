<?php
/**
 * Technician service execution — start / complete transitions.
 *
 * All authorisation, state and timestamp rules live here so the same guarantees
 * apply no matter which page calls them:
 *
 *  - The technician must own the job (checked in SQL, not from a form field).
 *  - Only confirmed -> in_progress -> completed is permitted.
 *  - An estimated duration is required before a job can start.
 *  - Timestamps come from the DATABASE clock (NOW()), never from the request
 *    and never from PHP's clock, which is a different timezone on this server.
 *  - Transitions are conditional UPDATEs, so a double submit updates 0 rows and
 *    is reported as "already done" instead of running twice.
 *
 * Actual duration is always derived from the two timestamps — it is never
 * stored or accepted as input, so it cannot be manipulated.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

/** Bounds for a technician's estimate, in minutes. */
const JOB_MIN_DURATION = 1;
const JOB_MAX_DURATION = 1440; // 24 hours

/**
 * Load a job only if it belongs to this technician.
 *
 * Ownership is part of the WHERE clause, so a crafted booking id simply
 * returns nothing.
 */
function jobFetchForTechnician(int $bookingId, int $technicianId): ?array {
    if ($bookingId <= 0 || $technicianId <= 0) {
        return null;
    }
    return fetchOne(
        "SELECT * FROM bookings WHERE id = ? AND technician_id = ?",
        [$bookingId, $technicianId]
    );
}

/**
 * Validate an hours/minutes pair into total minutes.
 *
 * @return array{ok:bool,minutes:int,error:string}
 */
function jobParseDuration(string $hoursRaw, string $minutesRaw): array {
    $hoursRaw   = trim($hoursRaw);
    $minutesRaw = trim($minutesRaw);

    if ($hoursRaw === '' && $minutesRaw === '') {
        return ['ok' => false, 'minutes' => 0, 'error' => 'Enter an estimated service duration before starting the job.'];
    }
    if (($hoursRaw !== '' && !ctype_digit($hoursRaw)) || ($minutesRaw !== '' && !ctype_digit($minutesRaw))) {
        return ['ok' => false, 'minutes' => 0, 'error' => 'Estimated duration must use whole positive numbers.'];
    }

    $total = ((int)$hoursRaw * 60) + (int)$minutesRaw;

    if ($total < JOB_MIN_DURATION) {
        return ['ok' => false, 'minutes' => 0, 'error' => 'Please enter an estimated duration of at least 1 minute.'];
    }
    if ($total > JOB_MAX_DURATION) {
        return ['ok' => false, 'minutes' => 0, 'error' => 'Estimated duration cannot exceed 24 hours.'];
    }

    return ['ok' => true, 'minutes' => $total, 'error' => ''];
}

/** Save/update the estimate while the job is confirmed or in progress. */
function jobSaveEstimate(int $bookingId, int $technicianId, int $minutes): array {
    $job = jobFetchForTechnician($bookingId, $technicianId);
    if (!$job) {
        return ['ok' => false, 'error' => 'Job not found or not assigned to you.'];
    }
    if (!in_array($job['status'], ['confirmed', 'in_progress'], true)) {
        return ['ok' => false, 'error' => 'The estimated duration can only be set while the job is active.'];
    }
    if ($minutes < JOB_MIN_DURATION || $minutes > JOB_MAX_DURATION) {
        return ['ok' => false, 'error' => 'Estimated duration is out of range.'];
    }

    getDB()->prepare(
        "UPDATE bookings SET estimated_duration_minutes = ? WHERE id = ? AND technician_id = ?"
    )->execute([$minutes, $bookingId, $technicianId]);

    return ['ok' => true, 'error' => '', 'minutes' => $minutes];
}

/**
 * START JOB: confirmed -> in_progress.
 *
 * Requires an estimate (either already saved or supplied with this request).
 * `actual_start_time` is set from NOW() — the technician cannot supply it.
 *
 * @return array{ok:bool,error:string,started_at:?string}
 */
function jobStart(int $bookingId, int $technicianId, ?int $estimateMinutes = null): array {
    $job = jobFetchForTechnician($bookingId, $technicianId);
    if (!$job) {
        return ['ok' => false, 'error' => 'Job not found or not assigned to you.', 'started_at' => null];
    }

    if ($job['status'] === 'in_progress') {
        return ['ok' => false, 'error' => 'This job has already been started.', 'started_at' => $job['actual_start_time']];
    }
    if ($job['status'] !== 'confirmed') {
        return ['ok' => false, 'error' => 'Only a confirmed job can be started.', 'started_at' => null];
    }

    // An estimate must exist before work begins.
    $minutes = $estimateMinutes ?? ($job['estimated_duration_minutes'] !== null ? (int)$job['estimated_duration_minutes'] : null);
    if ($minutes === null || $minutes < JOB_MIN_DURATION || $minutes > JOB_MAX_DURATION) {
        return ['ok' => false, 'error' => 'Enter an estimated service duration before starting the job.', 'started_at' => null];
    }

    // Conditional update: only moves a job that is still 'confirmed', so two
    // simultaneous submits cannot both start it.
    $stmt = getDB()->prepare(
        "UPDATE bookings
            SET status = 'in_progress',
                estimated_duration_minutes = ?,
                actual_start_time = COALESCE(actual_start_time, NOW())
          WHERE id = ? AND technician_id = ? AND status = 'confirmed'"
    );
    $stmt->execute([$minutes, $bookingId, $technicianId]);

    if ($stmt->rowCount() !== 1) {
        return ['ok' => false, 'error' => 'This job could not be started (it may have already been started).', 'started_at' => null];
    }

    $fresh = jobFetchForTechnician($bookingId, $technicianId);
    return ['ok' => true, 'error' => '', 'started_at' => $fresh['actual_start_time'] ?? null];
}

/**
 * COMPLETE JOB: in_progress -> completed.
 *
 * `completed_at` is set from NOW(). Returns ok=false for a repeat attempt so
 * the caller knows not to send a second notification.
 *
 * @return array{ok:bool,error:string,already:bool}
 */
function jobComplete(int $bookingId, int $technicianId): array {
    $job = jobFetchForTechnician($bookingId, $technicianId);
    if (!$job) {
        return ['ok' => false, 'error' => 'Job not found or not assigned to you.', 'already' => false];
    }

    if ($job['status'] === 'completed') {
        return ['ok' => false, 'error' => 'This job has already been completed.', 'already' => true];
    }
    if ($job['status'] !== 'in_progress') {
        return ['ok' => false, 'error' => 'Only a job that is in progress can be completed.', 'already' => false];
    }

    $stmt = getDB()->prepare(
        "UPDATE bookings
            SET status = 'completed',
                completed_at = COALESCE(completed_at, NOW())
          WHERE id = ? AND technician_id = ? AND status = 'in_progress'"
    );
    $stmt->execute([$bookingId, $technicianId]);

    if ($stmt->rowCount() !== 1) {
        // Another request won the race and completed it first.
        return ['ok' => false, 'error' => 'This job has already been completed.', 'already' => true];
    }

    return ['ok' => true, 'error' => '', 'already' => false];
}

/**
 * Estimated finish = actual start + estimate.
 *
 * Derived on read rather than stored: storing it would duplicate data that can
 * be computed exactly, and would go stale if the estimate were revised.
 */
function jobEstimatedFinish(?string $actualStart, ?int $estimateMinutes): ?string {
    if (!$actualStart || !$estimateMinutes) {
        return null;
    }
    try {
        $dt = new DateTime($actualStart, new DateTimeZone('Asia/Manila'));
        $dt->modify('+' . (int)$estimateMinutes . ' minutes');
        return $dt->format('Y-m-d H:i:s');
    } catch (Throwable $e) {
        return null;
    }
}

/** Actual duration in minutes, always derived from the two timestamps. */
function jobActualMinutes(?string $actualStart, ?string $completedAt): ?int {
    if (!$actualStart || !$completedAt) {
        return null;
    }
    try {
        $a = new DateTime($actualStart, new DateTimeZone('Asia/Manila'));
        $b = new DateTime($completedAt, new DateTimeZone('Asia/Manila'));
        return max(0, (int)round(($b->getTimestamp() - $a->getTimestamp()) / 60));
    } catch (Throwable $e) {
        return null;
    }
}
