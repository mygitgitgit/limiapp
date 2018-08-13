<?php
/*
 * 回调控制器，所有第三方回调走这个控制器
 */
namespace app\callback\controller;
use think\Controller;
use think\Exception;
use think\Request;
use think\Db;
use app\com\controller\Aligreen;
use app\com\controller\Alivideo;
use app\com\controller\Log;

class Alivideocallback
{    static $redis_db=RedisDb; //redis选择的数据库
     static $redis_pass='youhong@limiapp';
     static $redis_host='47.97.218.145';

    /**
     * Created by zyjun
     * Info:视频上传完毕后回调，转码截图完毕后，开始视频鉴黄任务
     * 截图和转码是并行处理，二者无法确定先后顺序。所以采用转码完成后，url方式鉴黄
     */
    public function videoUploaded(){
        $data=$this->objectToArray(input());
        try{
            $Status=$data['Status'];
            if($Status!='success'){ //处理成功
                return;
            }
            $EventType=$data['EventType'];
            if($EventType!='TranscodeComplete'){ //转换成功
                return;
            }
            $VideoId=$data['VideoId'];  //点播系统里的视频id
//        $VideoId='842f21e338fb4c708647bd20759ffbab';
            //目前是短视频，所以直接查询原始视频url,然后提交鉴黄【点播回调通知里面，不能确定截图先完成还是转码先完成，如果要用截图来鉴黄，那么必须保证
            //视频转码已经完成，只有这样才能设置审核成功，相当于要用到2次回调，麻烦；所以这里直接以转码结束为标记，去用url来鉴黄】
            $obj=new Alivideo();
            $res=$obj->getMezzanineInfo($VideoId);
            if($res['status']){
                return; //不做任何处理，视频默认提交都是审核状态
            }
        }catch (\Exception $e){
            Log::write('error',305,'shortVideo','视频回调初始化失败：'.$e->getMessage(),json_encode($data));
        }

        #在数据库设置视频尺寸
        try{
            $updata['width']='';
            $updata['height']='';
            if(!$res['status']){
                $video_info=$res['data']['Mezzanine'];
                $updata['width']=$video_info['Width'];
                $updata['height']=$video_info['Height'];
            }
            Db::name('video')->where('video',$VideoId)->update($updata);
        }catch (\Exception $e){
            Log::write('error',303,'shortVideo','短视频回调获取高度和宽度',$e->getMessage());
        }

        #视频鉴黄
        try{
            $FileURL=$res['data']['Mezzanine']['FileURL'];
            //提交内容鉴定
            $dataid=$this->createAliGreenDataid();
            $obj=new Aligreen();
            $res=$obj->videoUrl($FileURL,'',$dataid); //如果返回状态失败，也直接后台审核
            if($res['status']){
                Db::name('video')->where('video',$VideoId)->setField('video_check_status',2);//视频检测失败
                return;
            }
            #给本次上传的视频id设置鉴黄green_dataid
            Db::name('video')->where('video',$VideoId)->setField('green_dataid',$dataid);
        }catch (\Exception $e){
            Db::name('video')->where('video',$VideoId)->setField('video_check_status',2);//视频检测失败
            Log::write('error',304,'shortVideo','短视频回调鉴黄失败',$e->getMessage());
        }
        #调用方法判断是否是原创音乐，如果是原创音乐，则获取分离的MP3写入music_user表
        try{
            $obj=new Alivideo();
            error_reporting(E_ALL ^ E_NOTICE);//阿里云core文件存在一个小错误会输出notice错误，这里屏蔽下
            $res=$obj->getPlayInfoMp3($VideoId);
            if($res['status']){
                Log::write('error',306,'shortVideo','短视频设置原创音乐url失败',$res['msg']);
                return;
            }
            $update=[];
            $update['music']=$res['data']['mp3_url'];
            $update['time']=$res['data']['mp3_duration'];
            #查看music_user设置url地址，并设置video表原创音乐id
            Db::name('music_user')->where('video_addr',$VideoId)->update($update);
            $music_id=Db::name('music_user')->where('video_addr',$VideoId)->value('id');
            Db::name('video')->where('video',$VideoId)->setField('music_id',$music_id);

        }catch (\Exception $e){
            Log::write('error',307,'shortVideo','短视频设置原创音乐url失败',$e->getMessage());
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

    /**
     * Created by zyjun
     * Info:阿里云内容鉴别任务id
     */
    public function createAliGreenDataid(){
        $dataid=date('YmdHis',time()).rand(100,999);
        return $dataid;
    }
}