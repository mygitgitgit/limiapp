<?php
namespace app\behavior正式版;
use think\Db;
use think\Request;
/**
 * Created by PhpStorm.
 * User: zyjun
 * Date: 2018/3/23
 * Time: 11:51
 */

/***************定义全局常量*******************************/
define('ApiUrl','http://cloud.youhongtech.com');  //app资源存储域名 相关接口，[文件读取相关接口]
define('AppUrl','http://app.youhongtech.com');  //app接口调用域名 ：相关接口，[微信，支付宝回调]
define('AliGreenNoticefyUrl','http://admin.youhongtech.com/index.php/index');  //阿里云内容鉴定回调地址
define('QiniuBucket','limiapp');  //七牛云存储上传空间名称
define('WyImPrefix','limiapp');  //网易云通讯accid前缀
define('RedisDb',0);  //redis使用的db
define('REQUEST_PATH',Request::instance()->path());

/**
 * Class Server
 * @package apps\behavior系统运维检测
 */

class Appset{
    //入口文件  run
    public function run(){

    }
}



