<?php
$items = $result['items'];
$page = $result['page'];
$pages = $result['pages'];
$unread = 0;
foreach ($items as $n) { $unread += $n['is_read'] ? 0 : 1; }
?>
<div class="page-head">
    <div>
        <h1>Thông báo</h1>
        <p>Kỳ thi mới và các thay đổi liên quan đến bài thi của bạn</p>
    </div>
    <?php if ($unread > 0): ?>
        <form method="post" action="<?= url('/notifications/read-all') ?>">
            <?= csrf_field() ?>
            <button class="btn btn-outline" type="submit">Đánh dấu tất cả đã đọc</button>
        </form>
    <?php endif; ?>
</div>

<?php if (!$ready): ?>
    <div class="panel"><div class="panel-body muted">Chưa bật chức năng thông báo. Quản trị viên cần nhập file <code>database/011_notifications.sql</code>.</div></div>
<?php elseif (!$items): ?>
    <div class="panel"><div class="panel-body muted">Bạn chưa có thông báo nào.</div></div>
<?php else: ?>
    <div class="panel notif-list">
        <?php foreach ($items as $n): ?>
            <a class="notif<?= $n['is_read'] ? '' : ' unread' ?>" href="<?= url('/notifications/' . (int) $n['id'] . '/open') ?>">
                <span class="notif-dot" aria-hidden="true"></span>
                <span class="notif-main">
                    <strong><?= e($n['title']) ?></strong>
                    <?php if ((string) $n['body'] !== ''): ?><span><?= e($n['body']) ?></span><?php endif; ?>
                    <small class="muted"><?= e(fmt_date($n['created_at'])) ?></small>
                </span>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if ($pages > 1): ?>
        <div class="pagination">
            <span class="muted">Trang <?= (int) $page ?> / <?= (int) $pages ?></span>
            <div class="pages">
                <?php if ($page > 1): ?><a href="<?= url('/notifications?page=' . ($page - 1)) ?>">&laquo; Trước</a><?php endif; ?>
                <?php if ($page < $pages): ?><a href="<?= url('/notifications?page=' . ($page + 1)) ?>">Sau &raquo;</a><?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>
