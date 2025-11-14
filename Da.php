<?php
/**
 * 本文件功能: 学生答题页面主文件
 * 作者信息: 15058593138@qq.com(手机号同微信)
 */

require_once './inc/conn.php';
require_once './inc/pubs.php';
require_once './inc/sqls.php';

// 获取do参数
$do = isset($_GET['do']) ? $_GET['do'] : 'login';

// 默认登录页面和退出不需要登录
if (!in_array($do, ['login', 'lgout'])) {
    $user = checkLogin();
    if (!$user) {
        header('Location: Da.php?do=login');
        exit();
    }
}

// 如果do为空或login后已登录，显示index
if ($do === 'login') {
    $user = checkLogin();
    if ($user) {
        $do = 'index';
    }
}

// 加载对应的功能模块
$modulePath = './Da/' . $do . '.php';
if (file_exists($modulePath)) {
    require_once $modulePath;
} else {
    echo '页面不存在';
}
