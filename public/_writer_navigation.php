<?php
function writer_tabs(bool $isAdmin): array
{
    $tabs=['how_it_works'=>'How It Works','overview'=>'Story Framework','characters'=>'Characters','timeline'=>'Events','decisions'=>'Decisions','map'=>'Map, Dependencies, Opportunities','endings'=>'Endings','media'=>'Media'];
    if($isAdmin)$tabs['validation']='Submit for Review';
    return $tabs;
}

function writer_subtab_items(string $tab): array
{
    return match($tab){
        'how_it_works'=>['guide'=>'How Story Writer Works','identifiers'=>'Identifier Codes'],
        'overview'=>['story'=>'Story Overview','answers'=>'Guided Answers','locations'=>'Locations','values'=>'Story Values'],
        'map'=>['dependencies'=>'Story Map and Dependencies','opportunities'=>'Conditional Opportunities'],
        'endings'=>['fragments'=>'Ending Fragments','sections'=>'Ending Sections'],
        'media'=>['library'=>'Media Library','artwork'=>'Artwork'],
        default=>[],
    };
}

function writer_route(string $tab,string $subtab,bool $isAdmin): array
{
    if($tab==='artwork'){ $tab='media'; $subtab='artwork'; }
    if($tab==='overview'&&$subtab==='sections')$tab='endings';
    if(!array_key_exists($tab,writer_tabs($isAdmin)))$tab='overview';
    $items=writer_subtab_items($tab);
    if(!array_key_exists($subtab,$items))$subtab=$items?array_key_first($items):'';
    return [$tab,$subtab];
}

function writer_subtabs(int $plotId,string $tab,string $subtab): string
{
    $items=writer_subtab_items($tab);
    if(!$items)return '';
    $html='<nav class="subtabs" aria-label="'.e(writer_tabs(true)[$tab].' sections').'">';
    foreach($items as$key=>$label){
        $url='?'.http_build_query(['plot'=>$plotId,'tab'=>$tab,'subtab'=>$key]);
        $html.='<a class="subtab'.($key===$subtab?' active':'').'"'.($key===$subtab?' aria-current="page"':'').' href="'.e($url).'">'.e($label).'</a>';
    }
    return $html.'</nav>';
}

function writer_filter(): string
{
    return '<div class="record-toolbar" data-writer-filter-toolbar><label><span>Filter by name or code</span><input type="search" placeholder="Filter by name or code" aria-label="Filter by name or code"></label><button type="button" data-record-action="expand">Expand All</button><button type="button" data-record-action="collapse">Collapse All</button></div><p class="help" data-writer-filter-empty hidden>No matching entries.</p>';
}

function writer_quick_values(int $plotId,string $tab,array $w,array $codeUsage): string
{
    ob_start();
    ?>
<details class="quick-definition"><summary>Quick Add / Edit Story Values</summary><form method="post" class="grid state-definition-form" data-state-definition data-definition-type="PRESET"><?=writer_form_context($plotId,$tab,'state_definition')?><?=writer_definition_picker($w['state_definitions'],$codeUsage['state_definition']??[])?><?=writer_stable_code_field('Stable Target Code','target_code','',[],'Used by effects and conditions after this value is created.')?><label class="field">Effect Scope<select name="effect_scope" data-stable-scope><?php foreach(['WORLD_STATE','FACTOR','STAT','LOCATION','KNOWLEDGE','RELATIONSHIP','GOAL','FEAR']as$scope):?><option><?=$scope?></option><?php endforeach;?></select></label><label class="field">Value Type<select name="value_type"><option value="PRESET">Preset Values</option><option value="NUMERIC">Numerical</option><option value="TEXT">Free Text</option></select></label><label class="field">Label<input name="label_text" required></label><label class="field" data-definition-field="NUMERIC">Default Numerical Value<input type="number" name="default_numeric_value"></label><label class="field" data-definition-field="PRESET">Allowed Preset Values<textarea name="allowed_values_text" rows="2" placeholder="One per line"></textarea></label><label class="field wide">Writer Guidance<input name="description"></label><input type="hidden" name="active" value="1"><button class="btn">Save Story Value</button></form><p class="help" style="padding:0 12px 10px">Edit full definitions in Story Framework → Story Values.</p></details>
    <?php
    return (string)ob_get_clean();
}
