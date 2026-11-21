<?php
// index.php —— 站点入口：访问根路径时强制进入登录页
// 配合 index.html 内的 sessionStorage 登录守卫，形成完整登录流程
header('Location: login.html');
exit;
