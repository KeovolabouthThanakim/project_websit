<?php
use App\Models\Exam;
?>
<div class="page-head">
    <div>
        <h1>Kỳ thi của tôi</h1>
        <p>Các kỳ thi dành cho lớp của bạn. Mỗi kỳ thi chỉ được làm một lần.</p>
    </div>
</div>

<?php if (!$hasClass): ?>
    <section class="panel"><div class="panel-body"><p class="muted" style="margin:0">Bạn chưa được xếp vào lớp nào nên chưa có kỳ thi. Vui lòng liên hệ giáo viên hoặc quản trị viên.</p></div></section>
<?php else: ?>
<div class="table-wrap">
    <table class="table">
        <thead>
            <tr><th>STT</th><th>Kỳ thi</th><th>Mở cổng - đóng cổng</th><th>Thời gian</th><th>Trạng thái</th><th>Điểm</th><th>Thao tác</th></tr>
        </thead>
        <tbody>
        <?php foreach ($items as $i => $x): ?>
            <?php
            $st = Exam::state($x);
            $done = $x['attempt_status'] === 'submitted';
            $running = $x['attempt_status'] === 'in_progress';
            ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td class="q-cell">
                    <a href="<?= url('/my-exams/' . (int) $x['id']) ?>"><strong><?= e($x['title']) ?></strong></a>
                    <div class="muted" style="font-size:.82rem"><?= e($x['subject_code'] . ' - ' . $x['subject_name']) ?> · <?= (int) $x['question_count'] ?> câu</div>
                </td>
                <td style="white-space:nowrap"><?= e(fmt_date($x['start_at'])) ?><br><span class="muted"><?= e(fmt_date($x['end_at'])) ?></span></td>
                <td style="white-space:nowrap"><?= (int) $x['duration_minutes'] ?> phút</td>
                <td>
                    <?php if ($done): ?><span class="badge badge-on">Đã nộp bài</span>
                    <?php elseif ($running): ?><span class="badge badge-lv-medium">Đang làm dở</span>
                    <?php else: ?><span class="badge badge-st-<?= e($st) ?>"><?= e(Exam::STATES[$st]) ?></span><?php endif; ?>
                </td>
                <td><strong><?= $done ? e(fmt_score($x['score'])) : '-' ?></strong></td>
                <td class="actions" style="white-space:nowrap;min-width:0">
                    <?php if ($done): ?>
                        <a class="btn btn-sm btn-outline" href="<?= url('/attempts/' . (int) $x['attempt_id'] . '/result') ?>">Xem kết quả</a>
                    <?php elseif ($running): ?>
                        <a class="btn btn-sm" href="<?= url('/attempts/' . (int) $x['attempt_id']) ?>">Tiếp tục làm bài</a>
                    <?php elseif ($st === 'open'): ?>
                        <a class="btn btn-sm" href="<?= url('/my-exams/' . (int) $x['id']) ?>">Vào thi</a>
                    <?php else: ?>
                        <a class="btn btn-sm btn-outline" href="<?= url('/my-exams/' . (int) $x['id']) ?>">Chi tiết</a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$items): ?><tr><td colspan="7" class="empty">Lớp của bạn chưa có kỳ thi nào được công bố.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
