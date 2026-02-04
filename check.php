<?php
include 'functions.php';

function displayResult($url, $result)
{
    echo "<div class='result'>";
    echo "<h3>Results for: $url</h3>";
    if ($result['error']) {
        echo "<p>Error: {$result['error']}</p>";
    } else {
        $redirects = $result['redirects'];
        $has_301 = $result['has_301'];
        $otherRedirects = $result['other'];
        $finalUrl = $result['final_url'];
        $finalCode = $result['final_code'];

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
    }
    echo "</div>";
} 
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    $results = [];

    if (!empty($_POST['url'])) {
        // Process single URL
        $url = $_POST['url'];
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            $result = checkRedirects($url);
            $results[] = ['url' => $url, 'result' => $result];
        } else {
            $results[] = ['url' => $url, 'error' => 'Invalid URL'];
        }
    }

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode($results);
        exit;
    } else {
        // Display HTML
        foreach ($results as $item) {
            if (isset($item['error'])) {
                echo "<div class='result'><p>{$item['error']}</p></div>";
            } else {
                displayResult($item['url'], $item['result']);
            }
        }
    }
}
?>
 <!-- #region -->