<?php

/**
 * One-off recovery tool (v2): mine dangling git blobs for lost working-tree
 * versions of TRACKED files under admin/ whose uncommitted modifications were
 * discarded by a bad `git checkout --`.
 *
 * Untracked (new) files were never touched by that checkout — they are safe.
 *
 * Method: for every tracked admin/ file, find dangling blobs that are
 * related-to-but-different-from the HEAD version (line-set Jaccard), rank by
 * similarity (closest = likely latest iteration), and write candidates out.
 *
 * Usage: php scripts/recover_dangling_blobs.php <output-dir>
 */

if (PHP_SAPI !== 'cli') {
    exit("CLI only\n");
}

$outDir = $argv[1] ?? sys_get_temp_dir() . '/sbtech-recovery';
if (!is_dir($outDir) && !mkdir($outDir, 0777, true)) {
    exit("Cannot create output dir: $outDir\n");
}

function git(array $args): string
{
    $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $pipes = [];
    $process = proc_open(
        implode(' ', array_map('escapeshellarg', array_merge(['git'], $args))),
        $descriptors,
        $pipes
    );
    if (!is_resource($process)) {
        return '';
    }
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);

    return $stdout;
}

/** Line-set Jaccard similarity — cheap and good enough to spot "same file, edited". */
function lineJaccard(string $a, string $b): float
{
    $la = array_count_values(explode("\n", $a));
    $lb = array_count_values(explode("\n", $b));
    $common = 0;
    $totalA = array_sum($la);
    $totalB = array_sum($lb);
    foreach ($la as $line => $countA) {
        $countB = $lb[$line] ?? 0;
        $common += min($countA, $countB);
    }
    $union = max(1, $totalA + $totalB - $common);

    return $common / $union;
}

// ---- 1. Dangling blobs -------------------------------------------------------
preg_match_all('/^dangling blob ([0-9a-f]{40})$/m', git(['fsck', '--dangling']), $m);
$blobs = array_unique($m[1]);
echo "Dangling blobs: " . count($blobs) . "\n";

// Pre-load blob contents once.
$blobContents = [];
foreach ($blobs as $sha) {
    $c = git(['cat-file', '-p', $sha]);
    if (strlen($c) >= 200) {
        $blobContents[$sha] = $c;
    }
}
echo "Usable blobs (>=200 bytes): " . count($blobContents) . "\n";

// ---- 2. Targets: every tracked file under admin/ ------------------------------
$paths = array_filter(
    explode("\n", trim(git(['ls-tree', '-r', '--name-only', 'HEAD', 'admin/']))),
    static fn ($p) => $p !== ''
);
echo "Tracked admin/ files in HEAD: " . count($paths) . "\n";

// ---- 3. Mine candidates per target --------------------------------------------
$report = ["# Recovery candidates (v2, similarity-based)\n"];
$recoveredAny = false;

foreach ($paths as $path) {
    $head = git(['show', 'HEAD:' . $path]);
    $safe = str_replace('/', '__', $path);
    $cands = [];

    foreach ($blobContents as $sha => $content) {
        if ($content === $head) {
            continue; // identical = HEAD snapshot, not a lost edit
        }
        // Quick size gate before the O(n) similarity pass.
        if (strlen($content) < strlen($head) * 0.3 || strlen($content) > strlen($head) * 3) {
            continue;
        }
        $sim = lineJaccard($head, $content);
        if ($sim >= 0.6) {
            $cands[] = ['sha' => $sha, 'content' => $content, 'sim' => $sim];
        }
    }

    usort($cands, static fn ($a, $b) => $b['sim'] <=> $a['sim']);

    $report[] = "## $path (" . strlen($head) . " bytes in HEAD)";
    if (!$cands) {
        $report[] = '- no related-but-different blob found (edits may never have been staged)';
        $report[] = '';
        continue;
    }
    $recoveredAny = true;
    foreach ($cands as $i => $c) {
        file_put_contents(sprintf('%s/%s.cand%02d', $outDir, $safe, $i), $c['content']);
        $report[] = sprintf(
            '- candidate %02d: %s | similarity %.3f | %d bytes',
            $i,
            substr($c['sha'], 0, 12),
            $c['sim'],
            strlen($c['content'])
        );
    }
    $report[] = '';
}

file_put_contents("$outDir/REPORT.md", implode("\n", $report));
echo $recoveredAny
    ? "Candidates written to $outDir — see REPORT.md (highest similarity ≈ latest lost state)\n"
    : "No related-but-different blobs found for any tracked admin/ file.\n";
