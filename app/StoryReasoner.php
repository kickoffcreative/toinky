<?php
interface StoryReasoner
{
    /**
     * Select one engine-approved option. Implementations must never invent
     * option codes or mutate world state directly.
     *
     * Expected return keys:
     * selected_action, confidence, reason, provider, model, interaction_id.
     */
    public function selectOption(array $context,array $options): array;
}
