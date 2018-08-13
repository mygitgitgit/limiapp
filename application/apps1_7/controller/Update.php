<?php
namespace app\apps1_7\controller;
use think\Db;
use think\Request;

class Update extends Common
{

    /**
     * Created by zyjun
     * Info:获取最新版本号,APP-设置-升级
     */
    public function getNewVersion($device){
        if(empty($device)){
            $return['status']=1;
            $return['msg']='缺少参数';
            return  $return;
        }
        if(!in_array($device,array('android','ios'))){
            $return['status']=1;
            $return['msg']='参数错误';
            return $return;
        }
        $res= Db::name('update_history')->where('device',$device)->where('status',0)->order('id desc')->find(); //查找最后一条记录
        $data['version']='V'.$res['version'];
        $data['update_url']=$res['update_url'];
        $data['content']=json_decode($res['content'],true);

        if($device=='android'){
            $return['status']=0;
            $return['msg']='安卓版本信息';
            $return['data']=$data;
            return  $return;
        }
        if($device=='ios'){
            $return['status']=0;
            $return['msg']='IOS版本信息';
            $return['data']=$data;
            return  $return;
        }
    }

    /**
     * Created by zyjun
     * Info:获取启动页广告
     */
    public function getStartAds(){
        return '启动页面广告，暂忽略' ;
    }


    public function isUpdate($device,$version){
        if($device==''&&$version==''){
            $re['msg']='版本参数错误';
            $re['update']=2; //不更新
            $re['status']=1;
            return $re;
        }
        if(!in_array($device,array('android','ios'))){
            $re['msg']='版本参数错误';
            $re['update']=2; //不更新
            $re['status']=1;
            return $re;
        }
        //这里要检测用户版本号，查看新版本是否更新，或者老版本是否不兼容需要更新
        $res= Db::name('update_history')->where('device',$device)->where('status',0)->order('id desc')->find();
        if(empty($res)){
            $msg='不更新';
            $update=2;
        }
        $version=str_replace('V','',$version);
        $ver= $res['version'];
        if(strcmp($version,$ver)<0){
            $msg='更新';
            $update=0;
        }else{
            $msg='不更新';
            $update=2;
        }
        if($device=='android'){
            $re['msg']=$msg;
            $re['update']=$update;
            $re['status']=0;
            $re['version']=$this->getNewVersion('android');  //只获取更新内容
            return $re;
        }
        if($device=='ios'){
            $re['msg']=$msg;
            $re['update']=$update;
            $re['status']=0;
            $re['version']=$this->getNewVersion('ios');
            return $re;
        }
    }



    /**
     * Created by zyjun
     * Info:启动页接口
     */
    public function appStart(){
        $id=input('id');
        $version=input('post.version');
        $device=input('post.device');
        $data['ads']=$this->getStartAds();
        $data['update']=$this->isUpdate($device,$version);
        $this->recordVersion($id,$version,$device);
        apiSuccess('成功',$data);
    }

    /**
     * Created by zyjun
     * Info:记录版本号
     */
    public function recordVersion($id,$version,$device){
        if(!empty($version)){
            Db::name('user')->where('id',$id)->setField('app_version',$version);
        }
        if(!empty($device)){
            Db::name('user')->where('id',$id)->setField('app_device',$device);
        }
    }

}
