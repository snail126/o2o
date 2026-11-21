<?php
// api/symptom_recommend.php —— 病症推荐接口（词典映射 + 关联扩展）
// 用法：
//   GET api/symptom_recommend.php                  → 返回全部可选病症列表
//   GET api/symptom_recommend.php?symptom=感冒流感  → 返回该病症的 主治药 + 搭配推荐
header('Content-Type: application/json; charset=utf-8');

$host = 'localhost';
$dbname = 'pharm_order_db';
$username = 'root';
$password = '926472';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['code' => 500, 'message' => '数据库连接失败', 'data' => null]);
    exit;
}

$symptom = isset($_GET['symptom']) ? trim($_GET['symptom']) : '';

/* ---------- 无参数：返回病症标签列表 ---------- */
if ($symptom === '') {
    $stmt = $pdo->query(
        "SELECT symptom, COUNT(*) AS drug_count
         FROM symptom_drug
         GROUP BY symptom
         ORDER BY symptom"
    );
    echo json_encode([
        'code' => 200,
        'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        'message' => 'success'
    ]);
    exit;
}

/* ---------- 有参数：第一级 · 词典查主治药 ---------- */
$sqlMain = "SELECT
                s.drug_name,
                s.reason,
                COALESCE((SELECT SUM(od.buy_num) FROM order_detail od
                          WHERE od.drug_name = s.drug_name), 0) AS sales,
                COALESCE((SELECT MAX(od.is_prescription) FROM order_detail od
                          WHERE od.drug_name = s.drug_name), 0) AS is_prescription
            FROM symptom_drug s
            WHERE s.symptom = :symptom
            ORDER BY sales DESC";
$stmtMain = $pdo->prepare($sqlMain);
$stmtMain->execute([':symptom' => $symptom]);
$mains = $stmtMain->fetchAll(PDO::FETCH_ASSOC);

if (empty($mains)) {
    echo json_encode(['code' => 404, 'message' => '暂无该病症的推荐数据', 'data' => null]);
    exit;
}

/* ---------- 第二级 · 共现关联扩展（搭配药） ---------- */
$mainNames = array_column($mains, 'drug_name');
$phMain = implode(',', array_fill(0, count($mainNames), '?'));

// 与任一主治药在同一订单中出现过的其他药品，按共同订单数排序
$sqlCombo = "SELECT
                d2.drug_name,
                COUNT(DISTINCT d1.order_id) AS co_orders,
                SUM(d2.buy_num) AS sales
             FROM order_detail d1
             JOIN order_detail d2
               ON d1.order_id = d2.order_id AND d2.drug_name <> d1.drug_name
             WHERE d1.drug_name IN ($phMain)
               AND d2.drug_name NOT IN ($phMain)
             GROUP BY d2.drug_name
             ORDER BY co_orders DESC, sales DESC
             LIMIT 5";
$stmtCombo = $pdo->prepare($sqlCombo);
$stmtCombo->execute(array_merge($mainNames, $mainNames));
$combos = $stmtCombo->fetchAll(PDO::FETCH_ASSOC);

// 为搭配药补上推荐理由
foreach ($combos as &$c) {
    $c['reason'] = "常与{$symptom}主治药同单购买（共同出现 {$c['co_orders']} 单）";
    $c['is_prescription'] = 0;
    $p = $pdo->prepare("SELECT MAX(is_prescription) FROM order_detail WHERE drug_name = ?");
    $p->execute([$c['drug_name']]);
    $c['is_prescription'] = (int)$p->fetchColumn();
}
unset($c);

echo json_encode([
    'code' => 200,
    'data' => [
        'symptom' => $symptom,
        'mains'   => $mains,   // 主推荐：词典映射 + 销量
        'combos'  => $combos   // 搭配推荐：共现关联扩展
    ],
    'message' => 'success'
]);
