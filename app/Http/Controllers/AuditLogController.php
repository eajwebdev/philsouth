<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('logs.view'), 403);

        $logs = AuditLog::query()
            ->with(['user:id,name', 'site:id,code,name'])
            ->when($request->string('action')->isNotEmpty(), fn ($q) => $q->where('action', $request->string('action')))
            ->when($request->string('search')->isNotEmpty(), function ($q) use ($request) {
                $s = $request->string('search')->value();
                $q->where(fn ($w) => $w->where('description', 'like', "%{$s}%")
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$s}%")));
            })
            ->latest()
            ->paginate(10)
            ->withQueryString()
            ->through(fn (AuditLog $l) => [
                'id' => $l->id,
                'action' => $l->action,
                'description' => $l->description,
                'user' => $l->user?->name,
                'site' => $l->site ? $l->site->code : null,
                'properties' => $l->properties,
                'at' => $l->created_at?->toIso8601String(),
            ]);

        return Inertia::render('logs/index', [
            'logs' => $logs,
            'filters' => [
                'search' => $request->string('search')->value(),
                'action' => $request->string('action')->value(),
            ],
            'actions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action'),
        ]);
    }
}
