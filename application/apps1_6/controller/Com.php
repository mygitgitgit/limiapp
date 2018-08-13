<?php
namespace app\apps1_6\controller;
use think\Db;
class Com extends Common
{

    public function _initialize(){
        #权限检测，只有配置了权限的模块才会检测
        $this->Auth();
    }


    public function videoDetail(){
        $id=input('post.id');
        $token=input('post.token');
        $video_id=input('post.video_id');
        $res=$this->dealVideoDetail($video_id,$id,$token);
        if($res['status']){
            isset($res['msg'])?:$res['msg']='';
            isset($res['code'])?:$res['code']=NULL;
            isset($res['sub_msg'])?:$res['sub_msg']='';
            isset($res['sub_code'])?:$res['sub_code']=NULL;
            return apiError($res['msg'],'',$res['code'],$res['sub_msg'],$res['sub_code']);
        }
       apiSuccess('视频详情',$res['data']);
    }
    

}