<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
ob_start();

date_default_timezone_set('Africa/Johannesburg');

$dbHost = '127.0.0.1';
$dbUser = 'root';
$dbPass = '';
$dbName = ' football_management';

$conn = mysqli_connect($dbHost, $dbUser, $dbPass, $dbName);
if (!$conn) {
    die('Database connection failed: ' . mysqli_connect_error());
}

mysqli_set_charset($conn, 'utf8mb4');

function db_query($sql, $types = '', $params = [])
{
    global $conn;

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return false;
    }

    if ($types !== '' && !empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }

    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return false;
    }

    return $stmt;
}

function db_fetch_all($sql, $types = '', $params = [])
{
    $stmt = db_query($sql, $types, $params);
    if (!$stmt) {
        return [];
    }

    $result = mysqli_stmt_get_result($stmt);
    $rows = $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
    mysqli_stmt_close($stmt);

    return $rows;
}

function db_fetch_one($sql, $types = '', $params = [])
{
    $rows = db_fetch_all($sql, $types, $params);
    return $rows[0] ?? null;
}

function db_execute($sql, $types = '', $params = [])
{
    $stmt = db_query($sql, $types, $params);
    if (!$stmt) {
        return false;
    }

    mysqli_stmt_close($stmt);
    return true;
}

function db_last_id()
{
    global $conn;
    return mysqli_insert_id($conn);
}

function table_columns($table)
{
    static $cache = [];

    if (isset($cache[$table])) {
        return $cache[$table];
    }

    $tableSafe = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $rows = db_fetch_all("SHOW COLUMNS FROM `{$tableSafe}`");

    $cache[$table] = array_map(static function ($row) {
        return $row['Field'];
    }, $rows);

    return $cache[$table];
}

function has_column($table, $column)
{
    return in_array($column, table_columns($table), true);
}

function pick_status_column($table)
{
    $candidates = ['status', 'statuss', 'ststuss', 'is_active'];
    foreach ($candidates as $column) {
        if (has_column($table, $column)) {
            return $column;
        }
    }

    return null;
}

function db_table_count($table, $where = '1=1', $types = '', $params = [])
{
    $tableSafe = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $row = db_fetch_one("SELECT COUNT(*) AS total FROM `{$tableSafe}` WHERE {$where}", $types, $params);

    return (int) ($row['total'] ?? 0);
}

