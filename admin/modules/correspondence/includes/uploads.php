<?php
/**
 * SB-Tech — Correspondence attachments (P2), shared by both operation
 * handlers. Mirrors the expense-claim-files pattern: validate every file
 * up-front, then store under user_uploads/correspondence/ and record the
 * row in tbl_correspondence_files inside the caller's transaction.
 */

/**
 * Validate + store any files in $_FILES['files'] (multi-input) for an entry.
 * Call validate first (correspondence_validate_uploads) before writing the
 * entry, then store after the entry row exists.
 *
 * @return string|null an error message, or null on success / no files.
 */
function correspondence_store_uploads(Database $db, string $register, int $entryId, ?int $userId): ?string
{
    if (empty($_FILES['files']['name']) || !is_array($_FILES['files']['name'])) {
        return null;
    }
    foreach ($_FILES['files']['name'] as $i => $name) {
        if ($name === '') {
            continue;
        }
        $file = [
            'name'     => $_FILES['files']['name'][$i] ?? '',
            'type'     => $_FILES['files']['type'][$i] ?? '',
            'tmp_name' => $_FILES['files']['tmp_name'][$i] ?? '',
            'error'    => $_FILES['files']['error'][$i] ?? UPLOAD_ERR_NO_FILE,
            'size'     => $_FILES['files']['size'][$i] ?? 0,
        ];
        $up = validateUpload($file, ['jpg', 'jpeg', 'png', 'pdf']);
        if (!$up['ok']) {
            return $up['message'];
        }
        $loc = storeUpload($file, 'correspondence', $up['extension']);
        if ($loc === null) {
            return 'Could not store the uploaded file ' . basename((string) $name) . '.';
        }
        $db->insert('tbl_correspondence_files', [
            'register'       => $register,
            'entry_id'       => $entryId,
            'file_location'  => $loc,
            'file_name'      => basename((string) $name),
            'file_extension' => $up['extension'],
            'file_size'      => (int) $file['size'],
            'added_by'       => $userId,
        ]);
    }

    return null;
}

/**
 * Validate every uploaded file before the entry is written, so a bad
 * extension/size never allocates a number or half-saves an entry.
 *
 * @return string|null an error message, or null when valid / no files.
 */
function correspondence_validate_uploads(): ?string
{
    if (empty($_FILES['files']['name']) || !is_array($_FILES['files']['name'])) {
        return null;
    }
    foreach ($_FILES['files']['name'] as $i => $name) {
        if ($name === '') {
            continue;
        }
        $file = [
            'name'     => $_FILES['files']['name'][$i] ?? '',
            'type'     => $_FILES['files']['type'][$i] ?? '',
            'tmp_name' => $_FILES['files']['tmp_name'][$i] ?? '',
            'error'    => $_FILES['files']['error'][$i] ?? UPLOAD_ERR_NO_FILE,
            'size'     => $_FILES['files']['size'][$i] ?? 0,
        ];
        $up = validateUpload($file, ['jpg', 'jpeg', 'png', 'pdf']);
        if (!$up['ok']) {
            return $up['message'];
        }
    }

    return null;
}
