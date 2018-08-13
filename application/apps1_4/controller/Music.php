<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/5/17 0017
 * Time: 17:54
 */

namespace app\apps1_4\controller;
use app\com\controller\Aliyunoss;
use app\com\controller\MusicFile;
use think\Db;

class Music extends Common
{
    public function _initialize(){

    }

    public function musicList(){

        $page=input('get.page');
        $type=input('get.type','hot');
        $name=input('get.name');

        if($name!=''){
            $d=[];
            $d['name']=['like','%'.$name.'%'];
            $res=Db::name('music')
                ->where($d)
                ->select();
            if($res){
                foreach ($res as $k=>&$v){
                    if($v['pic']){
                        $v['pic']=AliUrl.$v['pic'];
                    }
                    if($v['music']){
                        $v['music']=AliUrl.$v['music'];
                    }

                }
                return apiSuccess('',$res);
            }else{
                return apiSuccess('没有相关的内容');
            }
        }
        if($page==''||$page<1){
            apiError('请求页码有误');
            return;
        }
        if($type=='new'){
            $res=Db::name('music')
                ->where('delete_time')
                ->page($page,21)
                ->order('create_time desc')
                ->select();
        }elseif ($type=='hot'){
            $res=Db::name('music')
                ->where('delete_time')
                ->page($page,21)
                ->order('down_num desc')
                ->select();
        }else{
            return apiError();
        }
        foreach ($res as $k=>&$v){
            if($v['pic']){
                $v['pic']=AliUrl.$v['pic'];
            }
            if($v['music']){
                $v['music']=AliUrl.$v['music'];
            }

        }
        return apiSuccess('',$res);

    }

    /**
     * 搜索音乐
     * 不用
     */
    public function searchMusic(){

        $name=input('post.name');
        $d=[];
        if($name!=''){
            $d['name']=['like','%'.$name.'%'];
        }else{
            apiError('查询内容不能为空');
        }
        $res=Db::name('music')->where($d)->select();
        if($res){
            foreach ($res as $k=>&$v){
                if($v['pic']){
                    $v['pic']=AliUrl.$v['pic'];
                }
                if($v['music']){
                    $v['music']=AliUrl.$v['music'];
                }
            }
            return apiSuccess('',$res);
        }else{
            return apiSuccess('没有相关的内容');
        }
    }

    /**
     * 同款音乐
     */
    public function sameMusic(){
        $music_id=input('music_id');
        if($music_id==''){
            return apiError('音乐id不能为空');
        }
        $music=Db::name('music')
            ->where('id',$music_id)
            ->field('name,music,pic,down_num')
            ->find();
        if($music){
            $music['music']=AliUrl.$music['music'];
            $music['pic']=AliUrl.$music['pic'];
        }
        $data['music']=$music;
        $video=Db::name('video')
            ->where('music_id',$music_id)
            ->field('id,user_id,video,video_cover,create_time,click_num')
            ->order('click_num desc,create_time desc')
            ->select();
        if($video){
            foreach ($video as & $v){
                //响应的发布时间
                $v['create_time']=$this->timeToHour($v['create_time']);
            }
        }
        $data['video']=$video;
        return apiSuccess('',$data);
    }

    /**
     * 下载的音乐id
     */
    public function musicId(){
        $id=input('id');
        if($id){
            $res=Db::name('music')->where('id',$id)->setInc('down_num',1);
            if($res){
                return apiSuccess('下载成功');
            }
        }
    }

    /**
     * 下载到本地 public/
     */
    public function downloadMusic2(){

        //下载的文件路径
        $id=input('id');
        $object=input('music');  // /music/music/....
        if(substr($object,0,1)=='/'){
            $object=substr($object,1);   //  music/music/....
        }
        $localfile=substr($object,strrpos($object,'/')+1);  //1526635494_2071.mp3
        $Aliyunoss=new Aliyunoss();
        $res=$Aliyunoss->downloadFile2($object,$localfile);
        if($res['status']==1){
            Db::name('music')->where('id',$id)->setInc('down_num',1);
            apiSuccess($res['message']);
            return;
        }else{
            apiError($res['message']);
            return;
        }
    }
    /**
     * 下载到内存
     */
    public function downloadMusic1(){

        //下载的文件路径
        $id=input('id');
        $object=input('music');  // /music/music/....
        if(substr($object,0,1)=='/'){
            $object=substr($object,1);   //  music/music/....
        }
        $Aliyunoss=new Aliyunoss();
        $res=$Aliyunoss->downloadFile($object);
        if($res['status']==1){
            Db::name('music')->where('id',$id)->setInc('down_num',1);
                apiSuccess($res['message'],$res['content']);
                return;
        }else{
             apiError($res['message'],$res['content']);
             return;
        }
    }
}