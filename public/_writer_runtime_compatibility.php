<?php
// Included only after writer.php has authorized this story. A direct request
// has no writer workspace and renders no data.
if (!isset($plotId, $w) || !is_array($w) || !class_exists('StoryAuthoringService')) return;
$rq = db()->prepare('SELECT p.*,e.entity_code FROM plot_entity_properties p JOIN plot_entities e ON e.plot_entity_id=p.plot_entity_id WHERE e.plot_id=? ORDER BY e.entity_code,p.property_code');
$rq->execute([$plotId]);
$runtimeProperties = $rq->fetchAll();
if (!$runtimeProperties) return;
$rq = db()->prepare('SELECT state_key,entity_code,property_code FROM plot_state_aliases WHERE plot_id=? ORDER BY state_key');
$rq->execute([$plotId]);
$runtimeAliases = [];
foreach ($rq->fetchAll() as $alias) $runtimeAliases[$alias['entity_code'].'|'.$alias['property_code']][] = (string)$alias['state_key'];
$rq = db()->prepare('SELECT t.* FROM plot_property_transitions t JOIN plot_entity_properties p ON p.property_id=t.property_id JOIN plot_entities e ON e.plot_entity_id=p.plot_entity_id WHERE e.plot_id=? AND t.active=1 ORDER BY t.property_id,t.property_transition_id');
$rq->execute([$plotId]);
$runtimeTransitions = [];
foreach ($rq->fetchAll() as $transition) $runtimeTransitions[(int)$transition['property_id']][] = $transition;
$managedWorldValues = [];
foreach ($w['state_definitions'] as $definition) if ($definition['effect_scope']==='WORLD_STATE' && (int)($definition['active']??1)===1) $managedWorldValues[(string)$definition['target_code']] = $definition;
?>
<details class="record">
<summary>Runtime Compatibility</summary>
<p class="help">Some existing stories also have runtime state rules. Compare their allowed values and transitions with the Story Values below before publishing. This view is read-only. Changing a dropdown does not change these runtime rules, and published revisions that include runtime rules keep their own copies.</p>
<table>
<thead><tr><th>Value</th><th>Authoring Definition</th><th>Runtime Rule</th><th>Allowed Transitions</th></tr></thead>
<tbody>
<?php foreach ($runtimeProperties as $property):
    $directKey = $property['entity_code'].'.'.$property['property_code'];
    $aliases = $runtimeAliases[$property['entity_code'].'|'.$property['property_code']]??[];
    $keys = array_values(array_unique(array_merge($aliases, [$directKey])));
    $foundDefinition = false;
?>
<tr>
<td><strong><?=e($property['label'])?></strong><br><code><?=e(implode(', ', $keys))?></code></td>
<td>
<?php foreach ($keys as $key): if (!isset($managedWorldValues[$key])) continue; $definition=$managedWorldValues[$key]; $foundDefinition=true; ?>
<p><strong><?=e($definition['target_code'])?></strong> · <?=e($definition['value_type'])?><br>
<?=e(str_replace(["\r\n", "\n"], ', ', (string)($definition['allowed_values_text']??'')))?><br>
Initial: <?=e((string)($definition['value_type']==='NUMERIC' ? ($definition['default_numeric_value']??'Not Set') : ($definition['default_text_value']??'Not Set')))?></p>
<?php endforeach; ?>
<?php if (!$foundDefinition): ?><span class="muted">No active shared-world authoring definition under these keys.</span><?php endif; ?>
</td>
<td><strong><?=e($property['value_type'])?></strong><br>
<?=e((string)($property['allowed_values']??''))?><br>
Initial: <?=e((string)($property['initial_value']??'Not Set'))?>
<?php if ($property['value_type']==='NUMBER'): ?><br>Range: <?=e((string)($property['min_value']??'Unbounded'))?> to <?=e((string)($property['max_value']??'Unbounded'))?><?php endif; ?>
</td>
<td>
<?php $transitions=$runtimeTransitions[(int)$property['property_id']]??[]; if (!$transitions): ?>
<span class="muted">No transition restrictions beyond the allowed values.</span>
<?php else: ?><ul><?php foreach ($transitions as $transition): ?><li><?=e((string)($transition['from_value']??'Not Set'))?> → <?=e($transition['to_value'])?></li><?php endforeach; ?></ul><?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</tbody></table>
</details>
