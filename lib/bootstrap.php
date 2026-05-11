<?php

date_default_timezone_set('America/Chicago');

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $path = __DIR__ . '/../db.sqlite';
        $pdo = new PDO('sqlite:' . $path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON');
    }
    return $pdo;
}

function run_migrations(PDO $pdo): void {
    $dir = __DIR__ . '/../migrations';
    if (!is_dir($dir)) {
        return;
    }

    $files = glob($dir . '/*.sql') ?: [];
    sort($files);

    foreach ($files as $file) {
        $sql = trim((string) file_get_contents($file));
        if ($sql !== '') {
            $pdo->exec($sql);
        }
    }
}

function current_staff(): array {
    $stmt = db()->prepare('SELECT * FROM staff WHERE id = 1');
    $stmt->execute();
    $row = $stmt->fetch();
    if (!$row) {
        throw new RuntimeException('No staff row #1 found. Did you run `php seed.php`?');
    }
    return $row;
}

function audit_log(string $action, string $entity_type, int $entity_id, array $details = []): void {
    $staff = current_staff();
    $stmt = db()->prepare('
        INSERT INTO audit_log (staff_id, action, entity_type, entity_id, details)
        VALUES (?, ?, ?, ?, ?)
    ');
    $stmt->execute([
        $staff['id'],
        $action,
        $entity_type,
        $entity_id,
        json_encode($details),
    ]);
}

function random_token(int $bytes = 16): string {
    return bin2hex(random_bytes($bytes));
}

function random_readable_suffix(int $length = 3): string {
    $alphabet = '23456789abcdefghjkmnpqrstuvwxyz';
    $suffix = '';
    $max = strlen($alphabet) - 1;

    for ($i = 0; $i < $length; $i++) {
        $suffix .= $alphabet[random_int(0, $max)];
    }

    return $suffix;
}

function slugify_title(string $title): string {
    $slug = strtolower(trim($title));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
    $slug = trim($slug, '-');

    if ($slug === '') {
        $slug = 'document';
    }

    $slug = substr($slug, 0, 32);
    return trim($slug, '-') ?: 'document';
}

function readable_id_exists(PDO $pdo, string $readableId): bool {
    $stmt = $pdo->prepare('SELECT 1 FROM documents WHERE readable_id = ? LIMIT 1');
    $stmt->execute([$readableId]);
    return (bool) $stmt->fetchColumn();
}

function generate_readable_id(PDO $pdo, string $title): string {
    $base = slugify_title($title);

    for ($i = 0; $i < 12; $i++) {
        $candidate = $base . '-' . random_readable_suffix();
        if (!readable_id_exists($pdo, $candidate)) {
            return $candidate;
        }
    }

    do {
        $candidate = 'doc-' . random_readable_suffix(8);
    } while (readable_id_exists($pdo, $candidate));

    return $candidate;
}

function parse_publish_at(?string $value): ?string {
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    $timezone = new DateTimeZone(date_default_timezone_get());
    $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value, $timezone);
    $errors = DateTimeImmutable::getLastErrors();
    $hasErrors = is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0);

    if (!$date || $hasErrors) {
        throw new InvalidArgumentException('Publish time must be a valid date and time.');
    }

    return $date->format('Y-m-d H:i:s');
}

function datetime_local_value(?string $value): string {
    if (!$value) {
        return '';
    }

    $timestamp = strtotime($value);
    return $timestamp ? date('Y-m-d\TH:i', $timestamp) : '';
}

function format_publish_at(?string $value): string {
    if (!$value) {
        return 'Immediately';
    }

    $timestamp = strtotime($value);
    return $timestamp ? date('M j, Y g:i A T', $timestamp) : $value;
}

function is_document_published(array $document, ?DateTimeImmutable $now = null): bool {
    if (empty($document['publish_at'])) {
        return true;
    }

    $publishAt = strtotime((string) $document['publish_at']);
    if (!$publishAt) {
        return true;
    }

    $nowTimestamp = $now ? $now->getTimestamp() : time();
    return $publishAt <= $nowTimestamp;
}

function document_label(array $document): string {
    $readableId = trim((string) ($document['readable_id'] ?? ''));
    return $readableId !== '' ? $readableId : '#' . (int) $document['id'];
}

function find_document_by_reference(string $reference): ?array {
    $reference = trim($reference);
    if ($reference === '') {
        return null;
    }

    if (ctype_digit($reference)) {
        $stmt = db()->prepare('SELECT * FROM documents WHERE id = ?');
        $stmt->execute([(int) $reference]);
        $row = $stmt->fetch();
        if ($row) {
            return $row;
        }
    }

    $stmt = db()->prepare('SELECT * FROM documents WHERE readable_id = ?');
    $stmt->execute([$reference]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function escape_like(string $value): string {
    return strtr($value, [
        '\\' => '\\\\',
        '%' => '\\%',
        '_' => '\\_',
    ]);
}

function search_documents(string $query = ''): array {
    $query = trim($query);
    $sql = '
        SELECT d.*, s.name AS creator_name
        FROM documents d
        JOIN staff s ON s.id = d.created_by
    ';
    $params = [];

    if ($query !== '') {
        $sql .= '
            WHERE d.title LIKE ? ESCAPE \'\\\'
               OR d.readable_id LIKE ? ESCAPE \'\\\'
        ';
        $needle = '%' . escape_like($query) . '%';
        $params = [$needle, $needle];
    }

    $sql .= ' ORDER BY d.created_at DESC, d.id DESC';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function create_document(string $title, string $body, int $staffId, ?string $publishAt = null): array {
    $readableId = generate_readable_id(db(), $title);
    $stmt = db()->prepare('
        INSERT INTO documents (title, body, created_by, readable_id, publish_at)
        VALUES (?, ?, ?, ?, ?)
    ');
    $stmt->execute([$title, $body, $staffId, $readableId, $publishAt]);
    $docId = (int) db()->lastInsertId();

    audit_log('create', 'document', $docId, [
        'title' => $title,
        'readable_id' => $readableId,
        'publish_at' => $publishAt,
    ]);

    return find_document_by_reference($readableId) ?: [
        'id' => $docId,
        'title' => $title,
        'body' => $body,
        'created_by' => $staffId,
        'readable_id' => $readableId,
        'publish_at' => $publishAt,
    ];
}

function update_document_schedule(int $documentId, ?string $publishAt): void {
    $stmt = db()->prepare('SELECT id, publish_at FROM documents WHERE id = ?');
    $stmt->execute([$documentId]);
    $document = $stmt->fetch();

    if (!$document) {
        throw new RuntimeException('Document not found.');
    }

    $oldPublishAt = $document['publish_at'] ?? null;
    if ($oldPublishAt === $publishAt) {
        return;
    }

    $stmt = db()->prepare('UPDATE documents SET publish_at = ? WHERE id = ?');
    $stmt->execute([$publishAt, $documentId]);

    audit_log('schedule_update', 'document', $documentId, [
        'from' => $oldPublishAt,
        'to' => $publishAt,
    ]);
}

function create_share(array $document, string $recipientEmail): string {
    $token = random_token();
    $stmt = db()->prepare('
        INSERT INTO shares (document_id, token, recipient_email)
        VALUES (?, ?, ?)
    ');
    $stmt->execute([(int) $document['id'], $token, $recipientEmail]);
    $shareId = (int) db()->lastInsertId();

    audit_log('create', 'share', $shareId, [
        'document_id' => (int) $document['id'],
        'document_readable_id' => document_label($document),
        'recipient_email' => $recipientEmail,
    ]);

    return $token;
}

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
