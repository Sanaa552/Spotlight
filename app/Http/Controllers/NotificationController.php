<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    /** Recevoir notification : liste des notifications du citoyen connecté */
  public function index(Request $request): View
    {
        $notifications = $request->user()
            ->appNotifications()
            ->with('declaration')
            ->latest('date_envoi')
            ->paginate(15);

        return view('notifications.index', compact('notifications'));
    }

    public function marquerLue(AppNotification $appNotification): RedirectResponse
    {
        if ($appNotification->user_id !== request()->user()->id) {
            abort(403);
        }

        $appNotification->marquerCommeLue();

        return back();
    }
}