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

$sql = "SELECT drug_name, 
               SUM(buy_num) as total_volume, 
               SUM(buy_num * unit_price) as total_sales
        FROM order_detail
        GROUP BY drug_name
        ORDER BY total_sales DESC
        LIMIT 10";

$stmt = $pdo->query($sql);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'code' => 200,
    'data' => $rows,
    'message' => 'success'
]);
?>