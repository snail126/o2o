<?php
header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = new PDO('mysql:host=localhost;dbname=pharm_order_db;charset=utf8', 'root', '123456');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['error' => '数据库连接失败：' . $e->getMessage()]);
    exit;
}

$sql = "SELECT drug_name, SUM(buy_num) AS total_sales 
        FROM order_detail 
        GROUP BY drug_name 
        ORDER BY total_sales DESC 
        LIMIT 50";
$stmt = $pdo->query($sql);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$drugs = array_column($rows, 'drug_name');
echo json_encode($drugs);
?>