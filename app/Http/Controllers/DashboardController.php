<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        // Phase 1 baseline KPIs; role-specific widgets land in Phase 9.
        $kpis = [
            'sites' => $user->bypassesSiteScope()
                ? Site::count()
                : $user->siteIds()->count(),
        ];

        if ($user->bypassesSiteScope()) {
            $kpis['users'] = User::count();
            $kpis['engineers'] = User::role('engineer')->count();
            $kpis['ics'] = User::role('ics')->count();
        }

        return Inertia::render('dashboard', [
            'kpis' => $kpis,
            'mySites' => $user->accessibleSites()->map->only('id', 'code', 'name'),
        ]);
    }
}
