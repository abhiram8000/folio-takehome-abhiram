<?php

require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/layout.php';

$staff = current_staff();
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create_document';

    try {
        if ($action === 'create_document') {
            $title = trim($_POST['title'] ?? '');
            $body = trim($_POST['body'] ?? '');
            $publishAt = parse_publish_at($_POST['publish_at'] ?? null);

            if ($title === '' || $body === '') {
                $error = 'Title and body are required.';
            } else {
                $doc = create_document($title, $body, (int) $staff['id'], $publishAt);
                header('Location: /admin.php?created=' . urlencode(document_label($doc)));
                exit;
            }
        } elseif ($action === 'update_schedule') {
            $docId = (int) ($_POST['document_id'] ?? 0);
            $publishAt = parse_publish_at($_POST['publish_at'] ?? null);

            update_document_schedule($docId, $publishAt);
            header('Location: /admin.php?scheduled=1');
            exit;
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$search = trim($_GET['q'] ?? '');
$docs = search_documents($search);

render_header('Admin', $staff);
?>

<h1 class="page-title">Admin</h1>
<p class="page-subtitle">Create documents, schedule release times, and generate private recipient links.</p>

<?php if (!empty($_GET['created'])): ?>
    <div class="banner banner-success">Document <?= h((string) $_GET['created']) ?> created.</div>
<?php endif ?>

<?php if (!empty($_GET['scheduled'])): ?>
    <div class="banner banner-success">Schedule updated.</div>
<?php endif ?>

<?php if ($error): ?>
    <div class="banner banner-error"><?= h($error) ?></div>
<?php endif ?>

<section class="card">
    <h2 class="card-title">New document</h2>
    <form method="post">
        <input type="hidden" name="action" value="create_document">
        <div class="form-field">
            <label for="title">Title</label>
            <input type="text" id="title" name="title" required>
        </div>
        <div class="form-field">
            <label for="body">Body</label>
            <textarea id="body" name="body" required></textarea>
        </div>
        <div class="form-field">
            <label for="publish_at">Publish at</label>
            <input type="datetime-local" id="publish_at" name="publish_at">
            <p class="field-help">Leave blank to make the document available as soon as a share link is opened.</p>
        </div>
        <button type="submit" class="btn">Create document</button>
    </form>
</section>

<section class="card">
    <div class="section-heading">
        <h2 class="card-title">Documents</h2>
        <form method="get" class="search-form">
            <label class="sr-only" for="q">Search by title</label>
            <input type="text" id="q" name="q" value="<?= h($search) ?>" placeholder="Search by title">
            <button type="submit" class="btn">Search</button>
            <?php if ($search !== ''): ?>
                <a href="/admin.php" class="btn-secondary">Clear</a>
            <?php endif ?>
        </form>
    </div>

    <?php if (empty($docs)): ?>
        <p class="empty">No documents found.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Availability</th>
                        <th>Schedule</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($docs as $d): ?>
                        <?php $isPublished = is_document_published($d); ?>
                        <tr>
                            <td class="id"><?= h(document_label($d)) ?></td>
                            <td>
                                <strong><?= h($d['title']) ?></strong>
                                <span class="row-meta">By <?= h($d['creator_name']) ?> on <?= h($d['created_at']) ?></span>
                            </td>
                            <td>
                                <span class="status-badge <?= $isPublished ? 'status-live' : 'status-scheduled' ?>">
                                    <?= $isPublished ? 'Live' : 'Scheduled' ?>
                                </span>
                                <span class="row-meta"><?= h(format_publish_at($d['publish_at'] ?? null)) ?></span>
                            </td>
                            <td>
                                <form method="post" class="schedule-form">
                                    <input type="hidden" name="action" value="update_schedule">
                                    <input type="hidden" name="document_id" value="<?= (int) $d['id'] ?>">
                                    <input type="datetime-local" name="publish_at" value="<?= h(datetime_local_value($d['publish_at'] ?? null)) ?>">
                                    <button type="submit" class="btn-link">Save</button>
                                </form>
                            </td>
                            <td><a href="/share.php?doc=<?= urlencode(document_label($d)) ?>" class="btn-link">Share &rarr;</a></td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
    <?php endif ?>
</section>

<?php render_footer(); ?>
