<?php
namespace app\apps1_4\controller;
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

class Im extends Common
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
     * Info:获取通讯token,从数据库获取
     * 用户已认证，直接获取用户昵称和头像作为初始值
     */
    public function getToken()
    {
        $uid = input('id');
        $token = input('token');
        $to_uid=input('to_uid');  //0 请求自己accid 1：请求对方accid
        $res = $this->checkToken($uid, $token);
        if ($res['status']) {
            apiError($res['msg'],'',$res['code']);
            return;
        }
        //验证权限
        $res=$this->isAccess($uid);
        if($res['identity_status']!=2){
            apiError('请先认证个人身份信息！');
            return;
        }
        if(!$to_uid){ //没传递对方uid 返回自己的网易云信息
            $res=Db::name('im_user')->where('uid',$uid)->field('accid,name,token')->find();
            if(empty($res)){
                return apiError('本用户IM通讯异常','',100);
            }
        }else{
            $res=Db::name('im_user')->where('uid',$to_uid)->field('accid,name,token')->find();
            if(empty($res)){
                return apiError('对方用户IM通讯异常','',100);
            }
        }
        $im_user['token']=$res['token'];
        $im_user['accid']=$res['accid'];
        $im_user['name']=$res['name'];
        return apiSuccess('用户Token信息',$im_user);
    }




    /**
     * Created by zyjun
     * 短信注册的时候生成token
     * Info:获取通讯token  没注册的获取token,注册过的直接更新token;这里只记录accid
     * accid是否已经注册过，通过网易云接口来判断
     */
    public function regToken($uid)
    {
        $data['accid'] =MD5($this->accid_prefix.time().$uid); //唯一accid
        $data['ex'] =json_encode($user['uid']=$uid);
        //上传到IM服务器，并获取token
        $res = $this->IM->postData('https://api.netease.im/nimserver/user/create.action',$data);
        if($res['status']){
            $this->errorLog(1,array('error_code'=>$res['data'],'error_msg'=>$res['_msg'])); //其他错误返回
            $re['status']=1;
            $re['msg']='未知错误';
            return ;
        }
        //存储token
        $data=$res['data'];
        if($data['code']!=200){
            if($res['data']['desc']=='already register'){ //已经注册过的，更新信息[比如清空用户表，重新注册的情况，网易云信ID也得更新成新的]
                $this->refreshToken($uid);
                return;
            }else{
                $this->errorLog(1,array('error_code'=>$data['code'],'error_msg'=>$data['desc']));
                $re['status']=1;
                $re['msg']=$data['desc'];
                return ;
            }
        }
        Db::name('im_user')->where('accid',$uid)->insert(['uid'=>$uid,'accid'=>$data['info']['accid'],'token'=>$data['info']['token'],'ex'=>$uid]);
        $re['status']=0;
        $re['msg']='创建token成功';
        return ;
    }

    /**
     * Created by zyjun
     * 更新token 或者更新并写入表
     */
    public function refreshToken($uid)
    {
        $data['accid'] =Db::name('im_user')->where('uid',$uid)->value('accid');
        //上传到IM服务器，并获取token
        $res = $this->IM->postData('https://api.netease.im/nimserver/user/refreshToken.action',$data);
        if($res['status']){
              $this->errorLog(1,array('error_code'=>$res['data'],'error_msg'=>$res['msg'])); //其他错误返回
        }
        //存储token
        $data=$res['data'];
        if($data['code']!=200){
            $this->errorLog(1,array('error_code'=>$data['code'],'error_msg'=>$data['desc']));
            return ;
        }
        $res=Db::name('im_user')->where('uid',$uid)->find();
        if(empty($res)){ //没有写入过表，直接写入
            Db::name('im_user')->where('uid',$uid)->insert(['uid'=>$uid,'accid'=>$data['accid'],'token'=>$data['info']['token']]);
        }else{
            Db::name('im_user')->where('uid',$uid)->update(['token'=>$data['info']['token']]);
        }

    }

    /**
     * Created by zyjun
     * Info:更新用户名片,这借口必须有验证权限的接口来调用
     * 注意：如果没做修改，就不要调用此接口，不然此accid网易云名片会、被全部清空， 要更新指定的字段，务必传入参数
     */
   public function updateImUinfo($accid,$name,$icon,$gender,$sign,$email,$birth,$mobile,$ex){
       $data['accid']=$accid;
       if($name==''&&$icon==''&&$sign==''&&$email==''&&$birth==''&&$mobile==''&&$gender===''&&$ex==''){
           $data['name']='';
           $data['icon']='';
           $data['sign']='';
           $data['email']='';
           $data['birth']='';
           $data['mobile']='';
           $data['gender']='';
           $data['ex']='';
       }
       if(!empty($name)){
           $data['name']=$name;
           if(strlen($name)>64){
               $re['status']=1;
               $re['msg']='Im昵称超出长度限制';
               return $re;
           }
       }
       if(!empty($icon)){
           $data['icon']=$this->addApiUrl($icon);
           if(strlen($icon)>100){
               $re['status']=1;
               $re['msg']='头像超出长度限制';
               return $re;
           }
       }
       if(!empty($sign)){
           $data['sign']=$sign;
           if(strlen($icon)>250){
               $re['status']=1;
               $re['msg']='签名超出长度限制';
               return $re;
           }
       }
       if(!empty($email)){
           $data['email']=$email;
           if($this->checkEmail($email)){
               $re['status']=1;
               $re['msg']='邮箱格式错误';
               return $re;
           }
           if(strlen($email)>100){
               $re['status']=1;
               $re['msg']='邮箱长度超出限制';
               return $re;
           }
       }
       if(!empty($birth)){
           $data['birth']=$birth;
       }
       if(!empty($mobile)){
           $data['mobile']=$mobile;
           if(checkMobile($mobile)){
               $re['status']=1;
               $re['msg']='手机号格式错误';
               return $re;
           }
       }
       if($gender!==''){
           if(!in_array($gender,array(0,1))){
               $re['status']=1;
               $re['msg']='性别参数错误';
               return $re;
           }
           $data['gender']=$gender;
       }
       if(!empty($ex)){
           $data['ex']=$ex;
           if(strlen($ex)>100){
               $re['status']=1;
               $re['msg']='名片扩展超出长度限制';
               return $re;
           }
       }
       $res = $this->IM->postData('https://api.netease.im/nimserver/user/updateUinfo.action',$data);
       if($res['status']){
           $this->errorLog(1,array('error_code'=>$res['data'],'error_msg'=>$res['_msg']));
           return apiError('更新失败');
       }
       $data['icon']=$icon; //保存没域名的聊天头像
       Db::name('im_user')->where('accid',$accid)->update($data);
   }

    /**
     * Created by zyjun
     * Info:获取im用户信息
     */
   public function getImUinfo(){
       $data['accids'] =json_encode(['9552d881ee38c65f8fc7e277bdbee0e4']);
       //上传到IM服务器，并获取token
       $res = $this->IM->postData('https://api.netease.im/nimserver/user/getUinfos.action',$data);
       if($res['status']){
           $this->errorLog(1,array('error_code'=>$res['data'],'error_msg'=>$res['msg'])); //其他错误返回
       }
       //存储token
       apiSuccess($res);
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
     * Info:点对点系统消息
     * $to发给谁 $type内容类型0 表示文本消息,1 表示图片，2 表示语音，3 表示视频，4 表示地理位置信息，6 表示文件，
     */

    public function sendMessage2(){
        $content='今晚1点进行系统升级';
        $id=DB::name('message_sys')->insertGetId(['from'=>11,'to'=>27,'content'=>11]);
        $attach['type']=2; //0:点赞 1评论 2：系统内部消息，比如活动通知，系统维护通知，显示区域固定
        $attach['msg_id']=$id;
        $attach=json_encode($attach);
        $payload['content']=""; //
        $pushcontent='消息：'.$id.'——今晚1点进行系统升级';
        $payload=json_encode($payload);
        $res=$this->sendOneSysMessage(11,27,$attach,$pushcontent,$payload);
        if($res['status']){
            return apiError($res['msg']);
        }
        apiSuccess('成功：'.$pushcontent);
    }




    /**
     * Created by zyjun
     * Info:只用于点赞评论,只发消息id和
     */
    public function sendMessage($from,$to,$type,$attach){
        $attach['type']=$type; //0:点赞 1评论 2：系统内部消息，比如活动通知，系统维护通知，显示区域固定
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
         * Name:IM普通消息发送
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
     * Info:发送普通消息
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















}
