<?php
/**
 * 本文件功能: 考试ID输入页面
 * 作者信息: 15058593138@qq.com(手机号同微信)
 */

// 处理Ajax请求
if (isset($_GET['act'])) {
    switch ($_GET['act']) {
        case 'check':
            checkExam();
            break;
    }
    exit();
}

function checkExam() {
    $examId = isset($_POST['exam_id']) ? trim($_POST['exam_id']) : '';
    
    if (empty($examId)) {
        jsonMsg(0, '请输入考试ID');
    }
    
    // 检查题库文件是否存在
    $examPath = './Ku/' . $examId . '.json';
    if (!file_exists($examPath)) {
        jsonMsg(0, '考试不存在');
    }
    
    // 读取题库
    $exam = readJson($examPath);
    if (!$exam) {
        jsonMsg(0, '题库文件格式错误');
    }
    
    jsonMsg(1, '考试存在', [
        'name' => $exam['name'],
        'exam_id' => $examId
    ]);
}

$pageTitle = '开始答题';
require_once './inc/head.php';
?>

<div class="card">
    <div class="card-title">输入考试ID</div>
    <form id="examForm" onsubmit="return false;">
        <div class="form-group">
            <label class="form-label">考试ID</label>
            <input type="text" name="exam_id" id="examId" class="form-control" placeholder="请输入考试ID（文件名）" required>
        </div>
        <button type="button" class="btn btn-primary" onclick="checkExam()">查询考试</button>
    </form>
</div>

<div id="examInfo" style="display: none;">
    <div class="card">
        <div class="card-title">考试信息</div>
        <p><strong>考试名称：</strong><span id="examName"></span></p>
        <button class="btn btn-primary" onclick="startExam()">进入答题</button>
    </div>
</div>

<script>
var currentExamId = '';

function checkExam() {
    var examId = document.getElementById('examId').value;
    if (!examId) {
        toast('请输入考试ID');
        return;
    }
    
    ajax('Da.php?do=index&act=check', {exam_id: examId}, function(res) {
        if (res.code === 1) {
            currentExamId = res.data.exam_id;
            document.getElementById('examName').textContent = res.data.name;
            document.getElementById('examInfo').style.display = 'block';
        } else {
            toast(res.msg);
            document.getElementById('examInfo').style.display = 'none';
        }
    });
}

function startExam() {
    if (currentExamId) {
        location.href = 'Da.php?do=idati&exam_id=' + currentExamId;
    }
}

// 回车查询
document.getElementById('examId').addEventListener('keypress', function(e) {
    if (e.keyCode === 13) {
        checkExam();
    }
});
</script>

<?php require_once './inc/foot.php'; ?>
