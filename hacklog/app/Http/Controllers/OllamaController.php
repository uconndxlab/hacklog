<?php

namespace App\Http\Controllers;

use App\Services\OllamaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OllamaController extends Controller
{
    public function index()
    {
        $this->authorizeOllamaAccess();

        return view('ollama.index');
    }

    public function store(Request $request, OllamaService $ollamaService)
    {
        $this->authorizeOllamaAccess();

        $validated = $request->validate([
            'prompt' => 'required|string|max:4000',
            'debug_tools' => 'nullable|boolean',
        ]);

        $debugTools = (bool) ($validated['debug_tools'] ?? false);

        // Restrict detailed tool debug output to admins in this phase.
        if ($debugTools && !auth()->user()->isAdmin()) {
            $debugTools = false;
        }

        // Keep AI data access constrained to explicit read-only tools in the service layer.
        $result = $ollamaService->chatWithTools($request->user(), $validated['prompt'], $debugTools);

        if (!$result['ok']) {
            return back()->withInput()->with('error', $result['error'] ?? 'Unable to get response from Ollama.');
        }

        if ($debugTools) {
            Log::info('Ollama tool-calling debug trace.', [
                'user_id' => $request->user()->id,
                'tool_calls' => $result['debug']['tool_calls'] ?? [],
                'final_response' => $result['response'] ?? null,
            ]);
        }

        return view('ollama.index', [
            'prompt' => $validated['prompt'],
            'response' => $result['response'],
            'toolDebug' => $result['debug']['tool_calls'] ?? [],
            'debugTools' => $debugTools,
        ]);
    }

    protected function authorizeOllamaAccess(): void
    {
        $user = auth()->user();

        if (!$user || (!$user->isAdmin() && !$user->isTeam())) {
            abort(403, 'You are not authorized to use Ollama tools.');
        }
    }
}
