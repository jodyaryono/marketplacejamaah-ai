<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Listing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

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
     * Proxy first listing image through app domain for OG/social preview crawlers
     */
    public function ogImage(int $id)
    {
        $listing  = Listing::where('status', 'active')->findOrFail($id);
        $mediaUrl = $listing->media_urls[0] ?? null;

        if (!$mediaUrl) {
            abort(404);
        }

        try {
            $response = Http::timeout(8)->get($mediaUrl);
            if (!$response->successful()) {
                abort(404);
            }

            $contentType = $response->header('Content-Type') ?? 'image/jpeg';
            // Strip charset if appended (e.g. "image/jpeg; charset=utf-8")
            $contentType = strtok($contentType, ';');

            return response($response->body())
                ->header('Content-Type', trim($contentType))
                ->header('Cache-Control', 'public, max-age=86400');
        } catch (\Exception $e) {
            abort(404);
        }
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
