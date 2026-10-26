<?php

final class NarratorService
{
    public static function enabled(): bool
    {
        try {
            $q = db()->query("SELECT setting_value FROM platform_settings WHERE setting_key='NARRATOR_ENABLED'");
            $row = $q->fetch();
            return $row !== false && (string)$row['setting_value'] === '1';
        } catch (Throwable $e) {
            return false;
        }
    }

    public static function saveSettings(int $adminId, array $input): void
    {
        $enabled = ((string)($input['narrator_enabled'] ?? '0') === '1') ? '1' : '0';
        $q = db()->prepare('INSERT INTO platform_settings(setting_key,setting_value,updated_by_user_id) VALUES(?,?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_by_user_id=VALUES(updated_by_user_id)');
        $q->execute(['NARRATOR_ENABLED', $enabled, $adminId]);
    }
}
