<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json; charset=utf-8');

$host = 'localhost';
$dbname = 'pharm_order_db';
$username = 'root';
$password = '123456';  // 空密码

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['code' => 500, 'message' => '数据库连接失败', 'data' => null]);
    exit;
}

$sql = "SELECT 
            (SELECT COUNT(*) FROM order_info WHERE DATE(create_time) = CURDATE()) as today_orders,
            (SELECT SUM(actual_pay) FROM order_info WHERE MONTH(create_time) = MONTH(CURDATE())) as month_sales,
            (SELECT COUNT(DISTINCT user_id) FROM order_info) as active_users";

$stmt = $pdo->query($sql);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode([
    'code' => 200,
    'data' => [
        'today_orders' => (int)$row['today_orders'],
        'month_sales' => (float)$row['month_sales'],
        'active_users' => (int)$row['active_users']
    ],
    'message' => 'success'
]);
?>