<?php
// modules/user-profiles/functions/user-profiles-country-detection.php

if (!defined('ABSPATH')) {
    exit;
}

// Note: poke_hub_get_client_ip() is already defined in user-profiles-friend-codes-helpers.php

/**
 * Detect country from IP using IPinfo Lite (free, unlimited for country-level)
 * Uses WordPress transients for caching (1 day per IP)
 *
 * @param string $ip_address IP address to check
 * @return array|null Array with 'code' (ISO code) and 'name' (country name), or null on failure
 */
function poke_hub_detect_country_from_ip($ip_address) {
    // Skip empty IPs
    if (empty($ip_address)) {
        return null;
    }
    
    // For local development, allow private IPs (they might still work with IPinfo)
    // In production, this will still filter them out via poke_hub_get_client_ip()
    $is_private = filter_var($ip_address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    
    // If IP is 0.0.0.0 or invalid, try to get a fallback
    if ($ip_address === '0.0.0.0' || !filter_var($ip_address, FILTER_VALIDATE_IP)) {
        // Try to get IP directly from REMOTE_ADDR as fallback
        $fallback_ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;
        if ($fallback_ip && filter_var($fallback_ip, FILTER_VALIDATE_IP)) {
            $ip_address = $fallback_ip;
            $is_private = filter_var($ip_address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
        } else {
            return null;
        }
    }
    
    // For private IPs in development, use a test IP or return default country
    if ($is_private) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[PokeHub] Country detection: Private IP detected: ' . $ip_address);
        }
        
        // In development, try to use a test IP (8.8.8.8 - Google DNS, should return US)
        // Or you can set a default country for development
        $test_ip = '8.8.8.8'; // Google DNS - will return US
        $ip_address = $test_ip;
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[PokeHub] Country detection: Using test IP for development: ' . $ip_address);
        }
    }
    
    // Check cache first (1 day per IP)
    $cache_key = 'ph_country_' . md5($ip_address);
    $cached = get_transient($cache_key);
    if ($cached !== false) {
        return $cached;
    }
    
    // Try IPinfo Lite (free, unlimited for country)
    // Format: https://ipinfo.io/{ip}/country (returns just "FR" - ISO code)
    $country_code = null;
    
    // Get country code (lightweight endpoint)
    $url = 'https://ipinfo.io/' . urlencode($ip_address) . '/country';
    $response = wp_remote_get($url, [
        'timeout' => 5,
        'sslverify' => true,
        'headers' => [
            'Accept' => 'text/plain'
        ]
    ]);
    
    if (is_wp_error($response)) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[PokeHub] IPinfo /country error: ' . $response->get_error_message());
        }
    } elseif (wp_remote_retrieve_response_code($response) === 200) {
        $country_code = trim(wp_remote_retrieve_body($response));
        if (empty($country_code)) {
            $country_code = null;
        } else {
            $country_code = strtoupper($country_code);
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[PokeHub] IPinfo /country returned: ' . $country_code);
            }
        }
    } else {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[PokeHub] IPinfo /country HTTP code: ' . wp_remote_retrieve_response_code($response));
        }
    }
    
    // If we still don't have a code, try the JSON endpoint
    if (!$country_code) {
        $url_json = 'https://ipinfo.io/' . urlencode($ip_address) . '/json';
        $response_json = wp_remote_get($url_json, [
            'timeout' => 5,
            'sslverify' => true
        ]);
        
        if (is_wp_error($response_json)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[PokeHub] IPinfo /json error: ' . $response_json->get_error_message());
            }
        } elseif (wp_remote_retrieve_response_code($response_json) === 200) {
            $body = wp_remote_retrieve_body($response_json);
            $data = json_decode($body, true);
            if ($data) {
                // Check if IPinfo returned "bogon" (private/invalid IP)
                if (isset($data['bogon']) && $data['bogon'] === true) {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('[PokeHub] IPinfo returned bogon=true for IP: ' . $ip_address);
                    }
                    // For bogons, we can't geolocate, return null
                    $country_code = null;
                } elseif (isset($data['country'])) {
                    $country_code = strtoupper(trim($data['country']));
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('[PokeHub] IPinfo /json returned country: ' . $country_code);
                    }
                } else {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('[PokeHub] IPinfo /json response (no country): ' . $body);
                    }
                }
            } else {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[PokeHub] IPinfo /json invalid JSON: ' . $body);
                }
            }
        } else {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[PokeHub] IPinfo /json HTTP code: ' . wp_remote_retrieve_response_code($response_json));
            }
        }
    }
    
    // Map ISO code to country name using Ultimate Member countries list
    $country_name = null;
    if ($country_code && function_exists('poke_hub_get_countries')) {
        $countries = poke_hub_get_countries(); // Returns array: CODE => LABEL
        if (is_array($countries) && isset($countries[$country_code])) {
            $country_name = $countries[$country_code];
        }
    }
    
    // Prepare result
    $result = null;
    if ($country_code) {
        $result = [
            'code' => $country_code,
            'name' => $country_name // May be null if not found in UM list
        ];
        
        // Cache for 1 day (86400 seconds)
        set_transient($cache_key, $result, DAY_IN_SECONDS);
    }
    
    return $result;
}

/**
 * AJAX endpoint to detect country from visitor's IP
 * Accessible to both logged-in and non-logged-in users
 */
function poke_hub_ajax_detect_country() {
    // Get client IP
    $ip = poke_hub_get_client_ip();
    
    // For debugging: log the IP
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[PokeHub] Country detection AJAX: IP = ' . $ip);
    }
    
    // If IP is 0.0.0.0 or invalid, try fallback
    if ($ip === '0.0.0.0' || !filter_var($ip, FILTER_VALIDATE_IP)) {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[PokeHub] Country detection AJAX: Using fallback IP = ' . $ip);
        }
    }
    
    // Detect country
    $country_data = poke_hub_detect_country_from_ip($ip);
    
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[PokeHub] Country detection AJAX: Result = ' . print_r($country_data, true));
    }
    
    if ($country_data) {
        wp_send_json_success($country_data);
    } else {
        wp_send_json_error([
            'message' => __('Unable to detect country from IP address.', 'poke-hub'),
            'ip' => $ip, // Include IP in error for debugging
            'debug' => defined('WP_DEBUG') && WP_DEBUG ? [
                'ip_source' => $ip,
                'is_private' => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false
            ] : null
        ]);
    }
}
add_action('wp_ajax_poke_hub_detect_country', 'poke_hub_ajax_detect_country');
add_action('wp_ajax_nopriv_poke_hub_detect_country', 'poke_hub_ajax_detect_country');

