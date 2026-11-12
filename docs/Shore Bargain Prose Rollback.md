# Shore Bargain Prose Rollback

Studio values captured November 12, 2026 before this batch's edits.
Story FORGOTTEN_SHORE, plot 5. Stable codes, effects and conditions are unchanged.
Restore these fields through the studio, then submit/review a new fixed revision.

## CAVE_BREATHES / GHOST — Prompt

The door is stirring, and Renee knows its price better than anyone alive: it opens for one voice, and closes on another. She has been this island's keeper for thirteen years. She can offer the bargain — herself, for Bernard — or refuse it and let the door keep what it took.

## OFFER_BARGAIN — Label

Offer the bargain — she stays, Bernard goes

## OFFER_BARGAIN — Player-Facing Result

[character] offers the bargain, and the cave answers with a breath warm against her face: she will trade herself for the ones she loves, and the portal opens on that price. There is no haggling with a door. She steps forward, and the island takes what it is owed. The price is her watch — she keeps the island through the night, and at dawn her watch ends and she is free.

Existing effects: PORTAL Open; LOCATION Cave of Sorrows. These enable the later
BRIANNA RESURRECT_FATHER option; they do not themselves set BERNARD Returned.

## LONG_NIGHT / BRIANNA / RESURRECT_FATHER — Player-Facing Result

[character] chooses the impossible. If the door opens for one voice and closes on another, then let it open — and let her father walk out of thirteen years of dark.

Existing effects: BRIANNA_GOAL Resurrect Father; BERNARD Returned; LOCATION The
Siren's Fangs. Existing condition: PORTAL EQ Open.

## BRIANNAS_ANSWER — Section Order 40, Priority 100

Condition BRIANNA_GOAL EQ Resurrect Father.

Bernard Laureano steps out of the Cave of Sorrows thirteen years after the island took him, blinking in the dawn, his daughter's name the first word he says. Behind him, the door stands shut on Renée, the woman who paid his way back. Brianna has her father. The arithmetic of that sentence will take the rest of her life to finish.

## BRIANNAS_ANSWER — Section Order 10, Priority 100

Condition BRIANNA_GOAL EQ Find Parents.

Brianna came to Imago Island with a lie in her luggage and a thirteen-year-old question. She leaves with the truth entire: a mother the sea gave back by halves, a father's name spoken aloud at last. It is not the ending she rehearsed on the yacht. It is the one the island had been keeping for her, in a kept camp and a carved charm, all these years. Her mother is freed from her watch at dawn, and stays on Imago by choice as its keeper, and her father stays lost to the Cave of Sorrows — what Brianna carries home is the truth of them, not them themselves.
