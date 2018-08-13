<?php
/*
 * 回调控制器，所有第三方回调走这个控制器
 */
namespace app\callback\controller;
use think\Controller;
use think\Request;
use think\Db;
use app\com\controller\Redis;
use app\com\controller\Log;
use app\com\controller\Im;

class Aligreencallback
{   static $redis_db=RedisDb; //redis选择的数据库
    static $redis_pass='youhong@limiapp';
    static $redis_host='47.97.218.145';
    /**
     * Created by zyjun
     * Info:视频检测回调
     */
    public function videoSafe(){
        try{
            Db::startTrans();
            $data=input();
            $checksum=$data['checksum']; //签名
            $content=json_decode($data['content']);
            $content=(array)$content;
            $code=$content['code']; //状态码
            $dataid=$content['dataId']; //任务id
            $res= Db::name('video')->where('green_dataid',$dataid)->find();
            if(empty($res)||!empty($res['delete_time'])){
                return;  //已经删除的视频都返回
            }
            $video=$res['video']; //视频MD5 id
            $video_id=$res['id']; //视频id
            $from_uid=$res['user_id'];
            $to_uid=$res['notify_users']; //可能是多个用户id字符串
            if($code==200){
                $result=(array)$content['results'][0];
                $label=$result['label'];  //normal正常  porn色情
                $rate=$result['rate']; //评分
                if($label=='normal'){
                    $update['status']=0; //通过审核
                    $update['video_check_status']=0; //正常视频
                    Db::name('video')->where('green_dataid',$dataid)->update($update); //通过审核
                    #处理挑战业务逻辑
                    $this->challenge($dataid);
                    #视频审核通过后@用户 2为短视频@
                    $im=new Im();
                    $im->noticeMessage($from_uid,$to_uid,2,$video_id);
                }else{
                    $update['status']=1; //不通过审核
                    $update['video_check_status']=1; //色情视频
                    Db::name('video')->where('green_dataid',$dataid)->update($update); //人工审核
                }
            }else{
                Db::name('video')->where('green_dataid',$dataid)->setField('video_check_status',2); //其他错误  手动审核
            }
            Db::commit();
        }catch (\Exception $e){
            Db::rollback();
            Log::write('error',301,'shortVideo','短视频鉴黄后，回调通知视频状态处理失败:'.$e->getMessage(),json_encode($data));
        }

    }

    /**
     * Created by zyjun
     * Info:挑战处理，审核通过后，如果是新挑战则写入挑战表,写入使用数量，并在video表记录挑战id；如果是老挑战，只记录挑战使用数量
     * 新版本会处理数据，老版本没有$challenge_id，$challenge_name，本函数不执行数据
     * $dataid 鉴黄任务id
     */
    public function challenge($dataid){
        try{
            $res=Db::name('video')->where('green_dataid',$dataid)->field('user_id,challenge_name,challenge_id')->limit(1)->find();
            $challenge_id=$res['challenge_id'];
            #屏蔽老版本
            if(empty($challenge_id)){
                return;
            }
            #累加使用量
              Db::name('video_challenge')->where('id',$challenge_id)->limit(1)->setInc('use_num');
        }catch (\Exception $E){
            return;
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


    /**
     * Created by zyjun
     * Info:测试版本手动回调
     */
//   public function test(){
//
//       $dataid='20180723124442477';
//       #视频审核通过后@用户
//       $im=new Im();
//       $im->noticeMessage(36,'3,45',2,84);
//       try{
//           $res=Db::name('video')->where('green_dataid',$dataid)->field('user_id,challenge_name,challenge_id')->limit(1)->find();
//           $uid=$res['user_id'];
//           $challenge_id=$res['challenge_id'];
//           $challenge_name=$res['challenge_name'];
//           #屏蔽老版本
//           if(empty($challenge_id)&&empty($challenge_name)){
//               return;
//           }
//           #老挑战，累加使用量
//           if(!empty($challenge_id)){
//               Db::name('video_challenge')->where('id',$challenge_id)->limit(1)->setInc('use_num');
//           }
//           #发起新挑战,写入挑战表，记录挑战id
////           if(empty($challenge_id)&&!empty($challenge_name)){
////               $challenge_id=Db::name('video_challenge')->where('name',$challenge_name)->limit(1)->value('id');//异步回调，需再次判断
////               if(!empty($challenge_id)){
////                   Db::name('video')->where('green_dataid',$dataid)->setField('challenge_id',$challenge_id);
////                   Db::name('video_challenge')->where('id',$challenge_id)->limit(1)->setInc('use_num');
////               }else{
////                   $insert['name']=$challenge_name;//只记录name
////                   $insert['user_id']=$uid;
////                   $insert['create_time']=date('Y-m-d H:i:s',time());
////                   $challenge_id=Db::name('video_challenge')->insertGetId($insert);
////                   Db::name('video')->where('green_dataid',$dataid)->limit(1)->setField('challenge_id',$challenge_id);
////                   Db::name('video_challenge')->where('id',$challenge_id)->limit(1)->setInc('use_num');
////               }
////           }
//       }catch (\Exception $E){
//           return;
//       }
//   }

}