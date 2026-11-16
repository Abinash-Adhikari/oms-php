<?php

/**
 * SB-Tech — image upload post-processing (GD).
 *
 * Used after storeUpload() for image fields (staff photo, office logo,
 * Website CMS images). The browser-side editor (assets/js/image-field.js)
 * already crops/rotates/resizes, but the server never trusts the client:
 * every stored image is re-encoded here.
 *
 * No globals, no session, no DB — unit-testable (tests/ImagesTest.php).
 */

/**
 * Re-encode an image file in place: verify it really is an image (by its
 * bytes, not its extension), apply JPEG EXIF orientation, shrink so neither
 * side exceeds $maxDim, and strip all metadata (EXIF/GPS) by re-saving.
 *
 * GIFs are verified but left untouched (re-encoding would drop animation).
 * PNG/WebP keep their alpha channel.
 *
 * @param string $path    Absolute path of the stored upload.
 * @param int    $maxDim  Longest allowed side in pixels (>= 16).
 * @param int    $quality JPEG/WebP quality 1-100.
 * @return array{ok: bool, message?: string, width?: int, height?: int, mime?: string}
 *         ok=false when the file is missing, is not a supported image, or GD
 *         fails to write it — callers should delete the file and reject.
 */
function optimize_image(string $path, int $maxDim = 2000, int $quality = 85): array
{
    if (!is_file($path)) {
        return ['ok' => false, 'message' => 'Uploaded image not found.'];
    }
    $info = @getimagesize($path);
    $types = [IMAGETYPE_JPEG => 'image/jpeg', IMAGETYPE_PNG => 'image/png', IMAGETYPE_GIF => 'image/gif', IMAGETYPE_WEBP => 'image/webp'];
    if ($info === false || !isset($types[$info[2]])) {
        return ['ok' => false, 'message' => 'The file is not a valid JPG, PNG, GIF or WebP image.'];
    }
    $type = (int) $info[2];
    $mime = $types[$type];
    [$w, $h] = [(int) $info[0], (int) $info[1]];
    if ($w < 1 || $h < 1 || $w * $h > 40_000_000) {
        return ['ok' => false, 'message' => 'Image dimensions are not supported (max 40 megapixels).'];
    }
    if ($type === IMAGETYPE_GIF) {
        return ['ok' => true, 'width' => $w, 'height' => $h, 'mime' => $mime];
    }

    // Remaining types: JPEG, PNG, WebP (GIF returned above).
    if ($type === IMAGETYPE_JPEG) {
        $src = @imagecreatefromjpeg($path);
    } elseif ($type === IMAGETYPE_PNG) {
        $src = @imagecreatefrompng($path);
    } else {
        $src = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false;
    }
    if (!$src instanceof GdImage) {
        return ['ok' => false, 'message' => 'The image could not be read.'];
    }

    // Camera photos store rotation in EXIF; bake it into the pixels before
    // the metadata is discarded.
    if ($type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $exif = @exif_read_data($path);
        $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;
        $angle = [3 => 180, 6 => -90, 8 => 90][$orientation] ?? 0;
        if ($angle !== 0) {
            $rotated = imagerotate($src, $angle, 0);
            if ($rotated instanceof GdImage) {
                $src = $rotated;
                $w = imagesx($src);
                $h = imagesy($src);
            }
        }
    }

    $maxDim = max(16, $maxDim);
    $scale = min(1, $maxDim / max($w, $h));
    $nw = max(1, (int) round($w * $scale));
    $nh = max(1, (int) round($h * $scale));

    $dst = imagecreatetruecolor($nw, $nh);
    if ($type !== IMAGETYPE_JPEG) {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
    }
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

    $quality = max(1, min(100, $quality));
    if ($type === IMAGETYPE_JPEG) {
        $saved = imagejpeg($dst, $path, $quality);
    } elseif ($type === IMAGETYPE_PNG) {
        $saved = imagepng($dst, $path, 6);
    } else {
        $saved = function_exists('imagewebp') && imagewebp($dst, $path, $quality);
    }
    if (!$saved) {
        return ['ok' => false, 'message' => 'The image could not be saved.'];
    }
    return ['ok' => true, 'width' => $nw, 'height' => $nh, 'mime' => $mime];
}

/**
 * Store an uploaded image: validate extension/size (validateUpload), move it
 * into user_uploads/<module>/ (storeUpload) and re-encode it (optimize_image).
 * On any failure nothing is left on disk.
 *
 * @param array  $file   One entry of $_FILES.
 * @param string $module Sub-folder under user_uploads/ (e.g. 'staff').
 * @param int    $maxDim Longest allowed side in pixels.
 * @param string[] $types Allowed extensions (e.g. ['jpg','jpeg','png'] for
 *        images that also go into PDFs, where WebP/GIF are unreliable).
 * @return array{ok: bool, path?: string, message?: string} path is relative
 *         to user_uploads/ (what the *_location / logo / photo columns hold).
 */
function store_image_upload(array $file, string $module, int $maxDim = 2000, array $types = ['jpg', 'jpeg', 'png', 'gif', 'webp']): array
{
    $check = validateUpload($file, $types);
    if (!$check['ok']) {
        return ['ok' => false, 'message' => $check['message']];
    }
    $stored = storeUpload($file, $module, $check['extension']);
    if ($stored === null) {
        return ['ok' => false, 'message' => 'The image could not be stored.'];
    }
    $abs = dirname(__DIR__) . '/user_uploads/' . $stored;
    $result = optimize_image($abs, $maxDim);
    if (!$result['ok']) {
        @unlink($abs);
        return ['ok' => false, 'message' => $result['message'] ?? 'Invalid image.'];
    }
    return ['ok' => true, 'path' => $stored];
}

/**
 * HTML for an image upload field with the in-browser editor (crop, aspect
 * presets, rotate, flip, zoom) — enhanced by assets/js/image-field.js, which
 * writes the edited image back into the plain <input type="file">, so the
 * form submits normally (no AJAX, PRG/CSRF unchanged). Without JS it is a
 * normal file input.
 *
 * @param string $name Form field name (the $_FILES key).
 * @param array{
 *     id?: string, current?: string, aspect?: float|null, max?: int,
 *     shape?: 'rect'|'circle', hint?: string, empty?: string, types?: string[]
 * } $opts current = URL of the saved image (preview), aspect = default crop
 *        ratio (null = free), max = longest output side in px, shape =
 *        preview mask (circle for avatars), types = allowed extensions
 *        (default jpg/jpeg/png/webp/gif; must match the server-side list).
 * @return string Escaped HTML.
 */
function image_field_html(string $name, array $opts = []): string
{
    $id = (string) ($opts['id'] ?? ('img_' . preg_replace('/[^a-z0-9_]/i', '_', $name)));
    $current = (string) ($opts['current'] ?? '');
    $aspect = $opts['aspect'] ?? null;
    $max = (int) ($opts['max'] ?? 1600);
    $shape = ($opts['shape'] ?? 'rect') === 'circle' ? 'circle' : 'rect';
    $hint = (string) ($opts['hint'] ?? 'JPG, PNG or WebP · up to 10 MB');
    $empty = (string) ($opts['empty'] ?? 'Drop an image here or click to browse');
    $mimeOf = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif'];
    $accept = implode(',', array_unique(array_values(array_intersect_key($mimeOf, array_flip($opts['types'] ?? array_keys($mimeOf))))));

    return '<div class="imgf imgf--' . $shape . ($current !== '' ? ' has-image' : '') . '" data-image-field'
        . ' data-aspect="' . e($aspect === null ? '' : (string) (float) $aspect) . '"'
        . ' data-max="' . $max . '">'
        . '<div class="imgf__drop" role="button" tabindex="0" aria-label="' . e($empty) . '">'
        . '<img class="imgf__preview" src="' . e($current) . '" alt=""' . ($current === '' ? ' hidden' : '') . '>'
        . '<div class="imgf__empty"><span class="imgf__empty-icon"><i class="fas fa-cloud-upload-alt" aria-hidden="true"></i></span>'
        . '<strong>' . e($empty) . '</strong><span>' . e($hint) . '</span></div>'
        . '<span class="imgf__badge" title="Edited — saved when you submit the form" hidden><i class="fas fa-magic mr-1" aria-hidden="true"></i>Edited · saves on submit</span>'
        . '</div>'
        . '<div class="imgf__bar">'
        . '<button type="button" class="btn btn-sm btn-light imgf__btn-edit"' . ($current === '' ? ' hidden' : '') . '><i class="fas fa-crop-alt mr-1" aria-hidden="true"></i>Edit</button>'
        . '<button type="button" class="btn btn-sm btn-light imgf__btn-pick"><i class="fas fa-upload mr-1" aria-hidden="true"></i><span>' . ($current === '' ? 'Upload' : 'Replace') . '</span></button>'
        . '<button type="button" class="btn btn-sm btn-light imgf__btn-undo" hidden><i class="fas fa-undo mr-1" aria-hidden="true"></i>Undo</button>'
        . '</div>'
        . '<input type="file" class="imgf__input" id="' . e($id) . '" name="' . e($name) . '" accept="' . e($accept) . '">'
        . '</div>';
}
