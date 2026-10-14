<?php
$letters = ['A', 'B', 'C', 'D', 'E', 'F'];
$attemptId = (int) $attempt['id'];
$total = count($paper);
$clock = fn(int $s) => sprintf('%02d:%02d:%02d', intdiv($s, 3600), intdiv($s % 3600, 60), $s % 60);
?>
<form method="post" action="<?= url('/attempts/' . $attemptId . '/submit') ?>" id="take-form" data-confirm="Nộp bài?">
    <?= csrf_field() ?>
    <input type="hidden" name="answers_sent" value="1">
    <input type="hidden" name="auto" value="0" id="auto-flag">
    <div class="leave-warn" id="leave-warn" role="alert" hidden></div>

    <div class="take-grid">
        <div class="take-main">
            <div class="take-head">
                <h1><?= e($attempt['title']) ?></h1>
                <p>Môn <?= e($attempt['subject_code']) ?> - <?= e($attempt['subject_name']) ?> · <?= $total ?> câu · <?= (int) $attempt['duration_minutes'] ?> phút</p>
                <?php if (!empty($track)): ?>
                <p class="take-track" id="take-track">Hệ thống ghi nhận khi bạn rời khỏi trang thi (chuyển tab, thu nhỏ trình duyệt, sao chép nội dung) và giáo viên có thể xem nhật ký này.<?php if (!empty($leaves)): ?> <strong id="track-count">Đã ghi nhận <?= (int) $leaves ?> lần rời trang.</strong><?php endif; ?></p>
                <?php endif; ?>
            </div>

            <?php foreach ($paper as $n => $q): ?>
            <section class="take-q" id="q-<?= $n + 1 ?>" data-qid="<?= (int) $q['id'] ?>" data-multiple="<?= $q['multiple'] ? '1' : '0' ?>">
                <div class="take-q-head">
                    <h2 class="take-q-no">Câu <?= $n + 1 ?><?php if ($q['multiple']): ?> <span class="badge badge-lv-medium">Chọn nhiều đáp án</span><?php endif; ?></h2>
                </div>
                <p class="q-content"><?= e($q['content']) ?></p>
                <?php if ($q['image']): ?>
                    <img class="q-image" src="<?= url('/attempts/' . $attemptId . '/images/' . e($q['image'])) ?>" alt="Ảnh minh họa câu hỏi">
                <?php endif; ?>
                <div class="take-opts">
                <?php foreach ($q['options'] as $i => $o): ?>
                    <label class="take-opt">
                        <input type="<?= $q['multiple'] ? 'checkbox' : 'radio' ?>" name="a[<?= (int) $q['id'] ?>][]" value="<?= (int) $o['id'] ?>" <?= $o['selected'] ? 'checked' : '' ?>>
                        <span class="opt-letter"><?= $letters[$i] ?? $i + 1 ?>.</span>
                        <span class="opt-text"><?= e($o['content']) ?></span>
                    </label>
                <?php endforeach; ?>
                </div>
                <div class="take-q-foot">
                    <?php if (!$q['multiple']): ?><button type="button" class="link-btn" data-clear>Bỏ chọn</button><?php endif; ?>
                    <button type="button" class="flag-btn" data-flag aria-pressed="false"><span>Lát kiểm tra lại</span><i class="flag-dot" aria-hidden="true"></i></button>
                </div>
            </section>
            <?php endforeach; ?>
        </div>

        <aside class="take-nav" id="take-nav" aria-label="Bảng điều khiển bài thi">
            <div class="take-stats">
                <div class="take-stat">
                    <span class="take-stat-label">Số câu đã làm</span>
                    <strong class="take-stat-num"><span id="answered-count"><?= (int) $answered ?></span>/<?= $total ?></strong>
                </div>
                <div class="take-stat">
                    <span class="take-stat-label">Thời gian còn lại</span>
                    <strong class="take-stat-num take-timer" id="timer" role="timer" aria-live="off" data-seconds="<?= (int) $secondsLeft ?>"><?= e($clock($secondsLeft)) ?></strong>
                </div>
            </div>

            <button type="button" class="nav-toggle" id="nav-toggle" aria-expanded="false">Danh sách câu hỏi</button>
            <div class="nav-more">
                <div class="nav-chips" id="nav-chips">
                    <?php foreach ($paper as $n => $q): ?>
                        <?php $isDone = (bool) array_filter($q['options'], fn($o) => $o['selected']); ?>
                        <a href="#q-<?= $n + 1 ?>" class="chip<?= $isDone ? ' done' : '' ?>" data-chip="<?= (int) $q['id'] ?>"><?= $n + 1 ?></a>
                    <?php endforeach; ?>
                </div>
            </div>

            <button class="btn-submit" type="submit" id="submit-btn">Nộp bài</button>

            <ul class="nav-legend">
                <li><i class="lg lg-done"></i>Câu đã làm</li>
                <li><i class="lg lg-todo"></i>Câu chưa làm</li>
                <li><i class="lg lg-flag"></i>Câu cần kiểm tra lại</li>
            </ul>
            <p class="take-save"><span id="save-state" class="save-state">Đã lưu</span></p>
        </aside>
    </div>
</form>

<script>
(function () {
    var form = document.getElementById('take-form');
    var timerEl = document.getElementById('timer');
    var nav = document.getElementById('take-nav');
    var saveEl = document.getElementById('save-state');
    var countEl = document.getElementById('answered-count');
    var autoFlag = document.getElementById('auto-flag');
    var csrf = form.querySelector('input[name="_csrf"]').value;
    var answerUrl = <?= json_encode(url('/attempts/' . $attemptId . '/answer')) ?>;
    var TOTAL = <?= $total ?>;
    var FLAG_KEY = 'nauquiz.flags.<?= $attemptId ?>';

    // ----- Đồng hồ: lấy số giây còn lại từ server, đếm bằng đồng hồ máy; mỗi lần tự lưu lại đồng bộ với server -----
    var endAt = Date.now() + parseInt(timerEl.getAttribute('data-seconds'), 10) * 1000;
    var finished = false, leaving = false;
    function p2(x) { return (x < 10 ? '0' : '') + x; }
    function fmt(s) { return p2(Math.floor(s / 3600)) + ':' + p2(Math.floor((s % 3600) / 60)) + ':' + p2(s % 60); }
    function autoSubmit() {
        if (finished) return;
        finished = true;
        autoFlag.value = '1';
        timerEl.textContent = fmt(0);
        saveEl.textContent = 'Hết giờ - đang nộp bài...';
        form.submit(); // submit() không chạy hộp xác nhận
    }
    function tick() {
        var left = Math.max(0, Math.round((endAt - Date.now()) / 1000));
        timerEl.textContent = fmt(left);
        nav.classList.toggle('low', left <= 300);
        if (left <= 0) autoSubmit();
    }
    tick();
    setInterval(tick, 500);

    // ----- Tự lưu đáp án -----
    var dirty = {}, timers = {}, inflight = 0, failed = false;
    function setState(text, bad) { saveEl.textContent = text; saveEl.classList.toggle('bad', !!bad); }

    // Chỉ tính các ô đáp án (có name); nút "Lát kiểm tra lại" không phải là đáp án
    function checked(box) { return box.querySelectorAll('.take-opt input:checked'); }
    function values(box) { return [].map.call(checked(box), function (i) { return i.value; }); }
    function chipOf(box) { return document.querySelector('[data-chip="' + box.getAttribute('data-qid') + '"]'); }

    function refreshProgress() {
        var done = 0;
        document.querySelectorAll('.take-q').forEach(function (box) {
            var has = checked(box).length > 0;
            if (has) done++;
            var chip = chipOf(box);
            if (chip) chip.classList.toggle('done', has);
        });
        countEl.textContent = done;
    }
    function save(box) {
        var qid = box.getAttribute('data-qid');
        var body = new URLSearchParams();
        body.append('_csrf', csrf);
        body.append('question_id', qid);
        values(box).forEach(function (v) { body.append('option_ids[]', v); });
        inflight++;
        setState('Đang lưu...');
        return fetch(answerUrl, { method: 'POST', body: body, credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json().then(function (j) { return { status: r.status, json: j }; }); })
            .then(function (res) {
                if (res.json && res.json.finished) { autoSubmit(); return; }
                if (!res.json || !res.json.ok) throw new Error('save failed');
                if (typeof res.json.seconds_left === 'number') endAt = Date.now() + res.json.seconds_left * 1000;
                delete dirty[qid];
                failed = false;
            })
            .catch(function () { failed = true; dirty[qid] = box; })
            .then(function () {
                inflight--;
                if (inflight === 0) {
                    if (Object.keys(dirty).length) setState('Chưa lưu được - sẽ thử lại', true);
                    else setState('Đã lưu');
                }
            });
    }
    function schedule(box) {
        var qid = box.getAttribute('data-qid');
        dirty[qid] = box;
        setState('Đang lưu...');
        clearTimeout(timers[qid]);
        timers[qid] = setTimeout(function () { save(box); }, 250);
    }

    // ----- "Lát kiểm tra lại": đánh dấu cá nhân, chỉ lưu trong trình duyệt (không ảnh hưởng điểm) -----
    var flags = {};
    try { flags = JSON.parse(localStorage.getItem(FLAG_KEY) || '{}') || {}; } catch (e) { flags = {}; }
    function applyFlag(box) {
        var on = !!flags[box.getAttribute('data-qid')];
        box.querySelector('[data-flag]').setAttribute('aria-pressed', on ? 'true' : 'false');
        box.classList.toggle('flagged', on);
        var chip = chipOf(box);
        if (chip) chip.classList.toggle('flag', on);
    }

    document.querySelectorAll('.take-q').forEach(function (box) {
        applyFlag(box);
        box.addEventListener('change', function (e) {
            if (!e.target.closest('.take-opt')) return;
            refreshProgress(); schedule(box);
        });
        var clear = box.querySelector('[data-clear]');
        if (clear) clear.addEventListener('click', function () {
            checked(box).forEach(function (i) { i.checked = false; });
            refreshProgress(); schedule(box);
        });
        box.querySelector('[data-flag]').addEventListener('click', function () {
            var qid = box.getAttribute('data-qid');
            if (flags[qid]) delete flags[qid]; else flags[qid] = 1;
            try { localStorage.setItem(FLAG_KEY, JSON.stringify(flags)); } catch (e) { /* trình duyệt chặn lưu: vẫn dùng được trong phiên này */ }
            applyFlag(box);
        });
    });
    refreshProgress();

    // Thử lại các câu chưa lưu được (mất mạng thoáng qua)
    setInterval(function () {
        if (!failed || inflight > 0) return;
        Object.keys(dirty).forEach(function (qid) { save(dirty[qid]); });
    }, 4000);
    window.addEventListener('beforeunload', function (e) {
        if (!finished && !leaving && Object.keys(dirty).length) { e.preventDefault(); e.returnValue = ''; }
    });

    // ----- Ghi nhận rời khỏi trang thi -----
    // Chỉ là dấu hiệu tham khảo cho giáo viên (không chặn thao tác, không trừ điểm). Mốc giờ do máy chủ ghi.
    var TRACK = <?= json_encode(!empty($track)) ?>;
    if (TRACK) {
        var eventUrl = <?= json_encode(url('/attempts/' . $attemptId . '/event')) ?>;
        var warnEl = document.getElementById('leave-warn');
        var leaves = <?= (int) ($leaves ?? 0) ?>;
        var away = false, unloading = false, blurTimer = null, warnTimer = null, lastSent = {}, pending = [];

        function sendEvent(type) {
            var body = new URLSearchParams();
            body.append('_csrf', csrf);
            body.append('type', type);
            // keepalive: yêu cầu vẫn được gửi đi kể cả khi trang đang bị ẩn / đóng
            return fetch(eventUrl, { method: 'POST', body: body, credentials: 'same-origin', keepalive: true, headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json().catch(function () { return null; }); })
                .then(function (j) {
                    if (j && j.finished) return null;             // bài đã nộp / hết giờ: không ghi nữa
                    if (!j || !j.ok) throw new Error('event failed');
                    if (typeof j.leaves === 'number') leaves = j.leaves;
                    return j;
                })
                .catch(function () { if (pending.length < 20) pending.push(type); return null; });   // mất mạng: thử lại sau
        }
        function showWarn() {
            warnEl.textContent = 'Bạn vừa rời khỏi trang thi (lần thứ ' + leaves + '). Hệ thống đã ghi nhận và giáo viên có thể xem. Hãy ở lại trang thi cho đến khi nộp bài.';
            warnEl.hidden = false;
            clearTimeout(warnTimer);
            warnTimer = setTimeout(function () { warnEl.hidden = true; }, 10000);
            var c = document.getElementById('track-count');
            if (!c) { c = document.createElement('strong'); c.id = 'track-count'; document.getElementById('take-track').appendChild(document.createTextNode(' ')); document.getElementById('take-track').appendChild(c); }
            c.textContent = 'Đã ghi nhận ' + leaves + ' lần rời trang.';
        }
        function leave(type) {
            if (away || finished || leaving || unloading) return;
            away = true; leaves++;
            sendEvent(type);
        }
        function back() {
            if (!away) return;
            away = false;
            sendEvent('return');
            showWarn();
        }
        function note(type) {
            var t = Date.now();
            if (finished || leaving || (lastSent[type] && t - lastSent[type] < 3000)) return;
            lastSent[type] = t;
            sendEvent(type);
        }
        warnEl.addEventListener('click', function () { warnEl.hidden = true; });

        document.addEventListener('visibilitychange', function () { if (document.hidden) leave('hidden'); else back(); });
        // Mất tiêu điểm nhưng trang vẫn hiển thị (bấm sang cửa sổ khác): đợi 1,5 giây cho chắc rồi mới ghi
        window.addEventListener('blur', function () {
            clearTimeout(blurTimer);
            blurTimer = setTimeout(function () { if (!document.hidden && !document.hasFocus()) leave('blur'); }, 1500);
        });
        window.addEventListener('focus', function () { clearTimeout(blurTimer); if (!document.hidden) back(); });
        // Tải lại / rời trang: không tính là "chuyển tab" (máy chủ tự đóng lần rời trang còn mở khi tải lại)
        window.addEventListener('pagehide', function (e) { if (!e.persisted) unloading = true; });
        document.addEventListener('copy', function () { note('copy'); });
        document.addEventListener('contextmenu', function () { note('contextmenu'); });

        setInterval(function () {
            if (!pending.length || finished) return;
            sendEvent(pending.shift());
        }, 5000);
    }

    // ----- Màn hình hẹp: thu gọn bảng số câu, bấm để mở -----
    var toggle = document.getElementById('nav-toggle');
    toggle.addEventListener('click', function () {
        var open = nav.classList.toggle('open');
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    document.getElementById('nav-chips').addEventListener('click', function (e) {
        if (e.target.closest('.chip')) { nav.classList.remove('open'); toggle.setAttribute('aria-expanded', 'false'); }
    });

    // ----- Nộp bài: báo số câu chưa trả lời trong hộp xác nhận (chạy trước trình xử lý chung ở layout) -----
    form.addEventListener('submit', function () {
        var left = TOTAL - parseInt(countEl.textContent, 10);
        var flagged = Object.keys(flags).length;
        var msg = left > 0 ? 'Bạn còn ' + left + ' câu chưa trả lời.' : '';
        if (flagged > 0) msg += (msg ? ' ' : '') + 'Có ' + flagged + ' câu đang đánh dấu cần kiểm tra lại.';
        form.setAttribute('data-confirm', (msg ? msg + ' ' : '') + 'Vẫn nộp bài? Nộp rồi sẽ không sửa được.');
    });
    // Đã bấm "Xác nhận" trong hộp thoại: đang rời trang để nộp, không cảnh báo nữa
    // (hộp thoại nằm sau nội dung trang trong layout nên dùng bắt sự kiện ở document, pha capture để chạy trước khi form gửi đi)
    document.addEventListener('click', function (e) {
        if (e.target && e.target.id === 'confirm-ok') leaving = true;
    }, true);
})();
</script>
