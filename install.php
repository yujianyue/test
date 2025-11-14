<?php
/**
 * 本文件功能: 数据库安装页面
 * 作者信息: 15058593138@qq.com(手机号同微信)
 */

require_once './inc/conn.php';
require_once './inc/pubs.php';

// 处理Ajax请求
if (isset($_GET['act'])) {
    switch ($_GET['act']) {
        case 'install':
            install();
            break;
    }
    exit();
}

function install() {
    $importDemo = isset($_POST['import_demo']) ? intval($_POST['import_demo']) : 0;
    
    // 连接数据库
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS);
    if ($conn->connect_error) {
        jsonMsg(0, '数据库连接失败: ' . $conn->connect_error);
    }
    
    // 设置字符集
    $conn->set_charset(DB_CHARSET);
    
    // 创建数据库
    $dbName = DB_NAME;
    $sql = "CREATE DATABASE IF NOT EXISTS `{$dbName}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci";
    if (!$conn->query($sql)) {
        jsonMsg(0, '创建数据库失败: ' . $conn->error);
    }
    
    // 选择数据库
    $conn->select_db($dbName);
    
    // 创建用户表
    $sql = "CREATE TABLE IF NOT EXISTS `user` (
        `id` BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `usid` VARCHAR(50) NOT NULL UNIQUE COMMENT '学号',
        `name` VARCHAR(100) NOT NULL COMMENT '姓名',
        `pass` VARCHAR(255) NOT NULL COMMENT '密码',
        `ctime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '添加时间',
        `utime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '修改时间',
        INDEX `idx_usid` (`usid`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户表'";
    
    if (!$conn->query($sql)) {
        jsonMsg(0, '创建用户表失败: ' . $conn->error);
    }
    
    // 创建答题表
    $sql = "CREATE TABLE IF NOT EXISTS `recs` (
        `id` BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `ename` VARCHAR(200) NOT NULL COMMENT '考试名称',
        `epath` TEXT NOT NULL COMMENT '试题路径',
        `ptime` DATETIME NOT NULL COMMENT '路径时间',
        `usid` VARCHAR(50) NOT NULL COMMENT '学号',
        `stime` DATETIME NOT NULL COMMENT '开始时间',
        `ltime` DATETIME NOT NULL COMMENT '最后时间',
        `idati` TEXT NOT NULL COMMENT '答题记录',
        `jifen` TEXT NOT NULL COMMENT '计分明细',
        `score` DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT '得分',
        `ipadd` VARCHAR(45) COMMENT 'IP地址',
        INDEX `idx_usid` (`usid`),
        INDEX `idx_ename` (`ename`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='答题记录表'";
    
    if (!$conn->query($sql)) {
        jsonMsg(0, '创建答题表失败: ' . $conn->error);
    }
    
    // 插入默认管理员账号
    global $DEFAULT_ADMIN;
    foreach ($DEFAULT_ADMIN as $admin) {
        $usid = $conn->real_escape_string($admin['usid']);
        $name = $conn->real_escape_string($admin['name']);
        $pass = encryptPassword($admin['pass']);
        
        // 检查是否已存在
        $check = $conn->query("SELECT id FROM `user` WHERE usid='{$usid}'");
        if ($check->num_rows == 0) {
            $sql = "INSERT INTO `user` (usid, name, pass) VALUES ('{$usid}', '{$name}', '{$pass}')";
            $conn->query($sql);
        }
    }
    
    // 导入演示数据
    if ($importDemo) {
        // 插入36条演示用户数据
        for ($i = 1; $i <= 36; $i++) {
            $usid = 'S' . str_pad($i, 4, '0', STR_PAD_LEFT);
            $name = '学生' . $i;
            $pass = encryptPassword('123456');
            
            $check = $conn->query("SELECT id FROM `user` WHERE usid='{$usid}'");
            if ($check->num_rows == 0) {
                $sql = "INSERT INTO `user` (usid, name, pass) VALUES ('{$usid}', '{$name}', '{$pass}')";
                $conn->query($sql);
            }
        }
        
        // 插入一些演示答题记录
        for ($i = 1; $i <= 10; $i++) {
            $usid = 'S' . str_pad($i, 4, '0', STR_PAD_LEFT);
            $ename = '演示试卷';
            $epath = './Ku/demo.json';
            $ptime = date('Y-m-d H:i:s');
            $stime = date('Y-m-d H:i:s', time() - rand(3600, 7200));
            $ltime = date('Y-m-d H:i:s', time() - rand(0, 3600));
            
            $idati = json_encode([
                'q1' => 'A',
                'q2' => 'B',
                'q3' => ['A', 'B']
            ], JSON_UNESCAPED_UNICODE);
            
            $jifen = json_encode([
                'q1' => ['answer' => 'A', 'user' => 'A', 'score' => 10],
                'q2' => ['answer' => 'B', 'user' => 'C', 'score' => 0],
                'q3' => ['answer' => ['A', 'B', 'C'], 'user' => ['A', 'B'], 'score' => 5]
            ], JSON_UNESCAPED_UNICODE);
            
            $score = rand(60, 100);
            $ipadd = '127.0.0.1';
            
            $sql = "INSERT INTO `recs` (ename, epath, ptime, usid, stime, ltime, idati, jifen, score, ipadd) 
                    VALUES ('{$ename}', '{$epath}', '{$ptime}', '{$usid}', '{$stime}', '{$ltime}', '{$idati}', '{$jifen}', {$score}, '{$ipadd}')";
            $conn->query($sql);
        }
    }
    
    // 生成管理员账号缓存文件
    global $DEFAULT_ADMIN;
    $adminCache = "aaa:<?php exit();?>\n";
    foreach ($DEFAULT_ADMIN as $admin) {
        $adminCache .= $admin['usid'] . "\t" . $admin['name'] . "\t" . encryptPassword($admin['pass']) . "\n";
    }
    file_put_contents('./inc/json_user.php', $adminCache);
    
    $conn->close();
    jsonMsg(1, '安装成功！');
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>系统安装</title>
    <link rel="stylesheet" href="./inc/pubs.css?v=<?php echo VERSION; ?>">
    <script src="./inc/pubs.js?v=<?php echo VERSION; ?>"></script>
</head>
<body>
    <div class="login-wrapper">
        <div class="login-box">
            <div class="login-title">答题系统安装</div>
            
            <div class="card">
                <h3>环境检查</h3>
                <p>PHP版本: <strong><?php echo PHP_VERSION; ?></strong> <?php echo version_compare(PHP_VERSION, '7.0.0', '>=') ? '✓' : '✗'; ?></p>
                <p>MySQLi扩展: <strong><?php echo extension_loaded('mysqli') ? '已安装' : '未安装'; ?></strong> <?php echo extension_loaded('mysqli') ? '✓' : '✗'; ?></p>
                <p>GD库: <strong><?php echo extension_loaded('gd') ? '已安装' : '未安装'; ?></strong> <?php echo extension_loaded('gd') ? '✓' : '✗'; ?></p>
            </div>
            
            <div class="card">
                <h3>数据库配置</h3>
                <p>主机: <strong><?php echo DB_HOST; ?></strong></p>
                <p>用户名: <strong><?php echo DB_USER; ?></strong></p>
                <p>数据库名: <strong><?php echo DB_NAME; ?></strong></p>
            </div>
            
            <div class="form-group">
                <label>
                    <input type="checkbox" id="import_demo" checked> 导入演示数据（36条用户+10条答题记录）
                </label>
            </div>
            
            <button class="btn btn-primary" style="width: 100%;" onclick="doInstall()">开始安装</button>
            
            <div style="margin-top: 20px; font-size: 12px; color: #999;">
                <p>默认管理员账号: admin</p>
                <p>默认密码: 123456</p>
                <p>演示账号: S0001 ~ S0036, 密码: 123456</p>
            </div>
        </div>
    </div>
    
    <script>
    function doInstall() {
        var importDemo = document.getElementById('import_demo').checked ? 1 : 0;
        
        ajax('install.php?act=install', {import_demo: importDemo}, function(res) {
            if (res.code === 1) {
                toast(res.msg);
                setTimeout(function() {
                    location.href = 'Ti.php';
                }, 1500);
            } else {
                toast(res.msg);
            }
        });
    }
    </script>
</body>
</html>
