-- Empty Throne story-data copy cleanup.
-- Story-specific wording remains in the database; generic PHP is unchanged.

SET @plot=(SELECT plot_id FROM plots WHERE code='EMPTY_THRONE' LIMIT 1);

UPDATE plot_dynamic_opportunities
SET prompt_text=REPLACE(REPLACE(prompt_text,'youas','you as'),'ofhis','of his')
WHERE plot_id=@plot;

UPDATE plot_dynamic_opportunity_options pdoo
JOIN plot_dynamic_opportunities pdo
  ON pdo.dynamic_opportunity_id=pdoo.dynamic_opportunity_id
SET pdoo.label=REPLACE(REPLACE(pdoo.label,'youas','you as'),'ofhis','of his'),
    pdoo.result_text=REPLACE(REPLACE(pdoo.result_text,'youas','you as'),'ofhis','of his'),
    pdoo.rationale_text=REPLACE(REPLACE(pdoo.rationale_text,'youas','you as'),'ofhis','of his')
WHERE pdo.plot_id=@plot;

UPDATE plot_decision_templates
SET prompt_text=REPLACE(REPLACE(prompt_text,'youas','you as'),'ofhis','of his')
WHERE plot_id=@plot;

UPDATE plot_decision_template_options pdto
JOIN plot_decision_templates pdt ON pdt.template_id=pdto.template_id
SET pdto.label=REPLACE(REPLACE(pdto.label,'youas','you as'),'ofhis','of his'),
    pdto.result_text=REPLACE(REPLACE(pdto.result_text,'youas','you as'),'ofhis','of his'),
    pdto.rationale_text=REPLACE(REPLACE(pdto.rationale_text,'youas','you as'),'ofhis','of his')
WHERE pdt.plot_id=@plot;

INSERT IGNORE INTO schema_migrations(migration)
VALUES('034_clean_empty_throne_story_copy.sql');
