<?php
declare(strict_types=1);
// Deploy manifest (audit task 13, 2026-10-26). The deploy copies files but
// never deletes, so anything removed from the repo lingers on the live server
// until someone hand-writes an rm line. This script is the general mechanism
// that replaces those hand-written lines: it builds the expected file list +
// SHA-256 hashes from the repo, verifies the deployed copies match, and (with
// --apply) deletes deployed files that are absent from the manifest.
//
// Run from the repo, after the copy steps, before migrations:
//   php tools/deploy_manifest.php $DEPLOYPATH [--apply]
// Verify-only by default (reports mismatches and stale files, deletes
// nothing). With --apply it deletes stale files. Server-owned paths are
// NEVER deleted (see DEPLOY_ALLOWLIST). Exit 0 when everything verified;
// exit 1 on a hash mismatch, a missing expected file, or an error.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// Server-owned paths that must NEVER be deleted, even if absent from the
// manifest. config/database.php is the live DB config (not in the repo);
// .installed is the installer marker; public/uploads/ is user content.
const DEPLOY_ALLOWLIST = [
    'config/database.php',
    '.installed',
    'public/uploads/',
];

function deployAllowlisted(string $rel): bool
{
    foreach (DEPLOY_ALLOWLIST as $allowed) {
        if (str_ends_with($allowed, '/')) {
            if (str_starts_with($rel, $allowed)) return true;
        } elseif ($rel === $allowed) {
            return true;
        }
    }
    return false;
}

// Expected deployed files: deployed relative path => SHA-256 of the repo file.
// Root *.php wrappers deploy to the root; public/assets/** deploys to
// assets/** (the live asset tree); everything else deploys to the same path.
// .htaccess is excluded: deploy_htaccess.php merges cPanel blocks into it, so
// its live content differs from the repo template by design.
function buildManifest(string $repoRoot): array
{
    $manifest = [];
    $add = function (string $repoPath, string $deployRel) use (&$manifest, $repoRoot) {
        $full = $repoRoot . '/' . $repoPath;
        if (!is_file($full)) return;
        $hash = hash_file('sha256', $full);
        if ($hash === false) throw new RuntimeException('Could not hash ' . $repoPath);
        $manifest[$deployRel] = $hash;
    };
    foreach (glob($repoRoot . '/*.php') as $file) {
        $add(basename($file), basename($file));
    }
    $walk = function (string $dir, callable $map) use (&$walk, $add) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if (!$file->isFile()) continue;
            $rel = substr($file->getPathname(), strlen($dir) + 1);
            $map($rel, $file->getPathname());
        }
    };
    $walk($repoRoot . '/app', function (string $rel) use ($add) { $add('app/' . $rel, 'app/' . $rel); });
    $walk($repoRoot . '/public', function (string $rel, string $full) use ($add) {
        if (str_starts_with($rel, 'assets/')) {
            $add('public/' . $rel, 'assets/' . substr($rel, strlen('assets/')));
        } else {
            $add('public/' . $rel, 'public/' . $rel);
        }
    });
    $walk($repoRoot . '/database', function (string $rel) use ($add) { $add('database/' . $rel, 'database/' . $rel); });
    $walk($repoRoot . '/tools', function (string $rel) use ($add) { $add('tools/' . $rel, 'tools/' . $rel); });
    $add('config/database.example.php', 'config/database.example.php');
    return $manifest;
}

// Deployed trees scanned for stale files (files present on the server but
// absent from the manifest). config/ is deliberately NOT scanned: it is
// server-owned except database.example.php, which is verified above.
function staleCandidates(string $deployRoot): array
{
    $found = [];
    $roots = [$deployRoot . '/app', $deployRoot . '/public', $deployRoot . '/assets', $deployRoot . '/database', $deployRoot . '/tools'];
    foreach ($roots as $dir) {
        if (!is_dir($dir)) continue;
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if (!$file->isFile()) continue;
            $found[] = substr($file->getPathname(), strlen($deployRoot) + 1);
        }
    }
    foreach (glob($deployRoot . '/*.php') as $file) {
        $found[] = basename($file);
    }
    return $found;
}

function runManifest(string $repoRoot, string $deployRoot, bool $apply): array
{
    $deployRoot = rtrim($deployRoot, '/');
    if (!is_dir($deployRoot)) throw new RuntimeException('Deployment root does not exist: ' . $deployRoot);
    $manifest = buildManifest($repoRoot);
    $missing = [];
    $mismatched = [];
    foreach ($manifest as $rel => $hash) {
        $live = $deployRoot . '/' . $rel;
        if (!is_file($live)) { $missing[] = $rel; continue; }
        if (hash_file('sha256', $live) !== $hash) $mismatched[] = $rel;
    }
    $stale = [];
    foreach (staleCandidates($deployRoot) as $rel) {
        if (!isset($manifest[$rel]) && !deployAllowlisted($rel)) $stale[] = $rel;
    }
    $deleted = [];
    if ($apply) {
        foreach ($stale as $rel) {
            $live = $deployRoot . '/' . $rel;
            if (is_link($live)) continue;
            if (!is_file($live)) continue;
            if (!unlink($live)) throw new RuntimeException('Could not delete stale file: ' . $rel);
            $deleted[] = $rel;
        }
    }
    return ['expected' => count($manifest), 'missing' => $missing, 'mismatched' => $mismatched, 'stale' => $stale, 'deleted' => $deleted];
}

$deployRoot = $argv[1] ?? '';
$apply = in_array('--apply', array_slice($argv, 2), true);
if ($deployRoot === '') {
    fwrite(STDERR, "Usage: php tools/deploy_manifest.php <deploy-path> [--apply]\n");
    exit(2);
}
try {
    $result = runManifest(dirname(__DIR__), $deployRoot, $apply);
    echo 'Manifest: ' . $result['expected'] . ' expected file(s).' . PHP_EOL;
    foreach ($result['missing'] as $rel) echo 'MISSING: ' . $rel . PHP_EOL;
    foreach ($result['mismatched'] as $rel) echo 'MISMATCH: ' . $rel . PHP_EOL;
    foreach ($result['stale'] as $rel) echo ($apply ? 'DELETED: ' : 'STALE: ') . $rel . PHP_EOL;
    if ($result['missing'] || $result['mismatched']) {
        fwrite(STDERR, 'Deploy manifest verification failed.' . PHP_EOL);
        exit(1);
    }
    echo 'Deploy manifest verified.' . PHP_EOL;
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Deploy manifest failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
