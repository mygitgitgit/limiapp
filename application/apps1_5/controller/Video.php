<?php
namespace app\apps1_5\controller;
use think\Db;
use app\apps1_5\model\VideoDiscuss;
use app\apps1_5\controller\Pushvideo;
use app\com\controller\Aligreen;
use app\com\controller\Alivideo;
use app\com\controller\Redis;
use app\com\controller\Alists;

class Video extends Common
{
    static $redis_db=RedisDb; //redis选择的数据库
    static $redis_pass='youhong@limiapp';
    static $redis_host='47.97.218.145';
    /**
     * Created by zyjun
     * Info:初始化
     */
    public function _initialize(){
      $ss=input();
    }

    /**
     * 关注。同校
     * Attention。school
     */
    public function indexVideoList(){
        $page=input('get.page');
        $time=input('get.time');//第一次请求时间
        if($page==''||$page<1){
            apiError('请求页码有误');
            return;
        }
        if(!$time){
            return apiError('时间不能为空');
        }
        $timestamp=$time;
        if($page==1){
            $time=time();
            $timestamp=time();
        }
        $type=(int)input('get.type');  //type 0关注  1学校

        $id=input('get.id');
        $token=input('get.token');
        $res=$this->checkToken($id,$token);
        if($res['status']){
            return apiError($res['msg'],'',$res['code']);
        }
//        $res=$this->isAccess($id);
//        if($res['identity_status']!=2){
//            return apiError($res['msg'],$res['identity_status']);
//        }
        $data=array();
        if($time!=''){
            $time=date('Y-m-d H:i:s',$time);
            $data['v.create_time']=['<=',$time];
        }
        if($id!=''){
            //判断是否有拉黑名单
            $res=Db::name('user_black')->where(['user_id'=>$id,'is_cancel'=>0])->find();
            if($res){
                //有黑名单
                $res=Db::name('user_black')
                    ->where(['user_id'=>$id,'is_cancel'=>0])
                    ->column('black_user_id');
                $data['v.user_id']=array('not in',$res); //筛选条件 后期添加
            }
        }else{
            return apiError('id不能为空');
        }
        if($type===0){
            $res2=Db::name('user_relation')->where(['user_id'=>$id,'is_cancel'=>0])->column('attention_id');
            if($res2){
                $data['v.user_id']=array('in',$res2);
                $data['v.view_auth']=array('in',[1,2]);
                $video_list=$this->videoList2($data,$page);
                $dd=[];
                if($video_list){
                    foreach ($video_list as $k=>&$v){
                        $dd[$k]['click_num']=$v['click_num'];
                        $dd[$k]['discuss_num']=$v['discuss_num'];
                        $dd[$k]['id']=$v['id'];
                        $r=Db::name('video_click')->where(['user_id'=>$id,'video_id'=>$v['id'],'type'=>1])->find();
                        if($r){
                            $dd[$k]['is_click']=1;
                        }else{
                            $dd[$k]['is_click']=0;
                        }
                        $dd[$k]['user']['user_id']=$v['user_id'];
                        $dd[$k]['user']['nickname']=$v['user_nickname'];
                        $dd[$k]['user']['head_pic']=$v['user_head_pic'];
                        if($v['coid']){
                            $dd[$k]['user']['college']['id']=$v['coid'];
                            $dd[$k]['user']['college']['name']=$v['college'];
                        }else{
                            $dd[$k]['user']['college']=null;
                        }

                        $dd[$k]['music']['music_id']=$v['music_id'];
                        $dd[$k]['music']['music_type']=$v['music_type'];
                        $dd[$k]['music']['name']=$v['music_name'];
                        $dd[$k]['music']['singer']=$v['music_singer'];
                        $dd[$k]['music']['pic']=$v['music_pic'];

                        $dd[$k]['title']=$v['title'];

                        $dd[$k]['video']['video']=$v['video'];
                        $dd[$k]['video']['cover']=$v['video_cover'];
                        $dd[$k]['video']['height']=$v['height'];
                        $dd[$k]['video']['width']=$v['width'];
                        $dd[$k]['video']['v_create_time']=$v['v_create_time'];

                        $dd[$k]['is_attention']=1;
                    }
                }
                $return['timestamp']=$timestamp;
                $return['data']=$dd;
                return apiSuccess('关注列表',$return);
            }
        }elseif($type===1){
            $college_id=input('college_id');
            if(!$college_id){
                $college_id=Db::name('user')
                    ->where('id',$id)
                    ->value('college_id');
            }
            $data['u.college_id']=$college_id;
            $vids=Db::name('video v')
                ->join('user u','v.user_id=u.id','LEFT')
                ->where($data)->field('v.id,v.user_id,v.view_auth')->select();
            $ids=[];
            if($vids){
                foreach ($vids as $k=> &$v){
                    if($v['view_auth']==1){  //所有人可见时
                        $ids[]=$v['id'];
                    }
                    if ($v['view_auth']==2){  //粉丝可见时
                        $res1=Db::name('user_relation')->where(['user_id'=>$id,'attention_id'=>$v['user_id'],'is_cancel'=>0])->find();
                        if($res1||($id==$v['user_id'])){   //粉丝或者是自己时可以看到
                            $ids[]=$v['id'];
                        }
                    }
                    if ($v['view_auth']==3){  //自己可见时
                        if($id==$v['user_id']){   //粉丝或者是自己时可以看到
                            $ids[]=$v['id'];
                        }
                    }

                }
            }
            $d['v.id']=array('in',$ids);
            $college=Db::name('college')->where('coid',$college_id)->value('name');
            $video_list=$this->videoList2($d,$page);
            $dd=[];
            if($video_list){
                foreach ($video_list as $k=>&$v){
                    $dd[$k]['click_num']=$v['click_num'];
                    $dd[$k]['discuss_num']=$v['discuss_num'];
                    $dd[$k]['id']=$v['id'];
                    $r=Db::name('video_click')->where(['user_id'=>$id,'video_id'=>$v['id'],'type'=>1])->find();
                    if($r){
                        $dd[$k]['is_click']=1;
                    }else{
                        $dd[$k]['is_click']=0;
                    }
                    $dd[$k]['user']['user_id']=$v['user_id'];
                    $dd[$k]['user']['nickname']=$v['user_nickname'];
                    $dd[$k]['user']['head_pic']=$v['user_head_pic'];
                    if($v['coid']){
                        $dd[$k]['user']['college']['id']=$v['coid'];
                        $dd[$k]['user']['college']['name']=$v['college'];
                    }else{
                        $dd[$k]['user']['college']=null;
                    }

                    $dd[$k]['music']['music_id']=$v['music_id'];
                    $dd[$k]['music']['music_type']=$v['music_type'];
                    $dd[$k]['music']['name']=$v['music_name'];
                    $dd[$k]['music']['singer']=$v['music_singer'];
                    $dd[$k]['music']['pic']=$v['music_pic'];

                    $dd[$k]['title']=$v['title'];

                    $dd[$k]['video']['video']=$v['video'];
                    $dd[$k]['video']['cover']=$v['video_cover'];
                    $dd[$k]['video']['height']=$v['height'];
                    $dd[$k]['video']['width']=$v['width'];
                    $dd[$k]['video']['v_create_time']=$v['v_create_time'];
                    $dd[$k]['is_attention']=0; //未关注
                    $res1=Db::name('user_relation')->where(['user_id'=>$id,'attention_id'=>$v['user_id'],'is_cancel'=>0])->find();
                    if($res1){
                        $dd[$k]['is_attention']=1; //yi关注
                    }
                }
            }
            $return['timestamp']=$timestamp;
            $return['data']=$dd;
            return apiSuccess($college,$return);
        }else{
            return apiError('不在处理范围');
        }

    }

    /**
     * 点击评论显示评论列表
     * get:video_id,page,time,id
     */
    public function discussList(){
        $video_id=input('get.video_id');
        $page=input('get.page');
        $time=input('get.time');//第一次请求时间
        $id=input('get.id');
        //1. 判断参数是否为空
        if(!$video_id){
            return apiError('动态id不能为空');
        }
        if($page==''||$page<1){
            apiError('请求页码有误');
            return;
        }
        if(!$time){
            return apiError('时间不能为空');
        }
        $timestamp=$time;
        if($page==1){
            $time=time();
            $timestamp=time();
        }
        $time=date('Y-m-d H:i:s',$time);

        //6.评论的动态信息
//        $action=$this->oneInfo($action_id);
//        if($action){
//            if($action['action_pic']==''){
//                $action['action_pic']=[];
//            }
//            $action['content']=$this->userTextDecode($action['content']);
//            $action['action_pic_num']=count($action['action_pic']);
//            $r=Db::name('click')->where(['user_id'=>$id,'action_id'=>$action['action_id']])->find();
//            if($r){
//                $action['is_click']=1;
//            }else{
//                $action['is_click']=0;
//            }
//
//            //如果有红包
//            if($action['red_type']!='null'){
//                if($id){
//                    $action['red_type']=$this->getRedType($id,$action['action_id'],$action['red_type'],$action['is_over']);
//                }
//            }
//            $dat['action']=$action;
//        }
        $count=Db::name('video')
            ->where(['id'=>$video_id])
            ->value('discuss_num');
        $dat['timestamp']=$timestamp;
        $dat['discuss_num']=$this->numToString($count);
        $dat['discuss_list']='';
        //4. 查询评论内容列表
        $res=Db::name('video_discuss')
            ->field('id,parent_id,parent_uid,group_id,user_id,video_id,content,create_time,click_num')
            ->where(['delete_time'=>null,'video_id'=>$video_id,'parent_id'=>0,'create_time'=>['<=',$time]])
            ->where('group_id','neq','null')
            ->order('click_num desc,create_time desc')
            ->page($page,20)
            ->select();
        if(!$res){
            apiSuccess('评论列表',$dat);
            return;
        }
        foreach ($res as$k=>&$v){
            $v['content']=$this->userTextDecode($v['content']);
            $v['create_time']=$this->timeToHour($v['create_time']);
            $v=array_merge($v,$this->userInfo2($v['user_id']));
            $v['parent_name']=null;
            $v['child_num']=(Db::name('video_discuss')->where('delete_time',null)->where('group_id',$v['group_id'])->count())-1;
            $child=Db::name('video_discuss')
                ->field('id,parent_id,parent_uid,group_id,user_id,video_id,content,create_time,click_num')
                ->where(['delete_time'=>null,'group_id'=>$v['id'],'create_time'=>['<=',$time]])
                ->where('id','neq',$v['id'])
                ->order('create_time desc')
                //->limit(3)
                ->select();
            if($child){
                foreach ($child as &$value){
                    $value['content']=$this->userTextDecode($value['content']);
                    $value['create_time']=$this->timeToHour($value['create_time']);
                    $value=array_merge($value,$this->userInfo2($value['user_id']));
                    $value['parent_name']=Db::name('user')->where('id',$value['parent_uid'])->value('nickname');
                    $c=Db::name('video_discuss_click')->where(['user_id'=>$id,'discuss_id'=>$value['id'],'type'=>1])->find();
                    if($c){
                        $value['is_click']=1;
                    }else{
                        $value['is_click']=0;
                    }
                }
            }
            $v['child']=$child;
            $r=Db::name('video_discuss_click')->where(['user_id'=>$id,'discuss_id'=>$v['id'],'type'=>1])->find();
            if($r){
                $v['is_click']=1;
            }else{
                $v['is_click']=0;
            }
        }
        $dat['discuss_list']=$res;
        apiSuccess('评论列表',$dat);
    }

    /**
     * 评论删除
     */
    public function deleteDiscuss(){
        $id=input('id');
        $token=input('token');
        $discuss_id=input('discuss_id');
        //1 判断用户是否存在
        $res=$this->checkToken($id,$token);
        if($res['status']){
            return apiError($res['msg'],'',$res['code']);
        }
        //2 判断用户是否已经通过认证
//        $res=$this->isAccess($id);
//        if($res['identity_status']!=2){
//            return apiError($res['msg'],$res['identity_status']);
//        }
        if($discuss_id==''){
            return apiError('discuss_id不能为空');
        }
        //判断是否可以删除
        $video_id=Db::name('video_discuss')->where('id',$discuss_id)->value('video_id');
        $user_id=Db::name('video')->where('id',$video_id)->value('user_id');
        //是否在自己动态下
        if($user_id!=$id){
            //如果不是自己动态，判断是不是自己的评论
            $user_id2=Db::name('video_discuss')->where('id',$discuss_id)->value('user_id');
            if($user_id2==$id){
                //如果是自己的评论 判断是不是父级评论
                $parent_id=Db::name('video_discuss')->where('id',$discuss_id)->value('parent_id');
                if($parent_id===0){
                    //如果是父级评论下面子评论一并删除
                    $discuss=Db::name('video_discuss')->where('group_id','=',$discuss_id)->update(['delete_time'=>date('Y-m-d H:i:s',time())]);
                    if($discuss){
                        $count=Db::name('video_discuss')->where('group_id','=',$discuss_id)->count();
                        Db::name('video')->where('id',$video_id)->setDec('discuss_num',$count);
                        return apiSuccess('删除成功');
                    }
                }
                //不是父级评论直接删除
                $discuss=VideoDiscuss::get($discuss_id)->delete();
                if($discuss){
                    Db::name('video')->where('id',$video_id)->setDec('discuss_num',1);
                    return apiSuccess('删除成功');
                }
            }else{
                //如果不是自己的评论 判断是不是自己评论的子评论
//                $group_id=Db::name('video_discuss')->where('id',$discuss_id)->value('group_id');
//                $user_id3=Db::name('video_discuss')->where('id',$group_id)->value('user_id');
//                if($user_id3==$id){
//                    $discuss=VideoDiscuss::get($discuss_id)->delete();
//                    if($discuss){
//                        return apiSuccess('删除成功');
//                    }
//                }
                return apiError('无权删除');
            }
        }
        //在自己的动态下可以删除所有评论
        $parent_id=Db::name('video_discuss')->where('id',$discuss_id)->value('parent_id');
        if($parent_id===0){
            //如果是父级评论下面子评论一并删除
            $discuss=Db::name('video_discuss')->where('group_id','=',$discuss_id)->update(['delete_time'=>date('Y-m-d H:i:s',time())]);
            if($discuss){
                $count=Db::name('video_discuss')->where('group_id','=',$discuss_id)->count();
                Db::name('video')->where('id',$video_id)->setDec('discuss_num',$count);
                return apiSuccess('删除成功');
            }
        }
        $discuss=VideoDiscuss::get($discuss_id)->delete();
        if($discuss){
            Db::name('video')->where('id',$video_id)->setDec('discuss_num',1);
            return apiSuccess('删除成功');
        }

    }
    /**
     * 添加评论动作
     */
    public function discussAction(){
        //判断用户是否存在
        $id=input('id');
        $token=input('token');
        $content=input('content');
        $video_id=input('video_id');
        $parent_id=input('parent_id',0); //父级评论的id
        $parent_uid=input('parent_uid',0); //父级评论的uid
        //1 判断用户是否存在
        $res=$this->checkToken($id,$token);
        if($res['status']){
            return apiError($res['msg'],'',$res['code']);
        }
        //2 判断用户是否已经通过认证
//        $res=$this->isAccess($id);
//        if($res['identity_status']!=2){
//            return apiError($res['msg'],$res['identity_status']);
//        }
        //3 对评论信息判断
        if(empty(trim($content))){
            return apiError('请输入评论信息');
        }
        if(strlen($content)>600){
            return apiError('请输入少于200字的评论信息');
        }
        if($video_id==''){
            return apiError('action_id不能为空');
        }
        //内容的表情处理
        $content=$this->userTextEncode($content);
        $data=[
            'user_id'=>$id,
            'video_id'=>$video_id,
            'parent_id'=>$parent_id,
            'parent_uid'=>$parent_uid,
            'content'=>$content,
            'create_time'=>date('Y-m-d H:i:s',time())
        ];
        //4 添加到评论 表
        $res=Db::name('video_discuss')->insertGetId($data);
        if(!$res){
            return apiError('评论添加失败');
        }else{
            //添加评论成功找到该评论
            $ids=Db::name('video_discuss')->where('id',$res)->find();
            //dump($ids);die;
            //如果该评论是父级评论
            if($ids['parent_id']===0){
                // 所属组的id为该评论的id
                $d=Db::name('video_discuss')->where('id',$res)->update(['group_id'=>$res]);
                if(!$d){
                    Db::name('video_discuss')->where('id',$res)->update(['delete_time'=>date('Y-m-d H:i:s',time())]);
                    return apiError('');
                }
            }else{
                //如果该评论不是父级评论，该所属组id为父级评论的所属组id
                $group_id=Db::name('video_discuss')->where('id',$ids['parent_id'])->value('group_id');
                $d=Db::name('video_discuss')->where('id',$res)->update(['group_id'=>$group_id]);
                if(!$d){
                    Db::name('video_discuss')->where('id',$res)->update(['delete_time'=>date('Y-m-d H:i:s',time())]);
                    return apiError('');
                }
            }
            $discuss_id=$res; //记录回复内容id
            $res=Db::name('video')->where('id',$video_id)->setInc('discuss_num',1);
            if(!$res){
                return apiError('评论累加失败');
            }
            //写入评论总表,发送评论消息
            $from_uid=$id;
            $from=Db::name('im_user')->where('uid',$from_uid)->value('accid');
            //通知发送给谁
            if($parent_uid!=0){
                // $parent_id===0表示父级评论，通知动态用户，$parent_id!=0 此评论是回复评论,通知parent_uid
                $to_uid=$parent_uid;
                $version=$this->getUserAppInfo($to_uid)['app_version'];
                $version=str_replace('V','',$version);
                if(strcmp($version,'1.2')<0){
                    return apiSuccess('评论添加/累加成功');
                }
            }else{
                //动态作者
                $to_uid=Db::name('video')->where('id',$video_id)->value('user_id');
            }
            $to=Db::name('im_user')->where('uid',$to_uid)->value('accid');
            $im=new Im();
            $comment_message['from']=$from_uid;
            $comment_message['to']=$to_uid;
            $comment_message['type']=2; //需求  0:动态  1：话题  2：短视频
            $comment_message['type_id']=$video_id; //动态id
            $comment_message['rid']=$discuss_id; //记录回复内容id
            $comment_message['time']=date('Y-m-d H:i:s',time());
            $msg_id=$this->totalComment($comment_message);  //消息id必须写入一个总表来保证消息id的唯一性，客户端显示时候，直接通过总表反查，而不必联查多张点赞记录子表

            if($from_uid!=$to_uid){ //自己不能给自己发IM消息
                $type=1; //0：点赞 ；1：评论 ； 100:系统内部通知消息
                $attach['msg_id']=$msg_id; //评论消息总表id
                $res=$im->sendMessage($from,$to,$type,$attach);
                if($res['status']){
                    $im->errorLog('动态-话题消息发送失败，video_id='.$video_id.'评论用户id='.$from_uid,$res['msg']);
                }
            }
            return apiSuccess('评论添加/累加成功');
        }
    }

    /**
     * 点赞动作
     */
    public function videoClickAction(){
        $video_id=input('post.video_id');
        if(!$video_id){
            return apiError('动态id不能为空');
        }
        //1 判断用户是否存在
        $id=input('post.id');
        $token=input('post.token');
        $res=$this->checkToken($id,$token);
        if($res['status']){
            return apiError($res['msg'],'',$res['code']);
        }
        //2 判断用户是否已经通过认证
//        $res=$this->isAccess($id);
//        if($res['identity_status']!=2){
//            return apiError($res['msg'],$res['identity_status']);
//        }
        //3 判断该用户是否对此评论点过赞记录
        $type=Db::name('video_click')->where(['user_id'=>$id,'video_id'=>$video_id])->value('type');
        if($type==1){
            //已经是点赞状态，直接设置为取消点赞
            $d=Db::name('video_click')->where(['user_id'=>$id,'video_id'=>$video_id])->update(['type'=>0]);
            Db::name('video')->where('id',$video_id)->setInc('click_num',-1);
//            $this->totalClickDel($id,0,$action_id); //清理点赞总表id
            if($d){
                return apiSuccess('取消点赞');
            }
        }elseif($type===0){//取消点赞状态，直接设置点赞状态为1，
            $d=Db::name('video_click')->where(['user_id'=>$id,'video_id'=>$video_id])->update(['type'=>1]);
            Db::name('video')->where('id',$video_id)->setInc('click_num',1);
            //注释掉下面部分，不能反复点赞发消息，只有第一次点赞可以收到消息
//            $this->totalClickDel($id,0,$action_id); //清理点赞总表id
//            if($d){
//                $im=new Im();
//                $from_uid=$id;
//                $from=Db::name('im_user')->where('uid',$from_uid)->value('accid');
//                $to_uid=Db::name('video')->where('id',$video_id)->value('user_id');
//                $to=Db::name('im_user')->where('uid',$to_uid)->value('accid');
//
//                $click_message['from']=$from_uid;
//                $click_message['to']=$to_uid;
//                $click_message['type']=2; //短视频点赞
//                $click_message['type_id']=$video_id; //动态id
//                $click_message['time']=date('Y-m-d H:i:s',time());
//                $res=Db::name('message_click')->where('from',$from_uid)->where('type',2)->where('type_id',$video_id)->find();//查询是否写如果
//                if(empty($res)) { //不能反复点赞，反复发送消息,同一个人对同一个
//                    $msg_id=$this->totalClick($click_message);  //消息id必须写入一个总表来保证消息id的唯一性，客户端显示时候，直接通过总表反查，而不必联查多张点赞记录子表
//                    if($from_uid!=$to_uid) { //自己不能给自己发IM消息
//                        $type=0; //系统应用通知消息0：点赞 ；系统应用通知消息  1：评论 ； 系统内部通知消息100
//                        $attach['msg_id']=$msg_id; //点赞消息总表id
//                        $res=$im->sendMessage($from,$to,$type,$attach);
//                        if($res['status']){
//                            $im->errorLog('动态需求-点赞消息发送失败，action_id='.$video_id.'点赞用户id='.$from_uid,$res['msg']);
//                        }
//                    }
//                }
//            }
            return apiSuccess('点赞成功');
        }else{//没有任何点赞记录的，直接insert点赞记录
            $data=[
                'user_id'=>$id,
                'video_id'=>$video_id,
                'create_time'=>date('Y-m-d H:i:s',time())
            ];
            //4. 记录点赞的action_id和用户id
            $res=Db::name('video_click')->insert($data);
            if(!$res){
                return apiError('添加点赞表格失败');
            }
            //修改video表 点赞数量加1
            $res=Db::name('video')->where('id',$video_id)->setInc('click_num',1);
            if(!$res){
                return apiError('点赞累加失败');
            }
            //写入点赞总表,发送点赞消息
            $im=new Im();
            $from_uid=$id;
            $from=Db::name('im_user')->where('uid',$from_uid)->value('accid');
            $to_uid=Db::name('video')->where('id',$video_id)->value('user_id');
            $to=Db::name('im_user')->where('uid',$to_uid)->value('accid');

            $click_message['from']=$from_uid;
            $click_message['to']=$to_uid;
            $click_message['type']=2; //0:动态  1：话题  2：短视频
            $click_message['type_id']=$video_id; //动态id
            $click_message['time']=date('Y-m-d H:i:s',time());
            $res=Db::name('message_click')->where('from',$from_uid)->where('type',2)->where('type_id',$video_id)->find();//查询是否写如果
            if(empty($res)) { //不能反复点赞，反复发送消息,同一个人对同一个
                $msg_id=$this->totalClick($click_message);  //消息id必须写入一个总表来保证消息id的唯一性，客户端显示时候，直接通过总表反查，而不必联查多张点赞记录子表
                if($from_uid!=$to_uid) { //自己不能给自己发IM消息
                    $type=0; //系统应用通知消息0：点赞 ；系统应用通知消息  1：评论 ； 系统内部通知消息100
                    $attach['msg_id']=$msg_id; //点赞消息总表id
                    $res=$im->sendMessage($from,$to,$type,$attach);
                    if($res['status']){
                        $im->errorLog('动态需求-点赞消息发送失败，action_id='.$video_id.'点赞用户id='.$from_uid,$res['msg']);
                    }
                }
            }
            apiSuccess('点赞累加/添加成功');

        }

    }
    public function discussClickAction(){
        $discuss_id=input('post.discuss_id');
        if(!$discuss_id){
            return apiError('动态id不能为空');
        }
        //1 判断用户是否存在
        $id=input('post.id');
        $token=input('post.token');
        $res=$this->checkToken($id,$token);
        if($res['status']){
            return apiError($res['msg'],'',$res['code']);
        }
        //2 判断用户是否已经通过认证
//        $res=$this->isAccess($id);
//        if($res['identity_status']!=2){
//            return apiError($res['msg'],$res['identity_status']);
//        }
        //3 判断该用户是否对此评论点过赞记录
        $type=Db::name('video_discuss_click')->where(['user_id'=>$id,'discuss_id'=>$discuss_id])->value('type');
        if($type==1){
            //有记录
            $d=Db::name('video_discuss_click')->where(['user_id'=>$id,'discuss_id'=>$discuss_id])->update(['type'=>0]);
            if($d){
                Db::name('video_discuss')->where('id',$discuss_id)->setInc('click_num',-1);
                return apiSuccess('取消点赞');
            }
        }elseif($type===0){
            $d=Db::name('video_discuss_click')->where(['user_id'=>$id,'discuss_id'=>$discuss_id])->update(['type'=>1]);
            if($d){
                Db::name('video_discuss')->where('id',$discuss_id)->setInc('click_num',1);
                return apiSuccess('点赞成功');
            }
        }else{
            $data=[
                'user_id'=>$id,
                'discuss_id'=>$discuss_id,
                'create_time'=>date('Y-m-d H:i:s',time())
            ];
            //4. 记录点赞的discuss_id和用户id
            $res=Db::name('video_discuss_click')->insert($data);
            if(!$res){
                return apiError('添加点赞表格失败');
            }
            Db::name('video_discuss')->where('id',$discuss_id)->setInc('click_num',1);
            apiSuccess('点赞成功');
        }
    }
    /**
     * 个人详情 personalDetails
     * id token 用户
     * user_id 查看别人动态时候传递
     * type 查看like还是action
     */
    public function personalDetails(){
        $user_id=input('get.user_id'); //头像id
        $id=input('get.id'); //用户id
        $type=(int)input('get.type',0);  //0 我的视频   1喜欢
        $page=input('get.page');
        $time=input('get.time');//第一次请求时间
        if($page<1||$page==''){
            return apiError('页码错误');
        }
        if(!$time){
            return apiError('时间不能为空');
        }
        $timestamp=$time;
        if($page==1){
            $time=time();
            $timestamp=time();
        }
        $time=date('Y-m-d H:i:s',$time);
        if($user_id==''){
            return apiError('头像id不为空');
        }
        $d=array();
        $dd=[];
        $d2=array();
        if($user_id){
            //判断是否有该用户
            $is=Db::name('user')->where('id',$user_id)->find();
            if(!$is){
                return apiError('该用户不存在');
            }
            $user_info=$this->userInfo($user_id);
            $data=array();
            $is_access=$this->isAccess($user_id);
            $my_num=$this->myNum($user_id);
            if($user_info){
                $data['user_id']=$user_info['user_id'];
                $data['id_code']=$user_info['id_code'];
                $data['nickname']=$user_info['nickname'];
                $data['head_pic']=$user_info['head_pic'];
                $data['back_pic']=$user_info['back_pic'];
                $data['sex']=$user_info['sex'];
                $data['send_status']=$user_info['send_status'];
                $data['clickVL_status']=$user_info['clickVL_status'];
                $data['fansL_status']=$user_info['fansL_status'];
                $data['attentionL_status']=$user_info['attentionL_status'];
                $data['birthday']=null;
                if($user_info['birthday']){
                    $data['birthday']=strtotime($user_info['birthday']);
                }
                $data['signature']=$user_info['signature'];
                if($user_info['city_id']){
                    $data['city']['name']=$user_info['city'];
                    $data['city']['id']=$user_info['city_id'];
                    $data['city']['province']['name']=$user_info['pname'];
                    $data['city']['province']['id']=$user_info['province_id'];
                }else{
                    $data['city']=null;
                }
                if($user_info['college']){
                    $data['college']['id']=$user_info['coid'];
                    $data['college']['name']=$user_info['college'];
                }else{
                    $data['college']=null;
                }
                $data['is_access']=$is_access['identity_status'];

                $data['attention_num']=$my_num['attention_num']; //关注数量
                $data['fans_num']=$my_num['fans_num']; //粉丝数量
                $data['is_attention']=0; //未关注

            }
            $res1=Db::name('user_relation')->where(['user_id'=>$id,'attention_id'=>$user_id,'is_cancel'=>0])->find();
            $res2=Db::name('user_relation')->where(['user_id'=>$user_id,'attention_id'=>$id,'is_cancel'=>0])->find();
            if($res1){
                $data['is_attention']=1; //互关注
            }
            if($res1&&$res2){
                $data['is_attention']=2; //互关注
            }
            if($user_id==$id){
                $data['is_attention']=3; //自己
            }
            //被点赞数量
            $click_num=Db::name('video')
                ->where(['user_id'=>$user_id])
                ->sum('click_num');
            $data['click_num']=$click_num;
            //查看别人的个人信息
            $w=[
                'v.delete_time'=>null,
                'v.create_time'=>['<=',$time],
                'v.status'=>0
            ];
            if($type===0){  //作品
                $w['v.user_id']=$user_id;
                $view[]=1; //定义可见视频集 【所有人可见的视频】
                if($data['is_attention']==1||$data['is_attention']==2){  //如果是粉丝进来【所有人可见的视频、粉丝可见的视频】
                    $view[]=2;
                }elseif($data['is_attention']==3){  //如果是本人进来【所有人可见的视频、粉丝可见的视频、自己可见的视频】
                    $view[]=2;
                    $view[]=3;
                    $w['v.status']=['in',[0,1]];
                }
                $w['v.view_auth']=array('in',$view);
                $d=$this->videoList($page,$w);
//if($page==1){
//    $w['v.status']=1;
//    $status1=$this->videoList($page,$w);
//    $d=array_merge($status1,$d);
//}
                if($d){
                    foreach ($d as $k=>&$v){
                        $dd[$k]['click_num']=$v['click_num'];
                        $dd[$k]['discuss_num']=$v['discuss_num'];
                        $dd[$k]['id']=$v['id'];
                        $r=Db::name('video_click')->where(['user_id'=>$id,'video_id'=>$v['id'],'type'=>1])->find();
                        if($r){
                            $dd[$k]['is_click']=1;
                        }else{
                            $dd[$k]['is_click']=0;
                        }
                        $dd[$k]['user']['user_id']=$v['user_id'];
                        $dd[$k]['user']['nickname']=$v['user_nickname'];
                        $dd[$k]['user']['head_pic']=$v['user_head_pic'];
                        if($v['coid']){
                            $dd[$k]['user']['college']['id']=$v['coid'];
                            $dd[$k]['user']['college']['name']=$v['college'];
                        }else{
                            $dd[$k]['user']['college']=null;
                        }

                        $dd[$k]['music']['music_id']=$v['music_id'];
                        $dd[$k]['music']['music_type']=$v['music_type'];
                        $dd[$k]['music']['name']=$v['music_name'];
                        $dd[$k]['music']['singer']=$v['music_singer'];
                        $dd[$k]['music']['pic']=$v['music_pic'];

                        $dd[$k]['title']=$v['title'];

                        $dd[$k]['video']['video']=$v['video'];
                        $dd[$k]['video']['cover']=$v['video_cover'];
                        $dd[$k]['video']['height']=$v['height'];
                        $dd[$k]['video']['width']=$v['width'];
                        $dd[$k]['video']['v_create_time']=$v['v_create_time'];

                    }

                }
            }
            if($type===1){  //喜欢列表
                //判断是否公开
                if($is['clickVL_status']==1||$id==$user_id){
                    $videos=Db::name('video_click')
                        ->where(['user_id'=>$user_id,'type'=>1])
                        ->column('video_id');
                    if($data['is_attention']==3){  //如果是自己查看自己不加限制条件
                        $w['v.id']=['in',$videos];
                    }else{  //不是自己查看，查出该视频的user_id（用来判断与登录用户之间的关系） 和 view_auth（关系详情）
                        $vs=Db::name('video')
                            ->where(['id'=>['in',$videos]])
                            ->field('id,user_id,view_auth')
                            ->select();
                        $ids=[];
                        if($vs){
                            foreach ($vs as $k=> &$v){
                                if($v['view_auth']==1){
                                    $ids[]=$v['id'];
                                }
                                if ($v['view_auth']==2){
                                    $res1=Db::name('user_relation')
                                        ->where(['user_id'=>$id,'attention_id'=>$v['user_id'],'is_cancel'=>0])
                                        ->find();
                                    if($res1){
                                        $ids[]=$v['id'];
                                    }
                                }
                            }
                        }
                        $w['v.id']=['in',$ids];
                    }
                    $d=$this->videoList($page,$w);
                    if($d){
                        foreach ($d as $k=>&$v){
                            $dd[$k]['click_num']=$v['click_num'];
                            $dd[$k]['discuss_num']=$v['discuss_num'];
                            $dd[$k]['id']=$v['id'];
                            $r=Db::name('video_click')->where(['user_id'=>$id,'video_id'=>$v['id'],'type'=>1])->find();
                            if($r){
                                $dd[$k]['is_click']=1;
                            }else{
                                $dd[$k]['is_click']=0;
                            }
                            $dd[$k]['user']['user_id']=$v['user_id'];
                            $dd[$k]['user']['nickname']=$v['user_nickname'];
                            $dd[$k]['user']['head_pic']=$v['user_head_pic'];
                            if($v['coid']){
                                $dd[$k]['user']['college']['id']=$v['coid'];
                                $dd[$k]['user']['college']['name']=$v['college'];
                            }else{
                                $dd[$k]['user']['college']=null;
                            }


                            $dd[$k]['music']['music_id']=$v['music_id'];
                            $dd[$k]['music']['music_type']=$v['music_type'];
                            $dd[$k]['music']['name']=$v['music_name'];
                            $dd[$k]['music']['singer']=$v['music_singer'];
                            $dd[$k]['music']['pic']=$v['music_pic'];

                            $dd[$k]['title']=$v['title'];

                            $dd[$k]['video']['video']=$v['video'];
                            $dd[$k]['video']['cover']=$v['video_cover'];
                            $dd[$k]['video']['height']=$v['height'];
                            $dd[$k]['video']['width']=$v['width'];
                            $dd[$k]['video']['v_create_time']=$v['v_create_time'];
                            $dd[$k]['is_attention']=0;
                            $res1=Db::name('user_relation')->where(['user_id'=>$id,'attention_id'=>$v['user_id'],'is_cancel'=>0])->find();
                            if($res1){
                                $dd[$k]['is_attention']=1; //互关注
                            }
                        }

                    }
                }else{  //不公开
                    $d2['user']=$data;
                    $d2['video_list']=$dd;
                    $return['timestamp']=$timestamp;
                    $return['data']=$d2;
                    return apiSuccess('无权访问该列表',$return);
                }
            }
            $d2['user']=$data;
            $d2['video_list']=$dd;
            $return['timestamp']=$timestamp;
            $return['data']=$d2;
            return apiSuccess('个人详情',$return);
        }else{
            return apiError('user_id不能为空');
        }
    }

    /**
     * 我的作品(废弃)
     * id token time page  type
     */
    public function myVideoList(){
        $page=input('get.page');
        $time=input('get.time');//第一次请求时间
        $type=(int)input('get.type',0);  //0 作品  1  喜欢
        if($page==''||$page<1){
            apiError('请求页码有误');
            return;
        }
        if(!$time){
            return apiError('时间不能为空');
        }
        $timestamp=$time;
        if($page==1){
            $time=time();
            $timestamp=time();
        }
        $time=date('Y-m-d H:i:s',$time);
        //判断用户是否存在
        $id=input('get.id');
        $token=input('get.token');
        $res=$this->checkToken($id,$token);
        if($res['status']){
            return apiError($res['msg'],'',$res['code']);
        }
        //2 判断用户是否已经通过认证
//        $res=$this->isAccess($id);
//        if($res['identity_status']!=2){
//            return apiError($res['msg'],$res['identity_status']);
//        }
        $w=[
            'v.delete_time'=>null,
            'v.create_time'=>['<=',$time]
        ];
        if($type===0){
            $w['v.user_id']=$id;
        }
        if($type===1){
            $videos=Db::name('video_click')->where(['user_id'=>$id,'type'=>1])->column('video_id');
            $w['v.id']=['in',$videos];
        }
        $video_list=$this->videoList($page,$w);
        if($video_list!=''){
            foreach ($video_list as $k=>&$v){
                $r=Db::name('video_click')->where(['user_id'=>$id,'video_id'=>$v['id'],'type'=>1])->find();
                if($r){
                    $v['is_click']=1;
                }else{
                    $v['is_click']=0;
                }
            }
        }
        $return['timestamp']=$timestamp;
        $return['data']=$video_list;
        if($page==1){
            $w['v.status']=1;
            $ing=Db::name('video v')
                ->join('user u','v.user_id=u.id','LEFT')
                ->join('music m','v.music_id=m.id','LEFT')
                ->field('v.id,v.user_id,v.title,v.video,v.video_cover,v.height,v.width,v.create_time v_create_time,u.nickname user_nickname,u.head_pic user_head_pic,m.id music_id,m.name music_name,m.pic music_pic,v.click_num,v.discuss_num')
                ->order('v.create_time desc')
                ->where($w)
                ->select();
            if(!empty($ing)){
                foreach ($ing as $k=>&$v){
                    $v['user_head_pic']=ApiUrl.$v['user_head_pic'];
                    $v['v_create_time']=$this->timeToHour($v['v_create_time']);
                    if($v['music_name']){
                        $v['music_pic']=AliUrl.$v['music_pic'];
                    }
                    $v['title']=$this->userTextDecode($v['title']);
                    $r=Db::name('video_click')->where(['user_id'=>$id,'video_id'=>$v['id'],'type'=>1])->find();
                    if($r){
                        $v['is_click']=1;
                    }else{
                        $v['is_click']=0;
                    }
                }
                $return['data']=array_merge($ing,$video_list);
            }
        }
        return apiSuccess('我的作品',$return);
    }

    /**
     * 举报，拉黑，发消息，删除
     * report black sendmsg delete
     * type video_id user_id   id   token
     */
    public function multiFunction(){
        $type=input('type');           //操作的类型report black delete
        $video_id=input('video_id');//操作的动态
        $user_id=input('user_id'); //被举报人
        //1.判断用户是否存在
        $id=input('id');
        $token=input('token');
        $res=$this->checkToken($id,$token);
        if($res['status']){
            return apiError($res['msg'],'',$res['code']);
        }
        //2.判断用户是否已经通过认证
//        $res=$this->isAccess($id);
//        if($res['identity_status']!=2){
//            return apiError($res['msg'],$res['identity_status']);
//        }
        //3.判断处理类型不能为空
        if($type==''){
            return apiError('处理类型不能为空');
        }
        //4.判断处理类型 开始对应操作
        if($type=='report'){
            //动态举报
            if($user_id!=''&&$video_id!=''){
                if($user_id==$id){
                    return apiError('不能将自己举报');
                }
                //判断该用户和动态是否来自一条记录
                $a=Db::name('video')->where(['id'=>$video_id,'user_id'=>$user_id])->find();
                if(!$a){
                    return apiError('动态不是该用户发表');
                }
                //从动态列表传递
                //1 是否已经举报
                $yes=Db::name('report')->where(['report_user_id'=>$id,'action_user_id'=>$user_id,'action_id'=>$video_id])->find();
                if($yes){
                    return apiSuccess('举报成功','');
                }
                //将动态的id，发表动态的用户和举报人保存数据库
                $data=[
                    'action_id'=>$video_id,
                    'action_user_id'=>$user_id,
                    'report_user_id'=>$id,
                    'type'=>4,
                    'create_time'=>date('Y-m-d H:i:s',time())
                ];
                $res=Db::name('report')
                    ->insert($data);
                if(!$res){
                    return apiError('举报信息保存错误');
                }
                return apiSuccess('举报成功，正在处理','');

            }
            //2 从个人中心传递 $user_id
            // 2.1 是否已经举报
            if($user_id==''){
                return apiError('$user_id不能为空');
            }
            if($user_id==$id){
                return apiError('不能将自己举报');
            }
            $yes=Db::name('report')->where(['report_user_id'=>$id,'action_user_id'=>$user_id])->find();
            if($yes){
                return apiSuccess('举报成功','');
            }
            //将动态的id，发表动态的用户和举报人保存数据库
            $data=[
                'action_user_id'=>$user_id,
                'report_user_id'=>$id,
                'type'=>1,
                'create_time'=>date('Y-m-d H:i:s',time())
            ];
            $res=Db::name('report')
                ->insert($data);
            if(!$res){
                return apiError('举报信息保存错误');
            }
            return apiSuccess('举报成功，正在处理','');
        }elseif ($type=='black'){
            //拉黑 不再接收此人动态
            if($user_id==''){
                return apiError('拉黑的用户id不能为空');
            }
            if($user_id==$id){
                return apiError('不能将自己拉黑');
            }
            //判断之前是否拉黑
            $data=array();

            $is_cancel=Db::name('user_black')->where(['user_id'=>$id,'black_user_id'=>$user_id])->value('is_cancel');
            if($is_cancel===0){
                $data['is_cancel']=1;
                $res=Db::name('user_black')
                    ->where(['user_id'=>$id,'black_user_id'=>$user_id])
                    ->update(['is_cancel'=>1]);
                if($res){
                    return apiSuccess('取消拉黑成功',$data); //1 没有拉黑
                }
            }elseif ($is_cancel==1){
                $data['is_cancel']=0;
                $res=Db::name('user_black')
                    ->where(['user_id'=>$id,'black_user_id'=>$user_id])
                    ->update(['is_cancel'=>0]);
                if($res){
                    //判断是否有关注如果关注则取消关注
                    $id=Db::name('user_relation')->where(['user_id'=>$id,'attention_id'=>$user_id,'is_cancel'=>0])->value('id');
                    if($id){
                        Db::name('user_relation')->where('id',$id)->update(['is_cancel'=>1]);
                    }
                    return apiSuccess('拉黑成功',$data); // 0拉黑成功
                }
            }
            $d=[
                'user_id'=>$id,
                'black_user_id'=>$user_id,
                'is_cancel'=>0,
                'create_time'=>date('Y-m-d H:i:s',time())
            ];
            //2.将动态发布人的id和举报人的id保存到用户拉黑名单表
            $res=Db::name('user_black')->insert($d);

            $data['is_cancel']=0;
            if(!$res){
                return apiError('拉黑名单表添加错误');
            }
            //判断是否有关注如果关注则取消关注
            $id=Db::name('user_relation')->where(['user_id'=>$id,'attention_id'=>$user_id,'is_cancel'=>0])->value('id');
            if($id){
                Db::name('user_relation')->where('id',$id)->update(['is_cancel'=>1]);
            }
            return apiSuccess('拉黑成功',$data);
        }elseif ($type=='delete'){
            //1.判断是否有删除操作权利
            $user_id=Db::name('video')->where('id',$video_id)->value('user_id');
            if($user_id!=$id){
                return apiError('没有删除权利');
            }
            //2.判断该动态是否已经被删除
            $action=Db::name('video')->where('id',$video_id)->value('delete_time');
            if($action){
                return apiError('该动态已经被删除');
            }
            $res=Db::name('video')->where('id',$video_id)->update(['delete_time'=>date('Y-m-d H:i:s',time()),'status'=>2]);
            if($res===false){
                return apiError('删除动态错误');
            }
            return apiSuccess('删除成功','');
        }else{
            return apiError('不在处理范围之内');
        }


    }

    /**
     * 筛选列表显示
     * filtrateList
     */
    public function filtrateList(){
        $data=[];
        //查找学校列表
        $coids=[22001,22002,22003,22004,22005,22006,22007,22008,22009,22010,22011,22013,22014,22015,22016,22017,22033,22037,22073,22081,22086,22087,22088,22094,22095,22096,22097,22098,22099,22100];
        $data['college']=Db::name('college')->where(['provinceID'=>510000])->where('coid','in',$coids)->select();
        $data['school']='';
        $data['grade']=Db::name('grade')->order('id desc')->select();
        $data['sex']=[0=>['id'=>0,'sex'=>'女'],1=>['id'=>1,'sex'=>'男']];
        $data['skill']=Db::name('skill')->select();
        return apiSuccess('',$data);

    }

    /**
     * 学校搜索列表
     */
    public function collegeList(){
        $college=input('college');
        $where=[];
        if($college!=''){
            $where['name']=['like','%'.$college.'%'];
        }else{
            $where['coid']=['in',[22001,22002,22003,22004,22005,22006,22007,22008,22009,22010]];
        }
        $res=Db::name('college')->where($where)->select();
        apiSuccess('大学信息',$res);
    }

    /**
     * Created by zyjun
     * Info:发布短视频，本接口在获取上传授权之后，客户端上传视频之前调用，以此保证回调通知时数据存在
     */
    public function publishVideo(){
        #验证登录
        $id = input('post.id');
        $token = input('post.token');
        $res = $this->checkToken($id, $token);
        if ($res['status']) {
            return apiError($res['msg'],'',$res['code']);
        }
        #验证权限
//        $res=$this->isAccess($id);
//        if($res['identity_status']!=2){
//            return apiError($res['msg'],$res['identity_status']);
//        }
        #接收参数
        $title=$this->userTextEncode(input('post.title')); //标题描述
        $tags=input('post.tags');//标签
        $data['user_id']=$id;
        $video=input('video_addr'); //点播存储的视频MD5
        $video_cover=input('video_cover'); //点播存储的视频封面
        $view_auth=input('view_auth'); //视频可见范围参数错误
        $music_type=input('post.music_type'); //音乐0:在线音乐  1：用户上传音乐
        $music_id=input('music_id'); //音乐id
        $music_start=input('music_start'); //音乐片段开始时间
        $music_end=input('music_end'); //音乐片段结束时间，用于拍摄同款短视频功能，暂定


        #视频
        if(empty($video)){
            return apiError('视频ID未填写');
        }
        if(empty($video_cover)){
            return apiError('视频封面未填写');
        }
        if($this->checkVideo($video)){
            return apiError('视频参数格式错误');
        }
        if($this->checkVideoCover($video_cover)){
            return apiError('视频封面格式错误');
        }
        if(!empty($title)){
            if(strlen($title)>128){
                return apiError('标题长度超过限制');
            }
        }
        if(!empty($tags)){
            if(strlen($tags)>50){
                return apiError('标签长度超过限制');
            }
            $data['tags']=trim($tags,', '); //去掉左右,和空格
        }
        if(!in_array($view_auth,[1,2,3])){
            return apiError('视频可见范围参数错误');
        }
        if(!in_array($music_type,[0,1])){
            return apiError('音乐类型错误');
        }
        if(!empty($music_id)){
            if($this->checkInt($music_id,'','')){
                return apiError('音乐ID参数错误');
            }
        }
        $data['video']=$video;
        $data['video_cover']=$video_cover;
        $data['title']=$title;
        $data['view_auth']=$view_auth;
        $data['music_type']=$music_type;
        $data['music_id']=$music_id;
        $data['music_start']=$music_start;
        $data['music_end']=$music_end;
        $data['status']=1; //发视频一律需要审核后显示
        #开始发布
        $res=$this->doPublish($data);
        if($res['status']){
            return apiError($res['msg']);
        }
        #发布成功后记录音乐使用次数
        if($music_type==0){
            Db::name('music')->where('id',$music_id)->setInc('down_num');
            Db::name('music')->where('id',$music_id)->setField('update_time',date('Y-m-d',time()));
        }
        if($music_type==1){
            if(!empty($music_id)){//拍同款用户上传的音乐使用统计
                Db::name('music_user')->where('id',$music_id)->setInc('down_num');
            }else{
                #如果是本地视频上传，视为用户原创音乐。记录到music_user表，转码成功后，回调时写入音乐地址
                $data2['video_addr']=$video;
                $data2['uid']=$id;
                $data2['create_time']=date('Y-m-d H:i:s',time());
                Db::name('music_user')->insert($data2);
            }
        }
        apiSuccess('发布成功');
    }

    /**
     * Created by zyjun
     * Info:发布函数，同时限制发布频率,默认是发布显示，图文视频审核单独在外部判断
     */
    private function  doPublish($data){
        Db::startTrans();
        try {
            #查询这个视频是否上传过
            $res=Db::name('video')->where('video', $data['video'])->find();
            if(!empty($res)){
                $re['status']=1;
                $re['msg']='请勿重复发布';
                return $re;
            }
            #禁止频繁发布
            $create_time=time();
            $data['create_time']=date('Y-m-d H:i:s',$create_time);
            $uid=$data['user_id'];
            $time = Db::name('video')->where('user_id', $uid)->order('id desc')->lock(true)->value('create_time');
            $time = strtotime($time);
            $nowtime = time();
            if ($nowtime - $time < 5) { //发布间隔2秒
                $re['status']=1;
                $re['msg']='请勿频繁发布';
                return $re;
            }
            $video_id=Db::name('video')->insertGetId($data);
            if(empty($video_id)){
                $re['status']=1;
                $re['msg']='发布失败';
                return $re;
            }
            Db::commit();
            $res=Db::name('video')->where('id',$video_id)->find();
            $re['status']=0;
            $re['msg']='发布成功';
            $re['data']=$res;
            return $re;
        } catch (\Exception $e){
            // 回滚事务
            Db::rollback();
            $re['status']=1;
            $re['msg']='发布失败';
            return $re;
        }
    }

    /**
     * Created by zyjun
     * Info:创建短视频临时sts权限
     */
    public function getStsAuth(){
        $type=input('type');
        if($type=='upload'){
            #认证用户信息
            $id = input('post.id');
            $token = input('post.token');
            $res = $this->checkToken($id, $token);
            if ($res['status']) {
                return apiError($res['msg'],'',$res['code']);
            }
            #验证权限
//            $res=$this->isAccess($id);
//            if($res['identity_status']!=2){
//                return apiError($res['msg'],$res['identity_status']);
//            }
            $role='acs:ram::1324203554625576:role/uploadvideo';
        }else{
            $role='acs:ram::1324203554625576:role/playvideo';  //播放不需要登录
        }
        $access_key_id='LTAIYVDlNgRgzDdt';
        $access_key_secret='NLwh48KKs89TbGyj89GMuOUeJsema5';
        $region_id='cn-shanghai';
        $endpoint='sts.cn-shanghai.aliyuncs.com';

        $client_name='video';
        $duration_seconds=3600;
        $obj=new Alists();
        $res=$obj->createSts($access_key_id,$access_key_secret,$region_id,$endpoint,$role,$client_name,$duration_seconds);
        if($res['status']){
            return apiError('获取授权失败');
        }
        $data=$res['data'];
        apiSuccess('授权信息',$data);
    }

    /**
     * Created by zyjun
     * Info：获取播放列表，一次获取20条
     */
    public function getVideoList(){
        $obj=new Pushvideo();
        $page=input('get.page');
        $time=input('get.time');
        if(empty($page)||$this->checkInt($page,'','')){
            return apiError('分页参数错误');
        }
        #验证登录
        $uid=$id = input('get.id');
        $token = input('get.token');
        if($id&&$token){
            $res = $this->checkToken($id, $token);
            if ($res['status']) {
                #不返回错误，直接返回游客查看的信息
                $res=$obj->getNomalVideoList($page,$time);
                $data['timestamp']=time();
                $data['data']=$res;
                return apiSuccess('视频列表',$data);
            }
            #开启智能推荐，没有关键词暂时不写
            $res=$obj->getRecommVideoList($page,$time,$uid);
            $data['timestamp']=time();
            $data['data']=$res;
            return apiSuccess('视频列表',$data);
        }else{
            $res=$obj->getNomalVideoList($page,$time);
            $data['timestamp']=time();
            $data['data']=$res;
            return apiSuccess('视频列表',$data);
        }
    }

    /**
     * Created by zyjun
     * Info:拍同款视频列表页
     * 如果同款视频音乐是在线音乐，那么拍同款列表顶部返回在线音乐的封面，名称，和使用量。
     * 如果来源于用户上传音乐，则显示该用户昵称，头像，使用量
     */
    public function getSameVideoList(){
        $id = $uid=input('get.id');
        $token = input('get.token');
        $music_id=input('get.music_id');
        $music_type=input('get.music_type');
        $page=input('get.page');
        $time=input('get.time');
        if($id&&$token){
            $res = $this->checkToken($id, $token);
            if ($res['status']) {
                return apiError($res['msg'],'',$res['code']);
            }
        }
        if(!in_array($music_type,[0,1])){
            return apiError('音乐类型参数错误');
        }
        if(empty($music_id)||$this->checkInt($music_id,'','')){
            return apiError('音乐ID参数错误');
        }
        if(empty($page)||$this->checkInt($page,'','')){
            return apiError('分页参数错误');
        }
        if(!empty($time)){
            if($this->checkInt($time,10,10)){
                return apiError('时间戳参数错误');
            }
            $time=date('Y-m-d H:i:s',$time);
        }else{
            $time=date('Y-m-d H:i:s',time());
        }
        #分页参数
        $pageSize=20;
        $pageStart=($page-1)*$pageSize;
        #在线音乐的同款
        if($music_type==0){
            #先判断这首音乐是否存在，音乐不存在会影响后面的功能就行，没有音乐直接返回空
            $res=Db::name('music')->where('id',$music_id)->field('id as music_id,name,music,pic,down_num')->find();
            if(!empty($res)){
                $res['pic']=AliUrl.$res['pic'];
                $res['music']=AliUrl.$res['music'];
            }else{
              return apiError('未找到同款音乐信息');
            }
            $res['music_type']=(int)$music_type;
            #登陆的用户判断是否收藏
            if(!empty($uid)){
                $where['user_id']=$uid;
                $where['music_id']=$music_id;
                $where['music_type']=0;
                $where['delete_time']=NULL;
                $res['is_collect']=Db::name('user_music')->where($where)->value('status');
                if(empty($res['is_collect'])){
                    $res['is_collect']=0;
                }
            }else{
               $res['is_collect']=0;
            }
            $res['down_num']=Db::name('video')->where('status',0)->where('music_id',$music_id)->where('music_type',0)->count();
            $return_data['music']=$res;
            #判断首发视频
            $return_data['original_video']=$this->getSameOriginalVideo($id,$music_type,$music_id);
            if(!empty( $return_data['original_video'])){
                $original_video[0]=$return_data['original_video'];
                $return_data['original_video']=$this->formatVideo($original_video);
            }
            #如果登录了
            if(!empty($uid)){
                $field=' a.id,a.user_id,a.title,a.video_cover,a.video,a.width,a.height,a.view_num,a.music_type, CASE d.type WHEN 1 THEN 1 WHEN 0 THEN 0 ELSE 0 END as is_click,CASE e.is_cancel WHEN 1 THEN 0 WHEN 0 THEN 1 ELSE 0 END as is_attention,a.click_num,a.discuss_num,a.tags,a.music_id,b.id user_id,b.nickname user_nickname,CONCAT("'.ApiUrl.'",b.head_pic) AS user_head_pic,c.singer,c.name music_name,CONCAT("'.AliUrl.'",c.pic) AS music_pic,f.name college_name,f.coid college_id';
                $child_sql="SELECT black_user_id FROM limi_user_black WHERE user_id=$uid";
                $where='a.music_type=0 AND a.music_id='.$music_id.' AND a.create_time < "'.$time.'" AND a.view_auth=1 AND a.status=0 AND a.user_id NOT IN ('.$child_sql.')';
                $query="SELECT $field FROM limi_video AS a LEFT JOIN limi_user AS b ON a.user_id = b.id LEFT JOIN limi_music AS c ON (a.music_id = c.id AND a.music_type=0) LEFT JOIN limi_video_click AS d ON (a.id = d.video_id AND d.user_id=$uid ) LEFT JOIN limi_user_relation AS e ON (e.attention_id=a.user_id AND e.user_id=".$uid." ) LEFT JOIN limi_college AS f ON b.college_id=f.coid WHERE $where ORDER BY a.click_num DESC LIMIT $pageStart,$pageSize";
                $res=Db::query($query);
                $data['same_video']=$res;
            }else{
                #没登录
                $field=' a.id,a.user_id,a.title,a.video_cover,a.video,a.width,a.height,a.view_num,a.click_num,a.discuss_num,a.tags,a.music_id,a.music_type,b.id user_id,b.nickname user_nickname,CONCAT("'.ApiUrl.'",b.head_pic) AS user_head_pic,c.singer,c.name music_name,CONCAT("'.AliUrl.'",c.pic) AS music_pic,f.name college_name,f.coid college_id';
                $where='a.music_type=0 AND a.music_id='.$music_id.' AND a.create_time < "'.$time.'" AND a.view_auth=1 AND a.status=0';
                $query="SELECT $field FROM limi_video AS a LEFT JOIN limi_user AS b ON a.user_id = b.id LEFT JOIN limi_music AS c ON (a.music_id = c.id AND a.music_type=0)  LEFT JOIN limi_college AS f ON b.college_id=f.coid WHERE $where ORDER BY a.click_num DESC LIMIT $pageStart,$pageSize";
                $res=Db::query($query);
                $data['same_video']=$res;
            }

            #前端要求的格式
            if(empty($data['same_video'])){
                return apiSuccess('没有查询到记录');
            }
            #去掉返回的视频里的原视频，防止出现2次,只有一款原视频
            foreach ($data['same_video'] as $key=>$val){
                if($val['id']==$return_data['original_video'][0]['id']){
                    unset($data['same_video'][$key]);
                }
            }
            $data['same_video']=array_values($data['same_video']);
            $return_data['video']=$this->formatVideo($data['same_video']);
            $return_data2['time']=strtotime($time);
            $return_data2['data']=$return_data;
            apiSuccess('拍同款列表',$return_data2);

        }
        #用户上传音乐的同款
        if($music_type==1){
            #获取用户上传音乐，用户头像，昵称，使用量
            $res=Db::name('music_user')->alias('a')
                ->join('user b','a.uid=b.id','LEFT')
                ->where('a.id',$music_id)->field('b.nickname,b.head_pic,a.down_num,a.music,a.id as music_id')->find();
            if(!empty($res['head_pic'])){
                $res['pic']=ApiUrl.$res['head_pic'];
            }else{
                return apiError('未找到同款音乐信息');
            }
            $res['name']=$res['nickname'].'的原创音乐';
            unset($res['nickname']);
            unset($res['head_pic']);
            $res['music_type']=(int)$music_type;
            #登陆的用户判断是否收藏
            if(!empty($uid)){
                $where['user_id']=$uid;
                $where['music_id']=$music_id;
                $where['music_type']=1;
                $where['delete_time']=NULL;
                $res['is_collect']=Db::name('user_music')->where($where)->value('status');
                if(empty($res['is_collect'])){
                    $res['is_collect']=0;
                }
            }else{
                $res['is_collect']=0;
            }
            $res['down_num']=Db::name('video')->where('status',0)->where('music_id',$music_id)->where('music_type',1)->count();
            $return_data['music']=$res;
            #判断首发视频
            $return_data['original_video']=$this->getSameOriginalVideo($id,$music_type,$music_id);
            if(!empty( $return_data['original_video'])){
                $original_video[0]=$return_data['original_video'];
                $return_data['original_video']=$this->formatVideo($original_video);
            }
            #获取这款音乐的同款视频
            #如果登录了
            if(!empty($uid)){
                $field=' a.id,a.user_id,a.title,a.video_cover,a.video,a.width,a.height,a.view_num,a.music_type, CASE d.type WHEN 1 THEN 1 WHEN 0 THEN 0 ELSE 0 END as is_click,CASE e.is_cancel WHEN 1 THEN 0 WHEN 0 THEN 1 ELSE 0 END as is_attention,a.click_num,a.discuss_num,a.tags,a.music_id,b.id user_id,b.nickname user_nickname,CONCAT("'.ApiUrl.'",b.head_pic) AS user_head_pic,c.singer,c.name music_name,CONCAT("'.AliUrl.'",c.pic) AS music_pic,f.name college_name,f.coid college_id';
                $child_sql="SELECT black_user_id FROM limi_user_black WHERE user_id=$uid";
                $where='a.music_type=1 AND a.music_id='.$music_id.' AND a.create_time < "'.$time.'" AND a.view_auth=1 AND a.status=0 AND a.user_id NOT IN ('.$child_sql.')';
                $query="SELECT $field FROM limi_video AS a LEFT JOIN limi_user AS b ON a.user_id = b.id LEFT JOIN limi_music AS c ON (a.music_id = c.id AND a.music_type=0) LEFT JOIN limi_video_click AS d ON (a.id = d.video_id AND d.user_id=$uid ) LEFT JOIN limi_user_relation AS e ON (e.attention_id=a.user_id AND e.user_id=".$uid." ) LEFT JOIN limi_college AS f ON b.college_id=f.coid WHERE $where ORDER BY a.click_num DESC LIMIT $pageStart,$pageSize";
                $res=Db::query($query);
                $data['same_video']=$res;
            }else{
                #没登录
                $field=' a.id,a.user_id,a.title,a.video_cover,a.video,a.width,a.height,a.view_num,a.click_num,a.discuss_num,a.tags,a.music_id,a.music_type,b.id user_id,b.nickname user_nickname,CONCAT("'.ApiUrl.'",b.head_pic) AS user_head_pic,c.singer,c.name music_name,CONCAT("'.AliUrl.'",c.pic) AS music_pic,f.name college_name,f.coid college_id';
                $where='a.music_type=1 AND a.music_id='.$music_id.' AND a.create_time < "'.$time.'" AND a.view_auth=1 AND a.status=0';
                $query="SELECT $field FROM limi_video AS a LEFT JOIN limi_user AS b ON a.user_id = b.id LEFT JOIN limi_music AS c ON (a.music_id = c.id AND a.music_type=0)  LEFT JOIN limi_college AS f ON b.college_id=f.coid WHERE $where ORDER BY a.click_num DESC LIMIT $pageStart,$pageSize";
                $res=Db::query($query);
                $data['same_video']=$res;
            }

            #前端要求的格式
            if(empty($data['same_video'])){
                return apiSuccess('没有查询到记录');
            }
            #去掉返回的视频里的原视频，防止出现2次,只有一款原视频
            #去掉返回的视频里的原视频，防止出现2次,只有一款原视频
            foreach ($data['same_video'] as $key=>$val){
                if($val['id']==$return_data['original_video'][0]['id']){
                    unset($data['same_video'][$key]);
                }
            }
            $data['same_video']=array_values($data['same_video']);
            $return_data['video']=$this->formatVideo($data['same_video']);
            $return_data2['time']=strtotime($time);
            $return_data2['data']=$return_data;
            apiSuccess('拍同款列表',$return_data2);
        }


    }

    /**
     * Created by zyjun
     * Info:拍同款列表，获取原创视频，首发视频
     * 只有设置了所有人可见
     */
    public function getSameOriginalVideo($uid,$music_type,$music_id){
        if(!empty($uid)){
            $field=' a.id,a.status,a.user_id,a.view_auth,a.title,a.video_cover,a.video,a.width,a.height,a.view_num,a.music_type, CASE d.type WHEN 1 THEN 1 WHEN 0 THEN 0 ELSE 0 END as is_click,CASE e.is_cancel WHEN 1 THEN 0 WHEN 0 THEN 1 ELSE 0 END as is_attention,a.click_num,a.discuss_num,a.tags,a.music_id,b.id user_id,b.nickname user_nickname,CONCAT("'.ApiUrl.'",b.head_pic) AS user_head_pic,c.singer,c.name music_name,CONCAT("'.AliUrl.'",c.pic) AS music_pic,f.name college_name,f.coid college_id';
            $child_sql="SELECT black_user_id FROM limi_user_black WHERE user_id=$uid";
            $where='a.music_type='.$music_type.' AND a.music_id='.$music_id.' AND a.view_auth=1 AND a.status=0 AND a.user_id NOT IN ('.$child_sql.')';
            $query="SELECT $field FROM limi_video AS a LEFT JOIN limi_user AS b ON a.user_id = b.id LEFT JOIN limi_music AS c ON (a.music_id = c.id AND a.music_type=0) LEFT JOIN limi_video_click AS d ON (a.id = d.video_id AND d.user_id=$uid ) LEFT JOIN limi_user_relation AS e ON (e.attention_id=a.user_id AND e.user_id=".$uid." ) LEFT JOIN limi_college AS f ON b.college_id=f.coid WHERE $where ORDER BY id ASC LIMIT 1";
            $res=Db::query($query);
        }else{
            $field=' a.id,a.status,a.user_id,a.title,a.video_cover,a.view_auth,a.video,a.width,a.height,a.view_num,a.click_num,a.discuss_num,a.tags,a.music_id,a.music_type,b.id user_id,b.nickname user_nickname,CONCAT("'.ApiUrl.'",b.head_pic) AS user_head_pic,c.singer,c.name music_name,CONCAT("'.AliUrl.'",c.pic) AS music_pic,f.name college_name,f.coid college_id';
            $where='a.music_type='.$music_type.' AND a.music_id='.$music_id.' AND a.view_auth=1 AND a.status=0';
            $query="SELECT $field FROM limi_video AS a LEFT JOIN limi_user AS b ON a.user_id = b.id LEFT JOIN limi_music AS c ON (a.music_id = c.id AND a.music_type=0)  LEFT JOIN limi_college AS f ON b.college_id=f.coid WHERE $where ORDER BY id ASC LIMIT 1";
            $res=Db::query($query);
        }
        $res=$res[0];
        $view_auth=$res['view_auth'];
        $publish_uid=$res['user_id'];
        $status=$res['status'];
        if($status!=0){
            return NULL;
        }
        unset($res['status']);
        if($view_auth==1){
            unset($res['view_auth']);
            return $res;
        }
        if(empty($uid)){
            return NULL;
        }
        if($view_auth==2){
            #自己看自己
            unset($res['view_auth']);
            if($publish_uid==$uid){
                return $res;
            }
            $where=[];
            $where['user_id']=$uid;
            $where['attention_id']=$publish_uid;
            $where['is_cancel']=0;
            $res=Db::name('user_relation')->where($where)->find();
            #不是粉丝返回空，粉丝返回数据
            if(empty($res)){
                return NULL;
            }else{
                return $res;
            }

        }
        if($view_auth==3){
            unset($res['view_auth']);
            #自己看自己
            if($publish_uid==$uid){
                return $res;
            }else{
                return NULL;
            }
        }

    }

    /**
     * Created by zyjun
     * Info:格式化输出视频格式
     */
    public function formatVideo($data){
        if(empty($data)){
            return NULL;
        }
        foreach ($data as $key=>$val){
            $rdata[$key]['id']=$val['id'];
            $rdata[$key]['click_num']=$val['click_num'];
            $rdata[$key]['discuss_num']=$val['discuss_num'];
            if(isset($data['is_attention'])){ //只有登陆用户才会返回is_attention，is_click
                $rdata['is_attention']=$data['is_attention'];
            }
            if(isset($data['is_click'])){
                $rdata['is_click']=$data['is_click'];
            }
            $rdata[$key]['title']=$val['title'];

            $music=[];
            $music['music_id']=$val['music_id'];
            $music['music_type']=$val['music_type'];
            $music['name']=$val['music_name'];
            $music['pic']=$val['music_pic'];
            $music['singer']=$val['singer'];
            $rdata[$key]['music']=$music;

            $user=[];
            $user['user_id']=$val['user_id'];
            $user['head_pic']=$val['user_head_pic'];
            $user['nickname']=$val['user_nickname'];
            $rdata[$key]['user']=$user;

            $college=[];
            $college['id']=$val['college_id'];
            $college['name']=$val['college_name'];
            $rdata[$key]['user']['college']=$college;

            $video=[];
            $video['cover']=$val['video_cover'];
            $video['video']=$val['video'];
            $video['width']=$val['width'];
            $video['height']=$val['height'];
            $rdata[$key]['video']=$video;
        }
        return $rdata;
    }


}
