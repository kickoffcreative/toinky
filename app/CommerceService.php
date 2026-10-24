<?php
final class CommerceService
{
    private const GRANT_TYPES = ['FREE_FIRST','PURCHASE','ADMIN_GRANT','ADMIN_REVOKE','REFUND_REVOKE'];

    public static function settings(): array
    {
        $raw = [];
        try {
            $q = db()->query("SELECT setting_key,setting_value FROM platform_settings WHERE setting_key IN('COMMERCE_ENABLED','COMMERCE_FREE_FIRST','COMMERCE_BUNDLES')");
            foreach ($q->fetchAll() as $row) $raw[(string)$row['setting_key']] = (string)$row['setting_value'];
        } catch (Throwable $e) { $raw = []; }
        $decoded = json_decode((string)($raw['COMMERCE_BUNDLES'] ?? ''), true);
        if (!is_array($decoded) || !isset($decoded['bundles']) || !is_array($decoded['bundles'])) $decoded = self::defaultBundles();
        $bundles = [];
        foreach ($decoded['bundles'] as $b) {
            if (!is_array($b)) continue;
            $key = preg_replace('/[^a-z0-9_]/', '', strtolower((string)($b['key'] ?? '')));
            $occ = (int)($b['occasions'] ?? 0);
            $price = (int)($b['price_cents'] ?? 0);
            if ($key === '' || $occ < 1 || $price < 1) continue;
            $bundles[] = ['key' => $key, 'occasions' => $occ, 'price_cents' => $price];
        }
        if ($bundles === []) $bundles = self::defaultBundles()['bundles'];
        $currency = strtoupper((string)($decoded['currency'] ?? 'USD'));
        if ($currency === '') $currency = 'USD';
        return ['enabled' => ($raw['COMMERCE_ENABLED'] ?? '0') === '1', 'free_first' => ($raw['COMMERCE_FREE_FIRST'] ?? '1') !== '0', 'currency' => $currency, 'bundles' => $bundles];
    }

    public static function enabled(): bool
    {
        return self::settings()['enabled'];
    }

    public static function bundle(string $key): ?array
    {
        foreach (self::settings()['bundles'] as $b) if ($b['key'] === $key) return $b;
        return null;
    }

    public static function saveSettings(int $adminId, array $input): void
    {
        $current = self::settings();
        $bundles = [];
        foreach ($current['bundles'] as $b) {
            $occ = (int)($input['occasions_' . $b['key']] ?? $b['occasions']);
            $dollars = (string)($input['price_' . $b['key']] ?? '');
            $price = $dollars === '' ? $b['price_cents'] : (int)round(((float)$dollars) * 100);
            if ($occ < 1 || $occ > 50) throw new RuntimeException('Each bundle must hold between 1 and 50 occasions.');
            if ($price < 50 || $price > 100000) throw new RuntimeException('Bundle prices must be between $0.50 and $1,000.');
            $bundles[] = ['key' => $b['key'], 'occasions' => $occ, 'price_cents' => $price];
        }
        $values = [
            'COMMERCE_ENABLED' => ((string)($input['commerce_enabled'] ?? '0')) === '1' ? '1' : '0',
            'COMMERCE_FREE_FIRST' => ((string)($input['commerce_free_first'] ?? '0')) === '1' ? '1' : '0',
            'COMMERCE_BUNDLES' => json_encode(['currency' => $current['currency'], 'bundles' => $bundles], JSON_UNESCAPED_SLASHES),
        ];
        $q = db()->prepare('INSERT INTO platform_settings(setting_key,setting_value,updated_by_user_id) VALUES(?,?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_by_user_id=VALUES(updated_by_user_id)');
        foreach ($values as $k => $v) $q->execute([$k, $v, $adminId]);
    }

    public static function balance(int $userId): array
    {
        $pdo = db();
        $in = implode(',', array_map(static fn($t) => $pdo->quote($t), self::GRANT_TYPES));
        $q = $pdo->prepare("SELECT COALESCE(SUM(occasions),0) FROM host_occasion_ledger WHERE user_id=? AND entry_type IN($in)");
        $q->execute([$userId]);
        $granted = (int)$q->fetchColumn();
        $q = $pdo->prepare("SELECT COUNT(*) FROM host_occasion_ledger WHERE user_id=? AND entry_type='CONSUME'");
        $q->execute([$userId]);
        $consumed = (int)$q->fetchColumn();
        $q = $pdo->prepare("SELECT COUNT(*) FROM host_occasion_ledger r WHERE r.user_id=? AND r.entry_type='RESERVE' AND NOT EXISTS(SELECT 1 FROM host_occasion_ledger x WHERE x.game_id=r.game_id AND x.entry_type IN('CONSUME','RELEASE'))");
        $q->execute([$userId]);
        $reserved = (int)$q->fetchColumn();
        return ['granted' => $granted, 'consumed' => $consumed, 'reserved' => $reserved, 'available' => $granted - $consumed - $reserved];
    }

    public static function grantFreeFirst(int $userId): bool
    {
        if (!self::settings()['free_first']) return false;
        if (self::isAdmin($userId)) return false;
        $q = db()->prepare("INSERT IGNORE INTO host_occasion_ledger(user_id,entry_type,occasions,note) VALUES(?,'FREE_FIRST',1,'Your first occasion is on us.')");
        $q->execute([$userId]);
        return $q->rowCount() > 0;
    }

    public static function assertCanHost(int $userId): void
    {
        if (self::isAdmin($userId)) return;
        self::grantFreeFirst($userId);
        if (self::balance($userId)['available'] < 1) throw new RuntimeException('You have no occasions available. Buy more occasions to host another game.');
    }

    public static function reserveForGame(int $userId, int $gameId): void
    {
        if (self::isAdmin($userId)) return;
        $pdo = db();
        $lock = $pdo->prepare('SELECT ledger_id FROM host_occasion_ledger WHERE user_id=? FOR UPDATE');
        $lock->execute([$userId]);
        $lock->fetchAll();
        $q = $pdo->prepare("SELECT COUNT(*) FROM host_occasion_ledger WHERE game_id=? AND entry_type='RESERVE'");
        $q->execute([$gameId]);
        if ((int)$q->fetchColumn() > 0) return;
        if (self::balance($userId)['available'] < 1) throw new RuntimeException('You have no occasions available. Buy more occasions to host another game.');
        $pdo->prepare("INSERT INTO host_occasion_ledger(user_id,entry_type,occasions,game_id,note) VALUES(?,'RESERVE',1,?,'Held for this lobby.')")->execute([$userId, $gameId]);
    }

    public static function consumeForGame(int $gameId): void
    {
        $pdo = db();
        $q = $pdo->prepare("SELECT user_id FROM host_occasion_ledger WHERE game_id=? AND entry_type='RESERVE'");
        $q->execute([$gameId]);
        $userId = $q->fetchColumn();
        if ($userId === false) return;
        $pdo->prepare("INSERT IGNORE INTO host_occasion_ledger(user_id,entry_type,occasions,game_id,note) VALUES(?,'CONSUME',1,?,'Game started.')")->execute([(int)$userId, $gameId]);
    }

    public static function releaseForGame(int $gameId): void
    {
        $pdo = db();
        $q = $pdo->prepare("SELECT r.user_id FROM host_occasion_ledger r WHERE r.game_id=? AND r.entry_type='RESERVE' AND NOT EXISTS(SELECT 1 FROM host_occasion_ledger x WHERE x.game_id=r.game_id AND x.entry_type IN('CONSUME','RELEASE'))");
        $q->execute([$gameId]);
        $userId = $q->fetchColumn();
        if ($userId === false) return;
        $pdo->prepare("INSERT IGNORE INTO host_occasion_ledger(user_id,entry_type,occasions,game_id,note) VALUES(?,'RELEASE',1,?,'Lobby closed before the game started.')")->execute([(int)$userId, $gameId]);
    }

    public static function adminAdjust(int $adminId, int $userId, int $occasions, string $note): void
    {
        if ($userId < 1) throw new RuntimeException('Choose a valid user.');
        if ($occasions === 0 || abs($occasions) > 100) throw new RuntimeException('Enter an adjustment between -100 and 100 occasions.');
        $q = db()->prepare('SELECT COUNT(*) FROM users WHERE user_id=?');
        $q->execute([$userId]);
        if ((int)$q->fetchColumn() === 0) throw new RuntimeException('That user does not exist.');
        if ($occasions < 0) {
            $b = self::balance($userId);
            if ($b['granted'] + $occasions < $b['consumed'] + $b['reserved']) throw new RuntimeException('That would revoke occasions the user has already spent or holds.');
        }
        $note = trim($note);
        if ($note === '') $note = 'Admin adjustment.';
        db()->prepare('INSERT INTO host_occasion_ledger(user_id,entry_type,occasions,note,created_by_user_id) VALUES(?,?,?,?,?)')->execute([$userId, $occasions > 0 ? 'ADMIN_GRANT' : 'ADMIN_REVOKE', $occasions, $note, $adminId]);
    }

    public static function recentLedger(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $q = db()->query("SELECT l.*,u.display_name FROM host_occasion_ledger l JOIN users u ON u.user_id=l.user_id ORDER BY l.ledger_id DESC LIMIT $limit");
        return $q->fetchAll();
    }

    private static function isAdmin(int $userId): bool
    {
        $q = db()->prepare('SELECT is_admin FROM users WHERE user_id=?');
        $q->execute([$userId]);
        return (int)$q->fetchColumn() === 1;
    }

    private static function defaultBundles(): array
    {
        return ['currency' => 'USD', 'bundles' => [['key' => 'single', 'occasions' => 1, 'price_cents' => 500], ['key' => 'trio', 'occasions' => 3, 'price_cents' => 1200], ['key' => 'five', 'occasions' => 5, 'price_cents' => 1900]]];
    }
}
