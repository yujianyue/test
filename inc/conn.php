<?php
/**
 * 本文件功能: 公共配置文件，包含数据库连接信息和系统配置
 * 作者信息: 15058593138@qq.com(手机号同微信)
 */

// 数据库连接信息
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'quiz_system');
define('DB_CHARSET', 'utf8mb4');

// 数据表公共前缀
define('DB_PREFIX', '');

// 分页配置
define('PAGE_SIZE', 20); // 每页显示条数

// 版本号，用于JS/CSS缓存更新
define('VERSION', '1.0.0');

// 文件上传限制
define('MAX_FILE_SIZE', 2097152); // 2MB
define('MAX_IMAGE_WIDTH', 1920);
define('MAX_IMAGE_HEIGHT', 1080);

// 密码加密盐值
define('PASSWORD_SALT', 'Quiz@System#2024');

// 会话配置
define('SESSION_NAME', 'QUIZ_SYS');
define('SESSION_EXPIRE', 7200); // 2小时

// 菜单配置（根据用户类型显示不同菜单）
$MENUS = [
    'admin' => [
        ['name' => '用户列表', 'do' => 'iuser'],
        ['name' => '用户导入', 'do' => 'iusin'],
        ['name' => '答题记录', 'do' => 'ilist'],
        ['name' => '数据下载', 'do' => 'idown'],
        ['name' => '修改密码', 'do' => 'ipass']
    ],
    'user' => [
        ['name' => '开始答题', 'do' => 'index'],
        ['name' => '答题记录', 'do' => 'ilist'],
        ['name' => '修改密码', 'do' => 'ipass']
    ]
];

// 默认管理员账号（首次安装时写入）
$DEFAULT_ADMIN = [
    ['usid' => 'admin', 'name' => '管理员', 'pass' => '123456']
];

// 计分方案配置
define('SCORE_MODE_1', 1); // 答题模式1: 全对得分
define('SCORE_MODE_2', 2); // 调查模式: 顺序得分
define('SCORE_MODE_3', 3); // 答题模式2: 全对得分+多选逐项对比
