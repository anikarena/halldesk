<?php

namespace App\Http\Controllers;

use App\Models\Notice;
use App\Models\Room;
use App\Services\ReportService;
use Illuminate\View\View;

/**
 * Public landing page: what the hall offers, free seats, public notices.
 */
class HomeController extends Controller
{
    public function index(ReportService $reports): View
    {
        $roomTypes = $reports->occupancyByRoomType()
            ->map(fn (array $type) => $type + ['photo' => Room::photoFor($type['capacity'])]);

        return view('home', [
            'occupancy' => $reports->occupancy(),
            'roomTypes' => $roomTypes,
            'rooms' => Room::query()->count(),
            'floors' => Room::query()->distinct()->count('floor'),
            'notices' => Notice::query()->live()->where('is_public', true)->boardOrder()->limit(3)->get(),
        ]);
    }

    public function notice(Notice $notice): View
    {
        abort_unless($notice->is_public && $notice->isLive(), 404);

        return view('public-notice', ['notice' => $notice]);
    }
}
