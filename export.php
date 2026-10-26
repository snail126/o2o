<?php
// export.php
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="orders_export.csv"');

// 输出BOM解决中文乱码
echo "\xEF\xBB\xBF";

$pdo = new PDO('mysql:host=localhost;dbname=pharm_order_db;charset=utf8', 'root', '123456');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// 查询订单主表和明细表，生成扁平数据（类似原始Excel）
$sql = "SELECT DATE_FORMAT(o.create_time, '%Y.%m.%d') AS order_date, 
               o.community AS address, 
               d.drug_name AS drug_name, 
               d.buy_num AS quantity, 
               d.unit_price AS unit_price, 
               (d.buy_num * d.unit_price) AS total_price
        FROM order_info o
        JOIN order_detail d ON o.order_id = d.order_id
        ORDER BY o.create_time, o.order_id";

$stmt = $pdo->query($sql);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 打开输出流
$output = fopen('php://output', 'w');
// 写入标题行
fputcsv($output, ['日期', '小区地址', '药品种类', '药品数量', '药品单价', '药品总价']);

foreach ($rows as $row) {
    fputcsv($output, $row);
}
fclose($output);
exit;
?>