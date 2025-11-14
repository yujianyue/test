<?php
/**
 * 本文件功能: 答题记录列表
 * 作者信息: 15058593138@qq.com(手机号同微信)
 */

// 处理Ajax请求
if (isset($_GET['act'])) {
    switch ($_GET['act']) {
        case 'list':
            getRecsList();
            break;
        case 'detail':
            getRecsDetail();
            break;
    }
    exit();
}

function getRecsList() {
    $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
    $ename = isset($_POST['ename']) ? trim($_POST['ename']) : '';
    $usid = isset($_POST['usid']) ? trim($_POST['usid']) : '';
    
    $db = new DB();
    $conn = $db->getConn();
    
    $where = '1=1';
    if ($ename) {
        $ename = safeStr($ename, $conn);
        $where .= " AND ename LIKE '%{$ename}%'";
    }
    if ($usid) {
        $usid = safeStr($usid, $conn);
        $where .= " AND usid LIKE '%{$usid}%'";
    }
    
    $result = $db->getPage('recs', $where, $page, PAGE_SIZE, 'id DESC');
    
    // 关联用户姓名
    foreach ($result['list'] as &$item) {
        $user = $db->getRow("SELECT name FROM `user` WHERE usid='{$item['usid']}'");
        $item['uname'] = $user ? $user['name'] : '-';
    }
    
    jsonMsg(1, '获取成功', $result);
}

function getRecsDetail() {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if (!$id) {
        jsonMsg(0, '参数错误');
    }
    
    $db = new DB();
    $recs = $db->getRow("SELECT * FROM `recs` WHERE id={$id}");
    
    if (!$recs) {
        jsonMsg(0, '记录不存在');
    }
    
    // 获取用户信息
    $user = $db->getRow("SELECT name FROM `user` WHERE usid='{$recs['usid']}'");
    $recs['uname'] = $user ? $user['name'] : '-';
    
    // 解析答题记录和计分明细
    $recs['idati'] = json_decode($recs['idati'], true);
    $recs['jifen'] = json_decode($recs['jifen'], true);
    
    // 读取题库获取题目内容
    if (file_exists($recs['epath'])) {
        $questions = readJson($recs['epath']);
        $recs['questions'] = $questions;
    }
    
    jsonMsg(1, '获取成功', $recs);
}

$pageTitle = '答题记录';
require_once './inc/head.php';
?>

<div class="table-wrapper">
    <div class="table-header">
        <div class="table-title">答题记录</div>
        <div class="table-tools">
            <input type="text" id="searchEname" placeholder="考试名称" style="width: 150px;">
            <input type="text" id="searchUsid" placeholder="学号" style="width: 120px;">
            <button class="btn btn-primary" onclick="loadList(1)">查询</button>
        </div>
    </div>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>考试名称</th>
                <th>学号</th>
                <th>姓名</th>
                <th>得分</th>
                <th>开始时间</th>
                <th>完成时间</th>
                <th>IP地址</th>
                <th width="100">操作</th>
            </tr>
        </thead>
        <tbody id="recsList">
            <tr><td colspan="9" style="text-align: center;">加载中...</td></tr>
        </tbody>
    </table>
</div>

<div class="pagination" id="pagination"></div>

<script>
function loadList(page) {
    page = page || 1;
    var ename = document.getElementById('searchEname').value;
    var usid = document.getElementById('searchUsid').value;
    
    ajax('Ti.php?do=ilist&act=list', {page: page, ename: ename, usid: usid}, function(res) {
        if (res.code === 1) {
            var data = res.data;
            var html = '';
            
            if (data.list.length === 0) {
                html = '<tr><td colspan="9" style="text-align: center;">暂无数据</td></tr>';
            } else {
                for (var i = 0; i < data.list.length; i++) {
                    var item = data.list[i];
                    html += '<tr>';
                    html += '<td>' + item.id + '</td>';
                    html += '<td>' + item.ename + '</td>';
                    html += '<td>' + item.usid + '</td>';
                    html += '<td>' + item.uname + '</td>';
                    html += '<td><strong>' + item.score + '</strong></td>';
                    html += '<td>' + item.stime + '</td>';
                    html += '<td>' + item.ltime + '</td>';
                    html += '<td>' + (item.ipadd || '-') + '</td>';
                    html += '<td><button class="btn btn-default" onclick="viewDetail(' + item.id + ')">详情</button></td>';
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

function viewDetail(id) {
    ajax('Ti.php?do=ilist&act=detail', {id: id}, function(res) {
        if (res.code === 1) {
            var data = res.data;
            var html = '<div style="max-height: 60vh; overflow-y: auto;">';
            html += '<p><strong>学号：</strong>' + data.usid + '</p>';
            html += '<p><strong>姓名：</strong>' + data.uname + '</p>';
            html += '<p><strong>考试：</strong>' + data.ename + '</p>';
            html += '<p><strong>得分：</strong><span style="color: #1890ff; font-size: 18px;">' + data.score + '</span></p>';
            html += '<hr>';
            
            if (data.jifen && data.questions && data.questions.questions) {
                var questions = data.questions.questions;
                var jifen = data.jifen;
                var mode = data.questions.mode || 1;
                
                html += '<table style="width: 100%; border-collapse: collapse;">';
                html += '<thead><tr>';
                html += '<th style="border: 1px solid #eee; padding: 8px;">题目</th>';
                html += '<th style="border: 1px solid #eee; padding: 8px;">正确答案</th>';
                html += '<th style="border: 1px solid #eee; padding: 8px;">用户答案</th>';
                html += '<th style="border: 1px solid #eee; padding: 8px;">得分</th>';
                html += '</tr></thead><tbody>';
                
                for (var qid in jifen) {
                    var item = jifen[qid];
                    var question = questions[qid];
                    
                    if (question) {
                        html += '<tr>';
                        html += '<td style="border: 1px solid #eee; padding: 8px;">' + question.title + '</td>';
                        
                        // 正确答案（调查模式显示-）
                        if (mode == 2) {
                            html += '<td style="border: 1px solid #eee; padding: 8px; text-align: center;">-</td>';
                        } else {
                            var correctAns = Array.isArray(item.answer) ? item.answer.join(',') : item.answer;
                            html += '<td style="border: 1px solid #eee; padding: 8px; text-align: center;">' + correctAns + '</td>';
                        }
                        
                        // 用户答案
                        var userAns = Array.isArray(item.user) ? item.user.join(',') : item.user;
                        html += '<td style="border: 1px solid #eee; padding: 8px; text-align: center;">' + (userAns || '-') + '</td>';
                        
                        // 得分
                        html += '<td style="border: 1px solid #eee; padding: 8px; text-align: center;">' + item.score + '</td>';
                        html += '</tr>';
                    }
                }
                
                html += '</tbody></table>';
            }
            
            html += '</div>';
            
            showModal('答题详情', html, [
                {text: '关闭', class: 'btn-default', onclick: 'closeModal()'}
            ]);
        } else {
            toast(res.msg);
        }
    });
}

// 页面加载时获取列表
loadList(1);
</script>

<?php require_once './inc/foot.php'; ?>
