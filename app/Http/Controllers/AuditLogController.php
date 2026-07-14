<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $logs = AuditLog::query()
            ->with(['user', 'auditable'])
            ->when($request->input('action'), fn ($q, $action) => $q->where('action', $action))
            ->when($request->input('user_id'), fn ($q, $userId) => $q->where('user_id', $userId))
            ->when($request->input('date_from'), fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($request->input('date_to'), fn ($q, $date) => $q->whereDate('created_at', '<=', $date))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return view('admin.audit-logs.index', compact('logs'));
    }

    public function show(AuditLog $auditLog): View
    {
        $auditLog->load(['user', 'auditable']);

        return view('admin.audit-logs.show', compact('auditLog'));
    }
}
