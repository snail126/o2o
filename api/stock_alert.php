<?php
// api/stock_alert.php —— 药品库存预警接口
// 用法：
//   GET api/stock_alert.php             → 默认假设当前库存 500 盒
//   GET api/stock_alert.php?stock=800   → 指定当前库存量
// 逻辑：取数据最新日期往前 30 天为"近30天"，再往前 30 天为"前30天"；
//       按近30天日均销量估算可售天数 = 当前库存 / 日均销量；
//       可售天数 <7 天标红（紧急备货），<15 天标黄（关注），其余正常。
//       同时给出近30天销量的环比涨幅，辅助判断需求趋势。
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

$stock = isset($_GET['stock']) ? max(1, (int)$_GET['stock']) : 500;

/* ---------- 数据最新日期，推算两个30天窗口 ---------- */
$end = $pdo->query("SELECT DATE(MAX(create_time)) FROM order_info")->fetchColumn();
if (!$end) {
    echo json_encode(['code' => 404, 'message' => '暂无订单数据', 'data' => null]);
    exit;
}
$s1 = date('Y-m-d', strtotime($end . ' -29 days')); // 近30天起点
$s2 = date('Y-m-d', strtotime($s1 . ' -30 days'));  // 前30天起点

/* ---------- 热销 Top30：近30天销量 + 前30天销量 ---------- */
$sql = "SELECT
    od.drug_name,
    SUM(CASE WHEN DATE(oi.create_time) >= :s1 THEN od.buy_num ELSE 0 END) AS recent30,
    SUM(CASE WHEN DATE(oi.create_time) >= :s2 AND DATE(oi.create_time) < :s1 THEN od.buy_num ELSE 0 END) AS prev30
FROM order_detail od
JOIN order_info oi ON oi.order_id = od.order_id
WHERE DATE(oi.create_time) >= :s2
GROUP BY od.drug_name
ORDER BY recent30 DESC
LIMIT 30";
$stmt = $pdo->prepare($sql);
$stmt->execute([':s1' => $s1, ':s2' => $s2]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ---------- 计算日均销量 / 可售天数 / 环比 / 预警级别 ---------- */
$list = [];
$urgent = 0; $watch = 0; $normal = 0;
foreach ($rows as $r) {
    $dailyAvg = round($r['recent30'] / 30, 2);
    $daysLeft = $dailyAvg > 0 ? floor($stock / $dailyAvg) : 999;
    $change   = $r['prev30'] > 0 ? round(($r['recent30'] - $r['prev30']) / $r['prev30'] * 100, 1) : null;

    if ($daysLeft < 7)       { $level = 'urgent'; $levelText = '🔴 紧急备货'; $urgent++; }
    elseif ($daysLeft < 15)  { $level = 'watch';  $levelText = '🟡 建议关注'; $watch++;  }
    else                     { $level = 'normal'; $levelText = '🟢 库存充足'; $normal++; }

    $list[] = [
        'drug_name'  => $r['drug_name'],
        'recent30'   => (int)$r['recent30'],
        'prev30'     => (int)$r['prev30'],
        'daily_avg'  => $dailyAvg,
        'days_left'  => $daysLeft,
        'change'     => $change,
        'level'      => $level,
        'level_text' => $levelText
    ];
}

echo json_encode([
    'code' => 200,
    'data' => [
        'stock'      => $stock,
        'window_end' => $end,
        'urgent'     => $urgent,
        'watch'      => $watch,
        'normal'     => $normal,
        'list'       => $list
    ],
    'message' => 'success'
]);
