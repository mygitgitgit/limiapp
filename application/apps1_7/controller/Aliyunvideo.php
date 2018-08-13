<?php
/**
 * 这个类接口暂时没用，app直接以sts方式获取权限
 */
namespace app\apps1_7\controller;
use app\com\controller\Alivideo;
use think\Controller;
use think\Request;
use think\Db;

class Aliyunvideo extends Common
{

    /**
     * Created by zyjun
     * Info:初始化
     */
    public function _initialize(){

    }


/**********************业务逻辑***************************/
    /**
     * Created by zyjun
     * Info:获取播放凭证
     */
    public function shortVideoGetPlayAuth(){
        $id=input('post.id'); //资源id
        if(empty($id)){
            return apiError('缺少id参数');
        }
        if($this->checkInt($id,'','')){
            return apiError('ID参数错误');
        }
        $videoId=Db::name('video')->where('id',$id)->where('status',0)->value('video');
        if(empty($videoId)){
            return apiError('视频资源不存在');
        }
        $obj=new Alivideo();
        $res=$obj->getPlayAuth($videoId);
        if($res['status']){
           return apiError('获取播放凭证失败');
        }
        $auth=json_decode(base64_decode($res['data']['PlayAuth']),true);  //解析base64auth授权码
        $res['data']['playAuthDecode']=$auth;
        apiSuccess('获取播放凭证成功',$res['data']);
    }


    /**
     * Created by zyjun
     * Info: 创建视频上传凭证和地址
     */
    public function shortVideoCreateUpload(){
        //验证token，是否认证
        $id=input('id');
        $token=input('token');
        $res=$this->checkToken($id,$token);
        if($res['status']){
            return apiError($res['msg'],'',$res['code']);
        }
        #验证权限
        $res=$this->isAccess($id);
        if($res['identity_status']!=2){
            return apiError($res['msg'],$res['identity_status']);
        }
         //获取数据
        $title=input('post.title');
        $fileName=input('post.fileName'); //包含后缀
        $fileDes=input('post.fileDes');
        $fileCover=input('post.fileCover');//视频封面上传后返回的url  'https://oss-cn-shanghai.aliyuncs.com/image/cover/750F7DA8D22D4B428618F04DAA6EAA31-6-2.jpeg'
        $tags=input('post.tags');
        //处理数据
        $allow_type=['3gp', 'asf', 'avi', 'dat', 'dv', 'flv', 'f4v', 'gif', 'm2t', 'm3u8', 'm4v', 'mj2', 'mjpeg', 'mkv', 'mov', 'mp4', 'mpe', 'mpg', 'mpeg', 'mts', 'ogg', 'qt', 'rm', 'rmvb', 'swf', 'ts', 'vob', 'wmv', 'webm'];
        if(empty($title)){
            return apiError('标题未填写');
        }
        if(empty($fileName)){
            return apiError('视频类型错误');
        }
        $video_name=explode('.',$fileName);
        $video_length=count($video_name);
        if($video_length<2){
            return apiError('视频类型错误');
        }
        $video_type=$video_name[$video_length-1];
        if(!in_array($video_type,$allow_type)){
            return apiError('视频类型错误');
        }
        if(strlen($title)>128){
            return apiError('标题长度超过限制');
        }
        if(strlen($fileName)>256){
            return apiError('视频名称长度超过限制');
        }
        if(strlen($fileDes)>1024){
            return apiError('视频描述长度超过限制');
        }
        if(strlen($tags)>50){
            return apiError('视频标签长度超过限制');
        }
        if(strlen($fileCover)>1024){
            return apiError('视频封面长度超过限制');
        }
        if(!empty($fileCover)){
            if($this->checkVideoCover($fileCover)){
                return apiError('视频封面URL格式错误');
            }
        }
        $fileSize='';
        $obj=new Alivideo();
        $obj->videoCat='693233331'; //点播系统后台固定设置的分类
        $obj->videoUpLoadIp='127.0.0.1';
        $res=$obj->createUploadVideo($title,$fileName,$fileSize,$fileDes,$fileCover,$tags);
        if($res['status']){
            return apiError('获取上传地址和凭证失败');
        }
        $auth=json_decode(base64_decode($res['data']['auth']),true);  //解析base64auth授权码
        $res['data']['authDecode']=$auth;
        apiSuccess('获取上传地址和凭证成功',$res['data']);
    }

    /**
     * Created by zyjun
     * Info:刷新某个视频id的上传权限
     */
    public function refreshShortVideoCreateUpload(){
        $id=input('id');
        $token=input('token');
        $videoId=input('videoId');
        $res=$this->checkToken($id,$token);
        if($res['status']){
            return apiError($res['msg'],'',$res['code']);
        }
        $res=$this->identityStatus($id);
        if($res['status']){
            return apiError($res['msg'],'',$res['code']);
        }
        $obj=new Alivideo();
        $res=$obj->refreshUploadVideo($videoId);
        if($res['status']){
            return apiError('刷新上传地址和凭证失败');
        }

        apiSuccess('刷新上传地址和凭证成功',$res['data']);


    }


    /**
     * Created by zyjun
     * Info: 创建图片上传凭证和地址
     * 先上传图片，图片上传成功后，把返回的图片地址作为封面传递给createUploadVideo()函数，继续上传视频
     */
    public function shortVdeoCreateUploadImage(){
        //验证token，是否认证
        $id=input('id');
        $token=input('token');
        $res=$this->checkToken($id,$token);
        if($res['status']){
            return apiError($res['msg'],'',$res['code']);
        }
        $res=$this->identityStatus($id);
        if($res['status']){
            return apiError($res['msg'],'',$res['code']);
        }
        //获取数据
        $title=input('post.title');
        $imageType='cover'; //用于封面
        $imageExt=input('post.imageExt');
        //处理参数
        if(empty($title)){
            return apiError('标题未填写');
        }
        if(empty($imageType)){
            return apiError('图片类型错误');
        }
        if(strlen($title)>128){
            return apiError('标题长度超过限制');
        }
        if(!in_array($imageExt,['png','jpg','jpeg'])){
            return apiError('图片类型错误');
        }
        $tags=input('post.tags');
        if(!empty($tags)){
            if(strlen($title)>50){
                return apiError('标签长度超过限制');
            }
        }
        $obj=new Alivideo();
        $res=$obj->createUploadImage($title,$imageType,$imageExt,$tags);
        if($res['status']){
            return apiError('获取图片上传地址和凭证失败');
        }
        $auth=json_decode(base64_decode($res['data']['UploadAuth']),true);  //解析base64auth授权码
        $res['data']['uploadAuthDecode']=$auth;
        apiSuccess('获取图片上传地址和凭证成功',$res['data']);
    }


    public function shortVideoGetInfo(){
        $obj=new Alivideo();
        $res=$obj->getVideoInfo('a5b320ca571447fb9129939d5555ec51');
        $ss=0;
    }

}