<?php
/**
 * 本文件功能: 公共PHP函数库
 * 作者信息: 15058593138@qq.com(手机号同微信)
 */

/**
 * JSON提示函数
 * @param int $code 状态码 0失败 1成功
 * @param string $msg 提示信息
 * @param mixed $data 返回数据
 */
function jsonMsg($code, $msg, $data = null) {
    $result = ['code' => $code, 'msg' => $msg];
    if ($data !== null) {
        $result['data'] = $data;
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit();
}

/**
 * 安全过滤函数
 * @param string $str 待过滤字符串
 * @param mysqli $conn 数据库连接
 * @return string 过滤后的字符串
 */
function safeStr($str, $conn = null) {
    $str = trim($str);
    $str = stripslashes($str);
    $str = htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
    if ($conn) {
        $str = $conn->real_escape_string($str);
    }
    return $str;
}

/**
 * 密码加密函数（加盐）
 * @param string $password 原始密码
 * @return string 加密后的密码
 */
function encryptPassword($password) {
    return md5(md5($password) . PASSWORD_SALT);
}

/**
 * 验证密码格式（6-16位数字字母组成）
 * @param string $password 密码
 * @return bool
 */
function checkPassword($password) {
    return preg_match('/^[a-zA-Z0-9]{6,16}$/', $password);
}

/**
 * 编码转换函数
 * @param string $str 字符串
 * @param string $from 源编码
 * @param string $to 目标编码
 * @return string
 */
function convertEncoding($str, $from = 'GBK', $to = 'UTF-8') {
    if (function_exists('mb_convert_encoding')) {
        return mb_convert_encoding($str, $to, $from);
    } elseif (function_exists('iconv')) {
        return iconv($from, $to . '//IGNORE', $str);
    }
    return $str;
}

/**
 * CSV数据导入函数
 * @param string $file 文件路径
 * @param mysqli $conn 数据库连接
 * @param string $table 表名
 * @param array $fields 字段映射
 * @return array 返回成功和失败数量
 */
function importCsvData($file, $conn, $table, $fields) {
    $success = 0;
    $fail = 0;
    
    if (!file_exists($file)) {
        return ['success' => 0, 'fail' => 0, 'msg' => '文件不存在'];
    }
    
    $handle = fopen($file, 'r');
    if (!$handle) {
        return ['success' => 0, 'fail' => 0, 'msg' => '文件打开失败'];
    }
    
    while (($data = fgetcsv($handle, 1000, "\t")) !== false) {
        if (count($data) < count($fields)) {
            $fail++;
            continue;
        }
        
        $values = [];
        foreach ($fields as $i => $field) {
            $values[$field] = safeStr($data[$i], $conn);
        }
        
        // 构建插入SQL
        $fieldStr = implode(',', array_keys($values));
        $valueStr = "'" . implode("','", array_values($values)) . "'";
        $sql = "INSERT INTO {$table} ({$fieldStr}) VALUES ({$valueStr})";
        
        if ($conn->query($sql)) {
            $success++;
        } else {
            $fail++;
        }
    }
    
    fclose($handle);
    return ['success' => $success, 'fail' => $fail];
}

/**
 * 获取客户端IP地址
 * @return string
 */
function getClientIp() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    return $ip;
}

/**
 * 检查用户登录状态
 * @return array|false 返回用户信息或false
 */
function checkLogin() {
    session_start();
    if (isset($_SESSION['user']) && isset($_SESSION['login_time'])) {
        // 检查会话是否过期
        if (time() - $_SESSION['login_time'] > SESSION_EXPIRE) {
            session_destroy();
            return false;
        }
        // 更新最后活动时间
        $_SESSION['login_time'] = time();
        return $_SESSION['user'];
    }
    return false;
}

/**
 * 设置用户登录
 * @param array $user 用户信息
 */
function setLogin($user) {
    session_start();
    $_SESSION['user'] = $user;
    $_SESSION['login_time'] = time();
}

/**
 * 用户退出登录
 */
function logout() {
    session_start();
    session_destroy();
}

/**
 * 生成随机字符串
 * @param int $length 长度
 * @return string
 */
function randomStr($length = 8) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $str = '';
    for ($i = 0; $i < $length; $i++) {
        $str .= $chars[rand(0, strlen($chars) - 1)];
    }
    return $str;
}

/**
 * 读取JSON文件
 * @param string $file 文件路径
 * @return array|false
 */
function readJson($file) {
    if (!file_exists($file)) {
        return false;
    }
    $content = file_get_contents($file);
    return json_decode($content, true);
}

/**
 * 写入JSON文件
 * @param string $file 文件路径
 * @param mixed $data 数据
 * @return bool
 */
function writeJson($file, $data) {
    $content = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    return file_put_contents($file, $content) !== false;
}

/**
 * 计算得分
 * @param array $questions 题目数组
 * @param array $answers 用户答案
 * @param int $mode 计分模式
 * @return array 返回得分详情
 */
function calculateScore($questions, $answers, $mode) {
    $details = [];
    $totalScore = 0;
    
    foreach ($questions as $qid => $question) {
        $userAnswer = isset($answers[$qid]) ? $answers[$qid] : '';
        $correctAnswer = $question['answer'];
        $score = 0;
        
        if ($mode == SCORE_MODE_1) {
            // 模式1: 全对得分
            if ($question['type'] == 'multi') {
                // 多选题
                $userArr = is_array($userAnswer) ? $userAnswer : explode(',', $userAnswer);
                $correctArr = is_array($correctAnswer) ? $correctAnswer : explode(',', $correctAnswer);
                sort($userArr);
                sort($correctArr);
                if ($userArr == $correctArr) {
                    $score = $question['score'];
                }
            } else {
                // 单选题和对错题
                if ($userAnswer == $correctAnswer) {
                    $score = $question['score'];
                }
            }
        } elseif ($mode == SCORE_MODE_2) {
            // 模式2: 调查模式顺序得分
            if ($question['type'] == 'multi') {
                // 多选题计选中个数
                $userArr = is_array($userAnswer) ? $userAnswer : explode(',', $userAnswer);
                $score = count($userArr);
            } else {
                // 单选题和对错题按选项顺序赋分
                $score = ord($userAnswer) - ord('A');
            }
        } elseif ($mode == SCORE_MODE_3) {
            // 模式3: 答题模式2
            if ($question['type'] == 'multi') {
                // 多选题逐项对比
                $userArr = is_array($userAnswer) ? $userAnswer : explode(',', $userAnswer);
                $correctArr = is_array($correctAnswer) ? $correctAnswer : explode(',', $correctAnswer);
                $correct = 0;
                $wrong = 0;
                foreach ($userArr as $ans) {
                    if (in_array($ans, $correctArr)) {
                        $correct++;
                    } else {
                        $wrong++;
                    }
                }
                $score = max(0, $correct - $wrong);
            } else {
                // 单选题和对错题全对得分
                if ($userAnswer == $correctAnswer) {
                    $score = $question['score'];
                }
            }
        }
        
        $details[$qid] = [
            'answer' => $correctAnswer,
            'user' => $userAnswer,
            'score' => $score
        ];
        $totalScore += $score;
    }
    
    return ['details' => $details, 'total' => $totalScore];
}
