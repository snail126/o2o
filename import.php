<?php
// import.php —— 增量导入（去重追加）· 重构版
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
set_time_limit(300);                       // 大文件保护

/* ============ 统一数据库入口（后续可抽到 db.php 复用） ============ */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $pdo = new PDO(
        'mysql:host=localhost;dbname=pharm_order_db;charset=utf8mb4',
        'root',
        '123456',
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
    return $pdo;
}

function respond(array $payload, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

/* ============ 1. 请求 & 文件校验 ============ */
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['csv_file'])) {
    respond(['success' => false, 'msg' => '未收到文件'], 400);
}
$file = $_FILES['csv_file'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    respond(['success' => false, 'msg' => '文件上传失败（code=' . $file['error'] . '）'], 400);
}
if ($file['size'] <= 0 || $file['size'] > 50 * 1024 * 1024) {
    respond(['success' => false, 'msg' => '文件为空或超过 50MB 限制'], 400);
}
if (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'csv') {
    respond(['success' => false, 'msg' => '只支持 CSV 文件'], 400);
}

/* ============ 2. 打开文件并剥离 UTF-8 BOM ============ */
$handle = fopen($file['tmp_name'], 'rb');
if (!$handle) respond(['success' => false, 'msg' => '无法打开文件'], 500);

$bom = fread($handle, 3);
if ($bom !== "\xEF\xBB\xBF") rewind($handle);       // 无 BOM 则回退

$header = fgetcsv($handle);                          // 丢弃标题行
if ($header === false) {
    fclose($handle);
    respond(['success' => false, 'msg' => 'CSV 为空'], 400);
}

/* ============ 3. 事务导入 ============ */
$pdo = db();
$pdo->beginTransaction();

$rowCount = 0;          // 有效数据行
$insertCount = 0;       // 新增订单数
$detailInsert = 0;      // 新增明细数
$skipCount = 0;         // 去重跳过的明细
$badRows = [];          // 格式异常行号

try {
    /* 3.1 订单主表：依赖 order_info.order_id 主键 + INSERT IGNORE */
    $stmtInfo = $pdo->prepare(
        "INSERT IGNORE INTO order_info
             (order_id, user_id, create_time, actual_pay, original_price,
              discount_amount, use_coupon, community, weather, temp_max, temp_min, is_holiday)
         VALUES
             (:order_id, :user_id, :create_time, :actual_pay, :original_price,
              0, 0, :community, NULL, NULL, NULL, 0)"
    );

    /* 3.2 订单明细：依赖 uk_order_drug(order_id, drug_name) + INSERT IGNORE */
    $stmtDetail = $pdo->prepare(
        "INSERT IGNORE INTO order_detail
             (order_id, drug_name, buy_num, unit_price, is_prescription)
         VALUES
             (:order_id, :drug_name, :buy_num, :unit_price, 0)"
    );

    /* 3.3 本次导入内存去重（避免同文件重复行反复查库） */
    $seenOrder   = [];  // order_id  => true
    $seenDetail  = [];  // "order_id|drug" => true
    $affectedCom = [];  // 本次涉及的 community，用于精准重置画像

    $lineNo = 1;
    while (($row = fgetcsv($handle)) !== false) {
        $lineNo++;

        // 跳过空行
        if (!array_filter($row, fn($v) => $v !== null && trim((string)$v) !== '')) continue;

        // 列数不足
        if (count($row) < 6) { $badRows[] = $lineNo; continue; }

        [$date, $address, $drugName, $quantity, $unitPrice] = array_map(
            'trim', array_slice($row, 0, 5)
        );

        // 类型与必填校验
        if ($address === '' || $drugName === '' || $date === ''
            || !is_numeric($quantity) || !is_numeric($unitPrice)
            || (int)$quantity < 0 || (float)$unitPrice < 0) {
            $badRows[] = $lineNo;
            continue;
        }
        $quantity  = (int)$quantity;
        $unitPrice = (float)$unitPrice;
        $date      = str_replace(['.', '/'], '-', $date);

        $orderId = md5($date . '|' . $address);   // 与原库 CHAR(32) 兼容
        $rowCount++;

        /* ---- 订单主表 ---- */
        if (!isset($seenOrder[$orderId])) {
            $stmtInfo->execute([
                ':order_id'       => $orderId,
                ':user_id'        => md5($address),
                ':create_time'    => $date,
                ':actual_pay'     => $unitPrice * $quantity,
                ':original_price' => $unitPrice * $quantity,
                ':community'      => $address,
            ]);
            if ($stmtInfo->rowCount() > 0) $insertCount++;      // 真正新增
            $seenOrder[$orderId]   = true;
            $affectedCom[$address] = true;
        }

        /* ---- 订单明细 ---- */
        $key = $orderId . '|' . $drugName;
        if (isset($seenDetail[$key])) { $skipCount++; continue; }

        $stmtDetail->execute([
            ':order_id'   => $orderId,
            ':drug_name'  => $drugName,
            ':buy_num'    => $quantity,
            ':unit_price' => $unitPrice,
        ]);
        if ($stmtDetail->rowCount() > 0) $detailInsert++;
        else                              $skipCount++;        // 跨次导入的重复
        $seenDetail[$key] = true;
    }

    /* ============ 4. user_info：UPSERT 代替 REPLACE（不消耗自增、可加外键） ============ */
    $pdo->exec("
        INSERT INTO user_info (user_id, first_buy_time, total_order_cnt, total_consume)
        SELECT MD5(community)             AS user_id,
               MIN(create_time)           AS first_buy_time,
               COUNT(*)                   AS total_order_cnt,
               COALESCE(SUM(actual_pay), 0) AS total_consume
        FROM order_info
        GROUP BY community
        ON DUPLICATE KEY UPDATE
            first_buy_time  = LEAST(first_buy_time, VALUES(first_buy_time)),
            total_order_cnt = VALUES(total_order_cnt),
            total_consume   = VALUES(total_consume)
    ");

    /* ============ 5. user_portrait：仅重置本次涉及用户（不再全表重置） ============ */
    if ($affectedCom) {
        $in = implode(',', array_fill(0, count($affectedCom), '?'));
        $sql = "REPLACE INTO user_portrait
                    (user_id, r_score, f_score, m_score, cluster_label, cluster_name, update_time)
                SELECT user_id, NULL, NULL, NULL, NULL, '待更新', NOW()
                FROM user_info
                WHERE user_id IN (
                    SELECT MD5(community) FROM order_info WHERE community IN ($in)
                )";
        $pdo->prepare($sql)->execute(array_keys($affectedCom));
    }

    $pdo->commit();

    /* ============ 6. 友好返回 ============ */
    $msg = "成功处理 {$rowCount} 行：新增订单 {$insertCount} 个，"
         . "新增明细 {$detailInsert} 条，跳过重复明细 {$skipCount} 条。";
    if ($badRows) {
        $msg .= ' 格式异常 ' . count($badRows) . ' 行（首行 ' . $badRows[0] . '）已忽略。';
    }

    respond([
        'success'       => true,
        'msg'           => $msg,
        'need_analysis' => ($insertCount + $detailInsert) > 0,   // 只有真新增才提示
    ]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    respond(['success' => false, 'msg' => '导入失败：' . $e->getMessage()], 500);
} finally {
    if (is_resource($handle)) fclose($handle);                // 句柄必关
}
