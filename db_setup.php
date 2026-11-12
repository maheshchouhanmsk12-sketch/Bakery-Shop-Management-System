<?php
/**
 * 1-Click Aiven Cloud MySQL Database Setup & Health Check Tool
 * Bakery Shop Management System
 */

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

if (function_exists('mysqli_report')) {
    mysqli_report(MYSQLI_REPORT_OFF);
}

// Helper to get environment or POST values
function get_db_config() {
    $db_url = getenv('DATABASE_URL') ?: getenv('MYSQL_URL');
    $config = [
        'host' => 'localhost',
        'port' => 3306,
        'user' => 'root',
        'pass' => '',
        'name' => 'bsms_db',
        'ssl'  => false,
    ];

    if (!empty($db_url)) {
        $parsed = parse_url($db_url);
        $config['host'] = $parsed['host'] ?? 'localhost';
        $config['port'] = isset($parsed['port']) ? intval($parsed['port']) : 3306;
        $config['user'] = isset($parsed['user']) ? urldecode($parsed['user']) : 'root';
        $config['pass'] = isset($parsed['pass']) ? urldecode($parsed['pass']) : '';
        $config['name'] = isset($parsed['path']) ? ltrim($parsed['path'], '/') : 'bsms_db';
        $config['ssl']  = true;
    } else {
        $config['host'] = getenv('DB_SERVER') ?: (getenv('DB_HOST') ?: 'localhost');
        $config['port'] = intval(getenv('DB_PORT') ?: 3306);
        $config['user'] = getenv('DB_USERNAME') ?: (getenv('DB_USER') ?: 'root');
        $config['pass'] = getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : (getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
        $config['name'] = getenv('DB_NAME') ?: (getenv('DB_DATABASE') ?: 'bsms_db');
        
        $ssl_env = getenv('DB_SSL') ?: getenv('MYSQL_SSL');
        if ($ssl_env !== false && $ssl_env !== '') {
            $config['ssl'] = in_array(strtolower((string)$ssl_env), ['true', '1', 'require', 'required', 'yes']);
        } else {
            $config['ssl'] = (strpos($config['host'], 'aivencloud.com') !== false || ($config['port'] !== 3306 && $config['port'] > 0)) && $config['host'] !== 'localhost' && $config['host'] !== '127.0.0.1';
        }
    }

    // Allow session or POST override for interactive configuration
    if (isset($_POST['host'])) {
        $config['host'] = trim($_POST['host']);
        $config['port'] = intval($_POST['port']);
        $config['user'] = trim($_POST['user']);
        $config['pass'] = $_POST['pass'];
        $config['name'] = trim($_POST['name']);
        $config['ssl']  = isset($_POST['ssl']);
        $_SESSION['custom_db_config'] = $config;
    } elseif (isset($_SESSION['custom_db_config'])) {
        $config = $_SESSION['custom_db_config'];
    }

    return $config;
}

$cfg = get_db_config();
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$message = '';
$message_type = 'info';

// Database connection factory with SSL
function get_db_connection($cfg, &$err = null) {
    if (function_exists('mysqli_report')) {
        mysqli_report(MYSQLI_REPORT_OFF);
    }

    $mysqli = mysqli_init();
    if (!$mysqli) {
        $err = "mysqli_init failed";
        return null;
    }
    $mysqli->options(MYSQLI_OPT_CONNECT_TIMEOUT, 6);

    $flags = 0;
    if ($cfg['ssl']) {
        $mysqli->ssl_set(NULL, NULL, NULL, NULL, NULL);
        if (defined('MYSQLI_OPT_SSL_VERIFY_SERVER_CERT')) {
            $mysqli->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);
        }
        $flags |= MYSQLI_CLIENT_SSL;
    }

    try {
        $connected = @$mysqli->real_connect(
            $cfg['host'],
            $cfg['user'],
            $cfg['pass'],
            $cfg['name'],
            $cfg['port'],
            null,
            $flags
        );

        if (!$connected || $mysqli->connect_errno) {
            $err = $mysqli->connect_error ?: "Failed to connect to MySQL on {$cfg['host']}:{$cfg['port']}";
            return null;
        }

        $mysqli->set_charset("utf8mb4");
        return $mysqli;
    } catch (\Throwable $e) {
        $err = $e->getMessage();
        return null;
    }
}

// Handle Import Action
if ($action === 'import') {
    $sql_file = __DIR__ . '/database/bsms_db.sql';
    if (!file_exists($sql_file)) {
        $message = "Database dump file not found at: database/bsms_db.sql";
        $message_type = "danger";
    } else {
        $conn_err = null;
        $mysqli = get_db_connection($cfg, $conn_err);
        if (!$mysqli) {
            $message = "Cannot import. Database connection failed: " . htmlspecialchars($conn_err);
            $message_type = "danger";
        } else {
            $sql_content = file_get_contents($sql_file);
            
            @$mysqli->query("SET SESSION sql_require_primary_key = 0;");
            @$mysqli->query("SET FOREIGN_KEY_CHECKS = 0;");
            @$mysqli->query("SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';");
            
            $lines = explode("\n", $sql_content);
            $clean_sql = "";
            foreach ($lines as $line) {
                $trimmed = trim($line);
                if (empty($trimmed) || str_starts_with($trimmed, '--') || str_starts_with($trimmed, '/*') || str_starts_with($trimmed, '//')) {
                    continue;
                }
                $clean_sql .= $line . "\n";
            }

            $statements = preg_split('/;\s*[\r\n]+/', $clean_sql);
            $success_count = 0;
            $error_count = 0;
            $errors = [];

            foreach ($statements as $stmt) {
                $stmt = trim($stmt);
                if (!empty($stmt)) {
                    if (@$mysqli->query($stmt)) {
                        $success_count++;
                    } else {
                        $error_count++;
                        if (count($errors) < 5) {
                            $errors[] = $mysqli->error;
                        }
                    }
                }
            }

            $mysqli->query("SET FOREIGN_KEY_CHECKS = 1;");

            $message = "Import completed! Successfully executed {$success_count} queries." . ($error_count > 0 ? " ({$error_count} warnings/errors bypassed)" : "");
            $message_type = ($error_count > 0 && $success_count === 0) ? "danger" : "success";
            $mysqli->close();
        }
    }
}

// Handle Create Admin Action
if ($action === 'create_admin') {
    $conn_err = null;
    $mysqli = get_db_connection($cfg, $conn_err);
    if (!$mysqli) {
        $message = "Connection failed: " . htmlspecialchars($conn_err);
        $message_type = "danger";
    } else {
        $username = 'admin';
        $password = md5('admin123');
        $fullname = 'Administrator';
        
        $chk = @$mysqli->query("SELECT user_id FROM user_list WHERE username = 'admin'");
        if ($chk && $chk->num_rows > 0) {
            $mysqli->query("UPDATE user_list SET `password` = '{$password}', `type` = 1, `status` = 1 WHERE username = 'admin'");
            $message = "Admin user password reset to 'admin123' successfully!";
            $message_type = "success";
        } else {
            $ins = @$mysqli->query("INSERT INTO user_list (fullname, username, password, type, status) VALUES ('{$fullname}', '{$username}', '{$password}', 1, 1)");
            if ($ins) {
                $message = "Admin user ('admin' / 'admin123') created successfully!";
                $message_type = "success";
            } else {
                $message = "Failed to create admin user: " . $mysqli->error;
                $message_type = "danger";
            }
        }
        $mysqli->close();
    }
}

// Run Health Check
$health = [
    'connected' => false,
    'error' => null,
    'version' => 'N/A',
    'ssl_cipher' => 'None',
    'tables' => [],
    'admin_status' => 'Unknown',
];

$test_err = null;
$check_conn = get_db_connection($cfg, $test_err);
if ($check_conn) {
    $health['connected'] = true;
    $health['version'] = $check_conn->server_info;
    
    $ssl_res = @$check_conn->query("SHOW STATUS LIKE 'Ssl_cipher'");
    if ($ssl_res && $row = $ssl_res->fetch_assoc()) {
        $health['ssl_cipher'] = !empty($row['Value']) ? $row['Value'] : 'Encrypted (SSL Active)';
    }

    $req_tables = ['category_list', 'product_list', 'stock_list', 'transaction_items', 'transaction_list', 'user_list'];
    foreach ($req_tables as $tbl) {
        $res = @$check_conn->query("SELECT COUNT(*) as cnt FROM `{$tbl}`");
        if ($res && $row = $res->fetch_assoc()) {
            $health['tables'][$tbl] = [
                'exists' => true,
                'count' => intval($row['cnt'])
            ];
        } else {
            $health['tables'][$tbl] = [
                'exists' => false,
                'count' => 0
            ];
        }
    }

    $adm_res = @$check_conn->query("SELECT user_id, username FROM user_list WHERE username = 'admin' LIMIT 1");
    if ($adm_res && $adm_res->num_rows > 0) {
        $health['admin_status'] = 'Found (admin / admin123)';
    } else {
        $health['admin_status'] = 'Missing';
    }

    $check_conn->close();
} else {
    $health['error'] = $test_err;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aiven MySQL Database Setup & Health Check | Bakery Shop</title>
    <link rel="stylesheet" href="./css/bootstrap.min.css">
    <link rel="stylesheet" href="./Font-Awesome-master/css/all.min.css">
    <style>
        body {
            background: #0f172a;
            color: #e2e8f0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            min-height: 100vh;
        }
        .card {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 0.75rem;
        }
        .card-header {
            background: #1e293b;
            border-bottom: 1px solid #334155;
        }
        .form-control, .form-select {
            background-color: #0f172a;
            border: 1px solid #334155;
            color: #e2e8f0;
        }
        .form-control:focus {
            background-color: #0f172a;
            border-color: #38bdf8;
            color: #ffffff;
            box-shadow: 0 0 0 0.25rem rgba(56, 189, 248, 0.25);
        }
        .badge-success { background-color: #10b981; }
        .badge-danger { background-color: #ef4444; }
        .badge-warning { background-color: #f59e0b; color: #000; }
        .badge-info { background-color: #0ea5e9; }
        .table-dark {
            --bs-table-bg: #1e293b;
            --bs-table-border-color: #334155;
        }
        .nav-brand-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: #38bdf8;
        }
    </style>
</head>
<body class="py-4">
    <div class="container">
        <!-- Header -->
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 pb-2 border-bottom border-secondary">
            <div>
                <span class="nav-brand-title"><i class="fa fa-database me-2"></i>Aiven Cloud MySQL & Render Setup Tool</span>
                <p class="text-secondary small mb-0">Bakery Shop Management System</p>
            </div>
            <div class="mt-2 mt-md-0">
                <a href="login.php" class="btn btn-outline-info btn-sm me-2"><i class="fa fa-sign-in-alt me-1"></i> Go to Login</a>
                <a href="index.php" class="btn btn-outline-light btn-sm"><i class="fa fa-home me-1"></i> App Home</a>
            </div>
        </div>

        <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show shadow-sm" role="alert">
            <strong><?php echo ($message_type === 'success' ? 'Success:' : 'Notice:'); ?></strong> <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- Left Column: Health Check & Table Status -->
            <div class="col-lg-7">
                <!-- Health Card -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center py-3">
                        <h5 class="mb-0 text-light"><i class="fa fa-heartbeat me-2 text-danger"></i>Connection Health Check</h5>
                        <?php if ($health['connected']): ?>
                            <span class="badge badge-success px-3 py-2"><i class="fa fa-check-circle me-1"></i> CONNECTED</span>
                        <?php else: ?>
                            <span class="badge badge-danger px-3 py-2"><i class="fa fa-times-circle me-1"></i> DISCONNECTED</span>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <small class="text-secondary d-block">Host / Server</small>
                                <span class="fw-bold"><?php echo htmlspecialchars($cfg['host']); ?></span>
                            </div>
                            <div class="col-sm-3">
                                <small class="text-secondary d-block">Port</small>
                                <span class="fw-bold"><?php echo htmlspecialchars((string)$cfg['port']); ?></span>
                            </div>
                            <div class="col-sm-3">
                                <small class="text-secondary d-block">Database</small>
                                <span class="fw-bold text-info"><?php echo htmlspecialchars($cfg['name']); ?></span>
                            </div>
                            <div class="col-sm-6">
                                <small class="text-secondary d-block">MySQL Server Version</small>
                                <span class="fw-bold"><?php echo htmlspecialchars($health['version']); ?></span>
                            </div>
                            <div class="col-sm-6">
                                <small class="text-secondary d-block">SSL Encryption Status</small>
                                <?php if ($cfg['ssl']): ?>
                                    <span class="badge badge-info"><i class="fa fa-lock me-1"></i> SSL Enforced (<?php echo htmlspecialchars($health['ssl_cipher']); ?>)</span>
                                <?php else: ?>
                                    <span class="badge badge-warning">Plain (SSL Disabled)</span>
                                <?php endif; ?>
                            </div>
                            <?php if (!$health['connected']): ?>
                            <div class="col-12">
                                <div class="alert alert-danger mb-0 py-2 small">
                                    <strong>Connection Error:</strong> <?php echo htmlspecialchars($health['error'] ?? 'Unknown error'); ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Database Tables & Records Card -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center py-3">
                        <h5 class="mb-0 text-light"><i class="fa fa-table me-2 text-primary"></i>Database Tables Verification</h5>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Execute 1-Click database import from database/bsms_db.sql?');">
                            <input type="hidden" name="action" value="import">
                            <button type="submit" class="btn btn-primary btn-sm px-3" <?php echo !$health['connected'] ? 'disabled' : ''; ?>>
                                <i class="fa fa-file-import me-1"></i> 1-Click Import DB
                            </button>
                        </form>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-dark table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th class="ps-3">Table Name</th>
                                        <th>Status</th>
                                        <th class="text-end pe-3">Rows Count</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($health['tables'])): ?>
                                        <?php foreach ($health['tables'] as $table => $data): ?>
                                        <tr>
                                            <td class="ps-3 fw-bold"><code><?php echo htmlspecialchars($table); ?></code></td>
                                            <td>
                                                <?php if ($data['exists']): ?>
                                                    <span class="badge badge-success"><i class="fa fa-check me-1"></i> Ready</span>
                                                <?php else: ?>
                                                    <span class="badge badge-danger"><i class="fa fa-times me-1"></i> Missing</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end pe-3">
                                                <?php echo $data['exists'] ? number_format($data['count']) : '-'; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="3" class="text-center py-3 text-secondary">Connect database to view table status</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer d-flex justify-content-between align-items-center py-2 bg-transparent border-secondary">
                        <span class="small text-secondary">Admin User: <strong class="<?php echo $health['admin_status'] !== 'Missing' ? 'text-success' : 'text-warning'; ?>"><?php echo htmlspecialchars($health['admin_status']); ?></strong></span>
                        <?php if ($health['connected'] && $health['admin_status'] === 'Missing'): ?>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="action" value="create_admin">
                            <button type="submit" class="btn btn-warning btn-sm py-0">Create Admin</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right Column: Settings & Quick Login -->
            <div class="col-lg-5">
                <!-- Quick Credentials / Go To Login -->
                <div class="card shadow-sm mb-4 border-info">
                    <div class="card-header bg-transparent border-info py-3">
                        <h5 class="mb-0 text-info"><i class="fa fa-key me-2"></i>Default Credentials</h5>
                    </div>
                    <div class="card-body">
                        <p class="small text-secondary mb-2">Once imported, login with default admin credentials:</p>
                        <div class="p-3 rounded bg-black bg-opacity-50 mb-3 border border-secondary">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-secondary">Username:</span>
                                <strong class="text-light">admin</strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="text-secondary">Password:</span>
                                <strong class="text-light">admin123</strong>
                            </div>
                        </div>
                        <a href="login.php" class="btn btn-success w-100 py-2 fw-bold shadow-sm">
                            <i class="fa fa-sign-in-alt me-2"></i> Open Application Login
                        </a>
                    </div>
                </div>

                <!-- Database Configuration / Override Form -->
                <div class="card shadow-sm">
                    <div class="card-header py-3">
                        <h5 class="mb-0 text-light"><i class="fa fa-sliders-h me-2 text-warning"></i>Connection Parameters</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-2">
                                <label class="form-label small text-secondary mb-1">DB Server (Host)</label>
                                <input type="text" name="host" class="form-control form-control-sm" value="<?php echo htmlspecialchars($cfg['host']); ?>" required>
                            </div>
                            <div class="row g-2 mb-2">
                                <div class="col-6">
                                    <label class="form-label small text-secondary mb-1">Port</label>
                                    <input type="number" name="port" class="form-control form-control-sm" value="<?php echo htmlspecialchars((string)$cfg['port']); ?>" required>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small text-secondary mb-1">Database Name</label>
                                    <input type="text" name="name" class="form-control form-control-sm" value="<?php echo htmlspecialchars($cfg['name']); ?>" required>
                                </div>
                            </div>
                            <div class="mb-2">
                                <label class="form-label small text-secondary mb-1">Username</label>
                                <input type="text" name="user" class="form-control form-control-sm" value="<?php echo htmlspecialchars($cfg['user']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small text-secondary mb-1">Password</label>
                                <input type="password" name="pass" class="form-control form-control-sm" value="<?php echo htmlspecialchars($cfg['pass']); ?>" placeholder="Enter password">
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="ssl" id="sslCheck" <?php echo $cfg['ssl'] ? 'checked' : ''; ?>>
                                <label class="form-check-label small" for="sslCheck">
                                    Enable SSL Encryption (Required for Aiven)
                                </label>
                            </div>
                            <button type="submit" class="btn btn-outline-primary btn-sm w-100">
                                <i class="fa fa-sync-alt me-1"></i> Update & Test Connection
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
