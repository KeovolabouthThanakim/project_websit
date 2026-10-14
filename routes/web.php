<?php
// Khai báo đường dẫn: $router->get|post(đường_dẫn, [Controller::class, 'phương_thức'], [middleware])
// Middleware: 'auth' (phải đăng nhập), 'guest' (chưa đăng nhập), 'role:admin,teacher' (đúng vai trò)

use App\Controllers\AnalyticsController;
use App\Controllers\AuditController;
use App\Controllers\BackupController;
use App\Controllers\AuthController;
use App\Controllers\ClassController;
use App\Controllers\ExamController;
use App\Controllers\ExamGrantController;
use App\Controllers\ExamResultController;
use App\Controllers\DashboardController;
use App\Controllers\HelpController;
use App\Controllers\HomeController;
use App\Controllers\NotificationController;
use App\Controllers\PasswordResetController;
use App\Controllers\ProfileController;
use App\Controllers\QuestionController;
use App\Controllers\ReportController;
use App\Controllers\QuestionImportController;
use App\Controllers\StudentExamController;
use App\Controllers\SettingsController;
use App\Controllers\SubjectController;
use App\Controllers\SystemController;
use App\Controllers\UserController;
use App\Controllers\UserImportController;

/** @var App\Core\Router $router */

$router->get('/', [HomeController::class, 'index']);

// Hướng dẫn sử dụng (công khai)
$router->get('/help', [HelpController::class, 'index']);

// Đăng nhập / đăng xuất
$router->get('/login',  [AuthController::class, 'showLogin'], ['guest']);
$router->post('/login', [AuthController::class, 'login'],     ['guest']);
$router->post('/logout', [AuthController::class, 'logout'],   ['auth']);

// Quên mật khẩu (gửi liên kết đặt lại qua email)
$router->get('/forgot-password',  [PasswordResetController::class, 'showForgot'], ['guest']);
$router->post('/forgot-password', [PasswordResetController::class, 'sendLink'],   ['guest']);
$router->get('/reset-password',   [PasswordResetController::class, 'showReset'],  ['guest']);
$router->post('/reset-password',  [PasswordResetController::class, 'reset'],      ['guest']);

$router->get('/dashboard', [DashboardController::class, 'index'], ['auth']);

// Hồ sơ cá nhân (mọi vai trò): xem thông tin, đổi email, đổi mật khẩu
$router->get('/profile',           [ProfileController::class, 'show'],           ['auth']);
$router->post('/profile',          [ProfileController::class, 'updateEmail'],    ['auth']);
$router->post('/profile/password', [ProfileController::class, 'updatePassword'], ['auth']);

// Thông báo trong hệ thống (mọi vai trò)
$router->get('/notifications',                   [NotificationController::class, 'index'],   ['auth']);
$router->post('/notifications/read-all',         [NotificationController::class, 'readAll'], ['auth']);
$router->get('/notifications/{id}/open',         [NotificationController::class, 'open'],    ['auth']);

// Quản lý người dùng (chỉ quản trị viên)
$admin = ['auth', 'role:admin'];
$router->get('/admin/backup',                          [BackupController::class, 'index'],         $admin);
$router->post('/admin/backup/download',                [BackupController::class, 'download'],      $admin);
$router->post('/admin/backup/save',                    [BackupController::class, 'save'],          $admin);
$router->post('/admin/backup/files',                   [BackupController::class, 'downloadFiles'], $admin);
$router->get('/admin/backup/files/{name}',             [BackupController::class, 'downloadSaved'], $admin);
$router->post('/admin/backup/files/{name}/delete',     [BackupController::class, 'deleteSaved'],   $admin);
$router->post('/admin/backup/restore',                 [BackupController::class, 'restore'],       $admin);
$router->get('/admin/audit',     [AuditController::class, 'index'],    $admin);
$router->get('/admin/settings',  [SettingsController::class, 'show'],   $admin);
$router->post('/admin/settings', [SettingsController::class, 'update'], $admin);
$router->get('/admin/users',                 [UserController::class, 'index'],         $admin);
$router->get('/admin/users/create',          [UserController::class, 'create'],        $admin);
// Nhập hàng loạt tài khoản + xếp lớp từ Excel / CSV
$router->get('/admin/users/import',                  [UserImportController::class, 'form'],        $admin);
$router->post('/admin/users/import/preview',         [UserImportController::class, 'preview'],     $admin);
$router->get('/admin/users/import/preview',          [UserImportController::class, 'showPreview'], $admin);
$router->post('/admin/users/import/confirm',         [UserImportController::class, 'confirm'],     $admin);
$router->get('/admin/users/import/result',           [UserImportController::class, 'result'],      $admin);
$router->get('/admin/users/import/result.csv',       [UserImportController::class, 'download'],    $admin);
$router->get('/admin/users/import/template',         [UserImportController::class, 'template'],    $admin);
$router->post('/admin/users',                [UserController::class, 'store'],         $admin);
$router->get('/admin/users/{id}/edit',       [UserController::class, 'edit'],          $admin);
$router->post('/admin/users/{id}',           [UserController::class, 'update'],        $admin);
$router->post('/admin/users/{id}/password',  [UserController::class, 'resetPassword'], $admin);
$router->post('/admin/users/{id}/toggle',    [UserController::class, 'toggle'],        $admin);
$router->post('/admin/users/{id}/delete',    [UserController::class, 'destroy'],       $admin);

// Báo cáo tổng quan toàn hệ thống (chỉ quản trị viên)
$router->get('/admin/reports',        [ReportController::class, 'index'],  $admin);
$router->get('/admin/reports/export', [ReportController::class, 'export'], $admin);

// Lớp học
// - Quản trị viên: toàn quyền. Giáo viên: chỉ xem / thêm / bớt sinh viên ở lớp mình chủ nhiệm
//   (quyền sở hữu lớp được kiểm tra trong ClassController::findAndAuthorize)
// Lưu ý: '/classes/create' phải khai báo TRƯỚC '/classes/{id}'
$staff = ['auth', 'role:admin,teacher'];
$router->get('/classes',                         [ClassController::class, 'index'],         $staff);
$router->get('/classes/create',                  [ClassController::class, 'create'],        $admin);
$router->post('/classes',                        [ClassController::class, 'store'],         $admin);
$router->get('/classes/{id}',                    [ClassController::class, 'show'],          $staff);
$router->get('/classes/{id}/edit',               [ClassController::class, 'edit'],          $admin);
$router->post('/classes/{id}',                   [ClassController::class, 'update'],        $admin);
$router->post('/classes/{id}/delete',            [ClassController::class, 'destroy'],       $admin);
$router->post('/classes/{id}/students',          [ClassController::class, 'addStudents'],   $staff);
$router->post('/classes/{id}/students/{uid}/remove', [ClassController::class, 'removeStudent'], $staff);
// Môn học của lớp (chỉ quản trị viên gán / bỏ)
$router->post('/classes/{id}/subjects',                [ClassController::class, 'addSubject'],    $admin);
$router->post('/classes/{id}/subjects/{sid}/remove',   [ClassController::class, 'removeSubject'], $admin);

// Môn học
// - Quản trị viên: toàn quyền. Giáo viên: chỉ xem các môn mình được phân công
//   (quyền xem được kiểm tra trong SubjectController::show)
// Lưu ý: '/subjects/create' phải khai báo TRƯỚC '/subjects/{id}'
$router->get('/subjects',                  [SubjectController::class, 'index'],   $staff);
$router->get('/subjects/create',           [SubjectController::class, 'create'],  $admin);
$router->post('/subjects',                 [SubjectController::class, 'store'],   $admin);
$router->get('/subjects/{id}',             [SubjectController::class, 'show'],    $staff);
// Thống kê môn học qua nhiều kỳ thi (quyền kiểm tra trong AnalyticsController)
$router->get('/subjects/{id}/analytics',   [AnalyticsController::class, 'subject'], $staff);
$router->get('/subjects/{id}/edit',        [SubjectController::class, 'edit'],    $admin);
$router->post('/subjects/{id}',            [SubjectController::class, 'update'],  $admin);
$router->post('/subjects/{id}/toggle',     [SubjectController::class, 'toggle'],  $admin);
$router->post('/subjects/{id}/delete',     [SubjectController::class, 'destroy'], $admin);
// Lớp học của môn (chỉ quản trị viên gán / bỏ)
$router->post('/subjects/{id}/classes',                [SubjectController::class, 'addClass'],    $admin);
$router->post('/subjects/{id}/classes/{cid}/remove',   [SubjectController::class, 'removeClass'], $admin);

// Ngân hàng câu hỏi
// - Quản trị viên: mọi môn. Giáo viên: chỉ câu hỏi của các môn mình được phân công
//   (quyền được kiểm tra trong QuestionController::canManage)
// Lưu ý: '/questions/create' phải khai báo TRƯỚC '/questions/{id}'
$router->get('/questions',                 [QuestionController::class, 'index'],   $staff);
$router->get('/questions/create',          [QuestionController::class, 'create'],  $staff);
// Nhập hàng loạt từ Excel / CSV / Word / văn bản (phải khai báo TRƯỚC '/questions/{id}')
$router->get('/questions/import',                    [QuestionImportController::class, 'form'],        $staff);
$router->post('/questions/import/preview',           [QuestionImportController::class, 'preview'],     $staff);
$router->get('/questions/import/preview',            [QuestionImportController::class, 'showPreview'], $staff);
$router->post('/questions/import/confirm',           [QuestionImportController::class, 'confirm'],     $staff);
$router->get('/questions/import/template/{type}',    [QuestionImportController::class, 'template'],    $staff);
$router->post('/questions',                [QuestionController::class, 'store'],   $staff);
$router->get('/questions/{id}',            [QuestionController::class, 'show'],    $staff);
$router->get('/questions/{id}/edit',       [QuestionController::class, 'edit'],    $staff);
$router->post('/questions/{id}',           [QuestionController::class, 'update'],  $staff);
$router->post('/questions/{id}/toggle',    [QuestionController::class, 'toggle'],  $staff);
$router->post('/questions/{id}/delete',    [QuestionController::class, 'destroy'], $staff);
// Ảnh câu hỏi (thư mục uploads/ không truy cập trực tiếp được nên phải đọc qua controller)
$router->get('/question-images/{file}',    [QuestionController::class, 'image'],   $staff);

// Đề thi / kỳ thi
// - Quản trị viên: mọi môn. Giáo viên: chỉ đề thi của các môn mình được phân công
//   (quyền được kiểm tra trong ExamController::findAuthorized)
// Lưu ý: '/exams/create' phải khai báo TRƯỚC '/exams/{id}'
$router->get('/exams',                     [ExamController::class, 'index'],  $staff);
$router->get('/exams/create',              [ExamController::class, 'create'], $staff);
$router->post('/exams',                    [ExamController::class, 'store'],  $staff);
$router->get('/exams/{id}',                [ExamController::class, 'show'],   $staff);
$router->get('/exams/{id}/edit',           [ExamController::class, 'edit'],   $staff);
$router->post('/exams/{id}',               [ExamController::class, 'update'], $staff);
$router->post('/exams/{id}/status',        [ExamController::class, 'status'], $staff);
$router->post('/exams/{id}/delete',        [ExamController::class, 'destroy'], $staff);
$router->post('/exams/{id}/copy',          [ExamController::class, 'copy'],    $staff);
$router->post('/exams/{id}/questions',            [ExamController::class, 'addQuestions'],    $staff);
$router->post('/exams/{id}/questions/random',     [ExamController::class, 'randomQuestions'], $staff);
$router->post('/exams/{id}/questions/clear',      [ExamController::class, 'clearQuestions'],  $staff);
$router->post('/exams/{id}/questions/{qid}/remove', [ExamController::class, 'removeQuestion'], $staff);

// Kết quả & thống kê kỳ thi (bảng điểm, phân bố điểm, xuất CSV) - quyền kiểm tra trong ExamResultController
// Lưu ý: '/results/export' và '/results/print' phải khai báo TRƯỚC '/results/{aid}'
$router->get('/exams/{id}/monitor',          [ExamResultController::class, 'monitor'],     $staff);
$router->get('/exams/{id}/monitor/data',     [ExamResultController::class, 'monitorData'], $staff);
$router->get('/exams/{id}/results',          [ExamResultController::class, 'index'],   $staff);
$router->get('/exams/{id}/results/export',   [ExamResultController::class, 'export'],  $staff);
$router->get('/exams/{id}/results/print',    [ExamResultController::class, 'printSheet'], $staff);
$router->get('/exams/{id}/results/{aid}',    [ExamResultController::class, 'attempt'], $staff);
// Cấp quyền riêng cho một sinh viên: cộng thêm thời gian, cho thi lại, thi muộn (cần database/010_exam_grants.sql)
$router->get('/exams/{id}/grants/{uid}',     [ExamGrantController::class, 'show'],     $staff);
$router->post('/exams/{id}/grants/{uid}',    [ExamGrantController::class, 'update'],   $staff);

// Sinh viên: kỳ thi của lớp mình, làm bài, nộp bài, xem kết quả
// - Chỉ thấy kỳ thi đã công bố cho lớp của mình; mỗi bài làm chỉ chủ nhân mới truy cập được (kiểm tra trong StudentExamController)
$student = ['auth', 'role:student'];
$router->get('/my-exams',                     [StudentExamController::class, 'index'],  $student);
$router->get('/my-exams/{id}',                [StudentExamController::class, 'show'],   $student);
$router->get('/my-schedule',                  [StudentExamController::class, 'schedule'], $student);
$router->get('/my-results',                   [StudentExamController::class, 'results'], $student);
$router->get('/my-results/print',             [StudentExamController::class, 'resultsPrint'], $student);
$router->post('/my-exams/{id}/start',         [StudentExamController::class, 'start'],  $student);
$router->get('/attempts/{id}',                [StudentExamController::class, 'take'],   $student);
$router->post('/attempts/{id}/answer',        [StudentExamController::class, 'answer'], $student);
$router->post('/attempts/{id}/submit',        [StudentExamController::class, 'submit'], $student);
$router->get('/attempts/{id}/result',         [StudentExamController::class, 'result'], $student);
$router->get('/attempts/{id}/print',          [StudentExamController::class, 'printAttempt'], $student);
$router->post('/attempts/{id}/event',         [StudentExamController::class, 'event'],  $student);
$router->get('/attempts/{id}/images/{file}',  [StudentExamController::class, 'image'],  $student);

// Kiểm tra kết nối CSDL (chỉ hoạt động khi debug = true)
$router->get('/db-check', [SystemController::class, 'dbCheck']);