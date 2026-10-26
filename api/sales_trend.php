<?php
header('Content-Type: application/json; charset=utf-8');

$host = 'localhost';
$dbname = 'pharm_order_db';
$username = 'root';
$password = '123456';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['code' => 500, 'message' => '数据库连接失败', 'data' => null]);
    exit;
}

// 修改 SQL：按日期分组，取最近有数据的30天（不限日期范围）
$sql = "SELECT DATE(create_time) as date, 
               SUM(actual_pay) as total_sales, 
               COUNT(*) as order_count
        FROM order_info 
        GROUP BY DATE(create_time)
        ORDER BY date DESC
        LIMIT 30";

$stmt = $pdo->query($sql);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
// 反转数组，使日期从旧到新
$rows = array_reverse($rows);

echo json_encode([
    'code' => 200,
    'data' => $rows,
    'message' => 'success'
]);
?>