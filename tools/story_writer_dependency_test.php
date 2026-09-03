<?php
declare(strict_types=1);

require_once dirname(__DIR__).'/app/bootstrap.php';
require_once dirname(__DIR__).'/app/StoryAuthoringService.php';

$workspace=[
    'locations'=>[['plot_location_id'=>1,'location_code'=>'JUNGLE','label_text'=>'Jungle','active'=>1]],
    'characters'=>[['plot_role_id'=>1,'role_code'=>'SURVIVOR','name'=>'Survivor','starting_location'=>'Jungle','active'=>0]],
    'state_definitions'=>[['plot_state_definition_id'=>1,'effect_scope'=>'WORLD_STATE','target_code'=>'DANGER','active'=>1]],
    'story_sections'=>[['plot_story_section_id'=>1,'section_code'=>'AFTERMATH','active'=>1]],
    'events'=>[['plot_world_event_id'=>1,'event_code'=>'VENTURE_INTO_JUNGLE','active'=>1]],
    'decisions'=>[['template_id'=>10,'event_code'=>'VENTURE_INTO_JUNGLE','role_code'=>'SURVIVOR','active'=>0]],
    'options'=>[['template_option_id'=>20,'template_id'=>10,'option_code'=>'HIDE','active'=>1]],
    'effects'=>[['effect_id'=>30,'template_option_id'=>20,'event_code'=>'VENTURE_INTO_JUNGLE','option_code'=>'HIDE','effect_scope'=>'WORLD_STATE','target_code'=>'DANGER','active'=>1]],
    'opportunities'=>[['dynamic_opportunity_id'=>40,'opportunity_code'=>'FIND_SHELTER','role_code'=>'SURVIVOR','active'=>0]],
    'dynamic_conditions'=>[['dynamic_condition_id'=>50,'dynamic_opportunity_id'=>40,'opportunity_code'=>'FIND_SHELTER','source_type'=>'WORLD_STATE','source_key'=>'DANGER','active'=>1]],
    'dynamic_options'=>[['dynamic_option_id'=>60,'dynamic_opportunity_id'=>40,'opportunity_code'=>'FIND_SHELTER','option_code'=>'CAMP','active'=>1]],
    'dynamic_effects'=>[['dynamic_effect_id'=>70,'dynamic_option_id'=>60,'effect_scope'=>'WORLD_STATE','target_code'=>'DANGER','active'=>1]],
    'resolutions'=>[['resolution_fragment_id'=>80,'section_code'=>'AFTERMATH','active'=>0]],
    'resolution_conditions'=>[['resolution_condition_id'=>90,'resolution_fragment_id'=>80,'section_code'=>'AFTERMATH','state_key'=>'DANGER','active'=>1]],
    'media'=>[],
];

$usage=StoryAuthoringService::stableCodeUsageMap(1,$workspace);
if(isset($usage['event'][1]))throw new RuntimeException('Inactive decisions must not lock an event code.');
if(isset($usage['location'][1]))throw new RuntimeException('Inactive character dependencies must not lock a location code.');
if(isset($usage['state_definition'][1]))throw new RuntimeException('Inactive parent decisions and endings must not lock a story value.');
if(isset($usage['opportunity'][40]))throw new RuntimeException('Inactive opportunities must not lock their own code.');

$workspace['decisions'][0]['active']=1;
$usage=StoryAuthoringService::stableCodeUsageMap(1,$workspace);
if(($usage['event'][1]['DECISIONS']??0)!==1)throw new RuntimeException('Active decisions must lock their event code.');
if(($usage['state_definition'][1]['EFFECTS']??0)!==1)throw new RuntimeException('Active option effects must lock their story value.');

$details=StoryAuthoringService::dependencyDetails(1,$workspace);
foreach(['location','event','state_definition','story_section','resolution']as$entity)if(empty($details[$entity]))throw new RuntimeException('Dependency details are missing '.$entity.' coverage.');

echo "Story Writer active-dependency behavior passed.\n";
