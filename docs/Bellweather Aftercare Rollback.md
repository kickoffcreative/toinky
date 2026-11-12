# Bellweather Aftercare Rollback

Before this batch, REAR_EXIT / LEILA has three options: COMPLETE_EVAC_NPC,
CALL_FOR_FINAL_HELP, and SHELTER_FINAL. They concern an unfinished evacuation.
Their existing conditions and effects are preserved.

Original decision prompt, captured November 12, 2026:

The guest must move now. The route, the available help, and the failing ceiling will decide how.

Batch additions: STAY_WITH_PATIENT_AFTER_EVAC and RECORD_INJURY_AFTER_EVAC,
each gated by WORLD_STATE GUEST_STATUS EQ EVACUATED. The first provides
reassurance and changes Group Trust; the second records an injury account and
changes Evidence. Neither moves the guest or rewrites rescue/evidence custody
facts. Deactivate the new options to roll back; restore the original prompt
above if undoing the whole change. Preserve the prior published revision.

Original new-option state: these two option records do not exist before the
batch. Their effects and conditions are new records belonging only to them.
