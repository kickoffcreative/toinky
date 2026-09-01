<?php
require_once __DIR__.'/StoryAuthoringService.php';
require_once __DIR__.'/AiConnectionService.php';

final class InkyCowriterService
{
    public static function run(array $writer,int $plotId,string $action): array
    {
        if(!StoryAuthoringService::canEdit($writer,$plotId))throw new RuntimeException('You may only ask Inky to review stories you can edit.');
        return match($action){'review_plot'=>self::reviewPlot($plotId),default=>throw new RuntimeException('That Inky action is not available yet.')};
    }

    private static function reviewPlot(int $plotId): array
    {
        $audit=StoryAuthoringService::audit($plotId);
        $system='You are Inky, a concise co-writer reviewing an authored interactive story. Use only the supplied story package. Separate observed evidence from inference. Every finding and recommendation must cite a named stable key from the package. Review immersion, viewpoint continuity, creative prose, character agency, branch causality, dependency coverage, reachability, pacing, replayability, mobile readability, and whether each character receives enough non-privileged information for meaningful choices. Never recommend plot-specific PHP. Do not propose chronology or structural-order changes unless the supplied evidence demonstrates a defect; label any such recommendation MANUAL STRUCTURAL REVIEW. Prefer prose and authored-data corrections. Unobserved simulation options are not proof of unreachability. Return JSON only with this exact shape: {"summary":"one concise paragraph","observed_evidence":[{"stable_key":"KEY","finding":"fact directly supported by the package"}],"inferences":[{"stable_key":"KEY","inference":"clearly labeled interpretation"}],"recommendations":[{"stable_key":"KEY","category":"one review category","recommendation":"specific copy-ready improvement","manual_structural_review":false}]}. Keep each item short enough to read on a phone. Return no markdown and no additional keys.';
        $response=AiConnectionService::completeJson('INKY_REVIEW_PLOT',$system,$audit['text']);$review=self::validatedReview((array)$response['data']);return['action'=>'review_plot','model'=>$response['model'],'review'=>$review,'automated_findings'=>['errors'=>count($audit['errors']),'warnings'=>count($audit['warnings'])]];
    }

    private static function validatedReview(array $input): array
    {
        $summary=trim((string)($input['summary']??''));if($summary==='')throw new RuntimeException('Inky’s review did not include a summary.');
        $observed=self::items($input['observed_evidence']??null,'finding',false);$inferences=self::items($input['inferences']??null,'inference',false);$recommendations=self::items($input['recommendations']??null,'recommendation',true);if(!$observed||!$recommendations)throw new RuntimeException('Inky’s review did not include the required evidence and recommendations.');
        return['summary'=>$summary,'observed_evidence'=>$observed,'inferences'=>$inferences,'recommendations'=>$recommendations];
    }

    private static function items(mixed $rows,string $textKey,bool $recommendation): array
    {
        if(!is_array($rows))return[];$out=[];foreach(array_slice($rows,0,10)as$row){if(!is_array($row))continue;$key=trim((string)($row['stable_key']??''));$text=trim((string)($row[$textKey]??''));if($key===''||$text==='')continue;$item=['stable_key'=>$key,$textKey=>$text];if($recommendation){$item['category']=trim((string)($row['category']??'Story quality'));$item['manual_structural_review']=!empty($row['manual_structural_review']);}$out[]=$item;}return$out;
    }
}
