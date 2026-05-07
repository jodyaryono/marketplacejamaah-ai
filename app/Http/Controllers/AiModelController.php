<?php

namespace App\Http\Controllers;

use App\Models\AiModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;

class AiModelController extends Controller
{
    public function index()
    {
        $models = AiModel::orderBy('role')
            ->orderBy('priority')
            ->orderByDesc('is_active')
            ->get()
            ->groupBy('role');

        return view('settings.ai-models.index', [
            'modelsByRole' => $models,
            'roles'        => AiModel::ROLES,
            'providers'    => AiModel::PROVIDERS,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateInput($request);
        AiModel::create($data);
        return back()->with('saved', 'Model AI berhasil ditambahkan.');
    }

    public function update(Request $request, AiModel $aiModel)
    {
        $data = $this->validateInput($request, isUpdate: true);
        // Empty api_key on update means "keep existing"
        if (empty($data['api_key'])) {
            unset($data['api_key']);
        }
        $aiModel->fill($data);
        $aiModel->save();
        return back()->with('saved', "Model '{$aiModel->name}' berhasil diperbarui.");
    }

    /**
     * Return the decrypted api_key as JSON. Auth-gated by the parent route group.
     * Used by the index page's per-row 👁 reveal button so the admin can copy
     * the full key and match it against the provider's billing dashboard
     * (e.g. Google AI Studio, Anthropic Console) to identify which key is
     * driving which cost line item.
     */
    public function reveal(AiModel $aiModel)
    {
        $key = $aiModel->api_key;
        return response()->json([
            'id'     => $aiModel->id,
            'name'   => $aiModel->name,
            'provider' => $aiModel->provider,
            'model'  => $aiModel->model,
            'api_key' => $key,
            'has_key' => !empty($key),
            'last4'  => $key ? substr($key, -4) : null,
        ]);
    }

    public function destroy(AiModel $aiModel)
    {
        $name = $aiModel->name;
        $aiModel->delete();
        return back()->with('saved', "Model '{$name}' dihapus.");
    }

    public function toggleActive(AiModel $aiModel)
    {
        $aiModel->is_active = !$aiModel->is_active;
        $aiModel->save();
        return back()->with('saved', "Model '{$aiModel->name}' " . ($aiModel->is_active ? 'diaktifkan' : 'dinonaktifkan') . '.');
    }

    /**
     * Test the model — sends a tiny prompt and returns latency + response.
     * Supports gemini, groq/openai-compatible, anthropic, openrouter.
     */
    public function test(AiModel $aiModel)
    {
        $start = microtime(true);
        try {
            $apiKey = $aiModel->api_key;
            if (empty($apiKey)) {
                return back()->with('test_result', [
                    'id' => $aiModel->id,
                    'ok' => false,
                    'error' => 'API key kosong',
                ]);
            }

            $result = match ($aiModel->provider) {
                'gemini'     => $this->testGemini($aiModel, $apiKey),
                'groq', 'openai', 'openrouter' => $this->testOpenAiCompatible($aiModel, $apiKey),
                'anthropic'  => $this->testAnthropic($aiModel, $apiKey),
                default      => ['ok' => false, 'error' => "Provider '{$aiModel->provider}' belum didukung untuk test"],
            };
            $ms = (int) ((microtime(true) - $start) * 1000);

            return back()->with('test_result', array_merge($result, [
                'id' => $aiModel->id,
                'latency' => $ms,
            ]));
        } catch (\Throwable $e) {
            return back()->with('test_result', [
                'id' => $aiModel->id,
                'ok' => false,
                'error' => $e->getMessage(),
                'latency' => (int) ((microtime(true) - $start) * 1000),
            ]);
        }
    }

    private function testGemini(AiModel $m, string $apiKey): array
    {
        $endpoint = rtrim($m->endpoint ?: 'https://generativelanguage.googleapis.com/v1beta/models', '/');
        $url = "{$endpoint}/{$m->model}:generateContent";
        $resp = Http::timeout(15)->post($url . '?key=' . urlencode($apiKey), [
            'contents' => [['parts' => [['text' => 'Reply with the single word: PONG']]]],
            'generationConfig' => ['temperature' => 0, 'maxOutputTokens' => 16],
        ]);
        if ($resp->failed()) {
            return ['ok' => false, 'error' => 'HTTP ' . $resp->status() . ': ' . mb_substr(strip_tags($resp->body()), 0, 160)];
        }
        $text = trim($resp->json('candidates.0.content.parts.0.text') ?? '');
        return ['ok' => str_contains(strtolower($text), 'pong'), 'response' => $text];
    }

    private function testOpenAiCompatible(AiModel $m, string $apiKey): array
    {
        $endpoint = $m->endpoint ?: 'https://api.groq.com/openai/v1/chat/completions';
        $resp = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type'  => 'application/json',
        ])->timeout(15)->post($endpoint, [
            'model' => $m->model,
            'messages' => [['role' => 'user', 'content' => 'Reply with the single word: PONG']],
            'temperature' => 0,
            'max_tokens' => 16,
        ]);
        if ($resp->failed()) {
            return ['ok' => false, 'error' => 'HTTP ' . $resp->status() . ': ' . mb_substr(strip_tags($resp->body()), 0, 160)];
        }
        $text = trim($resp->json('choices.0.message.content') ?? '');
        return ['ok' => str_contains(strtolower($text), 'pong'), 'response' => $text];
    }

    private function testAnthropic(AiModel $m, string $apiKey): array
    {
        $endpoint = $m->endpoint ?: 'https://api.anthropic.com/v1/messages';
        $resp = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
            'Content-Type' => 'application/json',
        ])->timeout(15)->post($endpoint, [
            'model' => $m->model,
            'max_tokens' => 16,
            'messages' => [['role' => 'user', 'content' => 'Reply with the single word: PONG']],
        ]);
        if ($resp->failed()) {
            return ['ok' => false, 'error' => 'HTTP ' . $resp->status() . ': ' . mb_substr(strip_tags($resp->body()), 0, 160)];
        }
        $text = trim($resp->json('content.0.text') ?? '');
        return ['ok' => str_contains(strtolower($text), 'pong'), 'response' => $text];
    }

    private function validateInput(Request $request, bool $isUpdate = false): array
    {
        return $request->validate([
            'name' => 'required|string|max:120',
            'provider' => ['required', Rule::in(array_keys(AiModel::PROVIDERS))],
            'model' => 'required|string|max:200',
            'api_key' => $isUpdate ? 'nullable|string|max:500' : 'nullable|string|max:500',
            'endpoint' => 'nullable|url|max:500',
            'role' => ['required', Rule::in(array_keys(AiModel::ROLES))],
            'priority' => 'required|integer|min:1|max:999',
            'is_active' => 'sometimes|boolean',
            'notes' => 'nullable|string|max:1000',
        ]) + ['is_active' => $request->boolean('is_active', true)];
    }
}
