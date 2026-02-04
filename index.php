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
    } else {
        $results[] = ['error' => 'Please enter a URL or upload a file.'];
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
} else {
    ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <title>Check Your Redirects and Status Code</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                margin: 20px;
            }

            form {
                margin-bottom: 20px;
            }

            input[type="text"] {
                padding: 8px;
                width: 300px;
            }

            input[type="submit"] {
                padding: 8px 16px;
                background-color: #4CAF50;
                color: white;
                border: none;
                cursor: pointer;
            }

            input[type="submit"]:hover {
                background-color: #45a049;
            }

            .result {
                background-color: #f9f9f9;
                padding: 10px;
                border: 1px solid #ddd;
            }
        </style>
    </head>

    <body>
        <h1>Check Your Redirects and Status Code</h1>
        <p>301 vs 302, meta refresh & javascript redirects</p>
        <form id="checkForm">
            <label for="url">Enter URL to check:</label><br>
            <input type="text" name="url" id="url" size="50" placeholder="https://example.com"><br><br>
            <input type="submit" value="Check">
        </form>
        <div id="loading" style="display:none;">Checking...</div>
        <div id="results"></div>
        <script>
            document.getElementById('checkForm').addEventListener('submit', function (e) {
                e.preventDefault();
                if (!validateForm()) return;

                const formData = new FormData(this);
                const loading = document.getElementById('loading');
                const resultsDiv = document.getElementById('results');

                loading.style.display = 'block';
                resultsDiv.innerHTML = '';

                fetch('', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                    .then(response => response.json())
                    .then(data => {
                        loading.style.display = 'none';
                        data.forEach(item => {
                            const div = document.createElement('div');
                            div.className = 'result';
                            if (item.error) {
                                div.innerHTML = `<p>${item.error}</p>`;
                            } else {
                                div.innerHTML = `
                                <h3>Results for: ${item.url}</h3>
                                <p>Redirect Chain:</p>
                                <ol>${item.result.redirects.map(r => `<li>${r.url} -> ${r.code}</li>`).join('')}</ol>
                                <p>Final URL: ${item.result.final_url}</p>
                                ${item.result.has_301 ? '<p><strong>301 redirect detected in chain!</strong></p>' : '<p>No 301 redirect in chain.</p>'}
                                ${item.result.other && item.result.other.meta.found ? `<p><strong>Meta refresh redirect found:</strong> ${item.result.other.meta.url}</p>` : ''}
                                ${item.result.other && item.result.other.js.found ? `<p><strong>JavaScript redirect found:</strong> ${item.result.other.js.url}</p>` : ''}
                            `;
                            }
                            resultsDiv.appendChild(div);
                        });
                    })
                    .catch(error => {
                        loading.style.display = 'none';
                        resultsDiv.innerHTML = '<div class="result"><p>Error: ' + error.message + '</p></div>';
                    });
            });

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