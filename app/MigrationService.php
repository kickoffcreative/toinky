<?php
final class MigrationService {
    public static function run(): void {
        $pdo=db();
        $pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (migration VARCHAR(190) PRIMARY KEY, applied_at_utc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $dir=__DIR__.'/../database/migrations';
        if(!is_dir($dir)) return;
        $files=glob($dir.'/*.sql') ?: [];
        sort($files,SORT_STRING);
        foreach($files as $file){
            $name=basename($file);
            $q=$pdo->prepare('SELECT 1 FROM schema_migrations WHERE migration=?');$q->execute([$name]);
            if($q->fetchColumn()) continue;
            $sql=file_get_contents($file);if($sql===false) throw new RuntimeException('Unable to read migration '.$name);
            try{$pdo->exec($sql);$pdo->prepare('INSERT IGNORE INTO schema_migrations(migration) VALUES(?)')->execute([$name]);}
            catch(Throwable $e){throw new RuntimeException('Migration '.$name.' failed: '.$e->getMessage(),0,$e);}
        }
    }
}
