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
            }
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
            $this->db = null;
        }
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
