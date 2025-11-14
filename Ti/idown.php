<?php
/**
 * 本文件功能: 答题数据下载
 * 作者信息: 15058593138@qq.com(手机号同微信)
 */

// 处理Ajax请求
if (isset($_GET['act'])) {
    switch ($_GET['act']) {
        case 'exams':
            getExams();
            break;
        case 'stats':
            getStats();
            break;
        case 'download_tsv':
            downloadTsv();
            break;
        case 'download_detail':
            downloadDetail();
            break;
    }
    exit();
}

function getExams() {
    $db = new DB();
    $sql = "SELECT DISTINCT ename FROM `recs` ORDER BY ename";
    $list = $db->getAll($sql);
    jsonMsg(1, '获取成功', $list);
}

function getStats() {
    $ename = isset($_POST['ename']) ? trim($_POST['ename']) : '';
    if (empty($ename)) {
        jsonMsg(0, '请选择考试');
    }
    
    $db = new DB();
    $conn = $db->getConn();
    $ename = safeStr($ename, $conn);
    
    $total = $db->count('recs', "ename='{$ename}'");
    $avgScore = $db->getOne("SELECT AVG(score) FROM `recs` WHERE ename='{$ename}'");
    $maxScore = $db->getOne("SELECT MAX(score) FROM `recs` WHERE ename='{$ename}'");
    $minScore = $db->getOne("SELECT MIN(score) FROM `recs` WHERE ename='{$ename}'");
    
    $stats = [
        'total' => $total,
        'avg' => round($avgScore, 2),
        'max' => $maxScore,
        'min' => $minScore
    ];
    
    jsonMsg(1, '获取成功', $stats);
}

function downloadTsv() {
    $ename = isset($_POST['ename']) ? trim($_POST['ename']) : '';
    if (empty($ename)) {
        jsonMsg(0, '请选择考试');
    }
    
    $db = new DB();
    $conn = $db->getConn();
    $ename = safeStr($ename, $conn);
    
    $list = $db->getAll("SELECT * FROM `recs` WHERE ename='{$ename}' ORDER BY usid");
    
    if (empty($list)) {
        jsonMsg(0, '暂无数据');
    }
    
    // 生成TSV数据
    $filename = 'answer_' . date('YmdHis') . '.tsv';
    
    header('Content-Type: text/tab-separated-values');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // 标题行
    $firstRec = $list[0];
    $idati = json_decode($firstRec['idati'], true);
    $headers = ['学号', '姓名', '得分', '开始时间', '完成时间'];
    foreach ($idati as $qid => $ans) {
        $headers[] = $qid;
    }
    echo implode("\t", $headers) . "\n";
    
    // 数据行
    foreach ($list as $item) {
        $user = $db->getRow("SELECT name FROM `user` WHERE usid='{$item['usid']}'");
        $uname = $user ? $user['name'] : '-';
        
        $row = [$item['usid'], $uname, $item['score'], $item['stime'], $item['ltime']];
        
        $idati = json_decode($item['idati'], true);
        foreach ($idati as $qid => $ans) {
            $row[] = is_array($ans) ? implode(',', $ans) : $ans;
        }
        
        echo implode("\t", $row) . "\n";
    }
    exit();
}

function downloadDetail() {
    $ename = isset($_POST['ename']) ? trim($_POST['ename']) : '';
    if (empty($ename)) {
        jsonMsg(0, '请选择考试');
    }
    
    $db = new DB();
    $conn = $db->getConn();
    $ename = safeStr($ename, $conn);
    
    $list = $db->getAll("SELECT * FROM `recs` WHERE ename='{$ename}' ORDER BY usid");
    
    if (empty($list)) {
        jsonMsg(0, '暂无数据');
    }
    
    // 生成计分明细TSV数据
    $filename = 'detail_' . date('YmdHis') . '.tsv';
    
    header('Content-Type: text/tab-separated-values');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // 标题行
    $firstRec = $list[0];
    $jifen = json_decode($firstRec['jifen'], true);
    $headers = ['学号', '姓名', '考试名称', '总分'];
    foreach ($jifen as $qid => $detail) {
        $headers[] = $qid . '_正确答案';
        $headers[] = $qid . '_用户答案';
        $headers[] = $qid . '_得分';
    }
    echo implode("\t", $headers) . "\n";
    
    // 数据行
    foreach ($list as $item) {
        $user = $db->getRow("SELECT name FROM `user` WHERE usid='{$item['usid']}'");
        $uname = $user ? $user['name'] : '-';
        
        $row = [$item['usid'], $uname, $item['ename'], $item['score']];
        
        $jifen = json_decode($item['jifen'], true);
        foreach ($jifen as $qid => $detail) {
            $correctAns = is_array($detail['answer']) ? implode(',', $detail['answer']) : $detail['answer'];
            $userAns = is_array($detail['user']) ? implode(',', $detail['user']) : $detail['user'];
            $row[] = $correctAns;
            $row[] = $userAns;
            $row[] = $detail['score'];
        }
        
        echo implode("\t", $row) . "\n";
    }
    exit();
}

$pageTitle = '数据下载';
require_once './inc/head.php';
?>

<div class="card">
    <div class="card-title">答题数据下载</div>
    
    <div class="form-group">
        <label class="form-label">选择考试</label>
        <select id="examSelect" class="form-control" onchange="loadStats()">
            <option value="">请选择考试</option>
        </select>
    </div>
    
    <div id="statsBox" style="display: none;">
        <div style="background: #f0f0f0; padding: 15px; border-radius: 4px; margin-bottom: 15px;">
            <h4>统计信息</h4>
            <p>答题人数：<strong id="statTotal">0</strong></p>
            <p>平均分：<strong id="statAvg">0</strong></p>
            <p>最高分：<strong id="statMax">0</strong></p>
            <p>最低分：<strong id="statMin">0</strong></p>
        </div>
        
        <div style="display: flex; gap: 10px;">
            <button class="btn btn-primary" onclick="downloadTsv()">下载答题记录（TSV）</button>
            <button class="btn btn-primary" onclick="downloadDetail()">下载计分明细（TSV）</button>
        </div>
    </div>
</div>

<script>
function loadExams() {
    ajax('Ti.php?do=idown&act=exams', {}, function(res) {
        if (res.code === 1) {
            var select = document.getElementById('examSelect');
            var html = '<option value="">请选择考试</option>';
            for (var i = 0; i < res.data.length; i++) {
                html += '<option value="' + res.data[i].ename + '">' + res.data[i].ename + '</option>';
            }
            select.innerHTML = html;
        }
    });
}

function loadStats() {
    var ename = document.getElementById('examSelect').value;
    if (!ename) {
        document.getElementById('statsBox').style.display = 'none';
        return;
    }
    
    ajax('Ti.php?do=idown&act=stats', {ename: ename}, function(res) {
        if (res.code === 1) {
            document.getElementById('statTotal').textContent = res.data.total;
            document.getElementById('statAvg').textContent = res.data.avg;
            document.getElementById('statMax').textContent = res.data.max;
            document.getElementById('statMin').textContent = res.data.min;
            document.getElementById('statsBox').style.display = 'block';
        }
    });
}

function downloadTsv() {
    var ename = document.getElementById('examSelect').value;
    if (!ename) {
        toast('请选择考试');
        return;
    }
    
    var form = document.createElement('form');
    form.method = 'POST';
    form.action = 'Ti.php?do=idown&act=download_tsv';
    
    var input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'ename';
    input.value = ename;
    form.appendChild(input);
    
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}

function downloadDetail() {
    var ename = document.getElementById('examSelect').value;
    if (!ename) {
        toast('请选择考试');
        return;
    }
    
    var form = document.createElement('form');
    form.method = 'POST';
    form.action = 'Ti.php?do=idown&act=download_detail';
    
    var input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'ename';
    input.value = ename;
    form.appendChild(input);
    
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}

// 页面加载时获取考试列表
loadExams();
</script>

<?php require_once './inc/foot.php'; ?>
