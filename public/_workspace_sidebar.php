<?php
if(!function_exists('render_workspace_sidebar')){
function render_workspace_sidebar(array $user,string $active):void{?>
<aside class="workspace-side admin-shell-side"><div class="toinky-brand-group"><a class="toinky-brand" href="./?view=public" aria-label="Open Toinky homepage"><span class="toinky-logo-mark" role="img" aria-label="Toinky"></span></a><span class="brand-context">Workspace</span></div>
<?php if((int)($user['is_admin']??0)===1):?><div class="workspace-group">Accounts</div><a href="admin.php?view=overview">Overview</a><a href="admin.php?view=accounts">Manage Users</a><a href="admin.php?view=invitations">Invitations</a><a href="admin.php?view=writer_requests">Writer Requests</a><?php endif;?>
<div class="workspace-group">Gameplay</div><?php if((int)($user['is_admin']??0)===1):?><a href="admin.php?view=simulation">Simulation View</a><a class="<?=$active==='lab'?'active':''?>" href="lab.php">Story Engine Lab</a><?php endif;?><a class="<?=$active==='writer'?'active':''?>" href="writer.php">Story Writer</a>
<?php if((int)($user['is_admin']??0)===1):?><div class="workspace-group">Story Publishing</div><a href="admin.php?view=story_reviews">Story Reviews</a><a href="admin.php?view=themes">Theme Manager</a><div class="workspace-group">Site Settings</div><a href="admin.php?view=brand_fonts">Brand</a><a href="admin.php?view=security">Security</a><?php endif;?>
<div class="workspace-group">Account</div><a href="profile.php">My Profile</a><a href="./?view=public">Public Portal</a></aside>
<?php }}
