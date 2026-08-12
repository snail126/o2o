<?php
// 1. 设置数据库连接参数
$host = 'localhost';
$dbname = 'o2o_db';
$username = 'root';
$password = '123456'; // XAMPP 默认 root 密码为空

try {
    // 2. 创建 PDO 连接 (PHP 连接 MySQL 的标准方式)
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 3. 查询最近 7 天的日期和销售额
    $sql = "SELECT sale_date, amount FROM sales ORDER BY sale_date ASC LIMIT 7";
    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. 整理成前端 ECharts 需要的格式
    $dates = [];
    $amounts = [];
    foreach ($rows as $row) {
        $dates[] = $row['sale_date'];       // 比如 ['2026-08-06', ...]
        $amounts[] = (float)$row['amount']; // 比如 [8200, 9400, ...]
    }

    // 5. 返回 JSON 给前端
    header('Content-Type: application/json');
    echo json_encode([
        'dates' => $dates,
        'amounts' => $amounts
    ]);

} catch (PDOException $e) {
    // 如果出错，返回错误信息
    header('Content-Type: application/json');
    echo json_encode(['error' => '数据库连接失败: ' . $e->getMessage()]);
}
?>