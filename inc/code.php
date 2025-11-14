<?php
/**
 * 本文件功能: 验证码生成类
 * 作者信息: 15058593138@qq.com(手机号同微信)
 */

session_start();

class Code {
    private $width;
    private $height;
    private $code;
    private $image;
    
    /**
     * 构造函数
     * @param int $width 宽度
     * @param int $height 高度
     */
    public function __construct($width = 100, $height = 40) {
        $this->width = $width;
        $this->height = $height;
    }
    
    /**
     * 生成验证码
     */
    public function make() {
        // 生成随机验证码
        $this->code = $this->randomCode(4);
        $_SESSION['verify_code'] = strtolower($this->code);
        
        // 创建图像
        $this->image = imagecreatetruecolor($this->width, $this->height);
        
        // 填充背景色
        $bgColor = imagecolorallocate($this->image, 240, 240, 240);
        imagefill($this->image, 0, 0, $bgColor);
        
        // 添加干扰线
        for ($i = 0; $i < 5; $i++) {
            $lineColor = imagecolorallocate($this->image, rand(150, 200), rand(150, 200), rand(150, 200));
            imageline($this->image, rand(0, $this->width), rand(0, $this->height), 
                     rand(0, $this->width), rand(0, $this->height), $lineColor);
        }
        
        // 添加干扰点
        for ($i = 0; $i < 50; $i++) {
            $pointColor = imagecolorallocate($this->image, rand(150, 200), rand(150, 200), rand(150, 200));
            imagesetpixel($this->image, rand(0, $this->width), rand(0, $this->height), $pointColor);
        }
        
        // 写入验证码文字
        for ($i = 0; $i < strlen($this->code); $i++) {
            $textColor = imagecolorallocate($this->image, rand(0, 100), rand(0, 100), rand(0, 100));
            $x = ($this->width / strlen($this->code)) * $i + 10;
            $y = rand($this->height / 2, $this->height - 10);
            imagestring($this->image, 5, $x, $y, $this->code[$i], $textColor);
        }
        
        // 输出图像
        header('Content-Type: image/png');
        imagepng($this->image);
        imagedestroy($this->image);
    }
    
    /**
     * 生成随机验证码
     * @param int $length 长度
     * @return string
     */
    private function randomCode($length) {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $chars[rand(0, strlen($chars) - 1)];
        }
        return $code;
    }
    
    /**
     * 验证验证码
     * @param string $code 用户输入的验证码
     * @return bool
     */
    public static function check($code) {
        if (!isset($_SESSION['verify_code'])) {
            return false;
        }
        return strtolower($code) == $_SESSION['verify_code'];
    }
}

// 直接访问时生成验证码
if (basename($_SERVER['PHP_SELF']) == 'code.php') {
    $code = new Code();
    $code->make();
}
