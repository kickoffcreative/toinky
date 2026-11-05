<?php
declare(strict_types=1);
// Guard test: the admin View-as-Player / View-as-Writer preview switch.
// An admin previews the site as a Player or a Writer from a button only
// admins see. The switch is presentation only: a session flag changes
// what menus and data the templates show, while every authorization
// check keeps reading the real user row, so a preview can neither grant
// nor remove access. Static checks only; no database needed.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

$root = dirname(__DIR__);
$auth = (string)file_get_contents($root . '/app/AuthService.php');
$index = (string)file_get_contents($root . '/public/index.php');
$header = (string)file_get_contents($root . '/public/_signed_header.php');
$sidebar = (string)file_get_contents($root . '/public/_workspace_sidebar.php');
$profile = (string)file_get_contents($root . '/public/profile.php');
$workflow = (string)file_get_contents($root . '/.github/workflows/php-validation.yml');
$failures = [];
$check = function (bool $ok, string $label) use (&$failures) {
    echo ($ok ? 'PASS' : 'FAIL') . ' ' . $label . "\n";
    if (!$ok) $failures[] = $label;
};

/* AuthService helpers exist and validate the mode strictly. */
$check(str_contains($auth, 'function viewAsMode()'), 'AuthService defines viewAsMode');
$check(str_contains($auth, 'function setViewAs(string $mode)'), 'AuthService defines setViewAs');
$check(str_contains($auth, "in_array(\$mode,['PLAYER','WRITER'],true)"), 'setViewAs accepts only PLAYER or WRITER, anything else clears');
$check(str_contains($auth, 'function displayIsAdmin(array $user)'), 'AuthService defines displayIsAdmin');
$check(str_contains($auth, 'function displayIsWriter(array $user)'), 'AuthService defines displayIsWriter');
$check(str_contains($auth, 'function displayIsSuperAdmin(array $user)'), 'AuthService defines displayIsSuperAdmin');
$check(str_contains($auth, "self::viewAsMode()===null") && str_contains($auth, "self::viewAsMode()!=='PLAYER'"), 'Display helpers derive from the view-as mode, not the real role alone');

/* Authorization still reads the real role: no view-as reference inside
   the enforcing checks. */
$requireAdmin = substr($auth, (int)strpos($auth, 'function requireAdmin'), 400);
$requireWriter = substr($auth, (int)strpos($auth, 'function requireWriter'), 500);
$check(!str_contains($requireAdmin, 'viewAs') && !str_contains($requireWriter, 'viewAs'), 'requireAdmin and requireWriter never consult the view-as flag');
$deactivate = substr($auth, (int)strpos($auth, 'function deactivateAccount'), 800);
$check(str_contains($deactivate, "is_super_admin") && str_contains($deactivate, 'cannot be deactivated'), 'deactivateAccount still blocks super admin accounts from the database row');

/* Dashboard: the switch action is admin-gated, CSRF-checked by the
   surrounding dispatch, and returns to the dashboard. */
$check(str_contains($index, "if(\$action==='view_as'){\$admin=AuthService::requireAdmin();AuthService::setViewAs((string)(\$_POST['mode']??''));header('Location: ./');exit;}"), 'Dashboard view_as action requires a real admin and redirects home');
$check(str_contains($index, 'if(!AuthService::displayIsAdmin($user))$durationOptions=array_values'), 'Dashboard duration list uses the display role, so previewing as Player hides admin-only durations');
$check(str_contains($index, 'if(AuthService::displayIsSuperAdmin($user)): ?><p class="muted"><strong>Super Admin</strong>'), 'Dashboard Super Admin host line uses the display role');
$check(str_contains($index, 'if(!AuthService::displayIsAdmin($user)&&!AuthService::displayIsWriter($user)):?>'), 'Dashboard Write Stories card shows only when the display role is a plain player');

/* Public header: nav links use the display role; the control is
   admin-only and carries both buttons plus an exit. */
$check(str_contains($header, 'if(AuthService::displayIsAdmin($user)):?><a href="admin.php">Admin</a>'), 'Public header Admin link uses the display role');
$check(str_contains($header, 'if(AuthService::displayIsWriter($user)):?><a href="writer.php">Story Writer</a>'), 'Public header Story Writer link uses the display role');
$check(str_contains($header, 'View as Player') && str_contains($header, 'View as Writer'), 'Public header has View as Player and View as Writer buttons');
$check(str_contains($header, 'Exit view'), 'Public header has an Exit view button');
$check(str_contains($header, "if((int)(\$user['is_admin']??0)===1):\$viewAsMode=AuthService::viewAsMode();"), 'Public header view-as control is shown only to real admins');

/* Workspace sidebar: section gating uses the display role; the control
   sits before the logout form and is admin-only. */
$check(str_contains($sidebar, '$isAdmin=AuthService::displayIsAdmin($user);$isWriter=AuthService::displayIsWriter($user);'), 'Sidebar derives its admin and writer flags from the display role');
$check(str_contains($sidebar, 'View as Player') && str_contains($sidebar, 'View as Writer'), 'Sidebar has View as Player and View as Writer buttons');
$check(str_contains($sidebar, 'Exit view'), 'Sidebar has an Exit view button');
$check(strpos($sidebar, 'sidebar-view-as') !== false && strpos($sidebar, 'sidebar-view-as') < strpos($sidebar, 'sidebar-logout'), 'Sidebar view-as control sits before the logout form');

/* Profile: writer tab and data use the display role; Super Admin lines
   use the display role. */
$check(str_contains($profile, '$isWriter=AuthService::displayIsWriter($user);'), 'Profile writer flag uses the display role');
$check(str_contains($profile, 'if(AuthService::displayIsSuperAdmin($user)): ?><section class="card"><h2>Deactivate Account</h2>'), 'Profile Deactivate section uses the display role');
$check(str_contains($profile, 'if(AuthService::displayIsSuperAdmin($user)): ?><p><strong>Super Admin</strong>'), 'Profile My Occasions Super Admin line uses the display role');

/* Workflow wires the guard into php-lint. */
$check(str_contains($workflow, 'run: php tools/view_as_guard_test.php'), 'Workflow runs the view-as guard in php-lint');

if ($failures) {
    fwrite(STDERR, 'FAILED: ' . implode('; ', $failures) . "\n");
    exit(1);
}
echo "OK admin view-as preview guard\n";
