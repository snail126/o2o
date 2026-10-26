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

// 1. 人数占比
$sqlRatio = "SELECT cluster_name, COUNT(*) as cnt FROM user_portrait GROUP BY cluster_name";
$stmtRatio = $pdo->query($sqlRatio);
$ratios = $stmtRatio->fetchAll(PDO::FETCH_ASSOC);

// 2. 雷达图数据（R/F/M 平均值）
$sqlRadar = "SELECT cluster_name, 
                    AVG(r_score) as r, 
                    AVG(f_score) as f, 
                    AVG(m_score) as m 
             FROM user_portrait 
             GROUP BY cluster_name";
$stmtRadar = $pdo->query($sqlRadar);
$radarData = $stmtRadar->fetchAll(PDO::FETCH_ASSOC);

// 计算总人数用于占比百分比
$total = array_sum(array_column($ratios, 'cnt'));
foreach ($ratios as &$r) {
    $r['percentage'] = $total > 0 ? round($r['cnt'] / $total * 100, 1) : 0;
}

echo json_encode([
    'code' => 200,
    'data' => [
        'ratio' => $ratios,
        'radar' => $radarData
    ],
    'message' => 'success'
]);
?>