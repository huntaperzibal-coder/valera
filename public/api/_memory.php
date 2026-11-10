<?php
declare(strict_types=1);

/* Auto-memory. Only compact non-sensitive interests and story milestones are stored. */
function foxyMemoryDb(): ?PDO {
    if (!extension_loaded('pdo_sqlite')) return null;
    $folder = dirname(__DIR__, 3) . '/valera-private';
    if (!is_dir($folder)) return null;
    try {
        $db = new PDO('sqlite:' . $folder . '/foxy-memory.sqlite');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->exec('PRAGMA busy_timeout = 3000');
        $db->exec('CREATE TABLE IF NOT EXISTS memories (id INTEGER PRIMARY KEY AUTOINCREMENT, kind TEXT NOT NULL, memory_key TEXT NOT NULL UNIQUE, content TEXT NOT NULL, created_at INTEGER NOT NULL, updated_at INTEGER NOT NULL)');
        @chmod($folder . '/foxy-memory.sqlite', 0600);
        return $db;
    } catch (Throwable $e) { return null; }
}
function foxyMemoryRows(PDO $db, int $limit = 25): array {
    $q = $db->query('SELECT id, kind, content, updated_at FROM memories ORDER BY updated_at DESC, id DESC LIMIT ' . max(1,min(50,$limit)));
    return $q ? $q->fetchAll(PDO::FETCH_ASSOC) : [];
}
function foxyMemoryIsSafe(string $text): bool {
    if (mb_strlen($text, 'UTF-8') < 5 || mb_strlen($text, 'UTF-8') > 160) return false;
    // Defense in depth; this is not a substitute for testing and reviewing memory.
    if (preg_match('/(?:https?:|www\.|@|\+?\d[\d\s\-()]{8,}|парол|телефон|адрес|улиц|квартир|школ[аыеу]|болезн|диагноз|лекарств|плачет|ссор|побил|секрет|банк|карт[аыу]|паспорт|геолокац)/iu', $text)) return false;
    return true;
}
function foxyMemoryUpdate(PDO $db, array $entries): int {
    $allowed = ['interest','preference','story'];
    $saved = 0; $now = time();
    // Compatible with older SQLite builds: UPDATE first, INSERT if no existing row.
    $update = $db->prepare('UPDATE memories SET kind = :kind, content = :content, updated_at = :updated WHERE memory_key = :key');
    $insert = $db->prepare('INSERT INTO memories (kind, memory_key, content, created_at, updated_at) VALUES (:kind, :key, :content, :created, :updated)');
    foreach (array_slice($entries, 0, 3) as $entry) {
        if (!is_array($entry) || !in_array($entry['kind'] ?? '', $allowed, true)) continue;
        $content = trim((string)($entry['content'] ?? ''));
        $key = mb_strtolower(trim((string)($entry['key'] ?? '')), 'UTF-8');
        if (!foxyMemoryIsSafe($content) || !preg_match('/^[a-z0-9_\-]{3,45}$/', $key)) continue;
        $update->execute([':kind'=>$entry['kind'],':content'=>$content,':updated'=>$now,':key'=>$key]);
        if ($update->rowCount() === 0) {
            $insert->execute([':kind'=>$entry['kind'],':key'=>$key,':content'=>$content,':created'=>$now,':updated'=>$now]);
        }
        $saved++;
    }
    // Keep at most 40 useful memories.
    $db->exec('DELETE FROM memories WHERE id NOT IN (SELECT id FROM memories ORDER BY updated_at DESC, id DESC LIMIT 40)');
    return $saved;
}
