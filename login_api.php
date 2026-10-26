<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// 硬编码账号
$DEMO_USER = 'admin';
$DEMO_PASS = '123456';

// 获取原始请求体
$input = file_get_contents('php://input');
$data = json_decode($input, true);

$user = trim($data['username'] ?? '');
$pass = trim($data['password'] ?? '');

if ($user === $DEMO_USER && $pass === $DEMO_PASS) {
    echo json_encode(['success' => true, 'msg' => '登录成功', 'role' => 'admin']);
} else {
    echo json_encode(['success' => false, 'msg' => '用户名或密码错误']);
}
?>