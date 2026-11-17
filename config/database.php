<?php
function get_db_env($keys, $default = null) {
    if (!is_array($keys)) {
        $keys = [$keys];
    }
    foreach ($keys as $key) {
        $val = getenv($key);
        if ($val !== false && $val !== null && $val !== '') {
            $val = trim($val);
            if ((str_starts_with($val, "'") && str_ends_with($val, "'")) || 
                (str_starts_with($val, '"') && str_ends_with($val, '"'))) {
                $val = substr($val, 1, -1);
            }
            return $val;
        }
    }
    return $default;
}

$host     = get_db_env(['DB_HOST'], "localhost");
$username = get_db_env(['DB_USER', 'DB_USERNAME'], "root");

$raw_pass = getenv('DB_PASS');
if ($raw_pass === false) {
    $raw_pass = getenv('DB_PASSWORD');
}
if ($raw_pass !== false && $raw_pass !== null) {
    $raw_pass = trim($raw_pass);
    if ((str_starts_with($raw_pass, "'") && str_ends_with($raw_pass, "'")) || 
        (str_starts_with($raw_pass, '"') && str_ends_with($raw_pass, '"'))) {
        $raw_pass = substr($raw_pass, 1, -1);
    }
    $password = $raw_pass;
} else {
    $password = "";
}

$database = get_db_env(['DB_NAME', 'DB_DATABASE'], (($host === "localhost" || $host === "127.0.0.1") ? "db_stunting" : "test"));
$port     = (int)get_db_env(['DB_PORT'], 3306);

$conn = mysqli_init();
if (!$conn) {
    http_response_code(500);
    die("Koneksi database gagal: mysqli_init gagal.");
}

$conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);

// Jika host bukan localhost (misalnya TiDB Cloud), gunakan koneksi SSL/TLS yang aman
if ($host !== 'localhost' && $host !== '127.0.0.1') {
    $caPaths = [
        __DIR__ . '/isrgrootx1.pem',
        __DIR__ . '/cacert.pem',
        '/etc/ssl/certs/ca-certificates.crt',
        '/etc/pki/tls/certs/ca-bundle.crt',
        '/etc/ssl/ca-bundle.pem'
    ];
    $caFile = null;
    foreach ($caPaths as $path) {
        if (file_exists($path)) {
            $caFile = $path;
            break;
        }
    }

    if ($caFile) {
        $conn->ssl_set(NULL, NULL, $caFile, NULL, NULL);
    } else {
        $conn->ssl_set(NULL, NULL, NULL, NULL, NULL);
    }

    // Tetap enkripsi TLS tetapi hindari penolakan jika sertifikat proxy cloud serverless menggunakan wildcard
    $conn->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);

    $connected = @$conn->real_connect($host, $username, $password, $database, $port, NULL, MYSQLI_CLIENT_SSL);
} else {
    $connected = @$conn->real_connect($host, $username, $password, $database, $port);
}

if (!$connected) {
    http_response_code(500);
    $err = $conn->connect_error ?: mysqli_connect_error();
    die("Koneksi database gagal. Periksa konfigurasi database Anda. Error: " . htmlspecialchars($err));
}

$conn->set_charset("utf8mb4");
