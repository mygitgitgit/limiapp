<?php
/**
 * 暂时不用，目前短视频SDK只能是STS方式上传，标题，标签不上传到阿里云点播。
 */
namespace app\com\controller;
include_once APP_EXTEND.'aliyunOpenapi/aliyun-php-sdk-core/Config.php';
use vod\Request\V20170321 as vod;

class Alivideo
{
    const regionId = 'cn-shanghai';
    const access_key_id='LTAIdUDh7bLbUAqe';
    const access_key_secret='z8drl5bB3dRhA3Jn9qwPgoztTrhIf7';
    public $videoCat; //视频点播系统后台分类
    public $videoUpLoadIp; //上传地址ip
    private $client;


    /**
     * Created by zyjun
     * Info:初始化
     */
    public function  __construct(){
        $profile = \DefaultProfile::getProfile(self::regionId, self::access_key_id, self::access_key_secret);
        $client = new \DefaultAcsClient($profile);
        $this->client=$client;
    }

    /**
     * Created by zyjun
     * Info:公共方法 获取播放凭证
     */
    public function getPlayAuth($video_id){
        try {
            $request = new vod\GetVideoPlayAuthRequest();
            $request->setAcceptFormat('JSON');
            $request->setRegionId(self::regionId);
            $request->setVideoId($video_id);            //视频ID
            $response = $this->client->getAcsResponse($request);
            $re['status']=0;
            $re['data']=$this->objectToArray($response);
            return $re;
        } catch (\Exception $e){
            $re['status']=1;
            $re['msg']=$e->getMessage();
            return $re;
        }
    }

    /**
     * Created by zyjun
     * Info:公共方法 创建视频上传凭证和地址，视频地址为点播系统自建的分类
     */
    public  function createUploadVideo($title,$file_name,$file_size,$file_des,$file_cover,$tags){
        try {
            $request = new vod\CreateUploadVideoRequest();
            //视频源文件标题(必选)
            $request->setTitle($title);
            //视频源文件名称，必须包含扩展名(必选)
            $request->setFileName($file_name); //"文件名称.mov"
            //视频源文件字节数(可选)
            $request->setFileSize($file_size);
            //视频源文件描述(可选)
            $request->setDescription($file_des);
            //自定义视频封面URL地址(可选)
            $request->setCoverURL($file_cover);
            //上传所在区域IP地址(可选)
            $request->setIP($this->videoUpLoadIp);
            //视频标签，多个用逗号分隔(可选)
            $request->setTags($tags);
            //视频分类ID(可选)
            $request->setCateId($this->videoCat);  //int类型
            $response = $this->client->getAcsResponse($request);
            $data['UploadAuth']=$response->UploadAuth;
            $data['UploadAddress']=$response->UploadAddress;
            $data['VideoId']=$response->VideoId;
            $data['requestId']=$response->RequestId;
            $re['status']=0;
            $re['data']=$data;
            return $re;
        } catch (\Exception $e) {
            $re['status']=1;
            $re['msg']=$e->getMessage();
            return $re;
        }
    }


    /**
     * Created by zyjun
     * Info:公共方法 创建图片上传凭证和地址,这里的图片上传没有分类，上传到私有OSS
     */
    public  function createUploadImage($title,$imageType,$imageExt,$tags){
        try {
            $request = new vod\CreateUploadImageRequest();
            $request->setTitle($title);//标题
            $request->setImageType($imageType);  //图片类型。取值范围：default（默认）cover（封面）watermark（水印）
            $request->setImageExt($imageExt); //图片文件扩展名。取值范围：png  jpg  jpeg  默认值：png
            $request->setTags($tags); //图片标签
            $request->setAcceptFormat('JSON');
            $response=$this->client->getAcsResponse($request);
            $re['status']=0;
            $re['data']=$this->objectToArray($response);
            return $re;
        } catch (\Exception $e) {
            $re['status']=1;
            $re['msg']=$e->getMessage();
            return $re;
        }
    }

    /**
     * Created by zyjun
     * Info:刷新某个视频id的上传权限
     */
    public function refreshUploadVideo($videoId)
    {
        try {
            $request = new vod\RefreshUploadVideoRequest();
            $request->setVideoId($videoId);
            $request->setAcceptFormat('JSON');
            $response = $this->client->getAcsResponse($request);
            $re['status'] = 0;
            $re['data'] = $this->objectToArray($response);
            return $re;
        } catch (\Exception $e) {
            $re['status'] = 1;
            $re['msg'] = $e->getMessage();
            return $re;
        }
    }

    /**
     * Created by zyjun
     * Info:获取视频播放信息，包括音频
     */
    public function  getPlayInfo($videoId) {
        try {
            $request = new vod\GetPlayInfoRequest();
            $request->setVideoId($videoId);
            $request->setAuthTimeout(3600*24);    // 播放地址过期时间（只有开启了URL鉴权才生效），默认为3600秒，支持设置最小值为3600秒
            $request->setAcceptFormat('JSON');
            $response = $this->client->getAcsResponse($request);
            $re['status'] = 0;
            $re['data'] = $this->objectToArray($response);
            return $re;
        } catch (\Exception $e) {
            $re['status'] = 1;
            $re['msg'] = $e->getMessage();
            return $re;
        }
    }
    /**
     * Created by zyjun
     * Info:获取视频转码后的mp3音乐
     */
    public function getPlayInfoMp3($videoId){
        try {
            $request = new vod\GetPlayInfoRequest();
            $request->setVideoId($videoId);
            $request->setAuthTimeout(3600*24);    // 播放地址过期时间（只有开启了URL鉴权才生效），默认为3600秒，支持设置最小值为3600秒
            $request->setAcceptFormat('JSON');
            $response = $this->client->getAcsResponse($request);
            $response=$this->objectToArray($response);
            $mp3=$response['PlayInfoList']['PlayInfo'];
            if(!empty($mp3)){
                foreach ($mp3 as $key=>$val){
                    if($val['Format']=='mp3'){
                        $data['mp3_url']=$val['PlayURL'];
                        $data['mp3_duration']=$val['Duration'];
                        $re['status'] = 0;
                        $re['data'] =$data;
                        return $re;
                    }
                }
            }
            $re['status'] = 1;
            $re['msg'] ='未找到mp3';
            return $re;
        } catch (\Exception $e) {
            $re['status'] = 1;
            $re['msg'] = $e->getMessage();
            return $re;
        }
    }
    /**
     * Created by zyjun
     * Info:获取点播视频信息[不包含视频地址]
     */
    public function getVideoInfo($videoId){
        try {
            $request = new vod\GetVideoInfoRequest();
            $request->setVideoId($videoId);
            $request->setAcceptFormat('JSON');
            $response = $this->client->getAcsResponse($request);
            $re['status'] = 0;
            $re['data'] = $this->objectToArray($response);
            return $re;
        } catch (\Exception $e) {
            $re['status'] = 1;
            $re['msg'] = $e->getMessage();
            return $re;
        }
    }

    /**
     * Created by zyjun
     * Info:获取原始视频信息
     */
    public function getMezzanineInfo($videoId){
        try {
            $request = new vod\GetMezzanineInfoRequest();
            $request->setVideoId($videoId);
            $request->setAuthTimeout(3600*5);   // 原片下载地址过期时间，单位：秒，默认为3600秒  1小时
            $request->setAcceptFormat('JSON');
            $response = $this->client->getAcsResponse($request);
            $re['status'] = 0;
            $re['data'] = $this->objectToArray($response);
            return $re;
        } catch (\Exception $e) {
            $re['status'] = 1;
            $re['msg'] = $e->getMessage();
            return $re;
        }
    }


    /*************************辅助函数******************************/

    /**
     * 对象 转 数组
     *
     * @param object $obj 对象
     * @return array
     */
    private function objectToArray($obj) {
        $obj = (array)$obj;
        foreach ($obj as $k => $v) {
            if (gettype($v) == 'resource') {
                return;
            }
            if (gettype($v) == 'object' || gettype($v) == 'array') {
                $obj[$k] = (array)$this->objectToArray($v);
            }
        }
    return $obj;
    }



}