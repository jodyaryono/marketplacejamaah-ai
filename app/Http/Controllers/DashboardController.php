<?php

namespace App\Http\Controllers;

use App\Models\AnalyticsDaily;
use App\Models\Contact;
use App\Models\Listing;
use App\Models\Message;
use App\Models\WhatsappGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'total_messages' => Message::count(),
            'total_ads' => Message::where('is_ad', true)->count(),
            'total_listings' => Listing::count(),
            'total_contacts' => Contact::count(),
            'total_groups' => WhatsappGroup::where('is_active', true)->count(),
            'today_messages' => Message::whereDate('created_at', today())->count(),
            'today_ads' => Message::whereDate('created_at', today())->where('is_ad', true)->count(),
            'today_listings' => Listing::whereDate('created_at', today())->count(),
            // Moderation stats
            'total_deleted' => Message::whereIn('message_category', ['non_ad_deleted', 'extraction_failed'])->count(),
            'today_deleted' => Message::whereIn('message_category', ['non_ad_deleted', 'extraction_failed'])->whereDate('created_at', today())->count(),
            'total_violations' => Message::where('violation_detected', true)->count(),
            'today_violations' => Message::where('violation_detected', true)->whereDate('created_at', today())->count(),
            'total_blocked' => Contact::where('is_blocked', true)->count(),
            'today_contacts' => Contact::whereDate('created_at', today())->count(),
            'registered_count' => Contact::where('is_registered', true)->count(),
        ];

        $recentMessages = Message::with('group')
            ->latest()
            ->limit(10)
            ->get();

        $recentListings = Listing::with(['category', 'group'])
            ->latest()
            ->limit(8)
            ->get();

        $chartData = AnalyticsDaily::select('date',
                DB::raw('SUM(total_messages) as messages'),
                DB::raw('SUM(total_ads) as ads'),
                DB::raw('SUM(total_listings) as listings'))
            ->where('date', '>=', now()->subDays(7))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $topCategories = \App\Models\Category::withCount('listings')
            ->orderByDesc('listings_count')
            ->limit(5)
            ->get();

        // Violators: contacts with warnings or violations, with their latest violation message
        $violators = Contact::where(function ($q) {
            $q
                ->where('warning_count', '>', 0)
                ->orWhere('total_violations', '>', 0)
                ->orWhere('is_blocked', true);
        })
            ->orderByDesc('total_violations')
            ->orderByDesc('warning_count')
            ->limit(20)
            ->get()
            ->map(function ($contact) {
                $lastViolation = Message::where('sender_number', $contact->phone_number)
                    ->where('violation_detected', true)
                    ->orderByDesc('created_at')
                    ->first();
                $contact->last_violation_message = $lastViolation;
                return $contact;
            });

        // Deleted messages — latest 10
        $recentDeleted = Message::with('group')
            ->whereIn('message_category', ['non_ad_deleted', 'extraction_failed'])
            ->latest()
            ->limit(10)
            ->get();

        // Pending onboarding: contacts that haven't completed registration
        $pendingContacts = Contact::where(function ($q) {
            $q
                ->where('is_registered', false)
                ->orWhereNull('is_registered');
        })
            ->where(function ($q) {
                $q
                    ->whereNull('onboarding_status')
                    ->orWhere('onboarding_status', '!=', 'completed');
            })
            ->where('is_blocked', false)
            ->orderBy('created_at', 'desc')
            ->get();

        return view('dashboard.index', compact(
            'stats', 'recentMessages', 'recentListings', 'chartData', 'topCategories',
            'violators', 'recentDeleted', 'pendingContacts'
        ));
    }

    public function stats()
    {
        return response()->json([
            'total_messages' => Message::count(),
            'total_ads' => Message::where('is_ad', true)->count(),
            'total_listings' => Listing::count(),
            'total_contacts' => Contact::count(),
            'today_messages' => Message::whereDate('created_at', today())->count(),
            'today_listings' => Listing::whereDate('created_at', today())->count(),
            'total_deleted' => Message::whereIn('message_category', ['non_ad_deleted', 'extraction_failed'])->count(),
            'total_violations' => Message::where('violation_detected', true)->count(),
        ]);
    }
}
