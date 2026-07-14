<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;

class AuditLogController extends Controller
{
    public function index()
    {
        $logs = AuditLog::query()
            ->with('user:id,email')
            ->latest('id')
            ->paginate(50);

        return view('admin.audit.index', compact('logs'));
    }
}
