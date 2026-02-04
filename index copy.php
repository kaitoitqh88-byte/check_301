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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $url = $_POST['url'];
    if (filter_var($url, FILTER_VALIDATE_URL)) {
        $result = checkRedirects($url);
        if ($result['error']) {
            echo "<div class='result'><p>Error: {$result['error']}</p></div>";
        } else {
            $redirects = $result['redirects'];
            $has_301 = $result['has_301'];
            $otherRedirects = $result['other'];
            $finalUrl = $result['final_url'];
            $finalCode = $result['final_code'];

            echo "<div class='result'>";
            echo "<h2>Check Results:</h2>";
            echo "<p>Original URL: $url</p>";
            echo "<p>Redirect Chain:</p>";
            echo "<ol>";
            foreach ($redirects as $redirect) {
                echo "<li>{$redirect['url']} -> {$redirect['code']}</li>";
            }
            echo "</ol>";
            echo "<p>Final URL: $finalUrl</p>";

            if ($has_301) {
                echo "<p><strong>301 redirect detected in chain!</strong></p>";
            } else {
                echo "<p>No 301 redirect in chain.</p>";
            }

            if ($otherRedirects) {
                if ($otherRedirects['meta']['found']) {
                    echo "<p><strong>Meta refresh redirect found:</strong> {$otherRedirects['meta']['url']}</p>";
                }
                if ($otherRedirects['js']['found']) {
                    echo "<p><strong>JavaScript redirect found:</strong> {$otherRedirects['js']['url']}</p>";
                }
            }
            echo "</div>";
        }
    } else {
        echo "<div class='result'><p>Invalid URL.</p></div>";
    }
} else {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Check Your Redirects and Status Code</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            form { margin-bottom: 20px; }
            input[type="text"] { padding: 8px; width: 300px; }
            input[type="submit"] { padding: 8px 16px; background-color: #4CAF50; color: white; border: none; cursor: pointer; }
            input[type="submit"]:hover { background-color: #45a049; }
            .result { background-color: #f9f9f9; padding: 10px; border: 1px solid #ddd; }
        </style>
    </head>
    <body>
        <h1>Check Your Redirects and Status Code</h1>
        <p>301 vs 302, meta refresh & javascript redirects</p>
        <form method="post" onsubmit="return validateForm()">
            <label for="url">Enter URL to check:</label><br>
            <input type="text" name="url" id="url" size="50" placeholder="https://example.com"><br><br>
            <input type="submit" value="Check">
        </form>
        <script>
            function validateForm() {
                const url = document.getElementById('url').value.trim();
                if (!url) {
                    alert('Please enter a URL');
                    return false;
                }
                const urlRegex = /^https?:\/\/.+/i;
                if (!urlRegex.test(url)) {
                    alert('Invalid URL. Must start with http:// or https://');
                    return false;
                }
                return true;
            }
        </script>
    </body>
    </html> 
    <?php 
}
?>
