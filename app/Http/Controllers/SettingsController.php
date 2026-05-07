<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\WhacenterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SettingsController extends Controller
{
    public function index()
    {
        $rawKey = config('services.gemini.api_key', '');
        $maskedKey = strlen($rawKey) > 8
            ? substr($rawKey, 0, 6) . str_repeat('*', strlen($rawKey) - 8) . substr($rawKey, -2)
            : str_repeat('*', strlen($rawKey));

        $settings = [
            'whacenter_device_id' => config('services.whacenter.device_id'),
            'whacenter_url' => config('services.whacenter.api_url'),
            'gemini_key' => $maskedKey,
            'gemini_model' => config('services.gemini.model'),
            'app_url' => config('app.url'),
            'webhook_url' => url('/api/webhook/whacenter'),
            'queue_driver' => config('queue.default'),
        ];

        // DB settings grouped for display
        $dbSettings = Setting::orderBy('group')
            ->orderBy('sort_order')
            ->get()
            ->groupBy('group');

        return view('settings.index', compact('settings', 'dbSettings'));
    }

    public function update(Request $request)
    {
        $values = $request->input('setting', []);
        foreach ($values as $key => $value) {
            $row = Setting::where('key', $key)->first();
            if (!$row) continue;
            // Secrets: empty submission means "keep existing" — don't overwrite
            // the encrypted value with empty string. Allows the masked input to
            // stay blank in the form without nuking the saved key.
            if ($row->is_secret && (is_null($value) || trim((string) $value) === '')) {
                continue;
            }
            Setting::set($key, $value);
        }
        return back()->with('saved', 'Pengaturan berhasil disimpan.');
    }

    public function testWhacenter(Request $request, WhacenterService $whacenter)
    {
        $request->validate([
            'type' => 'required|in:private,group',
            'message' => 'required|string',
            'number' => 'required_if:type,private|nullable|string',
            'group' => 'required_if:type,group|nullable|string',
        ]);

        $result = $request->type === 'group'
            ? $whacenter->sendGroupMessage($request->group, $request->message)
            : $whacenter->sendMessage($request->number, $request->message);

        return back()->with('whacenter_result', [
            'success' => $result['success'],
            'message' => $result['error'] ?? 'OK',
        ]);
    }
}
