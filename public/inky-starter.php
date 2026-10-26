<?php
declare(strict_types=1);

ini_set('display_errors','0');
error_reporting(E_ALL);
$mode=trim((string)($_POST['mode']??'guide'));
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try{
    $root=dirname(__DIR__);
    require_once $root.'/app/bootstrap.php';
    require_once $root.'/app/AuthService.php';
    require_once $root.'/app/StoryAuthoringService.php';
    require_once $root.'/app/InkyGuideService.php';
    AuthService::boot();
    $writer=AuthService::currentUser();
    if(!$writer)throw new RuntimeException('Your Story Writer session expired. Refresh the page and try again.');
    if((int)$writer['is_admin']!==1&&!AuthService::hasRole((int)$writer['user_id'],'WRITER'))throw new RuntimeException('Story Writer access is required.');
    if($_SERVER['REQUEST_METHOD']!=='POST')throw new RuntimeException('POST is required.');
    AuthService::requireCsrf();
    $plotId=(int)($_POST['plot_id']??0);
    if($mode==='guide')$result=['ok'=>true]+InkyGuideService::guide($writer,$_POST);
    elseif($mode==='suggest_content')$result=['ok'=>true]+InkyGuideService::suggestContent($writer,$_POST);
    elseif($mode==='suggest_edits')$result=['ok'=>true]+InkyGuideService::suggestEdits($writer,$_POST);
    elseif($mode==='load_progress'){$progress=InkyGuideService::loadProgress($writer,$plotId);$result=['ok'=>true,'progress'=>$progress];}
    elseif($mode==='save_progress'){$state=$_POST['state']??[];if(is_string($state))$state=json_decode($state,true,20,JSON_THROW_ON_ERROR);InkyGuideService::saveProgress($writer,$plotId,$state);$accepted=(array)($state['accepted_proposals']??[]);$draftSnapshot=StoryAuthoringService::inkyDraftSnapshot($plotId,$accepted);$result=['ok'=>true,'draft_dependencies'=>$draftSnapshot['dependencies'],'draft_entries'=>$draftSnapshot['entries']];}
    elseif($mode==='create_draft'){$draftInput=$_POST;if(trim((string)($draftInput['title']??''))===''){$draftInput['title']='Untitled Story';$draftInput['code']='INKY_DRAFT_'.gmdate('YmdHis').'_'.random_int(1000,9999);}$result=['ok'=>true,'plot_id'=>StoryAuthoringService::createStory($writer,$draftInput)];}
    elseif($mode==='save_proposal'){$key=trim((string)($_POST['proposal_key']??''));$proposalValue=$_POST['proposal_value']??null;if(is_string($proposalValue)&&in_array(substr(ltrim($proposalValue),0,1),['{','['],true))$proposalValue=json_decode($proposalValue,true,20,JSON_THROW_ON_ERROR);$progress=InkyGuideService::loadProgress($writer,$plotId);$accepted=(array)($progress['accepted_proposals']??[]);if(array_key_exists($key,$accepted))StoryAuthoringService::assertInkyDraftEditAllowed($plotId,$key,$accepted[$key],$proposalValue);StoryAuthoringService::applyInkyProposal($writer,$plotId,$key,$proposalValue);$accepted[$key]=$proposalValue;if($progress){$progress['accepted_proposals']=$accepted;InkyGuideService::saveProgress($writer,$plotId,$progress);}$draftSnapshot=StoryAuthoringService::inkyDraftSnapshot($plotId,$accepted);$result=['ok'=>true,'draft_dependencies'=>$draftSnapshot['dependencies'],'draft_entries'=>$draftSnapshot['entries']];}
    else throw new RuntimeException('That Inky conversation action is not available.');
    echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);
}catch(Throwable$e){
    $detail=$e->getMessage();
    error_log('Inky guide failed ['.get_class($e).']: '.$detail);
    $normalized=strtolower($detail);
    $malformed=$e instanceof JsonException||str_contains($normalized,'json')||str_contains($normalized,'structured response')||str_contains($normalized,'syntax error')||str_contains($normalized,'incomplete');
    $message=str_contains($normalized,'timed out')||str_contains($normalized,'timeout')?'Inky’s conversation took too long. Please try again.':($malformed?'Inky returned an incomplete conversation response. Please try again.':(str_contains($normalized,'offline')||str_contains($normalized,'not available')||str_contains($normalized,'cannot be reached')||str_contains($normalized,'curl')||preg_match('/ai http (404|408|429|5[0-9]{2})/i',$detail)?'Inky’s assistant service is unavailable right now. Check the AI connection and try again.':($e instanceof RuntimeException&&!$e instanceof PDOException?$detail:'Inky could not continue the conversation. Check the AI connection and try again.')));
    http_response_code(422);
    echo json_encode(['ok'=>false,'message'=>$message],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);
}
