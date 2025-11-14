<?php
/**
 * 本文件功能: 学生答题记录
 * 作者信息: 15058593138@qq.com(手机号同微信)
 */

// 处理Ajax请求
if (isset($_GET['act'])) {
    switch ($_GET['act']) {
        case 'list':
            getMyRecsList();
            break;
    }
    exit();
}

function getMyRecsList() {
    $user = checkLogin();
    if (!$user) {
        jsonMsg(0, '请先登录');
    }
    
    $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
    
    $db = new DB();
    $conn = $db->getConn();
    $usid = safeStr($user['usid'], $conn);
    
    $result = $db->getPage('recs', "usid='{$usid}'", $page, PAGE_SIZE, 'id DESC');
    
    jsonMsg(1, '获取成功', $result);
}

$pageTitle = '答题记录';
require_once './inc/head.php';
?>

<div class="table-wrapper">
    <div class="table-header">
        <div class="table-title">我的答题记录</div>
    </div>
    <table>
        <thead>
            <tr>
                <th>考试名称</th>
                <th>得分</th>
                <th>开始时间</th>
                <th>完成时间</th>
            </tr>
        </thead>
        <tbody id="recsList">
            <tr><td colspan="4" style="text-align: center;">加载中...</td></tr>
        </tbody>
    </table>
</div>

<div class="pagination" id="pagination"></div>

<script>
function loadList(page) {
    page = page || 1;
    
    ajax('Da.php?do=ilist&act=list', {page: page}, function(res) {
        if (res.code === 1) {
            var data = res.data;
            var html = '';
            
            if (data.list.length === 0) {
                html = '<tr><td colspan="4" style="text-align: center;">暂无数据</td></tr>';
            } else {
                for (var i = 0; i < data.list.length; i++) {
                    var item = data.list[i];
                    html += '<tr>';
                    html += '<td>' + item.ename + '</td>';
                    html += '<td><strong style="color: #1890ff; font-size: 16px;">' + item.score + '</strong></td>';
                    html += '<td>' + item.stime + '</td>';
                    html += '<td>' + item.ltime + '</td>';
                    html += '</tr>';
                }
            }
            
            document.getElementById('recsList').innerHTML = html;
            initPagination(data.page, data.totalPage);
        } else {
            toast(res.msg);
        }
    });
}

function gotoPage(page) {
    loadList(page);
}

// 页面加载时获取列表
loadList(1);
</script>

<?php require_once './inc/foot.php'; ?>
