<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/2/2 0002
 * Time: 10:00
 */

namespace app\apps1_2\controller;


use app\apps1_2\model\TopicAction;
use app\apps1_2\model\TopicDiscuss;
use think\Db;

class Topic extends Common
{
    /**
     * 添加话题
     */
    public function addTopic(){
        # 接收参数
        $id=input('id'); //用户id
        $token=input('token'); //用户token
        $title=input('title'); //话题主题
        $content=input('content'); //话题简介
        //1 判断用户是否存在
        $res=$this->checkToken($id,$token);
        if($res['status']){
            return apiError($res['msg'],'',$res['code']);
        }
        //2 判断用户是否已经通过认证
        $res=$this->isAccess($id);
        if($res['identity_status']!=2){
            return apiError($res['msg'],$res['identity_status']);
        }
        //3判断标题不能为空
        if(empty(trim($title))){
            return apiError('标题不能为空');
        }
        if(strlen($title)>60){
            return apiError('请输入少于20字的标题');
        }
        //4 判断简介不能为空
        if(empty(trim($content))){
            return apiError('标题简介不能为空');
        }
        if(strlen($content)>600){
            return apiError('请输入少于200字的标题简介');
        }

        //5 整理数据
        $data=[
            'title'=>$this->userTextEncode($title),
            'content'=>$this->userTextEncode($content),
            'user_id'=>$id,
            'create_time'=>date('Y-m-d H:i:s',time())
        ];
        //6 添加到数据库
        $res=Db::name('topic')->insert($data);
        if($res){
            return apiSuccess('添加话题成功','');
        }else{
            return apiError('添加话题失败');
        }

    }

    /**
     * 添加话题动态
     */

    public function addTopicAction(){
        $is_verify=0; //审核标志
        # 接收参数
        $id=input('id'); //用户id
        $token=input('token'); //用户token
        $topic_id=input('topic_id');
        $pic=input('pic');
        if($pic){
                $picinfo=json_decode($pic,true);
                if(!$picinfo){
                    return apiError('json格式不对');
                }
                $pic=$picinfo['url'];
                $w=$picinfo['width'];
                $h=$picinfo['height'];
                $action_pic_size=$w.','.$h;
                $data['action_pic_size']=$action_pic_size;
                $data['pic']=$pic;
            //图片鉴黄
            $res=$this->isSexyImg($pic);
            if($res['status']){//包含色情或者性感图片
                $sex_status=$res['code'];
                if($sex_status==0){
                    $data['is_verify']=1; //色情图片审核中
                    $data['is_show']=1; //审核完毕后修改is_show=0;
                    $is_verify=1;
                }
                $data['sex_status']=$sex_status;
            }
        }

        $content=input('content'); //
        //1 判断用户是否存在
        $res=$this->checkToken($id,$token);
        if($res['status']){
            return apiError($res['msg'],'',$res['code']);
        }
        //2 判断用户是否已经通过认证
        $res=$this->isAccess($id);
        if($res['identity_status']!=2){
            return apiError($res['msg'],$res['identity_status']);
        }

        if(!empty(trim($content))){
            if(strlen($content)>600){
                return apiError('请输入少于200字的动态内容');
            }
            $data['content']=$this->userTextEncode($content);
        }
        if(empty($topic_id)){
            return apiError('话题id不能为空');
        }
        if(empty($content)&&empty($pic)){
            return apiError('发布动态不能为空');
        }

        //6 整理数据
        $data['topic_id']=$topic_id;
        $data['user_id']=$id;
        $data['create_time']=date('Y-m-d H:i:s',time());
        //7 添加数据
        //验证该话题是否已经通过
        $isno=Db::name('topic')->where('id',$topic_id)->find();
        if($isno['identity']!=1){
            return apiError('该话题未通过验证');
        }
        $res=Db::name('topic_action')->insert($data);
        if($res){
            if($is_verify==1){
                return apiSuccess('发布成功，等待审核通过后显示',100);
            }else{
                return apiSuccess('发布成功');
            }
        }
    }

    /**
     * 点赞动作
     */
    public function clickAction(){
        # 接收参数
        $id=input('id'); //用户id
        $token=input('token'); //用户token
        $topic_action_id=input('topic_action_id');
        //1 判断用户是否存在
        $res=$this->checkToken($id,$token);
        if($res['status']){
            return apiError($res['msg'],'',$res['code']);
        }
        //2 判断用户是否已经通过认证
        $res=$this->isAccess($id);
        if($res['identity_status']!=2){
            return apiError($res['msg'],$res['identity_status']);
        }
        //判断是否点过赞
        $res=Db::name('topic_click')
            ->where(['topic_action_id'=>$topic_action_id,'user_id'=>$id])
            ->find();
        if($res){
            //已经点过赞取消
            $delete=Db::name('topic_click')
                ->where(['topic_action_id'=>$topic_action_id,'user_id'=>$id])
                ->delete();
            if($delete){
                $a=Db::name('topic_action')->where('id',$topic_action_id)->setInc('click_num',-1);
                if($a){
                    $this->totalClickDel($id,1,$topic_action_id); //清理点赞总表id
                    return apiSuccess('取消点赞');
                }
            }
        }
        //没有点赞
        $data=[
            'topic_action_id'=>$topic_action_id,
            'user_id'=>$id,
            'create_time'=>date('Y-m-d H:i:s',time())
        ];
        $res=Db::name('topic_click')->insert($data);

        if($res){
            $a=Db::name('topic_action')->where('id',$topic_action_id)->setInc('click_num',1);
            if($a){
                //写入点赞总表,发送点赞消息
                $im=new Im();
                $to_uid=Db::name('topic_action')->where('id',$topic_action_id)->value('user_id');
                $to=Db::name('im_user')->where('uid',$to_uid)->value('accid');
                $from_uid=$id;
                $from=Db::name('im_user')->where('uid',$from_uid)->value('accid');

                $click_message['from']=$from_uid;
                $click_message['to']=$to_uid;
                $click_message['type']=1; //话题点赞
                $click_message['type_id']=$topic_action_id; //动态id
                $click_message['time']=date('Y-m-d H:i:s',time());
                $res=Db::name('message_click')->where('from',$from_uid)->where('type',1)->where('type_id',$topic_action_id)->find();//查询是否写如果
                if(empty($res)){
                    $msg_id=$this->totalClick($click_message);  //消息id必须写入一个总表来保证消息id的唯一性，客户端显示时候，直接通过总表反查，而不必联查多张点赞记录子表
                    if($from_uid!=$to_uid){ //自己不能给自己发IM消息
                        $type=0; //0：点赞 ；1：评论 ； 系统内部通知消息100
                        $attach['msg_id']=$msg_id; //点赞消息总表id
                        $res=$im->sendMessage($from,$to,$type,$attach);
                        if($res['status']){
                            $im->errorLog('话题-点赞消息发送失败，action_id='.$topic_action_id.'点赞用户id='.$from_uid,$res['msg']);
                        }
                    }
                }
                return apiSuccess('点赞成功');
            }
        }
    }

    /**
     * 评论动作
     */
    public function discussAction(){
        # 接收参数
        $id=input('id'); //用户id
        $token=input('token'); //用户token
        $topic_action_id=input('topic_action_id','');
        $content=input('content','');
        $parent_id=input('parent_id',0); //父级评论的id
        $parent_uid=input('parent_uid',0); //父级评论的uid
        //1 判断用户是否存在
        $res=$this->checkToken($id,$token);
        if($res['status']){
            return apiError($res['msg'],'',$res['code']);
        }
        //2 判断用户是否已经通过认证
        $res=$this->isAccess($id);
        if($res['identity_status']!=2){
            return apiError($res['msg'],$res['identity_status']);
        }
        if($topic_action_id==''){
            return apiError('动态id不能为空');
        }
        if(empty(trim($content))){
            return apiError('评论内容不能为空');
        }
        if(strlen($content)>600){
            return apiError('请输入少于200字的评论信息');
        }
        //整理数据
        $data=[
            'topic_action_id'=>$topic_action_id,
            'content'=>$this->userTextEncode($content),
            'user_id'=>$id,
            'parent_id'=>$parent_id,
            'parent_uid'=>$parent_uid,
            'create_time'=>date('Y-m-d H:i:s',time())
        ];
        $res=Db::name('topic_discuss')->insertGetId($data);
        if($res){
            //添加评论成功找到该评论
            $ids=Db::name('topic_discuss')->where('id',$res)->find();
            //dump($ids);die;
            //如果该评论是父级评论
            if($ids['parent_id']===0){
                // 所属组的id为该评论的id
                $d=Db::name('topic_discuss')->where('id',$res)->update(['group_id'=>$res]);
                if(!$d){
                    Db::name('topic_discuss')->where('id',$res)->update(['delete_time'=>date('Y-m-d H:i:s',time())]);
                    return apiError('');
                }
            }else{
                //如果该评论不是父级评论，该所属组id为父级评论的所属组id
                $group_id=Db::name('topic_discuss')->where('id',$ids['parent_id'])->value('group_id');
                $d=Db::name('topic_discuss')->where('id',$res)->update(['group_id'=>$group_id]);
                if(!$d){
                    Db::name('topic_discuss')->where('id',$res)->update(['delete_time'=>date('Y-m-d H:i:s',time())]);
                    return apiError('');
                }
            }
            $topic_discuss_id=$res; //回复id
            $a=Db::name('topic_action')->where('id',$topic_action_id)->setInc('discuss_num',1);
            if($a){
                //写入评论总表,发送评论消息
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
                    $to_uid=Db::name('topic_action')->where('id',$topic_action_id)->value('user_id');
                }
                $to=Db::name('im_user')->where('uid',$to_uid)->value('accid');
                $from_uid=$id;
                $from=Db::name('im_user')->where('uid',$from_uid)->value('accid');

                $im=new Im();
                $comment_message['from']=$from_uid;
                $comment_message['to']=$to_uid;
                $comment_message['type']=1; //话题
                $comment_message['type_id']=$topic_action_id; //动态id
                $comment_message['rid']=$topic_discuss_id; //回复id
                $comment_message['time']=date('Y-m-d H:i:s',time());
                $msg_id=$this->totalComment($comment_message);  //消息id必须写入一个总表来保证消息id的唯一性，客户端显示时候，直接通过总表反查，而不必联查多张点赞记录子表

                if($from_uid!=$to_uid){ //自己不能给自己发IM消息
                    $type=1; //0：点赞 ；1：评论 ； 100:系统内部通知消息
                    $attach['msg_id']=$msg_id; //评论消息总表id
                    $res=$im->sendMessage($from,$to,$type,$attach);
                    if($res['status']){
                        $im->errorLog('话题-评论消息发送失败，action_id='.$topic_action_id.'评论用户id='.$from_uid,$res['msg']);
                    }
                }
                return apiSuccess('评论成功');
            }
        }

    }

    /**
     * 查看评论列表
     */
    public function discussList(){
        $topic_action_id=input('get.topic_action_id');
        $page=input('get.page');
        $time=input('get.time');//第一次请求时间
        //1. 判断参数是否为空
        if(!$topic_action_id){
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
        //2.判断用户是否存在
        $id=input('get.id');
        //6.评论的动态信息
        $action=$this->oneAction($topic_action_id);
        //dump($action);die;
        if($action){
            $action['content']=$this->userTextDecode($action['content']);
            $r=Db::name('topic_click')->where(['user_id'=>$id,'topic_action_id'=>$action['topic_action_id']])->find();
            if($r){
                $action['is_click']=1;
            }else{
                $action['is_click']=0;
            }
            $dat['action']=$action;
        }
        $dat['discuss']='';
        //4. 查询评论内容列表
        $res=Db::name('topic_discuss')
            ->field('id,parent_id,parent_uid,group_id,user_id,topic_action_id,content,create_time,delete_time')
            ->where(['delete_time'=>null,'topic_action_id'=>$topic_action_id,'parent_id'=>0,'create_time'=>['<=',$time]])
            ->where('group_id','neq','null')
            ->order('create_time desc')
            ->page($page,20)
            ->select();
        if(!$res){
            apiSuccess(['timestamp'=>$timestamp],$dat);
            return;
        }
        foreach ($res as$k=>&$v){
            $v['content']=$this->userTextDecode($v['content']);
            $v['create_time']=$this->timeToHour($v['create_time']);
            $v=array_merge($v,$this->userInfo2($v['user_id']));
            $v['parent_name']=null;
            $v['child_num']=(Db::name('topic_discuss')->where('delete_time',null)->where('group_id',$v['group_id'])->count())-1;
            $child=Db::name('topic_discuss')
                ->field('id,parent_id,parent_uid,group_id,user_id,topic_action_id,content,create_time,delete_time')
                ->where(['delete_time'=>null,'group_id'=>$v['id'],'create_time'=>['<=',$time]])
                ->where('id','neq',$v['id'])
                ->order('create_time desc')
                ->limit(3)
                ->select();
            if($child){
                foreach ($child as &$value){
                    $value['content']=$this->userTextDecode($value['content']);
                    $value['create_time']=$this->timeToHour($value['create_time']);
                    $value=array_merge($value,$this->userInfo2($value['user_id']));
                    $value['parent_name']=Db::name('user')->where('id',$value['parent_uid'])->value('nickname');
                }
            }
            $v['child']=$child;
        }
        $dat['discuss']=$res;
        apiSuccess(['timestamp'=>$timestamp],$dat);
    }

    /**
     * 评论详情页
     */
    public function discussOneFather(){
        $discuss_id=input('get.discuss_father_id');
        $page=input('get.page');
        $time=input('get.time');
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
        if($time!=''){
            $time=date('Y-m-d H:i:s',$time);
            $where['create_time']=['<=',$time];
        }
        if($discuss_id==''){
            return apiError('discuss_id不能为空');
        }
        $discuss_father=Db::name('topic_discuss')
            ->where('id',$discuss_id)
            ->where('delete_time',null)
            ->field('id,parent_id,parent_uid,group_id,user_id,topic_action_id,content,create_time')
            ->find();
        $data=[];
        if($discuss_father){
            $where['parent_id']=['neq',0];
            $where['group_id']=$discuss_father['id'];
            $child=Db::name('topic_discuss')
                ->where($where)
                ->where('delete_time',null)
                ->field('id,parent_id,parent_uid,group_id,user_id,topic_action_id,content,create_time')
                ->order('create_time desc')
                ->page($page,20)
                ->select();
            $data2=[];
            if($child){
                foreach ($child as$k=>&$v) {
                    $v['content'] = $this->userTextDecode($v['content']);
                    $v['create_time']=$this->timeToHour($v['create_time']);
                    $data2[$k]=array_merge($v,$this->userInfo2($v['user_id']));
                    $data2[$k]['parent_name']=Db::name('user')->where('id',$v['parent_uid'])->value('nickname');
                }
            }
            $discuss_father['child']=$data2;
            $discuss_father['content']=$this->userTextDecode($discuss_father['content']);
            $discuss_father['create_time']=$this->timeToHour($discuss_father['create_time']);
            $data=array_merge($discuss_father,$this->userInfo2($discuss_father['user_id']));
        }
        return apiSuccess(['timestamp'=>$timestamp],$data);
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
        $res=$this->isAccess($id);
        if($res['identity_status']!=2){
            return apiError($res['msg'],$res['identity_status']);
        }
        if($discuss_id==''){
            return apiError('discuss_id不能为空');
        }
        //判断是否可以删除
        $action_id=Db::name('topic_discuss')->where('id',$discuss_id)->value('topic_action_id');
        $user_id=Db::name('topic_action')->where('id',$action_id)->value('user_id');
        if($user_id!=$id){
            //如果不是自己动态，判断是不是自己的评论
            $user_id2=Db::name('topic_discuss')->where('id',$discuss_id)->value('user_id');
            if($user_id2==$id){
                //如果是自己的评论 判断是不是父级评论
                $parent_id=Db::name('topic_discuss')->where('id',$discuss_id)->value('parent_id');
                if($parent_id===0){
                    //如果是父级评论下面子评论一并删除
                    $discuss=Db::name('topic_discuss')->where('group_id','=',$discuss_id)->update(['delete_time'=>date('Y-m-d H:i:s',time())]);
                    if($discuss){
                        return apiSuccess('删除成功');
                    }
                }
                //不是父级评论直接删除
                $discuss=TopicDiscuss::get($discuss_id)->delete();
                if($discuss){
                    return apiSuccess('删除成功');
                }
            }else{
                //如果不是自己的评论 判断是不是自己评论的子评论
                $group_id=Db::name('topic_discuss')->where('id',$discuss_id)->value('group_id');
                $user_id3=Db::name('topic_discuss')->where('id',$group_id)->value('user_id');
                if($user_id3==$id){
                    $discuss=TopicDiscuss::get($discuss_id)->delete();
                    if($discuss){
                        return apiSuccess('删除成功');
                    }
                }
                return apiError('无权删除');
            }
        }
        //如果是自己的评论 判断是不是父级评论
        $parent_id=Db::name('topic_discuss')->where('id',$discuss_id)->value('parent_id');
        if($parent_id===0){
            //如果是父级评论下面子评论一并删除
            $discuss=Db::name('topic_discuss')->where('group_id','=',$discuss_id)->update(['delete_time'=>date('Y-m-d H:i:s',time())]);
            if($discuss){
                return apiSuccess('删除成功');
            }
        }
        $discuss=TopicDiscuss::get($discuss_id)->delete();
        if($discuss){
            return apiSuccess('删除成功');
        }

    }
    /**
     * 显示某话题列表
     */
    public function oneTopicList(){
        $id=input('get.id',''); //用户id
        //$token=input('get.token',''); //用户token
        $topic_id=input('get.topic_id','');
        $type=input('get.type','new'); // 最新 /最热
        $page=input('get.page','');
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
        $time=date('Y-m-d H:i:s',$time);
        if($topic_id==''){
            return apiError('话题id不能为空');
        }
        $data['topic']=[];
        //查询该话题标题简介
        $topic=Db::name('topic')->where('id',$topic_id)->find();
        if($topic){

            $data['topic']['title']=$this->userTextDecode($topic['title']);
            $data['topic']['content']=$this->userTextDecode($topic['content']);
        }
        // 筛选条件
        $where=[
            'topic_id'=>$topic_id,
            'ta.create_time'=>['<=',$time]
        ];

        if ($id) {
            // 拉黑名单
            $back_id= Db::name('user_black')
                ->where(['user_id'=>$id,'is_cancel'=>0])
                ->column('black_user_id');
            $where['user_id'] = array('not in', $back_id);
        }

        $data['actionList']=$this->topicActionList($where,$page,$type);
        foreach ($data['actionList'] as $k=>& $v){
            $v['content']=$this->userTextDecode($v['content']);
            $r=Db::name('topic_click')
                ->where(['user_id'=>$id,'topic_action_id'=>$v['topic_action_id']])
                ->find();
            if($r){
                $v['is_click']=1;
            }else{
                $v['is_click']=0;
            }
        }
        apiSuccess(['timestamp'=>$timestamp],$data);
        //浏览量增加
        foreach ($data['actionList'] as & $v){
            Db::name('topic_action')
                ->where('id',$v['topic_action_id'])
                ->setInc('view_num',rand(0,3));
        }
        return;
    }

    /**
     * 显示所有话题列表
     */
    public function allTopicList(){
        $id = input('get.id', ''); //用户id
        $page = input('get.page', '');
        $time=input('get.time');//第一次请求时间
        if ($page == '' || $page < 1) {
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
        $where['identity'] = 1;
        $where['create_time'] = ['<=',$time];
        $w=array();
        if ($id) {
            // 是否有不感兴趣的话题
            $unlike_id = Db::name('topic_unlike')
                ->where('user_id', $id)
                ->column('topic_id');
            $where['id'] = array('not in', $unlike_id);
            //是否有拉黑用户
            $black_user_id=Db::name('user_black')
                ->where(['user_id'=>$id,'is_cancel'=>0])
                ->column('black_user_id');
            // $where['user_id'] = array('not in', $black_user_id);
            $w['user_id']=array('not in', $black_user_id);
        }
        $topic_list = Db::name('topic')
                ->where($where)
                ->field('id,status,user_id,title,content')
                ->order('is_top desc,create_time desc')
                ->page($page,20)
                ->select();
        //$topic_list = array_merge($sys_topic, $topic_list);
        foreach ($topic_list as $k => $v) {
            //用户相关信息
            $user_info = Db::name('user')
                ->where('id', $v['user_id'])
                ->field('head_pic,nickname')
                ->find();
            $topic_list[$k]['head_pic'] = $this->addApiUrl($user_info['head_pic']);
            $topic_list[$k]['nickname'] = $user_info['nickname'];
            $topic_list[$k]['title'] = $this->userTextDecode($topic_list[$k]['title']);
            $topic_list[$k]['content'] = $this->userTextDecode($topic_list[$k]['content']);
            //查找最新该话题动态
            $ac = Db::name('topic_action')
                ->where($w)
                ->where('topic_id', $topic_list[$k]['id'])
                ->where('is_show',0)
                ->where('delete_time',null)
                ->field('pic')
                ->order('create_time desc')
                ->select();
            $topic_list[$k]['pics']=[];
            $i=0;
            foreach ($ac as $ke=>&$va){
                if(!empty($va['pic'])){
                    $topic_list[$k]['pics'][$i]=$this->addApiUrl($va['pic']);
                    $i++;
                }
                if($i>2){
                    break;
                }
            }
            //$topic_list[$k]['pics'] =$pics;
            $topic_list[$k]['pics_num'] = count($topic_list[$k]['pics']);
        }
        return apiSuccess(['timestamp'=>$timestamp], $topic_list);
    }

    /**
     * 不感兴趣动作
     */
    public function unlikeTopic(){
        $id=input('id'); //用户id
        $token=input('token'); //用户token
        $topic_id=input('topic_id');
        //1 判断用户是否存在
        $res=$this->checkToken($id,$token);
        if($res['status']){
            return apiError($res['msg'],'',$res['code']);
        }
        //2 判断用户是否已经通过认证
        $res=$this->isAccess($id);
        if($res['identity_status']!=2){
            return apiError($res['msg'],$res['identity_status']);
        }
        if ($topic_id == '' || $topic_id < 1) {
            return apiError('topic_id有误');
        }
        //检查是否已经写入
        $is=Db::name('topic_unlike')->where(['user_id'=>$id, 'topic_id'=>$topic_id])->find();
        if($is){
            return apiSuccess();
        }
        //写入数据
        $data=[
            'user_id'=>$id,
            'topic_id'=>$topic_id,
            'create_time'=>date('Y-m-d H:i:s',time())
        ];
        $res=Db::name('topic_unlike')->insert($data);
        if($res){
            return apiSuccess();
        }
    }

    /**
     * 举报，拉黑，删除
     * report black sendmsg delete
     */
    public function multiFunction(){
        $type=input('type'); //操作的类型report black delete
        $topic_action_id=input('topic_action_id');
        $user_id=input('user_id');
        //1.判断用户是否存在
        $id=input('id');
        $token=input('token');
        $res=$this->checkToken($id,$token);
        if($res['status']){
            return apiError($res['msg'],'',$res['code']);
        }
        //2.判断用户是否已经通过认证
        $res=$this->isAccess($id);
        if($res['identity_status']!=2){
            return apiError($res['msg'],$res['identity_status']);
        }
        //3.判断处理类型不能为空
        if($type==''){
            return apiError('处理类型不能为空');
        }
        if($topic_action_id==''){
            return apiError('该动态的id不能为空');
        }
        if($user_id==''){
            return apiError('该动态的用户id不能为空');
        }
        $a=Db::name('topic_action')->where(['id'=>$topic_action_id,'user_id'=>$user_id])->find();
        if(!$a){
            return apiError('动态不是该用户发表');
        }
        //4.判断处理类型 开始对应操作
        if($type=='report'){
            //举报
            if($user_id==$id){
                return apiError('不能将自己举报');
            }
                //从动态列表传递
                //1 是否已经举报
                $yes=Db::name('report')->where(['report_user_id'=>$id,'action_user_id'=>$user_id,'action_id'=>$topic_action_id])->find();
                if($yes){
                    return apiSuccess('举报成功','');
                }
                //将动态的id，发表动态的用户和举报人保存数据库
                $data=[
                    'action_id'=>$topic_action_id,
                    'action_user_id'=>$user_id,
                    'report_user_id'=>$id,
                    'type'=>3,
                    'create_time'=>date('Y-m-d H:i:s',time())
                ];
                $res=Db::name('report')
                    ->insert($data);
                if(!$res){
                    return apiError('举报信息保存错误');
                }
                return apiSuccess('举报成功，正在处理','');

        }elseif ($type=='black'){
            //拉黑不再接收此人动态
            //是否已经拉黑
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
            //$user_id=Db::name('topic_action')->where('id',$topic_action_id)->value('user_id');
            if($user_id!=$id){
                return apiError('没有删除权利');
            }
            //2.判断该动态是否已经被删除；
            $action=Db::name('topic_action')->where('id',$topic_action_id)->value('delete_time');
            if($action){
                return apiError('该动态已经被删除');
            }
            $res=TopicAction::get($topic_action_id)->delete(); //软删除
            if(!$res){
                return apiError('删除动态错误');
            }
            return apiSuccess('删除成功','');
        }else{
            return apiError('不在处理范围之内');
        }


    }

    /***************************************************方法***************************************/
    /**
     * 某一话题的动态列表
     * @param $topic_id
     */
    public function topicActionList($where,$page,$type){
        //$res=[];
        if($type=='new'){
            $res=Db::name('topic_action')
                ->alias('ta')
                ->join('user','ta.user_id=user.id','LEFT')
                ->join('school sch','sch.scid=user.school_id','LEFT')
                ->join('college col','col.coid=user.college_id','LEFT')
                ->field('ta.id topic_action_id,user.id user_id,user.nickname,true_name,user.sex,user.head_pic,col.name college,sch.name school,ta.content,ta.create_time,ta.discuss_num,ta.view_num,ta.click_num,ta.pic,ta.action_pic_size')
                ->order('ta.create_time desc')
                ->page($page,20)
                ->where('delete_time',null)
                ->where('is_show',0)
                ->where($where)
                ->select();
        }elseif ($type=='hot'){
            $res=Db::name('topic_action')
                ->alias('ta')
                ->join('user','ta.user_id=user.id','LEFT')
                ->join('school sch','sch.scid=user.school_id','LEFT')
                ->join('college col','col.coid=user.college_id','LEFT')
                ->field('ta.id topic_action_id,user.id user_id,user.nickname,true_name,user.sex,user.head_pic,col.name college,sch.name school,ta.content,ta.create_time,ta.discuss_num,ta.view_num,ta.click_num,ta.pic,action_pic_size')
                ->order('ta.click_num desc,ta.create_time desc')
                ->page($page,20)
                ->where('delete_time',null)
                ->where('is_show',0)
                ->where($where)
                ->select();
        }else{
            return apiError('type不能为空');
        }
        $date=array();
        if($res){
            foreach($res as$k=> & $v){

                $date[$k]['topic_action_id']=$v['topic_action_id'];
                $date[$k]['user_id']=$v['user_id'];
                $date[$k]['nickname']=$v['nickname'];
                $date[$k]['college']=$v['college'];
                $date[$k]['school']=$v['school'];
                $date[$k]['content']=$v['content'];
                $date[$k]['discuss_num']=$v['discuss_num'];
                $date[$k]['click_num']=$v['click_num'];
                if($v['sex']=='1'){
                    $date[$k]['sex']=$v['sex']='男';
                }elseif($v['sex']=='0'){
                    $date[$k]['sex']=$v['sex']='女';
                }
                //响应头像地址
                if($v['head_pic']){
                    $date[$k]['head_pic']=$v['head_pic']=$this->addApiUrl($v['head_pic']);
                }
                //响应的发布时间
                $date[$k]['create_time']=$this->timeToHour($v['create_time']);
                //响应评论数量
                $count=Db::name('topic_discuss')
                    ->field('count(*) discuss_num')
                    ->where(['topic_action_id'=>$v['topic_action_id'],'group_id'=>['neq','null'],'delete_time'=>null])
                    ->select();
                if(!$count){
                    return apiError('响应评论数量有误');
                }
                $date[$k]['discuss_num']=$count[0]['discuss_num'];
                //响应的浏览数量
                if($v['view_num']>1000){
                    $date[$k]['view_num']=$v['view_num']=round($v['view_num']/1000,1).'k';
                    if($v['view_num']>10000){
                        $date[$k]['view_num']=$v['view_num']=round($v['view_num']/10000,1).'w';
                    }
                }else{
                    $date[$k]['view_num']=$v['view_num']=(string)$v['view_num'];
                }
                //响应动态的文字和 图片

                if($v['pic']){
                    $date[$k]['pic']['url']=$v['pic']=$this->addApiUrl($v['pic']);
                    if($v['action_pic_size']){
                        $d=explode(',',$v['action_pic_size']);
                        $date[$k]['pic']['w']=(int)$d[0];
                        $date[$k]['pic']['h']=(int)$d[1];

                    }else{
                        $date[$k]['pic']['w']=400;
                        $date[$k]['pic']['h']=300;
                    }
                }
            }
        }
        return $date;
    }


    /**
     * 评论显示的某一动态
     * @param $id
     */
    public function oneAction($id){
        $res=Db::name('topic_action')
            ->alias('ta')
            ->join('user','ta.user_id=user.id','LEFT')
            ->join('school sch','sch.scid=user.school_id','LEFT')
            ->join('college col','col.coid=user.college_id','LEFT')
            ->field('ta.id topic_action_id,user.id user_id,user.nickname,true_name,user.sex,user.head_pic,col.name college,sch.name school,ta.content,ta.create_time,ta.discuss_num,ta.view_num,ta.click_num,ta.pic,action_pic_size')
            ->where('delete_time',null)
            ->where('ta.id',$id)
            ->find();
        $data=array();
        if($res){
            $data['topic_action_id']=$res['topic_action_id'];
            $data['user_id']=$res['user_id'];
            $data['nickname']=$res['nickname'];
            $data['college']=$res['college'];
            $data['school']=$res['school'];
            $data['content']=$res['content'];
            $data['discuss_num']=$res['discuss_num'];
            $data['click_num']=$res['click_num'];
            if($res['sex']=='1'){
                $data['sex']=$res['sex']='男';
            }elseif($res['sex']=='0'){
                $data['sex']=$res['sex']='女';
            }
            //响应头像地址
            if($res['head_pic']){
                $data['head_pic']=$res['head_pic']=$this->addApiUrl($res['head_pic']);
            }
            //响应的发布时间
            $data['create_time']=$res['create_time']=$this->timeToHour($res['create_time']);
            //响应评论数量
            $count=Db::name('topic_discuss')
                ->field('count(*) discuss_num')
                ->where(['topic_action_id'=>$res['topic_action_id'],'group_id'=>['neq','null'],'delete_time'=>null])
                ->select();
            if(!$count){
                return apiError('响应评论数量有误');
            }
            $data['discuss_num']=$res['discuss_num']=$count[0]['discuss_num'];
            //响应浏览数量
            if($res['view_num']>1000){
                $data['view_num']=$res['view_num']=round($res['view_num']/1000,1).'k';
                if($res['view_num']>10000){
                    $data['view_num']=$res['view_num']=round($res['view_num']/10000,1).'w';
                }
            }else{
                $data['view_num']=$res['view_num']=(string)$res['view_num'];
            }

            //响应动态的文字和 图片
            if($res['pic']){
                $data['pic']['url']=$res['pic']=$this->addApiUrl($res['pic']);
                if($res['action_pic_size']){
                    $d=explode(',',$res['action_pic_size']);
                    $data['pic']['w']=(int)$d[0];
                    $data['pic']['h']=(int)$d[1];
                }else{
                    $data['pic']['w']=400;
                    $data['pic']['h']=300;
                }
            }
            return $data;
        }
    }
}