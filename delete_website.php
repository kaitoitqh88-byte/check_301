<?php
// Delete Website from VPS using WP-CLI via SSH
// NOTE: This file includes both PHP and HTML. It must remain valid PHP.

// Function to get VPS information from vps.json
function getVpsInfo(string $domain): ?array {
    $vpsDataPath = __DIR__ . '/../vps.json';
    if (!file_exists($vpsDataPath)) {
        return ['error' => 'Could not read vps.json. Ensure it exists in the root directory.'];
    }

    $raw = file_get_contents($vpsDataPath);
    if ($raw === false) {
        return ['error' => 'Could not read vps.json.'];
    }

    $vpsList = json_decode($raw, true);
    if (!is_array($vpsList)) {
        return ['error' => 'Error decoding vps.json: ' . json_last_error_msg()];
    }

    foreach ($vpsList as $vps) {
        if (isset($vps['domain']) && $vps['domain'] === $domain) {
            return $vps['vps_info'] ?? null;
        }
    }

    return null;
}

// Function to delete website using WP-CLI via SSH
function deleteWebsiteViaSsh(array $vpsInfo, string $domain): array {
    $ip = escapeshellarg((string)($vpsInfo['ip'] ?? ''));
    $user = escapeshellarg((string)($vpsInfo['user'] ?? ''));
    $password = escapeshellarg((string)($vpsInfo['password'] ?? '')); // WARNING: Passing password via CLI is insecure.

    if ($ip === "''" || $user === "''") {
        throw new Exception('Missing ip/user in vps_info');
    }

    $wordpressPath = !empty($vpsInfo['wordpress_path'])
        ? escapeshellarg((string)$vpsInfo['wordpress_path'])
        : escapeshellarg('/var/www/html/' . $domain);

    $commands = [
        "cd {$wordpressPath}",
        "wp db drop --yes",
        "rm -rf {$wordpressPath}",
    ];

    $output = [];
    $status = 0;

    foreach ($commands as $command) {
        $sshCommand = "sshpass -p {$password} ssh -o StrictHostKeyChecking=no {$user}@{$ip} '{$command}' 2>&1";
        exec($sshCommand, $currentOutput, $currentStatus);
        $output = array_merge($output, $currentOutput);

        if ($currentStatus !== 0) {
            $status = $currentStatus;
            break;
        }
    }

    return ['output' => implode("\n", $output), 'status' => $status];
}

// Handle request
$domainToDelete = '';
$result = null;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $domainToDelete = trim((string)($_POST['domain'] ?? ''));

    if ($domainToDelete === '') {
        $error = 'Domain is required';
    } else {
        $vpsInfoResult = getVpsInfo($domainToDelete);

        if (is_array($vpsInfoResult) && isset($vpsInfoResult['error'])) {
            $error = $vpsInfoResult['error'];
        } elseif ($vpsInfoResult) {
            try {
                $result = deleteWebsiteViaSsh($vpsInfoResult, $domainToDelete);
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        } else {
            $error = 'No VPS information found for the domain: ' . htmlspecialchars($domainToDelete);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Website</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css">
</head>
<body>
<div class="container">
    <h1>Delete Website from VPS using WP-CLI and SSH</h1>

    <form method="POST" action="" enctype="multipart/form-data" class="mt-3">
        <label for="domain">Domain to Delete:</label>
        <br/>
        <input type="text" id="domain" name="domain" required value="<?php echo htmlspecialchars($domainToDelete); ?>" />
        <br/><br/>
        <input type="submit" value="Delete Website" class="btn btn-danger" />
    </form>

    <?php if ($error !== ''): ?>
        <div class="mt-4 alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($result !== null): ?>
        <h2 class="mt-4">Processing deletion for: <?php echo htmlspecialchars($domainToDelete); ?></h2>
        <?php if ((int)$result['status'] === 0): ?>
            <div class="alert alert-success">Website deletion command executed successfully!</div>
        <?php else: ?>
            <div class="alert alert-danger">Failed to delete website. Status code: <?php echo htmlspecialchars((string)$result['status']); ?></div>
        <?php endif; ?>

        <h4>SSH Command Output:</h4>
        <pre><?php echo htmlspecialchars($result['output']); ?></pre>
    <?php endif; ?>
</div>
</body>
</html>

