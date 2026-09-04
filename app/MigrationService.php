<?php
final class MigrationService {
    public static function run(): void {
        static $completed=false;
        if($completed)return;
        $pdo=db();
        $dir=__DIR__.'/../database/migrations';
        if(!is_dir($dir)) return;
        $files=glob($dir.'/*.sql') ?: [];
        sort($files,SORT_STRING);
        if(PHP_SAPI!=='cli'&&self::deploymentMarkerMatches($dir,$files)){$completed=true;return;}
        $lock=false;
        try{
            $pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (migration VARCHAR(190) PRIMARY KEY, applied_at_utc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            $lock=(bool)$pdo->query("SELECT GET_LOCK('toinky:schema-migrations',30)")->fetchColumn();
            if(!$lock)throw new RuntimeException('Another migration is already running.');
        foreach($files as $file){
            $name=basename($file);
            $q=$pdo->prepare('SELECT 1 FROM schema_migrations WHERE migration=?');$q->execute([$name]);
            if($q->fetchColumn()) continue;
            $sql=file_get_contents($file);if($sql===false) throw new RuntimeException('Unable to read migration '.$name);
            try{$pdo->exec($sql);$pdo->prepare('INSERT IGNORE INTO schema_migrations(migration) VALUES(?)')->execute([$name]);}
            catch(Throwable $e){throw new RuntimeException('Migration '.$name.' failed: '.$e->getMessage(),0,$e);}
        }
            self::writeDeploymentMarker($dir,$files);
            $completed=true;
        } finally { if($lock){try{$pdo->query("SELECT RELEASE_LOCK('toinky:schema-migrations')");}catch(Throwable $ignored){}} }
    }

    private static function deploymentMarkerMatches(string $dir,array $files): bool
    {
        $marker=dirname(__DIR__).'/config/.migrations-ready';
        if(!is_file($marker))return false;
        $expected=self::deploymentSignature($dir,$files);
        return trim((string)@file_get_contents($marker))===$expected;
    }

    private static function writeDeploymentMarker(string $dir,array $files): void
    {
        $marker=dirname(__DIR__).'/config/.migrations-ready';
        @file_put_contents($marker,self::deploymentSignature($dir,$files).PHP_EOL,LOCK_EX);
    }

    private static function deploymentSignature(string $dir,array $files): string
    {
        $latest=$files?basename($files[count($files)-1]):'';
        return (string)((int)@filemtime($dir)).':'.count($files).':'.$latest;
    }
}
