<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$host = 'localhost';
$dbname = 'pharm_order_db';
$username = 'root';
$password = '123456';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['code' => 500, 'message' => '数据库连接失败']);
    exit;
}

$drug = isset($_GET['drug_name']) ? trim($_GET['drug_name']) : '';

if (empty($drug)) {
    echo json_encode(['code' => 400, 'message' => '缺少 drug_name 参数']);
    exit;
}

// 1. 先查询该药品的专属重要性
$sql = "SELECT feature_name, importance FROM drug_feature_importance WHERE drug_name = :drug ORDER BY importance DESC LIMIT 10";
$stmt = $pdo->prepare($sql);
$stmt->execute([':drug' => $drug]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!empty($rows)) {
    echo json_encode(['code' => 200, 'data' => $rows, 'message' => 'success']);
    exit;
}

// 2. 如果没有专属数据，回退到全局重要性（兜底）
$sqlGlobal = "SELECT feature_name, importance FROM feature_importance ORDER BY importance DESC LIMIT 10";
$stmtGlobal = $pdo->query($sqlGlobal);
$rowsGlobal = $stmtGlobal->fetchAll(PDO::FETCH_ASSOC);

if (empty($rowsGlobal)) {
    echo json_encode(['code' => 404, 'message' => '暂无该药品的重要性数据，且全局数据为空']);
    exit;
}

echo json_encode(['code' => 200, 'data' => $rowsGlobal, 'message' => 'success (使用全局重要性兜底)']);
?>