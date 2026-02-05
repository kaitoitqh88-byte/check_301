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

            input[type="text"],
            textarea {
                padding: 8px;
                width: 500px;
                font-family: Arial, sans-serif;
            }

            textarea {
                min-height: 150px;
                resize: vertical;
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
                margin-bottom: 10px;
            }

            .result.checking {
                background-color: #fff3cd;
                border-color: #ffc107;
            }

            .result.error {
                background-color: #f8d7da;
                border-color: #dc3545;
            }

            .progress {
                margin: 10px 0;
                padding: 10px;
                background-color: #e9ecef;
                border-radius: 5px;
            }

            .tab-buttons {
                margin-bottom: 10px;
            }

            .tab-buttons button {
                padding: 8px 16px;
                margin-right: 5px;
                border: 1px solid #ddd;
                background-color: #f0f0f0;
                cursor: pointer;
            }

            .tab-buttons button.active {
                background-color: #4CAF50;
                color: white;
                border-color: #4CAF50;
            }

            .tab-content {
                display: none;
            }

            .tab-content.active {
                display: block;
            }
        </style>
    </head>

    <body>
        <h1>Check Your Redirects and Status Code</h1>
        <p>301 vs 302, meta refresh & javascript redirects</p>

        <div class="tab-buttons">
            <button class="active" onclick="switchTab('single')">Single URL</button>
            <button onclick="switchTab('multiple')">Multiple URLs</button>
        </div>

        <!-- Single URL Tab -->
        <div id="singleTab" class="tab-content active">
            <form id="checkForm">
                <label for="url">Enter URL to check:</label><br>
                <input type="text" name="url" id="url" size="50" placeholder="https://example.com"><br><br>
                <input type="submit" value="Check">
            </form>
        </div>

        <!-- Multiple URLs Tab -->
        <div id="multipleTab" class="tab-content">
            <form id="checkMultipleForm">
                <label for="urls">Enter URLs to check (one per line):</label><br>
                <textarea name="urls" id="urls" placeholder="https://example1.com&#10;https://example2.com&#10;https://example3.com"></textarea><br><br>
                <input type="submit" value="Check All">
            </form>
        </div>

        <div id="progress" class="progress" style="display:none;"></div>
        <div id="results"></div>

        <script>
            let currentChecking = 0;
            let totalToCheck = 0;

            function switchTab(tab) {
                // Update buttons
                const buttons = document.querySelectorAll('.tab-buttons button');
                buttons.forEach(btn => btn.classList.remove('active'));
                event.target.classList.add('active');

                // Update tabs
                document.getElementById('singleTab').classList.remove('active');
                document.getElementById('multipleTab').classList.remove('active');

                if (tab === 'single') {
                    document.getElementById('singleTab').classList.add('active');
                } else {
                    document.getElementById('multipleTab').classList.add('active');
                }

                // Clear results
                document.getElementById('results').innerHTML = '';
                document.getElementById('progress').style.display = 'none';
            }

            // Single URL form handler
            document.getElementById('checkForm').addEventListener('submit', function (e) {
                e.preventDefault();
                const url = document.getElementById('url').value.trim();
                if (!url) {
                    alert('Please enter a URL');
                    return;
                }
                if (!validateUrl(url)) {
                    alert('Invalid URL. Must start with http:// or https://');
                    return;
                }

                document.getElementById('results').innerHTML = '';
                checkSingleUrl(url);
            });

            // Multiple URLs form handler
            document.getElementById('checkMultipleForm').addEventListener('submit', function (e) {
                e.preventDefault();
                const urlsText = document.getElementById('urls').value.trim();
                if (!urlsText) {
                    alert('Please enter at least one URL');
                    return;
                }

                const urls = urlsText.split('\n')
                    .map(url => url.trim())
                    .filter(url => url.length > 0);

                if (urls.length === 0) {
                    alert('Please enter at least one valid URL');
                    return;
                }

                // Validate all URLs
                for (let url of urls) {
                    if (!validateUrl(url)) {
                        alert('Invalid URL: ' + url + '\nAll URLs must start with http:// or https://');
                        return;
                    }
                }

                checkMultipleUrls(urls);
            });

            function validateUrl(url) {
                const urlRegex = /^https?:\/\/.+/i;
                return urlRegex.test(url);
            }

            function checkSingleUrl(url) {
                const resultsDiv = document.getElementById('results');
                const resultDiv = createResultDiv(url);
                resultDiv.classList.add('checking');
                resultDiv.innerHTML = `<h3>Checking: ${url}</h3><p>Please wait...</p>`;
                resultsDiv.appendChild(resultDiv);

                const formData = new FormData();
                formData.append('url', url);

                fetch('', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                    .then(response => response.json())
                    .then(data => {
                        displayResult(resultDiv, url, data[0]);
                    })
                    .catch(error => {
                        displayError(resultDiv, url, error.message);
                    });
            }

            function checkMultipleUrls(urls) {
                const resultsDiv = document.getElementById('results');
                const progressDiv = document.getElementById('progress');
                resultsDiv.innerHTML = '';
                progressDiv.style.display = 'block';
                
                currentChecking = 0;
                totalToCheck = urls.length;
                updateProgress();

                // Create result divs for all URLs
                const resultDivs = {};
                urls.forEach(url => {
                    const resultDiv = createResultDiv(url);
                    resultDiv.classList.add('checking');
                    resultDiv.innerHTML = `<h3>Waiting: ${url}</h3><p>In queue...</p>`;
                    resultsDiv.appendChild(resultDiv);
                    resultDivs[url] = resultDiv;
                });

                // Check each URL with separate AJAX call
                urls.forEach((url, index) => {
                    setTimeout(() => {
                        checkUrlAjax(url, resultDivs[url]);
                    }, index * 100); // Small delay to prevent overwhelming the server
                });
            }

            function checkUrlAjax(url, resultDiv) {
                resultDiv.innerHTML = `<h3>Checking: ${url}</h3><p>Please wait...</p>`;
                
                const formData = new FormData();
                formData.append('url', url);

                fetch('', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                    .then(response => response.json())
                    .then(data => {
                        displayResult(resultDiv, url, data[0]);
                        currentChecking++;
                        updateProgress();
                    })
                    .catch(error => {
                        displayError(resultDiv, url, error.message);
                        currentChecking++;
                        updateProgress();
                    });
            }

            function createResultDiv(url) {
                const div = document.createElement('div');
                div.className = 'result';
                div.id = 'result-' + btoa(url).replace(/[^a-zA-Z0-9]/g, '');
                return div;
            }

            function displayResult(resultDiv, url, data) {
                resultDiv.classList.remove('checking');
                
                if (data.error) {
                    displayError(resultDiv, url, data.error);
                    return;
                }

                const result = data.result;
                resultDiv.innerHTML = `
                    <h3>✓ Results for: ${url}</h3>
                    <p><strong>Redirect Chain:</strong></p>
                    <ol>${result.redirects.map(r => `<li>${r.url} → <strong>${r.code}</strong></li>`).join('')}</ol>
                    <p><strong>Final URL:</strong> ${result.final_url}</p>
                    ${result.has_301 ? '<p style="color: #28a745;"><strong>✓ 301 redirect detected in chain!</strong></p>' : '<p>No 301 redirect in chain.</p>'}
                    ${result.other && result.other.meta.found ? `<p><strong>Meta refresh redirect found:</strong> ${result.other.meta.url}</p>` : ''}
                    ${result.other && result.other.js.found ? `<p><strong>JavaScript redirect found:</strong> ${result.other.js.url}</p>` : ''}
                `;
            }

            function displayError(resultDiv, url, errorMsg) {
                resultDiv.classList.remove('checking');
                resultDiv.classList.add('error');
                resultDiv.innerHTML = `<h3>✗ Error checking: ${url}</h3><p>${errorMsg}</p>`;
            }

            function updateProgress() {
                const progressDiv = document.getElementById('progress');
                progressDiv.innerHTML = `<strong>Progress:</strong> ${currentChecking} / ${totalToCheck} URLs checked`;
                
                if (currentChecking === totalToCheck) {
                    progressDiv.innerHTML += ' - <span style="color: #28a745;">✓ All done!</span>';
                }
            }
        </script>
    </body>

    </html>
<?php
}
?>