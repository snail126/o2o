<?php
// 这是高璇的第一个后端测试接口
$data = [
    'message' => '后端连接成功！高璇，你太棒了！',
    'current_time' => date('Y-m-d H:i:s')
];

// 告诉浏览器返回的是 JSON 格式
header('Content-Type: application/json; charset=utf-8');
// 把 PHP 数组转成 JSON 字符串并输出
echo json_encode($data);
?>