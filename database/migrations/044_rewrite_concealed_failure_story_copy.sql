-- Complete player-facing prose rewrite for The Storm at Bellweather Resort.
-- All story-specific writing remains in data. Generic PHP only reads plot-level presentation settings.

ALTER TABLE plots
  ADD COLUMN IF NOT EXISTS resolution_headline VARCHAR(255) NULL AFTER status,
  ADD COLUMN IF NOT EXISTS include_factor_summary TINYINT(1) NOT NULL DEFAULT 1 AFTER resolution_headline;

SET @plot=(SELECT plot_id FROM plots WHERE code='CONCEALED_FAILURE' LIMIT 1);

UPDATE plots
SET title='The Storm At Bellweather Resort',
    archetype='Concealed Failure',
    genre='Contemporary Disaster Thriller',
    setting_name='Bellweather Mountain Resort',
    resolution_headline='After The Mountain Moves',
    include_factor_summary=0
WHERE plot_id=@plot;

UPDATE plot_factors SET description=CASE factor_code
  WHEN 'SAFETY' THEN 'Whether the people inside Bellweather can get clear of the failing east wing.'
  WHEN 'EVIDENCE' THEN 'Whether proof of the failed inspection survives Farah’s attempt to bury it.'
  WHEN 'CONTROL' THEN 'How firmly Farah still commands the room and the story people believe.'
  WHEN 'SUSPICION' THEN 'How many people have begun to see the disaster as something more than bad luck.'
  WHEN 'TRUST' THEN 'Whether frightened people can still rely on one another when every choice costs time.'
  ELSE description END
WHERE plot_id=@plot;

UPDATE plot_roles SET
  public_description=CASE role_code
    WHEN 'KELLAN' THEN 'A repeat guest with a patient eye for routines—and for the moment someone breaks one.'
    WHEN 'NIKO' THEN 'Bellweather’s operations manager. Guests trust his calm voice, and Farah has long treated his loyalty as something she owns.'
    WHEN 'WREN' THEN 'A practical guest with emergency-response experience, more interested in what the building is doing than what frightened people are saying.'
    WHEN 'FARAH' THEN 'Bellweather’s polished owner. Warm when watched, decisive under pressure, and fiercely protective of everything carrying her name.'
    WHEN 'ALDEN' THEN 'Bellweather’s meticulous accountant, a quiet man who has finally found a number he cannot explain away.'
    WHEN 'LEILA' THEN 'A guest with first-aid experience who measures every crisis by the person most likely to be forgotten.'
    ELSE public_description END,
  private_knowledge=CASE role_code
    WHEN 'KELLAN' THEN 'Yesterday, outside the lounge, you heard Farah ask maintenance whether the east service door could be opened without leaving an alarm record. She lowered her voice before asking.'
    WHEN 'NIKO' THEN 'Yesterday Farah placed a passing structural certificate in your hands and waited while you read it. You told the staff the east wing was safe because you believed her.'
    WHEN 'WREN' THEN 'Before dinner you saw Alden at the copier with a rain-spotted engineering report. A red FAILED stamp crossed the first page before he hid it beneath his jacket.'
    WHEN 'FARAH' THEN 'You kept Bellweather open after the east retaining wall failed inspection. You buried the report behind a false $40,000 invoice to inspector Martin Vale, and Alden has been following the payment.'
    WHEN 'ALDEN' THEN 'You copied the failed inspection report and traced a false $40,000 invoice to Martin Vale. The original has vanished. Your copy may be the only proof left.'
    WHEN 'LEILA' THEN 'The injured guest can be stabilized here, but not carried safely by one person. If the east wing shifts again, you will need a second pair of hands and a clear west route.'
    ELSE private_knowledge END,
  goal_text=CASE role_code
    WHEN 'KELLAN' THEN 'Find out what Farah was preparing to do before the wall came down.'
    WHEN 'NIKO' THEN 'Get the guests out without surrendering Bellweather to panic—or to a lie.'
    WHEN 'WREN' THEN 'Read the danger correctly and get people clear before the mountain moves again.'
    WHEN 'FARAH' THEN 'Keep the failed inspection and the payment to Martin from becoming provable.'
    WHEN 'ALDEN' THEN 'Put surviving proof into hands Farah cannot silence or discredit.'
    WHEN 'LEILA' THEN 'Keep the injured guest alive and get them beyond the east wing.'
    ELSE goal_text END,
  fear_text=CASE role_code
    WHEN 'KELLAN' THEN 'Following the secret while the mountain prepares a second collapse.'
    WHEN 'NIKO' THEN 'Learning that Farah used his trust to keep people inside an unsafe building.'
    WHEN 'WREN' THEN 'Reading the danger correctly but choosing the wrong person to save.'
    WHEN 'FARAH' THEN 'Prison, public disgrace, and watching Bellweather become evidence against her.'
    WHEN 'ALDEN' THEN 'Reaching the truth too late for it to matter.'
    WHEN 'LEILA' THEN 'Hearing people argue over proof while someone dies a few rooms away.'
    ELSE fear_text END
WHERE plot_id=@plot;

CREATE TEMPORARY TABLE tmp_cf_world (
  event_code VARCHAR(120) PRIMARY KEY,
  headline VARCHAR(255) NOT NULL,
  event_text TEXT NOT NULL
);
INSERT INTO tmp_cf_world VALUES
('STORY_START','Rain Against The Glass','Rain needles the tall lobby windows and turns the mountain road below Bellweather into a black ribbon of runoff. Staff place brass buckets beneath old leaks while Farah Xu moves from table to table, assuring guests that the storm is ordinary.'),
('WALL_SHIFT','The Mountain Strikes','A deep crack rolls beneath the wind. The east retaining wall gives way with a sound like stone furniture dragged across the mountain. Lights flicker; plaster bursts over the stairwell; and somewhere beyond the dust, a guest screams.'),
('REPORT_MISSING','Alden Opens His Coat','Alden Rowan emerges from the office corridor soaked and breathless. He pulls a creased report from beneath his coat. Across the first page, a red engineering stamp reads FAILED. Farah stops speaking in the middle of an evacuation order.'),
('PRESSURE_RISE','The Building Groans Again','Emergency lights stain the lobby red. Muddy water pushes beneath the east doors while the radios answer in bursts of static. Leila calls for another pair of hands. Alden guards the report. Farah begins edging toward the rear hall with a records case pressed to her side.'),
('REAR_EXIT','The Door At The Back','The rear door slams open under a gust of rain. Beyond it, the service path is disappearing beneath brown water. Farah stands close enough to the threshold to run. Behind her, Leila shouts that the east stairwell is moving again.'),
('FINAL_STATE','What The Storm Leaves Behind','The mountain grows quiet by degrees. Inside Bellweather, wet footprints, broken plaster, and the choices made in the dark remain for everyone to see.');
UPDATE plot_world_events w JOIN tmp_cf_world x ON x.event_code=w.event_code
SET w.headline=x.headline,w.event_text=x.event_text
WHERE w.plot_id=@plot;
DROP TEMPORARY TABLE tmp_cf_world;

CREATE TEMPORARY TABLE tmp_cf_perceptions (
  event_code VARCHAR(120), role_code VARCHAR(100), title VARCHAR(255), perception_text TEXT,
  PRIMARY KEY(event_code,role_code)
);
INSERT INTO tmp_cf_perceptions VALUES
('WALL_SHIFT','KELLAN','Farah Looks The Wrong Way','The scream comes from the east stairwell. Farah looks toward Alden’s office. It is only a glance, but it is the same corridor connected to yesterday’s question about an alarm that leaves no record.'),
('WALL_SHIFT','NIKO','Every Face Turns To You','Glasses tremble off the lobby tables. Guests call your name from three directions. Farah is issuing reassurances, but Leila is already shouting for help at the east stairwell.'),
('WALL_SHIFT','WREN','The Wall Is Still Talking','Dust boils from the east stairwell where Leila kneels beside an injured guest. Beneath the alarms you hear stone grinding against stone. The first collapse may not be finished.'),
('WALL_SHIFT','FARAH','Alden Knows What Fell','Alden does not look toward the scream. He looks toward his office—and then at you. The copied report is still somewhere down that corridor.'),
('WALL_SHIFT','ALDEN','The Only Copy Is Down The Hall','Your office door swings open and shut in the pressure change. The failed report is inside. Farah has seen where you looked.'),
('WALL_SHIFT','LEILA','A Hand Reaches Through The Dust','A guest lies beneath fallen plaster near the east stairwell, conscious but unable to stand. Their hand finds your sleeve. The floor shivers again under your knees.'),
('REPORT_MISSING','KELLAN','Yesterday’s Question Has An Answer','The red FAILED stamp turns Farah’s quiet question about the unlogged service door into something colder. Alden has one piece of the story. You may have another.'),
('REPORT_MISSING','NIKO','The Certificate In Your Hands Was A Lie','You remember Farah tapping the signature on the passing certificate while you read it. Alden’s report carries the same date and a red FAILED stamp. Both cannot be true.'),
('REPORT_MISSING','WREN','The Red Stamp Returns','It is the same report you saw beneath Alden’s hand at the copier. Now Farah is looking at it as though the paper itself could bring down what the mountain left standing.'),
('REPORT_MISSING','FARAH','Everyone Has Seen The Stamp','Alden holds the report where the nearest guests can read the first page. Taking it now would look like guilt. Leaving it with him may be worse.'),
('REPORT_MISSING','ALDEN','Paper Can Still Be Called A Lie','The report is in your hands, but Farah already has the room listening to her. Unless someone else reads the details, she can turn your proof into one frightened man’s accusation.'),
('REPORT_MISSING','LEILA','The Argument Drowns Out The Injured','Voices rise in the lobby while the injured guest’s breathing grows shallow beside you. You need another helper, and everyone with two good hands is staring at the report.'),
('PRESSURE_RISE','KELLAN','Farah Is Measuring The Exits','Farah keeps one hand on the records case and checks the rear hall whenever thunder shakes the glass. The office, the alarm, and the report now point in the same direction.'),
('PRESSURE_RISE','NIKO','Farah Uses Your Name','Farah tells the guests that you have the evacuation under control. Alden asks you to take the report. Leila calls for bodies, not promises. Whichever voice you answer will carry your authority with it.'),
('PRESSURE_RISE','WREN','Dust Falls In A New Pattern','Fresh dust feathers from the east archway. The wall is not settling; it is loading for another movement. Leila can move the guest, but only if the route and the help are real.'),
('PRESSURE_RISE','FARAH','The Room Is Learning Too Quickly','Kellan watches you. Alden has stopped whispering. Niko no longer answers at once. The rear hall is still clear, but not for long.'),
('PRESSURE_RISE','ALDEN','The Report Needs Another Voice','Rain has softened the edges of the pages, and Farah is already calling them incomplete. One copy in one man’s hands can still disappear.'),
('PRESSURE_RISE','LEILA','First Aid Has Bought Minutes, Not Safety','The bleeding is controlled, but the guest cannot stay beneath a ceiling that sheds plaster whenever the mountain groans.'),
('REAR_EXIT','KELLAN','Watching Is No Longer Neutral','Farah is one stride from the rear door. If she crosses it with the records case, yesterday’s whispered question may become the only clue she leaves behind.'),
('REAR_EXIT','NIKO','Farah Waits For Your Permission','Farah meets your eyes beside the open door. She does not ask to leave. She simply waits to see whether the authority you built for her will stop her now.'),
('REAR_EXIT','WREN','Two Emergencies, One Pair Of Hands','Leila needs help at the east stairwell. Alden needs witnesses at the rear door. The floor shifts beneath both of them.'),
('REAR_EXIT','FARAH','One Door Still Opens','Cold rain blows through the rear doorway. The room behind you is no longer yours, but the path outside may still be.'),
('REAR_EXIT','ALDEN','Do Not Let The Proof Leave Alone','The report is no longer enough by itself. If Farah leaves with the remaining records—or if you remain its only witness—she can still rewrite the night.'),
('REAR_EXIT','LEILA','The Ceiling Answers The Mountain','A seam opens above the stairwell and drops a line of plaster across the guest’s coat. Waiting has become its own decision.');
UPDATE plot_event_perceptions p JOIN tmp_cf_perceptions x
  ON x.event_code=p.event_code AND x.role_code=p.role_code
SET p.title=x.title,p.perception_text=x.perception_text
WHERE p.plot_id=@plot;
DROP TEMPORARY TABLE tmp_cf_perceptions;

CREATE TEMPORARY TABLE tmp_cf_prompts (
  event_code VARCHAR(120), role_code VARCHAR(100), prompt_text TEXT,
  PRIMARY KEY(event_code,role_code)
);
INSERT INTO tmp_cf_prompts VALUES
('WALL_SHIFT','KELLAN','The east stairwell fills with dust, but Farah is already moving toward Alden’s office. Yesterday she asked how to open the service door without an alarm record.'),
('WALL_SHIFT','NIKO','Guests crowd toward you as another tremor runs through the east wing. Leila is calling for help, and Farah is telling everyone to remain calm.'),
('WALL_SHIFT','WREN','Leila is on her knees beside an injured guest. Beyond her, the damaged wall continues to grind against the mountain.'),
('WALL_SHIFT','FARAH','Alden has looked toward the office where his copied report may still be hidden. Dust and panic have given you a brief opening.'),
('WALL_SHIFT','ALDEN','Your copied failed report is still in the office. Farah has seen where you looked, and the lobby is erupting around you.'),
('WALL_SHIFT','LEILA','The injured guest grips your sleeve while plaster continues to fall. You can treat them here, call for help, or risk leaving long enough to test the route.'),
('REPORT_MISSING','KELLAN','Alden raises the red-stamped report. Farah calls it a distraction. You remember her asking how to bypass the service-door alarm without leaving a record.'),
('REPORT_MISSING','NIKO','The report in Alden’s hands is dated the same day as the passing certificate Farah personally gave you. The lobby is waiting to see whom you believe.'),
('REPORT_MISSING','WREN','You recognize the red-stamped pages, but Leila still has no safe way to move the injured guest alone.'),
('REPORT_MISSING','FARAH','The word FAILED is visible from across the lobby. You cannot simply take the report now; too many people are watching.'),
('REPORT_MISSING','ALDEN','Farah is already calling the report incomplete and your motives suspect. The pages will not protect themselves.'),
('REPORT_MISSING','LEILA','Alden and Farah are shouting over a report while the injured guest beside you still needs a second helper.'),
('PRESSURE_RISE','KELLAN','Farah keeps checking the rear hall. Alden guards the report. From the east stairwell, Leila calls again for help.'),
('PRESSURE_RISE','NIKO','Farah invokes your authority, Alden asks you to preserve the report, and muddy water is spreading beneath the east doors.'),
('PRESSURE_RISE','WREN','Fresh dust is falling from the east archway. You have time for one decisive move before the next shift.'),
('PRESSURE_RISE','FARAH','Kellan is watching the records case. Alden will not back down. Niko’s loyalty is no longer automatic, and the rear hall is still open.'),
('PRESSURE_RISE','ALDEN','Rain has reached the report’s edges. Farah is working on the room, and one surviving copy still leaves the truth dangerously fragile.'),
('PRESSURE_RISE','LEILA','The guest is stable enough to move but not strong enough to walk. The east ceiling sheds another veil of dust.'),
('REAR_EXIT','KELLAN','Farah reaches the open rear door with the records case. Leila’s voice carries from the east stairwell behind you.'),
('REAR_EXIT','NIKO','Farah stands at the rear exit and waits to see whether you will stop her. Behind you, the evacuation is still unfinished.'),
('REAR_EXIT','WREN','The east side shudders as Farah reaches the rear door. Leila needs hands; Alden needs a witness.'),
('REAR_EXIT','FARAH','The rear path is flooding, the room has turned against you, and the strongest surviving records may still decide what happens after tonight.'),
('REAR_EXIT','ALDEN','Farah is at the rear door. The proof must leave Bellweather in safer hands before she does.'),
('REAR_EXIT','LEILA','The guest must move now. The route, the available help, and the failing ceiling will decide how.');
UPDATE plot_decision_templates d JOIN tmp_cf_prompts x
  ON x.event_code=d.event_code AND x.role_code=d.role_code
SET d.prompt_text=x.prompt_text
WHERE d.plot_id=@plot;
DROP TEMPORARY TABLE tmp_cf_prompts;

CREATE TEMPORARY TABLE tmp_cf_options (
  event_code VARCHAR(120), role_code VARCHAR(100), option_code VARCHAR(100),
  label VARCHAR(500), result_text TEXT, rationale_text TEXT NULL,
  PRIMARY KEY(event_code,role_code,option_code)
);
INSERT INTO tmp_cf_options VALUES
('WALL_SHIFT','KELLAN','FOLLOW_FARAH','Slip into the office corridor behind Farah.','You follow before Farah notices. The stairwell cries fade behind you as she reaches for Alden’s office door.','Curiosity makes Farah’s impossible first reaction more urgent than the obvious danger.'),
('WALL_SHIFT','KELLAN','HELP_GUESTS','Run toward the scream at the east stairwell.','You shoulder through the dust toward Leila, leaving Farah free to disappear down the office corridor.','Fear of another collapse pulls Kellan toward the person already hurt.'),
('WALL_SHIFT','KELLAN','STAY_WATCH','Hold your ground and watch who chooses which door.','You stay where the whole lobby remains visible. Farah, Alden, and the rescuers split in different directions.','Kellan may learn more by watching the pattern than by following one person.'),
('WALL_SHIFT','NIKO','EVACUATE_EAST','Take the radio and clear the east wing.','Your voice cuts through the lobby. Staff begin moving guests west while you head toward the damaged wing.','Duty favors immediate command before panic chooses the route.'),
('WALL_SHIFT','NIKO','CHECK_FARAH','Keep Farah beside you and demand the truth about the wall.','You block Farah’s path with a quiet question. Her answer comes too quickly and explains too little.','Niko’s loyalty makes him confront Farah privately before undermining her in public.'),
('WALL_SHIFT','NIKO','FIND_ALDEN','Find Alden before he reaches the office.','You leave the crowd to the staff and cut toward the corridor, determined to learn why Alden ran the wrong way.','The unexplained office reaction may reveal what the normal emergency plan cannot.'),
('WALL_SHIFT','WREN','HELP_LEILA','Kneel beside Leila and stabilize the injured guest.','You brace the guest’s neck while Leila checks their breathing. Dust continues to sift across both your shoulders.','Wren’s safety instinct puts the injured person ahead of the unanswered questions.'),
('WALL_SHIFT','WREN','CHECK_WALL','Move close enough to read the damaged wall.','You study the new cracks and hear water moving where no drain should be. The east side is still loading.','Knowing whether another collapse is coming can protect everyone, not only the first casualty.'),
('WALL_SHIFT','WREN','FIND_NIKO','Pull Niko away from the lobby and show him the moving cracks.','You find Niko and make him look past Farah’s assurances to the dust pulsing from the east archway.','The operations manager can move more people if he understands the physical risk.'),
('WALL_SHIFT','FARAH','SECURE_REPORT','Use the dust to reach Alden’s office first.','You disappear into the corridor as another alarm begins. Alden’s office door is unlocked.','The report is the one object capable of turning a disaster into a crime.'),
('WALL_SHIFT','FARAH','CONTROL_ROOM','Stay visible and take command of the lobby.','You lift your voice above the alarms, assign staff, and make frightened guests look at you instead of Alden.','Farah is most convincing when everyone needs her to sound certain.'),
('WALL_SHIFT','FARAH','CONFRONT_ALDEN','Intercept Alden before he reaches the corridor.','You catch Alden by the arm and order him toward the guests. He looks at your hand until you let go.','Stopping Alden may protect the report without exposing Farah’s exact destination.'),
('WALL_SHIFT','ALDEN','PROTECT_REPORT','Reach the office and take the copied report.','You force the swollen office door open and pull the red-stamped pages from beneath the ledger drawer.','Without the copy, the false invoice can still be explained away.'),
('WALL_SHIFT','ALDEN','HELP_RESPONSE','Stay in the lobby and help move guests west.','You turn away from the office and begin directing guests toward the safer side, knowing the report remains unguarded.','Alden may decide that proof is worthless if people are hurt while he saves it.'),
('WALL_SHIFT','ALDEN','WATCH_FARAH','Wait long enough to see what Farah protects first.','You hold back. Farah’s eyes find the office corridor before they find the injured guest.','Farah’s first move may reveal more than anything Alden can accuse her of.'),
('WALL_SHIFT','LEILA','STABILIZE_GUEST','Stay on the floor and begin first aid.','You press folded linen against the wound and keep the guest answering you while the building trembles.','Leila will not leave the injured person for a danger that has not reached them yet.'),
('WALL_SHIFT','LEILA','GET_HELP','Run to the lobby doors and drag someone back with you.','You shout until faces turn, then point to the guest who cannot be moved by one person.','The guest will need two people when treatment becomes evacuation.'),
('WALL_SHIFT','LEILA','CHECK_EXIT','Test the west route before anyone tries to carry the guest.','You leave the guest for seconds, counting clear doors and listening for movement beneath the floor.','A safe lift means nothing if the route ends in another trap.'),
('REPORT_MISSING','KELLAN','TELL_ALDEN_ALARM','Tell Alden exactly what you heard Farah ask yesterday.','Alden goes still when you repeat Farah’s words. He folds the report around your new piece of the story.','Sharing the alarm question may connect Farah’s preparation to the missing records.'),
('REPORT_MISSING','KELLAN','WATCH_FARAH_REPORT','Keep the alarm question secret and study Farah.','You say nothing. When Alden mentions the inspection date, Farah’s hand tightens around the records case.','Kellan may catch a revealing reaction if Farah does not know what he heard.'),
('REPORT_MISSING','KELLAN','HELP_LEILA_NOW','Leave the argument and answer Leila’s call.','You turn your back on the report and run toward the stairwell. Leila finally has another pair of hands.','The living person at the east stairwell may matter more than one more witness in the lobby.'),
('REPORT_MISSING','NIKO','CHALLENGE_CERTIFICATE','Hold both reports up and make Farah explain the dates.','The lobby quiets when you place Farah’s passing certificate beside Alden’s failed report. Farah cannot dismiss one without explaining the other.','Niko’s duty may finally outweigh the trust Farah asked him to place in her.'),
('REPORT_MISSING','NIKO','TAKE_EVAC_COMMAND','End the argument and take command of the evacuation.','You seize the radio, assign the west route, and tell both Farah and Alden that the papers can wait until the guests are moving.','Order and physical safety remain Niko’s clearest responsibility.'),
('REPORT_MISSING','NIKO','BACK_FARAH','Stand beside Farah and silence the accusation for now.','Your support steadies Farah’s voice. Alden lowers the report, but he does not stop watching you.','Backing Farah preserves a chain of command at the cost of trusting her again.'),
('REPORT_MISSING','WREN','TELL_NIKO_RED_STAMP','Tell Niko you saw Alden copy the failed report before dinner.','Niko looks from you to Alden and finally treats the red stamp as more than a desperate claim.','Wren can corroborate the document without abandoning the east wing for long.'),
('REPORT_MISSING','WREN','MOVE_GUEST','Stay with Leila and prepare the guest to move.','You slide a blanket beneath the guest and show Leila where to lift when the route is clear.','The guest needs committed hands more than the argument needs another voice.'),
('REPORT_MISSING','WREN','CHECK_ROUTE','Take a lamp and test the west evacuation route.','You move through the dark west corridor, checking the floor, doors, and drifting water before signalling that the path can take a stretcher.','Moving an injured person into an unknown corridor could turn rescue into another emergency.'),
('REPORT_MISSING','FARAH','DISCREDIT_ALDEN','Tell the room Alden is using the disaster to settle a private grievance.','You call Alden frightened, resentful, and dangerously obsessed. Several guests look at him instead of the FAILED stamp.','Farah can weaken proof by making its keeper look untrustworthy.'),
('REPORT_MISSING','FARAH','PRESSURE_NIKO','Pull Niko close and make the guests his responsibility.','You remind Niko that every frightened face is waiting for him, not for Alden’s accusations. His attention shifts back to the room.','Niko’s sense of duty can become cover for Farah if she frames investigation as neglect.'),
('REPORT_MISSING','FARAH','MOVE_EVIDENCE','Use the argument to hide the records you still control.','While every eye follows Alden, you slip the records case behind the locked service desk.','Distance and confusion may protect what open denial cannot.'),
('REPORT_MISSING','ALDEN','SHOW_NIKO','Put the report in Niko’s hands and make him read the failed finding.','Niko reads the engineering conclusion twice. Farah can no longer pretend he heard it only from you.','A trusted insider is harder for Farah to dismiss than the resort accountant alone.'),
('REPORT_MISSING','ALDEN','HIDE_COPY','Fold the report beneath your coat until authorities arrive.','You remove the red stamp from view and keep one hand over the pages as the crowd shifts around you.','Alden’s caution favors survival of the evidence over winning the lobby.'),
('REPORT_MISSING','ALDEN','ACCUSE_FARAH','Name the false invoice and accuse Farah in front of everyone.','You read the payment date aloud and say exactly what it purchased. The argument stops being about a damaged wall and becomes an accusation of concealment.','Direct accusation risks division but denies Farah time to invent a cleaner explanation.'),
('REPORT_MISSING','LEILA','RECRUIT_HELPER','Stop the argument and point to the person who still needs carrying.','Your shout cuts through the lobby. For one second, even Farah looks toward the east stairwell.','Leila knows the guest cannot be evacuated by concern alone.'),
('REPORT_MISSING','LEILA','KEEP_TREATING','Stay beside the guest and keep them conscious.','You keep talking, checking breathing and circulation while the argument swells in the next room.','Leaving the guest alone would trade a present life for uncertain help.'),
('REPORT_MISSING','LEILA','CHECK_WEST_ROUTE_NPC','Leave for one minute and test the west corridor.','You mark the clearest path with overturned lamps so helpers can carry the guest without guessing.','The safest evacuation begins with knowing where the stretcher can actually go.'),
('PRESSURE_RISE','KELLAN','SHARE_PUBLICLY','Repeat Farah’s alarm-bypass question so the whole lobby hears it.','You say the words loudly and without embellishment. Farah’s denial arrives before you finish the sentence.','Public knowledge is harder for Farah to isolate or quietly discredit.'),
('PRESSURE_RISE','KELLAN','GUARD_EVIDENCE','Stand beside whoever holds the report and do not let it vanish.','You place yourself close enough to see every hand that reaches for the pages. The report no longer has only one witness.','Kellan can protect the trail by watching the person who controls it.'),
('PRESSURE_RISE','KELLAN','JOIN_RESCUE','Run to the east stairwell and become Leila’s second helper.','You reach the stairwell as another crack crosses the plaster. Leila points to the blanket and tells you where to lift.','Another collapse is the danger Kellan feared from the beginning.'),
('PRESSURE_RISE','NIKO','SIDE_WITH_EVIDENCE','Take custody of the report for the authorities.','You tell Farah the pages stay at Bellweather and place them beneath your radio hand. The room sees where your loyalty has moved.','Niko can preserve both order and proof by making custody an official act.'),
('PRESSURE_RISE','NIKO','ORDER_FULL_EVAC','Order every guest and staff member toward the west point.','Your radio call moves through Bellweather. Doors open, names are counted, and the crowd finally begins flowing away from the east wing.','The building is still the most immediate threat to everyone inside it.'),
('PRESSURE_RISE','NIKO','KEEP_FARAH_IN_CHARGE','Keep Farah at the front of the response.','You repeat Farah’s instructions and make the staff follow one voice. The evacuation steadies, and so does her position.','A single command structure may prevent panic even when its leader is compromised.'),
('PRESSURE_RISE','WREN','EVAC_GUEST','Lift with Leila and start moving the guest west.','On Leila’s count, you raise the blanket stretcher. The guest cries out, but the east stairwell begins receding behind you.','The hazard has advanced beyond treatment; movement is now the safer risk.'),
('PRESSURE_RISE','WREN','VERIFY_HAZARD','Return to the damaged wall and read its next movement.','You watch a crack widen through fresh dust and hear water hammering behind the stone. You shout that the east side may have minutes, not hours.','A precise warning can force action before fear or argument wastes the remaining time.'),
('PRESSURE_RISE','WREN','CARRY_EVIDENCE_INFO','Bring Niko your memory of the red-stamped copy.','You leave the stairwell long enough to tell Niko exactly when and where you saw Alden copy the failed report.','Independent observation may stop Farah from reducing the report to Alden’s word.'),
('PRESSURE_RISE','FARAH','PREPARE_EXIT','Move toward the rear hall with the records case close.','You drift from the lobby under the pretense of checking staff, keeping the case tight against your ribs.','Farah’s safest remaining asset may be distance.'),
('PRESSURE_RISE','FARAH','RALLY_GUESTS','Make the storm—not the report—the enemy in the room.','You point to the buckling doors and accuse Alden of wasting the minutes people need to escape. Frightened guests begin following your hands again.','Fear of immediate danger can make people postpone questions about its cause.'),
('PRESSURE_RISE','FARAH','PRESSURE_ALDEN','Tell Alden how many jobs his accusation will destroy.','You speak softly enough that only Alden hears. He looks toward the staff before tightening his grip on the report.','Private guilt may slow Alden without giving the room another accusation to remember.'),
('PRESSURE_RISE','ALDEN','DUPLICATE_EVIDENCE','Photograph every page and send the images to a second device.','You flatten the damp report beneath a lobby lamp and copy each page before the connection fails.','One document in one place is still easy to erase.'),
('PRESSURE_RISE','ALDEN','FIND_WITNESS','Make another person read the failed finding and payment trail.','You guide a second witness through the date, the red stamp, and the false invoice until they can repeat it without you.','Proof survives better when knowledge no longer belongs to one frightened accountant.'),
('PRESSURE_RISE','ALDEN','CONFRONT_NOW','Put the report between you and Farah and demand an answer.','You cross the lobby and read the failed finding to Farah’s face. Everyone nearby hears what she refuses to answer.','The shrinking window may justify abandoning Alden’s usual caution.'),
('PRESSURE_RISE','LEILA','MOVE_WITH_HELP','Lift the guest with whoever has finally come.','You count to three and begin the slow carry toward the west corridor, correcting every uneven step.','Remaining beneath the east ceiling is now more dangerous than movement.'),
('PRESSURE_RISE','LEILA','DEMAND_HELP_PUBLIC','Make the whole lobby hear that the guest cannot move alone.','You shout the guest’s condition and name the exact help you need. The evidence argument falters as people finally turn.','Direct social pressure may succeed where repeated calls did not.'),
('PRESSURE_RISE','LEILA','SHELTER_IN_PLACE','Reinforce the shelter and wait for a clear route.','You pull a heavy table over the guest and stay beneath it, listening to grit strike the tabletop.','If the west route is uncertain, movement may expose the guest to greater harm.'),
('REAR_EXIT','KELLAN','BLOCK_WITH_INFO','Call out the alarm-bypass question before Farah crosses the threshold.','Your words stop Farah with one hand on the door. Every face turns toward the records case at her side.','This may be Kellan’s last chance to make his private observation matter.'),
('REAR_EXIT','KELLAN','FOLLOW_EXIT','Follow Farah into the rain and witness what she does next.','You step through the rear door behind her, close enough that the records cannot disappear without someone seeing how.','A witness outside may preserve the trail even if no one can stop her.'),
('REAR_EXIT','KELLAN','FINAL_RESCUE_K','Leave Farah and run back to help carry the guest.','You turn from the open door and reach Leila in time to take the second side of the stretcher.','Kellan’s fear of another collapse finally outweighs the unanswered secret.'),
('REAR_EXIT','NIKO','STOP_FARAH','Plant yourself in the doorway and order Farah to surrender the records case.','You block the rain with your body. Farah says your name like a warning, but the case remains inside.','Niko’s authority can finally protect Bellweather’s people from its owner.'),
('REAR_EXIT','NIKO','LET_FARAH_GO','Clear the doorway and keep the evacuation moving.','You step aside. Farah disappears into the rain while the west-bound line continues behind you.','Stopping Farah could choke the only working exit at the worst possible moment.'),
('REAR_EXIT','NIKO','FINAL_EVAC_COMMAND','Turn from Farah and personally finish clearing the resort.','You raise the radio and choose the living people still inside. Farah is no longer the order you are trying to preserve.','Duty to the guests may matter more than custody of one person or one case.'),
('REAR_EXIT','WREN','FINISH_GUEST_EVAC','Stay with Leila until the guest reaches the west point.','You keep the stretcher level through the dark corridor. Behind you, part of the east ceiling comes down.','Wren can finish the rescue already placed in their hands.'),
('REAR_EXIT','WREN','JOIN_EXIT_CONFLICT','Bring your red-stamp testimony to the rear door.','You reach the doorway and tell everyone when you saw Alden make the copy. Farah now faces a witness she did not prepare for.','Independent testimony may keep the truth alive if the report changes hands.'),
('REAR_EXIT','WREN','WARN_SECOND_FAILURE','Shout that the east side is moving again and force everyone west.','Your warning cuts across every argument. The room moves as a second deep crack rolls beneath the floor.','The physical threat is now too immediate for anyone to postpone.'),
('REAR_EXIT','FARAH','LEAVE_WITH_RECORDS','Take the records case through the rear door.','You step into the rain with the case held beneath your coat, trading Bellweather’s lobby for the dark service path.','Distance may preserve Farah’s freedom and the last records she controls.'),
('REAR_EXIT','FARAH','DESTROY_LEVERAGE','Ruin whatever evidence can still be used against you.','You tear soaked pages across the spine and grind them into the muddy water spreading across the floor. The room sees exactly what you chose to destroy.','If escape fails, making proof unusable may still protect Farah.'),
('REAR_EXIT','FARAH','STAY_AND_DENY','Stay in the lobby and call the accusation panic.','You release the door and face the room. Your voice remains calm even as fewer people meet your eyes.','Farah may still survive by appearing steadier than everyone accusing her.'),
('REAR_EXIT','ALDEN','HAND_OFF_EVIDENCE','Press the report into another person’s hands.','You surrender the pages to a second keeper and step between Farah and the door.','Proof is safer when Farah cannot erase it by silencing one person.'),
('REAR_EXIT','ALDEN','BLOCK_FARAH_NPC','Reach the rear door and confront Farah yourself.','You stand in the rain-blown doorway and ask Farah to explain the false invoice where everyone can hear.','The last chance to preserve proof may require Alden to stop hiding behind the pages.'),
('REAR_EXIT','ALDEN','CALL_AUTHORITIES','Send the report summary beyond Bellweather before the signal dies.','The message leaves your phone one page at a time. When the final attachment clears, the proof no longer belongs only to the resort.','External custody makes Farah’s physical control less decisive.'),
('REAR_EXIT','LEILA','COMPLETE_EVAC_NPC','Move the guest west with every available hand.','You keep one hand at the guest’s shoulder and count each step until the east wing is behind you.','Getting the guest clear remains Leila’s overriding purpose.'),
('REAR_EXIT','LEILA','CALL_FOR_FINAL_HELP','Refuse an unsafe lift and call one last time.','You brace the guest in place and shout for a named second helper rather than risk dropping them in the dark.','A hurried lift without enough hands could make the injury fatal.'),
('REAR_EXIT','LEILA','SHELTER_FINAL','Pull the guest beneath the strongest remaining cover.','You drag the guest beneath the stair landing and shield their head as debris strikes the floor beyond your shoes.','If the route has failed, shelter may be the only honest choice left.');

UPDATE plot_decision_template_options o
JOIN plot_decision_templates d ON d.template_id=o.template_id
JOIN tmp_cf_options x ON x.event_code=d.event_code AND x.role_code=d.role_code AND x.option_code=o.option_code
SET o.label=x.label,o.result_text=x.result_text,o.rationale_text=x.rationale_text
WHERE d.plot_id=@plot;
DROP TEMPORARY TABLE tmp_cf_options;

UPDATE plot_dynamic_opportunities SET prompt_text=CASE opportunity_code
  WHEN 'WREN_RESCUE_GAP' THEN 'Leila is still alone beside the injured guest when a fresh crack runs across the east archway. You are close enough to help—but also close enough to inspect what the wall is doing.'
  WHEN 'NIKO_LEADERSHIP_GAP' THEN 'The lobby has lost its center. Farah is giving orders, Alden is demanding witnesses, and guests keep turning toward you for a decision.'
  ELSE prompt_text END
WHERE plot_id=@plot;

CREATE TEMPORARY TABLE tmp_cf_dynamic (
  opportunity_code VARCHAR(120), option_code VARCHAR(100), label VARCHAR(500), result_text TEXT, rationale_text TEXT NULL,
  PRIMARY KEY(opportunity_code,option_code)
);
INSERT INTO tmp_cf_dynamic VALUES
('WREN_RESCUE_GAP','JOIN_RESCUE','Drop beside Leila and become the second helper.','You stop measuring the room and take the other side of the injured guest.','Wren’s safety instinct answers the person who has been left without enough help.'),
('WREN_RESCUE_GAP','CHECK_HAZARD','Read the new cracks before anyone attempts a lift.','You study the moving seam and confirm that the east side is growing less stable by the minute.','A correct hazard reading may prevent rescuers from carrying the guest into a second collapse.'),
('WREN_RESCUE_GAP','KEEP_WATCH','Hold back and keep the whole east side in view.','You remain on your feet, watching doors, cracks, and movement while Leila continues alone.','Wren may preserve awareness of the wider hazard, but the guest still lacks hands.'),
('NIKO_LEADERSHIP_GAP','TAKE_COMMAND','Take the radio and give every person a job.','Your assignments cut through the shouting. The lobby begins moving like a team again.','Niko’s clearest strength is turning fear into coordinated action.'),
('NIKO_LEADERSHIP_GAP','CHALLENGE_FARAH','Tell Farah you will not repeat her story until she explains the certificate.','The staff hear you separate Bellweather’s emergency command from Farah’s personal authority.','Niko can lead without continuing to lend Farah his credibility.'),
('NIKO_LEADERSHIP_GAP','STAY_NEUTRAL','Keep the radio silent until the facts are clearer.','You refuse to endorse either Farah or Alden. The room keeps waiting for the order you do not give.','Neutrality protects Niko from a false choice but leaves the group without direction.');
UPDATE plot_dynamic_opportunity_options o
JOIN plot_dynamic_opportunities d ON d.dynamic_opportunity_id=o.dynamic_opportunity_id
JOIN tmp_cf_dynamic x ON x.opportunity_code=d.opportunity_code AND x.option_code=o.option_code
SET o.label=x.label,o.result_text=x.result_text,o.rationale_text=x.rationale_text
WHERE d.plot_id=@plot;
DROP TEMPORARY TABLE tmp_cf_dynamic;

UPDATE plot_resolution_fragments SET fragment_text=CASE
  WHEN section_code='PEOPLE' AND priority=10 THEN 'At the west evacuation point, Leila finally lowers the stretcher. The injured guest is pale and shaking, but alive—and the east ceiling collapses only after the last pair of feet clears the corridor.'
  WHEN section_code='PEOPLE' AND priority=20 THEN 'A second crash swallows Leila’s next call from the east wing. The injured guest remains inside the danger while dust rolls through the doorway like smoke.'
  WHEN section_code='PEOPLE' AND priority=30 THEN 'The stretcher is halfway through the west corridor when the lights fail again. Hands tighten, someone begins counting steps aloud, and the rescue continues into the dark.'
  WHEN section_code='PEOPLE' AND priority=40 THEN 'Leila keeps two fingers against the guest’s pulse beneath the stair landing. The bleeding has slowed, but the failing east wing still surrounds them.'
  WHEN section_code='PEOPLE' AND priority=100 THEN 'When the shouting fades, no one can say with certainty whether the injured guest ever reached the west side.'
  WHEN section_code='TRUTH' AND priority=10 THEN 'Outside Bellweather, a phone receives the failed finding, the inspection date, and the false invoice. Whatever happens to the wet pages inside, the truth has already crossed the mountain.'
  WHEN section_code='TRUTH' AND priority=20 THEN 'The report no longer has a single throat Farah can close. Copies, photographs, and witnesses now carry the same red word: FAILED.'
  WHEN section_code='TRUTH' AND priority=30 THEN 'Someone besides Alden can now describe the failed finding and the payment trail. Farah may still deny the pages, but she can no longer reduce them to one man’s story.'
  WHEN section_code='TRUTH' AND priority=40 THEN 'Alden keeps the rain-softened report beneath his coat. It survives the night, but for now the truth still depends on him surviving with it.'
  WHEN section_code='TRUTH' AND priority=50 THEN 'The failed report ends the night in Farah’s hands. The paper survives, but every locked door between it and the outside world belongs to her.'
  WHEN section_code='TRUTH' AND priority=100 THEN 'The original report remains beyond secure reach. Still, too many people saw the red stamp, the records case, and Farah’s fear for the night to be explained away as weather alone.'
  WHEN section_code='FARAH' AND priority=10 THEN 'Niko blocks the rear door. Rain lashes Farah’s back as she slowly lowers the records case, realizing Bellweather’s staff are no longer waiting for her permission.'
  WHEN section_code='FARAH' AND priority=20 THEN 'Farah disappears down the flooded service path with the records held beneath her coat. By the time anyone follows, the mountain and the rain have erased her footprints.'
  WHEN section_code='FARAH' AND priority=30 THEN 'Farah remains framed in the rear doorway, one foot in the rain and one inside the resort, as the people behind her decide whether she is still their owner—or the reason they nearly died.'
  WHEN section_code='FARAH' AND priority=40 THEN 'Farah never crosses the threshold. She stays near the rear door with the records case close, watching the path outside vanish beneath the runoff.'
  WHEN section_code='FARAH' AND priority=100 THEN 'Farah remains inside Bellweather. She keeps speaking in the calm voice that once controlled the room, but now the only answer is rain striking broken glass.'
  ELSE fragment_text END
WHERE plot_id=@plot;

UPDATE plot_no_action_effects SET detail_text=CASE
  WHEN event_code='WALL_SHIFT' AND role_code='KELLAN' THEN 'Farah reaches the office corridor without Kellan following.'
  WHEN event_code='WALL_SHIFT' AND role_code='NIKO' THEN 'Without Niko’s direction, guests crowd the lobby and block one another’s path.'
  WHEN event_code='WALL_SHIFT' AND role_code='WREN' THEN 'Leila remains alone with the injured guest while the east side continues to move.'
  WHEN event_code='REPORT_MISSING' AND role_code='KELLAN' THEN 'Kellan keeps the alarm question to himself, leaving Alden without the corroboration.'
  WHEN event_code='REPORT_MISSING' AND role_code='NIKO' THEN 'Niko’s silence lets Farah’s explanation remain the strongest voice in the lobby.'
  WHEN event_code='REPORT_MISSING' AND role_code='WREN' THEN 'Wren does not commit, and Leila still lacks a second helper.'
  WHEN event_code='PRESSURE_RISE' AND role_code='KELLAN' THEN 'Farah gains distance while Kellan holds his information back.'
  WHEN event_code='PRESSURE_RISE' AND role_code='NIKO' THEN 'The lobby fractures into competing instructions while Niko withholds his authority.'
  WHEN event_code='PRESSURE_RISE' AND role_code='WREN' THEN 'The east-side danger advances without Wren joining the rescue or sounding a warning.'
  WHEN event_code='REAR_EXIT' AND role_code='KELLAN' THEN 'Farah reaches the threshold without Kellan making the alarm question public.'
  WHEN event_code='REAR_EXIT' AND role_code='NIKO' AND target_code='FARAH_EXIT_STATUS' THEN 'Niko does not block the rear door, and Farah leaves.'
  WHEN event_code='REAR_EXIT' AND role_code='NIKO' THEN 'Farah meets no formal resistance at the exit.'
  WHEN event_code='REAR_EXIT' AND role_code='WREN' AND target_code='GUEST_STATUS' THEN 'The rescue remains unfinished as the east wing fails again.'
  WHEN event_code='REAR_EXIT' AND role_code='WREN' THEN 'The injured guest remains exposed beneath the failing east side.'
  ELSE detail_text END
WHERE plot_id=@plot;

UPDATE plot_world_event_effects SET detail_text=CASE
  WHEN event_code='REPORT_MISSING' AND target_code='REPORT_HOLDER' THEN 'Alden produces the copied report in full view of the lobby and keeps it in his hands.'
  WHEN event_code='REPORT_MISSING' AND target_code='FAILED_REPORT_STATE' THEN 'The red-stamped report is now visible, identified, and physically recoverable.'
  ELSE detail_text END
WHERE plot_id=@plot;

INSERT IGNORE INTO schema_migrations(migration)
VALUES('044_rewrite_concealed_failure_story_copy.sql');
