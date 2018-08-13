<?php
/**
 * 用于回调接口里面使用im功能
 */
namespace app\com\controller;
use think\Controller;
use think\Db;
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/1/31 0031
 * Time: 16:47
 * info: 网易云信
 */
require_once APP_EXTEND. 'Wangyi/ImApi.php';

class Im
{
    public $IM;
    public $accid_prefix=WyImPrefix; //网易云通讯前缀，和测试环境IM分开
    /**
     * Created by zyjun
     * Info:权限验证
     */
    public function _initialize()
    {

        $this->IM=new \ImApi();
    }



    /**
     * Created by zyjun
     * Info:IM错误日志表
     */
    public function errorLog($msg,$content){
        $data['msg']=$msg;
        $data['data']=json_encode($content);
        $data['create_time']=date('Y-m-d H:i:s',time());
        Db::name('im_error_log')->insert($data);
    }


    /**
     * Created by zyjun
     * Info:只用于点赞、评论、@功能,只发消息accid
     * 调用网易自定义系统消息接口,组装外部数据
     */
    public function sendMessage($from,$to,$type,$attach){
        $attach['type']=$type; //0:点赞 1评论   2@好友功能
        $attach=json_encode($attach);
        $payload['content']="";
        $pushcontent='';
        $payload=json_encode($payload);
        $res=$this->sendOneSysMessage($from,$to,$attach,$pushcontent,$payload);
        if($res['status']){
            $re['status']=1;
            $re['msg']=$res['msg'];
            return $re;
        }
        $re['status']=0;
        $re['msg']='发送点对点系统消息成功';
        return $re;
    }

    /**
     * Created by zyjun
     * Info:网易自定义系统消息接口
     */
    public function sendOneSysMessage($from,$to,$attach,$pushcontent,$payload){
        $data['from']=$from; //固定
        $data['msgtype']=0;   //0：点对点自定义通知，1：群消息自定义通知，其他返回414
        $data['to']=$to;
        $data['attach']=$attach; //自定义消息内容json
        $data['pushcontent']=$pushcontent; //不超过150字符
        $data['payload']=$payload; //json 2k
        $IM=new \ImApi();
        $res = $IM->postData('https://api.netease.im/nimserver/msg/sendAttachMsg.action',$data);
        if($res['status']){
            $this->errorLog(1,array('error_code'=>$res['data'],'error_msg'=>$res['_msg']));
            $re['status']=1;
            $re['msg']=$res['msg'];
            return $re;
        }
        if($res['data']['code']!=200){
            $re['status']=1;
            $re['msg']='发送点对点系统消息失败,code:'.$res['data']['code'].'msg:'.$res['data']['desc'];
            return $re;
        }
        $re['status']=0;
        $re['msg']='发送点对点系统消息成功';
        return $re;
    }


        /**
         * Created by zyjun
         * Name:IM普通消息发送，调用网易普通普通消息接口     【业务逻辑的关注，系统通知，调用此接口，会自动显示到网易IM sdk列表里面】
         * Info：处理$content数据，以满足IM发送格式要求,转换uid为accid
         * $im_type 0表示文本消息,1 表示图片，2 表示语音，3 表示视频，4 表示地理位置信息，6 表示文件的数组
         * $from发送者admin_id，$to接受者uid,$content发送过来的内容，
         */
        public function sentImMsg($im_type,$type,$from_accid,$to_accid,$content){
            //组装格式
            $data['from']=$from_accid;
            $data['to']=$to_accid;
            $data['ope']=0;  // 0：点对点个人消息，1：群消息（高级群），其他返回414
            $data['type']=$im_type;
            $body['data']=$content;
            $body['type']=$type;//自定义消息格式 与app前端显示消息类型有关，约定为系统消息7  关注8
            $data['body']=json_encode($body);
            $data['pushcontent']=$content['title'];
            $data['payload']='';
            $im=new Im();
            $res=$im->sendMsg($data); //目前固定为admin账户发送
            if($res['status']){
                $re['status']=1;
                $re['msg']=$res['msg'];
                return $re;
            }
            $re['status']=0;
            $re['msg']='发送成功';
            return $re;
        }

    /**
     * Created by zyjun
     * Info:网易普通普通消息接口
     */
    public function sendMsg($data){
        $IM=new \ImApi();
        $res = $IM->postData('https://api.netease.im/nimserver/msg/sendMsg.action',$data);  //发送普通单个消息
        if($res['status']){
            $this->errorLog(1,array('error_code'=>$res['data'],'error_msg'=>$res['_msg']));
            $re['status']=1;
            $re['msg']=$res['msg'];
            return $re;
        }
        if($res['data']['code']!=200){
            $re['status']=1;
            $re['msg']='发送失败code:'.$res['data']['code'].'msg:'.$res['data']['desc'];
            return $re;
        }
        $re['status']=0;
        $re['msg']='发送成功';
        return $re;
    }



    /***************业务逻辑相关函数*******************/

    /**
     * Created by zyjun
     * Info:@用户总表，用户记录@某个用户消息总表
     */
    public function totalNoticefyUser($data){
        $msg_id=Db::name('message_notice')->insertGetId($data);
        return $msg_id;
    }

    /**
     * Created by zyjun
     * Info:通用消息发送函数，只负责IM发消息，不负责记录消息到数据库，可用于发送点赞，评论，@好友的功能
     * $from_uid发送者uid   $to_uid接收者uid   $type消息类型 0 点赞、1评论 、2@【客户端的约定】 $msg_id客户端收到的消息id,此id为数据库记录插入后的id，用于
     * 前端点开消息，查询数据库数据
     *
     */
    public function sentUserImMsg($from_uid,$to_uid,$type,$msg_id){
        $im=new Im();
        $from=Db::name('im_user')->where('uid',$from_uid)->value('accid');
        $to=Db::name('im_user')->where('uid',$to_uid)->value('accid');
        if(!($from&&$to)){
            $re['status']=1;
            $re['msg']='发送者accid或者接收者accid不能为空';
            return $re;
        }
        if($from_uid!=$to_uid){ //自己不能给自己发IM消息
            $attach['msg_id']=$msg_id; //评论消息总表id
            $res=$im->sendMessage($from,$to,$type,$attach);
            if($res['status']){
                $re['status']=1;
                $re['msg']='发送消息失败';
                return $re;
            }
            $re['status']=0;
            $re['msg']='发送成功';
            return $re;
        }
    }

    /**
     * Created by zyjun
     * Info:@单个好友功能
     * 记录@消息到数据库,并发送im消息
     * $from,$to为用户id,$data_id为回调通知短视频id
     */
    public function noticeSingleMessage($from_uid,$to_uid,$data_type,$data_id){
        try{
            DB::startTrans();
            $from=Db::name('im_user')->where('uid',$from_uid)->value('accid');
            $to=Db::name('im_user')->where('uid',$to_uid)->value('accid');
            $content['from']=$from_uid;
            $content['to']=$to_uid;
            $content['type']=$data_type; //类型
            $content['type_id']=$data_id; //类型id
            $content['time']=date('Y-m-d H:i:s',time());
            $msg_id=$this->totalNoticefyUser($content);  //消息id必须写入一个总表来保证消息id的唯一性，客户端显示时候，直接通过总表反查，

            if($from_uid!=$to_uid){ //自己不能给自己发IM消息
                $type=2; //0：点赞 ；1：评论 ； 2 @   3:关注
                $attach['msg_id']=$msg_id; //评论消息总表id
                $res=$this->sendMessage($from,$to,$type,$attach);
                if($res['status']){
                    $re['status']=1;
                    $re['msg']=$res['msg'];
                    return $re;
                }
                DB::commit();
                $re['status']=0;
                $re['msg']='发送成功';
                return $re;
            }
        }catch (\Exception $e){
            DB::rollback();
            $re['status']=1;
            $re['msg']='发送失败';
            return $re;
        }
    }

    /**
     * Created by zyjun
     * Info:同时处理@多个好友
     */
    public function noticeMessage($from_uid,$to_uid,$data_type,$data_id){
        if(empty($to_uid)){
            return;
        }
        $to_uid=explode(',',$to_uid);
        #发送一个消息
        if(count($to_uid)==1){
          $this->noticeSingleMessage($from_uid,$to_uid,$data_type,$data_id);
        }
        #发送多个消息
        else{
            foreach ($to_uid as $key=>$val){
                $this->noticeSingleMessage($from_uid,$val,$data_type,$data_id);
            }
        }

    }







}
