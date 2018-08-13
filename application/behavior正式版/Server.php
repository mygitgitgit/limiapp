<?php
namespace app\behavior正式版;
use think\Db;
/**
 * Created by PhpStorm.
 * User: zyjun
 * Date: 2018/3/23
 * Time: 11:51
 */

/**
 * Class Server
 * @package apps\behavior系统运维检测
 */
class Server{
    public function run(){
     $this->stopApp();
    }

    public function stopApp(){
        //    9000:系统维护中，停止运行  9001：部分功能禁用，
       $res=Db::name('sys_set')->where('key',5)->find();
       if(empty($res)){
           return;
       }
       $data=json_decode($res['data'],true);
       $status=$data['status'];
       $content=$data['content'];
        if($status==1){
            Success($content,'',9000);
            exit();
        }
        if($status==2){
            //部分功能禁用，判断用户是哪个控制器进来的，如果是周末游直接exit;
            Success('周末游功能维护中，暂时无法使用','',9001);
            exit();
        }
    }

}