<?php
/**
 * 本文件功能: 用户导入
 * 作者信息: 15058593138@qq.com(手机号同微信)
 */

// 处理Ajax请求
if (isset($_GET['act'])) {
    switch ($_GET['act']) {
        case 'import':
            importUsers();
            break;
    }
    exit();
}

function importUsers() {
    $data = isset($_POST['data']) ? trim($_POST['data']) : '';
    
    if (empty($data)) {
        jsonMsg(0, '请输入导入数据');
    }
    
    // 保存为临时文件
    $tmpFile = './inc/tmp_import_' . time() . '.txt';
    file_put_contents($tmpFile, $data);
    
    // 读取并导入
    $db = new DB();
    $conn = $db->getConn();
    $success = 0;
    $fail = 0;
    $errors = [];
    
    $handle = fopen($tmpFile, 'r');
    if ($handle) {
        $line = 0;
        while (($row = fgetcsv($handle, 1000, "\t")) !== false) {
            $line++;
            
            if (count($row) < 3) {
                $errors[] = "第{$line}行：数据格式错误（需要3列）";
                $fail++;
                continue;
            }
            
            $usid = trim($row[0]);
            $name = trim($row[1]);
            $pass = trim($row[2]);
            
            if (empty($usid) || empty($name) || empty($pass)) {
                $errors[] = "第{$line}行：数据不能为空";
                $fail++;
                continue;
            }
            
            if (!checkPassword($pass)) {
                $errors[] = "第{$line}行：密码格式错误（6-16位数字字母）";
                $fail++;
                continue;
            }
            
            // 检查是否已存在
            $usidSafe = safeStr($usid, $conn);
            $check = $db->getRow("SELECT id FROM `user` WHERE usid='{$usidSafe}'");
            if ($check) {
                $errors[] = "第{$line}行：学号{$usid}已存在";
                $fail++;
                continue;
            }
            
            // 插入数据
            $result = $db->insert('user', [
                'usid' => $usid,
                'name' => $name,
                'pass' => encryptPassword($pass)
            ]);
            
            if ($result) {
                $success++;
            } else {
                $errors[] = "第{$line}行：插入失败";
                $fail++;
            }
        }
        fclose($handle);
    }
    
    // 删除临时文件
    unlink($tmpFile);
    
    $msg = "导入完成！成功：{$success}，失败：{$fail}";
    if (!empty($errors)) {
        $msg .= "\n错误详情：\n" . implode("\n", array_slice($errors, 0, 10));
        if (count($errors) > 10) {
            $msg .= "\n...等" . count($errors) . "个错误";
        }
    }
    
    jsonMsg(1, $msg, ['success' => $success, 'fail' => $fail]);
}

$pageTitle = '用户导入';
require_once './inc/head.php';
?>

<div class="card">
    <div class="card-title">批量导入用户</div>
    <div style="background: #f0f0f0; padding: 15px; border-radius: 4px; margin-bottom: 15px;">
        <strong>导入说明：</strong><br>
        1. 每行一个用户，使用制表符（Tab）分隔<br>
        2. 数据格式：学号[Tab]姓名[Tab]密码<br>
        3. 密码必须是6-16位数字字母组成<br>
        4. 示例：<br>
        <code style="display: block; margin-top: 5px; padding: 10px; background: #fff;">
S0001	张三	123456<br>
S0002	李四	abcd1234<br>
S0003	王五	pass123
        </code>
    </div>
    
    <form id="importForm" onsubmit="return false;">
        <div class="form-group">
            <label class="form-label">导入数据（学号、姓名、密码，用Tab分隔）</label>
            <textarea name="data" class="form-control" rows="15" placeholder="请粘贴导入数据"></textarea>
        </div>
        <button type="button" class="btn btn-primary" onclick="doImport()">开始导入</button>
    </form>
</div>

<script>
function doImport() {
    var data = getFormData('importForm');
    if (!data.data) {
        toast('请输入导入数据');
        return;
    }
    
    ajax('Ti.php?do=iusin&act=import', data, function(res) {
        if (res.code === 1) {
            var msg = res.msg;
            if (res.data) {
                msg += '\n成功：' + res.data.success + '，失败：' + res.data.fail;
            }
            showModal('导入结果', '<pre>' + msg + '</pre>', [
                {text: '确定', class: 'btn-primary', onclick: 'closeModal();location.href="Ti.php?do=iuser"'}
            ]);
        } else {
            toast(res.msg);
        }
    });
}
</script>

<?php require_once './inc/foot.php'; ?>
