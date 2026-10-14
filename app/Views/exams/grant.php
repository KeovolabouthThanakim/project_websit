<?php
use App\Models\Exam;

$examId = (int) $exam['id'];
$uid = (int) $student['id'];
$action = url('/exams/' . $examId . '/grants/' . $uid);
$inProgress = $attempt && $attempt['status'] === 'in_progress';
$submitted = $attempt && $attempt['status'] === 'submitted';
$none = !$attempt;
?>
<div class="page-head">
    <div>
        <h1>Cấp quyền thi: <?= e($student['full_name']) ?></h1>
        <p><?= e(sv_code($student)) ?><?= $student['class_code'] ? ' · lớp ' . e($student['class_code']) : '' ?> · <?= e($exam['title']) ?> <span class="badge badge-st-<?= e($state) ?>"><?= e(Exam::STATES[$state]) ?></span></p>
    </div>
    <div class="header-actions">
        <a class="btn btn-outline" href="<?= url('/exams/' . $examId . '/results') ?>">&laquo; Bảng điểm</a>
    </div>
</div>

<?php if (!$ready): ?>
    <section class="panel"><div class="panel-body">
        <p style="margin:0">Chưa có bảng dữ liệu cho chức năng này. Hãy nhập file <code>database/010_exam_grants.sql</code> trong phpMyAdmin (không xóa dữ liệu, chạy lại nhiều lần vẫn an toàn) rồi tải lại trang.</p>
    </div></section>
<?php else: ?>

<div class="two-col">
    <section class="panel">
        <h2 class="panel-title">Tình trạng hiện tại</h2>
        <div class="panel-body">
            <dl class="info">
                <dt>Bài làm</dt>
                <dd>
                    <?php if ($none): ?>
                        <span class="badge badge-off">Chưa vào thi</span>
                    <?php elseif ($inProgress): ?>
                        <span class="badge badge-st-upcoming">Đang làm</span> bắt đầu <?= e(fmt_date($attempt['started_at'])) ?>, hạn nộp <strong><?= e(fmt_date($attempt['deadline_at'])) ?></strong>
                    <?php else: ?>
                        <span class="badge badge-on">Đã nộp</span> lúc <?= e(fmt_date($attempt['submitted_at'])) ?>, điểm <strong><?= e(fmt_score($attempt['score'])) ?></strong>/10<?= $attempt['auto_submitted'] ? ' (hết giờ, hệ thống tự nộp)' : '' ?>
                    <?php endif; ?>
                </dd>
                <dt>Quyền đang chờ</dt>
                <dd>
                    <?php if ($grant): ?>
                        Được vào thi đến <strong><?= e(fmt_date($grant['valid_until'])) ?></strong><?= (int) $grant['extra_minutes'] > 0 ? ', cộng thêm ' . (int) $grant['extra_minutes'] . ' phút' : '' ?>.
                        <?php if ($grant['note']): ?><br><span class="muted">Ghi chú: <?= e($grant['note']) ?></span><?php endif; ?>
                    <?php else: ?>
                        <span class="muted">Không có</span>
                    <?php endif; ?>
                </dd>
                <dt>Thời gian làm bài</dt><dd><?= (int) $exam['duration_minutes'] ?> phút · cổng thi <?= e(fmt_date($exam['start_at'])) ?> - <?= e(fmt_date($exam['end_at'])) ?></dd>
            </dl>
            <?php if ($grant): ?>
                <form method="post" action="<?= $action ?>" data-confirm="Thu hồi quyền vào thi đã cấp cho sinh viên này?">
                    <?= csrf_field() ?><input type="hidden" name="action" value="revoke">
                    <button class="btn btn-sm btn-outline" type="submit">Thu hồi quyền đang chờ</button>
                </form>
            <?php endif; ?>
        </div>
    </section>

    <section class="panel">
        <h2 class="panel-title">Lịch sử thi lại (<?= count($history) ?>)</h2>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Bài đã hủy</th><th>Điểm</th><th>Hủy lúc</th><th>Bởi / lý do</th></tr></thead>
                <tbody>
                <?php foreach ($history as $h): ?>
                    <tr>
                        <td data-label="Bài đã hủy"><?= $h['status'] === 'submitted' ? 'Đã nộp ' . e(fmt_date($h['submitted_at'])) : 'Đang làm (bắt đầu ' . e(fmt_date($h['started_at'])) . ')' ?></td>
                        <td data-label="Điểm"><?= $h['status'] === 'submitted' ? '<strong>' . e(fmt_score($h['score'])) . '</strong>' : '-' ?></td>
                        <td data-label="Hủy lúc"><?= e(fmt_date($h['archived_at'])) ?></td>
                        <td data-label="Bởi / lý do"><?= e((string) $h['by_name']) ?><?= $h['note'] ? '<br><span class="muted">' . e($h['note']) . '</span>' : '' ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$history): ?><tr><td colspan="4" class="empty">Chưa từng cho sinh viên này thi lại.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<?php if (!$canGrant): ?>
    <section class="panel"><div class="panel-body">
        <p style="margin:0">Đề thi đang ở trạng thái <strong><?= e(Exam::STATES[$state]) ?></strong>. Sinh viên chỉ thấy đề đã công bố, nên hãy mở lại đề thi (ở trang chi tiết đề thi) trước khi cấp quyền.</p>
    </div></section>
<?php else: ?>

<?php if ($inProgress): ?>
<section class="panel">
    <h2 class="panel-title">Cộng thêm thời gian cho bài đang làm</h2>
    <div class="panel-body">
        <p class="muted" style="margin-top:0">Dùng khi sinh viên bị rớt mạng hoặc sự cố máy. Thời gian được cộng vào hạn nộp hiện tại; bài làm và đáp án đã chọn được giữ nguyên.</p>
        <form method="post" action="<?= $action ?>" class="form-grid" autocomplete="off">
            <?= csrf_field() ?><input type="hidden" name="action" value="extend">
            <div class="form-row">
                <label for="minutes">Số phút cộng thêm</label>
                <input type="number" id="minutes" name="minutes" min="1" max="<?= (int) $maxExtra ?>" value="10" required>
            </div>
            <div class="form-row"><label>&nbsp;</label><button class="btn" type="submit">Cộng thêm thời gian</button></div>
        </form>
    </div>
</section>
<?php endif; ?>

<?php if ($none): ?>
<section class="panel">
    <h2 class="panel-title">Cho vào thi (thi muộn / thi bù)</h2>
    <div class="panel-body">
        <p class="muted" style="margin-top:0">Sinh viên sẽ vào thi được dù cổng thi chưa mở hoặc đã đóng, trong khoảng thời gian bạn chọn. Vẫn chỉ làm một lần.</p>
        <form method="post" action="<?= $action ?>" autocomplete="off">
            <?= csrf_field() ?><input type="hidden" name="action" value="allow">
            <?php require __DIR__ . '/_grant_fields.php'; ?>
            <button class="btn" type="submit">Cho vào thi</button>
        </form>
    </div>
</section>
<?php else: ?>
<section class="panel">
    <h2 class="panel-title">Cho thi lại</h2>
    <div class="panel-body">
        <p style="margin-top:0">Bài hiện tại (<?= $inProgress ? 'đang làm dở' : 'điểm ' . e(fmt_score($attempt['score'])) ?>) sẽ bị <strong>xóa</strong> cùng đáp án và nhật ký rời trang; điểm cũ được lưu vào lịch sử bên trên. Sinh viên làm lại từ đầu với đề được xáo lại.</p>
        <p class="muted">Lưu ý: sau khi kỳ thi kết thúc, các sinh viên khác có thể đã xem đáp án đúng.</p>
        <form method="post" action="<?= $action ?>" autocomplete="off" data-confirm="Hủy bài hiện tại của sinh viên này và cho thi lại? Việc này không hoàn tác được (điểm cũ vẫn được lưu trong lịch sử).">
            <?= csrf_field() ?><input type="hidden" name="action" value="retake">
            <?php require __DIR__ . '/_grant_fields.php'; ?>
            <button class="btn btn-danger" type="submit">Hủy bài và cho thi lại</button>
        </form>
    </div>
</section>
<?php endif; ?>

<?php endif; ?>
<?php endif; ?>
