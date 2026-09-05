<?php
declare(strict_types=1);

$root=dirname(__DIR__);
require_once $root.'/app/StoryAuthoringService.php';
require_once $root.'/app/InkyCowriterService.php';

$method=new ReflectionMethod(StoryAuthoringService::class,'auditManagedReferences');
$method->setAccessible(true);
$workspace=[
    'state_definitions'=>[
        ['effect_scope'=>'WORLD_STATE','target_code'=>'GUEST_HELPERS','value_type'=>'PRESET','allowed_values_text'=>'2','active'=>1],
        ['effect_scope'=>'WORLD_STATE','target_code'=>'GUEST_STATUS','value_type'=>'PRESET','allowed_values_text'=>"EVACUATED\nEVACUATING\nSTABILIZED",'active'=>1],
        ['effect_scope'=>'WORLD_STATE','target_code'=>'GUEST_SECOND_HELPER','value_type'=>'PRESET','allowed_values_text'=>"KELLAN\nWREN",'active'=>1],
    ],
    'locations'=>[['location_code'=>'EAST_STAIRWELL','label_text'=>'East Stairwell','active'=>1]],
    'events'=>[['phase'=>'CLIMAX','active'=>1]],
    'dynamic_conditions'=>[
        ['active'=>1,'opportunity_code'=>'WREN_RESCUE_GAP','source_type'=>'WORLD_STATE','source_key'=>'GUEST_HELPERS','operator'=>'EQ','compare_value'=>'1'],
        ['active'=>1,'opportunity_code'=>'WREN_RESCUE_GAP','source_type'=>'WORLD_STATE','source_key'=>'GUEST_STATUS','operator'=>'IN','compare_value'=>'INJURED,STABILIZED,AT_RISK'],
        ['active'=>1,'opportunity_code'=>'WREN_FINAL_RESCUE_CHOICE','source_type'=>'WORLD_STATE','source_key'=>'GUEST_HELPERS','operator'=>'GTE','compare_value'=>'2'],
        ['active'=>1,'opportunity_code'=>'WREN_RESCUE_GAP','source_type'=>'LOCATION','source_key'=>'','operator'=>'EQ','compare_value'=>'East Stairwell'],
    ],
    'resolution_conditions'=>[
        ['active'=>1,'section_code'=>'PEOPLE','state_key'=>'GUEST_SECOND_HELPER','operator'=>'EQ','compare_value'=>'NIKO'],
    ],
    'effects'=>[],
    'dynamic_effects'=>[],
];
$result=$method->invoke(null,$workspace);
$errors=implode("\n",$result[0]);
foreach([
    'WREN_RESCUE_GAP has an invalid WORLD_STATE|GUEST_HELPERS condition',
    'WREN_RESCUE_GAP has an invalid WORLD_STATE|GUEST_STATUS condition',
    'WREN_FINAL_RESCUE_CHOICE has an invalid WORLD_STATE|GUEST_HELPERS condition',
    'Ending condition PEOPLE has an invalid WORLD_STATE|GUEST_SECOND_HELPER condition',
]as$expected)if(!str_contains($errors,$expected))throw new RuntimeException('Integrity audit missed '.$expected.'.');
if(count($result[0])!==4)throw new RuntimeException('Integrity audit emitted an unexpected number of findings.');
$knowledgeMethod=new ReflectionMethod(InkyCowriterService::class,'characterKnowledgeItems');
$knowledgeMethod->setAccessible(true);
$knowledge=$knowledgeMethod->invoke(null,[
    ['role_code'=>'WREN','stable_key'=>'CHARACTER_KEY','severity'=>'HIGH','issue'=>'Invalid WORLD_STATE condition','evidence'=>'GUEST_HELPERS uses GTE with a Preset Story Value.','fix'=>'Change the operator.'],
    ['role_code'=>'WREN','stable_key'=>'CHARACTER_KEY','severity'=>'MEDIUM','issue'=>'Private knowledge is too broad','evidence'=>'The character knows a fact without a player-facing explanation.','fix'=>'Clarify the private_knowledge field.'],
],['CHARACTER_KEY'],['WREN']);
if(count($knowledge)!==1||$knowledge[0]['issue']!=='Private knowledge is too broad')throw new RuntimeException('Character review guardrail did not remove structural Story Value findings.');
echo "PASS: managed Story Value integrity and review-scope guardrails are enforced.\n";
