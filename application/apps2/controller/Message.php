<?php
namespace app\apps2\controller;
use think\Db;
class Message extends Common
{
    /**
     * Created by zyjun
     * Info:显示点赞消息列表
     */
   public function clickList(){
       $uid=input('id');
       $token=input('token');
       input('page')?$page=input('page'):$page=0;
       if($uid==''||$token==''){
           return apiError('参数错误');
       }
       //1 判断用户是否存在
       $res=$this->checkToken($uid,$token);
       if($res['status']){
           return apiError($res['msg'],$res['identity_status']);
       }
       $condition['to']=$uid;
       $clear_time=Db::name('message_read')->where('uid',$uid)->value('time');
       if(!empty($clear_time)){
           $condition['time']=array('gt',$clear_time);
       }
       $condition['from']=array('neq',$uid);
        $res=Db::name('message_click')->where($condition)->order('time desc') ->page($page,'10')->select();
        if(empty($res)){
            apiSuccess(''); //暂无消息记录
            return;
        }
       $data=[];
        foreach ($res as $key=>$val){
            $from=$val['from'];
            $type=$val['type'];
            $type_id=$val['type_id'];
            $data[$key]['type']=$type;
            $data[$key]['type_id']=$type_id;

            $user=Db::name('user')->where('id',$from)->field('id,nickname,head_pic,sex')->find();
            $data[$key]['user_id']=$user['id'];
            $data[$key]['sex']=$user['sex'];
            $data[$key]['nickname']=$user['nickname'];
            $data[$key]['head_pic']=$this->addApiUrl($user['head_pic']);

            $data[$key]['msg']='为你点赞';
            $data[$key]['text']='';
            $data[$key]['img']='';
            $data[$key]['video']='';
            if($type==0){ //动态点赞
                $click_data=Db::name('action')->where('id',$type_id)->field('action_pic,action_video,content')->find();
                $content=$click_data['content'];
                $action_pic=$click_data['action_pic'];
                $action_video=$click_data['action_video'];
                if($content!=''){
                    $data[$key]['text']=$this->userTextDecode($content);
                }
                if($action_pic!=''){
                    $data[$key]['img']=$this->addApiUrl(explode(',',$action_pic)[0]);
                }
                if($action_video!=''){
                    $data[$key]['video']=$this->addApiUrl($action_video);
                }
            }
            if($type==1){ //话题点赞
                $click_data=Db::name('topic_action')->where('id',$type_id)->field('topic_id,pic,content')->find();
                $data[$key]['topic_id'] = $click_data['topic_id'];
                $content=$click_data['content'];
                $topic_pic=$click_data['pic'];
                if($content!=''){
                    $data[$key]['text']=$this->userTextDecode($content);
                }
                if($topic_pic!=''){
                    $data[$key]['img']=$this->addApiUrl(explode(',',$topic_pic)[0]);
                }
            }
            $data[$key]['time']=$this->timeToHour($val['time']);


        }
        apiSuccess('点赞消息列表',$data);
   }

    /**
     * Created by zyjun
     * Info:评论列表
     */
    public function commentList()
    {
        $uid = input('id');
        $token = input('token');
        input('page') ? $page = input('page') : $page = 0;
        if ($uid == '' || $token == '') {
            return apiError('');
        }
        //1 判断用户是否存在
        $res = $this->checkToken($uid, $token);
        if ($res['status']) {
            return apiError($res['msg'], $res['identity_status']);
        }
        $condition['to']=$uid;
        $clear_time=Db::name('message_read')->where('uid',$uid)->value('time');
        if(!empty($clear_time)){
            $condition['time']=array('gt',$clear_time);
        }
        $condition['from']=array('neq',$uid);
        $res = Db::name('message_comment')->where($condition)->order('time desc')->page($page, '10')->select();
        if (empty($res)) {
            return apiSuccess('');

        }
        $data = [];
        foreach ($res as $key => $val) {
            $message_comment_rid = $val['rid']; //回复id
            $from = $val['from'];
            $type = $val['type'];
            $type_id = $val['type_id'];
            $data[$key]['type'] = $type;
            $data[$key]['type_id'] = $type_id;

            $user = Db::name('user')->where('id', $from)->field('id,nickname,head_pic,sex')->find();
            $data[$key]['user_id'] = $user['id'];
            $data[$key]['sex'] = $user['sex'];
            $data[$key]['nickname'] = $user['nickname'];
            $data[$key]['head_pic'] = $this->addApiUrl($user['head_pic']);

            $data[$key]['msg'] = '评论了你';
            $data[$key]['text'] = '';
            $data[$key]['img'] = '';
            $data[$key]['video'] = '';
            if ($type == 0) { //动态评论
                $click_data = Db::name('action')->where('id', $type_id)->field('action_pic,action_video,content')->find();//获取被评论内容
                $content = $click_data['content'];
                $action_pic = $click_data['action_pic'];
                $action_video = $click_data['action_video'];
                if ($content != '') {
                    $data[$key]['text'] = $this->userTextDecode($content);
                }
                if ($action_pic != '') {
                    $data[$key]['img'] = $this->addApiUrl(explode(',', $action_pic)[0]);
                }
                if ($action_video != '') {
                    $data[$key]['video'] = $this->addApiUrl($action_video);
                }
                //获取评论内容
                $data[$key]['msg'] = Db::name('discuss')->where('id', $message_comment_rid)->value('content');
                $data[$key]['msg'] = $this->userTextDecode( $data[$key]['msg']);
            }
            if ($type == 1) { //话题评论
                $click_data = Db::name('topic_action')->where('id', $type_id)->field('topic_id,pic,content')->find();
                $data[$key]['topic_id'] = $click_data['topic_id'];
                $content = $click_data['content'];
                $topic_pic = $click_data['pic'];
                if ($content != '') {
                    $data[$key]['text'] = $this->userTextDecode($content);
                }
                if ($topic_pic != '') {
                    $data[$key]['img'] = $this->addApiUrl(explode(',', $topic_pic)[0]);
                }
                //获取评论内容
                $data[$key]['msg'] = Db::name('topic_discuss')->where('id', $message_comment_rid)->value('content');
                $data[$key]['msg'] = $this->userTextDecode( $data[$key]['msg']);
            }
            $data[$key]['time'] = $this->timeToHour($val['time']);
        }
        apiSuccess('评论消息列表', $data);
    }

        /**
         * Created by zyjun
         * Info:清空点赞，评论消息
         */
        public function clearMessage(){
            $uid=input('id');
            $token=input('token');
            $type=input('type');
            input('page')?$page=input('page'):$page=0;
            if($uid==''||$token==''||$type==''){
                return apiError('参数错误');
            }
            if(!in_array($type,[0,1])){
                return apiError('参数错误');
            }
            //1 判断用户是否存在
            $res=$this->checkToken($uid,$token);
            if($res['status']){
                return apiError($res['msg'],$res['identity_status']);
            }
            //写入清空时间
            $res=Db::name('message_read')->where('uid',$uid)->where('mtype',$type)->find();
            if(empty($res)){
                $res=Db::name('message_read')->insert(['uid'=>$uid,'mtype'=>$type,'time'=>date('Y-m-d H:i:s')]);
                if(!$res){
                    return apiError('清空消息失败');
                }
            }else{
                $res=Db::name('message_read')->where('uid',$uid)->where('mtype',$type)->update(['time'=>date('Y-m-d H:i:s')]);
                if($res==false){
                    return apiError('清空消息失败');
                }
            }
            apiSuccess('清空消息成功');
        }


}