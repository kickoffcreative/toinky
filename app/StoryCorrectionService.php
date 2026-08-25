<?php
final class StoryCorrectionService
{
    private const MAX_BYTES=2097152;
    private const FIELDS=[
        'plot'=>['table'=>'plots','fields'=>['title','archetype','setting_name','scenario_text','max_players','max_npcs']],
        'character'=>['table'=>'plot_roles','fields'=>['name','public_description','private_knowledge','starting_location','goal_text','fear_text','pronoun_set','simulation_active','slot_no']],
        'event'=>['table'=>'plot_world_events','fields'=>['headline','event_text','event_second','phase','active','sort_order']],
        'decision'=>['table'=>'plot_decision_templates','fields'=>['prompt_text','decision_window_seconds','actor_type']],
        'option'=>['table'=>'plot_decision_template_options','fields'=>['label','result_text','sort_order']],
        'opportunity'=>['table'=>'plot_dynamic_opportunities','fields'=>['prompt_text','window_seconds','once_only','priority','active']],
        'dynamic_option'=>['table'=>'plot_dynamic_opportunity_options','fields'=>['label','result_text','sort_order']],
        'resolution'=>['table'=>'plot_resolution_fragments','fields'=>['fragment_text','section_order','priority','active']],
    ];

    public static function import(array $admin,int $versionId,array $file): array
    {
        if((int)($admin['is_admin']??0)!==1)throw new RuntimeException('Administrator access is required.');
        if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new RuntimeException('Choose a JSON or CSV correction package.');
        $size=(int)($file['size']??0);if($size<1||$size>self::MAX_BYTES)throw new RuntimeException('Correction packages must be 2 MB or smaller.');
        $name=(string)($file['name']??'corrections');$ext=strtolower(pathinfo($name,PATHINFO_EXTENSION));if(!in_array($ext,['json','csv'],true))throw new RuntimeException('Upload a .json or .csv correction package.');
        $raw=file_get_contents((string)$file['tmp_name']);if($raw===false)throw new RuntimeException('The correction package could not be read.');
        $package=$ext==='json'?self::parseJson($raw):self::parseCsv($raw);$changes=$package['changes'];if(!$changes)throw new RuntimeException('The correction package contains no changes.');if(count($changes)>500)throw new RuntimeException('A correction package may contain at most 500 changes.');
        $pdo=db();$q=$pdo->prepare('SELECT v.*,p.code,p.title FROM plot_versions v JOIN plots p ON p.plot_id=v.plot_id WHERE v.plot_version_id=?');$q->execute([$versionId]);$version=$q->fetch();if(!$version)throw new RuntimeException('Submitted revision not found.');
        $q=$pdo->prepare('SELECT plot_version_id,version_no FROM plot_versions WHERE plot_id=? ORDER BY version_no DESC LIMIT 1');$q->execute([$version['plot_id']]);$latest=$q->fetch();if((int)$latest['plot_version_id']!==$versionId)throw new RuntimeException('Corrections can only be imported into the latest story revision.');
        if(!empty($package['plot_code'])&&strtoupper((string)$package['plot_code'])!==strtoupper((string)$version['code']))throw new RuntimeException('The correction package targets a different story.');
        if(!empty($package['base_version'])&&(int)$package['base_version']!==(int)$version['version_no'])throw new RuntimeException('The correction package was created for a different revision.');
        $pdo->beginTransaction();try{
            foreach($changes as$i=>$change)self::applyChange($pdo,(int)$version['plot_id'],$change,$i+1);
            $audit=StoryAuthoringService::audit((int)$version['plot_id']);$snapshot=json_encode(['plot'=>StoryAuthoringService::story((int)$version['plot_id']),'content'=>$audit['workspace']],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);$newNo=(int)$version['version_no']+1;
            $pdo->prepare('INSERT INTO plot_versions(plot_id,version_no,snapshot_json,submitted_by_user_id,workflow_status) VALUES(?,?,?,?,"SUBMITTED")')->execute([$version['plot_id'],$newNo,$snapshot,$admin['user_id']]);$newId=(int)$pdo->lastInsertId();
            $pdo->prepare('UPDATE plots SET workflow_status="SUBMITTED" WHERE plot_id=?')->execute([$version['plot_id']]);
            $pdo->prepare('INSERT INTO story_correction_imports(plot_id,source_plot_version_id,result_plot_version_id,imported_by_user_id,original_filename,file_type,change_count,package_json) VALUES(?,?,?,?,?,?,?,?)')->execute([$version['plot_id'],$versionId,$newId,$admin['user_id'],$name,strtoupper($ext),count($changes),json_encode($package,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
            $pdo->commit();return['plot_id'=>(int)$version['plot_id'],'version_id'=>$newId,'version_no'=>$newNo,'change_count'=>count($changes),'errors'=>$audit['errors'],'warnings'=>$audit['warnings']];
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
    }

    private static function parseJson(string $raw): array
    {
        try{$data=json_decode($raw,true,512,JSON_THROW_ON_ERROR);}catch(Throwable $e){throw new RuntimeException('The JSON correction package is invalid: '.$e->getMessage());}
        if(array_is_list($data))$data=['changes'=>$data];if(!is_array($data)||!is_array($data['changes']??null))throw new RuntimeException('JSON must contain a changes array.');if(isset($data['format'])&&$data['format']!=='toinky-story-corrections-v1')throw new RuntimeException('Unsupported correction-package format.');return$data;
    }

    private static function parseCsv(string $raw): array
    {
        $stream=fopen('php://temp','r+');fwrite($stream,$raw);rewind($stream);$headers=fgetcsv($stream);if(!$headers)throw new RuntimeException('The CSV correction package is empty.');$headers=array_map(static fn($x)=>strtolower(trim((string)$x)),$headers);foreach(['entity','key','field','value']as$required)if(!in_array($required,$headers,true))throw new RuntimeException('CSV headers must include entity, key, field, and value.');$changes=[];while(($row=fgetcsv($stream))!==false){if(count(array_filter($row,static fn($x)=>trim((string)$x)!==''))===0)continue;$row=array_pad($row,count($headers),'');$item=array_combine($headers,array_slice($row,0,count($headers)));$changes[]=['entity'=>$item['entity'],'key'=>$item['key'],'field'=>$item['field'],'value'=>$item['value']];}fclose($stream);return['changes'=>$changes];
    }

    private static function applyChange(PDO $pdo,int $plotId,array $change,int $line): void
    {
        $entity=strtolower(trim((string)($change['entity']??'')));$key=trim((string)($change['key']??''));$field=strtolower(trim((string)($change['field']??'')));$value=$change['value']??null;if(!is_scalar($value)&&$value!==null)throw new RuntimeException('Correction values must be scalar or null at change '.$line.'.');$spec=self::FIELDS[$entity]??null;if(!$spec||!in_array($field,$spec['fields'],true))throw new RuntimeException('Unsupported entity or field at change '.$line.'.');if($key==='')throw new RuntimeException('Missing key at change '.$line.'.');
        [$where,$params]=self::locator($pdo,$plotId,$entity,$key,$line);$sql='UPDATE '.$spec['table'].' SET `'.$field.'`=? WHERE '.$where;$q=$pdo->prepare($sql);$q->execute(array_merge([$value],$params));if($q->rowCount()<1){$check=$pdo->prepare('SELECT 1 FROM '.$spec['table'].' WHERE '.$where);$check->execute($params);if(!$check->fetchColumn())throw new RuntimeException('No matching '.$entity.' was found at change '.$line.'.');}
    }

    private static function locator(PDO $pdo,int $plotId,string $entity,string $key,int $line): array
    {
        if($entity==='plot')return['plot_id=?',[$plotId]];
        if($entity==='character')return['plot_id=? AND role_code=?',[$plotId,$key]];
        if($entity==='event')return['plot_id=? AND event_code=?',[$plotId,$key]];
        if($entity==='opportunity')return['plot_id=? AND opportunity_code=?',[$plotId,$key]];
        if($entity==='resolution'){$parts=explode('|',$key,2);if(count($parts)!==2)throw new RuntimeException('Resolution keys must be SECTION_CODE|PRIORITY at change '.$line.'.');return['plot_id=? AND section_code=? AND priority=?',[$plotId,$parts[0],(int)$parts[1]]];}
        $parts=explode('|',$key);if($entity==='decision'){if(count($parts)!==2)throw new RuntimeException('Decision keys must be EVENT_CODE|ROLE_CODE at change '.$line.'.');return['plot_id=? AND event_code=? AND role_code=?',[$plotId,$parts[0],$parts[1]]];}
        if($entity==='option'){if(count($parts)!==3)throw new RuntimeException('Option keys must be EVENT_CODE|ROLE_CODE|OPTION_CODE at change '.$line.'.');$q=$pdo->prepare('SELECT o.template_option_id FROM plot_decision_template_options o JOIN plot_decision_templates d ON d.template_id=o.template_id WHERE d.plot_id=? AND d.event_code=? AND d.role_code=? AND o.option_code=?');$q->execute([$plotId,$parts[0],$parts[1],$parts[2]]);$id=(int)$q->fetchColumn();if(!$id)throw new RuntimeException('No matching option was found at change '.$line.'.');return['template_option_id=?',[$id]];}
        if($entity==='dynamic_option'){if(count($parts)!==2)throw new RuntimeException('Dynamic option keys must be OPPORTUNITY_CODE|OPTION_CODE at change '.$line.'.');$q=$pdo->prepare('SELECT x.dynamic_option_id FROM plot_dynamic_opportunity_options x JOIN plot_dynamic_opportunities o ON o.dynamic_opportunity_id=x.dynamic_opportunity_id WHERE o.plot_id=? AND o.opportunity_code=? AND x.option_code=?');$q->execute([$plotId,$parts[0],$parts[1]]);$id=(int)$q->fetchColumn();if(!$id)throw new RuntimeException('No matching dynamic option was found at change '.$line.'.');return['dynamic_option_id=?',[$id]];}
        throw new RuntimeException('Unsupported correction entity at change '.$line.'.');
    }
}
