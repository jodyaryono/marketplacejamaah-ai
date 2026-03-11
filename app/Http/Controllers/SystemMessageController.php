<?php

namespace App\Http\Controllers;

use App\Models\SystemMessage;
use Illuminate\Http\Request;

class SystemMessageController extends Controller
{
    public function index()
    {
        $messages = SystemMessage::orderBy('sort_order')->orderBy('key')->get();
        return view('system-messages.index', compact('messages'));
    }

    public function update(Request $request, SystemMessage $systemMessage)
    {
        $validated = $request->validate([
            'body' => 'required|string',
            'is_active' => 'sometimes|boolean',
        ]);

        $systemMessage->update($validated);

        return redirect()
            ->route('system-messages.index')
            ->with('success', 'Pesan "' . e($systemMessage->label) . '" berhasil disimpan.');
    }
}
