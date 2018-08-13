<?php
namespace app\apps2\behavior;
use think\Db;
use think\Request;
define('apps2\REQUEST_PATH',Request::instance()->path());
/**
 * Created by PhpStorm.
 * User: zyjun
 * Date: 2018/3/23
 * Time: 11:51
 */

/**
 * Class Server
 * @package apps\behavior用户权限
 */
class Auth{
    /**
     * Created by zyjun
     * Info:用户禁用或者权限检测
     */
     public function run(){
     $this->userAuth();
    }

    /**
     * Created by zyjun
     * Info:用户权限检测，禁用,暂时写在这里，分类多了后再数据库做权限表
     */
    public function userAuth(){
     $uid=input('id');
     if(!empty($uid)){
         $user_status=Db::name('user')->where('id',$uid)->value('status');
         if($user_status==1){ //用户已经被禁用，不能发帖
//            $forbid=['apps/Publish/sendRedpacket','apps/Publish/publishDynamic','apps/Action/discussAction','apps/topic/addtopic','apps/topic/addTopicAction','apps/topic/discussAction','apps/Im/getToken'];
//            $ss=REQUEST_PATH;
//            if(in_array(REQUEST_PATH,$forbid,false)){
//             Error('您的账户无法使用此功能');
//             exit();
//            }
             Error('您的账户无法使用此功能');
             die();
         }
     }
    }



}