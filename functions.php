<?php
function checkOtherRedirects($html) {
    $metaRedirect = false;
    $jsRedirect = false;
    $metaUrl = '';
    $jsUrl = '';

    // Check meta refresh
    if (preg_match('/<meta[^>]+http-equiv=["\']refresh["\'][^>]+content=["\'](\d+);\s*url=([^"\'>\s]+)["\']/i', $html, $matches)) {
        $metaRedirect = true;
        $metaUrl = $matches[2];
    }

    // Check JS redirect
    if (preg_match('/(?:window\.|location\.)location(?:\.href)?\s*=\s*["\']([^"\']+)["\']/i', $html, $matches)) {
        $jsRedirect = true;
        $jsUrl = $matches[1];
    }

    return ['meta' => ['found' => $metaRedirect, 'url' => $metaUrl], 'js' => ['found' => $jsRedirect, 'url' => $jsUrl]];
}

function checkRedirects($url) {
    $redirects = [];
    $current_url = $url;
    $max_redirects = 10;
    $has_301 = false;

    for ($i = 0; $i < $max_redirects; $i++) {
        $ch = curl_init($current_url);
        if (!$ch) {
            return ['error' => 'Failed to initialize cURL'];
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $response = curl_exec($ch);
        if (curl_error($ch)) {
            curl_close($ch);
            return ['error' => 'cURL error: ' . curl_error($ch)];
        }
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $redirects[] = ['url' => $current_url, 'code' => $httpCode];

        if ($httpCode == 301) {
            $has_301 = true;
        }

        if ($httpCode == 301 || $httpCode == 302) {
            $headers = explode("\n", $response);
            foreach ($headers as $header) {
                if (stripos($header, 'Location:') === 0) {
                    $location = trim(substr($header, 9));
                    if (strpos($location, 'http') !== 0) {
                        $parsed = parse_url($current_url);
                        if ($location[0] == '/') {
                            $location = $parsed['scheme'] . '://' . $parsed['host'] . $location;
                        } else {
                            $location = dirname($current_url) . '/' . $location;
                        }
                    }
                    $current_url = $location;
                    break;
                }
            }
        } else {
            break;
        }
    }

    $finalCode = end($redirects)['code'];
    $finalUrl = end($redirects)['url'];
    $otherRedirects = null;

    if ($finalCode == 200) {
        $ch = curl_init($finalUrl);
        if ($ch) {
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            $html = curl_exec($ch);
            if (!curl_error($ch)) {
                $otherRedirects = checkOtherRedirects($html);
            }
            curl_close($ch);
        }
    }

    return [
        'redirects' => $redirects,
        'has_301' => $has_301,
        'other' => $otherRedirects,
        'final_url' => $finalUrl,
        'final_code' => $finalCode,
        'error' => null
    ];
}
?>