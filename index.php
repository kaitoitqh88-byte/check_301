<?php
include 'functions.php';

function displayResult($url, $result)
{
    $output = "<div class='card result-card mt-3'>";
    $output .= "<div class='card-header'>";
    $output .= "<h5 class='mb-0'><i class='fas fa-link me-2'></i>Results for: " . htmlspecialchars($url) . "</h5>";
    $output .= "</div>";
    $output .= "<div class='card-body'>";
    
    if ($result['error']) {
        $output .= "<div class='alert alert-danger'>";
        $output .= "<i class='fas fa-exclamation-triangle me-2'></i>" . htmlspecialchars($result['error']);
        $output .= "</div>";
    } else {
        $redirects = $result['redirects'];
        $has_301 = $result['has_301'];
        $otherRedirects = $result['other'];
        $finalUrl = $result['final_url'];
        $finalCode = $result['final_code'];

        $output .= "<div class='row'>";
        $output .= "<div class='col-md-6'>";
        $output .= "<h6><i class='fas fa-route me-2'></i>Redirect Chain:</h6>";
        $output .= "<div class='redirect-chain'>";
        foreach ($redirects as $index => $redirect) {
            $statusClass = '';
            if ($redirect['code'] == 301) $statusClass = 'text-success';
            elseif ($redirect['code'] == 302) $statusClass = 'text-warning';
            elseif ($redirect['code'] >= 400) $statusClass = 'text-danger';
            
            $output .= "<div class='redirect-step'>";
            $output .= "<span class='step-number'>" . ($index + 1) . ".</span>";
            $output .= "<code class='url'>" . htmlspecialchars($redirect['url']) . "</code>";
            $output .= "<span class='arrow'><i class='fas fa-arrow-right'></i></span>";
            $output .= "<span class='badge bg-secondary $statusClass'>" . $redirect['code'] . "</span>";
            $output .= "</div>";
        }
        $output .= "</div>";
        $output .= "</div>";
        
        $output .= "<div class='col-md-6'>";
        $output .= "<h6><i class='fas fa-flag-checkered me-2'></i>Final Destination:</h6>";
        $output .= "<div class='final-url'>";
        $output .= "<code>" . htmlspecialchars($finalUrl) . "</code>";
        $output .= "</div>";
        
        if ($has_301) {
            $output .= "<div class='alert alert-success mt-3'>";
            $output .= "<i class='fas fa-check-circle me-2'></i><strong>301 Permanent Redirect Detected!</strong>";
            $output .= "<br><small>This is good for SEO - search engines will transfer ranking signals.</small>";
            $output .= "</div>";
        } else {
            $output .= "<div class='alert alert-info mt-3'>";
            $output .= "<i class='fas fa-info-circle me-2'></i>No 301 redirect in chain.";
            $output .= "</div>";
        }
        
        if ($otherRedirects) {
            if ($otherRedirects['meta']['found']) {
                $output .= "<div class='alert alert-warning mt-2'>";
                $output .= "<i class='fas fa-code me-2'></i><strong>Meta Refresh Redirect:</strong> ";
                $output .= "<code>" . htmlspecialchars($otherRedirects['meta']['url']) . "</code>";
                $output .= "</div>";
            }
            if ($otherRedirects['js']['found']) {
                $output .= "<div class='alert alert-warning mt-2'>";
                $output .= "<i class='fab fa-js-square me-2'></i><strong>JavaScript Redirect:</strong> ";
                $output .= "<code>" . htmlspecialchars($otherRedirects['js']['url']) . "</code>";
                $output .= "</div>";
            }
        }
        $output .= "</div>";
        $output .= "</div>";
    }
    $output .= "</div>";
    $output .= "</div>";
    
    return $output;
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
            $results[] = ['url' => $url, 'error' => 'Invalid URL format'];
        }
    } elseif (!empty($_POST['urls'])) {
        // Process multiple URLs
        $urls = array_filter(array_map('trim', explode("\n", $_POST['urls'])));
        foreach ($urls as $url) {
            if (filter_var($url, FILTER_VALIDATE_URL)) {
                $result = checkRedirects($url);
                $results[] = ['url' => $url, 'result' => $result];
            } else {
                $results[] = ['url' => $url, 'error' => 'Invalid URL format'];
            }
        }
    } else {
        $results[] = ['error' => 'Please enter a URL or URLs to check.'];
    }

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode($results);
        exit;
    } else {
        // Display HTML results
        $output = '';
        foreach ($results as $item) {
            if (isset($item['error'])) {
                $output .= "<div class='card result-card mt-3'>";
                $output .= "<div class='card-body'>";
                $output .= "<div class='alert alert-danger'>";
                $output .= "<i class='fas fa-exclamation-triangle me-2'></i>" . htmlspecialchars($item['error']);
                $output .= "</div>";
                $output .= "</div>";
                $output .= "</div>";
            } else {
                $output .= displayResult($item['url'], $item['result']);
            }
        }
        echo $output;
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<link rel="icon" type="image/x-icon" href="assets/favicon.ico">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>301 Redirect Chain Checker</title>
    <link href="https://fonts.googleapis.com/css?family=Roboto:400,500,700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <link href="../assets/css/common.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #00ff66;
            --secondary-color: #00cc44;
            --success-color: #00ff66;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --dark-color: #000000;
            --light-color: #111111;
        }
        body {
            background: var(--dark-color) !important;
            color: var(--primary-color) !important;
            font-family: 'Roboto', Arial, Helvetica, sans-serif !important;
            min-height: 100vh;
        }
        h1, h2, h3, h4, h5, h6, .navbar, .sidebar-card, .form-label, .btn, .nav-link, .dropdown-item, .form-control, .card, .result-card, .final-url, .redirect-step, .stats-card {
            font-family: 'Roboto', Arial, Helvetica, sans-serif !important;
        }
        .nav-link.active, .navbar-nav .nav-link.active {
            color: #fff !important;
            background: linear-gradient(90deg, #00ff66 0%, #00cc44 100%);
            border-radius: 0.5rem;
            box-shadow: 0 0 12px #00ff66;
        }
        .container {
            max-width: 1200px;
        }
        .hero-section {
            background: #000;
            color: var(--primary-color);
            padding: 3rem 0;
            margin-bottom: 2rem;
            border-radius: 1rem;
            box-shadow: 0 10px 15px -3px rgba(0,255,0,0.08);
            border: 1.5px solid var(--primary-color);
        }
        .hero-section h1 {
            font-weight: 800;
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: var(--primary-color);
        }
        .hero-section p {
            font-size: 1.2rem;
            opacity: 0.9;
            color: var(--primary-color);
        }
        .card, .form-card, .result-card {
            background: #111 !important;
            color: var(--primary-color) !important;
            border-radius: 1rem;
            border: 1.5px solid var(--primary-color);
            box-shadow: 0 4px 16px 0 rgba(0,255,0,0.08);
        }
        .form-card .card-header {
            background: none;
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 1rem;
            margin-bottom: 1.5rem;
            color: var(--primary-color);
        }
        .form-control {
            background: #000 !important;
            color: var(--primary-color) !important;
            border: 2px solid var(--primary-color);
            border-radius: 0.75rem;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        .form-control:focus {
            border-color: var(--success-color);
            box-shadow: 0 0 0 0.2rem rgba(0,255,102,0.15);
        }
        .btn-primary {
            background: var(--primary-color) !important;
            border: none;
            border-radius: 0.75rem;
            padding: 0.75rem 2rem;
            font-weight: 600;
            font-size: 1.1rem;
            color: #000 !important;
            box-shadow: 0 0 10px 0 #00ff66;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            background: var(--secondary-color) !important;
            color: #000 !important;
            box-shadow: 0 0 16px 0 #00ff66;
        }
        .result-card {
            position: relative;
            z-index: 20;
            border-radius: 1rem;
            box-shadow: 0 4px 24px 0 rgba(0,255,102,0.10);
            margin-bottom: 2rem;
            background: #181f1c !important;
            border: 1.5px solid #00ff66;
            padding-bottom: 1rem;
        }
        .result-card .card-header {
            background: none;
            border-bottom: 1px solid #00ff66;
            text-align: center;
            font-weight: 600;
            font-size: 1.15rem;
            color: #00ff66;
            border-radius: 1rem 1rem 0 0;
        }
        .result-card .card-body {
            padding: 1.5rem 1rem 1rem 1rem;
        }
        .redirect-chain {
            overflow-x: auto;
            margin-bottom: 1rem;
            background: #101613;
            border-radius: 0.5rem;
            border: 1px solid #00ff66;
            padding: 0.75rem 1rem;
        }
        .final-url {
            background: #0a1a12;
            border-radius: 0.5rem;
            border: 1px solid #00ff66;
            padding: 0.75rem 1rem;
            margin-bottom: 1rem;
        }
        .alert {
            border-radius: 0.5rem;
            font-size: 1rem;
        }
        @media (max-width: 991px) {
            .col-md-6 {
                flex: 0 0 100%;
                max-width: 100%;
            }
            .result-card .card-body {
                padding: 1rem 0.5rem 1rem 0.5rem;
            }
        }
        /* Đảm bảo chữ phần Redirect Chain luôn màu trắng */
        .redirect-chain, .redirect-chain * {
            color: #fff !important;
        }
        /* Đảm bảo màu chữ tab Single URL và Multiple URLs là #fff */
        #checkTabs .nav-link {
            color: #fff !important;
        }
        #checkTabs .nav-link.active {
            color: #000 !important;
            background: linear-gradient(90deg, #00ff66 0%, #00cc44 100%);
        }
    </style>
</head>
<body>
   
    <div class="container-fluid mt-4">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-lg-3 mb-4">
                <?php include __DIR__ . '/sidebar.php'; ?>
            </div>
            <div class="col-lg-9">
        <!-- Hero Section -->
        <div class="hero-section text-center">
            <h1><i class="fas fa-link me-3"></i>301 Redirect Chain Checker</h1>
            <p class="lead mb-0">Analyze redirect chains, detect 301/302 redirects, meta refresh, and JavaScript redirects</p>
        </div>
        <!-- Main Form -->
        <div class="card form-card mb-4">
            <div class="card-header">
                <ul class="nav nav-pills" id="checkTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="single-tab" data-bs-toggle="pill" data-bs-target="#single" type="button" role="tab">
                            <i class="fas fa-link me-2"></i>Single URL
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="multiple-tab" data-bs-toggle="pill" data-bs-target="#multiple" type="button" role="tab">
                            <i class="fas fa-list me-2"></i>Multiple URLs
                        </button>
                    </li>
                </ul>
            </div>

            <div class="tab-content" id="checkTabsContent">
                <!-- Single URL Tab -->
                <div class="tab-pane fade show active" id="single" role="tabpanel">
                    <form id="singleUrlForm">
                        <div class="row">
                            <div class="col-md-10">
                                <label for="url" class="form-label">
                                    <i class="fas fa-globe me-2"></i>Enter URL to check:
                                </label>
                                <input type="text" class="form-control" id="url" name="url" 
                                       placeholder="https://example.com" required>
                                <div class="form-text">
                                    <i class="fas fa-info-circle me-1"></i>
                                    URL must start with http:// or https://
                                </div>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-search me-2"></i>Check
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Multiple URLs Tab -->
                <div class="tab-pane fade" id="multiple" role="tabpanel">
                    <form id="multipleUrlsForm">
                        <div class="row">
                            <div class="col-md-9">
                                <label for="urls" class="form-label">
                                    <i class="fas fa-list me-2"></i>Enter URLs to check (one per line):
                                </label>
                                <textarea class="form-control" id="urls" name="urls" rows="6" 
                                          placeholder="https://example1.com&#10;https://example2.com&#10;https://example3.com" required></textarea>
                                <div class="form-text">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Each URL must be on a separate line and start with http:// or https://
                                </div>
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-search-plus me-2"></i>Check All
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Progress Section -->
        <div id="progressContainer" class="progress-container" style="display: none;">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5><i class="fas fa-clock me-2"></i>Checking URLs...</h5>
                <span id="progressText" class="text-muted">0 / 0</span>
            </div>
            <div class="progress">
                <div id="progressBar" class="progress-bar" role="progressbar" style="width: 0%"></div>
            </div>

        </div>

          <div id="results"></div>
        
        <!-- Quick Stats -->
        <div id="quickStats" class="row" style="display: none;"></div>

        </div> <!-- end col-lg-9 -->
        </div> <!-- end row -->
        <!-- Results Section -->
      
    </div> <!-- end container-fluid -->

    </div>

    <!-- Back to Top Button -->
    <button id="backToTop" class="back-to-top">
        <i class="fas fa-chevron-up"></i>
    </button>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        let currentChecking = 0;
        let totalToCheck = 0;
        let checkResults = [];

        // Form handlers
        document.getElementById('singleUrlForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const url = document.getElementById('url').value.trim();
            
            if (!validateUrl(url)) {
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid URL',
                    text: 'URL must start with http:// or https://',
                    confirmButtonColor: '#6366f1'
                });
                return;
            }
            
            checkSingleUrl(url);
        });

        document.getElementById('multipleUrlsForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const urlsText = document.getElementById('urls').value.trim();
            
            if (!urlsText) {
                Swal.fire({
                    icon: 'error',
                    title: 'No URLs provided',
                    text: 'Please enter at least one URL to check',
                    confirmButtonColor: '#6366f1'
                });
                return;
            }

            const urls = urlsText.split('\n')
                .map(url => url.trim())
                .filter(url => url.length > 0);

            // Validate all URLs
            const invalidUrls = urls.filter(url => !validateUrl(url));
            if (invalidUrls.length > 0) {
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid URLs found',
                    html: `The following URLs are invalid:<br><code>${invalidUrls.join('<br>')}</code><br><br>All URLs must start with http:// or https://`,
                    confirmButtonColor: '#6366f1'
                });
                return;
            }

            checkMultipleUrls(urls);
        });

        function validateUrl(url) {
            const urlRegex = /^https?:\/\/.+/i;
            return urlRegex.test(url);
        }

        function checkSingleUrl(url) {
            clearResults();
            showProgress(1);
            
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
                hideProgress();
                displayResults(data);
                updateStats(data);
            })
            .catch(error => {
                hideProgress();
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to check URL: ' + error.message,
                    confirmButtonColor: '#6366f1'
                });
            });
        }

        function checkMultipleUrls(urls) {
            clearResults();
            showProgress(urls.length);
            
            currentChecking = 0;
            totalToCheck = urls.length;
            checkResults = [];

            // Check each URL sequentially with small delay
            urls.forEach((url, index) => {
                setTimeout(() => {
                    checkUrlAjax(url, index);
                }, index * 200);
            });
        }

        function checkUrlAjax(url, index) {
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
                checkResults.push(...data);
                currentChecking++;
                updateProgress();
                
                if (currentChecking === totalToCheck) {
                    hideProgress();
                    displayResults(checkResults);
                    updateStats(checkResults);
                }
            })
            .catch(error => {
                checkResults.push({
                    url: url,
                    error: 'Failed to check: ' + error.message
                });
                currentChecking++;
                updateProgress();
                
                if (currentChecking === totalToCheck) {
                    hideProgress();
                    displayResults(checkResults);
                    updateStats(checkResults);
                }
            });
        }

        function showProgress(total) {
            totalToCheck = total;
            currentChecking = 0;
            document.getElementById('progressContainer').style.display = 'block';
            document.getElementById('progressText').textContent = `0 / ${total}`;
            document.getElementById('progressBar').style.width = '0%';
        }

        function updateProgress() {
            const percentage = (currentChecking / totalToCheck) * 100;
            document.getElementById('progressText').textContent = `${currentChecking} / ${totalToCheck}`;
            document.getElementById('progressBar').style.width = percentage + '%';
        }

        function hideProgress() {
            setTimeout(() => {
                document.getElementById('progressContainer').style.display = 'none';
            }, 500);
        }

        function clearResults() {
            document.getElementById('results').innerHTML = '';
            document.getElementById('quickStats').style.display = 'none';
        }

        function displayResults(results) {
            const resultsDiv = document.getElementById('results');
            
            results.forEach((item, index) => {
                setTimeout(() => {
                    if (item.error) {
                        resultsDiv.innerHTML += createErrorCard(item.url || 'Unknown', item.error);
                    } else {
                        resultsDiv.innerHTML += createResultCard(item.url, item.result);
                    }
                }, index * 100);
            });
        }

        function createResultCard(url, result) {
            let card = `<div class="card result-card">`;
            card += `<div class="card-header">`;
            card += `<h5 class="mb-0"><i class="fas fa-link me-2"></i>${escapeHtml(url)}</h5>`;
            card += `</div>`;
            card += `<div class="card-body">`;
            
            if (result.error) {
                card += `<div class="alert alert-danger">`;
                card += `<i class="fas fa-exclamation-triangle me-2"></i>${escapeHtml(result.error)}`;
                card += `</div>`;
            } else {
                card += `<div class="row">`;
                card += `<div class="col-md-6">`;
                card += `<h6><i class="fas fa-route me-2"></i>Redirect Chain:</h6>`;
                card += `<div class="redirect-chain">`;
                
                result.redirects.forEach((redirect, index) => {
                    let statusClass = '';
                    if (redirect.code == 301) statusClass = 'text-success';
                    else if (redirect.code == 302) statusClass = 'text-warning';
                    else if (redirect.code >= 400) statusClass = 'text-danger';
                    
                    card += `<div class="redirect-step">`;
                    card += `<span class="step-number">${index + 1}</span>`;
                    card += `<code class="url">${escapeHtml(redirect.url)}</code>`;
                    card += `<span class="arrow"><i class="fas fa-arrow-right"></i></span>`;
                    card += `<span class="badge bg-secondary ${statusClass}">${redirect.code}</span>`;
                    card += `</div>`;
                });
                
                card += `</div>`;
                card += `</div>`;
                
                card += `<div class="col-md-6">`;
                card += `<h6><i class="fas fa-flag-checkered me-2"></i>Final Destination:</h6>`;
                card += `<div class="final-url">`;
                card += `<code>${escapeHtml(result.final_url)}</code>`;
                card += `</div>`;
                
                if (result.has_301) {
                    card += `<div class="alert alert-success mt-3">`;
                    card += `<i class="fas fa-check-circle me-2"></i><strong>301 Permanent Redirect Detected!</strong>`;
                    card += `<br><small>This is good for SEO - search engines will transfer ranking signals.</small>`;
                    card += `</div>`;
                } else {
                    card += `<div class="alert alert-info mt-3">`;
                    card += `<i class="fas fa-info-circle me-2"></i>No 301 redirect in chain.`;
                    card += `</div>`;
                }
                
                if (result.other) {
                    if (result.other.meta && result.other.meta.found) {
                        card += `<div class="alert alert-warning mt-2">`;
                        card += `<i class="fas fa-code me-2"></i><strong>Meta Refresh:</strong> `;
                        card += `<code>${escapeHtml(result.other.meta.url)}</code>`;
                        card += `</div>`;
                    }
                    if (result.other.js && result.other.js.found) {
                        card += `<div class="alert alert-warning mt-2">`;
                        card += `<i class="fab fa-js-square me-2"></i><strong>JavaScript Redirect:</strong> `;
                        card += `<code>${escapeHtml(result.other.js.url)}</code>`;
                        card += `</div>`;
                    }
                }
                
                card += `</div>`;
                card += `</div>`;
            }
            
            card += `</div>`;
            card += `</div>`;
            
            return card;
        }

        function createErrorCard(url, error) {
            let card = `<div class="card result-card error">`;
            card += `<div class="card-header">`;
            card += `<h5 class="mb-0 text-danger"><i class="fas fa-exclamation-triangle me-2"></i>Error: ${escapeHtml(url)}</h5>`;
            card += `</div>`;
            card += `<div class="card-body">`;
            card += `<div class="alert alert-danger mb-0">`;
            card += `<i class="fas fa-exclamation-triangle me-2"></i>${escapeHtml(error)}`;
            card += `</div>`;
            card += `</div>`;
            card += `</div>`;
            
            return card;
        }

        function updateStats(results) {
            const statsDiv = document.getElementById('quickStats');
            
            let total = results.length;
            let has301 = 0;
            let hasErrors = 0;
            let hasOtherRedirects = 0;
            
            results.forEach(item => {
                if (item.error) {
                    hasErrors++;
                } else if (item.result && !item.result.error) {
                    if (item.result.has_301) has301++;
                    if (item.result.other && (item.result.other.meta.found || item.result.other.js.found)) {
                        hasOtherRedirects++;
                    }
                }
            });
            
            let statsHtml = `
                <div class="col-md-3 mb-3">
                    <div class="card stats-card text-white">
                        <div class="card-body text-center">
                            <h3>${total}</h3>
                            <small>Total Checked</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card text-white" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                        <div class="card-body text-center">
                            <h3>${has301}</h3>
                            <small>Has 301 Redirect</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card text-white" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                        <div class="card-body text-center">
                            <h3>${hasOtherRedirects}</h3>
                            <small>Meta/JS Redirects</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card text-white" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);">
                        <div class="card-body text-center">
                            <h3>${hasErrors}</h3>
                            <small>Errors</small>
                        </div>
                    </div>
                </div>
            `;
            
            statsDiv.innerHTML = statsHtml;
            statsDiv.style.display = 'flex';
        }

        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, m => map[m]);
        }

        // Back to top button
        window.addEventListener('scroll', function() {
            const backToTop = document.getElementById('backToTop');
            if (window.pageYOffset > 300) {
                backToTop.classList.add('show');
            } else {
                backToTop.classList.remove('show');
            }
        });

        document.getElementById('backToTop').addEventListener('click', function() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });

        // Auto-focus on URL input
        document.getElementById('url').focus();
        
        // Navigation scroll effect
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar-main');
            if (navbar) {
                if (window.scrollY > 50) {
                    navbar.style.background = 'rgba(31, 41, 55, 0.95)';
                    navbar.style.backdropFilter = 'blur(10px)';
                } else {
                    navbar.style.background = 'linear-gradient(135deg, #1f2937 0%, #374151 100%)';
                    navbar.style.backdropFilter = 'none';
                }
            }
        });
    </script>
</body>
</html>

