<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import SettingsNavTabs from '@/Components/Admin/SettingsNavTabs.vue';
import { useToast } from 'vue-toastification';

const toast = useToast();

const props = defineProps({
    ai_settings: Object,
});

const form = useForm({
    ai_enabled:         props.ai_settings?.ai_enabled ?? false,
    ai_active_provider: props.ai_settings?.ai_active_provider || 'gemini',
    ai_gemini_key:      props.ai_settings?.ai_gemini_key || '',
    ai_gemini_model:    props.ai_settings?.ai_gemini_model || 'gemini-2.5-flash',
    ai_openai_key:      props.ai_settings?.ai_openai_key || '',
    ai_openai_model:    props.ai_settings?.ai_openai_model || 'gpt-4o-mini',
    ai_deepseek_key:    props.ai_settings?.ai_deepseek_key || '',
    ai_deepseek_model:  props.ai_settings?.ai_deepseek_model || 'deepseek-chat',
    ai_claude_key:      props.ai_settings?.ai_claude_key || '',
    ai_claude_model:    props.ai_settings?.ai_claude_model || 'claude-3-5-haiku-20241022',
});

const activeAiTab = ref(form.ai_active_provider || 'gemini');

const showApiKey = ref({
    gemini: false,
    openai: false,
    deepseek: false,
    claude: false,
});

const aiTesting = ref(false);
const aiTestResult = ref(null);

const testAiConnection = async (provider) => {
    aiTesting.value = true;
    aiTestResult.value = null;
    try {
        const keyMap = {
            gemini: form.ai_gemini_key,
            openai: form.ai_openai_key,
            deepseek: form.ai_deepseek_key,
            claude: form.ai_claude_key,
        };
        const modelMap = {
            gemini: form.ai_gemini_model,
            openai: form.ai_openai_model,
            deepseek: form.ai_deepseek_model,
            claude: form.ai_claude_model,
        };
        const res = await window.axios.post(route('admin.ai.test-connection'), {
            provider,
            api_key: keyMap[provider] || undefined,
            model: modelMap[provider] || undefined,
        });
        aiTestResult.value = res.data;
        if (res.data.success) {
            toast.success(res.data.message);
        } else {
            toast.error(res.data.message);
        }
    } catch (e) {
        const msg = e.response?.data?.message || 'Connection test failed.';
        aiTestResult.value = { success: false, message: msg };
        toast.error(msg);
    } finally {
        aiTesting.value = false;
    }
};

const save = () => {
    form.put(route('admin.ai-settings.update'), {
        preserveScroll: true,
        onError: () => toast.error('Failed to save settings, please check the form.'),
    });
};
</script>

<template>
    <Head title="AI Assistant Settings" />

    <AdminLayout>
        <div class="max-w-6xl mx-auto space-y-6">

            <!-- Top Navigation Tabs (Settings Sub-Menu) -->
            <SettingsNavTabs />

            <!-- Header & Save Button -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="text-2xl">🤖</span>
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">AI Assistant & Provider Settings</h1>
                    </div>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Configure Large Language Models (LLMs) powering 1-click marketing copy, plain text short summaries, and rich HTML product descriptions.
                    </p>
                </div>
                <button
                    type="button"
                    @click="save"
                    :disabled="form.processing"
                    class="bg-primary hover:bg-primary-dark text-white font-bold px-6 py-2.5 rounded-lg text-sm transition flex items-center gap-2 shadow-xs disabled:opacity-60 shrink-0"
                >
                    <svg v-if="form.processing" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                    <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    {{ form.processing ? 'Saving Changes...' : 'Save AI Settings' }}
                </button>
            </div>

            <!-- Master Toggle & Status Hero Card -->
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4 shadow-sm">
                <div class="flex items-start gap-4">
                    <div class="w-12 h-12 rounded-xl flex items-center justify-center text-2xl shrink-0" :class="form.ai_enabled ? 'bg-primary-soft text-primary' : 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400'">
                        ✨
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-base font-bold text-gray-900 dark:text-white">AI Assistant Global Status</h2>
                            <span
                                class="px-2 py-0.5 text-xs font-semibold rounded-full"
                                :class="form.ai_enabled ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300'"
                            >
                                {{ form.ai_enabled ? 'Active & Ready' : 'Disabled' }}
                            </span>
                        </div>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            When enabled, admins will see the "✨ AI Generate" buttons directly inside the Product Editor for Short and Full Descriptions.
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-3 shrink-0">
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" v-model="form.ai_enabled" class="sr-only peer" />
                        <div class="w-14 h-7 bg-gray-200 peer-focus:outline-none rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:start-[3px] after:bg-white after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-primary"></div>
                    </label>
                </div>
            </div>

            <!-- Active Provider Selection -->
            <section class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/60">
                    <h2 class="text-base font-bold text-gray-900 dark:text-white">1. Select Active Provider</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Choose which AI provider should process generation requests.</p>
                </div>

                <div class="p-6">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <!-- Google Gemini -->
                        <div
                            @click="form.ai_active_provider = 'gemini'; activeAiTab = 'gemini'"
                            :class="[
                                'p-4 rounded-xl border cursor-pointer transition flex flex-col justify-between relative',
                                form.ai_active_provider === 'gemini'
                                    ? 'border-purple-600 bg-purple-50/50 dark:bg-purple-950/30 ring-2 ring-purple-600'
                                    : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600 bg-white dark:bg-gray-900/30'
                            ]"
                        >
                            <div class="flex items-center justify-between">
                                <span class="text-2xl">✨</span>
                                <span v-if="form.ai_active_provider === 'gemini'" class="text-[10px] font-bold uppercase tracking-wider text-purple-700 dark:text-purple-300 bg-purple-100 dark:bg-purple-900/60 px-2 py-0.5 rounded-full">Active</span>
                            </div>
                            <div class="mt-3">
                                <div class="font-bold text-sm text-gray-900 dark:text-white">Google Gemini</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Gemini 2.5 Flash / 1.5 Pro</div>
                                <div class="mt-2 text-[11px] text-purple-700 dark:text-purple-400 font-medium">Free tier available on AI Studio</div>
                            </div>
                        </div>

                        <!-- OpenAI ChatGPT -->
                        <div
                            @click="form.ai_active_provider = 'openai'; activeAiTab = 'openai'"
                            :class="[
                                'p-4 rounded-xl border cursor-pointer transition flex flex-col justify-between relative',
                                form.ai_active_provider === 'openai'
                                    ? 'border-emerald-600 bg-emerald-50/50 dark:bg-emerald-950/30 ring-2 ring-emerald-600'
                                    : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600 bg-white dark:bg-gray-900/30'
                            ]"
                        >
                            <div class="flex items-center justify-between">
                                <span class="text-2xl">⚡</span>
                                <span v-if="form.ai_active_provider === 'openai'" class="text-[10px] font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-300 bg-emerald-100 dark:bg-emerald-900/60 px-2 py-0.5 rounded-full">Active</span>
                            </div>
                            <div class="mt-3">
                                <div class="font-bold text-sm text-gray-900 dark:text-white">OpenAI ChatGPT</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">GPT-4o / GPT-4o-mini</div>
                                <div class="mt-2 text-[11px] text-emerald-700 dark:text-emerald-400 font-medium">Industry standard accuracy</div>
                            </div>
                        </div>

                        <!-- DeepSeek -->
                        <div
                            @click="form.ai_active_provider = 'deepseek'; activeAiTab = 'deepseek'"
                            :class="[
                                'p-4 rounded-xl border cursor-pointer transition flex flex-col justify-between relative',
                                form.ai_active_provider === 'deepseek'
                                    ? 'border-blue-600 bg-blue-50/50 dark:bg-blue-950/30 ring-2 ring-blue-600'
                                    : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600 bg-white dark:bg-gray-900/30'
                            ]"
                        >
                            <div class="flex items-center justify-between">
                                <span class="text-2xl">🐋</span>
                                <span v-if="form.ai_active_provider === 'deepseek'" class="text-[10px] font-bold uppercase tracking-wider text-blue-700 dark:text-blue-300 bg-blue-100 dark:bg-blue-900/60 px-2 py-0.5 rounded-full">Active</span>
                            </div>
                            <div class="mt-3">
                                <div class="font-bold text-sm text-gray-900 dark:text-white">DeepSeek</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">DeepSeek-V3 / R1</div>
                                <div class="mt-2 text-[11px] text-blue-700 dark:text-blue-400 font-medium">Ultra-low pricing & high speed</div>
                            </div>
                        </div>

                        <!-- Anthropic Claude -->
                        <div
                            @click="form.ai_active_provider = 'claude'; activeAiTab = 'claude'"
                            :class="[
                                'p-4 rounded-xl border cursor-pointer transition flex flex-col justify-between relative',
                                form.ai_active_provider === 'claude'
                                    ? 'border-amber-600 bg-amber-50/50 dark:bg-amber-950/30 ring-2 ring-amber-600'
                                    : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600 bg-white dark:bg-gray-900/30'
                            ]"
                        >
                            <div class="flex items-center justify-between">
                                <span class="text-2xl">🎭</span>
                                <span v-if="form.ai_active_provider === 'claude'" class="text-[10px] font-bold uppercase tracking-wider text-amber-700 dark:text-amber-300 bg-amber-100 dark:bg-amber-900/60 px-2 py-0.5 rounded-full">Active</span>
                            </div>
                            <div class="mt-3">
                                <div class="font-bold text-sm text-gray-900 dark:text-white">Anthropic Claude</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Claude 3.5 Haiku / Sonnet</div>
                                <div class="mt-2 text-[11px] text-amber-700 dark:text-amber-400 font-medium">Supreme copywriting fluency</div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Provider Credentials & Live Connection Tester -->
            <section class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/60 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <h2 class="text-base font-bold text-gray-900 dark:text-white">2. Provider Credentials & Model Configuration</h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Manage API keys and test connection response latency.</p>
                    </div>

                    <button
                        type="button"
                        @click="testAiConnection(activeAiTab)"
                        :disabled="aiTesting"
                        class="inline-flex items-center justify-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg bg-gray-900 text-white dark:bg-white dark:text-gray-900 hover:opacity-90 transition disabled:opacity-50 shrink-0"
                    >
                        <svg v-if="aiTesting" class="animate-spin h-3.5 w-3.5" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                        <span v-else>⚡</span>
                        <span>{{ aiTesting ? 'Testing...' : 'Test ' + activeAiTab.toUpperCase() + ' Connection' }}</span>
                    </button>
                </div>

                <!-- Provider Tabs Header -->
                <div class="px-6 pt-4 border-b border-gray-200 dark:border-gray-700 flex gap-2">
                    <button
                        type="button"
                        @click="activeAiTab = 'gemini'"
                        :class="['px-4 py-2 text-xs font-semibold rounded-t-lg transition border-b-2', activeAiTab === 'gemini' ? 'border-purple-600 text-purple-700 dark:text-purple-300 bg-purple-50/50 dark:bg-purple-950/20' : 'border-transparent text-gray-500 hover:text-gray-700']"
                    >Google Gemini</button>
                    <button
                        type="button"
                        @click="activeAiTab = 'openai'"
                        :class="['px-4 py-2 text-xs font-semibold rounded-t-lg transition border-b-2', activeAiTab === 'openai' ? 'border-emerald-600 text-emerald-700 dark:text-emerald-300 bg-emerald-50/50 dark:bg-emerald-950/20' : 'border-transparent text-gray-500 hover:text-gray-700']"
                    >OpenAI ChatGPT</button>
                    <button
                        type="button"
                        @click="activeAiTab = 'deepseek'"
                        :class="['px-4 py-2 text-xs font-semibold rounded-t-lg transition border-b-2', activeAiTab === 'deepseek' ? 'border-blue-600 text-blue-700 dark:text-blue-300 bg-blue-50/50 dark:bg-blue-950/20' : 'border-transparent text-gray-500 hover:text-gray-700']"
                    >DeepSeek</button>
                    <button
                        type="button"
                        @click="activeAiTab = 'claude'"
                        :class="['px-4 py-2 text-xs font-semibold rounded-t-lg transition border-b-2', activeAiTab === 'claude' ? 'border-amber-600 text-amber-700 dark:text-amber-300 bg-amber-50/50 dark:bg-amber-950/20' : 'border-transparent text-gray-500 hover:text-gray-700']"
                    >Anthropic Claude</button>
                </div>

                <div class="p-6">
                    <!-- Google Gemini Tab -->
                    <div v-show="activeAiTab === 'gemini'" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <div class="flex items-center justify-between mb-1">
                                    <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300">Gemini API Key</label>
                                    <button type="button" @click="showApiKey.gemini = !showApiKey.gemini" class="text-[11px] text-gray-500 hover:text-gray-700">
                                        {{ showApiKey.gemini ? 'Hide' : 'Show' }}
                                    </button>
                                </div>
                                <input
                                    :type="showApiKey.gemini ? 'text' : 'password'"
                                    v-model="form.ai_gemini_key"
                                    placeholder="AIzaSy..."
                                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-2 text-sm font-mono"
                                />
                                <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-1">
                                    Get your free API key at Google AI Studio (<a href="https://aistudio.google.com" target="_blank" class="text-primary underline">aistudio.google.com</a>).
                                </p>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Model</label>
                                <select v-model="form.ai_gemini_model" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-2 text-sm">
                                    <option value="gemini-2.5-flash">gemini-2.5-flash (Recommended — Ultrafast & High Accuracy)</option>
                                    <option value="gemini-1.5-flash">gemini-1.5-flash (Fast & Lightweight)</option>
                                    <option value="gemini-1.5-pro">gemini-1.5-pro (Complex Reasoning & Deep Creative)</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- OpenAI Tab -->
                    <div v-show="activeAiTab === 'openai'" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <div class="flex items-center justify-between mb-1">
                                    <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300">OpenAI API Key</label>
                                    <button type="button" @click="showApiKey.openai = !showApiKey.openai" class="text-[11px] text-gray-500 hover:text-gray-700">
                                        {{ showApiKey.openai ? 'Hide' : 'Show' }}
                                    </button>
                                </div>
                                <input
                                    :type="showApiKey.openai ? 'text' : 'password'"
                                    v-model="form.ai_openai_key"
                                    placeholder="sk-..."
                                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-2 text-sm font-mono"
                                />
                                <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-1">
                                    From OpenAI API Dashboard (<a href="https://platform.openai.com/api-keys" target="_blank" class="text-primary underline">platform.openai.com</a>).
                                </p>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Model</label>
                                <select v-model="form.ai_openai_model" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-2 text-sm">
                                    <option value="gpt-4o-mini">gpt-4o-mini (Recommended — Budget-Friendly & Fast)</option>
                                    <option value="gpt-4o">gpt-4o (Flagship Omni Model)</option>
                                    <option value="gpt-3.5-turbo">gpt-3.5-turbo (Legacy Standard)</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- DeepSeek Tab -->
                    <div v-show="activeAiTab === 'deepseek'" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <div class="flex items-center justify-between mb-1">
                                    <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300">DeepSeek API Key</label>
                                    <button type="button" @click="showApiKey.deepseek = !showApiKey.deepseek" class="text-[11px] text-gray-500 hover:text-gray-700">
                                        {{ showApiKey.deepseek ? 'Hide' : 'Show' }}
                                    </button>
                                </div>
                                <input
                                    :type="showApiKey.deepseek ? 'text' : 'password'"
                                    v-model="form.ai_deepseek_key"
                                    placeholder="sk-..."
                                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-2 text-sm font-mono"
                                />
                                <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-1">
                                    From DeepSeek Open Platform (<a href="https://platform.deepseek.com" target="_blank" class="text-primary underline">platform.deepseek.com</a>).
                                </p>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Model</label>
                                <select v-model="form.ai_deepseek_model" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-2 text-sm">
                                    <option value="deepseek-chat">deepseek-chat (DeepSeek-V3 — High Speed & Value)</option>
                                    <option value="deepseek-reasoner">deepseek-reasoner (DeepSeek-R1 — Advanced Reasoning)</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Anthropic Claude Tab -->
                    <div v-show="activeAiTab === 'claude'" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <div class="flex items-center justify-between mb-1">
                                    <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300">Anthropic Claude API Key</label>
                                    <button type="button" @click="showApiKey.claude = !showApiKey.claude" class="text-[11px] text-gray-500 hover:text-gray-700">
                                        {{ showApiKey.claude ? 'Hide' : 'Show' }}
                                    </button>
                                </div>
                                <input
                                    :type="showApiKey.claude ? 'text' : 'password'"
                                    v-model="form.ai_claude_key"
                                    placeholder="sk-ant-..."
                                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-2 text-sm font-mono"
                                />
                                <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-1">
                                    From Anthropic Console (<a href="https://console.anthropic.com" target="_blank" class="text-primary underline">console.anthropic.com</a>).
                                </p>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Model</label>
                                <select v-model="form.ai_claude_model" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-2 text-sm">
                                    <option value="claude-3-5-haiku-20241022">claude-3-5-haiku-20241022 (Fast, Eloquent & Cost-Effective)</option>
                                    <option value="claude-3-5-sonnet-20241022">claude-3-5-sonnet-20241022 (Supreme Copywriting Quality)</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Connection Test Result Banner -->
                    <div v-if="aiTestResult" class="mt-5 p-3 rounded-lg text-xs flex items-center justify-between" :class="aiTestResult.success ? 'bg-green-50 text-green-800 border border-green-200 dark:bg-green-950/40 dark:text-green-300 dark:border-green-800' : 'bg-red-50 text-red-800 border border-red-200 dark:bg-red-950/40 dark:text-red-300 dark:border-red-800'">
                        <div class="flex items-center gap-2">
                            <span>{{ aiTestResult.success ? '✅' : '❌' }}</span>
                            <span class="font-medium">{{ aiTestResult.message }}</span>
                        </div>
                        <span v-if="aiTestResult.latency_ms" class="font-mono text-[11px] font-semibold bg-white/70 dark:bg-black/30 px-2 py-0.5 rounded">
                            {{ aiTestResult.latency_ms }}ms
                        </span>
                    </div>
                </div>
            </section>

            <!-- Bottom Save Button -->
            <div class="flex justify-end pt-2">
                <button
                    type="button"
                    @click="save"
                    :disabled="form.processing"
                    class="bg-primary hover:bg-primary-dark text-white font-bold px-8 py-3 rounded-lg transition disabled:opacity-60 shadow-sm"
                >
                    {{ form.processing ? 'Saving...' : 'Save AI Settings' }}
                </button>
            </div>
        </div>
    </AdminLayout>
</template>

