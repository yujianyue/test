<?php
/**
 * 本文件功能: 答题页面
 * 作者信息: 15058593138@qq.com(手机号同微信)
 */

// 处理Ajax请求
if (isset($_GET['act'])) {
    switch ($_GET['act']) {
        case 'submit':
            submitAnswers();
            break;
    }
    exit();
}

function submitAnswers() {
    $user = checkLogin();
    if (!$user) {
        jsonMsg(0, '请先登录');
    }
    
    $examId = isset($_POST['exam_id']) ? trim($_POST['exam_id']) : '';
    $answers = isset($_POST['answers']) ? $_POST['answers'] : '';
    $stime = isset($_POST['stime']) ? trim($_POST['stime']) : '';
    
    if (empty($examId) || empty($answers)) {
        jsonMsg(0, '参数错误');
    }
    
    // 读取题库
    $examPath = './Ku/' . $examId . '.json';
    if (!file_exists($examPath)) {
        jsonMsg(0, '题库不存在');
    }
    
    $exam = readJson($examPath);
    if (!$exam) {
        jsonMsg(0, '题库格式错误');
    }
    
    // 解析答案
    $answersArr = json_decode($answers, true);
    if (!$answersArr) {
        jsonMsg(0, '答案格式错误');
    }
    
    // 检查时间限制
    if (isset($exam['time_limit']) && $exam['time_limit'] > 0) {
        $startTime = strtotime($stime);
        $nowTime = time();
        $limitTime = $exam['time_limit'] * 60 + 30; // 允许30秒误差
        
        if ($nowTime - $startTime > $limitTime) {
            jsonMsg(0, '超过答题时间，不可提交');
        }
    }
    
    // 计算得分
    $mode = isset($exam['mode']) ? intval($exam['mode']) : SCORE_MODE_1;
    $result = calculateScore($exam['questions'], $answersArr, $mode);
    
    // 保存答题记录
    $db = new DB();
    $ptime = date('Y-m-d H:i:s', filemtime($examPath));
    $ltime = date('Y-m-d H:i:s');
    $ipadd = getClientIp();
    
    // 检查是否已有答题记录
    $existRec = $db->getRow("SELECT id FROM `recs` WHERE usid='{$user['usid']}' AND ename='{$exam['name']}' AND ptime='{$ptime}'");
    
    if ($existRec) {
        // 更新已有记录
        $db->update('recs', [
            'ltime' => $ltime,
            'idati' => json_encode($answersArr, JSON_UNESCAPED_UNICODE),
            'jifen' => json_encode($result['details'], JSON_UNESCAPED_UNICODE),
            'score' => $result['total'],
            'ipadd' => $ipadd
        ], "id={$existRec['id']}");
    } else {
        // 插入新记录
        $db->insert('recs', [
            'ename' => $exam['name'],
            'epath' => $examPath,
            'ptime' => $ptime,
            'usid' => $user['usid'],
            'stime' => $stime,
            'ltime' => $ltime,
            'idati' => json_encode($answersArr, JSON_UNESCAPED_UNICODE),
            'jifen' => json_encode($result['details'], JSON_UNESCAPED_UNICODE),
            'score' => $result['total'],
            'ipadd' => $ipadd
        ]);
    }
    
    jsonMsg(1, '提交成功', ['score' => $result['total']]);
}

// 获取考试ID
$examId = isset($_GET['exam_id']) ? trim($_GET['exam_id']) : '';
if (empty($examId)) {
    header('Location: Da.php?do=index');
    exit();
}

// 读取题库
$examPath = './Ku/' . $examId . '.json';
if (!file_exists($examPath)) {
    die('题库不存在');
}

$exam = readJson($examPath);
if (!$exam) {
    die('题库格式错误');
}

$pageTitle = $exam['name'];
require_once './inc/head.php';

// 准备题目数据（不含答案）
$questionsForJS = $exam;
unset($questionsForJS['questions']);
$questionsForJS['items'] = [];
foreach ($exam['questions'] as $qid => $question) {
    $item = $question;
    unset($item['answer']); // 移除答案
    $questionsForJS['items'][$qid] = $item;
}
?>

<style>
.quiz-header {
    background: #fff;
    padding: 15px;
    border-radius: 4px;
    margin-bottom: 15px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.quiz-progress {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.quiz-body {
    background: #fff;
    padding: 20px;
    border-radius: 4px;
    margin-bottom: 15px;
    min-height: 400px;
}

.question-title {
    font-size: 16px;
    font-weight: bold;
    margin-bottom: 20px;
    line-height: 1.6;
}

.option-item {
    display: block;
    padding: 12px;
    margin-bottom: 10px;
    border: 2px solid #e0e0e0;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.3s;
}

.option-item:hover {
    border-color: #1890ff;
    background: #f0f8ff;
}

.option-item input {
    margin-right: 10px;
}

.option-item.selected {
    border-color: #1890ff;
    background: #e6f7ff;
}

.quiz-nav {
    display: flex;
    justify-content: space-between;
    margin-top: 20px;
}

.question-map {
    background: #fff;
    padding: 15px;
    border-radius: 4px;
    margin-bottom: 15px;
}

.question-map-title {
    font-weight: bold;
    margin-bottom: 10px;
}

.question-map-items {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.question-map-item {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid #d9d9d9;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.3s;
}

.question-map-item:hover {
    border-color: #1890ff;
}

.question-map-item.answered {
    background: #1890ff;
    color: #fff;
    border-color: #1890ff;
}

.question-map-item.current {
    background: #52c41a;
    color: #fff;
    border-color: #52c41a;
}
</style>

<div class="quiz-header">
    <div class="quiz-progress">
        <span>已答：<strong id="answeredCount">0</strong> / <strong id="totalCount">0</strong></span>
        <span>进度：<strong id="progressPercent">0</strong>%</span>
        <span id="timeRemain"></span>
    </div>
    <div class="progress">
        <div class="progress-bar" id="progressBar" style="width: 0%"></div>
    </div>
    <div style="display: flex; gap: 10px; margin-top: 10px;">
        <span>开始时间：<strong id="startTime"></strong></span>
        <button class="btn btn-primary" id="submitBtn" onclick="submitExam()">完成答题</button>
    </div>
</div>

<div class="quiz-body" id="questionBox">
    <!-- 题目内容 -->
</div>

<div class="quiz-nav">
    <button class="btn btn-default" id="prevBtn" onclick="prevQuestion()">上一题</button>
    <button class="btn btn-default" id="nextBtn" onclick="nextQuestion()">下一题</button>
</div>

<div class="question-map">
    <div class="question-map-title">题目导航</div>
    <div id="questionMapTF" style="margin-bottom: 10px;"></div>
    <div id="questionMapSingle" style="margin-bottom: 10px;"></div>
    <div id="questionMapMulti"></div>
</div>

<script>
// 题库数据
var examData = <?php echo json_encode($questionsForJS, JSON_UNESCAPED_UNICODE); ?>;
var examId = '<?php echo $examId; ?>';
var questions = examData.items;
var questionIds = Object.keys(questions);
var currentIndex = 0;
var answers = {};
var startTime = new Date();
var autoSaveTimer = null;
var isSubmitting = false;

// 初始化
function init() {
    // 设置开始时间
    document.getElementById('startTime').textContent = formatDate(startTime.getTime(), 'H:i:s');
    document.getElementById('totalCount').textContent = questionIds.length;
    
    // 尝试从Cookie恢复答案
    var savedAnswers = getCookie('exam_' + examId + '_answers');
    if (savedAnswers) {
        try {
            answers = JSON.parse(savedAnswers);
        } catch (e) {
            answers = {};
        }
    }
    
    // 尝试从Cookie恢复开始时间
    var savedStartTime = getCookie('exam_' + examId + '_start');
    if (savedStartTime) {
        startTime = new Date(parseInt(savedStartTime));
        document.getElementById('startTime').textContent = formatDate(startTime.getTime(), 'H:i:s');
    } else {
        setCookie('exam_' + examId + '_start', startTime.getTime(), 1);
    }
    
    // 显示第一题
    showQuestion(0);
    updateProgress();
    renderQuestionMap();
    
    // 启动自动保存（每3分钟）
    autoSaveTimer = setInterval(autoSave, 180000);
    
    // 显示倒计时
    if (examData.time_limit && examData.time_limit > 0) {
        updateTimeRemain();
        setInterval(updateTimeRemain, 1000);
    }
}

// 显示题目
function showQuestion(index) {
    currentIndex = index;
    var qid = questionIds[index];
    var question = questions[qid];
    
    var html = '<div class="question-title">';
    html += (index + 1) + '. ' + question.title;
    if (question.type === 'multi') {
        html += ' <span class="badge badge-primary">多选题</span>';
    } else if (question.type === 'single') {
        html += ' <span class="badge badge-success">单选题</span>';
    } else {
        html += ' <span class="badge">判断题</span>';
    }
    html += '</div>';
    
    // 显示选项
    var userAnswer = answers[qid] || [];
    if (!Array.isArray(userAnswer)) {
        userAnswer = [userAnswer];
    }
    
    for (var i = 0; i < question.options.length; i++) {
        var option = question.options[i];
        var optionKey = String.fromCharCode(65 + i); // A, B, C, D...
        var checked = userAnswer.indexOf(optionKey) >= 0;
        
        if (question.type === 'multi') {
            html += '<label class="option-item' + (checked ? ' selected' : '') + '">';
            html += '<input type="checkbox" name="answer" value="' + optionKey + '"' + (checked ? ' checked' : '') + ' onchange="selectAnswer(this, \'' + qid + '\', true)">';
            html += optionKey + '. ' + option;
            html += '</label>';
        } else {
            html += '<label class="option-item' + (checked ? ' selected' : '') + '">';
            html += '<input type="radio" name="answer" value="' + optionKey + '"' + (checked ? ' checked' : '') + ' onchange="selectAnswer(this, \'' + qid + '\', false)">';
            html += optionKey + '. ' + option;
            html += '</label>';
        }
    }
    
    document.getElementById('questionBox').innerHTML = html;
    
    // 更新导航按钮状态
    document.getElementById('prevBtn').disabled = (index === 0);
    document.getElementById('nextBtn').disabled = (index === questionIds.length - 1);
    
    renderQuestionMap();
}

// 选择答案
function selectAnswer(el, qid, isMulti) {
    if (isMulti) {
        // 多选
        var checkboxes = document.getElementsByName('answer');
        var selected = [];
        for (var i = 0; i < checkboxes.length; i++) {
            if (checkboxes[i].checked) {
                selected.push(checkboxes[i].value);
                checkboxes[i].parentElement.classList.add('selected');
            } else {
                checkboxes[i].parentElement.classList.remove('selected');
            }
        }
        answers[qid] = selected;
    } else {
        // 单选
        var radios = document.getElementsByName('answer');
        for (var i = 0; i < radios.length; i++) {
            radios[i].parentElement.classList.remove('selected');
        }
        el.parentElement.classList.add('selected');
        answers[qid] = el.value;
    }
    
    updateProgress();
    saveAnswersToLocal();
    renderQuestionMap();
}

// 上一题
function prevQuestion() {
    if (currentIndex > 0) {
        showQuestion(currentIndex - 1);
    }
}

// 下一题
function nextQuestion() {
    if (currentIndex < questionIds.length - 1) {
        showQuestion(currentIndex + 1);
    }
}

// 跳转到指定题目
function gotoQuestion(index) {
    showQuestion(index);
}

// 更新进度
function updateProgress() {
    var answered = 0;
    for (var qid in answers) {
        if (answers[qid] && (Array.isArray(answers[qid]) ? answers[qid].length > 0 : answers[qid])) {
            answered++;
        }
    }
    
    var percent = Math.round((answered / questionIds.length) * 100);
    document.getElementById('answeredCount').textContent = answered;
    document.getElementById('progressPercent').textContent = percent;
    document.getElementById('progressBar').style.width = percent + '%';
}

// 渲染题目导航
function renderQuestionMap() {
    var mapTF = '';
    var mapSingle = '';
    var mapMulti = '';
    
    for (var i = 0; i < questionIds.length; i++) {
        var qid = questionIds[i];
        var question = questions[qid];
        var answered = answers[qid] && (Array.isArray(answers[qid]) ? answers[qid].length > 0 : answers[qid]);
        var isCurrent = i === currentIndex;
        
        var className = 'question-map-item';
        if (isCurrent) {
            className += ' current';
        } else if (answered) {
            className += ' answered';
        }
        
        var html = '<div class="' + className + '" onclick="gotoQuestion(' + i + ')">' + (i + 1) + '</div>';
        
        if (question.type === 'tf') {
            mapTF += html;
        } else if (question.type === 'single') {
            mapSingle += html;
        } else if (question.type === 'multi') {
            mapMulti += html;
        }
    }
    
    if (mapTF) {
        document.getElementById('questionMapTF').innerHTML = '<div style="margin-bottom: 5px;">判断题</div><div class="question-map-items">' + mapTF + '</div>';
    }
    if (mapSingle) {
        document.getElementById('questionMapSingle').innerHTML = '<div style="margin-bottom: 5px;">单选题</div><div class="question-map-items">' + mapSingle + '</div>';
    }
    if (mapMulti) {
        document.getElementById('questionMapMulti').innerHTML = '<div style="margin-bottom: 5px;">多选题</div><div class="question-map-items">' + mapMulti + '</div>';
    }
}

// 保存答案到本地
function saveAnswersToLocal() {
    setCookie('exam_' + examId + '_answers', JSON.stringify(answers), 1);
}

// 自动保存到服务器
function autoSave() {
    if (isSubmitting) return;
    
    isSubmitting = true;
    document.getElementById('submitBtn').disabled = true;
    document.getElementById('submitBtn').textContent = '提交中...';
    
    ajax('Da.php?do=idati&act=submit', {
        exam_id: examId,
        answers: JSON.stringify(answers),
        stime: formatDate(startTime.getTime(), 'Y-m-d H:i:s')
    }, function(res) {
        isSubmitting = false;
        document.getElementById('submitBtn').disabled = false;
        document.getElementById('submitBtn').textContent = '完成答题';
    });
}

// 提交答卷
function submitExam() {
    // 检查是否全部答完
    var answered = 0;
    for (var qid in answers) {
        if (answers[qid] && (Array.isArray(answers[qid]) ? answers[qid].length > 0 : answers[qid])) {
            answered++;
        }
    }
    
    if (answered < questionIds.length) {
        if (!confirm('还有 ' + (questionIds.length - answered) + ' 题未答，确定要提交吗？')) {
            return;
        }
    }
    
    if (isSubmitting) {
        toast('正在提交中，请稍候...');
        return;
    }
    
    isSubmitting = true;
    document.getElementById('submitBtn').disabled = true;
    document.getElementById('submitBtn').textContent = '提交中...';
    
    ajax('Da.php?do=idati&act=submit', {
        exam_id: examId,
        answers: JSON.stringify(answers),
        stime: formatDate(startTime.getTime(), 'Y-m-d H:i:s')
    }, function(res) {
        if (res.code === 1) {
            // 清除本地缓存
            deleteCookie('exam_' + examId + '_answers');
            deleteCookie('exam_' + examId + '_start');
            
            // 清除自动保存定时器
            if (autoSaveTimer) {
                clearInterval(autoSaveTimer);
            }
            
            showModal('提交成功', '<p>您的得分：<span style="color: #1890ff; font-size: 24px;">' + res.data.score + '</span></p>', [
                {text: '查看记录', class: 'btn-primary', onclick: 'location.href="Da.php?do=ilist"'}
            ]);
        } else {
            isSubmitting = false;
            document.getElementById('submitBtn').disabled = false;
            document.getElementById('submitBtn').textContent = '完成答题';
            toast(res.msg);
        }
    });
}

// 更新倒计时
function updateTimeRemain() {
    if (!examData.time_limit || examData.time_limit <= 0) return;
    
    var elapsed = Math.floor((new Date().getTime() - startTime.getTime()) / 1000);
    var remain = examData.time_limit * 60 - elapsed;
    
    if (remain <= 0) {
        document.getElementById('timeRemain').innerHTML = '<span style="color: #ff4d4f;">时间已到</span>';
        document.getElementById('submitBtn').disabled = true;
        document.getElementById('submitBtn').style.background = '#ccc';
        showModal('时间到', '答题时间已到，不可提交！', [
            {text: '知道了', class: 'btn-default', onclick: 'closeModal()'}
        ]);
    } else {
        var minutes = Math.floor(remain / 60);
        var seconds = remain % 60;
        document.getElementById('timeRemain').innerHTML = '剩余时间：<strong style="color: ' + (remain < 300 ? '#ff4d4f' : '#52c41a') + '">' + minutes + '分' + seconds + '秒</strong>';
    }
}

// 页面加载完成后初始化
init();

// 页面关闭前保存
window.addEventListener('beforeunload', function() {
    saveAnswersToLocal();
});
</script>

<?php require_once './inc/foot.php'; ?>
