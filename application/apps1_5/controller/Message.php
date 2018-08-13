<?php
namespace app\apps1_5\controller;
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
            if($type==2){ //短视频点赞
                $click_data=Db::name('video')->where('id',$type_id)->field('id,video_cover,title')->find();
                $data[$key]['video_id'] = $click_data['id'];
                $title=$click_data['title'];
                $data[$key]['video_cover']=$click_data['video_cover'];
                $data[$key]['title']=$title;
                if($title!=''){
                    $data[$key]['$title']=$this->userTextDecode($title);
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
        //$time=input('time');
        if ($uid == '' || $token == '') {
            return apiError('');
        }
        //1 判断用户是否存在
        $res = $this->checkToken($uid, $token);
        if ($res['status']) {
            return apiError($res['msg'], $res['identity_status']);
        }
//        if($page==1){
//            $time=time();
//        }
//        $time=date('Y-m-d H:i:s',$time);
//        $condition['time']=['<=',$time];
        $condition['to']=$uid;
        $clear_time=Db::name('message_read')->where('uid',$uid)->value('time');
        if(!empty($clear_time)){
            $condition['time']=array('gt',$clear_time);
        }
        $condition['from']=array('neq',$uid);
        $res = Db::name('message_comment')->where($condition)->order('time desc')->page($page, '10')->select();
        if (empty($res)) {
            return apiSuccess(['timestamp'=>time()]);

        }
        $data = [];
        foreach ($res as $key => $val) {
            $message_comment_rid = $val['rid']; //回复id  rid评论的id
            $from = $val['from'];
            $type = $val['type'];
            $type_id = $val['type_id'];
            $data[$key]['type'] = $type;
            $data[$key]['type_id'] = $type_id;  //动态的id
            $data[$key]['discuss_id']=$message_comment_rid;  //该条评论的id

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
                //赵添加开始父级评论用户信息
                $parent=Db::name('discuss')->where('id', $message_comment_rid)->field('parent_uid,group_id')->find();
                $data[$key]['group_id']=$parent['group_id'];
                $data[$key]['parent_uid']=$parent['parent_uid'];
                $data[$key]['parent_nickname'] =null;
                if($parent['parent_uid']!=0){
                    $data[$key]['parent_nickname'] = Db::name('user')->where('id', $parent['parent_uid'])->value('nickname');
                }
                //赵添加结束
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
                //赵添加开始父级评论用户信息
                $parent=Db::name('topic_discuss')->where('id', $message_comment_rid)->field('parent_uid,group_id')->find();
                $data[$key]['group_id']=$parent['group_id'];
                $data[$key]['parent_uid']=$parent['parent_uid'];
                $data[$key]['parent_nickname'] =null;
                if($parent['parent_uid']!=0){
                    $data[$key]['parent_nickname'] = Db::name('user')->where('id', $parent['parent_uid'])->value('nickname');
                }
                //赵添加结束
                //获取评论内容
                $data[$key]['msg'] = Db::name('topic_discuss')->where('id', $message_comment_rid)->value('content');
                $data[$key]['msg'] = $this->userTextDecode( $data[$key]['msg']);
            }
            if ($type == 2) { //短视频评论
                $click_data = Db::name('video')->where('id', $type_id)->field('id,video_cover,title')->find();
                $data[$key]['video_id'] = $click_data['id'];
                $title = $click_data['title'];
                $data[$key]['title'] = $this->userTextDecode($title);
                $data[$key]['video_cover'] = $click_data['video_cover'];
                if ($title != '') {
                    $data[$key]['title'] = $this->userTextDecode($title);
                }
                //添加开始父级评论用户信息
                $parent=Db::name('video_discuss')->where('id', $message_comment_rid)->field('parent_uid,group_id')->find();
                $data[$key]['group_id']=$parent['group_id'];
                $data[$key]['parent_uid']=$parent['parent_uid'];
                $data[$key]['parent_nickname'] =null;
                if($parent['parent_uid']!=0){
                    $data[$key]['parent_nickname'] = Db::name('user')->where('id', $parent['parent_uid'])->value('nickname');
                }
                //赵添加结束
                //获取评论内容
                $data[$key]['msg'] = Db::name('video_discuss')->where('id', $message_comment_rid)->value('content');
                $data[$key]['msg'] = $this->userTextDecode( $data[$key]['msg']);
            }
            $data[$key]['time'] = $this->timeToHour($val['time']);
        }
        apiSuccess(['timestamp'=>time()], $data);
    }

        /**
         * Created by zyjun
         * Info:清空点赞，评论,关注消息
         */
        public function clearMessage(){
            $uid=input('id');
            $token=input('token');
            $type=input('type');
            input('page')?$page=input('page'):$page=0;
            if($uid==''||$token==''||$type==''){
                return apiError('参数错误');
            }
            if(!in_array($type,[0,1,2])){
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


    /**
     * Created by zyjun
     * Info:获取最新好友关注通知列表
     */
    public function getNewAttentionList(){
        $id=input('id');
        $token=input('token');
        input('page')?$page=input('page'):$page=0;
        $res=$this->checkToken($id,$token);
        if($res['status']){
            return apiError($res['msg'],'',$res['code']);
        }
        $view_time=Db::name('message_read')->where('uid',$id)->where('mtype',2)->value('time');
        $where=array();
        $where['attention_id']=$id;
        $where['is_cancel']=0;
        if($view_time){
            $where['create_time']=['>',$view_time];
        }
        $res=Db::name('user_relation')->where($where)->order('create_time desc')->page($page, '10')->select();
        if(empty($res)){
            return apiSuccess('没有关注最新信息');
        }
        //查询某某关注了你，他的粉丝，哪个大学，是否是互相关注
        $data=[];
        foreach ($res as $key=>$val){
            $attention_fid=$val['user_id']; //关注来源用户id
            $attention_fid_info=$this->userInfo($attention_fid);
            $data[$key]['uid']=$attention_fid_info['user_id'];
            $data[$key]['head_pic']=$attention_fid_info['head_pic'];
            $data[$key]['nickname']=$attention_fid_info['nickname'];
            $data[$key]['time']=$this->getAttentionTime($id,$attention_fid);
//            $data[$key]['college']=$attention_fid_info['college'];
//            $data[$key]['school']=$attention_fid_info['school'];
//            $data[$key]['fans']=$this->myNum($attention_fid)['fans_num'];
//            $data[$key]['friends']=$this->isFriend($id,$attention_fid);

        }
        apiSuccess('最新关注列表',$data);
    }


    /**
     * Created by zyjun
     * Info:查询是否互相关注
     */
    public function isFriend($uid1,$uid2){
        //uid1是否关注uid2
        $res=Db::name('user_relation')->where('user_id',$uid1)->where('attention_id',$uid2)->find();
        if(empty($res)){
            return 0;
        }
        $res=Db::name('user_relation')->where('user_id',$uid2)->where('attention_id',$uid1)->find();
        if(empty($res)){
            return 0;
        }
        return 1;
    }

    /**
     * Created by zyjun
     * Info:关注列表获取关注时间
     * $attention_fid关注我的人
     */
    public function getAttentionTime($uid,$attention_fid){
       $time=Db::name('user_relation')->where('user_id',$attention_fid)->where('attention_id',$uid)->value('create_time');
       $now_time=time();
       $time=strtotime($time)+86400;
       if($now_time<$time){ //不超出一天，只显示时间
          $time=date('G:i',$time);
       }else{
           $time=date('n-j',$time);
       }
       return $time;

    }


}