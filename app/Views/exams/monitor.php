<?php
use App\Models\Exam;

$examId = (int) $exam['id'];
?>
<div class="page-head">
    <div>
        <h1>Theo dõi phòng thi <span class="badge badge-st-<?= e($state) ?>"><?= e(Exam::STATES[$state]) ?></span></h1>
        <p><?= e($exam['title']) ?> - môn <?= e($exam['subject_code']) ?> · <?= (int) $exam['duration_minutes'] ?> phút</p>
    </div>
    <div class="header-actions">
        <a class="btn btn-outline" href="<?= url('/exams/' . $examId . '/results' . ($classId ? '?class_id=' . $classId : '')) ?>">Kết quả &amp; thống kê</a>
        <a class="btn btn-outline" href="<?= url('/exams/' . $examId) ?>">&laquo; Chi tiết đề thi</a>
    </div>
</div>

<div class="filter-bar">
    <?php if (count($classes) > 1): ?>
    <div>
        <label for="mon-class">Lớp</label>
        <select id="mon-class">
            <option value="">Tất cả các lớp</option>
            <?php foreach ($classes as $c): ?>
                <option value="<?= (int) $c['id'] ?>" <?= $classId === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['code']) ?> - <?= e($c['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>
    <div class="grow">
        <label for="mon-q">Tìm sinh viên</label>
        <input type="search" id="mon-q" placeholder="Mã hoặc họ tên...">
    </div>
    <div class="mon-live">
        <span class="live-dot" id="live-dot" aria-hidden="true"></span>
        <span id="live-text">Đang tải...</span>
        <button class="btn btn-sm btn-outline" type="button" id="live-toggle">Tạm dừng</button>
    </div>
</div>

<div class="stats stats-5">
    <div class="stat"><span class="stat-num" id="c-working">-</span><span class="stat-label">Đang trong phòng thi (đang làm bài)</span></div>
    <div class="stat"><span class="stat-num" id="c-absent">-</span><span class="stat-label">Chưa vào thi</span></div>
    <div class="stat"><span class="stat-num" id="c-done">-</span><span class="stat-label">Đã nộp bài</span></div>
    <div class="stat"><span class="stat-num" id="c-total">-</span><span class="stat-label">Tổng số sinh viên</span></div>
    <div class="stat" id="stat-flagged" title="Sinh viên có ghi nhận rời khỏi trang thi, sao chép nội dung hoặc bấm chuột phải"><span class="stat-num" id="c-flagged">-</span><span class="stat-label">Có dấu hiệu khác thường</span></div>
</div>

<div class="mon-tabs" role="tablist">
    <button type="button" class="mon-tab on" data-tab="all">Tất cả</button>
    <button type="button" class="mon-tab" data-tab="working">Đang làm bài</button>
    <button type="button" class="mon-tab" data-tab="absent">Chưa vào thi</button>
    <button type="button" class="mon-tab" data-tab="done">Đã nộp</button>
    <button type="button" class="mon-tab" data-tab="flag">Có cảnh báo</button>
</div>

<div class="table-wrap">
    <table class="table">
        <thead><tr><th>Mã SV</th><th>Họ và tên</th><th>Lớp</th><th>Trạng thái</th><th>Tiến độ</th><th>Còn lại</th><th>Vào lúc</th><th>Nộp lúc</th><th>Điểm</th></tr></thead>
        <tbody id="mon-body"><tr><td colspan="9" class="empty">Đang tải dữ liệu...</td></tr></tbody>
    </table>
</div>
<p class="muted" style="font-size:.85rem;margin-top:10px">
    "Đang làm bài" nghĩa là sinh viên đã bấm vào thi và chưa nộp. Trang tự cập nhật mỗi 10 giây; bài hết giờ sẽ được hệ thống tự nộp.
    Cột tiến độ là số câu đã chọn đáp án trên tổng số câu.
</p>
<p class="muted" style="font-size:.85rem;margin-top:6px">
    Nhãn <strong>⚠ Rời trang</strong> dưới trạng thái: số lần sinh viên chuyển tab / thu nhỏ trình duyệt / rời cửa sổ thi và tổng thời gian vắng mặt (từ <?= (int) \App\Models\AttemptEvent::WARN_LEAVES ?> lần trở lên được tô đỏ).
    Dữ liệu do trình duyệt của sinh viên báo về nên <strong>chỉ là dấu hiệu tham khảo</strong>: có thể là mất mạng, thông báo hệ thống, hoặc ngược lại bị né tránh. Không dùng làm căn cứ duy nhất để kết luận gian lận.
    <?= \App\Models\AttemptEvent::ready() ? '' : '<strong>Chưa nhập database/009_exam_events.sql nên chưa ghi nhận được nhật ký.</strong>' ?>
</p>

<script>
(function () {
    var DATA_URL = <?= json_encode(url('/exams/' . $examId . '/monitor/data')) ?>;
    var EVERY = 10000;
    var tab = 'all', query = '', classId = <?= (int) $classId ?>, paused = false, timer = null;
    var rows = [], fetchedAt = Date.now();
    var body = document.getElementById('mon-body');
    var LABEL = { working: ['badge-st-upcoming', 'Đang làm'], absent: ['badge-off', 'Chưa vào thi'], done: ['badge-on', 'Đã nộp'] };

    function el(tag, cls, text) {
        var n = document.createElement(tag);
        if (cls) n.className = cls;
        if (text !== undefined) n.textContent = text;
        return n;
    }
    function p2(x) { return (x < 10 ? '0' : '') + x; }
    function clock(s) {
        s = Math.max(0, Math.round(s));
        var h = Math.floor(s / 3600), m = Math.floor((s % 3600) / 60), x = s % 60;
        return (h > 0 ? h + ':' + p2(m) : m) + ':' + p2(x);
    }
    function td(row, label, node) {
        var c = document.createElement('td');
        c.setAttribute('data-label', label);
        if (typeof node === 'string') c.textContent = node; else if (node) c.appendChild(node);
        row.appendChild(c);
        return c;
    }

    function render() {
        var q = query.trim().toLowerCase();
        var shown = rows.filter(function (r) {
            if (tab === 'flag') { if (r.level === 'none') return false; }
            else if (tab !== 'all' && r.state !== tab) return false;
            return !q || (r.username + ' ' + r.full_name).toLowerCase().indexOf(q) !== -1;
        });
        body.textContent = '';
        if (!shown.length) {
            var tr0 = el('tr'), c0 = el('td', 'empty', rows.length ? 'Không có sinh viên nào phù hợp.' : 'Chưa có sinh viên nào trong các lớp được thi.');
            c0.colSpan = 9; tr0.appendChild(c0); body.appendChild(tr0);
            return;
        }
        shown.forEach(function (r) {
            var tr = el('tr');
            td(tr, 'Mã SV', r.username);
            td(tr, 'Họ tên', el('strong', '', r.full_name));
            td(tr, 'Lớp', r.class ? el('span', 'tag', r.class) : el('span', 'muted', 'đã rời lớp'));
            var st = el('span', 'badge ' + LABEL[r.state][0], LABEL[r.state][1]);
            var stCell = td(tr, 'Trạng thái', st);
            if (r.auto) { stCell.appendChild(document.createTextNode(' ')); var a = el('span', 'badge badge-off', 'Tự nộp'); a.title = 'Hết giờ, hệ thống tự nộp'; stCell.appendChild(a); }
            if (r.level !== 'none') {
                stCell.appendChild(document.createElement('br'));
                var fb = el('span', 'badge ' + (r.level === 'warn' ? 'badge-off' : 'badge-st-upcoming'), '\u26A0 ' + (r.leaves > 0 ? 'Rời trang ' + r.leaves + ' lần · ' + clock(r.away) : r.others + ' thao tác'));
                fb.title = 'Rời khỏi trang thi ' + r.leaves + ' lần, tổng ' + clock(r.away) + ' · sao chép / chuột phải ' + r.others + ' lần. Chỉ là dấu hiệu tham khảo.';
                stCell.appendChild(fb);
                if (r.level === 'warn') tr.className = 'row-flag';
            }

            if (r.state === 'absent') {
                td(tr, 'Tiến độ', '-');
            } else {
                var wrap = el('div', 'prog');
                var bar = el('span', 'prog-bar'); bar.style.width = (r.questions ? Math.round(r.answered / r.questions * 100) : 0) + '%';
                var track = el('span', 'prog-track'); track.appendChild(bar);
                wrap.appendChild(track); wrap.appendChild(el('span', 'prog-text', r.answered + '/' + r.questions));
                td(tr, 'Tiến độ', wrap);
            }
            var leftCell = td(tr, 'Còn lại', r.state === 'working' ? clock(r.left) : '-');
            if (r.state === 'working') { leftCell.setAttribute('data-left', r.left); leftCell.className = 'mon-left'; }
            td(tr, 'Vào lúc', r.started || '-');
            td(tr, 'Nộp lúc', r.submitted || '-');
            td(tr, 'Điểm', r.state === 'done' ? el('strong', '', r.score) : '-');

            body.appendChild(tr);
        });
    }

    function setCounts(c) {
        ['working', 'absent', 'done', 'total', 'flagged'].forEach(function (k) { document.getElementById('c-' + k).textContent = c[k]; });
    }
    function setLive(text, bad) {
        document.getElementById('live-text').textContent = text;
        document.getElementById('live-dot').className = 'live-dot' + (bad ? ' bad' : (paused ? ' off' : ''));
    }

    function load() {
        var url = DATA_URL + (classId ? '?class_id=' + classId : '');
        return fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (r) { if (!r.ok) throw new Error('http ' + r.status); return r.json(); })
            .then(function (j) {
                if (!j.ok) throw new Error('bad');
                rows = j.rows; fetchedAt = Date.now();
                setCounts(j.counts); render();
                setLive('Cập nhật lúc ' + j.time);
            })
            .catch(function () { setLive('Mất kết nối - đang thử lại', true); });
    }
    function schedule() {
        clearTimeout(timer);
        if (paused) return;
        timer = setTimeout(function () { (document.hidden ? Promise.resolve() : load()).then(schedule); }, EVERY);
    }

    // Đếm ngược thời gian còn lại giữa hai lần cập nhật
    setInterval(function () {
        var spent = (Date.now() - fetchedAt) / 1000;
        document.querySelectorAll('.mon-left').forEach(function (c) {
            c.textContent = clock(parseInt(c.getAttribute('data-left'), 10) - spent);
        });
    }, 1000);

    document.querySelectorAll('.mon-tab').forEach(function (b) {
        b.addEventListener('click', function () {
            tab = b.getAttribute('data-tab');
            document.querySelectorAll('.mon-tab').forEach(function (x) { x.classList.toggle('on', x === b); });
            render();
        });
    });
    document.getElementById('mon-q').addEventListener('input', function (e) { query = e.target.value; render(); });
    var cls = document.getElementById('mon-class');
    if (cls) cls.addEventListener('change', function () {
        classId = parseInt(cls.value || '0', 10);
        history.replaceState(null, '', location.pathname + (classId ? '?class_id=' + classId : ''));
        load();
    });
    document.getElementById('live-toggle').addEventListener('click', function () {
        paused = !paused;
        this.textContent = paused ? 'Tiếp tục' : 'Tạm dừng';
        setLive(paused ? 'Đã tạm dừng' : 'Đang tải...');
        if (!paused) load().then(schedule); else clearTimeout(timer);
    });
    document.addEventListener('visibilitychange', function () { if (!document.hidden && !paused) load(); });

    load().then(schedule);
})();
</script>
