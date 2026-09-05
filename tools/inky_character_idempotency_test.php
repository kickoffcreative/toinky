<?php
declare(strict_types=1);

$source=file_get_contents(dirname(__DIR__).'/app/StoryAuthoringService.php');
if($source===false)throw new RuntimeException('Could not read StoryAuthoringService.php.');

$checks=[
    'character 1 uses the shared character proposal path'=>str_contains($source,"if(is_array(\$character))self::applyInkyCharacterProposal(\$user,\$plotId,\$character,1);"),
    'all character proposal keys use the shared path'=>str_contains($source,"preg_match('/^character_(?:[1-9]|1[0-6])$/',\$key)===1"),
    'existing character lookup uses the stable role code'=>str_contains($source,"SELECT plot_role_id,role_code,slot_no FROM plot_roles WHERE plot_id=? AND active=1 AND UPPER(role_code)=?"),
    'proposal slot can identify an existing character'=>str_contains($source,"SELECT plot_role_id,role_code,slot_no FROM plot_roles WHERE plot_id=? AND active=1 AND slot_no=?"),
    'existing characters are saved with their id'=>str_contains($source,"if(\$id)\$input['plot_role_id']=\$id;self::saveCharacter(\$user,\$plotId,\$input);"),
];

$failed=[];foreach($checks as$name=>$passed)if(!$passed)$failed[]=$name;
if($failed)throw new RuntimeException('Inky character idempotency contract failed: '.implode('; ',$failed));
echo "Inky character idempotency contract passed.\n";
