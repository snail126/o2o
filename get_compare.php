<?php
header('Content-Type: application/json; charset=utf-8');

$pdo = new PDO('mysql:host=localhost;dbname=pharm_order_db;charset=utf8', 'root', '123456');

$sql = "SELECT d.drug_name, 
               SUM(d.buy_num) AS total_quantity, 
               SUM(d.buy_num * d.unit_price) AS total_sales 
        FROM order_detail d
        GROUP BY d.drug_name 
        ORDER BY total_sales DESC 
        LIMIT 5";
$stmt = $pdo->query($sql);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$names = array_column($rows, 'drug_name');
$quantities = array_column($rows, 'total_quantity');
$sales = array_column($rows, 'total_sales');

echo json_encode(['names' => $names, 'quantities' => $quantities, 'sales' => $sales]);
?>