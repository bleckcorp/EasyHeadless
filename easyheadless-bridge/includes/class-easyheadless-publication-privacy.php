<?php

if (!defined('ABSPATH')) {
    exit;
}

/** Prevent shared caches from reusing authorization-dependent bridge responses. */
final class EasyHeadless_Publication_Privacy
{
    public static function before_dispatch($result, $server, $request)
    {
        if (!self::is_bridge_request($request)) {
            return $result;
        }
        if (!defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true);
        }
        nocache_headers();
        // REST requests may not run the normal WordPress page-cache hooks.
        do_action('litespeed_control_set_nocache', 'EasyHeadless reader privacy');
        return $result;
    }

    public static function after_dispatch($response, $server, $request)
    {
        if (!self::is_bridge_request($request)) {
            return $response;
        }
        $headers = $response->get_headers();
        $vary = array('Authorization', 'Cookie');
        foreach ($headers as $name => $value) {
            if (strtolower($name) === 'vary') {
                $vary = array_merge($vary, explode(',', $value));
            }
            if (in_array(strtolower($name), array('etag', 'last-modified', 'vary', 'cache-control', 'pragma', 'expires', 'surrogate-control', 'cdn-cache-control'), true)) {
                unset($headers[$name]);
            }
        }
        $response->set_headers($headers);
        $response->header('Cache-Control', 'private, no-store, no-cache, must-revalidate, max-age=0');
        $response->header('Pragma', 'no-cache');
        $response->header('Expires', 'Wed, 11 Jan 1984 05:00:00 GMT');
        $response->header('Surrogate-Control', 'no-store');
        $response->header('CDN-Cache-Control', 'no-store');
        $response->header('Vary', implode(', ', array_unique(array_map('trim', $vary))));
        return $response;
    }

    private static function is_bridge_request($request)
    {
        return (bool) preg_match('~^/easyheadless/v\d+(?:/|$)~', $request->get_route());
    }
}
