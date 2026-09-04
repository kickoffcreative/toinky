<?php
declare(strict_types=1);

$root=dirname(__DIR__);
$profile=file_get_contents($root.'/public/profile.php');
$profileScript=file_get_contents($root.'/public/assets/toinky-profile.js');
$writer=file_get_contents($root.'/public/writer.php');
$lobby=file_get_contents($root.'/public/lobby.php');
$lobbyService=file_get_contents($root.'/app/LobbyService.php');
$component=file_get_contents($root.'/public/_inky.php');
$script=file_get_contents($root.'/public/assets/toinky-inky.js');
$guideScript=file_get_contents($root.'/public/assets/toinky-inky-guide.js');
$style=file_get_contents($root.'/public/assets/toinky-inky.css');
$guideStyle=file_get_contents($root.'/public/assets/toinky-inky-guide.css');
$connection=file_get_contents($root.'/app/AiConnectionService.php');
$cowriter=file_get_contents($root.'/app/InkyCowriterService.php');
$actionEndpoint=file_get_contents($root.'/public/inky-action.php');
$guideService=file_get_contents($root.'/app/InkyGuideService.php');
$guideEndpoint=file_get_contents($root.'/public/inky-starter.php');
$authoringService=file_get_contents($root.'/app/StoryAuthoringService.php');
$docs=file_get_contents($root.'/docs/INKY.md');
$migration=file_get_contents($root.'/database/migrations/083_inky_user_preferences.sql');
$mysteryMigration=file_get_contents($root.'/database/migrations/094_mystery_characters.sql');
$identityMigration=file_get_contents($root.'/database/migrations/084_inky_character_identity.sql');
$futureCatalogMigration=file_get_contents($root.'/database/migrations/085_inky_future_character_catalog.sql');
$defaultCatalogMigration=file_get_contents($root.'/database/migrations/086_inky_character_type_catalog.sql');
$lanternMigration=file_get_contents($root.'/database/migrations/092_enable_lantern_inky.sql');
$guideDraftMigration=file_get_contents($root.'/database/migrations/093_inky_guided_story_drafts.sql');
$profileService=file_get_contents($root.'/app/UserProfileService.php');
foreach(compact('profile','profileScript','writer','component','script','guideScript','style','guideStyle','connection','cowriter','actionEndpoint','guideService','guideEndpoint','authoringService','docs','migration','identityMigration','futureCatalogMigration','defaultCatalogMigration','lanternMigration','guideDraftMigration','profileService')as$name=>$source)if($source===false)throw new RuntimeException('Could not read Inky '.$name.' source.');
foreach([
    'data-profile-tab="inky"',
    'save_inky_preferences',
    'Disable Inky',
    'These personal settings are available to every Toinky user.',
    'Name Your Inky',
    'Choose Your Inky Character',
    'inky-coming-soon-badge',
]as$required)if(!str_contains($profile,$required))throw new RuntimeException('Profile Inky controls are missing '.$required.'.');
foreach(["return'Inky'","'character_code'=>'INKY_OCTOPUS'","'INKY_OCTOPUS'"]as$required)if(!str_contains($profileService,$required))throw new RuntimeException('Profile Inky service default is missing '.$required.'.');
foreach(["return'Inky'"]as$required)if(!str_contains($profileScript,$required))throw new RuntimeException('Profile Inky preview default is missing '.$required.'.');
foreach([
    "render_inky(\$writer,['context'=>'story-writer'",
    "require_once dirname(__DIR__).'/app/UserProfileService.php'",
    'data-inky-target-type="decision"',
    'data-inky-target-type="opportunity"',
]as$required)if(!str_contains($writer,$required))throw new RuntimeException('Story Writer Inky integration is missing '.$required.'.');
foreach([
    "data-inky-action",
    "data-csrf",
    "UserProfileService::inkyPreferences",
    "UserProfileService::inkyDisplayName",
    "data-character-code",
    "data-display-name",
    "data-proactive-help",
    "filemtime(__DIR__.'/assets/toinky-inky.css')",
    "filemtime(__DIR__.'/assets/toinky-inky.js')",
    "inky-tentacle",
    "inky-sucker",
    "inky-pen",
    "if(!(int)\$preferences['enabled'])return",
    "'whats_next'=>['label'=>\"What's Next?\",'implemented'=>true]",
    "'quality_check'=>['label'=>'Quality Check My Work','implemented'=>true]",
    "'dependencies'=>['label'=>'Find Dependency Problems','implemented'=>true]",
    'data-inky-next-actions',
    'data-inky-action="guide_story"',
    'data-inky-action="review_plot"',
    'data-inky-action="character_knowledge"',
    'data-inky-action="story_values"',
    'data-inky-action="review_opportunity"',
    'data-prerequisite="characters"',
    'characterCapacity',
    'data-prerequisite="values"',
    'data-prerequisite="opportunities"',
    "'focused_tests'=>['label'=>'Prepare Focused Tests','implemented'=>true]",
]as$required)if(!str_contains($component,$required))throw new RuntimeException('Inky component contract is missing '.$required.'.');
foreach([
    "fetch('inky-status.php'",
    "fetch('inky-preferences.php'",
    "fetch('inky-action.php'",
    "setPointerCapture",
    "innerWidth>700",
    "button.dataset.implemented!=='1'",
    "addResultMeta",
    "automated_findings",
    "inky-result-meta",
    "currentTarget",
    "target_type",
    "target_code",
    "proactiveHelp",
    "companionName",
    "replace(/^Inky\\b/,companionName)",
    "has-proactive-suggestion",
    "Open '+companionName+' for story suggestions.",
    'nextToggle',
    "action==='guide_story'",
    "action==='quality_check'",
    "renderQualityCheck",
    "action==='dependencies'",
    "renderDependencyCheck",
    "action==='character_knowledge'",
    "renderCharacterKnowledge",
    "action==='story_values'",
    "renderStoryValues",
    "action==='review_opportunity'",
    "renderOpportunityReview",
    "action==='focused_tests'",
    "renderFocusedTests",
]as$required)if(!str_contains($script,$required))throw new RuntimeException('Inky browser behavior is missing '.$required.'.');
foreach(['Suggest A Character','Add and Continue Story with','toinkyGuideQueueProposal','action:\'suggest_character\'','character_suggestion','has-character-suggestion','inky-character-suggestion-details','data-next-character-capacity']as$required)if(!str_contains($script.$component,$required))throw new RuntimeException('Inky character suggestion behavior is missing '.$required.'.');
if(str_contains($script,'Open Characters to review and save'))throw new RuntimeException('The character suggestion must use the Continue Story approval flow.');
if(is_file($root.'/public/security-ai.php'))throw new RuntimeException('The obsolete public security-ai.php route must remain deleted.');
foreach([
    'pointer-events:none',
    'pointer-events:auto',
    '@media(max-width:700px)',
    '@media(prefers-reduced-motion:reduce)',
    'inky-nudge',
    'has-proactive-suggestion',
    'inky-coming-soon-badge',
    'inky-tentacle',
    'background:transparent!important',
    '@media(min-width:801px)',
    '.workspace-floating.workspace-menu-open .inky-shell[class*="anchor-left"]',
]as$required)if(!str_contains($style,$required))throw new RuntimeException('Inky safe responsive styling is missing '.$required.'.');
if(str_contains($script,'GAME2_AI_')||str_contains($component,'GAME2_AI_'))throw new RuntimeException('Inky exposed a server connection setting to the browser.');
if(!str_contains($connection,"'/api/tags'"))throw new RuntimeException('Inky availability does not use the Ollama status endpoint.');
if(!str_contains($connection,"'/api/chat'"))throw new RuntimeException('Inky co-writer actions do not use the Ollama chat endpoint.');
foreach(['decodeStructuredResponse','extractJsonValue','<think\\b[^>]*>.*?<\\/think>','choices']as$required)if(!str_contains($connection,$required))throw new RuntimeException('AI structured-response normalization is missing '.$required.'.');
foreach(['Separate observed evidence from inference','Never recommend plot-specific PHP','MANUAL STRUCTURAL REVIEW','stable_key']as$required)if(!str_contains($cowriter,$required))throw new RuntimeException('Review Plot contract is missing '.$required.'.');
foreach(['INKY_WHATS_NEXT','validatedNextSteps','Return 3 to 6 steps','Prioritize blocking validation errors']as$required)if(!str_contains($cowriter,$required))throw new RuntimeException('What\'s Next contract is missing '.$required.'.');
foreach(['INKY_QUALITY_CHECK','validatedQualityCheck','immersion, viewpoint_continuity, character_agency','Scores are integers from 1 to 5']as$required)if(!str_contains($cowriter,$required))throw new RuntimeException('Quality Check contract is missing '.$required.'.');
foreach(['INKY_DEPENDENCY_CHECK','validatedDependencyCheck','missing active choices','undefined managed story values']as$required)if(!str_contains($cowriter,$required))throw new RuntimeException('Dependency Check contract is missing '.$required.'.');
foreach(['INKY_CHARACTER_KNOWLEDGE','validatedCharacterKnowledge','non-privileged information','role_code']as$required)if(!str_contains($cowriter,$required))throw new RuntimeException('Character Knowledge contract is missing '.$required.'.');
foreach(['INKY_STORY_VALUES','validatedStoryValues','database-authored story values','allowed_values']as$required)if(!str_contains($cowriter,$required))throw new RuntimeException('Story Values contract is missing '.$required.'.');
foreach(['INKY_OPPORTUNITY_REVIEW','validatedOpportunityReview','AI rationale guidance','condition/effect fit','targetFocus','REQUESTED FOCUS']as$required)if(!str_contains($cowriter,$required))throw new RuntimeException('Opportunity Review contract is missing '.$required.'.');
foreach(['INKY_FOCUSED_TESTS','validatedFocusedTests','Game2 Simulation Lab workflows','seeded focused opportunity test']as$required)if(!str_contains($cowriter,$required))throw new RuntimeException('Focused Tests contract is missing '.$required.'.');
foreach(['INKY_SUGGEST_CHARACTER','suggestCharacter','validatedCharacterSuggestion','available Story Framework slots','starting_location']as$required)if(!str_contains($cowriter,$required))throw new RuntimeException('Suggest Character contract is missing '.$required.'.');
foreach(["`What's Next?` is the second implemented vertical slice",'Every step must cite a stable key','MANUAL STRUCTURAL REVIEW','`Quality Check My Work` is the third implemented vertical slice','scored checks for','`Find Dependency Problems` is the fourth implemented vertical slice','recommend an authored-data fix','`Check Character Knowledge` is the fifth implemented vertical slice','cite a character role code','`Suggest Story Values` is the sixth implemented vertical slice','does not change schema or PHP','`Review This Opportunity` is the seventh implemented vertical slice','prompt clarity, player agency','stable focus code','`Prepare Focused Tests` is the eighth implemented vertical slice','not proof of organic reachability','companion name, model','passive nudge','does not auto-open the panel','auto-run AI actions','Only When Clicked','Octopus is the default selectable','default floating artwork should read as the Octopus','moves the saved anchor to that side','open navigation drawer instead of hiding behind it','must remain transparent','Lantern preserves','Squid preserves','Catalog labels should describe character types']as$required)if(!str_contains($docs,$required))throw new RuntimeException('Inky documentation is missing '.$required.'.');
foreach(['AuthService::requireWriter','AuthService::requireCsrf','InkyCowriterService::run']as$required)if(!str_contains($actionEndpoint,$required))throw new RuntimeException('Inky action endpoint is missing '.$required.'.');
if(str_contains($actionEndpoint,"'message'=>\$e->getMessage()"))throw new RuntimeException('Inky action errors may expose private connection details to the browser.');
foreach(['INKY_STORY_GUIDE','INKY_STORY_GUIDE_CONTENT','INKY_STORY_GUIDE_EDITS','suggestContent','suggestEdits','AiConnectionService::completeJson','validatedResponse','STEP_COUNT','guidedDrafts','loadProgress','saveProgress','variation','editable_fields','proposalFields','mergeProposalFields','Only revise','edit target','edit replacement','genuinely different','no usable targeted edit suggestions']as$required)if(!str_contains($guideService,$required))throw new RuntimeException('Inky guided conversation service is missing '.$required.'.');
if(str_contains($guideService,'StoryAuthoringService'))throw new RuntimeException('Inky guide must not create story records during the conversation.');
foreach(['InkyGuideService::guide','InkyGuideService::suggestContent','InkyGuideService::suggestEdits','InkyGuideService::loadProgress','InkyGuideService::saveProgress','StoryAuthoringService::createStory','StoryAuthoringService::deleteDraft','StoryAuthoringService::applyInkyProposal','suggest_content','suggest_edits','load_progress','save_progress','create_draft','save_proposal','mode','requireCsrf']as$required)if(!str_contains($guideEndpoint,$required))throw new RuntimeException('Inky guided conversation endpoint is missing '.$required.'.');
 foreach(['public static function deleteDraft','inky_story_guide_drafts','ARCHETYPES','plot_world_event_id','resolution_fragment_id','Save the location name before accepting its description.']as$required)if(!str_contains($authoringService,$required))throw new RuntimeException('Draft lifecycle support is missing '.$required.'.');
foreach(['data-inky-guide-form','data-inky-guide-question','data-inky-guide-submit','data-inky-guide-complete','Use Brief In New Story','guidePost','inky-starter.php','writer.php?inky_starter=1','canAddPlayer','canAddNpc','Character creation is unavailable because both Story Framework character limits have been reached.']as$required)if(!str_contains($writer,$required))throw new RuntimeException('Story Writer guided conversation UI is missing '.$required.'.');
foreach(['story-creation-grid','story-creation-tabs','data-story-creation-tab','data-story-creation-panel','story-switcher .inky-starter']as$required)if(!str_contains($writer,$required))throw new RuntimeException('Story Writer creation layout is missing '.$required.'.');
foreach(['Write New Story with','data-inky-story-panel','data-inky-guide-form','name="code"','name="title"','name="genre_id"','name="setting_name"','name="archetype"','name="max_players"','name="max_npcs"','data-inky-story-create','currentCode']as$required)if(!str_contains($component.$guideScript,$required))throw new RuntimeException('Inky popup story form is missing '.$required.'.');
foreach(['stable-code-label','Stable Story Code','white-space:normal','overflow-wrap:anywhere']as$required)if(!str_contains($writer,$required))throw new RuntimeException('Stable Story Code label rendering is missing '.$required.'.');
foreach(['panelIndex={locations:0,values:1,sections:2}[active]','writer-content .overview-manager','manager.hidden=index!==panelIndex']as$required)if(!str_contains($writer,$required))throw new RuntimeException('Story Framework subtab visibility fallback is missing '.$required.'.');
foreach(['framework-subtab-<?=e($subtab)?>','framework-subtab-locations','.overview-manager:nth-of-type(1)']as$required)if(!str_contains($writer,$required))throw new RuntimeException('Story Framework server visibility rules are missing '.$required.'.');
foreach(['save_guide_answers','Guided Answers','guided_answers','guideProgress']as$required)if(!str_contains($writer,$required))throw new RuntimeException('Story Framework guided answer editing is missing '.$required.'.');
 foreach(['openStoryComposer','is-composing','mainActions.hidden=true','actionAvailable','nextToggle','data-inky-next-actions','requestContent','requestEdits','Suggested Edits','targetLabel','replacementLabel','indexOf(needle)','Targeted edit applied','no usable targeted edits','Save Draft','Edit','loadProgress','saveDraft','variationOptions','proposalStages','showAccepted','renderAccepted','renderGuideAnswers','data-inky-guide-answer-index','Save Step','Effect Scope','Allowed Values','role_type','phase','archetypeOptions.map','addEditableNode(editor,currentKey','data-inky-proposal-toggle','selectedProposalFields','editable_fields','Update with Inky','checked=false','anotherButton.disabled=disabled','inky-guide-button:disabled','createDraft','save_proposal','data-inky-story-create','Continue Story with','&inky_draft=','storyBack?.addEventListener','nextActions.hidden','inky-sub-actions[hidden]','padding:8px 12px','stepLabels','stepTitle','await createDraft();await persistProgress','answersState[step]=pending','inky-actions[hidden]','width:min(720px','inky-guide-variation-menu','readEditedValue(card,currentProposal?.value??null)','copyToClipboard','Copy Suggestion','Copy Review','Copy Story Brief','inky-copy-button','inky-guide-complete-footer','window.confirm','max-width:100%','current_proposal','acceptedContext','const fallback']as$required)if(!str_contains($script.$guideScript.$component.$style.$guideStyle))throw new RuntimeException('Inky popup authoring flow is missing '.$required.'.');
if(!str_contains($writer,'writer_archetype_field'))throw new RuntimeException('Story Writer archetype dropdown is missing.');
if(str_contains($script,'initStoryGuide();'))throw new RuntimeException('The legacy Story Writer guide initializer must not run alongside the active Inky guide.');
if(!str_contains($writer,'story-creation-grid{display:block!important}'))throw new RuntimeException('The normal New Story form is not visible.');
if(!str_contains($writer,'story-creation-tab[data-story-creation-tab="inky"],.story-creation-panel.inky-starter{display:none!important}'))throw new RuntimeException('The old top-level Create With Inky form is still visible.');
foreach(['Drafts','Delete Draft','delete_draft','inky_draft']as$required)if(!str_contains($writer,$required))throw new RuntimeException('Story Writer draft recovery UI is missing '.$required.'.');
foreach(['MAX_ANSWER_CHARS','MAX_TOTAL_CHARS','maxlength="12000"','story spark']as$required)if(!str_contains($guideService.$writer,$required))throw new RuntimeException('Inky guided conversation does not support long answers.');
foreach(['mystery_character','Identity and details revealed when the story begins.','Mystery Character']as$required)if(!str_contains($authoringService.$writer.$lobby.$lobbyService.$mysteryMigration,$required))throw new RuntimeException('Mystery character support is missing '.$required.'.');
if(!str_contains($migration,'PRIMARY KEY(user_id)'))throw new RuntimeException('Inky preferences are not scoped uniquely to each user.');
foreach(['inky_story_guide_drafts','state_json','uq_inky_guide_plot','fk_inky_guide_plot']as$required)if(!str_contains($guideDraftMigration,$required))throw new RuntimeException('Inky guided draft migration is missing '.$required.'.');
foreach(['CREATE TABLE IF NOT EXISTS inky_characters','companion_name_root','character_code','INKY_CLASSIC']as$required)if(!str_contains($identityMigration,$required))throw new RuntimeException('Inky identity migration is missing '.$required.'.');
foreach(['anchorForDock','anchor_position=VALUES(anchor_position)']as$required)if(!str_contains($profileService,$required))throw new RuntimeException('Inky side preference does not update the saved anchor.');
foreach(['INKY_DIRECTION_F','INKY_DIRECTION_D','INKY_OCTOPUS','INKY_SQUID','selectable,active)','20,0,1']as$required)if(!str_contains($futureCatalogMigration,$required))throw new RuntimeException('Inky future character catalog migration is missing '.$required.'.');
foreach(['INKY_OCTOPUS','Octopus','INKY_SQUID','Squid','Lantern',"ALTER character_code SET DEFAULT 'INKY_OCTOPUS'","WHERE character_code IN('INKY_CLASSIC','INKY_DIRECTION_F','INKY_TOINKY')"]as$required)if(!str_contains($defaultCatalogMigration,$required))throw new RuntimeException('Inky corrected character catalog migration is missing '.$required.'.');
foreach(["character_code='INKY_DIRECTION_D'","selectable=1","artwork_path='assets/inky/lantern/source/lantern_master_v1.png'","092_enable_lantern_inky.sql"]as$required)if(!str_contains($lanternMigration,$required))throw new RuntimeException('Lantern activation migration is missing '.$required.'.');
foreach(['inky-lantern-rig','inky-lantern-wrap','inky-lantern-shell','assets/inky/lantern/rig/','assets/inky/lantern/rig/optimized/','assets/inky/rig/optimized/','INKY_DIRECTION_D','assets/vendor/pixi.min.js','toinky-inky-pixi.js']as$required)if(!str_contains($component,$required))throw new RuntimeException('Live Lantern renderer is missing '.$required.'.');
foreach(['public/assets/inky/lantern/rig/optimized','public/assets/inky/rig/optimized']as$optimizedRig)if(!is_dir($root.'/'.$optimizedRig)||!count(glob($root.'/'.$optimizedRig.'/*.png')))throw new RuntimeException('Optimized Inky asset set is missing '.$optimizedRig.'.');
if(!str_contains($component,'$pixiVersion'))throw new RuntimeException('PixiJS renderer does not have an independent cache version.');
foreach(['lanternMoods','characterMoods','broken_halo']as$required)if(!str_contains($script,$required))throw new RuntimeException('Lantern mood animation support is missing '.$required.'.');
$pixiScript=file_get_contents($root.'/public/assets/toinky-inky-pixi.js');if($pixiScript===false)throw new RuntimeException('Could not read the PixiJS Inky renderer.');
foreach(['PIXI.Application','inky-pixi-canvas','app.init','fallback','resizeTo:wrap','Promise.all','halo:[365,359,120,112,360,354]','glow:[291,252,210,216,180,156]','sprite.pivot.set(texture.width','const z=','blinkActive','layers.eyeLeft.visible=!blinkActive','layers.eyeRight.visible=!blinkActive']as$required)if(!str_contains($pixiScript,$required))throw new RuntimeException('PixiJS Inky renderer is missing '.$required.'.');
foreach(['.inky-lantern-rig{transform:none','.inky-lantern-rig .inky-lantern-glow{z-index:2','.inky-lantern-rig .inky-rig-eye-left{width:7.167%','.inky-lantern-rig .inky-rig-mouth-open{left:47.167%','.inky-lantern-cape-left{left:24.167%;transform-origin:100% 48%','.inky-lantern-cape-right{left:50%;top:53.667%;transform-origin:0 48%','.inky-lantern-wrap{inset:-54.5%','.inky-lantern-rig .inky-rig-mouth{z-index:15;left:46%;top:51%;width:9.333%','.inky-lantern-rig .inky-rig-mouth.is-active{opacity:1']as$required)if(!str_contains($style,$required))throw new RuntimeException('Lantern fallback proportions are missing '.$required.'.');
if(!str_contains($style,'.inky-shell.is-inky-blinking .inky-rig-eye{opacity:0}'))throw new RuntimeException('Inky blinking must hide open-eye layers.');
echo "PASS: Inky profile, placement, privacy, Qwen availability, and Review Plot contracts are present.\n";
