<?php

/**
 * SB-Tech — office service helpers (tasks, meetings/events, daily tasks).
 * Loaded by config/bootstrap.php after functions/helpers.php and hr.php.
 */

/** Can this user see all office tasks (author/assignee scope bypass)? */
function canSeeAllTasks(?array $user = null): bool
{
    return Auth::isSuperAdmin($user);
}

/**
 * Task list scoped per AC-TSK-03.1: non-admin users see only tasks they
 * authored or are assigned to; Super Admin sees everything. Returns
 * [sql_where, params].
 */
function taskScopeSql(int $userId, bool $seeAll): array
{
    if ($seeAll) {
        return ['1=1', []];
    }
    return [
        '(t.author = ? OR EXISTS (
            SELECT 1 FROM `tbl_office_task_assignees` ta
            WHERE ta.task_id = t.id AND ta.staff_id = ?
        ))',
        [$userId, $userId],
    ];
}

/** "Past Due" when deadline passed and the task isn't finished/cancelled. */
function isTaskPastDue(array $task): bool
{
    if (empty($task['deadline']) || $task['deadline'] === '0000-00-00 00:00:00') {
        return false;
    }
    if (in_array($task['status'], ['Done', 'Cancelled'], true)) {
        return false;
    }
    return strtotime((string) $task['deadline']) < time();
}

/** Task visibility badge state: new (same-day) / past-due / normal. */
function taskBadgeClasses(array $task): array
{
    $new = date('Y-m-d', strtotime((string) ($task['added_on'] ?? ''))) === date('Y-m-d');
    return [
        'new'      => $new,
        'past_due' => isTaskPastDue($task),
    ];
}

/**
 * Event visibility scope for the calendar/list (AC-MTG-02.2):
 * own events + Public-to-all + Public-to-my-department + Private where
 * invited. Super Admin sees all. Returns [sql_where, params].
 */
function eventVisibilitySql(int $userId, ?array $user, bool $seeAll): array
{
    if ($seeAll) {
        return ['1=1', []];
    }
    $deptId = (int) ($user['department_id'] ?? 0);
    return [
        "(e.added_by = ?
         OR (e.privacy = 'Public' AND e.attendees_department IS NULL)
         OR (e.privacy = 'Public' AND e.attendees_department = ?)
         OR (e.privacy = 'Private' AND FIND_IN_SET(?, e.attendees_staffs)))",
        [$userId, $deptId ?: -1, $userId],
    ];
}

/**
 * Collect staff ids who are already booked in any event schedule that
 * overlaps the given (date, from, to) slots (AC-MTG-01.2 free-staff picker).
 */
function bookedStaffIdsAtSlots(array $slots, int $ignoreEventId = 0): array
{
    $db = Database::instance();
    $booked = [];
    foreach ($slots as $slot) {
        if (empty($slot['date'])) {
            continue;
        }
        $rows = $db->select(
            'SELECT e.attendees_staffs FROM `tbl_office_event_schedules` s
             JOIN `tbl_office_events` e ON e.id = s.event_id
             WHERE s.date = ? AND e.id != ?
               AND s.from_time IS NOT NULL AND s.to_time IS NOT NULL
               AND s.from_time < ? AND s.to_time > ?',
            [
                $slot['date'],
                $ignoreEventId,
                $slot['to'] ?: '23:59:59',
                $slot['from'] ?: '00:00:00',
            ]
        );
        foreach ($rows as $r) {
            foreach (explode(',', (string) $r['attendees_staffs']) as $id) {
                if (ctype_digit(trim($id))) {
                    $booked[(int) $id] = true;
                }
            }
        }
    }
    return array_keys($booked);
}

/** Human-readable schedule line for an event schedule row. */
function scheduleLine(array $schedule): string
{
    $line = formatDateView($schedule['date'] ?? '');
    if (!empty($schedule['from_time'])) {
        $line .= ' ' . date('g:i A', strtotime($schedule['from_time']));
        if (!empty($schedule['to_time'])) {
            $line .= ' – ' . date('g:i A', strtotime($schedule['to_time']));
        }
    }
    return $line;
}
