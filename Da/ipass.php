<?php
/**
 * 本文件功能: 修改密码
 * 作者信息: 15058593138@qq.com(手机号同微信)
 */

// 处理Ajax请求
if (isset($_GET['act'])) {
    switch ($_GET['act']) {
        case 'change':
            changePassword();
            break;
    }
    exit();
}

function changePassword() {
    $user = checkLogin();
    if (!$user) {
        jsonMsg(0, '请先登录');
    }
    
    $oldPass = isset($_POST['old_pass']) ? trim($_POST['old_pass']) : '';
    $newPass = isset($_POST['new_pass']) ? trim($_POST['new_pass']) : '';
    $confirmPass = isset($_POST['confirm_pass']) ? trim($_POST['confirm_pass']) : '';
    
    if (empty($oldPass) || empty($newPass) || empty($confirmPass)) {
        jsonMsg(0, '请填写完整信息');
    }
    
    if (!checkPassword($newPass)) {
        jsonMsg(0, '新密码格式错误（6-16位数字字母组成）');
    }
    
    if ($newPass !== $confirmPass) {
        jsonMsg(0, '两次输入的密码不一致');
    }
    
    // 验证旧密码
    $db = new DB();
    $conn = $db->getConn();
    $usid = safeStr($user['usid'], $conn);
    $oldPassEncrypted = encryptPassword($oldPass);
    
    $sql = "SELECT id FROM `user` WHERE usid='{$usid}' AND pass='{$oldPassEncrypted}'";
    $result = $db->getRow($sql);
    
    if (!$result) {
        jsonMsg(0, '原密码错误');
    }
    
    // 更新密码
    $newPassEncrypted = encryptPassword($newPass);
    $db->update('user', ['pass' => $newPassEncrypted], "usid='{$usid}'");
    
    jsonMsg(1, '密码修改成功');
}

$pageTitle = '修改密码';
require_once './inc/head.php';
?>

<div class="card">
    <div class="card-title">修改密码</div>
    <form id="passForm" onsubmit="return false;">
        <div class="form-group">
            <label class="form-label">原密码</label>
            <input type="password" name="old_pass" class="form-control" placeholder="请输入原密码" required>
        </div>
        <div class="form-group">
            <label class="form-label">新密码</label>
            <input type="password" name="new_pass" class="form-control" placeholder="6-16位数字字母组成" required>
        </div>
        <div class="form-group">
            <label class="form-label">确认密码</label>
            <input type="password" name="confirm_pass" class="form-control" placeholder="请再次输入新密码" required>
        </div>
        <button type="button" class="btn btn-primary" onclick="doChange()">确认修改</button>
    </form>
</div>

<script>
function doChange() {
    var data = getFormData('passForm');
    if (!data.old_pass || !data.new_pass || !data.confirm_pass) {
        toast('请填写完整信息');
        return;
    }
    
    ajax('Da.php?do=ipass&act=change', data, function(res) {
        toast(res.msg);
        if (res.code === 1) {
            setTimeout(function() {
                location.href = 'Da.php?do=login';
            }, 1500);
        }
    });
}
</script>

<?php require_once './inc/foot.php'; ?>
