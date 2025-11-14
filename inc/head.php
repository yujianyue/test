<?php
/**
 * 本文件功能: 公共头部文件
 * 作者信息: 15058593138@qq.com(手机号同微信)
 */

// 检查用户登录状态
$user = checkLogin();
if (!$user) {
    header('Location: ' . $_SERVER['PHP_SELF'] . '?do=login');
    exit();
}

// 获取当前do参数
$currentDo = isset($_GET['do']) ? $_GET['do'] : '';

// 获取菜单（根据用户类型）
$userType = $user['usid'] === 'admin' ? 'admin' : 'user';
$menus = isset($MENUS[$userType]) ? $MENUS[$userType] : [];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo isset($pageTitle) ? $pageTitle : '答题系统'; ?></title>
    <link rel="stylesheet" href="./inc/pubs.css?v=<?php echo VERSION; ?>">
    <script src="./inc/pubs.js?v=<?php echo VERSION; ?>"></script>
</head>
<body>
    <div class="header">
        <div class="header-top">
            <div class="header-title"><?php echo isset($pageTitle) ? $pageTitle : '答题系统'; ?></div>
            <div class="header-user">
                <?php echo htmlspecialchars($user['name']); ?>
                <button class="btn btn-default" style="margin-left: 10px;" onclick="logout()">退出</button>
            </div>
        </div>
        <?php if (!empty($menus)): ?>
        <div class="nav-tabs">
            <?php foreach ($menus as $menu): ?>
            <a href="?do=<?php echo $menu['do']; ?>" class="nav-tab <?php echo $currentDo === $menu['do'] ? 'active' : ''; ?>">
                <?php echo $menu['name']; ?>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <div class="container">
        
<script>
function logout() {
    ajax('<?php echo $_SERVER['PHP_SELF']; ?>?do=lgout', {}, function(res) {
        if (res.code === 1) {
            location.href = '<?php echo $_SERVER['PHP_SELF']; ?>?do=login';
        } else {
            toast(res.msg);
        }
    });
}
</script>
