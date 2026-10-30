<?php
require __DIR__ . '/app/runtime_check.php';
$root = __DIR__;
$marker = $root . '/.installed';
$configDir = $root . '/config';
$configFile = $configDir . '/database.php';
$schemaFile = $root . '/database/schema.sql';
$seedFile = $root . '/database/seed.sql';

function h(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }

function split_sql(string $sql): array
{
    $statements = [];
    $buffer = '';
    $quote = null;
    $escaped = false;
    $lineComment = false;
    $blockComment = false;
    $len = strlen($sql);

    for ($i = 0; $i < $len; $i++) {
        $c = $sql[$i];
        $next = $i + 1 < $len ? $sql[$i + 1] : '';

        if ($lineComment) {
            if ($c === "\n") { $lineComment = false; $buffer .= $c; }
            continue;
        }
        if ($blockComment) {
            if ($c === '*' && $next === '/') { $blockComment = false; $i++; }
            continue;
        }
        if ($quote === null) {
            if (($c === '-' && $next === '-' && ($i + 2 >= $len || ctype_space($sql[$i + 2]))) || $c === '#') {
                $lineComment = true;
                if ($c === '-') { $i++; }
                continue;
            }
            if ($c === '/' && $next === '*') { $blockComment = true; $i++; continue; }
            if ($c === "'" || $c === '"' || $c === '`') { $quote = $c; $buffer .= $c; continue; }
            if ($c === ';') {
                $stmt = trim($buffer);
                if ($stmt !== '') $statements[] = $stmt;
                $buffer = '';
                continue;
            }
            $buffer .= $c;
            continue;
        }

        $buffer .= $c;
        if ($escaped) { $escaped = false; continue; }
        if ($c === '\\' && $quote !== '`') { $escaped = true; continue; }
        if ($c === $quote) {
            if ($next === $quote && $quote !== '`') { $buffer .= $next; $i++; continue; }
            $quote = null;
        }
    }

    $stmt = trim($buffer);
    if ($stmt !== '') $statements[] = $stmt;
    return $statements;
}

function run_sql_file(PDO $pdo, string $path): int
{
    if (!is_file($path)) throw new RuntimeException('Required installer file is missing: ' . basename($path));
    $sql = file_get_contents($path);
    if ($sql === false) throw new RuntimeException('Could not read ' . basename($path));
    $count = 0;
    foreach (split_sql($sql) as $statement) {
        $pdo->exec($statement);
        $count++;
    }
    return $count;
}

$alreadyInstalled = is_file($marker);
$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$alreadyInstalled) {
    $host = trim((string)($_POST['db_host'] ?? 'localhost'));
    $name = trim((string)($_POST['db_name'] ?? ''));
    $user = trim((string)($_POST['db_user'] ?? ''));
    $pass = (string)($_POST['db_password'] ?? '');
    $port = trim((string)($_POST['db_port'] ?? '3306'));

    try {
        if ($host === '' || $name === '' || $user === '') throw new RuntimeException('Database host, name, and username are required.');
        if (!preg_match('/^[A-Za-z0-9_.-]+$/', $host)) throw new RuntimeException('Database host contains unsupported characters.');
        if (!preg_match('/^[A-Za-z0-9_$-]+$/', $name)) throw new RuntimeException('Database name contains unsupported characters.');
        if (!preg_match('/^[A-Za-z0-9_$@.-]+$/', $user)) throw new RuntimeException('Database username contains unsupported characters.');
        if (!preg_match('/^\d+$/', $port) || (int)$port < 1 || (int)$port > 65535) throw new RuntimeException('Database port must be a number between 1 and 65535.');

        $dsn = 'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $name . ';charset=utf8mb4';
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->query('SELECT 1');

        $schemaCount = run_sql_file($pdo, $schemaFile);
        $seedCount = run_sql_file($pdo, $seedFile);

        if (!is_dir($configDir) && !mkdir($configDir, 0750, true) && !is_dir($configDir)) {
            throw new RuntimeException('Could not create the config directory.');
        }

        $config = "<?php\n\nreturn [\n"
            . "    'dsn' => " . var_export($dsn, true) . ",\n"
            . "    'username' => " . var_export($user, true) . ",\n"
            . "    'password' => " . var_export($pass, true) . ",\n"
            . "];\n";

        if (file_put_contents($configFile, $config, LOCK_EX) === false) throw new RuntimeException('Database tables were installed, but config/database.php could not be written.');
        @chmod($configFile, 0600);

        $markerBody = "Toinky installed at " . gmdate('c') . "\nDatabase: " . $name . "\n";
        if (file_put_contents($marker, $markerBody, LOCK_EX) === false) throw new RuntimeException('Installation completed, but the .installed lock file could not be created.');
        @chmod($marker, 0600);

        $success = 'Installation complete. Schema statements: ' . $schemaCount . '. Seed statements: ' . $seedCount . '.';
        $alreadyInstalled = true;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Toinky Installer</title>
<style>
body{font-family:Inter,system-ui,sans-serif;background:#f3f4f6;color:#111827;margin:0;padding:32px}.card{max-width:680px;margin:40px auto;background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:28px;box-shadow:0 8px 30px #0000000d}h1{margin-top:0}label{display:block;font-weight:650;margin:16px 0 6px}input{width:100%;box-sizing:border-box;padding:11px 12px;border:1px solid #cbd5e1;border-radius:9px;font:inherit}button,.button{display:inline-block;margin-top:20px;background:#111827;color:#fff;border:0;border-radius:9px;padding:11px 16px;font:inherit;text-decoration:none;cursor:pointer}.notice{padding:12px 14px;border-radius:10px;margin:16px 0}.error{background:#fee2e2;color:#991b1b}.success{background:#dcfce7;color:#166534}.muted{color:#64748b;font-size:14px;line-height:1.5}code{background:#f1f5f9;padding:2px 5px;border-radius:5px}
</style>
</head>
<body><main class="card">
<h1>Toinky Installer</h1>
<p class="muted">This installer connects to an existing MySQL database, creates the Toinky tables, loads the starter plot data, writes <code>config/database.php</code>, and locks itself after a successful installation.</p>
<?php if ($error): ?><div class="notice error"><?=h($error)?></div><?php endif; ?>
<?php if ($success): ?><div class="notice success"><?=h($success)?></div><?php endif; ?>
<?php if ($alreadyInstalled): ?>
<div class="notice success"><strong>Toinky is installed.</strong><br>The installer is locked by <code>.installed</code>.</div>
<a class="button" href="./">Open Toinky</a>
<p class="muted">To intentionally reinstall later, first remove the production <code>.installed</code> file manually. Do not remove it during normal updates.</p>
<?php else: ?>
<form method="post" autocomplete="off">
<label for="db_host">Database Host</label>
<input id="db_host" name="db_host" value="<?=h((string)($_POST['db_host'] ?? 'localhost'))?>" required>
<label for="db_name">Database Name</label>
<input id="db_name" name="db_name" value="<?=h((string)($_POST['db_name'] ?? 'sixpillarsadmin_game2'))?>" required>
<label for="db_user">Database Username</label>
<input id="db_user" name="db_user" value="<?=h((string)($_POST['db_user'] ?? 'sixpillarsadmin_game2'))?>" required>
<label for="db_password">Database Password</label>
<input id="db_password" name="db_password" type="password" required>
<button type="submit">Install Toinky</button>
</form>
<p class="muted">The database and MySQL user must already exist in cPanel, and the user must have privileges on that database.</p>
<?php endif; ?>
</main></body></html>
