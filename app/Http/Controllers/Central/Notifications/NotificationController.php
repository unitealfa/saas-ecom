<?php

namespace App\Http\Controllers\Central\Notifications;

use App\Http\Controllers\Controller;
use App\Models\Central\Notifications\DatabaseNotification;
use App\Services\Central\Notifications\NotificationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function index(): View
    {
        return view('central.pages.notifications.index', ['notifications' => $this->notifications->paginate($this->centralActor())]);
    }

    public function read(DatabaseNotification $notification): RedirectResponse
    {
        $this->notifications->markRead($this->centralActor(), $notification);

        return to_route('notifications.index')->with('status', 'Notification marquée comme lue.');
    }
}
