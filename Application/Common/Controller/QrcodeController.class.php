<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/5/1 0001
 * Time: 上午 10:29
 */

namespace Common\Controller;
use Think\Controller;

Vendor('phpqrcode.phpqrcode');

class QrcodeController extends Controller
{
    public function qrcode($url,$size = 6,$error = 'L',$logo,$qr)
    {
        $value = $url;//二维码内容
        $errorCorrectionLevel = $error;// 纠错级别：L、M、Q、H
        $matrixPointSize = $size;// 点的大小：1到10
        //生成二维码图片
        \QRcode::png($value, $qr, $errorCorrectionLevel, $matrixPointSize, 2);
        $logo = $logo;//准备好的logo图片
        $QR = $qr;//已经生成的原始二维码图

        if ($logo !== FALSE) {
            $QR = imagecreatefromstring(file_get_contents($QR));
            $logo = imagecreatefromstring(file_get_contents($logo));
//            if (imageistruecolor($logo))
//            {
//                imagetruecolortopalette($logo, false, 65535);//添加这行代码来解决颜色失真问题
//            }
            $QR_width = imagesx($QR);//二维码图片宽度
            $QR_height = imagesy($QR);//二维码图片高度
            $logo_width = imagesx($logo);//logo图片宽度
            $logo_height = imagesy($logo);//logo图片高度
            $logo_qr_width = $QR_width / 5;
            $scale = $logo_width / $logo_qr_width;
            $logo_qr_height = $logo_height / $scale;
            $from_width = ($QR_width - $logo_qr_width) / 2;
            //重新组合图片并调整大小
            imagecopyresampled($QR, $logo, $from_width, $from_width, 0, 0, $logo_qr_width,
                $logo_qr_height, $logo_width, $logo_height);
        }
        //输出图片

        return imagepng($QR,$qr) ;
    }

    public function create_qrcode($url,$size = 4,$error = 'L',$logo,$dir=''){
        //$url = urldecode($url);
        if (empty($dir))
            $dir = "./Public/upload/Qrcode/". date("Y-m-d"); //保存路径
        dir_create($dir); //创建目录
        $dir .= '/' . time()  . '.jpg'; //文件路径
        $qr = $this->qrcode($url, $size, $error, $logo, $dir);   //生成二维码
        $qr_path = 'http://' . $_SERVER['HTTP_HOST'] . substr($dir, 1);  //获取完整二维码路径
        return $qr_path;
    }

    public function create_qrcode_encode($url,$size = 4,$error = 'L',$logo,$qr){
        $url = urldecode($url);
        $dir = "./Public/upload/Qrcode/". date("Y-m-d"); //保存路径
        dir_create($dir); //创建目录
        $dir .= '/' . time()  . '.jpg'; //文件路径
        $qr = $this->qrcode($url, $size, $error, $logo, $dir);   //生成二维码
        $qr_path = 'http://' . $_SERVER['HTTP_HOST'] . substr($dir, 1);  //获取完整二维码路径
        return $qr_path;
    }


    public static function qrcode_png($data){
        // 纠错级别：L、M、Q、H
        $level = 'L';
        // 点的大小：1到10,用于手机端4就可以了
        $size = 4;
        // 下面注释了把二维码图片保存到本地的代码,如果要保存图片,用$fileName替换第二个参数false
        //$path = "images/";
        // 生成的文件名
        //$fileName = $path.$size.'.png';
        echo '<img src="'.\QRcode::png($data, false, $level, $size).'"/>';
    }

    /**
     * 生成带背景图的二维码
     * @param string $qrcode_img   二维码
     * @param string $bg_img       背景图
     * @param int $dst_x           拷贝图x轴偏差
     * @param int $dst_y           拷贝图y轴偏差
     */
    public function qrcode_bg($qrcode_img='',$bg_img='',$dst_x=0,$dst_y=0){
        $bigImgPath = $bg_img ? $bg_img : 'http://'.$_SERVER['HTTP_HOST'].'/Public/images/qrcode_bg.jpg';
        $qCodePath = $qrcode_img;

        $bigImg = imagecreatefromstring(file_get_contents($bigImgPath));
        $qCodeImg = imagecreatefromstring(file_get_contents($qCodePath));

        list($qCodeWidth, $qCodeHight, $qCodeType) = getimagesize($qCodePath);
        // imagecopymerge使用注解
        imagecopymerge($bigImg, $qCodeImg, $dst_x, $dst_y, 0, 0, $qCodeWidth, $qCodeHight, 100);

        list($bigWidth, $bigHight, $bigType) = getimagesize($bigImgPath);


        switch ($bigType) {
            case 1: //gif
                header('Content-Type:image/gif');
                imagegif($qCodeImg);
                break;
            case 2: //jpg
                header('Content-Type:image/jpg');
                imagejpeg($qCodeImg);
                break;
            case 3: //jpg
                header('Content-Type:image/png');
                imagepng($qCodeImg);
                break;
            default:
                # code...
                break;
        }

        imagedestroy($bigImg);
        imagedestroy($qCodeImg);
    }

}