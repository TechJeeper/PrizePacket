<?php
// PrizePacket Installer
// Version 1.0

if (file_exists('config.php')) {
    die("PrizePacket is already installed. Please delete 'config.php' if you wish to reinstall.");
}

$message = '';
$error = '';

// Prerequisites Check
$phpVersion = phpversion();
$hasCurl = function_exists('curl_init');
$hasPdo = class_exists('PDO');
$requirementsMet = version_compare($phpVersion, '8.0', '>=') && $hasCurl && $hasPdo;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbHost = $_POST['db_host'] ?? '';
    $dbName = $_POST['db_name'] ?? '';
    $dbUser = $_POST['db_user'] ?? '';
    $dbPass = $_POST['db_pass'] ?? '';
    $appUrl = rtrim($_POST['app_url'] ?? '', '/');

    if (empty($dbHost) || empty($dbName) || empty($dbUser) || empty($appUrl)) {
        $error = "All fields except Database Password are required.";
    } else {
        try {
            // Test Connection
            $dsn = "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4";
            try {
                $pdo = new PDO($dsn, $dbUser, $dbPass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
            } catch (PDOException $e) {
                // If localhost fails, suggest 127.0.0.1
                if ($dbHost === 'localhost' && strpos($e->getMessage(), 'No such file or directory') !== false) {
                     throw new Exception("Connection failed: " . $e->getMessage() . ". Try using '127.0.0.1' instead of 'localhost'.");
                }
                throw $e;
            }

            // Write config.php
            $configContent = "<?php\n\n";
            $configContent .= "define('DB_HOST', '" . addslashes($dbHost) . "');\n";
            $configContent .= "define('DB_NAME', '" . addslashes($dbName) . "');\n";
            $configContent .= "define('DB_USER', '" . addslashes($dbUser) . "');\n";
            $configContent .= "define('DB_PASS', '" . addslashes($dbPass) . "');\n";
            $configContent .= "define('APP_URL', '" . addslashes($appUrl) . "');\n";

            if (file_put_contents('config.php', $configContent) === false) {
                 throw new Exception("Could not write to config.php. Check permissions.");
            }

            // SQL Schema
            $sql = <<<SQL
-- 1. App Configuration
CREATE TABLE IF NOT EXISTS settings (
   setting_key VARCHAR(50) PRIMARY KEY,
   setting_value TEXT
);

-- 2. Users
CREATE TABLE IF NOT EXISTS users (
   id INT AUTO_INCREMENT PRIMARY KEY,
   username VARCHAR(50) NOT NULL,
   password_hash VARCHAR(255) NOT NULL,
   created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 3. API Tokens
CREATE TABLE IF NOT EXISTS api_tokens (
   id INT AUTO_INCREMENT PRIMARY KEY,
   provider VARCHAR(20) NOT NULL,
   access_token TEXT,
   refresh_token TEXT,
   expires_at INT,
   scope TEXT,
   updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 4. Inventory Items
CREATE TABLE IF NOT EXISTS inventory (
   id INT AUTO_INCREMENT PRIMARY KEY,
   item_name VARCHAR(255) NOT NULL,
   sponsor VARCHAR(255),
   image_url VARCHAR(255),
   qty_initial INT DEFAULT 1,
   qty_current INT DEFAULT 1,
   created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 5. Campaigns
CREATE TABLE IF NOT EXISTS campaigns (
   id INT AUTO_INCREMENT PRIMARY KEY,
   title VARCHAR(255) NOT NULL,
   is_active TINYINT(1) DEFAULT 1,
   created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 6. Entrants
CREATE TABLE IF NOT EXISTS entrants (
   id INT AUTO_INCREMENT PRIMARY KEY,
   campaign_id INT NOT NULL,
   platform ENUM('twitch', 'youtube', 'google_sheet', 'manual') NOT NULL,
   platform_user_id VARCHAR(100),
   display_name VARCHAR(100) NOT NULL,
   source_data VARCHAR(255),
   UNIQUE KEY unique_entrant (campaign_id, platform, platform_user_id),
   FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE
);

-- 7. Winners
CREATE TABLE IF NOT EXISTS winners (
   id INT AUTO_INCREMENT PRIMARY KEY,
   campaign_id INT,
   inventory_id INT,
   entrant_id INT,
   display_name VARCHAR(100),
   contact_status ENUM('pending', 'contacted', 'info_received', 'shipped') DEFAULT 'pending',
   shipping_info TEXT,
   notes TEXT,
   won_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
   FOREIGN KEY (campaign_id) REFERENCES campaigns(id),
   FOREIGN KEY (inventory_id) REFERENCES inventory(id)
);
SQL;
            // Execute SQL, split by ; to handle multiple statements if PDO driver doesn't support multiple queries in one go (some don't)
            // But usually PDO::exec handles it if emulation is on, or we can just loop.
            // Let's loop to be safe.
            $statements = array_filter(array_map('trim', explode(';', $sql)));
            foreach ($statements as $stmt) {
                if (!empty($stmt)) {
                    $pdo->exec($stmt);
                }
            }

            // Create Default Admin User
            // Check if admin exists first
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $stmt->execute(['admin']);
            if ($stmt->fetchColumn() == 0) {
                $passwordHash = password_hash('password', PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, password_hash) VALUES (?, ?)");
                $stmt->execute(['admin', $passwordHash]);
            }

            $message = "Installation successful! 'config.php' created. Default user: admin / password. Please delete this file and <a href='index.php' class='underline'>Go to Login</a>.";

        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PrizePacket Installer</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">

    <div class="bg-white shadow-md rounded-lg p-8 w-full max-w-lg">
        <h1 class="text-3xl font-bold mb-6 text-center text-blue-600">PrizePacket Installer</h1>

        <?php if (!$requirementsMet): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4" role="alert">
                <strong class="font-bold">Requirements Not Met!</strong>
                <ul class="list-disc list-inside mt-2">
                    <li class="<?= version_compare($phpVersion, '8.0', '>=') ? 'text-green-600' : 'text-red-600' ?>">PHP 8.0+ (Current: <?= $phpVersion ?>)</li>
                    <li class="<?= $hasCurl ? 'text-green-600' : 'text-red-600' ?>">cURL Enabled</li>
                    <li class="<?= $hasPdo ? 'text-green-600' : 'text-red-600' ?>">PDO Enabled</li>
                </ul>
            </div>
        <?php else: ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                System Requirements Met.
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4" role="alert">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($message): ?>
            <div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded mb-4" role="alert">
                <?= $message ?>
            </div>
        <?php endif; ?>

        <?php if ($requirementsMet && empty($message)): ?>
            <form method="POST" action="" class="space-y-4">
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="app_url">App URL</label>
                    <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="app_url" name="app_url" type="text" placeholder="http://example.com/prizepacket" value="<?= isset($_POST['app_url']) ? htmlspecialchars($_POST['app_url']) : (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . dirname($_SERVER['PHP_SELF']) ?>">
                </div>

                <div class="border-t pt-4">
                    <h2 class="text-xl font-semibold mb-4 text-gray-800">Database Credentials</h2>

                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="db_host">Host</label>
                        <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="db_host" name="db_host" type="text" placeholder="127.0.0.1" value="<?= isset($_POST['db_host']) ? htmlspecialchars($_POST['db_host']) : '127.0.0.1' ?>">
                        <p class="text-xs text-gray-500 mt-1">Try '127.0.0.1' if 'localhost' fails.</p>
                    </div>

                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="db_name">Database Name</label>
                        <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="db_name" name="db_name" type="text" placeholder="prizepacket" value="<?= isset($_POST['db_name']) ? htmlspecialchars($_POST['db_name']) : '' ?>">
                    </div>

                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="db_user">User</label>
                        <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="db_user" name="db_user" type="text" placeholder="root" value="<?= isset($_POST['db_user']) ? htmlspecialchars($_POST['db_user']) : '' ?>">
                    </div>

                    <div class="mb-6">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="db_pass">Password</label>
                        <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 mb-3 leading-tight focus:outline-none focus:shadow-outline" id="db_pass" name="db_pass" type="password" placeholder="******************">
                    </div>
                </div>

                <div class="flex items-center justify-between">
                    <button class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline w-full transition duration-150 ease-in-out" type="submit">
                        Install PrizePacket
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>

</body>
</html>
