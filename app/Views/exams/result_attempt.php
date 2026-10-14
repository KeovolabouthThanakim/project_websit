<?php
$letters = ['A', 'B', 'C', 'D', 'E', 'F'];
$total = (int) $attempt['total_questions'];
$spent = max(0, strtotime($attempt['submitted_at']) - strtotime($attempt['started_at']));
$score = (float) $attempt['score'];
$grade = $score >= 8 ? 'good' : ($score >= 5 ? 'ok' : 'low');
?>
<div class="page-head">
    <div>
        <h1><?= e($attempt['full_name']) ?> <span class="muted" style="font-weight:500;font-size:1rem">(<?= e(sv_code($attempt)) ?><?= $attempt['class_code'] ? ' · ' . e($attempt['class_code']) : '' ?>)</span></h1>
        <p><?= e($exam['title']) ?> - môn <?= e($exam['subject_code']) ?></p>
    </div>
    <div class="header-actions">
        <a class="btn btn-outline" href="<?= url('/exams/' . (int) $exam['id'] . '/results') ?>">&laquo; Bảng điểm</a>
    </div>
</div>

<section class="panel">
    <div class="panel-body score-wrap">
        <div class="score-big score-<?= $grade ?>"><span><?= e(fmt_score($attempt['score'])) ?></span><small>/ 10 điểm</small></div>
        <dl class="info score-info">
            <dt>Tổng điểm các câu</dt><dd><?= e(fmt_score($attempt['earned'])) ?> / <?= $total ?></dd>
            <dt>Thời gian làm bài</dt><dd><?= e(fmt_duration($spent)) ?> / <?= (int) $exam['duration_minutes'] ?> phút</dd>
            <dt>Bắt đầu</dt><dd><?= e(fmt_date($attempt['started_at'])) ?></dd>
            <dt>Nộp lúc</dt><dd><?= e(fmt_date($attempt['submitted_at'])) ?><?= $attempt['auto_submitted'] ? ' <span class="badge badge-off">Hết giờ, tự nộp</span>' : '' ?></dd>
        </dl>
    </div>
</section>

<?php if (!empty($tracked)): ?>
<?php
$leaveN = count(array_filter($events, fn($ev) => in_array($ev['type'], \App\Models\AttemptEvent::LEAVE_TYPES, true)));
$awaySum = array_sum(array_map(fn($ev) => (int) ($ev['away'] ?? 0), $events));
?>
<section class="panel">
    <h2 class="panel-title">Nhật ký hành vi trong lúc thi
        <?php if ($leaveN >= \App\Models\AttemptEvent::WARN_LEAVES): ?><span class="badge badge-off">Rời trang nhiều lần</span>
        <?php elseif ($events): ?><span class="badge badge-st-upcoming">Có ghi nhận</span>
        <?php else: ?><span class="badge badge-on">Không có ghi nhận</span><?php endif; ?></h2>
    <div class="panel-body">
        <?php if ($events): ?>
            <p style="margin-top:0">Rời khỏi trang thi <strong><?= $leaveN ?> lần</strong>, tổng thời gian vắng mặt khoảng <strong><?= e(fmt_duration($awaySum)) ?></strong>.</p>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>#</th><th>Lúc</th><th>Hành vi</th><th>Vắng mặt</th></tr></thead>
                    <tbody>
                    <?php foreach ($events as $i => $ev): ?>
                        <tr>
                            <td data-label="#"><?= $i + 1 ?></td>
                            <td data-label="Lúc" style="white-space:nowrap"><?= e(date('H:i:s d/m', strtotime($ev['at']))) ?></td>
                            <td data-label="Hành vi"><?= e($ev['label']) ?></td>
                            <td data-label="Vắng mặt"><?= $ev['away'] === null ? '<span class="muted">-</span>' : e(fmt_duration((int) $ev['away'])) . ($ev['open'] ? ' <span class="muted" title="Không có báo cáo quay lại: tính đến lúc nộp bài (có thể đã đóng trình duyệt hoặc mất mạng)">(đến lúc nộp)</span>' : '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="muted" style="margin:0">Trình duyệt của sinh viên không báo về hành vi khác thường nào trong lúc làm bài.</p>
        <?php endif; ?>
        <p class="hint" style="margin-top:12px">Dữ liệu do trình duyệt của sinh viên báo về nên <strong>chỉ là dấu hiệu tham khảo</strong>: có thể do mất mạng, thông báo hệ thống hoặc thao tác vô ý, và cũng có thể bị né tránh (tắt JavaScript, dùng thiết bị khác). Không nên dùng làm căn cứ duy nhất để kết luận gian lận; hãy đối chiếu thêm bằng chứng khác.</p>
    </div>
</section>
<?php endif; ?>

<h2 style="margin:28px 0 14px">Chi tiết từng câu</h2>
<?php foreach ($paper as $n => $q): ?>
    <?php
    $earned = $q['earned'] ?? 0.0;
    $verdict = $earned >= 1.0 ? ['badge-on', 'Đúng'] : ($earned > 0 ? ['badge-lv-medium', 'Đúng một phần'] : ['badge-off', 'Sai / bỏ trống']);
    ?>
    <section class="panel">
        <h2 class="panel-title">Câu <?= $n + 1 ?> <span class="badge <?= $verdict[0] ?>"><?= $verdict[1] ?></span>
            <?php if ($q['multiple']): ?><span class="badge badge-st-draft">Nhiều đáp án</span><?php endif; ?>
            <span class="muted" style="float:right;font-weight:500;font-size:.85rem"><?= e(fmt_score($earned)) ?> điểm</span></h2>
        <div class="panel-body">
            <p class="q-content"><?= e($q['content']) ?></p>
            <?php if ($q['image']): ?>
                <img class="q-image" src="<?= url('/question-images/' . e($q['image'])) ?>" alt="Ảnh minh họa câu hỏi">
            <?php endif; ?>
            <ol class="opt-list">
                <?php foreach ($q['options'] as $i => $o): ?>
                    <li class="<?= $o['is_correct'] ? 'correct' : ($o['selected'] ? 'wrong' : '') ?>">
                        <span class="opt-letter"><?= $letters[$i] ?? $i + 1 ?></span>
                        <span class="opt-text"><?= e($o['content']) ?></span>
                        <?php if ($o['selected']): ?><span class="badge badge-st-draft">SV chọn</span><?php endif; ?>
                        <?php if ($o['is_correct']): ?><span class="badge badge-on">Đáp án đúng</span><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
            <?php if (!empty($q['explanation'])): ?>
                <h3 class="sub-title">Giải thích</h3>
                <p class="q-content" style="margin-bottom:0"><?= e($q['explanation']) ?></p>
            <?php endif; ?>
        </div>
    </section>
<?php endforeach; ?>
