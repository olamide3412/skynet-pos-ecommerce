<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StoreSetting;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AiSettingsController extends Controller
{
    /**
     * Display the dedicated AI Assistant configuration page.
     */
    public function index(): Response
    {
        return Inertia::render('Admin/AiSettings/Index', [
            'ai_settings' => [
                'ai_enabled'         => StoreSetting::get('ai_enabled', '0') === '1',
                'ai_active_provider' => StoreSetting::get('ai_active_provider', 'gemini'),
                'ai_gemini_key'      => StoreSetting::get('ai_gemini_key', ''),
                'ai_gemini_model'    => StoreSetting::get('ai_gemini_model', 'gemini-2.5-flash'),
                'ai_openai_key'      => StoreSetting::get('ai_openai_key', ''),
                'ai_openai_model'    => StoreSetting::get('ai_openai_model', 'gpt-4o-mini'),
                'ai_deepseek_key'    => StoreSetting::get('ai_deepseek_key', ''),
                'ai_deepseek_model'  => StoreSetting::get('ai_deepseek_model', 'deepseek-chat'),
                'ai_claude_key'      => StoreSetting::get('ai_claude_key', ''),
                'ai_claude_model'    => StoreSetting::get('ai_claude_model', 'claude-3-5-haiku-20241022'),
            ],
        ]);
    }

    /**
     * Update AI provider credentials, models, and master toggle.
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'ai_enabled'         => 'boolean',
            'ai_active_provider' => 'nullable|string|in:gemini,openai,deepseek,claude',
            'ai_gemini_key'      => 'nullable|string|max:500',
            'ai_gemini_model'    => 'nullable|string|max:100',
            'ai_openai_key'      => 'nullable|string|max:500',
            'ai_openai_model'    => 'nullable|string|max:100',
            'ai_deepseek_key'    => 'nullable|string|max:500',
            'ai_deepseek_model'  => 'nullable|string|max:100',
            'ai_claude_key'      => 'nullable|string|max:500',
            'ai_claude_model'    => 'nullable|string|max:100',
        ]);

        foreach ($validated as $key => $value) {
            StoreSetting::set($key, is_bool($value) ? ($value ? '1' : '0') : ($value ?? ''));
        }

        ActivityLogger::setting("Updated AI assistant provider configurations", [
            'active_provider' => $validated['ai_active_provider'] ?? 'gemini',
            'enabled'         => $validated['ai_enabled'] ?? false,
        ]);

        return back()->with('success', 'AI assistant settings saved successfully.');
    }
}

