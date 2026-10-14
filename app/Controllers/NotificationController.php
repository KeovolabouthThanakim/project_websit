<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Response;
use App\Models\Notification;

class NotificationController extends Controller
{
    private const PER_PAGE = 20;

    public function index(): void
    {
        $result = Notification::paginate((int) $this->user()['id'], (int) $this->request->input('page', 1), self::PER_PAGE);
        $this->view('notifications/index', ['title' => 'Thông báo', 'result' => $result, 'ready' => Notification::ready()]);
    }

    /** Mở một thông báo: đánh dấu đã đọc rồi chuyển tới trang liên quan */
    public function open(string $id): void
    {
        $n = ctype_digit($id) && Notification::ready() ? Notification::ofUser((int) $id, (int) $this->user()['id']) : null;
        if (!$n) {
            Response::abort(404, 'Không tìm thấy thông báo.');
        }
        Notification::markRead((int) $n['id'], (int) $this->user()['id']);
        $url = (string) $n['url'];
        // Chỉ chuyển tới đường dẫn nội bộ
        $this->redirect($url !== '' && $url[0] === '/' && strpos($url, '//') !== 0 ? $url : '/notifications');
    }

    public function readAll(): void
    {
        Notification::markAllRead((int) $this->user()['id']);
        $this->redirect('/notifications');
    }
}
