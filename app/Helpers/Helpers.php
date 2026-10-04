<?php

use Illuminate\Support\Facades\Log;

if (!function_exists('log_new')) {
    function log_new ($log_msg) {
        \Illuminate\Support\Facades\Log::info($log_msg, [
            'user_id' => \Illuminate\Support\Facades\Auth::check() ? \Illuminate\Support\Facades\Auth::id() : null
        ]);
    }
}
