<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;

/** Hướng dẫn sử dụng: xem được cả khi chưa đăng nhập (sinh viên cần đọc trước giờ thi) */
class HelpController extends Controller
{
    public function index(): void
    {
        $user = Auth::user();
        $this->view('pages/help', [
            'title' => 'Hướng dẫn sử dụng',
            'role'  => $user['role'] ?? '',
        ], $user ? 'layouts/main' : 'layouts/public');
    }
}
