<?php
header('Content-Type: application/json; charset=utf-8');

$host = 'localhost';
$dbname = 'pharm_order_db';
$username = 'root';
$password = '123456';  // 如果密码不是空，请修改

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['code' => 500, 'message' => '数据库连接失败', 'data' => null]);
    exit;
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

// 如果请求的是药品列表
if ($action === 'list_drugs') {
    $sql = "SELECT DISTINCT drug_name FROM predict_result ORDER BY drug_name";
    $stmt = $pdo->query($sql);
    $drugs = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo json_encode(['code' => 200, 'data' => $drugs, 'message' => 'success']);
    exit;
}

// 否则是预测数据
$drugName = isset($_GET['drug_name']) ? trim($_GET['drug_name']) : '';

if (empty($drugName)) {
    echo json_encode(['code' => 400, 'message' => '缺少 drug_name 参数', 'data' => null]);
    exit;
}

$sql = "SELECT predict_date, predict_sales 
        FROM predict_result 
        WHERE drug_name = :drug_name 
        ORDER BY predict_date 
        LIMIT 7";
$stmt = $pdo->prepare($sql);
$stmt->execute([':drug_name' => $drugName]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 如果没有数据，返回空数组（不再返回随机数）
echo json_encode([
    'code' => 200,
    'data' => $rows,
    'message' => 'success'
]);
?>