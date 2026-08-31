<?php
/**
 * SB-Tech — My Office / Meetings operations (US-MTG-01).
 *   save_event   — create/update event with schedules, privacy, venue,
 *                  attendees; private meetings reject invited staff who are
 *                  already booked at the same slot (AC-MTG-01.2)
 *   delete_event — 7-day window; private events only by creator or Super
 *                  Admin (AC-MTG-01.3)
 */
$db = Database::instance();
$me = (int) Auth::id();
$seeAll = Auth::isSuperAdmin();
$action = (string) ($_POST['action'] ?? '');
$back = 'show_page.php?module=staff_management&page=hr_care&tab=meetings';

try {
    if ($action === 'save_event') {
        $eventId = (int) ($_POST['event_id'] ?? 0);
        $title = trim((string) ($_POST['title'] ?? ''));
        $type = (string) ($_POST['type'] ?? 'Meeting');
        $privacy = (string) ($_POST['privacy'] ?? 'Public');
        $remarks = trim((string) ($_POST['remarks'] ?? ''));
        $otherAttendees = trim((string) ($_POST['other_attendees'] ?? ''));
        $venueType = (string) ($_POST['venue_type'] ?? 'In Office');
        $deptId = (int) ($_POST['attendees_department'] ?? 0);

        if (!in_array($type, ['Meeting', 'Event'], true)) {
            $type = 'Meeting';
        }
        if ($type === 'Event') {
            $privacy = 'Public'; // events are public by design
        }
        if (!in_array($privacy, ['Public', 'Private'], true)) {
            $privacy = 'Public';
        }

        $attendeeIds = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['attendees'] ?? [])))));
        if ($privacy === 'Private' && count($attendeeIds) === 0) {
            setFlash('error', 'Private meetings require at least one invited staff member.');
            redirect($back);
        }

        // Schedules
        $dates = (array) ($_POST['date'] ?? []);
        $fromTimes = (array) ($_POST['from_time'] ?? []);
        $toTimes = (array) ($_POST['to_time'] ?? []);
        $slots = [];
        foreach ($dates as $i => $d) {
            $d = trim((string) $d);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
                continue;
            }
            $from = trim((string) ($fromTimes[$i] ?? ''));
            $to = trim((string) ($toTimes[$i] ?? ''));
            if ($from !== '' && !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $from)) {
                $from = '';
            }
            if ($to !== '' && !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $to)) {
                $to = '';
            }
            if ($from !== '' && $to !== '' && $to <= $from) {
                setFlash('error', 'Schedule end time must be after start time.');
                redirect($back);
            }
            $slots[] = ['date' => $d, 'from' => $from ?: null, 'to' => $to ?: null];
        }
        if (count($slots) === 0) {
            setFlash('error', 'Add at least one valid schedule date.');
            redirect($back);
        }
        if ($title === '') {
            setFlash('error', 'Title is required.');
            redirect($back);
        }

        // Free-staff conflict check (AC-MTG-01.2): invited staff already
        // booked at an overlapping slot cannot be added.
        if ($privacy === 'Private') {
            $booked = bookedStaffIdsAtSlots($slots, $eventId);
            $conflicts = array_values(array_intersect($attendeeIds, $booked));
            if ($conflicts) {
                $names = [];
                foreach ($conflicts as $cid) {
                    $u = $db->selectOne('SELECT `fullname` FROM `tbl_users_login` WHERE `id` = ?', [$cid]);
                    $names[] = $u['fullname'] ?? ('#' . $cid);
                }
                setFlash('error', 'Cannot invite: already booked at the same slot — ' . implode(', ', $names) . '.');
                redirect($back);
            }
        }

        if ($venueType === 'Out of Office') {
            $venueLocation = trim((string) ($_POST['venue_location_text'] ?? ''));
        } else {
            $venueLocation = trim((string) ($_POST['venue_location'] ?? ''));
        }

        $data = [
            'title'               => $title,
            'type'                => $type,
            'privacy'             => $privacy,
            'attendees_staffs'    => $privacy === 'Private' ? implode(',', $attendeeIds) : null,
            'attendees_department'=> $privacy === 'Public' && $deptId ? $deptId : null,
            'other_attendees'     => $otherAttendees ?: null,
            'venue_type'          => $venueType,
            'venue_location'      => $venueLocation ?: null,
            'remarks'             => $remarks ?: null,
            'updated_by'          => $me,
        ];

        if ($eventId) {
            $existing = $db->selectOne('SELECT * FROM `tbl_office_events` WHERE `id` = ?', [$eventId]);
            if (!$existing) {
                setFlash('error', 'Event not found.');
                redirect($back);
            }
            $isCreator = (int) $existing['added_by'] === $me;
            if (!($seeAll || $isCreator) || time() > strtotime((string) $existing['added_on']) + 7 * 86400) {
                setFlash('error', 'Events can only be edited within 7 days of creation by the creator or an admin.');
                redirect($back);
            }
            if ($existing['privacy'] === 'Private' && !$seeAll && !$isCreator) {
                setFlash('error', 'Private events can only be edited by the creator or a Super Admin.');
                redirect($back);
            }
            $db->update('tbl_office_events', $data, '`id` = ?', [$eventId]);
            $db->delete('tbl_office_event_schedules', '`event_id` = ?', [$eventId]);
            $scheduleIds = [];
            foreach ($slots as $slot) {
                $sid = $db->insert('tbl_office_event_schedules', [
                    'event_id' => $eventId,
                    'date' => $slot['date'],
                    'from_time' => $slot['from'],
                    'to_time' => $slot['to'],
                    'this_event' => $title,
                    'added_by' => $me,
                ]);
                $scheduleIds[] = $sid;
            }
            $db->update('tbl_office_events', ['schedules' => implode(',', $scheduleIds), 'updated_by' => $me], '`id` = ?', [$eventId]);
            setFlash('success', 'Event updated.');
        } else {
            $eventId = $db->insert('tbl_office_events', array_merge($data, [
                'added_by' => $me,
                'schedules' => '',
            ]));
            $scheduleIds = [];
            foreach ($slots as $slot) {
                $sid = $db->insert('tbl_office_event_schedules', [
                    'event_id' => $eventId,
                    'date' => $slot['date'],
                    'from_time' => $slot['from'],
                    'to_time' => $slot['to'],
                    'this_event' => $title,
                    'added_by' => $me,
                ]);
                $scheduleIds[] = $sid;
            }
            $db->update('tbl_office_events', ['schedules' => implode(',', $scheduleIds)], '`id` = ?', [$eventId]);
            if ($privacy === 'Private') {
                foreach ($attendeeIds as $aid) {
                    notifyUser($aid, 'You were invited to "' . e($title) . '" (' . e(scheduleLine($slots[0])) . ').', 'Meeting', (string) $eventId, $me);
                }
            }
            setFlash('success', $type . ' scheduled.');
        }
        redirect($back);
    }

    if ($action === 'delete_event') {
        $eventId = (int) ($_POST['event_id'] ?? 0);
        $existing = $db->selectOne('SELECT * FROM `tbl_office_events` WHERE `id` = ?', [$eventId]);
        if (!$existing) {
            setFlash('error', 'Event not found.');
        } else {
            $isCreator = (int) $existing['added_by'] === $me;
            if (!($seeAll || $isCreator) || time() > strtotime((string) $existing['added_on']) + 7 * 86400) {
                setFlash('error', 'Events can only be deleted within 7 days of creation by the creator or an admin.');
            } elseif ($existing['privacy'] === 'Private' && !$seeAll && !$isCreator) {
                setFlash('error', 'Private events can only be deleted by the creator or a Super Admin.');
            } else {
                $db->delete('tbl_office_event_schedules', '`event_id` = ?', [$eventId]);
                $db->delete('tbl_office_events', '`id` = ?', [$eventId]);
                setFlash('success', 'Event deleted.');
            }
        }
        redirect($back);
    }

    setFlash('error', 'Unknown event action.');
    redirect($back);
} catch (Throwable $e) {
    setFlash('error', 'Event operation failed: ' . $e->getMessage());
    redirect($back);
}
