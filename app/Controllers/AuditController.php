<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\Audit;

class AuditController extends Controller
{
    private const PER_PAGE = 30;

    public function index(): void
    {
        $filters = [
            'q'     => str_cut(trim((string) $this->request->input('q', '')), 100),
            'group' => (string) $this->request->input('group', ''),
            'from'  => (string) $this->request->input('from', ''),
            'to'    => (string) $this->request->input('to', ''),
        ];
        $result = Audit::search($filters, (int) $this->request->input('page', 1), self::PER_PAGE);
        $this->view('admin/audit', [
            'title'   => 'Nhật ký hoạt động',
            'result'  => $result,
            'filters' => $filters,
            'ready'   => Audit::ready(),
            'perPage' => self::PER_PAGE,
        ]);
    }
}
