<?php

class DBConnection {
    protected $db = null;
    protected $host;
    protected $port;
    protected $username;
    protected $password;
    protected $database;
    protected $use_ssl = false;
    public $error = null;

    function __construct(){
        // Disable mysqli throwing unhandled exceptions in PHP 8.1+
        if (function_exists('mysqli_report')) {
            mysqli_report(MYSQLI_REPORT_OFF);
        }

        // Check for URI style first (DATABASE_URL or MYSQL_URL)
        $db_url = getenv('DATABASE_URL') ?: getenv('MYSQL_URL');
        if(!empty($db_url)){
            $parsed = parse_url($db_url);
            $this->host = isset($parsed['host']) ? $parsed['host'] : 'localhost';
            $this->port = isset($parsed['port']) ? intval($parsed['port']) : 3306;
            $this->username = isset($parsed['user']) ? urldecode($parsed['user']) : 'root';
            $this->password = isset($parsed['pass']) ? urldecode($parsed['pass']) : '';
            $this->database = isset($parsed['path']) ? ltrim($parsed['path'], '/') : 'bsms_db';
            $this->use_ssl = true;
        } else {
            // Environment variables with fallbacks
            $this->host = getenv('DB_SERVER') ?: (getenv('DB_HOST') ?: 'localhost');
            $this->port = intval(getenv('DB_PORT') ?: 3306);
            $this->username = getenv('DB_USERNAME') ?: (getenv('DB_USER') ?: 'root');
            $this->password = getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : (getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
            $this->database = getenv('DB_NAME') ?: (getenv('DB_DATABASE') ?: 'bsms_db');
            
            // Check SSL setting: if DB_SSL is set, or if remote Aiven cloud host, default to true
            $ssl_env = getenv('DB_SSL') ?: getenv('MYSQL_SSL');
            if ($ssl_env !== false && $ssl_env !== '') {
                $this->use_ssl = in_array(strtolower((string)$ssl_env), ['true', '1', 'require', 'required', 'yes']);
            } else {
                // Auto-detect Aiven or remote port != 3306
                $this->use_ssl = (strpos($this->host, 'aivencloud.com') !== false || ($this->port !== 3306 && $this->port > 0)) && $this->host !== 'localhost' && $this->host !== '127.0.0.1';
            }
        }

        // Initialize mysqli
        $mysqli = mysqli_init();
        if (!$mysqli) {
            $this->error = "mysqli_init initialization failed";
            return;
        }

        // Set connection timeout
        $mysqli->options(MYSQLI_OPT_CONNECT_TIMEOUT, 10);

        // Aiven Cloud MySQL SSL encryption enforcement
        $client_flags = 0;
        if ($this->use_ssl) {
            $mysqli->ssl_set(NULL, NULL, NULL, NULL, NULL);
            if (defined('MYSQLI_OPT_SSL_VERIFY_SERVER_CERT')) {
                $mysqli->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);
            }
            $client_flags |= MYSQLI_CLIENT_SSL;
        }

        // Attempt connection safely with custom port and try/catch
        try {
            $connected = @$mysqli->real_connect(
                $this->host,
                $this->username,
                $this->password,
                $this->database,
                $this->port,
                null,
                $client_flags
            );

            if (!$connected || $mysqli->connect_errno) {
                $this->error = $mysqli->connect_error ?: "Could not connect to database on {$this->host}:{$this->port}";
                $this->db = null;
            } else {
                $this->db = $mysqli;
                $this->db->set_charset("utf8mb4");
                // Automatically ensure tables exist on connection
                $this->auto_import_db();
            }
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
            $this->db = null;
        }
    }

    public function create_tables_direct() {
        if (!$this->db || !($this->db instanceof mysqli) || $this->db->connect_errno) {
            return false;
        }

        @$this->db->query("SET SESSION sql_require_primary_key = 0;");
        @$this->db->query("SET FOREIGN_KEY_CHECKS = 0;");

        $queries = [
            "CREATE TABLE IF NOT EXISTS `category_list` (
              `category_id` int(30) NOT NULL AUTO_INCREMENT,
              `name` text NOT NULL,
              `description` text NOT NULL,
              `status` tinyint(1) NOT NULL DEFAULT 1,
              `delete_flag` tinyint(1) NOT NULL DEFAULT 0,
              `date_created` datetime NOT NULL DEFAULT current_timestamp(),
              `date_updated` datetime DEFAULT NULL ON UPDATE current_timestamp(),
              PRIMARY KEY (`category_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

            "CREATE TABLE IF NOT EXISTS `product_list` (
              `product_id` int(30) NOT NULL AUTO_INCREMENT,
              `product_code` varchar(100) NOT NULL,
              `category_id` int(30) NOT NULL,
              `name` text NOT NULL,
              `description` text NOT NULL,
              `price` double NOT NULL DEFAULT 0,
              `alert_restock` double NOT NULL DEFAULT 0,
              `status` tinyint(1) NOT NULL DEFAULT 1,
              `delete_flag` tinyint(1) NOT NULL DEFAULT 0,
              `date_created` datetime NOT NULL DEFAULT current_timestamp(),
              `date_updated` datetime DEFAULT NULL ON UPDATE current_timestamp(),
              PRIMARY KEY (`product_id`),
              KEY `category_id` (`category_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

            "CREATE TABLE IF NOT EXISTS `stock_list` (
              `stock_id` int(30) NOT NULL AUTO_INCREMENT,
              `product_id` int(30) NOT NULL,
              `quantity` double NOT NULL DEFAULT 0,
              `expiry_date` datetime NOT NULL,
              `date_added` timestamp NOT NULL DEFAULT current_timestamp(),
              PRIMARY KEY (`stock_id`),
              KEY `product_id` (`product_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

            "CREATE TABLE IF NOT EXISTS `transaction_list` (
              `transaction_id` int(30) NOT NULL AUTO_INCREMENT,
              `receipt_no` varchar(100) NOT NULL,
              `total` double NOT NULL DEFAULT 0,
              `tendered_amount` double NOT NULL DEFAULT 0,
              `change` double NOT NULL DEFAULT 0,
              `user_id` int(30) DEFAULT 1,
              `date_added` timestamp NOT NULL DEFAULT current_timestamp(),
              PRIMARY KEY (`transaction_id`),
              KEY `user_id` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

            "CREATE TABLE IF NOT EXISTS `transaction_items` (
              `item_id` int(30) NOT NULL AUTO_INCREMENT,
              `transaction_id` int(30) NOT NULL,
              `product_id` int(30) NOT NULL,
              `quantity` double NOT NULL DEFAULT 0,
              `price` double NOT NULL DEFAULT 0,
              `date_added` timestamp NOT NULL DEFAULT current_timestamp(),
              PRIMARY KEY (`item_id`),
              KEY `product_id` (`product_id`),
              KEY `transaction_id` (`transaction_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

            "CREATE TABLE IF NOT EXISTS `user_list` (
              `user_id` int(30) NOT NULL AUTO_INCREMENT,
              `fullname` varchar(255) NOT NULL,
              `username` varchar(100) NOT NULL,
              `password` varchar(255) NOT NULL,
              `type` int(30) NOT NULL DEFAULT 1,
              `status` tinyint(1) NOT NULL DEFAULT 1,
              `date_created` timestamp NOT NULL DEFAULT current_timestamp(),
              PRIMARY KEY (`user_id`),
              UNIQUE KEY `username` (`username`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

            "INSERT INTO `user_list` (`user_id`, `fullname`, `username`, `password`, `type`, `status`) 
             VALUES (1, 'Administrator', 'admin', '0192023a7bbd73250516f069df18b500', 1, 1) 
             ON DUPLICATE KEY UPDATE `password` = '0192023a7bbd73250516f069df18b500';"
        ];

        foreach ($queries as $q) {
            @$this->db->query($q);
        }

        @$this->db->query("SET FOREIGN_KEY_CHECKS = 1;");
        return true;
    }

    public function auto_import_db(){
        if (!$this->db || !($this->db instanceof mysqli) || $this->db->connect_errno) {
            return false;
        }

        // Quick check if user_list already exists
        $check = @$this->db->query("SHOW TABLES LIKE 'user_list'");
        if ($check && $check->num_rows > 0) {
            // Check if admin user exists in user_list
            $adm = @$this->db->query("SELECT user_id FROM `user_list` WHERE `username` = 'admin'");
            if ($adm && $adm->num_rows > 0) {
                return true;
            }
        }

        // Direct schema creation guaranteeing all tables and admin user exist
        $this->create_tables_direct();

        // Also import sample data from SQL dump if available
        $sql_file = __DIR__ . '/database/bsms_db.sql';
        if (file_exists($sql_file)) {
            $sql_content = file_get_contents($sql_file);
            if (!empty($sql_content)) {
                @$this->db->query("SET SESSION sql_require_primary_key = 0;");
                @$this->db->query("SET FOREIGN_KEY_CHECKS = 0;");
                @$this->db->query("SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';");

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
                foreach ($statements as $stmt) {
                    $stmt = trim($stmt);
                    if (!empty($stmt)) {
                        @$this->db->query($stmt);
                    }
                }
                @$this->db->query("SET FOREIGN_KEY_CHECKS = 1;");
            }
        }

        return true;
    }

    function db_connect(){
        return $this->db;
    }

    function is_connected(){
        return ($this->db !== null && $this->db instanceof mysqli && !$this->db->connect_errno);
    }

    function get_error(){
        return $this->error;
    }

    function __destruct(){
        if ($this->db && $this->db instanceof mysqli) {
            @$this->db->close();
        }
    }
}

function format_num($number = '',$decimal=''){
    if(is_numeric($number)){
        $ex = explode(".",(string)$number);
        $dec_len = isset($ex[1]) ? strlen($ex[1]) : 0;
        if(!empty($decimal) || is_numeric($decimal)){
            return number_format((float)$number,$decimal);
        }else{
            return number_format((float)$number,$dec_len);
        }
    }else{
        return '0';
    }
}

$db = new DBConnection();
$conn = $db->db_connect();
