UPDATE plot_decision_template_options AS template_option
JOIN plot_decision_templates AS template
  ON template.template_id = template_option.template_id
JOIN plot_dynamic_opportunities AS opportunity
  ON opportunity.plot_id = template.plot_id
 AND opportunity.opportunity_code = template.event_code
 AND opportunity.role_code = template.role_code
JOIN plot_dynamic_opportunity_options AS opportunity_option
  ON opportunity_option.dynamic_opportunity_id = opportunity.dynamic_opportunity_id
 AND opportunity_option.option_code = template_option.option_code
SET template_option.npc_weight = GREATEST(0, opportunity_option.npc_weight);
