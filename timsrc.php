<?php
// Google Drive Folder Search Tool
// Yêu cầu: composer require google/apiclient

require_once __DIR__ . '/../vendor/autoload.php';

// Cấu hình OAuth2
$client = new Google_Client();
$client->setAuthConfig(__DIR__ . '/../credentials.json');
$client->addScope(Google_Service_Drive::DRIVE_METADATA_READONLY);
$client->setRedirectUri((isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF']);
$client->setAccessType('offline');

session_start();

// Xử lý đăng nhập OAuth2
if (isset($_GET['code'])) {
	$token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
	$_SESSION['access_token'] = $token;
	header('Location: ' . filter_var($client->getRedirectUri(), FILTER_SANITIZE_URL));
	exit();
}

if (isset($_SESSION['access_token']) && $_SESSION['access_token']) {
	$client->setAccessToken($_SESSION['access_token']);
	if ($client->isAccessTokenExpired()) {
		unset($_SESSION['access_token']);
		header('Location: ' . filter_var($client->getRedirectUri(), FILTER_SANITIZE_URL));
		exit();
	}
} else {
	$authUrl = $client->createAuthUrl();
	echo "<a href='$authUrl'>Đăng nhập Google để tìm kiếm thư mục Drive</a>";
	exit();
}

$service = new Google_Service_Drive($client);

$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$folders = [];
if ($query !== '') {
	$optParams = [
		'q' => sprintf("mimeType='application/vnd.google-apps.folder' and name contains '%s' and trashed = false", addslashes($query)),
		'fields' => 'files(id, name, webViewLink)',
		'pageSize' => 50
	];
	$results = $service->files->listFiles($optParams);
	$folders = $results->getFiles();
}

?>
<html lang="vi">
<head>
	<meta charset="UTF-8">
	<title>Tìm kiếm thư mục Google Drive</title>
	<style>
		body { font-family: Arial; margin: 40px; }
		input[type=text] { width: 300px; padding: 6px; }
		button { padding: 6px 16px; }
		.folder { margin: 10px 0; }
	</style>
</head>
<body>
	<h2>Tìm kiếm thư mục Google Drive</h2>
	<form method="get">
		<input type="text" name="q" placeholder="Nhập từ khóa..." value="<?=htmlspecialchars($query)?>" required>
		<button type="submit">Tìm kiếm</button>
	</form>

	<?php if ($query !== ''): ?>
		<h3>Kết quả cho "<?=htmlspecialchars($query)?>"</h3>
		<?php if (count($folders) === 0): ?>
			<p>Không tìm thấy thư mục nào.</p>
		<?php else: ?>
			<ul>
			<?php foreach ($folders as $folder): ?>
				<li class="folder">
					<a href="<?=$folder->getWebViewLink()?>" target="_blank">
						<?=htmlspecialchars($folder->getName())?>
					</a>
				</li>
			<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	<?php endif; ?>
</body>
</html>