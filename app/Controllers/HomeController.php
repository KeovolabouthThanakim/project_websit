<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;

class HomeController extends Controller
{
    /** Đã đăng nhập -> trang chủ theo vai trò; chưa đăng nhập -> trang giới thiệu */
    public function index(): void
    {
        if (Auth::check()) {
            $this->redirect('/dashboard');
        }
        $this->view('pages/landing', ['title' => ''], 'layouts/public');
    }
}
