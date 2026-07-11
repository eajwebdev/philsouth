<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /** Mark one notification read (or all when no id is given). */
    public function markRead(Request $request, ?string $id = null): RedirectResponse
    {
        $user = $request->user();

        if ($id) {
            $user->notifications()->whereKey($id)->update(['read_at' => now()]);
        } else {
            $user->unreadNotifications->markAsRead();
        }

        return back();
    }
}
