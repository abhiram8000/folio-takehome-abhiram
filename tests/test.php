<?php

require __DIR__ . '/../lib/bootstrap.php';

system('php ' . escapeshellarg(__DIR__ . '/../seed.php') . ' > /dev/null', $rc);
if ($rc !== 0) {
    fwrite(STDERR, "seed failed\n");
    exit(1);
}

$pass = 0;
$fail = 0;

function test(string $name, callable $fn): void {
    global $pass, $fail;
    try {
        $fn();
        echo "  [ok] {$name}\n";
        $pass++;
    } catch (Throwable $e) {
        echo "  [FAIL] {$name}: " . $e->getMessage() . "\n";
        $fail++;
    }
}

function assert_true($cond, string $msg = ''): void {
    if (!$cond) {
        throw new RuntimeException($msg !== '' ? $msg : 'expected true');
    }
}

function assert_same($expected, $actual, string $msg = ''): void {
    if ($expected !== $actual) {
        throw new RuntimeException($msg !== '' ? $msg : 'expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

echo "\nRunning tests:\n";

test('seeded share link resolves to the seeded document', function () {
    $stmt = db()->prepare('
        SELECT d.title
        FROM shares s
        JOIN documents d ON d.id = s.document_id
        LIMIT 1
    ');
    $stmt->execute();
    $row = $stmt->fetch();
    assert_true($row !== false, 'expected the seeded share to resolve');
    assert_true($row['title'] === 'Welcome Packet', 'unexpected title: ' . var_export($row['title'], true));
});

test('created documents receive short readable ids', function () {
    $doc = create_document('Council Welcome Packet', 'Body text', 1);
    $label = document_label($doc);

    assert_true((bool) preg_match('/^council-welcome-packet-[a-z0-9]{3}$/', $label), 'unexpected readable id: ' . $label);

    $found = find_document_by_reference($label);
    assert_true($found !== null, 'expected readable id to resolve');
    assert_same('Council Welcome Packet', $found['title']);

    $stmt = db()->prepare("
        SELECT details
        FROM audit_log
        WHERE action = 'create'
          AND entity_type = 'document'
          AND entity_id = ?
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([(int) $doc['id']]);
    $details = json_decode((string) $stmt->fetchColumn(), true);
    assert_same($label, $details['readable_id'] ?? null);
});

test('future scheduled documents are not published yet', function () {
    $future = date('Y-m-d H:i:s', strtotime('+1 hour'));
    $past = date('Y-m-d H:i:s', strtotime('-1 hour'));

    assert_true(!is_document_published(['publish_at' => $future]), 'future document should be hidden');
    assert_true(is_document_published(['publish_at' => $past]), 'past document should be visible');
    assert_true(is_document_published(['publish_at' => null]), 'unscheduled document should be visible');
});

test('schedule updates are saved and logged', function () {
    $doc = create_document('Scheduled Memo', 'Body text', 1);
    $publishAt = date('Y-m-d H:i:s', strtotime('+2 hours'));

    update_document_schedule((int) $doc['id'], $publishAt);
    $updated = find_document_by_reference(document_label($doc));
    assert_same($publishAt, $updated['publish_at']);

    $stmt = db()->prepare("
        SELECT details
        FROM audit_log
        WHERE action = 'schedule_update'
          AND entity_type = 'document'
          AND entity_id = ?
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([(int) $doc['id']]);
    $details = json_decode((string) $stmt->fetchColumn(), true);

    assert_same($publishAt, $details['to'] ?? null);
});

test('staff can search documents by title', function () {
    create_document('Budget Workshop Agenda', 'Body text', 1);

    $matches = search_documents('workshop');
    $titles = array_column($matches, 'title');

    assert_true(in_array('Budget Workshop Agenda', $titles, true), 'expected title search to find the document');
});

test('created shares are logged with the readable document id', function () {
    $doc = create_document('Parks Update', 'Body text', 1);
    create_share($doc, 'resident@example.com');

    $stmt = db()->query("
        SELECT details
        FROM audit_log
        WHERE action = 'create'
          AND entity_type = 'share'
        ORDER BY id DESC
        LIMIT 1
    ");
    $details = json_decode((string) $stmt->fetchColumn(), true);

    assert_same(document_label($doc), $details['document_readable_id'] ?? null);
});

echo "\n{$pass} passed, {$fail} failed.\n";
exit($fail > 0 ? 1 : 0);
