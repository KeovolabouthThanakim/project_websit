<?php
namespace App\Controllers;

use App\Core\App;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Response;
use PDOException;

class SystemController extends Controller
{
    /** /db-check - kiểm tra kết nối cơ sở dữ liệu từng bước (chỉ khi debug = true) */
    public function dbCheck(): void
    {
        if (!App::config('debug')) {
            Response::abort(404);
        }

        $c = App::config('db');
        $checks = [];
        $add = function (string $name, bool $ok, string $detail = '') use (&$checks) {
            $checks[] = ['name' => $name, 'ok' => $ok, 'detail' => $detail];
            return $ok;
        };

        // 1. Extension
        $hasDriver = extension_loaded('pdo_mysql');
        $add('PHP có extension pdo_mysql', $hasDriver,
            $hasDriver ? '' : 'Mở php.ini, bỏ dấu ; trước extension=pdo_mysql rồi khởi động lại Apache.');

        if ($hasDriver) {
            // 2. Kết nối tới máy chủ MySQL
            $server = null;
            try {
                $server = Database::connect(false);
                $add("Kết nối MySQL tại {$c['host']}:{$c['port']} (user: {$c['user']})", true);
            } catch (PDOException $e) {
                $add("Kết nối MySQL tại {$c['host']}:{$c['port']} (user: {$c['user']})", false,
                    Database::explain($e) . ' [' . $e->getMessage() . ']');
            }

            // 3. Database tồn tại
            if ($server) {
                $stmt = $server->prepare('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?');
                $stmt->execute([$c['name']]);
                $dbExists = (bool) $stmt->fetch();
                $add("Có database \"{$c['name']}\"", $dbExists,
                    $dbExists ? '' : 'Nhập database/schema.sql trong phpMyAdmin (tab Nhập / Import).');

                // 4. Bảng users + dữ liệu
                if ($dbExists) {
                    $pdo = Database::connect(true);
                    $hasTable = (bool) $pdo->query("SHOW TABLES LIKE 'users'")->fetch();
                    $add('Có bảng "users"', $hasTable, $hasTable ? '' : 'Nhập database/schema.sql.');

                    if ($hasTable) {
                        $n = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
                        $add("Bảng users có dữ liệu ($n người dùng)", $n > 0,
                            $n > 0 ? '' : 'Nhập database/seed.sql để có tài khoản đăng nhập.');
                    }
                }
            }
        }

        $this->view('system/db-check', ['checks' => $checks, 'cfg' => $c], null);
    }
}
