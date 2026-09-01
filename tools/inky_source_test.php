<?php
declare(strict_types=1);

$root=dirname(__DIR__);
$profile=file_get_contents($root.'/public/profile.php');
$writer=file_get_contents($root.'/public/writer.php');
$component=file_get_contents($root.'/public/_inky.php');
$script=file_get_contents($root.'/public/assets/toinky-inky.js');
$style=file_get_contents($root.'/public/assets/toinky-inky.css');
$connection=file_get_contents($root.'/app/AiConnectionService.php');
$cowriter=file_get_contents($root.'/app/InkyCowriterService.php');
$actionEndpoint=file_get_contents($root.'/public/inky-action.php');
$docs=file_get_contents($root.'/docs/INKY.md');
$migration=file_get_contents($root.'/database/migrations/083_inky_user_preferences.sql');
$identityMigration=file_get_contents($root.'/database/migrations/084_inky_character_identity.sql');
foreach(compact('profile','writer','component','script','style','connection','cowriter','actionEndpoint','docs','migration','identityMigration')as$name=>$source)if($source===false)throw new RuntimeException('Could not read Inky '.$name.' source.');
foreach([
    'data-profile-tab="inky"',
    'save_inky_preferences',
    'Disable Inky',
    'These personal settings are available to every Toinky user.',
    'Name Your Inky',
    'Choose Your Inky Character',
    'More Inky characters and artwork are in the pipeline.',
]as$required)if(!str_contains($profile,$required))throw new RuntimeException('Profile Inky controls are missing '.$required.'.');
foreach([
    "render_inky(\$writer,['context'=>'story-writer','plot_id'=>\$plotId])",
    "require_once dirname(__DIR__).'/app/UserProfileService.php'",
]as$required)if(!str_contains($writer,$required))throw new RuntimeException('Story Writer Inky integration is missing '.$required.'.');
foreach([
    "data-inky-action",
    "data-csrf",
    "UserProfileService::inkyPreferences",
    "UserProfileService::inkyDisplayName",
    "data-character-code",
    "if(!(int)\$preferences['enabled'])return",
    "'whats_next'=>['label'=>\"What's Next?\",'implemented'=>true]",
    "'quality_check'=>['label'=>'Quality Check My Work','implemented'=>true]",
    "'dependencies'=>['label'=>'Find Dependency Problems','implemented'=>true]",
    "'character_knowledge'=>['label'=>'Check Character Knowledge','implemented'=>true]",
]as$required)if(!str_contains($component,$required))throw new RuntimeException('Inky component contract is missing '.$required.'.');
foreach([
    "fetch('inky-status.php'",
    "fetch('inky-preferences.php'",
    "fetch('inky-action.php'",
    "setPointerCapture",
    "innerWidth>700",
    "button.dataset.implemented!=='1'",
    "action==='whats_next'",
    "renderNextSteps",
    "action==='quality_check'",
    "renderQualityCheck",
    "action==='dependencies'",
    "renderDependencyCheck",
    "action==='character_knowledge'",
    "renderCharacterKnowledge",
]as$required)if(!str_contains($script,$required))throw new RuntimeException('Inky browser behavior is missing '.$required.'.');
foreach([
    'pointer-events:none',
    'pointer-events:auto',
    '@media(max-width:700px)',
    '@media(prefers-reduced-motion:reduce)',
]as$required)if(!str_contains($style,$required))throw new RuntimeException('Inky safe responsive styling is missing '.$required.'.');
if(str_contains($script,'GAME2_AI_')||str_contains($component,'GAME2_AI_'))throw new RuntimeException('Inky exposed a server connection setting to the browser.');
if(!str_contains($connection,"'/api/tags'"))throw new RuntimeException('Inky availability does not use the Ollama status endpoint.');
if(!str_contains($connection,"'/api/chat'"))throw new RuntimeException('Inky co-writer actions do not use the Ollama chat endpoint.');
foreach(['Separate observed evidence from inference','Never recommend plot-specific PHP','MANUAL STRUCTURAL REVIEW','stable_key']as$required)if(!str_contains($cowriter,$required))throw new RuntimeException('Review Plot contract is missing '.$required.'.');
foreach(['INKY_WHATS_NEXT','validatedNextSteps','Return 3 to 6 steps','Prioritize blocking validation errors']as$required)if(!str_contains($cowriter,$required))throw new RuntimeException('What\'s Next contract is missing '.$required.'.');
foreach(['INKY_QUALITY_CHECK','validatedQualityCheck','immersion, viewpoint_continuity, character_agency','Scores are integers from 1 to 5']as$required)if(!str_contains($cowriter,$required))throw new RuntimeException('Quality Check contract is missing '.$required.'.');
foreach(['INKY_DEPENDENCY_CHECK','validatedDependencyCheck','missing active choices','undefined managed story values']as$required)if(!str_contains($cowriter,$required))throw new RuntimeException('Dependency Check contract is missing '.$required.'.');
foreach(['INKY_CHARACTER_KNOWLEDGE','validatedCharacterKnowledge','non-privileged information','role_code']as$required)if(!str_contains($cowriter,$required))throw new RuntimeException('Character Knowledge contract is missing '.$required.'.');
foreach(["`What's Next?` is the second implemented vertical slice",'Every step must cite a stable key','MANUAL STRUCTURAL REVIEW','`Quality Check My Work` is the third implemented vertical slice','scored checks for','`Find Dependency Problems` is the fourth implemented vertical slice','recommend an authored-data fix','`Check Character Knowledge` is the fifth implemented vertical slice','cite a character role code']as$required)if(!str_contains($docs,$required))throw new RuntimeException('Inky documentation is missing '.$required.'.');
foreach(['AuthService::requireWriter','AuthService::requireCsrf','InkyCowriterService::run']as$required)if(!str_contains($actionEndpoint,$required))throw new RuntimeException('Inky action endpoint is missing '.$required.'.');
if(str_contains($actionEndpoint,"'message'=>\$e->getMessage()"))throw new RuntimeException('Inky action errors may expose private connection details to the browser.');
if(!str_contains($migration,'PRIMARY KEY(user_id)'))throw new RuntimeException('Inky preferences are not scoped uniquely to each user.');
foreach(['CREATE TABLE IF NOT EXISTS inky_characters','companion_name_root','character_code','INKY_CLASSIC']as$required)if(!str_contains($identityMigration,$required))throw new RuntimeException('Inky identity migration is missing '.$required.'.');
echo "PASS: Inky profile, placement, privacy, Qwen availability, and Review Plot contracts are present.\n";
