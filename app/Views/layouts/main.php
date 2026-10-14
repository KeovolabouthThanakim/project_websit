<?php
use App\Core\Session;

$ic = function (string $n): string {
    static $p = [
        'home' => '<path d="M3 11l9-8 9 8v9a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/>',
        'users' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c0-3.6 2.9-6 6.5-6s6.5 2.4 6.5 6M16 4.5a3.5 3.5 0 0 1 0 7M18 14c2.2.7 3.5 2.6 3.5 5"/>',
        'class' => '<path d="M3 9l9-5 9 5-9 5z"/><path d="M7 11.5V16c0 1.4 2.2 3 5 3s5-1.6 5-3v-4.5"/>',
        'book' => '<path d="M4 4.5A1.5 1.5 0 0 1 5.5 3H20v16H5.5A1.5 1.5 0 0 0 4 20.5z"/><path d="M4 20.5A1.5 1.5 0 0 0 5.5 22H20"/>',
        'quiz' => '<circle cx="12" cy="12" r="9"/><path d="M9.5 9.5a2.5 2.5 0 1 1 3.4 2.3c-.6.3-.9.8-.9 1.4M12 17h.01"/>',
        'exam' => '<rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4h6v3H9zM9 14l2 2 4-4"/>',
        'calendar' => '<rect x="3.5" y="5" width="17" height="15.5" rx="2"/><path d="M3.5 10h17M8 3v4M16 3v4"/>',
        'chart' => '<path d="M4 20V4M4 20h16"/><path d="M8 15l3.5-4 3 2.5L19 7"/>',
        'db' => '<ellipse cx="12" cy="6" rx="7.5" ry="3"/><path d="M4.5 6v6c0 1.7 3.4 3 7.5 3s7.5-1.3 7.5-3V6M4.5 12v6c0 1.7 3.4 3 7.5 3s7.5-1.3 7.5-3v-6"/>',
        'log' => '<path d="M6 3h9l4 4v14H6z"/><path d="M14 3v5h5M9 13h7M9 17h7"/>',
        'cog' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.9.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.9 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.9l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.9.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.9-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.9V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
        'bell' => '<path d="M6 9a6 6 0 0 1 12 0c0 6 2.5 7.5 2.5 7.5h-17S6 15 6 9z"/><path d="M10 20a2 2 0 0 0 4 0"/>'
    ];
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p[$n] . '</svg>';
};
$user = auth_user();
$success = Session::pullFlash('success');
$error = Session::pullFlash('error');
$hasLogo = is_file(ROOT . '/public/assets/img/logo.png');
$unread = $user ? \App\Models\Notification::unreadCount((int) $user['id']) : 0;

// Thanh điều hướng dưới cùng trên điện thoại
$tabs = [];
if ($user && $user['role'] === 'admin') {
    $tabs = [['/dashboard', 'Trang chủ', 'home', false], ['/admin/users', 'Người dùng', 'users', true], ['/classes', 'Lớp học', 'class', true], ['/subjects', 'Môn học', 'book', true], ['/questions', 'Câu hỏi', 'quiz', true], ['/exams', 'Đề thi', 'exam', true]];
} elseif ($user && $user['role'] === 'teacher') {
    $tabs = [['/dashboard', 'Trang chủ', 'home', false], ['/classes', 'Lớp', 'class', true], ['/subjects', 'Môn học', 'book', true], ['/questions', 'Câu hỏi', 'quiz', true], ['/exams', 'Đề thi', 'exam', true]];
}
if ($user && $user['role'] === 'student') {
    $tabs = [['/dashboard', 'Trang chủ', 'home', false], ['/my-exams', 'Kỳ thi', 'exam', true], ['/my-schedule', 'Lịch thi', 'calendar', true], ['/my-results', 'Kết quả', 'chart', true]];
}
$initial = '';
if ($user) {
    $parts = preg_split('/\s+/u', trim($user['full_name']));
    $initial = str_cut((string) end($parts), 1);
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= e(($title ?? '') !== '' ? $title . ' - ' : '') ?><?= e(system_name()) ?> - <?= e(school_short()) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700;900&display=swap" media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700;900&display=swap"></noscript>
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
</head>
<body class="app<?= $tabs ? ' has-tabbar' : '' ?>">
<?php if ($user): ?>
<header class="topbar">
    <a class="topbar-brand" href="<?= url('/') ?>">
        <?php if ($hasLogo): ?><img class="crest-img" src="<?= asset('img/logo.png') ?>" alt="Logo"><?php else: ?><span class="crest">NAU</span><?php endif; ?>
        <span class="topbar-text"><strong><?= e(school_short()) ?></strong><small><?= e(system_name()) ?></small></span>
    </a>
    <?php if (!$tabs): ?>
    <button type="button" class="menu-btn" id="menu-btn" aria-label="Mở menu" aria-controls="sidebar" aria-expanded="false"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg><?php if ($unread > 0): ?><i class="menu-dot" aria-hidden="true"></i><?php endif; ?></button>
    <?php endif; ?>
</header>
<div class="scrim" id="scrim"></div>
<?php endif; ?>
<div class="shell">
    <aside class="sidebar" id="sidebar">
        <a class="brand" href="<?= url('/') ?>">
            <?php if ($hasLogo): ?>
                <img class="crest-img" src="<?= asset('img/logo.png') ?>" alt="Logo">
            <?php else: ?>
                <span class="crest">NAU</span>
            <?php endif; ?>
            <span class="brand-text">
                <span class="uni"><?= e(school_short()) ?></span>
                <span class="sys"><?= e(system_name()) ?></span>
            </span>
        </a>

        <?php if ($user): ?>
        <div class="nav-label">Menu</div>
        <nav class="side-nav" aria-label="Chức năng">
            <a class="<?= nav_active('/dashboard') ?>" href="<?= url('/dashboard') ?>"><?= $ic('home') ?>Trang chủ</a>
            <?php if ($user['role'] === 'admin'): ?>
                <a class="<?= nav_active('/admin/users', true) ?>" href="<?= url('/admin/users') ?>"><?= $ic('users') ?>Người dùng</a>
                <a class="<?= nav_active('/classes', true) ?>" href="<?= url('/classes') ?>"><?= $ic('class') ?>Lớp học</a>
                <a class="<?= nav_active('/subjects', true) ?>" href="<?= url('/subjects') ?>"><?= $ic('book') ?>Môn học</a>
                <a class="<?= nav_active('/questions', true) ?>" href="<?= url('/questions') ?>"><?= $ic('quiz') ?>Ngân hàng câu hỏi</a>
                <a class="<?= nav_active('/exams', true) ?>" href="<?= url('/exams') ?>"><?= $ic('exam') ?>Đề thi</a>
                <a class="<?= nav_active('/admin/reports', true) ?>" href="<?= url('/admin/reports') ?>"><?= $ic('chart') ?>Báo cáo</a>
                <a class="<?= nav_active('/admin/settings', true) ?>" href="<?= url('/admin/settings') ?>"><?= $ic('cog') ?>Cài đặt</a>
                <a class="<?= nav_active('/admin/audit', true) ?>" href="<?= url('/admin/audit') ?>"><?= $ic('log') ?>Nhật ký</a>
                <a class="<?= nav_active('/admin/backup', true) ?>" href="<?= url('/admin/backup') ?>"><?= $ic('db') ?>Sao lưu</a>
            <?php elseif ($user['role'] === 'teacher'): ?>
                <a class="<?= nav_active('/classes', true) ?>" href="<?= url('/classes') ?>"><?= $ic('class') ?>Lớp của tôi</a>
                <a class="<?= nav_active('/subjects', true) ?>" href="<?= url('/subjects') ?>"><?= $ic('book') ?>Môn học của tôi</a>
                <a class="<?= nav_active('/questions', true) ?>" href="<?= url('/questions') ?>"><?= $ic('quiz') ?>Ngân hàng câu hỏi</a>
                <a class="<?= nav_active('/exams', true) ?>" href="<?= url('/exams') ?>"><?= $ic('exam') ?>Đề thi</a>
            <?php else: ?>
                <a class="<?= nav_active('/my-exams', true) ?><?= nav_active('/attempts', true) ? ' active' : '' ?>" href="<?= url('/my-exams') ?>"><?= $ic('exam') ?>Kỳ thi của tôi</a>
                <a class="<?= nav_active('/my-schedule', true) ?>" href="<?= url('/my-schedule') ?>"><?= $ic('calendar') ?>Lịch thi</a>
                <a class="<?= nav_active('/my-results', true) ?>" href="<?= url('/my-results') ?>"><?= $ic('chart') ?>Kết quả của tôi</a>
            <?php endif; ?>
            <a class="<?= nav_active('/notifications', true) ?>" href="<?= url('/notifications') ?>"><?= $ic('bell') ?>Thông báo<?php if ($unread > 0): ?><span class="nav-badge"><?= $unread > 99 ? '99+' : (int) $unread ?></span><?php endif; ?></a>
            <a class="<?= nav_active('/help') ?>" href="<?= url('/help') ?>"><?= $ic('quiz') ?>Hướng dẫn</a>
        </nav>

        <div class="side-account">
            <div class="account-name"><a href="<?= url('/profile') ?>" title="Hồ sơ và đổi mật khẩu"><?= e($user['full_name']) ?></a></div>
            <div class="account-role"><?= e(role_label($user['role'])) ?></div>
            <form method="post" action="<?= url('/logout') ?>">
                <?= csrf_field() ?>
                <button class="btn btn-sm btn-light" type="submit">Đăng xuất</button>
            </form>
        </div>
        <?php endif; ?>
    </aside>

    <div class="main-col">
        <main class="content">
            <?php if ($success): ?><div class="toast toast-success" role="status"><span class="toast-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></span><div class="toast-body"><strong>Thành công</strong><span><?= e($success) ?></span></div></div><?php endif; ?>
            <?php if ($error): ?><div class="toast toast-error" role="alert"><span class="toast-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" aria-hidden="true"><path d="M12 6v7.5M12 18h.01"/></svg></span><div class="toast-body"><strong>Có lỗi xảy ra</strong><span><?= e($error) ?></span></div></div><?php endif; ?>
            <?= $content ?>
        </main>
        <footer class="site-footer">&copy; <?= date('Y') ?> <?= e(school_name()) ?></footer>
    </div>
</div>
<?php if ($tabs): ?>
<nav class="tabbar" aria-label="Điều hướng nhanh">
    <?php foreach ($tabs as [$path, $label, $icon, $prefix]): ?>
        <a class="<?= nav_active($path, $prefix) ?>" href="<?= url($path) ?>"><?= $ic($icon) ?><span><?= e($label) ?></span></a>
    <?php endforeach; ?>
    <button type="button" class="tab-menu" id="menu-btn" aria-label="Mở menu" aria-controls="sidebar" aria-expanded="false">
        <span class="tab-menu-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg><?php if ($unread > 0): ?><i class="menu-dot" aria-hidden="true"></i><?php endif; ?></span>
        <span>Menu</span>
    </button>
</nav>
<?php endif; ?>
<script>
(function () {
    // Gắn nhãn cột vào từng ô để bảng hiển thị dạng thẻ trên điện thoại
    document.querySelectorAll('table.table').forEach(function (t) {
        var h = [].map.call(t.querySelectorAll('thead th'), function (th) { return th.textContent.trim(); });
        // Bảng rộng hơn khung (laptop): cột "Thao tác" cuối bảng được ghim bên phải để luôn bấm được
        if (h.length && h[h.length - 1] === 'Thao tác') t.classList.add('sticky-actions');
        t.querySelectorAll('tbody tr').forEach(function (tr) {
            var i = 0;
            [].forEach.call(tr.children, function (td) {
                if (td.colSpan > 1) { i += td.colSpan; return; }
                td.setAttribute('data-label', h[i] || '');
                i++;
            });
        });
    });
    // Điện thoại: mỗi dòng bảng chỉ hiện 1 hàng gọn, bấm vào để xem chi tiết
    document.querySelectorAll('table.table').forEach(function (t) {
        var heads = [].map.call(t.querySelectorAll('thead th'), function (th) { return th.textContent.trim(); });
        t.querySelectorAll('tbody tr').forEach(function (tr) {
            var tds = [].slice.call(tr.children);
            if (tds.length < 2 || tds[0].colSpan > 1) return;
            var main = null, href = null, sub = null, mi = -1;
            tds.forEach(function (td, i) {
                if (main || td.classList.contains('actions')) return;
                var a = td.querySelector('a:not(.btn)');
                if (a) { main = td; mi = i; href = a.getAttribute('href'); }
            });
            if (!main) {
                for (var i = 0; i < heads.length; i++) { if (/^Họ/.test(heads[i])) { mi = i; break; } }
                if (mi < 0) { for (var j = 0; j < heads.length; j++) { if (heads[j] !== '' && heads[j] !== 'STT') { mi = j; break; } } }
                main = tds[mi] || null;
            }
            if (!main) return;
            if (!href) {
                var b = tr.querySelector('td.actions a.btn');
                if (b) href = b.getAttribute('href');
            }
            if (/^Mã/.test(heads[mi] || '') && tds[mi + 1] && !tds[mi + 1].classList.contains('actions')) sub = tds[mi + 1];
            tr.classList.add('row-compact');
            main.classList.add('c-main');
            if (sub) { sub.classList.add('c-sub'); tr.classList.add('has-sub'); }
            tds.forEach(function (td, i) {
                if (td !== main && td !== sub && /^(Trạng thái|Điểm)/.test(heads[i] || '')) td.classList.add('c-tag');
            });
            if (href) { tr.setAttribute('data-href', href); tr.setAttribute('role', 'link'); tr.tabIndex = 0; }
        });
    });
    var mq = window.matchMedia('(max-width:640px)');
    function go(tr) { var h = tr.getAttribute('data-href'); if (h) window.location.href = h; }
    document.addEventListener('click', function (e) {
        var tr = e.target.closest && e.target.closest('tr.row-compact[data-href]');
        if (!tr || !mq.matches || e.target.closest('a,button,input,label,form')) return;
        go(tr);
    });
    document.addEventListener('keydown', function (e) {
        var tr = e.target.matches && e.target.matches('tr.row-compact[data-href]') ? e.target : null;
        if (tr && mq.matches && e.key === 'Enter') go(tr);
    });
    // Menu trượt trên điện thoại
    var btn = document.getElementById('menu-btn'), scrim = document.getElementById('scrim');
    if (!btn) return;
    function set(open) { document.body.classList.toggle('nav-open', open); btn.setAttribute('aria-expanded', open ? 'true' : 'false'); }
    btn.addEventListener('click', function () { set(!document.body.classList.contains('nav-open')); });
    scrim.addEventListener('click', function () { set(false); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') set(false); });
    document.querySelectorAll('#sidebar .side-nav a').forEach(function (a) { a.addEventListener('click', function () { set(false); }); });
})();
</script>
<div class="modal" id="confirm-modal" hidden>
    <div class="modal-card" role="alertdialog" aria-modal="true" aria-labelledby="confirm-title" aria-describedby="confirm-text">
        <span class="modal-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 8v5M12 17h.01"/><path d="M10.3 3.9L2.4 17.5A2 2 0 0 0 4.1 20.5h15.8a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg></span>
        <div class="modal-body">
            <h2 id="confirm-title">Xác nhận thao tác</h2>
            <p id="confirm-text"></p>
        </div>
        <div class="modal-actions">
            <button type="button" class="btn btn-outline" id="confirm-cancel">Hủy</button>
            <button type="button" class="btn" id="confirm-ok">Xác nhận</button>
        </div>
    </div>
</div>
<script>
(function () {
    var modal = document.getElementById('confirm-modal'), text = document.getElementById('confirm-text');
    var ok = document.getElementById('confirm-ok'), cancel = document.getElementById('confirm-cancel'), pending = null;
    function close() { modal.hidden = true; pending = null; }
    document.addEventListener('submit', function (e) {
        var msg = e.target.getAttribute && e.target.getAttribute('data-confirm');
        if (!msg) return;
        e.preventDefault();
        pending = e.target;
        text.textContent = msg;
        modal.hidden = false;
        cancel.focus();
    });
    ok.addEventListener('click', function () { var f = pending; close(); if (f) f.submit(); });
    cancel.addEventListener('click', close);
    modal.addEventListener('click', function (e) { if (e.target === modal) close(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !modal.hidden) close(); });
})();
</script>
</body>
</html>