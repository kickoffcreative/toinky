<?php
require_once __DIR__.'/AiConnectionService.php';

final class InkyGuideService
{
    private const STEP_COUNT=5;
    private const MAX_ANSWER_CHARS=12000;
    private const MAX_TOTAL_CHARS=24000;

    public static function guide(array $user,array $input): array
    {
        $genreId=(int)($input['genre_id']??0);$q=db()->prepare('SELECT label_text FROM story_genres WHERE genre_id=? AND active=1');$q->execute([$genreId]);$genre=trim((string)$q->fetchColumn());if($genre==='')throw new RuntimeException('Choose a genre for the conversation.');
        $step=(int)($input['step']??0);if($step<0||$step>=self::STEP_COUNT)throw new RuntimeException('That Inky conversation step is not available.');$answers=self::answers($input['answers']??[]);for($index=0;$index<$step;$index++)if(trim((string)($answers[$index]??''))==='')throw new RuntimeException('Complete the earlier Inky question first.');$answer=self::text($input['answer']??'','answer',self::MAX_ANSWER_CHARS);if(mb_strlen($answer)<3)throw new RuntimeException('Give Inky a little more to work with.');$answers[$step]=$answer;ksort($answers);$total=0;foreach($answers as$value)$total+=mb_strlen((string)$value);if($total>self::MAX_TOTAL_CHARS)throw new RuntimeException('The Inky conversation has become too long. Start a new conversation or shorten an earlier answer.');
        $system='You are Inky, a patient story coach for a new interactive-fiction writer. Hold a five-step conversation in the supplied genre. Respond with JSON only and exactly this shape: {"reply":"brief warm acknowledgement and useful guidance","question":"one clear next question","complete":false,"brief":null}. On the fifth answer, use {"reply":"brief encouraging wrap-up","question":"","complete":true,"brief":{"title":"player-facing story title","setting_name":"short setting","scenario_text":"80 to 250 words describing the story concept","tone":"short tone","author_notes":"short notes for the writer"}}. Ask only one question at a time. Do not create characters, locations, events, decisions, options, endings, stable codes, database records, PHP, schema, or engine instructions. Do not force the writer to know technical story design terms. Build from the writer\'s answers and do not invent major facts without signaling them as a suggestion. The five questions are: story spark; who the story follows and what they want; where and when it happens; the central problem or meaningful choice; tone and the feeling the player should leave with. Keep reply and question concise. Return no markdown and no additional keys.';
        $prompt=json_encode(['genre'=>$genre,'step'=>$step+1,'current_answer'=>$answer,'previous_answers'=>$answers,'instruction'=>'Acknowledge the latest answer, then ask the next single question. At step five, summarize only the writer concept into the brief.'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);if($prompt===false)throw new RuntimeException('The Inky conversation request could not be prepared.');$response=AiConnectionService::completeJson('INKY_STORY_GUIDE',$system,$prompt);$guided=self::validatedResponse((array)$response['data'],$step);return['step'=>$step,'next_step'=>$guided['complete']?null:$step+1,'reply'=>$guided['reply'],'question'=>$guided['question'],'complete'=>$guided['complete'],'brief'=>$guided['brief'],'answers'=>$answers,'model'=>$response['model']];
    }

    private static function validatedResponse(array $input,int $step): array
    {
        $reply=self::text($input['reply']??'','Inky reply',1200);$complete=$step===self::STEP_COUNT-1;if($complete){$briefInput=is_array($input['brief']??null)?$input['brief']:[];$scenario=self::text($briefInput['scenario_text']??'','story concept',4000);if(mb_strlen($scenario)<80)throw new RuntimeException('Inky returned a story concept that is too short.');return['reply'=>$reply,'question'=>'','complete'=>true,'brief'=>['title'=>self::text($briefInput['title']??'','story title',190),'setting_name'=>self::text($briefInput['setting_name']??'','setting',190),'scenario_text'=>$scenario,'tone'=>self::text($briefInput['tone']??'','tone',160,false),'author_notes'=>self::text($briefInput['author_notes']??'','writer notes',2000,false)]];}
        return['reply'=>$reply,'question'=>self::text($input['question']??'','next question',600),'complete'=>false,'brief'=>null];
    }

    private static function answers(mixed $raw): array
    {
        if(is_string($raw)){try{$raw=json_decode($raw,true,20,JSON_THROW_ON_ERROR);}catch(Throwable$e){throw new RuntimeException('The Inky conversation state is invalid.');}}
        if(!is_array($raw))throw new RuntimeException('The Inky conversation state is invalid.');$answers=[];foreach($raw as$key=>$value){if(!preg_match('/^[0-4]$/',(string)$key))continue;if(!is_scalar($value))continue;$answers[(int)$key]=self::text($value,'earlier answer',self::MAX_ANSWER_CHARS);}return$answers;
    }

    private static function text(mixed$value,string$label,int$max,bool$required=true): string
    {
        $value=is_scalar($value)?trim((string)$value):'';if($required&&$value==='')throw new RuntimeException('Inky is missing a usable '.$label.'.');if(mb_strlen($value)>$max)throw new RuntimeException('Inky’s '.$label.' is too long.');return$value;
    }
}
