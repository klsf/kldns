<?php
session_start();
function random($len)
{
    $srcstr = '1a2s3d4f5g6hj8k9qAwBeCDrEFtGyHuIpJ0KzLxOQPRcSOVUTvWbXnYmZ';
    mt_srand();
    $strs = '';
    for ($i = 0; $i < $len; $i++) {
        $strs .= $srcstr[mt_rand(0, 30)];
    }
    return $strs;
}
//随机生成的字符串
$str = random(4);
//验证码图片的宽度
$width = 60;
//验证码图片的高度
$height = 25;
//声明需要创建的图层的图片格式
@header('Content-Type:image/png');
//创建一个图层
$im = imagecreate($width, $height);
//背景色
$back = imagecolorallocate($im, 255, 255, 255);
//模糊点颜色
$pix = imagecolorallocate($im, 187, 230, 247);
//字体色
$font = imagecolorallocate($im, 41, 163, 238);
//绘模糊作用的点
mt_srand();
for ($i = 0; $i < 1000; $i++) {
    imagesetpixel($im, mt_rand(0, $width), mt_rand(0, $height), $pix);
}
//输出字符
imagestring($im, 5, 7, 5, $str, $font);
//输出矩形
imagerectangle($im, 0, 0, $width - 1, $height - 1, $font);
//输出图片
imagepng($im);
imagedestroy($im);

$str = md5(strtolower($str));
//选择 cookie
SetCookie("verification", $str, time() + 7200, "/");
//选择 Session
//$_SESSION['verification'] = $str;

