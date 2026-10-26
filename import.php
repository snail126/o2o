<?php
// import.php - 增量导入（去重追加）
header('Content-Type: application/json; charset=utf-8');

// 检查是否上传了文件
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['csv_file'])) {
    echo json_encode(['success' => false, 'msg' => '未收到文件']);
    exit;
}

$file = $_FILES['csv_file'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'msg' => '文件上传失败']);
    exit;
}

// 检查文件扩展名
$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
if (strtolower($ext) !== 'csv') {
    echo json_encode(['success' => false, 'msg' => '只支持CSV文件']);
    exit;
}

// 连接数据库
try {
    $pdo = new PDO('mysql:host=localhost;dbname=pharm_order_db;charset=utf8', 'root', '123456');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'msg' => '数据库连接失败：' . $e->getMessage()]);
    exit;
}

// 读取CSV并解析
$handle = fopen($file['tmp_name'], 'r');
if (!$handle) {
    echo json_encode(['success' => false, 'msg' => '无法打开文件']);
    exit;
}

// 跳过标题行
$header = fgetcsv($handle);
$expectedHeader = ['日期','小区地址','药品种类','药品数量','药品单价','药品总价'];
if ($header !== $expectedHeader) {
    // 不强制要求，但提醒
}

// 开启事务
$pdo->beginTransaction();

try {
    // ========== 1. 核心改动：不再清空旧数据（不再 DELETE） ==========
    // 注释或删除原有的 DELETE 语句
    // $pdo->exec('DELETE FROM order_detail');
    // $pdo->exec('DELETE FROM order_info');
    // $pdo->exec('DELETE FROM user_info');
    // $pdo->exec('DELETE FROM user_portrait');
    // ================================================================

    // 预编译 SQL（使用 INSERT IGNORE 防止主键冲突）
    $sqlInfo = "INSERT IGNORE INTO order_info (order_id, user_id, create_time, actual_pay, original_price, discount_amount, use_coupon, community, weather, temp_max, temp_min, is_holiday)
                VALUES (:order_id, :user_id, :create_time, :actual_pay, :original_price, 0, 0, :community, NULL, NULL, NULL, 0)";
    $stmtInfo = $pdo->prepare($sqlInfo);

    $sqlDetail = "INSERT INTO order_detail (order_id, drug_name, buy_num, unit_price, is_prescription)
                  VALUES (:order_id, :drug_name, :buy_num, :unit_price, 0)";
    $stmtDetail = $pdo->prepare($sqlDetail);

    // 用于检查 order_detail 是否已存在（去重）
    $sqlCheckDetail = "SELECT COUNT(*) FROM order_detail WHERE order_id = :order_id AND drug_name = :drug_name";
    $stmtCheckDetail = $pdo->prepare($sqlCheckDetail);

    $orderMap = []; // 用于在本次导入中，缓存已创建的订单ID，避免重复插入 order_info
    $rowCount = 0;
    $insertCount = 0; // 实际新增的订单数

    while (($row = fgetcsv($handle)) !== false) {
        if (empty(array_filter($row))) continue;
        $rowCount++;

        list($date, $address, $drugName, $quantity, $unitPrice, $totalPrice) = $row;
        $address = trim($address);
        $drugName = trim($drugName);
        $date = trim($date);
        $date = str_replace('.', '-', $date);
        
        // 生成 order_id: 日期+地址的MD5
        $orderId = md5($date . $address);
        
        // ===== 2. 处理 order_info（如果本次导入或数据库中已存在则跳过） =====
        if (!isset($orderMap[$orderId])) {
            // 检查数据库中是否已存在此订单（防止重复导入同个文件）
            $checkOrder = $pdo->prepare("SELECT COUNT(*) FROM order_info WHERE order_id = :oid");
            $checkOrder->execute([':oid' => $orderId]);
            $exists = $checkOrder->fetchColumn();
            
            if ($exists == 0) {
                // 插入新订单
                $userId = md5($address);
                $createTime = $date;
                $actualPay = $totalPrice;
                $originalPrice = $totalPrice;
                
                $stmtInfo->execute([
                    ':order_id' => $orderId,
                    ':user_id' => $userId,
                    ':create_time' => $createTime,
                    ':actual_pay' => $actualPay,
                    ':original_price' => $originalPrice,
                    ':community' => $address
                ]);
                $insertCount++;
            }
            // 标记已处理（无论是否存在，本次导入不再重复查询）
            $orderMap[$orderId] = true;
        }

        // ===== 3. 处理 order_detail（检查 (order_id, drug_name) 是否已存在） =====
        $stmtCheckDetail->execute([
            ':order_id' => $orderId,
            ':drug_name' => $drugName
        ]);
        $detailExists = $stmtCheckDetail->fetchColumn();
        
        if ($detailExists == 0) {
            // 不存在则插入新明细
            $stmtDetail->execute([
                ':order_id' => $orderId,
                ':drug_name' => $drugName,
                ':buy_num' => $quantity,
                ':unit_price' => $unitPrice
            ]);
        }
    }

    fclose($handle);

    // ===== 4. 更新 user_info（使用 REPLACE INTO 覆盖更新汇总数据） =====
    $sqlUser = "REPLACE INTO user_info (user_id, first_buy_time, total_order_cnt, total_consume)
                SELECT MD5(community) AS user_id, MIN(create_time) AS first_buy_time, COUNT(*) AS total_order_cnt, SUM(actual_pay) AS total_consume
                FROM order_info
                GROUP BY community";
    $pdo->exec($sqlUser);

    // ===== 5. 更新 user_portrait（新用户插入待更新状态，老用户重置为待更新） =====
    // 注意：这里使用 REPLACE INTO，如果用户已存在，则覆盖更新为待更新状态，触发前端重新生成
    $sqlPortrait = "REPLACE INTO user_portrait (user_id, r_score, f_score, m_score, cluster_label, cluster_name, update_time)
                    SELECT user_id, NULL, NULL, NULL, NULL, '待更新', NOW() FROM user_info";
    $pdo->exec($sqlPortrait);

    $pdo->commit();

    // 返回成功，并标记 need_analysis = true（让前端显示重新生成按钮）
    echo json_encode([
        'success' => true,
        'msg' => "成功追加导入 {$rowCount} 条记录（新增 {$insertCount} 个订单，重复数据已自动跳过）",
        'need_analysis' => true   // 关键字段，触发前端显示重新生成按钮
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'msg' => '导入失败：' . $e->getMessage()]);
    exit;
}
?>