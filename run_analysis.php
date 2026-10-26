<?php
header('Content-Type: application/json; charset=utf-8');
set_time_limit(0);

$pythonScript = 'D:\\xamppp\\htdocs\\o2o\\run_cluster.py';

if (!file_exists($pythonScript)) {
    echo json_encode(['success' => false, 'message' => 'Python 脚本文件不存在：' . $pythonScript]);
    exit;
}

// 执行 Python 命令（Windows）
$command = "python \"$pythonScript\" 2>&1";
exec($command, $output, $return_var);

if ($return_var === 0) {
    echo json_encode(['success' => true, 'message' => '重新生成完成！画像与预测已更新。']);
} else {
    // 返回错误信息（取最后几行）
    $errorMsg = implode("\n", array_slice($output, -5));
    echo json_encode(['success' => false, 'message' => '运行失败，错误信息：' . $errorMsg]);
}
?>