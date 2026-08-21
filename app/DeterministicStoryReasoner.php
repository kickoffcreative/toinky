<?php
final class DeterministicStoryReasoner implements StoryReasoner
{
    public function selectOption(array $context,array $options): array
    {
        if(!$options)throw new RuntimeException('No valid options supplied to reasoner.');
        $weighted=[];$total=0;
        foreach($options as$o){$w=max(0,(int)($o['npc_weight']??1));$weighted[]=['option'=>$o,'weight'=>$w];$total+=$w;}
        if($total<=0)return self::result($options[0]);
        $roll=random_int(1,$total);$cursor=0;
        foreach($weighted as$row){$cursor+=$row['weight'];if($roll<=$cursor)return self::result($row['option']);}
        return self::result($options[0]);
    }
    private static function result(array$o): array{return['selected_action'=>(string)$o['option_code'],'confidence'=>null,'reason'=>'Deterministic weighted fallback.','provider'=>'deterministic','model'=>'weighted-rules','interaction_id'=>null];}
}
