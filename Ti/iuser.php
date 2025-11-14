<?php
/**
 * 本文件功能: 用户列表
 * 作者信息: 15058593138@qq.com(手机号同微信)
 */

// 处理Ajax请求
if (isset($_GET['act'])) {
    switch ($_GET['act']) {
        case 'list':
            getUserList();
            break;
        case 'delete':
            deleteUser();
            break;
        case 'batch_delete':
            batchDeleteUser();
            break;
        case 'reset_pass':
            resetPassword();
            break;
    }
    exit();
}

function getUserList() {
    $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
    $field = isset($_POST['field']) ? trim($_POST['field']) : '';
    $keyword = isset($_POST['keyword']) ? trim($_POST['keyword']) : '';
    
    $db = new DB();
    $conn = $db->getConn();
    
    $where = '1=1';
    if ($field && $keyword) {
        $field = safeStr($field, $conn);
        $keyword = safeStr($keyword, $conn);
        $where .= " AND `{$field}` LIKE '%{$keyword}%'";
    }
    
    $result = $db->getPage('user', $where, $page);
    jsonMsg(1, '获取成功', $result);
}

function deleteUser() {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if (!$id) {
        jsonMsg(0, '参数错误');
    }
    
    $db = new DB();
    
    // 检查是否是admin账号
    $user = $db->getRow("SELECT usid FROM `user` WHERE id={$id}");
    if ($user && $user['usid'] === 'admin') {
        jsonMsg(0, '不能删除管理员账号');
    }
    
    $db->delete('user', "id={$id}");
    jsonMsg(1, '删除成功');
}

function batchDeleteUser() {
    $ids = isset($_POST['ids']) ? $_POST['ids'] : '';
    if (empty($ids)) {
        jsonMsg(0, '请选择要删除的用户');
    }
    
    $idsArr = explode(',', $ids);
    $db = new DB();
    
    foreach ($idsArr as $id) {
        $id = intval($id);
        if ($id > 0) {
            // 检查是否是admin账号
            $user = $db->getRow("SELECT usid FROM `user` WHERE id={$id}");
            if (!$user || $user['usid'] === 'admin') {
                continue;
            }
            $db->delete('user', "id={$id}");
        }
    }
    
    jsonMsg(1, '批量删除成功');
}

function resetPassword() {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $newPass = isset($_POST['new_pass']) ? trim($_POST['new_pass']) : '';
    
    if (!$id || empty($newPass)) {
        jsonMsg(0, '参数错误');
    }
    
    if (!checkPassword($newPass)) {
        jsonMsg(0, '密码格式错误（6-16位数字字母组成）');
    }
    
    $db = new DB();
    $pass = encryptPassword($newPass);
    $db->update('user', ['pass' => $pass], "id={$id}");
    
    jsonMsg(1, '密码重置成功');
}

$pageTitle = '用户列表';
require_once './inc/head.php';
?>

<div class="table-wrapper">
    <div class="table-header">
        <div class="table-title">用户列表</div>
        <div class="table-tools">
            <select id="searchField">
                <option value="usid">学号</option>
                <option value="name">姓名</option>
            </select>
            <input type="text" id="searchKeyword" placeholder="搜索关键词" onkeypress="if(event.keyCode===13)loadList(1)">
            <button class="btn btn-primary" onclick="loadList(1)">查询</button>
            <button class="btn btn-danger" onclick="batchDelete()">批量删除</button>
        </div>
    </div>
    <table>
        <thead>
            <tr>
                <th width="50"><input type="checkbox" id="checkAll" onclick="toggleCheckAll()"></th>
                <th>ID</th>
                <th>学号</th>
                <th>姓名</th>
                <th>添加时间</th>
                <th>修改时间</th>
                <th width="150">操作</th>
            </tr>
        </thead>
        <tbody id="userList">
            <tr><td colspan="7" style="text-align: center;">加载中...</td></tr>
        </tbody>
    </table>
</div>

<div class="pagination" id="pagination"></div>

<script>
var selectedIds = [];

function loadList(page) {
    page = page || 1;
    var field = document.getElementById('searchField').value;
    var keyword = document.getElementById('searchKeyword').value;
    
    ajax('Ti.php?do=iuser&act=list', {page: page, field: field, keyword: keyword}, function(res) {
        if (res.code === 1) {
            var data = res.data;
            var html = '';
            
            if (data.list.length === 0) {
                html = '<tr><td colspan="7" style="text-align: center;">暂无数据</td></tr>';
            } else {
                for (var i = 0; i < data.list.length; i++) {
                    var item = data.list[i];
                    html += '<tr>';
                    html += '<td><input type="checkbox" class="itemCheck" value="' + item.id + '" onchange="updateSelectedIds()"></td>';
                    html += '<td>' + item.id + '</td>';
                    html += '<td>' + item.usid + '</td>';
                    html += '<td>' + item.name + '</td>';
                    html += '<td>' + item.ctime + '</td>';
                    html += '<td>' + item.utime + '</td>';
                    html += '<td>';
                    if (item.usid !== 'admin') {
                        html += '<button class="btn btn-default" style="margin-right: 5px;" onclick="resetPass(' + item.id + ')">改密</button>';
                        html += '<button class="btn btn-danger" onclick="deleteItem(' + item.id + ')">删除</button>';
                    }
                    html += '</td>';
                    html += '</tr>';
                }
            }
            
            document.getElementById('userList').innerHTML = html;
            initPagination(data.page, data.totalPage);
        } else {
            toast(res.msg);
        }
    });
}

function gotoPage(page) {
    loadList(page);
}

function toggleCheckAll() {
    var checkAll = document.getElementById('checkAll').checked;
    var checks = document.getElementsByClassName('itemCheck');
    for (var i = 0; i < checks.length; i++) {
        checks[i].checked = checkAll;
    }
    updateSelectedIds();
}

function updateSelectedIds() {
    selectedIds = [];
    var checks = document.getElementsByClassName('itemCheck');
    for (var i = 0; i < checks.length; i++) {
        if (checks[i].checked) {
            selectedIds.push(checks[i].value);
        }
    }
}

function deleteItem(id) {
    showModal('确认删除', '确定要删除这个用户吗？', [
        {text: '取消', class: 'btn-default', onclick: 'closeModal()'},
        {text: '确定', class: 'btn-danger', onclick: 'confirmDelete(' + id + ')'}
    ]);
}

function confirmDelete(id) {
    closeModal();
    ajax('Ti.php?do=iuser&act=delete', {id: id}, function(res) {
        toast(res.msg);
        if (res.code === 1) {
            loadList(currentPage);
        }
    });
}

function batchDelete() {
    if (selectedIds.length === 0) {
        toast('请选择要删除的用户');
        return;
    }
    
    showModal('确认删除', '确定要删除选中的 ' + selectedIds.length + ' 个用户吗？', [
        {text: '取消', class: 'btn-default', onclick: 'closeModal()'},
        {text: '确定', class: 'btn-danger', onclick: 'confirmBatchDelete()'}
    ]);
}

function confirmBatchDelete() {
    closeModal();
    ajax('Ti.php?do=iuser&act=batch_delete', {ids: selectedIds.join(',')}, function(res) {
        toast(res.msg);
        if (res.code === 1) {
            loadList(currentPage);
        }
    });
}

function resetPass(id) {
    var newPass = prompt('请输入新密码（6-16位数字字母组成）：');
    if (newPass) {
        ajax('Ti.php?do=iuser&act=reset_pass', {id: id, new_pass: newPass}, function(res) {
            toast(res.msg);
        });
    }
}

// 页面加载时获取列表
loadList(1);
</script>

<?php require_once './inc/foot.php'; ?>
