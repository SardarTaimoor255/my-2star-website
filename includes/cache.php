<?php
if (!defined('APP_INIT')) { http_response_code(403); exit('Direct access denied.'); }

/**
 * Simple session-based cache
 */
function cache_remember(string $key, callable $callback, int $ttl = 300)
{
    $cacheKey = '_cache_' . $key;
    $timeKey  = '_cache_time_' . $key;

    if (isset($_SESSION[$cacheKey], $_SESSION[$timeKey]) 
        && (time() - $_SESSION[$timeKey]) < $ttl) {
        return $_SESSION[$cacheKey];
    }

    $data = $callback();
    $_SESSION[$cacheKey] = $data;
    $_SESSION[$timeKey]  = time();

    return $data;
}

function cache_forget(?string $key = null): void
{
    if ($key === null) {
        foreach (array_keys($_SESSION) as $k) {
            if (strpos($k, '_cache_') === 0) {
                unset($_SESSION[$k]);
            }
        }
        return;
    }
    unset($_SESSION['_cache_' . $key], $_SESSION['_cache_time_' . $key]);
}