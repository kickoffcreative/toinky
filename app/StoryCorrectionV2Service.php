<?php
final class StoryCorrectionV2Service
{
    private const FORMAT='toinky-story-corrections-v2';
    private const MAX_BYTES=2097152;
    private const OPERATORS=['EQ','NEQ','IN','NOT_IN','EXISTS','NOT_EXISTS','LT','LTE','GT','GTE'];
    private const MANAGED_SCOPES=['FACTOR','STAT','LOCATION','KNOWLEDGE','RELATIONSHIP','WORLD_STATE','GOAL','FEAR'];
    private const SPECS=[
        'plot'=>['fields'=>['title','archetype','setting_name','scenario_text'],'risk'=>'CONTENT'],
        'character'=>['fields'=>['name','public_description','private_knowledge','starting_location','goal_text','fear_text','pronoun_set'],'risk'=>'CONTENT'],
        'event'=>['fields'=>['headline','event_text','trigger_percent','sort_order'],'risk'=>'STRUCTURAL'],
        'decision'=>['fields'=>['prompt_text','decision_window_seconds'],'risk'=>'CONTENT'],
        'option'=>['fields'=>['label','result_text','rationale_text','npc_weight'],'risk'=>'CONTENT'],
        'opportunity'=>['fields'=>['prompt_text','window_seconds'],'risk'=>'CONTENT'],
        'dynamic_option'=>['fields'=>['label','result_text','rationale_text','npc_weight'],'risk'=>'CONTENT'],
        'resolution'=>['fields'=>['fragment_text'],'risk'=>'CONTENT'],
        'story_value'=>['fields'=>['label_text','value_type','default_numeric_value','allowed_values_text','description'],'risk'=>'STRUCTURAL'],
        'dynamic_condition'=>['fields'=>['operator','compare_value'],'risk'=>'STRUCTURAL'],
        'option_effect'=>['fields'=>['delta_value','set_value','detail_text','sort_order'],'risk'=>'STRUCTURAL','upsert'=>true],
        'dynamic_effect'=>['fields'=>['delta_value','set_value','detail_text','sort_order'],'risk'=>'STRUCTURAL','upsert'=>true],
    ];

    public static function supports(array $file): bool
    {
        if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK || strtolower(pathinfo((string)($file['name']??''),PATHINFO_EXTENSION))!=='json')return false;
        $raw=@file_get_contents((string)($file['tmp_name']??''));if($raw===false)return false;
        $data=json_decode($raw,true);return is_array($data)&&($data['format']??null)===self::FORMAT;
    }

    public static function inspect(array $admin,int $versionId,array $file): array
    {
        [$package,$name]=self::load($admin,$file);$version=self::version($versionId,$package);
        return self::execute($admin,$version,$package,$name,false);
    }

    public static function import(array $admin,int $versionId,array $file,bool $confirmed): array
    {
        [$package,$name]=self::load($admin,$file);$version=self::version($versionId,$package);
        if(!$confirmed)throw new RuntimeException('Review the validation preview and confirm the structural changes before applying a Corrections v2 package.');
        return self::execute($admin,$version,$package,$name,true);
    }

    private static function load(array $admin,array $file): array
    {
        if((int)($admin['is_admin']??0)!==1)throw new RuntimeException('Administrator access is required.');
        if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new RuntimeException('Choose a Corrections v2 JSON package.');
        $size=(int)($file['size']??0);if($size<1||$size>self::MAX_BYTES)throw new RuntimeException('Correction packages must be 2 MB or smaller.');
        $name=(string)($file['name']??'corrections-v2.json');if(strtolower(pathinfo($name,PATHINFO_EXTENSION))!=='json')throw new RuntimeException('Corrections v2 packages must use JSON.');
        $raw=file_get_contents((string)$file['tmp_name']);if($raw===false)throw new RuntimeException('The correction package could not be read.');
        try{$data=json_decode($raw,true,512,JSON_THROW_ON_ERROR);}catch(Throwable $e){throw new RuntimeException('The JSON correction package is invalid: '.$e->getMessage());}
        if(!is_array($data)||array_is_list($data)||($data['format']??null)!==self::FORMAT)throw new RuntimeException('Use the toinky-story-corrections-v2 format.');
        self::onlyKeys($data,['format','plot_code','base_version','changes','advisories'],'package');
        if(!preg_match('/^[A-Z0-9_]+$/',(string)($data['plot_code']??'')))throw new RuntimeException('Corrections v2 requires an exact plot_code.');
        if(!is_int($data['base_version']??null)||$data['base_version']<1)throw new RuntimeException('Corrections v2 requires the exact numeric base_version.');
        if(!is_array($data['changes']??null)||!array_is_list($data['changes'])||!$data['changes'])throw new RuntimeException('Corrections v2 requires a non-empty changes array.');
        if(count($data['changes'])>500)throw new RuntimeException('A correction package may contain at most 500 changes.');
        if(isset($data['advisories'])){
            if(!is_array($data['advisories'])||!array_is_list($data['advisories'])||count($data['advisories'])>50)throw new RuntimeException('advisories must be a list of at most 50 items.');
            foreach($data['advisories']as$i=>$advisory){
                if(is_string($advisory)){if(trim($advisory)===''||mb_strlen($advisory)>2000)throw new RuntimeException('Advisory '.($i+1).' must contain 2,000 characters or fewer.');continue;}
                if(!is_array($advisory)||array_is_list($advisory))throw new RuntimeException('Advisory '.($i+1).' must be a string or an object.');self::onlyKeys($advisory,['key','issue','recommendation'],'advisory '.($i+1));
                if(!preg_match('/^[A-Z0-9_]+$/',(string)($advisory['key']??''))||trim((string)($advisory['issue']??''))===''||trim((string)($advisory['recommendation']??''))==='')throw new RuntimeException('Structured advisory '.($i+1).' requires key, issue, and recommendation.');
                if(mb_strlen((string)$advisory['issue'])>2000||mb_strlen((string)$advisory['recommendation'])>2000)throw new RuntimeException('Structured advisory '.($i+1).' is too long.');
            }
        }
        foreach($data['changes']as$i=>$change)self::validateShape($change,$i+1);
        return[$data,$name];
    }

    private static function validateShape(mixed $change,int $line): void
    {
        if(!is_array($change)||array_is_list($change))throw new RuntimeException('Change '.$line.' must be an object.');
        self::onlyKeys($change,['operation','entity','key','expected','values','reason'],'change '.$line);
        $operation=strtolower(trim((string)($change['operation']??'update')));$entity=strtolower(trim((string)($change['entity']??'')));$key=trim((string)($change['key']??''));
        $spec=self::SPECS[$entity]??null;if(!$spec)throw new RuntimeException('Unsupported Corrections v2 entity at change '.$line.'.');
        if(!in_array($operation,['update','upsert'],true)||($operation==='upsert'&&empty($spec['upsert'])))throw new RuntimeException('Unsupported operation for '.$entity.' at change '.$line.'.');
        if($key==='')throw new RuntimeException('Missing stable target key at change '.$line.'.');
        $values=$change['values']??null;if(!is_array($values)||array_is_list($values)||!$values)throw new RuntimeException('Change '.$line.' needs a non-empty values object.');
        foreach($values as$field=>$value){if(!in_array($field,$spec['fields'],true))throw new RuntimeException('Field '.$field.' is not allowed for '.$entity.' at change '.$line.'.');if(!is_scalar($value)&&$value!==null)throw new RuntimeException('Correction values must be scalar or null at change '.$line.'.');}
        if($operation==='upsert'){if(!array_key_exists('expected',$change)||$change['expected']!==null)throw new RuntimeException('Upsert change '.$line.' must use expected: null to prove the record is expected to be absent.');}
        else{$expected=$change['expected']??null;if(!is_array($expected)||array_is_list($expected))throw new RuntimeException('Update change '.$line.' needs an expected object.');foreach(array_keys($values)as$field)if(!array_key_exists($field,$expected))throw new RuntimeException('Update change '.$line.' must include expected.'.$field.'.');}
    }

    private static function version(int $versionId,array $package): array
    {
        $q=db()->prepare('SELECT v.*,p.code,p.title FROM plot_versions v JOIN plots p ON p.plot_id=v.plot_id WHERE v.plot_version_id=?');$q->execute([$versionId]);$version=$q->fetch();if(!$version)throw new RuntimeException('Submitted revision not found.');
        $q=db()->prepare('SELECT plot_version_id,version_no FROM plot_versions WHERE plot_id=? ORDER BY version_no DESC LIMIT 1');$q->execute([$version['plot_id']]);$latest=$q->fetch();if((int)$latest['plot_version_id']!==$versionId)throw new RuntimeException('Corrections can only be imported into the latest story revision. Open revision '.(int)$latest['version_no'].' and try again.');
        if(strtoupper((string)$package['plot_code'])!==strtoupper((string)$version['code']))throw new RuntimeException('The correction package targets a different story.');
        if((int)$package['base_version']!==(int)$version['version_no'])throw new RuntimeException('This package expects revision '.(int)$package['base_version'].', but the latest revision is '.(int)$version['version_no'].'. Generate a fresh reviewer package before changing structural data.');
        return$version;
    }

    private static function execute(array $admin,array $version,array $package,string $name,bool $commit): array
    {
        $pdo=db();$plotId=(int)$version['plot_id'];$beforeOrder=self::eventOrder($plotId);$affected=[];$report=[];
        $pdo->beginTransaction();try{
            foreach($package['changes']as$i=>$change)$report[]=self::apply($pdo,$plotId,$change,$i+1,$affected);
            self::validateChronology($plotId,$beforeOrder);self::validateAffectedValues($plotId,$affected);
            $audit=StoryAuthoringService::audit($plotId);if($audit['errors'])throw new RuntimeException('Post-import story validation failed: '.implode(' · ',$audit['errors']));
            if(!$commit){$pdo->rollBack();return['plot_id'=>$plotId,'version_no'=>(int)$version['version_no'],'change_count'=>count($report),'structural_count'=>count(array_filter($report,static fn($x)=>$x['risk']==='STRUCTURAL')),'report'=>$report,'advisories'=>$package['advisories']??[],'warnings'=>$audit['warnings']];}
            $snapshot=json_encode(['plot'=>StoryAuthoringService::story($plotId),'content'=>$audit['workspace']],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);$newNo=(int)$version['version_no']+1;
            $pdo->prepare('INSERT INTO plot_versions(plot_id,version_no,snapshot_json,submitted_by_user_id,workflow_status) VALUES(?,?,?,?,"SUBMITTED")')->execute([$plotId,$newNo,$snapshot,$admin['user_id']]);$newId=(int)$pdo->lastInsertId();
            $pdo->prepare('UPDATE plots SET workflow_status="SUBMITTED" WHERE plot_id=?')->execute([$plotId]);
            $pdo->prepare('INSERT INTO story_correction_imports(plot_id,source_plot_version_id,result_plot_version_id,imported_by_user_id,original_filename,file_type,change_count,package_json) VALUES(?,?,?,?,?,?,?,?)')->execute([$plotId,$version['plot_version_id'],$newId,$admin['user_id'],$name,'JSON',count($report),json_encode($package,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
            $pdo->commit();return['plot_id'=>$plotId,'version_id'=>$newId,'version_no'=>$newNo,'change_count'=>count($report),'structural_count'=>count(array_filter($report,static fn($x)=>$x['risk']==='STRUCTURAL')),'report'=>$report,'advisories'=>$package['advisories']??[],'warnings'=>$audit['warnings']];
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
    }

    private static function apply(PDO $pdo,int $plotId,array $change,int $line,array &$affected): array
    {
        $operation=strtolower((string)($change['operation']??'update'));$entity=strtolower((string)$change['entity']);$key=(string)$change['key'];$values=self::validatedValues($entity,$change['values'],$line);$spec=self::SPECS[$entity];
        if(in_array($entity,['option_effect','dynamic_effect'],true))return self::applyEffect($pdo,$plotId,$entity,$key,$operation,$change['expected'],$values,$line,$affected,trim((string)($change['reason']??'')));
        [$table,$pk,$id,$current]=self::locate($pdo,$plotId,$entity,$key,$line);self::assertExpected($current,$change['expected'],$line);
        $sets=[];$params=[];foreach($values as$field=>$value){$sets[]='`'.$field.'`=?';$params[]=$value;}$params[]=$id;
        $pdo->prepare('UPDATE '.$table.' SET '.implode(',',$sets).' WHERE '.$pk.'=?')->execute($params);
        if($entity==='story_value'){self::syncStoryValueAlias($pdo,$plotId,$current,$values);$affected[$current['effect_scope'].'|'.$current['target_code']]=true;}
        if($entity==='dynamic_condition'&&in_array((string)$current['source_type'],self::MANAGED_SCOPES,true)&&trim((string)$current['source_key'])!=='')$affected[$current['source_type'].'|'.$current['source_key']]=true;
        return['entity'=>$entity,'key'=>$key,'operation'=>$operation,'risk'=>$spec['risk'],'before'=>self::selectFields($current,array_keys($values)),'after'=>$values,'reason'=>trim((string)($change['reason']??''))];
    }

    private static function applyEffect(PDO $pdo,int $plotId,string $entity,string $key,string $operation,mixed $expected,array $values,int $line,array &$affected,string $reason): array
    {
        [$table,$pk,$parentColumn,$parentId,$scope,$target,$current]=self::locateEffect($pdo,$plotId,$entity,$key,$line);$before=null;
        if($operation==='upsert'){
            if($current)throw new RuntimeException('Expected no existing '.$entity.' at change '.$line.', but '.$key.' already exists. Use update with its exact expected values.');
            $row=['delta_value'=>null,'set_value'=>null,'detail_text'=>null,'sort_order'=>self::nextEffectOrder($pdo,$table,$parentColumn,$parentId),...$values];
            $pdo->prepare('INSERT INTO '.$table.'('.$parentColumn.',effect_scope,target_code,delta_value,set_value,detail_text,sort_order) VALUES(?,?,?,?,?,?,?)')->execute([$parentId,$scope,$target,$row['delta_value'],$row['set_value'],$row['detail_text'],$row['sort_order']]);$after=$row;
        }else{
            if(!$current)throw new RuntimeException('No matching '.$entity.' was found at change '.$line.'.');self::assertExpected($current,$expected,$line);$before=self::selectFields($current,array_keys($values));$sets=[];$params=[];foreach($values as$field=>$value){$sets[]='`'.$field.'`=?';$params[]=$value;}$params[]=$current[$pk];$pdo->prepare('UPDATE '.$table.' SET '.implode(',',$sets).' WHERE '.$pk.'=?')->execute($params);$after=$values;
        }
        $affected[$scope.'|'.$target]=true;return['entity'=>$entity,'key'=>$key,'operation'=>$operation,'risk'=>'STRUCTURAL','before'=>$before,'after'=>$after,'reason'=>$reason];
    }

    private static function locate(PDO $pdo,int $plotId,string $entity,string $key,int $line): array
    {
        if($entity==='plot')return self::one($pdo,'plots','plot_id','SELECT * FROM plots WHERE plot_id=?',[$plotId],$line,$entity);
        if($entity==='character')return self::one($pdo,'plot_roles','plot_role_id','SELECT * FROM plot_roles WHERE plot_id=? AND role_code=?',[$plotId,$key],$line,$entity);
        if($entity==='event')return self::one($pdo,'plot_world_events','plot_world_event_id','SELECT * FROM plot_world_events WHERE plot_id=? AND event_code=?',[$plotId,$key],$line,$entity);
        if($entity==='opportunity')return self::one($pdo,'plot_dynamic_opportunities','dynamic_opportunity_id','SELECT * FROM plot_dynamic_opportunities WHERE plot_id=? AND opportunity_code=?',[$plotId,$key],$line,$entity);
        if($entity==='story_value'){$p=explode('|',$key,2);if(count($p)!==2)return self::badKey($entity,$line);return self::one($pdo,'plot_state_definitions','plot_state_definition_id','SELECT * FROM plot_state_definitions WHERE plot_id=? AND effect_scope=? AND target_code=?',[$plotId,$p[0],$p[1]],$line,$entity);}
        if($entity==='resolution'){$p=explode('|',$key,2);if(count($p)!==2)return self::badKey($entity,$line);return self::one($pdo,'plot_resolution_fragments','resolution_fragment_id','SELECT * FROM plot_resolution_fragments WHERE plot_id=? AND section_code=? AND priority=?',[$plotId,$p[0],(int)$p[1]],$line,$entity);}
        if($entity==='decision'){$p=explode('|',$key);if(count($p)!==2)return self::badKey($entity,$line);return self::one($pdo,'plot_decision_templates','template_id','SELECT * FROM plot_decision_templates WHERE plot_id=? AND event_code=? AND role_code=?',[$plotId,$p[0],$p[1]],$line,$entity);}
        if($entity==='option'){$p=explode('|',$key);if(count($p)!==3)return self::badKey($entity,$line);return self::one($pdo,'plot_decision_template_options','template_option_id','SELECT o.* FROM plot_decision_template_options o JOIN plot_decision_templates t ON t.template_id=o.template_id WHERE t.plot_id=? AND t.event_code=? AND t.role_code=? AND o.option_code=?',[$plotId,$p[0],$p[1],$p[2]],$line,$entity);}
        if($entity==='dynamic_option'){$p=explode('|',$key);if(count($p)!==2)return self::badKey($entity,$line);return self::one($pdo,'plot_dynamic_opportunity_options','dynamic_option_id','SELECT x.* FROM plot_dynamic_opportunity_options x JOIN plot_dynamic_opportunities o ON o.dynamic_opportunity_id=x.dynamic_opportunity_id WHERE o.plot_id=? AND o.opportunity_code=? AND x.option_code=?',[$plotId,$p[0],$p[1]],$line,$entity);}
        if($entity==='dynamic_condition'){$p=explode('|',$key);if(count($p)!==4)return self::badKey($entity,$line);$sourceKey=$p[2]==='-'?null:$p[2];return self::one($pdo,'plot_dynamic_opportunity_conditions','dynamic_condition_id','SELECT c.* FROM plot_dynamic_opportunity_conditions c JOIN plot_dynamic_opportunities o ON o.dynamic_opportunity_id=c.dynamic_opportunity_id WHERE o.plot_id=? AND o.opportunity_code=? AND c.source_type=? AND ((c.source_key IS NULL AND ? IS NULL) OR c.source_key=?) AND c.sort_order=?',[$plotId,$p[0],$p[1],$sourceKey,$sourceKey,(int)$p[3]],$line,$entity);}
        throw new RuntimeException('Unsupported Corrections v2 entity at change '.$line.'.');
    }

    private static function locateEffect(PDO $pdo,int $plotId,string $entity,string $key,int $line): array
    {
        $p=explode('|',$key);$count=$entity==='option_effect'?5:4;if(count($p)!==$count)self::badKey($entity,$line);
        if($entity==='option_effect'){$q=$pdo->prepare('SELECT o.template_option_id FROM plot_decision_template_options o JOIN plot_decision_templates t ON t.template_id=o.template_id WHERE t.plot_id=? AND t.event_code=? AND t.role_code=? AND o.option_code=?');$q->execute([$plotId,$p[0],$p[1],$p[2]]);$parent=(int)$q->fetchColumn();$scope=$p[3];$target=$p[4];$table='plot_option_effects';$pk='effect_id';$parentColumn='template_option_id';}
        else{$q=$pdo->prepare('SELECT x.dynamic_option_id FROM plot_dynamic_opportunity_options x JOIN plot_dynamic_opportunities o ON o.dynamic_opportunity_id=x.dynamic_opportunity_id WHERE o.plot_id=? AND o.opportunity_code=? AND x.option_code=?');$q->execute([$plotId,$p[0],$p[1]]);$parent=(int)$q->fetchColumn();$scope=$p[2];$target=$p[3];$table='plot_dynamic_option_effects';$pk='dynamic_effect_id';$parentColumn='dynamic_option_id';}
        if(!$parent)throw new RuntimeException('The parent option for '.$key.' does not exist at change '.$line.'.');if(!in_array($scope,self::MANAGED_SCOPES,true)||!preg_match('/^[A-Z0-9_]+$/',$target))throw new RuntimeException('Invalid effect scope or target at change '.$line.'.');
        $q=$pdo->prepare('SELECT * FROM '.$table.' WHERE '.$parentColumn.'=? AND effect_scope=? AND target_code=?');$q->execute([$parent,$scope,$target]);$rows=$q->fetchAll();if(count($rows)>1)throw new RuntimeException('The effect key at change '.$line.' matches more than one record. Resolve the duplicate in Story Writer first.');
        return[$table,$pk,$parentColumn,$parent,$scope,$target,$rows[0]??null];
    }

    private static function one(PDO $pdo,string $table,string $pk,string $sql,array $args,int $line,string $entity): array
    {
        $q=$pdo->prepare($sql);$q->execute($args);$rows=$q->fetchAll();if(count($rows)!==1)throw new RuntimeException(count($rows)?'The '.$entity.' key at change '.$line.' is ambiguous.':'No matching '.$entity.' was found at change '.$line.'.');return[$table,$pk,$rows[0][$pk],$rows[0]];
    }

    private static function badKey(string $entity,int $line): never{throw new RuntimeException('Invalid '.$entity.' key at change '.$line.'. Use the exact Corrections v2 composite-key format.');}

    private static function assertExpected(array $current,array $expected,int $line): void
    {
        foreach($expected as$field=>$value){if(!array_key_exists($field,$current))throw new RuntimeException('Expected field '.$field.' does not exist at change '.$line.'.');$actual=self::comparable($field,$current[$field]);$wanted=self::comparable($field,$value);if($actual!==$wanted)throw new RuntimeException('Stale or hallucinated change '.$line.': expected '.$field.'='.self::shown($wanted).', but the story currently has '.self::shown($actual).'.');}
    }

    private static function validatedValues(string $entity,array $values,int $line): array
    {
        $out=[];foreach($values as$field=>$value){
            if($field==='trigger_percent'){if(!is_numeric($value))throw new RuntimeException('trigger_percent must be a number at change '.$line.'.');$value=(float)$value;if($value<0||$value>100)throw new RuntimeException('trigger_percent must be between 0 and 100 at change '.$line.'.');$out[$field]=$value;continue;}
            if(in_array($field,['sort_order','decision_window_seconds','window_seconds','npc_weight','default_numeric_value','delta_value'],true)){
                if($value===null&&in_array($field,['default_numeric_value','delta_value'],true)){$out[$field]=null;continue;}if(!is_int($value)&&!(is_string($value)&&preg_match('/^-?\d+$/',$value)))throw new RuntimeException($field.' must be a whole number at change '.$line.'.');$value=(int)$value;
                if(in_array($field,['decision_window_seconds','window_seconds'],true)&&($value<5||$value>86400))throw new RuntimeException($field.' must be between 5 and 86,400 at change '.$line.'.');if($field==='npc_weight'&&($value<0||$value>10000))throw new RuntimeException('npc_weight must be between 0 and 10,000 at change '.$line.'.');if($field==='sort_order'&&abs($value)>1000000)throw new RuntimeException('sort_order is outside the supported range at change '.$line.'.');$out[$field]=$value;continue;
            }
            if($field==='pronoun_set'){$value=strtoupper(trim((string)$value));if(!in_array($value,['HE','SHE','IT'],true))throw new RuntimeException('pronoun_set must be HE, SHE, or IT at change '.$line.'.');}
            if($field==='value_type'){$value=strtoupper(trim((string)$value));if(!in_array($value,['NUMERIC','PRESET','TEXT'],true))throw new RuntimeException('value_type must be NUMERIC, PRESET, or TEXT at change '.$line.'.');}
            if($field==='operator'){$value=strtoupper(trim((string)$value));if(!in_array($value,self::OPERATORS,true))throw new RuntimeException('Unsupported condition operator at change '.$line.'.');}
            if($field==='allowed_values_text')$value=self::normalizeAllowed((string)$value);
            if(in_array($field,['set_value','detail_text','rationale_text','description','private_knowledge','fear_text'],true)&&$value!==null){$value=trim((string)$value);if($value==='')$value=null;}
            if(is_string($value)&&mb_strlen($value)>200000)throw new RuntimeException('A correction value is too long at change '.$line.'.');$out[$field]=$value;
        }return$out;
    }

    private static function syncStoryValueAlias(PDO $pdo,int $plotId,array $current,array $values): void
    {
        if(!array_key_exists('allowed_values_text',$values)&&!array_key_exists('label_text',$values)&&!array_key_exists('value_type',$values)&&!array_key_exists('default_numeric_value',$values))return;
        $q=$pdo->prepare('SELECT p.property_id,p.initial_value FROM plot_state_aliases a JOIN plot_entities e ON e.plot_id=a.plot_id AND e.entity_code=a.entity_code JOIN plot_entity_properties p ON p.plot_entity_id=e.plot_entity_id AND p.property_code=a.property_code WHERE a.plot_id=? AND a.state_key=? LIMIT 1');$q->execute([$plotId,$current['target_code']]);$property=$q->fetch();if(!$property)return;
        $sets=[];$args=[];if(array_key_exists('allowed_values_text',$values)){$allowed=array_values(array_filter(explode("\n",(string)$values['allowed_values_text']),static fn($x)=>$x!==''));if($property['initial_value']!==null&&!in_array((string)$property['initial_value'],$allowed,true))throw new RuntimeException('Allowed values for '.$current['target_code'].' must retain its runtime initial value '.$property['initial_value'].'.');$sets[]='allowed_values=?';$args[]=implode(',',$allowed);}if(array_key_exists('label_text',$values)){$sets[]='label=?';$args[]=$values['label_text'];}if(array_key_exists('value_type',$values)){$sets[]='value_type=?';$args[]=$values['value_type']==='NUMERIC'?'NUMBER':'TEXT';}if(array_key_exists('default_numeric_value',$values)&&$values['default_numeric_value']!==null){$sets[]='initial_value=?';$args[]=(string)$values['default_numeric_value'];}
        if($sets){$args[]=$property['property_id'];$pdo->prepare('UPDATE plot_entity_properties SET '.implode(',',$sets).' WHERE property_id=?')->execute($args);}
    }

    private static function validateChronology(int $plotId,array $before): void
    {
        $q=db()->prepare('SELECT event_code,trigger_percent,sort_order,phase FROM plot_world_events WHERE plot_id=? AND active=1 ORDER BY trigger_percent,sort_order,plot_world_event_id');$q->execute([$plotId]);$rows=$q->fetchAll();$after=array_column($rows,'event_code');if($after!==$before)throw new RuntimeException('Chronology protection blocked this package because it changes the existing stable event sequence. Timing may change, but event order may not.');
        $rank=['EXPOSITION'=>1,'INCITING_INCIDENT'=>2,'RISING_ACTION'=>3,'CLIMAX'=>4,'RESOLUTION'=>5];$last=0;$pairs=[];foreach($rows as$row){$pair=$row['trigger_percent'].'|'.$row['sort_order'];if(isset($pairs[$pair]))throw new RuntimeException('Two active events would share the same time and same-time order: '.$pairs[$pair].' and '.$row['event_code'].'.');$pairs[$pair]=$row['event_code'];$r=$rank[$row['phase']]??0;if($r<$last)throw new RuntimeException('Chronology protection detected a phase moving backward at '.$row['event_code'].'.');$last=$r;}
    }

    private static function validateAffectedValues(int $plotId,array $affected): void
    {
        if(!$affected)return;$q=db()->prepare('SELECT * FROM plot_state_definitions WHERE plot_id=?');$q->execute([$plotId]);$definitions=[];foreach($q->fetchAll()as$d)$definitions[$d['effect_scope'].'|'.$d['target_code']]=$d;
        foreach(array_keys($affected)as$key){$d=$definitions[$key]??null;if(!$d)throw new RuntimeException('A structural correction references unmanaged Story Value '.$key.'.');[$scope,$code]=explode('|',$key,2);$allowed=self::allowed((string)$d['allowed_values_text']);
            foreach(self::effectsFor($plotId,$scope,$code)as$effect){if($d['value_type']==='NUMERIC'&&$effect['delta_value']===null)throw new RuntimeException($key.' has an effect without Change By.');if($d['value_type']!=='NUMERIC'&&$effect['set_value']===null)throw new RuntimeException($key.' has an effect without Set To.');if($d['value_type']==='PRESET'&&!in_array((string)$effect['set_value'],$allowed,true))throw new RuntimeException($key.' effect uses disallowed value '.$effect['set_value'].'.');}
            foreach(self::conditionsFor($plotId,$scope,$code)as$c)self::validateConditionValue($d,(string)$c['operator'],$c['compare_value'],$key);
        }
    }

    private static function effectsFor(int $plotId,string $scope,string $code): array
    {
        $q=db()->prepare('SELECT e.delta_value,e.set_value FROM plot_option_effects e JOIN plot_decision_template_options x ON x.template_option_id=e.template_option_id JOIN plot_decision_templates t ON t.template_id=x.template_id WHERE t.plot_id=? AND e.active=1 AND x.active=1 AND e.effect_scope=? AND e.target_code=? UNION ALL SELECT e.delta_value,e.set_value FROM plot_dynamic_option_effects e JOIN plot_dynamic_opportunity_options x ON x.dynamic_option_id=e.dynamic_option_id JOIN plot_dynamic_opportunities o ON o.dynamic_opportunity_id=x.dynamic_opportunity_id WHERE o.plot_id=? AND o.active=1 AND x.active=1 AND e.active=1 AND e.effect_scope=? AND e.target_code=?');$q->execute([$plotId,$scope,$code,$plotId,$scope,$code]);return$q->fetchAll();
    }

    private static function conditionsFor(int $plotId,string $scope,string $code): array
    {
        $pdo=db();$q=$pdo->prepare('SELECT c.operator,c.compare_value FROM plot_dynamic_opportunity_conditions c JOIN plot_dynamic_opportunities o ON o.dynamic_opportunity_id=c.dynamic_opportunity_id WHERE o.plot_id=? AND c.source_type=? AND c.source_key=?');$q->execute([$plotId,$scope,$code]);$rows=$q->fetchAll();if($scope==='WORLD_STATE'||$scope==='FACTOR'){$r=$pdo->prepare('SELECT c.operator,c.compare_value FROM plot_resolution_conditions c JOIN plot_resolution_fragments f ON f.resolution_fragment_id=c.resolution_fragment_id WHERE f.plot_id=? AND c.state_key=?');$r->execute([$plotId,$code]);$rows=array_merge($rows,$r->fetchAll());}return$rows;
    }

    private static function validateConditionValue(array $d,string $operator,?string $compare,string $key): void
    {
        if(in_array($operator,['EXISTS','NOT_EXISTS'],true))return;if($compare===null||trim($compare)==='')throw new RuntimeException($key.' has '.$operator.' without a comparison value.');if($d['value_type']==='NUMERIC'){if(!in_array($operator,['EQ','NEQ','LT','LTE','GT','GTE'],true)||!is_numeric($compare))throw new RuntimeException($key.' has a non-numeric condition value.');return;}if(in_array($operator,['LT','LTE','GT','GTE'],true))throw new RuntimeException($key.' uses a numerical operator for a non-numerical value.');if($d['value_type']==='PRESET'){foreach(array_map('trim',explode(',',$compare))as$v)if(!in_array($v,self::allowed((string)$d['allowed_values_text']),true))throw new RuntimeException($key.' condition uses disallowed value '.$v.'.');}
    }

    private static function eventOrder(int $plotId): array{$q=db()->prepare('SELECT event_code FROM plot_world_events WHERE plot_id=? AND active=1 ORDER BY sort_order,plot_world_event_id');$q->execute([$plotId]);return array_column($q->fetchAll(),'event_code');}
    private static function nextEffectOrder(PDO $pdo,string $table,string $parentColumn,int $parentId): int{$q=$pdo->prepare('SELECT COALESCE(MAX(sort_order),0)+1 FROM '.$table.' WHERE '.$parentColumn.'=?');$q->execute([$parentId]);return(int)$q->fetchColumn();}
    private static function selectFields(array $row,array $fields): array{$out=[];foreach($fields as$f)$out[$f]=$row[$f]??null;return$out;}
    private static function comparable(string $field,mixed $value): mixed{if($field==='allowed_values_text'&&$value!==null)return self::normalizeAllowed((string)$value);if($field==='trigger_percent'&&$value!==null)return(float)$value;if(in_array($field,['sort_order','decision_window_seconds','window_seconds','npc_weight','default_numeric_value','delta_value'],true)&&$value!==null)return(int)$value;return$value;}
    private static function shown(mixed $value): string{return$value===null?'null':json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}
    private static function normalizeAllowed(string $value): string{$parts=preg_split('/[\r\n,]+/',$value)?:[];$parts=array_values(array_unique(array_filter(array_map(static fn($x)=>trim((string)$x),$parts),static fn($x)=>$x!=='')));return implode("\n",$parts);}
    private static function allowed(string $value): array{return array_values(array_filter(explode("\n",self::normalizeAllowed($value)),static fn($x)=>$x!==''));}
    private static function onlyKeys(array $data,array $allowed,string $where): void{$unknown=array_diff(array_keys($data),$allowed);if($unknown)throw new RuntimeException('Unknown field '.reset($unknown).' in '.$where.'. Corrections v2 rejects undeclared model output.');}
}
