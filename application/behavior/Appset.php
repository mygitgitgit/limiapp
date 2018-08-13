<?php
namespace app\behavior;
use think\Db;
/**
 * Created by PhpStorm.
 * User: zyjun
 * Date: 2018/3/23
 * Time: 11:51
 */

/***************定义全局常量*******************************/
define('ApiUrl','http://testcloud.youhongtech.com');  //app资源存储域名 相关接口，[文件读取相关接口]
define('AppUrl','http://testapp.youhongtech.com');  //app接口调用域名 ：相关接口，[微信，支付宝回调]
define('AliGreenNoticefyUrl','http://testapp.youhongtech.com/index.php/callback');  //阿里云内容鉴定回调地址
define('QiniuBucket','testlimiapp');  //七牛云存储上传空间名称
define('WyImPrefix','testlimiapp');  //网易云通讯accid前缀
define('RedisDb',1);  //redis使用的db
define('AliUrl','http://oss.youhongtech.com');
define('AliVideoUrl','http://video.youhongtech.com');
define('SubApiMsg',true); //SubApiMsg=true，显示submsg和code,展示具体报错原因   false:只返回msg字段大概错误原因和code大概状态码用于查看，不显示具体原因


/**
 * Class Server
 * @package apps\behavior系统运维检测
 */

class Appset{
    //入口文件  run
    public function run(){
  $ss=0;
    }
}



