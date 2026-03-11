<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Listing;
use Illuminate\Http\Request;

class PublicController extends Controller
{
    /**
     * Public listing detail — accessible without admin login
     */
    public function listingDetail(int $id)
    {
        $listing = Listing::with(['category', 'contact', 'message'])
            ->where('status', 'active')
            ->findOrFail($id);

        $related = Listing::with(['contact', 'category'])
            ->where('status', 'active')
            ->where('id', '!=', $listing->id)
            ->where(function ($q) use ($listing) {
                if ($listing->category_id) {
                    $q->where('category_id', $listing->category_id);
                }
                if ($listing->contact_id) {
                    $q->orWhere('contact_id', $listing->contact_id);
                }
            })
            ->latest('source_date')
            ->limit(6)
            ->get();

        return view('public.listing-detail', compact('listing', 'related'));
    }

    /**
     * Public seller profile
     */
    public function sellerProfile(string $phone)
    {
        $phone = preg_replace('/\D/', '', $phone);
        $contact = Contact::where('phone_number', $phone)
            ->where('is_registered', true)
            ->firstOrFail();

        $listings = Listing::with('category')
            ->where(function ($q) use ($contact) {
                $q
                    ->where('contact_id', $contact->id)
                    ->orWhere('contact_number', $contact->phone_number);
            })
            ->where('status', 'active')
            ->latest('source_date')
            ->paginate(12);

        return view('public.seller-profile', compact('contact', 'listings'));
    }
}
