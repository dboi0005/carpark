<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\CardInventoryDetail;
use App\Models\Payment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        try {
            // 🚗 Active parkings — refresh often (every 30 sec)
            $activeParkings = Cache::remember('dashboard.activeParkings', now()->addSeconds(30), function () {
                return Ticket::whereNull('park_out_datetime')
                    ->where('cancelled', 0)
                    ->count();
            });

            // 💳 Total cards — refresh every 10 min (changes rarely)
            $totalCards = Cache::remember('dashboard.totalCards', now()->addMinutes(10), function () {
                return CardInventoryDetail::count();
            });

            // 💰 Total revenue today — refresh every 2 min
            $totalRevenue = Cache::remember('dashboard.totalRevenue', now()->addMinutes(2), function () {
                return Payment::whereDate('paid_at', today())->sum('total_amount');
            });

            // 📊 Revenue chart (7 days) — refresh every 10 min
            $revenueData = Cache::remember('dashboard.revenueData', now()->addMinutes(10), function () {
                return Payment::selectRaw('DATE(paid_at) as date, SUM(total_amount) as total')
                    ->where('paid_at', '>=', now()->subDays(6))
                    ->groupBy('date')
                    ->orderBy('date')
                    ->get();
            });

            // 🕒 Latest park-in — refresh every 30 sec
            $latestParkin = Cache::remember('dashboard.latestParkin', now()->addSeconds(30), function () {
                return Ticket::whereNull('deleted_at')
                    ->orderBy('park_datetime', 'desc')
                    ->select('park_datetime', 'plate_no')
                    ->first();
            });

            // 🏁 Latest park-out — refresh every 30 sec
            $latestParkout = Cache::remember('dashboard.latestParkout', now()->addSeconds(30), function () {
                return Ticket::whereNull('deleted_at')
                    ->orderBy('park_out_datetime', 'desc')
                    ->select('park_out_datetime', 'plate_no')
                    ->first();
            });

            return Inertia::render('Dashboard/Index', [
                'activeParkings' => $activeParkings,
                'totalCards'     => $totalCards,
                'totalRevenue'   => $totalRevenue,
                'revenueData'    => $revenueData,
                'latestParkin'   => $latestParkin,
                'latestParkout'  => $latestParkout,
            ]);
        } catch (\Throwable $e) {
            // 🧯 Log the error for debugging
            Log::error('Dashboard data error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            // 🪶 Fallback response if something breaks
            return Inertia::render('Dashboard/Index', [
                'error' => 'Unable to load dashboard data at the moment.',
                'activeParkings' => 0,
                'totalCards' => 0,
                'totalRevenue' => 0,
                'revenueData' => [],
                'latestParkin' => null,
                'latestParkout' => null,
            ]);
        }
    }
}
