<?php
/**
 * 本文件功能: 学生登录页面
 * 作者信息: 15058593138@qq.com(手机号同微信)
 */

// 处理Ajax登录请求
if (isset($_GET['act']) && $_GET['act'] === 'login') {
    $usid = isset($_POST['usid']) ? trim($_POST['usid']) : '';
    $pass = isset($_POST['pass']) ? trim($_POST['pass']) : '';
    $code = isset($_POST['code']) ? trim($_POST['code']) : '';
    
    // 验证验证码
    if (!Code::check($code)) {
        jsonMsg(0, '验证码错误');
    }
    
    if (empty($usid) || empty($pass)) {
        jsonMsg(0, '学号和密码不能为空');
    }
    
    // 查询用户
    $db = new DB();
    $conn = $db->getConn();
    $usid = safeStr($usid, $conn);
    $pass = encryptPassword($pass);
    
    $sql = "SELECT * FROM `user` WHERE usid='{$usid}' AND pass='{$pass}'";
    $user = $db->getRow($sql);
    
    if (!$user) {
        jsonMsg(0, '学号或密码错误');
    }
    
    // 设置登录
    setLogin($user);
    jsonMsg(1, '登录成功', ['redirect' => 'Da.php?do=index']);
}

require_once './inc/code.php';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>学生登录</title>
    <link rel="stylesheet" href="./inc/pubs.css?v=<?php echo VERSION; ?>">
    <script src="./inc/pubs.js?v=<?php echo VERSION; ?>"></script>
</head>
<body>
    <div class="login-wrapper">
        <div class="login-box">
            <div class="login-title">学生答题系统</div>
            <form id="loginForm" onsubmit="return false;">
                <div class="form-group">
                    <label class="form-label">学号</label>
                    <input type="text" name="usid" class="form-control" placeholder="请输入学号" required>
                </div>
                <div class="form-group">
                    <label class="form-label">密码</label>
                    <input type="password" name="pass" class="form-control" placeholder="请输入密码" required>
                </div>
                <div class="form-group">
                    <label class="form-label">验证码</label>
                    <div style="display: flex; gap: 10px;">
                        <input type="text" name="code" class="form-control" placeholder="请输入验证码" required style="flex: 1;">
                        <img src="./inc/code.php?t=<?php echo time(); ?>" id="codeImg" style="height: 38px; cursor: pointer;" onclick="refreshCode()" title="点击刷新">
                    </div>
                </div>
                <button type="button" class="btn btn-primary" style="width: 100%;" onclick="doLogin()">登录</button>
            </form>
            <div style="margin-top: 20px; text-align: center;">
                <a href="Ti.php" style="color: #1890ff; text-decoration: none;">管理员入口</a>
            </div>
        </div>
    </div>
    
    <script>
    function refreshCode() {
        document.getElementById('codeImg').src = './inc/code.php?t=' + new Date().getTime();
    }
    
    function doLogin() {
        var data = getFormData('loginForm');
        if (!data.usid || !data.pass || !data.code) {
            toast('请填写完整信息');
            return;
        }
        
        ajax('Da.php?do=login&act=login', data, function(res) {
            if (res.code === 1) {
                toast(res.msg);
                setTimeout(function() {
                    location.href = res.data.redirect;
                }, 1000);
            } else {
                toast(res.msg);
                refreshCode();
            }
        });
    }
    
    // 回车登录
    document.getElementById('loginForm').addEventListener('keypress', function(e) {
        if (e.keyCode === 13) {
            doLogin();
        }
    });
    </script>
</body>
</html>
