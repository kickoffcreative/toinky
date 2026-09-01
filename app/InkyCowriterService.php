<?php
require_once __DIR__.'/StoryAuthoringService.php';
require_once __DIR__.'/AiConnectionService.php';
require_once __DIR__.'/UserProfileService.php';

final class InkyCowriterService
{
    public static function run(array $writer,int $plotId,string $action): array
    {
        if(!StoryAuthoringService::canEdit($writer,$plotId))throw new RuntimeException('You may only ask Inky to review stories you can edit.');$displayName=UserProfileService::inkyDisplayName(UserProfileService::inkyPreferences((int)$writer['user_id']));
        return match($action){'review_plot'=>self::reviewPlot($plotId,$displayName),'whats_next'=>self::whatsNext($plotId,$displayName),'quality_check'=>self::qualityCheck($plotId,$displayName),'dependencies'=>self::dependencyCheck($plotId,$displayName),'character_knowledge'=>self::characterKnowledgeCheck($plotId,$displayName),default=>throw new RuntimeException('That Inky action is not available yet.')};
    }

    private static function reviewPlot(int $plotId,string $displayName): array
    {
        $audit=StoryAuthoringService::audit($plotId);
        $system='You are '.$displayName.', a concise co-writer reviewing an authored interactive story. Use only the supplied story package. Separate observed evidence from inference. Every finding and recommendation must cite a named stable key from the package. Review immersion, viewpoint continuity, creative prose, character agency, branch causality, dependency coverage, reachability, pacing, replayability, mobile readability, and whether each character receives enough non-privileged information for meaningful choices. Never recommend plot-specific PHP. Do not propose chronology or structural-order changes unless the supplied evidence demonstrates a defect; label any such recommendation MANUAL STRUCTURAL REVIEW. Prefer prose and authored-data corrections. Unobserved simulation options are not proof of unreachability. Return JSON only with this exact shape: {"summary":"one concise paragraph","observed_evidence":[{"stable_key":"KEY","finding":"fact directly supported by the package"}],"inferences":[{"stable_key":"KEY","inference":"clearly labeled interpretation"}],"recommendations":[{"stable_key":"KEY","category":"one review category","recommendation":"specific copy-ready improvement","manual_structural_review":false}]}. Keep each item short enough to read on a phone. Return no markdown and no additional keys.';
        $response=AiConnectionService::completeJson('INKY_REVIEW_PLOT',$system,$audit['text']);$review=self::validatedReview((array)$response['data']);return['action'=>'review_plot','display_name'=>$displayName,'model'=>$response['model'],'review'=>$review,'automated_findings'=>['errors'=>count($audit['errors']),'warnings'=>count($audit['warnings'])]];
    }

    private static function whatsNext(int $plotId,string $displayName): array
    {
        $audit=StoryAuthoringService::audit($plotId);
        $system='You are '.$displayName.', a concise co-writer helping an author decide the next best Story Writer tasks. Use only the supplied story package. Return advice only; do not rewrite the story or claim changes were made. Every step must cite one named stable key from the package. Prioritize blocking validation errors, then missing authored data, then high-value prose or dependency improvements. Separate direct evidence from inference inside the reason text. Never recommend plot-specific PHP. Do not propose chronology or structural-order changes unless the supplied evidence demonstrates a defect; label any such step MANUAL STRUCTURAL REVIEW. Prefer prose and authored-data corrections. Return JSON only with this exact shape: {"summary":"one concise paragraph","steps":[{"stable_key":"KEY","priority":"BLOCKER|HIGH|MEDIUM|LOW","action":"specific next authoring task","reason":"brief observed evidence or clearly labeled inference","manual_structural_review":false}]}. Return 3 to 6 steps, keep each item short enough to read on a phone, and return no markdown or additional keys.';
        $response=AiConnectionService::completeJson('INKY_WHATS_NEXT',$system,$audit['text']);$next=self::validatedNextSteps((array)$response['data']);return['action'=>'whats_next','display_name'=>$displayName,'model'=>$response['model'],'next_steps'=>$next,'automated_findings'=>['errors'=>count($audit['errors']),'warnings'=>count($audit['warnings'])]];
    }

    private static function qualityCheck(int $plotId,string $displayName): array
    {
        $audit=StoryAuthoringService::audit($plotId);
        $system='You are '.$displayName.', a concise co-writer performing a quality check on authored interactive-story data. Use only the supplied story package. Return advice only; do not rewrite the story or claim changes were made. Score only these categories: immersion, viewpoint_continuity, character_agency, branch_causality, dependency_coverage, pacing, replayability, mobile_readability. Each category must cite a named stable key from the package, distinguish observed evidence from inference in the note, and include one specific improvement. Never recommend plot-specific PHP. Do not propose chronology or structural-order changes unless the supplied evidence demonstrates a defect; label that item MANUAL STRUCTURAL REVIEW. Return JSON only with this exact shape: {"summary":"one concise paragraph","checks":[{"category":"immersion","score":1,"stable_key":"KEY","note":"observed evidence or labeled inference","improvement":"specific authoring improvement","manual_structural_review":false}]}. Scores are integers from 1 to 5. Return no markdown and no additional keys.';
        $response=AiConnectionService::completeJson('INKY_QUALITY_CHECK',$system,$audit['text']);$quality=self::validatedQualityCheck((array)$response['data']);return['action'=>'quality_check','display_name'=>$displayName,'model'=>$response['model'],'quality_check'=>$quality,'automated_findings'=>['errors'=>count($audit['errors']),'warnings'=>count($audit['warnings'])]];
    }

    private static function dependencyCheck(int $plotId,string $displayName): array
    {
        $audit=StoryAuthoringService::audit($plotId);
        $system='You are '.$displayName.', a concise co-writer finding dependency problems in authored interactive-story data. Use only the supplied story package. Return advice only; do not rewrite the story or claim changes were made. Focus on broken or weak authored dependencies: missing active choices, missing effects, missing conditions, inactive referenced entities, undefined managed story values, unreachable endings, dependency gaps between choices and outcomes, and player-visible engine leakage. Every problem must cite a named stable key from the package and include observed evidence or clearly labeled inference. Never recommend plot-specific PHP. Do not propose chronology or structural-order changes unless the supplied evidence demonstrates a defect; label that item MANUAL STRUCTURAL REVIEW. Return JSON only with this exact shape: {"summary":"one concise paragraph","problems":[{"stable_key":"KEY","severity":"BLOCKER|HIGH|MEDIUM|LOW","problem":"specific dependency issue","evidence":"observed evidence or labeled inference","fix":"specific authored-data fix","manual_structural_review":false}]}. Return no markdown and no additional keys.';
        $response=AiConnectionService::completeJson('INKY_DEPENDENCY_CHECK',$system,$audit['text']);$dependencies=self::validatedDependencyCheck((array)$response['data']);return['action'=>'dependencies','display_name'=>$displayName,'model'=>$response['model'],'dependency_check'=>$dependencies,'automated_findings'=>['errors'=>count($audit['errors']),'warnings'=>count($audit['warnings'])]];
    }

    private static function characterKnowledgeCheck(int $plotId,string $displayName): array
    {
        $audit=StoryAuthoringService::audit($plotId);
        $system='You are '.$displayName.', a concise co-writer checking character knowledge and viewpoint continuity in authored interactive-story data. Use only the supplied story package. Return advice only; do not rewrite the story or claim changes were made. Focus on whether each character has enough non-privileged information for meaningful choices, whether prompts or results expose private knowledge they should not have, whether private knowledge is authored clearly, and whether choices align with stated goals, fears, location, and known facts. Every issue must cite a named stable key from the package and include observed evidence or clearly labeled inference. Never recommend plot-specific PHP. Prefer authored-data and prose fixes. Return JSON only with this exact shape: {"summary":"one concise paragraph","issues":[{"role_code":"ROLE","stable_key":"KEY","severity":"BLOCKER|HIGH|MEDIUM|LOW","issue":"specific knowledge or viewpoint issue","evidence":"observed evidence or labeled inference","fix":"specific authored-data or prose fix"}]}. Return no markdown and no additional keys.';
        $response=AiConnectionService::completeJson('INKY_CHARACTER_KNOWLEDGE',$system,$audit['text']);$knowledge=self::validatedCharacterKnowledge((array)$response['data']);return['action'=>'character_knowledge','display_name'=>$displayName,'model'=>$response['model'],'character_knowledge'=>$knowledge,'automated_findings'=>['errors'=>count($audit['errors']),'warnings'=>count($audit['warnings'])]];
    }

    private static function validatedReview(array $input): array
    {
        $summary=trim((string)($input['summary']??''));if($summary==='')throw new RuntimeException('Inky’s review did not include a summary.');
        $observed=self::items($input['observed_evidence']??null,'finding',false);$inferences=self::items($input['inferences']??null,'inference',false);$recommendations=self::items($input['recommendations']??null,'recommendation',true);if(!$observed||!$recommendations)throw new RuntimeException('Inky’s review did not include the required evidence and recommendations.');
        return['summary'=>$summary,'observed_evidence'=>$observed,'inferences'=>$inferences,'recommendations'=>$recommendations];
    }

    private static function validatedNextSteps(array $input): array
    {
        $summary=trim((string)($input['summary']??''));if($summary==='')throw new RuntimeException('Inky’s next-step guidance did not include a summary.');
        $steps=self::nextStepItems($input['steps']??null);if(!$steps)throw new RuntimeException('Inky’s next-step guidance did not include usable steps.');
        return['summary'=>$summary,'steps'=>$steps];
    }

    private static function validatedQualityCheck(array $input): array
    {
        $summary=trim((string)($input['summary']??''));if($summary==='')throw new RuntimeException('Inky’s quality check did not include a summary.');
        $checks=self::qualityCheckItems($input['checks']??null);if(!$checks)throw new RuntimeException('Inky’s quality check did not include usable checks.');
        return['summary'=>$summary,'checks'=>$checks];
    }

    private static function validatedDependencyCheck(array $input): array
    {
        $summary=trim((string)($input['summary']??''));if($summary==='')throw new RuntimeException('Inky’s dependency check did not include a summary.');
        $problems=self::dependencyItems($input['problems']??null);if(!$problems)throw new RuntimeException('Inky’s dependency check did not include usable problems.');
        return['summary'=>$summary,'problems'=>$problems];
    }

    private static function validatedCharacterKnowledge(array $input): array
    {
        $summary=trim((string)($input['summary']??''));if($summary==='')throw new RuntimeException('Inky’s character-knowledge check did not include a summary.');
        $issues=self::characterKnowledgeItems($input['issues']??null);if(!$issues)throw new RuntimeException('Inky’s character-knowledge check did not include usable issues.');
        return['summary'=>$summary,'issues'=>$issues];
    }

    private static function items(mixed $rows,string $textKey,bool $recommendation): array
    {
        if(!is_array($rows))return[];$out=[];foreach(array_slice($rows,0,10)as$row){if(!is_array($row))continue;$key=trim((string)($row['stable_key']??''));$text=trim((string)($row[$textKey]??''));if($key===''||$text==='')continue;$item=['stable_key'=>$key,$textKey=>$text];if($recommendation){$item['category']=trim((string)($row['category']??'Story quality'));$item['manual_structural_review']=!empty($row['manual_structural_review']);}$out[]=$item;}return$out;
    }

    private static function nextStepItems(mixed $rows): array
    {
        if(!is_array($rows))return[];$priorities=['BLOCKER','HIGH','MEDIUM','LOW'];$out=[];foreach($rows as$row){if(!is_array($row))continue;$key=trim((string)($row['stable_key']??''));$action=trim((string)($row['action']??''));$reason=trim((string)($row['reason']??''));if($key===''||$action===''||$reason==='')continue;$priority=strtoupper(trim((string)($row['priority']??'MEDIUM')));if(!in_array($priority,$priorities,true))$priority='MEDIUM';$out[]=['stable_key'=>$key,'priority'=>$priority,'action'=>$action,'reason'=>$reason,'manual_structural_review'=>!empty($row['manual_structural_review'])];if(count($out)>=6)break;}return$out;
    }

    private static function qualityCheckItems(mixed $rows): array
    {
        if(!is_array($rows))return[];$allowed=['immersion','viewpoint_continuity','character_agency','branch_causality','dependency_coverage','pacing','replayability','mobile_readability'];$out=[];foreach($rows as$row){if(!is_array($row))continue;$category=strtolower(trim((string)($row['category']??'')));$key=trim((string)($row['stable_key']??''));$note=trim((string)($row['note']??''));$improvement=trim((string)($row['improvement']??''));if(!in_array($category,$allowed,true)||$key===''||$note===''||$improvement==='')continue;$score=max(1,min(5,(int)($row['score']??3)));$out[]=['category'=>$category,'score'=>$score,'stable_key'=>$key,'note'=>$note,'improvement'=>$improvement,'manual_structural_review'=>!empty($row['manual_structural_review'])];if(count($out)>=8)break;}return$out;
    }

    private static function dependencyItems(mixed $rows): array
    {
        if(!is_array($rows))return[];$severities=['BLOCKER','HIGH','MEDIUM','LOW'];$out=[];foreach($rows as$row){if(!is_array($row))continue;$key=trim((string)($row['stable_key']??''));$problem=trim((string)($row['problem']??''));$evidence=trim((string)($row['evidence']??''));$fix=trim((string)($row['fix']??''));if($key===''||$problem===''||$evidence===''||$fix==='')continue;$severity=strtoupper(trim((string)($row['severity']??'MEDIUM')));if(!in_array($severity,$severities,true))$severity='MEDIUM';$out[]=['stable_key'=>$key,'severity'=>$severity,'problem'=>$problem,'evidence'=>$evidence,'fix'=>$fix,'manual_structural_review'=>!empty($row['manual_structural_review'])];if(count($out)>=8)break;}return$out;
    }

    private static function characterKnowledgeItems(mixed $rows): array
    {
        if(!is_array($rows))return[];$severities=['BLOCKER','HIGH','MEDIUM','LOW'];$out=[];foreach($rows as$row){if(!is_array($row))continue;$role=self::roleCode((string)($row['role_code']??''));$key=trim((string)($row['stable_key']??''));$issue=trim((string)($row['issue']??''));$evidence=trim((string)($row['evidence']??''));$fix=trim((string)($row['fix']??''));if($role===''||$key===''||$issue===''||$evidence===''||$fix==='')continue;$severity=strtoupper(trim((string)($row['severity']??'MEDIUM')));if(!in_array($severity,$severities,true))$severity='MEDIUM';$out[]=['role_code'=>$role,'stable_key'=>$key,'severity'=>$severity,'issue'=>$issue,'evidence'=>$evidence,'fix'=>$fix];if(count($out)>=8)break;}return$out;
    }

    private static function roleCode(string $value): string
    {
        return trim(strtoupper((string)preg_replace('/[^A-Za-z0-9]+/','_',trim($value))),'_');
    }
}
