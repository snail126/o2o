<?php
// api/member_recall.php —— 会员召回清单接口
// 用法：
//   GET api/member_recall.php              → 返回流失预警客户召回清单（默认）
//   GET api/member_recall.php?label=X      → 返回指定分层的客户清单
// 每位客户包含：RFM 得分、分层名称、累计消费、最近一次购买时间、最常购买的药品
header('Content-Type: application/json; charset=utf-8');

$host = 'localhost';
$dbname = 'pharm_order_db';
$username = 'root';
$password = '123456';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['code' => 500, 'message' => '数据库连接失败', 'data' => null]);
    exit;
}

$label = isset($_GET['label']) ? trim($_GET['label']) : '流失预警型';

/* ---------- 客户基础信息 + 最近购买时间 ---------- */
$sql = "SELECT
            p.user_id,
            p.r_score, p.f_score, p.m_score,
            p.cluster_name,
            u.total_order_cnt,
            u.total_consume,
            u.first_buy_time,
            MAX(oi.create_time) AS last_buy_time
        FROM user_portrait p
        JOIN user_info u  ON u.user_id = p.user_id
        JOIN order_info oi ON oi.user_id = p.user_id
        WHERE p.cluster_name = :label
        GROUP BY p.user_id
        ORDER BY last_buy_time ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute([':label' => $label]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($rows)) {
    echo json_encode(['code' => 404, 'message' => '暂无该分层的客户数据', 'data' => null]);
    exit;
}

/* ---------- 逐位客户查最常购买的药品（按购买量取 Top1） ---------- */
foreach ($rows as &$r) {
    $s = $pdo->prepare(
        "SELECT od.drug_name, SUM(od.buy_num) AS total_buy
         FROM order_detail od
         JOIN order_info oi ON oi.order_id = od.order_id
         WHERE oi.user_id = :uid
         GROUP BY od.drug_name
         ORDER BY total_buy DESC
         LIMIT 1"
    );
    $s->execute([':uid' => $r['user_id']]);
    $top = $s->fetch(PDO::FETCH_ASSOC);
    $r['top_drug'] = $top ? $top['drug_name'] : '—';
    $r['top_drug_buy'] = $top ? (int)$top['total_buy'] : 0;
}
unset($r);

echo json_encode([
    'code' => 200,
    'data' => [
        'label' => $label,
        'count' => count($rows),
        'list'  => $rows
    ],
    'message' => 'success'
]);
