# Privacy Breach Response

November 13, 2026. Operational draft for A-06. This procedure requires operator
and legal review; it does not establish which laws apply in every incident.

## Responsibility And First Response

Toinky is the owner-supplied operator name. Randi, the accountable owner/Super
Admin, coordinates incidents and approves notices with qualified privacy/legal
advice. The monitored privacy contact must be **accounts@toinky.com**. If Randi
is unavailable, a designated substitute must be appointed before launch; none
has been confirmed. Do not invent a staffed security team or an SLA.

Anyone discovering an incident should alert that contact privately immediately.
Do not post credentials, account exports or incident evidence in GitHub issues.
Record discovery time in UTC, reporter, affected systems and a restricted
incident identifier. Preserve relevant logs and access history before routine
retention destroys them. Restrict the evidence store and record who accesses it.

Contain the incident: revoke compromised sessions/tokens, rotate affected
credentials through their owning systems, isolate vulnerable routes or disable
the affected integration, and preserve a safe rollback version. Avoid erasing
evidence or restoring a backup that reactivates compromised credentials. Keep
Toinky's owner informed; urgent containment must not wait for a complete report.

## Assessment And Notification Decision

Maintain a live timeline: what happened, earliest possible exposure, when the
operator became aware, categories/quantity of information, affected people and
countries, recipients, whether access was actually observed, safeguards in
effect, containment and residual risk. Identify sensitive information, account
takeover, fraud, embarrassment, reputational harm and risks to children. Do not
assume hashing, fictional labels or an unknown recipient means no personal risk.

The owner and privacy adviser document the applicable law, decision, supporting
facts, notification deadline and rationale. Reassess as evidence changes:

- Under applicable PIPEDA breach rules, assess a **real risk of significant
  harm**, including sensitivity and probability of misuse. Report to the OPC
  and notify affected people as soon as feasible when the threshold is met.
  Keep a record of every covered breach, including a reasoned decision not to
  report, for at least two years. See the [OPC breach guidance](https://www.priv.gc.ca/en/privacy-topics/privacy-for-businesses/privacy-breaches-at-your-business/gd_pb_201810/)
  and [PIPEDA section 10.1](https://laws-lois.justice.gc.ca/eng/acts/P-8.6/section-10.1.html?wbdisable=true).
- If Alberta PIPA applies, an incident creating a real risk of significant
  harm must be reported to the Alberta Commissioner **without unreasonable
  delay**. Follow the Commissioner's directions on affected-person notice;
  do not substitute an arbitrary 72-hour Alberta deadline. See [Alberta OIPC
  breach notification](https://oipc.ab.ca/breach-notification/).
- If GDPR applies, assess supervisory-authority notification within **72 hours
  of awareness**, unless the breach is unlikely to result in a risk to people's
  rights and freedoms; explain delay and provide staged information when
  necessary. High risk can separately require notice to affected people without
  undue delay. Document the assessment even where notification is not required.
  The threshold for authority notice differs from the high-risk individual
  notice threshold. See [EDPB breach guidance](https://www.edpb.europa.eu/sme/assess-the-risks/data-breaches_en)
  and [GDPR articles 33–34](https://eur-lex.europa.eu/eli/reg/2016/679).

These duties can overlap. Do not delay one required notice while deciding
whether another law also applies. Counsel must assess hosting/provider contracts,
other jurisdictions, law-enforcement requests and any exception to individual
notification. If a provider is involved, obtain incident details and contractual
assistance without assuming its notice replaces Toinky's obligations.

## Notice, Recovery And Record

Use plain language: known incident dates, affected information, likely impact,
containment, practical protective steps, Toinky's contact, and available
complaint routes. Never email passwords, unnecessary sensitive details or the
full affected-user list. Keep copies of notices and the method/date delivered.
Record failed delivery and proportionate alternative contact steps.

Verify the fix with evidence, restore safely, monitor recurrence and document
remaining uncertainty. Preserve a restricted breach record containing the
assessment, decider, notifications, evidence location, actions and retention
deadline. Set a documented review/destruction date after legal preservation
requirements are resolved. A legal hold should be narrow, dated and reviewed;
do not use it as a permanent excuse to retain every account record.

After closure, assign corrective actions, owners and dates. Review whether the
inventory, policy, provider list, retention schedule and access controls need
updating. The public repository must contain the procedure, not personal
incident evidence or affected-user exports.
