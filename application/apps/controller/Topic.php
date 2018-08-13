<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/2/2 0002
 * Time: 10:00
 */

namespace app\apps\controller;


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
        $pic=input('pic'); //
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
        //4 如果有图片上传限制图片上传数量
        if(!empty($pic)){
            if(strpos($pic,',')!==false){
                $length=count(explode(',',$pic));
                if($length>1){
                    return apiError('最多只能上传1张图片');
                }
            }
            $data['pic']=$pic;
        }
        if(empty($content)&&empty($pic)){
            return apiError('发布动态不能为空');
        }
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
            'create_time'=>date('Y-m-d H:i:s',time())
        ];
        $res=Db::name('topic_discuss')->insertGetId($data);
        if($res){
            $topic_discuss_id=$res; //回复id
            $a=Db::name('topic_action')->where('id',$topic_action_id)->setInc('discuss_num',1);
            if($a){
                //写入评论总表,发送评论消息
                $to_uid=Db::name('topic_action')->where('id',$topic_action_id)->value('user_id');
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
        //1. 判断参数是否为空
        if(!$topic_action_id){
            return apiError('动态id不能为空');
        }
        if($page==''||$page<1){
            apiError('请求页码有误');
            return;
        }
        //2.判断用户是否存在
        $id=input('get.id');
        //6.评论的动态信息
        $action=$this->oneAction($topic_action_id);
        //dump($action);
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
            ->field('user_id,topic_action_id,content,create_time')
            ->where('topic_action_id',$topic_action_id)
            ->order('create_time desc')
            ->page($page,'15')
            ->select();
        if(!$res){
            apiSuccess('',$dat);
            return;
        }
        //5 遍历每条评论查询出对应的user基本信息组合成新的数组响应
        $data=[];
        foreach ($res as & $v){
            $v['content']=$this->userTextDecode($v['content']);

            $v['create_time']=round((time()-strtotime($v['create_time']))/60); //分钟
            if($v['create_time']<1){
                $v['create_time']='刚刚';
            }
            if($v['create_time']>=1){
                $v['create_time']=$v['create_time'].'分钟前';
            }
            if($v['create_time']>59){
                //大于60分钟
                $v['create_time']=ceil($v['create_time']/60).'小时前'; //小时
                //大于24小时
                if($v['create_time']>23){
                    $v['create_time']=ceil($v['create_time']/24).'天前'; //天
                    //大于30天
                    if($v['create_time']>29){
                        $v['create_time']=ceil($v['create_time']/30).'月前'; //月
                        //大于12月
                        if($v['create_time']>11) {
                            $v['create_time'] ='n年前'; //月
                        }

                    }
                }
            }
            $userInfo=$this->userInfo($v['user_id']);
            $user=[
                'user_id'=>$userInfo['user_id'],
                'true_name'=>$userInfo['true_name'],
                'sex'=>$userInfo['sex'],
                'head_pic'=>$userInfo['head_pic'],
                'college'=>$userInfo['college'],
                'school'=>$userInfo['school']
            ];
            $data[]=array_merge($v,$user);
        }
        $dat['discuss']=$data;
        apiSuccess('',$dat);
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
        if($page==''||$page<1){
            apiError('请求页码有误');
            return;
        }
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
        ];

        if ($id) {
            // 拉黑名单
            $back_id= Db::name('user_black')
                ->where('user_id', $id)
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
        apiSuccess('',$data);
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
        if ($page == '' || $page < 1) {
            apiError('请求页码有误');
            return;
        }
        $where['identity'] = 1;
        $w=array();
        if ($id) {
            // 是否有不感兴趣的话题
            $unlike_id = Db::name('topic_unlike')
                ->where('user_id', $id)
                ->column('topic_id');
            $where['id'] = array('not in', $unlike_id);
            //是否有拉黑用户
            $black_user_id=Db::name('user_black')
                ->where('user_id',$id)
                ->column('black_user_id');
            // $where['user_id'] = array('not in', $black_user_id);
            $w['user_id']=array('not in', $black_user_id);
        }
        $topic_list = Db::name('topic')
                ->where($where)
                ->field('id,status,user_id,title,content')
                ->order('create_time desc')
                ->page($page)
                ->select();
        //$topic_list = array_merge($sys_topic, $topic_list);
        foreach ($topic_list as $k => $v) {
            //用户相关信息
            $user_info = Db::name('user')
                ->where('id', $v['user_id'])
                ->field('head_pic,true_name')
                ->find();
            $topic_list[$k]['head_pic'] = $this->addApiUrl($user_info['head_pic']);
            $topic_list[$k]['true_name'] = $user_info['true_name'];
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

        return apiSuccess('', $topic_list);


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
            $yes=Db::name('user_black')->where(['user_id'=>$id,'black_user_id'=>$user_id])->find();
            if($yes){
                return apiSuccess('拉黑成功拉黑成功，不再接收此人动态','');
            }
            $data=[
                'user_id'=>$id,
                'black_user_id'=>$user_id,
                'create_time'=>date('Y-m-d H:i:s',time())
            ];
            //2.将动态发布人的id和举报人的id保存到用户拉黑名单表
            $res=Db::name('user_black')->insert($data);
            if(!$res){
                return apiError('拉黑名单表添加错误');
            }
            return apiSuccess('拉黑成功，不再接收此人动态','');
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
            $res=\app\apps\model\TopicAction::get($topic_action_id)->delete(); //软删除
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
                ->field('ta.id topic_action_id,user.id user_id,user.true_name,user.sex,user.head_pic,col.name college,sch.name school,ta.content,ta.create_time,ta.discuss_num,ta.view_num,ta.click_num,ta.pic')
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
                ->field('ta.id topic_action_id,user.id user_id,user.true_name,user.sex,user.head_pic,col.name college,sch.name school,ta.content,ta.create_time,ta.discuss_num,ta.view_num,ta.click_num,ta.pic')
                ->order('ta.click_num desc,ta.create_time desc')
                ->page($page,20)
                ->where('delete_time',null)
                ->where('is_show',0)
                ->where($where)
                ->select();
        }else{
            return apiError('type不能为空');
        }
        if($res){
            foreach($res as & $v){
                if($v['sex']=='1'){
                    $v['sex']='男';
                }elseif($v['sex']=='0'){
                    $v['sex']='女';
                }
                //响应头像地址
                if($v['head_pic']){
                    $v['head_pic']=$this->addApiUrl($v['head_pic']);
                }
                //响应的发布时间
                $v['create_time']=round((time()-strtotime($v['create_time']))/60); //分钟
                if($v['create_time']<1){
                    $v['create_time']='刚刚';
                }
                if($v['create_time']>=1){
                    $v['create_time']=$v['create_time'].'分钟前';
                }
                if($v['create_time']>59){
                    //大于60分钟
                    $v['create_time']=ceil($v['create_time']/60).'小时前'; //小时
                    //大于24天
                    if($v['create_time']>23){
                        $v['create_time']=ceil($v['create_time']/24).'天前'; //天
                        //大于30天
                        if($v['create_time']>29){
                            $v['create_time']=ceil($v['create_time']/30).'月前'; //月
                            //大于12月
                            if($v['create_time']>11) {
                                $v['create_time'] ='n年前'; //月
                            }

                        }
                    }
                }
                //响应的浏览数量
                if($v['view_num']>1000){
                    $v['view_num']=round($v['view_num']/1000,1).'k';
                    if($v['view_num']>10000){
                        $v['view_num']=round($v['view_num']/10000,1).'w';
                    }
                }else{
                    $v['view_num']=(string)$v['view_num'];
                }
                //响应动态的文字和 图片

                if($v['pic']){
                    $v['pic']=$this->addApiUrl($v['pic']);
                }
            }
        }
        return $res;
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
            ->field('ta.id topic_action_id,user.id user_id,user.true_name,user.sex,user.head_pic,col.name college,sch.name school,ta.content,ta.create_time,ta.discuss_num,ta.view_num,ta.click_num,ta.pic')
            ->where('delete_time',null)
            ->where('ta.id',$id)
            ->find();

        if($res){
            if($res['sex']=='1'){
                $res['sex']='男';
            }elseif($res['sex']=='0'){
                        $res['sex']='女';
            }
            //响应头像地址
            if($res['head_pic']){
                $res['head_pic']=$this->addApiUrl($res['head_pic']);
            }
            //响应的发布时间
            $res['create_time']=round((time()-strtotime($res['create_time']))/60); //分钟
            if($res['create_time']<1){
                $res['create_time']='刚刚';
            }
            if($res['create_time']>=1){
                $res['create_time']=$res['create_time'].'分钟前';
            }
            if($res['create_time']>59){
                        //大于60分钟
                $res['create_time']=ceil($res['create_time']/60).'小时前'; //小时
                        //大于24天
                        if($res['create_time']>23){
                            $res['create_time']=ceil($res['create_time']/24).'天前'; //天
                            //大于30天
                            if($res['create_time']>29){
                                $res['create_time']=ceil($res['create_time']/30).'月前'; //月
                                //大于12月
                                if($res['create_time']>11) {
                                    $res['create_time'] ='n年前'; //月
                                }

                            }
                        }
                    }
            //响应的浏览数量
            if($res['view_num']>1000){
                $res['view_num']=round($res['view_num']/1000,1).'k';
                if($res['view_num']>10000){
                    $res['view_num']=round($res['view_num']/10000,1).'w';
                }
            }else{
                $res['view_num']=(string)$res['view_num'];
            }
            //响应动态的文字和 图片

            if($res['pic']){
                $res['pic']=$this->addApiUrl($res['pic']);
//                $pic=explode(',',$res['pic']);
//                foreach ($pic as & $value){
//                    $value=$this->addApiUrl($value);
//                }
//                $res['pic']=$pic;
            }
            return $res;
        }
    }
}