<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/5/17 0017
 * Time: 17:54
 */

namespace app\apps1_7\controller;
use app\com\controller\Aliyunoss;
use think\Db;

class statistics extends Common
{
    public function _initialize(){

    }

    /**
     * Created by zyjun
     * Info:记录用户点击事件
     */
    public function userClick(){
         $eventCode=input('event_code');//监测事件的代码
         $uid=input('id');
         $token=input('token');
         if(empty($eventCode)){
             return apiError('监测code不能为空');
         }
         $res=$this->checkToken($uid,$token);
         if($res['status']){
            return apiError($res['msg']);
         }
        $res=Db::name('statistics')->where('uid',$uid)->where('code',$eventCode)->find();
         if(empty($res)){
             $data['uid']=$uid;
             $data['code']=$eventCode;
             $res=$this->statisticsCode($eventCode);
             if($res['status']){
                 return apiError('事件状态码异常');
             }
             $data['des']=$res['data'];
             Db::name('statistics')->insert($data);
         }else{
             Db::name('statistics')->where('uid',$uid)->where('code',$eventCode)->setInc('click_num');
         }
         apiSuccess('统计成功');
    }
}