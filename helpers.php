<?php

if (! function_exists('clean_path')) {
    function clean_path(string $path): string
    {
        return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, trim($path, '/\\'));
    }
}

if (! function_exists('generate_url')) {
    function generate_url(string $domain, bool $isHttps = false): string
    {
        if (preg_match("/^https?:\/\//", $domain)) {
            return $isHttps
                ? preg_replace("/^https?/", 'https', $domain)
                : $domain;
        }

        return sprintf('http%s://%s', $isHttps ? 's' : '', $domain);
    }
}

if (! function_exists('clean_domain')) {
    function clean_domain(string $url): string
    {
        return rtrim(preg_replace("/^https?:\/\//", '', $url), '/');
    }
}