<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

if (!function_exists('getDropdownValue')) {
    function getDropdownValue($type)
    {
        $hashKey = 'dropdown_master_hk';
        $cacheField = "dropdown_{$type}";
 
        if ($cached = Cache::getHashValue($hashKey, $cacheField)) {
            return json_decode($cached, true);
        }
 
        if ($type === 'colour') {
            $colors = DB::table('colors')->pluck('colour')->toArray();
            $result = isset($colors) ? array_combine($colors, $colors) : [];
        } else {
            $dropdown_master = DB::table('dropdown_master')->where('type', $type)->value('options');
            $result = json_decode($dropdown_master, true) ?: [];
        }
        // Store in Redis cache for 24 hours
        Cache::setHashValue($hashKey, $cacheField, json_encode($result), 3600 * 24);
        return $result;
    }
}

function app_string($type, $key)
{
    if (!$type || !$key) {
        return '';
    }
    $dropdown = getDropdownValue($type);

    return $dropdown[$key] ?? '';
}

/**
 * Validate and normalize an Indian mobile number.
 *
 * @param string $mobile The input mobile number
 * @return string|null Returns 10-digit valid mobile number or null if invalid
 */
function getValidMobile(?string $mobile): ?string
{
    if (!$mobile) {
        return null;
    }
    // Remove all non-numeric characters
    $mobile = preg_replace('/\D/', '', $mobile);

    // Keep only last 10 digits (handles +91, 91, 0)
    if (strlen($mobile) > 10) {
        $mobile = substr($mobile, -10);
    }

    // Must be exactly 10 digits
    if (strlen($mobile) !== 10) {
        return null;
    }

    // Optional: Indian mobile numbers start from 6-9
    /*if (!preg_match('/^[6-9][0-9]{9}$/', $mobile)) {
        return null;
    }*/

    return $mobile;
}

function getAppConfig($config_key, $is_cached = true)
{
    $hash_key = AppConfig::getHashKey();
    $field = $config_key;
    $cached = $is_cached ? Cache::getHashValue($hash_key, $field) : null;
    if ($cached) {
        return $cached;
    }
    $config_value = AppConfig::where('config_key', $config_key)->value('config_value');
    if ($is_cached && $config_value !== null) {
        Cache::setHashValue($hash_key, $field, $config_value, 3600 * 24 * 7); // cache for 7 days
    }

    return $config_value;
}
