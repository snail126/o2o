<?php
// api/monthly_report.php —— 月度经营简报接口（一键生成文字版）
// 用法：
//   GET api/monthly_report.php → 返回数据最新月份的经营简报（含环比、Top10药品、激增榜）
// 说明：以订单数据本身的最新月份为"本月"（数据停在 2025-10 时，简报即 2025-10），
//       不用系统当前时间，避免演示数据与真实日期错位导致统计为 0。
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

/* ---------- 确定数据最新月份与上月 ---------- */
$lastDay = $pdo->query("SELECT MAX(create_time) FROM order_info")->fetchColumn();
if (!$lastDay) {
    echo json_encode(['code' => 404, 'message' => '暂无订单数据', 'data' => null]);
    exit;
}
$curMonth  = date('Y-m', strtotime($lastDay));
// 注意：不能直接用月底日期 -1 month（12-31 会溢出成 12-01），
// 先取本月 1 号再减一个月，保证拿到真正的上月
$prevMonth = date('Y-m', strtotime($curMonth . '-01 -1 month'));

/* ---------- 月度核心指标（销售额 / 订单数 / 客单价） ---------- */
$sqlKpi = "SELECT
    COALESCE(SUM(actual_pay), 0) AS sales,
    COUNT(*) AS orders,
    COUNT(DISTINCT user_id) AS users
FROM order_info
WHERE DATE_FORMAT(create_time, '%Y-%m') = :m";
$stmt = $pdo->prepare($sqlKpi);

$stmt->execute([':m' => $curMonth]);
$cur = $stmt->fetch(PDO::FETCH_ASSOC);
$stmt->execute([':m' => $prevMonth]);
$prev = $stmt->fetch(PDO::FETCH_ASSOC);

$curSales  = (float)$cur['sales'];   $prevSales  = (float)$prev['sales'];
$curOrders = (int)$cur['orders'];    $prevOrders = (int)$prev['orders'];
$curUsers  = (int)$cur['users'];
$curAvg    = $curOrders  > 0 ? $curSales  / $curOrders  : 0;
$prevAvg   = $prevOrders > 0 ? $prevSales / $prevOrders : 0;

$salesChange  = $prevSales  > 0 ? ($curSales  - $prevSales)  / $prevSales  * 100 : 0;
$ordersChange = $prevOrders > 0 ? ($curOrders - $prevOrders) / $prevOrders * 100 : 0;
$avgChange    = $prevAvg    > 0 ? ($curAvg    - $prevAvg)    / $prevAvg    * 100 : 0;

/* ---------- Top10 药品（本月销量 + 环比） ---------- */
$sqlTop = "SELECT
    od.drug_name,
    SUM(od.buy_num) AS cur_num,
    SUM(od.buy_num * od.unit_price) AS cur_amount
FROM order_detail od
JOIN order_info oi ON oi.order_id = od.order_id
WHERE DATE_FORMAT(oi.create_time, '%Y-%m') = :m
GROUP BY od.drug_name
ORDER BY cur_num DESC
LIMIT 10";
$stmt = $pdo->prepare($sqlTop);
$stmt->execute([':m' => $curMonth]);
$top10 = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 补上月销量，算环比
$sqlPrevNum = "SELECT COALESCE(SUM(od.buy_num), 0)
    FROM order_detail od
    JOIN order_info oi ON oi.order_id = od.order_id
    WHERE DATE_FORMAT(oi.create_time, '%Y-%m') = :m AND od.drug_name = :d";
$stmtPrev = $pdo->prepare($sqlPrevNum);
foreach ($top10 as &$t) {
    $stmtPrev->execute([':m' => $prevMonth, ':d' => $t['drug_name']]);
    $prevNum = (float)$stmtPrev->fetchColumn();
    $t['prev_num'] = $prevNum;
    $t['change']   = $prevNum > 0 ? round(($t['cur_num'] - $prevNum) / $prevNum * 100, 1) : null;
}
unset($t);

/* ---------- 销量激增榜（本月销量≥20盒，环比涨幅前5） ---------- */
$sqlRise = "SELECT
    od.drug_name,
    SUM(CASE WHEN DATE_FORMAT(oi.create_time, '%Y-%m') = :cur  THEN od.buy_num ELSE 0 END) AS cur_num,
    SUM(CASE WHEN DATE_FORMAT(oi.create_time, '%Y-%m') = :prev THEN od.buy_num ELSE 0 END) AS prev_num
FROM order_detail od
JOIN order_info oi ON oi.order_id = od.order_id
GROUP BY od.drug_name
HAVING cur_num >= 20 AND prev_num > 0
ORDER BY (cur_num - prev_num) / prev_num DESC
LIMIT 5";
$stmt = $pdo->prepare($sqlRise);
$stmt->execute([':cur' => $curMonth, ':prev' => $prevMonth]);
$rising = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($rising as &$r) {
    $r['change'] = round(($r['cur_num'] - $r['prev_num']) / $r['prev_num'] * 100, 1);
}
unset($r);

/* ---------- 生成中文简报 ---------- */
$fmt = function ($n) { return number_format($n, 1); };
$arrow = function ($v) { return $v >= 0 ? "上升" . round($v, 1) . "%" : "下降" . round(abs($v), 1) . "%"; };

$report = "【{$curMonth} 月度经营简报】\n";
$report .= "本月销售额 {$fmt($curSales)} 元，环比{$arrow($salesChange)}；成交订单 {$curOrders} 单，环比{$arrow($ordersChange)}；客单价 {$fmt($curAvg)} 元，环比{$arrow($avgChange)}；消费客户 {$curUsers} 人。\n";
if (!empty($top10)) {
    $t1 = $top10[0];
    $report .= "销量冠军为「{$t1['drug_name']}」，本月售出 {$t1['cur_num']} 盒。";
}
if (!empty($rising)) {
    $names = [];
    foreach ($rising as $r) { $names[] = "{$r['drug_name']}（+{$r['change']}%）"; }
    $report .= "值得关注的增长品类：" . implode("、", $names) . "，建议结合库存与促销策略重点跟进。";
}
if ($salesChange < 0) {
    $report .= "本月销售环比下滑，建议加强会员召回与复购引导。";
} else {
    $report .= "整体经营稳中有进，可延续当前营销节奏。";
}

echo json_encode([
    'code' => 200,
    'data' => [
        'month'        => $curMonth,
        'prev_month'   => $prevMonth,
        'cur_sales'    => round($curSales, 2),
        'cur_orders'   => $curOrders,
        'cur_users'    => $curUsers,
        'cur_avg'      => round($curAvg, 2),
        'sales_change'  => round($salesChange, 1),
        'orders_change' => round($ordersChange, 1),
        'avg_change'    => round($avgChange, 1),
        'top10'   => $top10,
        'rising'  => $rising,
        'report'  => $report
    ],
    'message' => 'success'
]);
