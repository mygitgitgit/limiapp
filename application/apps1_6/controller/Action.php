<?php
namespace app\apps1_6\controller;

use app\apps1_6\model\Discuss;
use think\Db;
class Action extends Common
{
    /**
     * Created by zyjun
     * Info:初始化
     */
    public function _initialize(){

    }

    /**
     * 动态和发现的首页列表以及筛选之后的列 表
     */
    public function indexList(){
        $type=input('get.type','action');
        $page=input('get.page');
        $time=input('get.time');//第一次请求时间
        $college_id=input('get.college_id');//筛选时候传递
        $school_id=input('get.school_id');//筛选时候传递
        $grade_id=input('get.grade_id');//筛选时候传递
        $sex=input('get.sex');//筛选时候传递
        $skill_id=input('get.skill_id');//筛选时候传递
        $id=input('get.id');
        $token=input('get.token');

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


        $data=[];//筛选条件拼接
        //1 学校
        if($college_id){
            $data['u.college_id']=$college_id;
        }
        //2 专业
        if($school_id){
            $data['u.school_id']=$school_id;
        }
        //3 年级
        if($grade_id){
            $data['u.grade_id']=$grade_id;
        }
        //4 技能
        if($skill_id){
            $data['s.id']=$skill_id;
        }
        //5 性别
        if($sex=='1'){
            $data['u.sex']=$sex;
        }elseif($sex=='0'){
            $data['u.sex']=$sex;
        }
        //
        if($time!=''){
            $time=date('Y-m-d H:i:s',$time);
            $data['a.create_time']=['<=',$time];
        }
        if($id!=null && $token!=null){
            //判断该用户是否有黑名单列表
            $res=Db::name('user_black')->where(['user_id'=>$id,'is_cancel'=>0])->find();
            if(!$res){
                $data['delete_time']=null; //没有被删除
                $data=$this->totalInfoList($type,$page,$data);
            }else{
                //有黑名单
                $res=Db::name('user_black')
                    ->where(['user_id'=>$id,'is_cancel'=>0])
                    ->column('black_user_id');
                $data['a.user_id']=array('not in',$res); //筛选条件 后期添加
                $data['delete_time']=null; //没有被删除
                $data=$this->totalInfoList($type,$page,$data);
            }
        }else{
            $data['delete_time']=null; //没有被删除
            $data=$this->totalInfoList($type,$page,$data);
        }
        $i=0;
        foreach ($data as &$v){
            //dump($v['action_pic']);
            if($v['action_pic']==''){
                $v['action_pic']=[];
            }
            $data[$i]['action_pic_num']=count($v['action_pic']);
            $r=Db::name('click')->where(['user_id'=>$id,'action_id'=>$data[$i]['action_id']])->find();
            if($r){
                $data[$i]['is_click']=1;
            }else{
                $data[$i]['is_click']=0;
            }
            //判断是否已经抢到红包
            //判断是否已经领取过红包
            if($v['red_type']!='null'){
                if($id){
                    $v['red_type']=$this->getRedType($id,$v['action_id'],$v['red_type'],$v['is_over']);
                }
            }
            $data[$i]['content']=$this->userTextDecode($data[$i]['content']);
            $i++;
        }
        return apiSuccess(['timestamp'=>$timestamp],$data);
    }
    /**
     * 点击评论显示评论列表
     */
    public function discussList(){
        $action_id=input('get.action_id');
        $page=input('get.page');
        $time=input('get.time');//第一次请求时间
        //1. 判断参数是否为空
        if(!$action_id){
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
        $token=input('get.token');
        //6.评论的动态信息
        $action=$this->oneInfo($action_id);
        if($action){
            if($action['action_pic']==''){
                $action['action_pic']=[];
            }
            $action['content']=$this->userTextDecode($action['content']);
            $action['action_pic_num']=count($action['action_pic']);
            $r=Db::name('click')->where(['user_id'=>$id,'action_id'=>$action['action_id']])->find();
            if($r){
                $action['is_click']=1;
            }else{
                $action['is_click']=0;
            }

            //如果有红包
            if($action['red_type']!='null'){
                if($id){
                    $action['red_type']=$this->getRedType($id,$action['action_id'],$action['red_type'],$action['is_over']);
                }
            }
            $dat['action']=$action;
        }
        $dat['discuss']='';
        //4. 查询评论内容列表
        $res=Db::name('discuss')
            ->field('id,parent_id,parent_uid,group_id,user_id,action_id,content,create_time,delete_time')
            ->where(['delete_time'=>null,'action_id'=>$action_id,'parent_id'=>0,'create_time'=>['<=',$time]])
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
            $v['child_num']=(Db::name('discuss')->where('delete_time',null)->where('group_id',$v['group_id'])->count())-1;
            $child=Db::name('discuss')
                ->field('id,parent_id,parent_uid,group_id,user_id,action_id,content,create_time,delete_time')
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
        //父级评论
        $discuss_father=Db::name('discuss')
            ->where('id',$discuss_id)
            ->where('delete_time',null)
            ->field('id,parent_id,parent_uid,group_id,user_id,action_id,content,create_time')
            ->find();
        $data=[];
        if($discuss_father){
            $where['parent_id']=['neq',0];
            $where['group_id']=$discuss_father['id'];
            //子级评论
            $child=Db::name('discuss')
                ->where($where)
                ->where('delete_time',null)
                ->field('id,parent_id,parent_uid,group_id,user_id,action_id,content,create_time')
                ->order('create_time desc')
                ->page($page,20)
                ->select();
            $data2=[];
            if($child){
                foreach ($child as$k=>&$v) {
                    $v['content'] = $this->userTextDecode($v['content']);
                    $v['create_time']=$this->timeToHour($v['create_time']);
                    $data2[$k]=array_merge($v,$this->userInfo($v['user_id']));
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
        $action_id=Db::name('discuss')->where('id',$discuss_id)->value('action_id');
        $user_id=Db::name('action')->where('id',$action_id)->value('user_id');
        //是否在自己动态下
        if($user_id!=$id){
            //如果不是自己动态，判断是不是自己的评论
            $user_id2=Db::name('discuss')->where('id',$discuss_id)->value('user_id');
            if($user_id2==$id){
                //如果是自己的评论 判断是不是父级评论
                $parent_id=Db::name('discuss')->where('id',$discuss_id)->value('parent_id');
                if($parent_id===0){
                    //如果是父级评论下面子评论一并删除
                    $discuss=Db::name('discuss')->where('group_id','=',$discuss_id)->update(['delete_time'=>date('Y-m-d H:i:s',time())]);
                    if($discuss){
                        return apiSuccess('删除成功');
                    }
                }
                //不是父级评论直接删除
                $discuss=Discuss::get($discuss_id)->delete();
                if($discuss){
                    return apiSuccess('删除成功');
                }
            }else{
                //如果不是自己的评论 判断是不是自己评论的子评论
                $group_id=Db::name('discuss')->where('id',$discuss_id)->value('group_id');
                $user_id3=Db::name('discuss')->where('id',$group_id)->value('user_id');
                if($user_id3==$id){
                    $discuss=Discuss::get($discuss_id)->delete();
                    if($discuss){
                        return apiSuccess('删除成功');
                    }
                }
                return apiError('无权删除');
            }
        }
        $parent_id=Db::name('discuss')->where('id',$discuss_id)->value('parent_id');
        if($parent_id===0){
            //如果是父级评论下面子评论一并删除
            $discuss=Db::name('discuss')->where('group_id','=',$discuss_id)->update(['delete_time'=>date('Y-m-d H:i:s',time())]);
            if($discuss){
                return apiSuccess('删除成功');
            }
        }
        $discuss=Discuss::get($discuss_id)->delete();
        if($discuss){
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
        $action_id=input('action_id');
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
        //3 对评论信息判断
        if(empty(trim($content))){
            return apiError('请输入评论信息');
        }
        if(strlen($content)>600){
            return apiError('请输入少于200字的评论信息');
        }
        if($action_id==''){
            return apiError('action_id不能为空');
        }
        //内容的表情处理
        $content=$this->userTextEncode($content);
        $data=[
            'user_id'=>$id,
            'action_id'=>$action_id,
            'parent_id'=>$parent_id,
            'parent_uid'=>$parent_uid,
            'content'=>$content,
            'create_time'=>date('Y-m-d H:i:s',time())
        ];
        //4 添加到评论 表
        $res=Db::name('discuss')->insertGetId($data);
        if(!$res){
            return apiError('评论添加失败');
        }else{
            //添加评论成功找到该评论
            $ids=Db::name('discuss')->where('id',$res)->find();
            //dump($ids);die;
            //如果该评论是父级评论
            if($ids['parent_id']===0){
                // 所属组的id为该评论的id
                $d=Db::name('discuss')->where('id',$res)->update(['group_id'=>$res]);
                if(!$d){
                    Db::name('discuss')->where('id',$res)->update(['delete_time'=>date('Y-m-d H:i:s',time())]);
                    return apiError('');
                }
            }else{
                //如果该评论不是父级评论，该所属组id为父级评论的所属组id
                $group_id=Db::name('discuss')->where('id',$ids['parent_id'])->value('group_id');
                $d=Db::name('discuss')->where('id',$res)->update(['group_id'=>$group_id]);
                if(!$d){
                    Db::name('discuss')->where('id',$res)->update(['delete_time'=>date('Y-m-d H:i:s',time())]);
                    return apiError('');
                }
            }
            $discuss_id=$res; //记录回复内容id
            $res=Db::name('action')->where('id',$action_id)->setInc('discuss_num',1);
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
                $to_uid=Db::name('action')->where('id',$action_id)->value('user_id');
            }
            $to=Db::name('im_user')->where('uid',$to_uid)->value('accid');
            $im=new Im();
            $comment_message['from']=$from_uid;
            $comment_message['to']=$to_uid;
            $comment_message['type']=0; //需求
            $comment_message['type_id']=$action_id; //动态id
            $comment_message['rid']=$discuss_id; //记录回复内容id
            $comment_message['time']=date('Y-m-d H:i:s',time());
            $msg_id=$this->totalComment($comment_message);  //消息id必须写入一个总表来保证消息id的唯一性，客户端显示时候，直接通过总表反查，而不必联查多张点赞记录子表

            if($from_uid!=$to_uid){ //自己不能给自己发IM消息
                $type=1; //0：点赞 ；1：评论 ； 100:系统内部通知消息
                $attach['msg_id']=$msg_id; //评论消息总表id
                $res=$im->sendMessage($from,$to,$type,$attach);
                if($res['status']){
                    $im->errorLog('动态-话题消息发送失败，action_id='.$action_id.'评论用户id='.$from_uid,$res['msg']);
                }
            }
            return apiSuccess('评论添加/累加成功');
        }
    }
    /**
     * 点赞动作
     */
    public function clickAction(){
        $action_id=input('action_id');
        if(!$action_id){
            return apiError('动态id不能为空');
        }
        //1 判断用户是否存在
        $id=input('id');
        $token=input('token');
        $res=$this->checkToken($id,$token);
        if($res['status']){
            return apiError($res['msg'],'',$res['code']);
        }
        //2 判断用户是否已经通过认证
        $res=$this->isAccess($id);
        if($res['identity_status']!=2){
            return apiError($res['msg'],$res['identity_status']);
        }
        //3 判断该用户是否对此评论点过赞
        $res=Db::name('click')->where(['user_id'=>$id,'action_id'=>$action_id])->find();
        if($res){
            //取消点赞
            $d=Db::name('click')->where(['user_id'=>$id,'action_id'=>$action_id])->delete();
            Db::name('action')->where('id',$action_id)->setInc('click_num',-1);
//            $this->totalClickDel($id,0,$action_id); //清理点赞总表id
            if($d){
                return apiSuccess('取消点赞');
            }
        }
        $data=[
            'user_id'=>$id,
            'action_id'=>$action_id,
            'create_time'=>date('Y-m-d H:i:s',time())
        ];
        //4. 记录点赞的action_id和用户id
        $res=Db::name('click')->insert($data);
        if(!$res){
            return apiError('添加点赞表格失败');
        }
        //修改action表 点赞数量加1
        $res=Db::name('action')->where('id',$action_id)->setInc('click_num',1);
        if(!$res){
            return apiError('点赞累加失败');
        }
        //写入点赞总表,发送点赞消息
        $im=new Im();
        $from_uid=$id;
        $from=Db::name('im_user')->where('uid',$from_uid)->value('accid');
        $to_uid=Db::name('action')->where('id',$action_id)->value('user_id');
        $to=Db::name('im_user')->where('uid',$to_uid)->value('accid');

        $click_message['from']=$from_uid;
        $click_message['to']=$to_uid;
        $click_message['type']=0; //动态点赞
        $click_message['type_id']=$action_id; //动态id
        $click_message['time']=date('Y-m-d H:i:s',time());
        $res=Db::name('message_click')->where('from',$from_uid)->where('type',0)->where('type_id',$action_id)->find();//查询是否写如果
        if(empty($res)) { //不能反复点赞，反复发送消息,同一个人对同一个
            $msg_id=$this->totalClick($click_message);  //消息id必须写入一个总表来保证消息id的唯一性，客户端显示时候，直接通过总表反查，而不必联查多张点赞记录子表
            if($from_uid!=$to_uid) { //自己不能给自己发IM消息
                $type=0; //系统应用通知消息0：点赞 ；系统应用通知消息  1：评论 ； 系统内部通知消息100
                $attach['msg_id']=$msg_id; //点赞消息总表id
                $res=$im->sendMessage($from,$to,$type,$attach);
                if($res['status']){
                    $im->errorLog('动态需求-点赞消息发送失败，action_id='.$action_id.'点赞用户id='.$from_uid,$res['msg']);
                }
            }
        }
        apiSuccess('点赞累加/添加成功','');
    }

    /**
     * 个人详情
     * id token 用户
     * user_id 查看别人动态时候传递
     * type 查看skill还是action
     */
    public function myActionList(){
        $user_id=input('get.user_id');
        $id=input('get.id');
        $type=input('get.type','action');
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
                $data['nickname']=$user_info['nickname'];
                $data['head_pic']=$user_info['head_pic'];
                $data['back_pic']=$user_info['back_pic'];
                $data['sex']=$user_info['sex'];
                $data['signature']=$this->userTextDecode($user_info['signature']);
                $data['is_access']=$is_access['identity_status'];
                $data['college']=$user_info['college'];
                $data['school']=$user_info['school'];
                $data['grade']=$user_info['grade'];
                $data['true_name']=null; //名字保密
                $data['attention_num']=$my_num['attention_num']; //粉丝数量
                $data['fans_num']=$my_num['fans_num']; //粉丝数量
                $data['is_attention']=0; //未关注
            }
            $res1=Db::name('user_relation')->where(['user_id'=>$id,'attention_id'=>$user_id,'is_cancel'=>0])->find();
            $res2=Db::name('user_relation')->where(['user_id'=>$user_id,'attention_id'=>$id,'is_cancel'=>0])->find();
            if($res1){
                $data['is_attention']=1; //互关注
            }
            if($res1&&$res2){
                $data['true_name']=$user_info['true_name']; //显示名字
                $data['is_attention']=2; //互关注
            }
            if($user_id==$id){
                $data['true_name']=$user_info['true_name']; //显示名字
                $data['is_attention']=3; //自己
            }
            //查看别人的个人信息
            $w=[
                'u.id'=>$user_id,
                'delete_time'=>null,
                'a.create_time'=>['<=',$time]
            ];
            $d=$this->myInfoList($type,$page,$w);
            foreach ($d as $k=>&$v){
                $v['content']=$this->userTextDecode($v['content']);
                $v['action_pic_num']=count($v['action_pic']);
                $r=Db::name('click')->where(['user_id'=>$id,'action_id'=>$v['action_id']])->find();
                if($r){
                    $v['is_click']=1;
                }else{
                    $v['is_click']=0;
                }
                //判断是否已经领取过红包
                if($v['red_type']!='null'){
                    if($id){
                        $v['red_type']=$this->getRedType($id,$v['action_id'],$v['red_type'],$v['is_over']);
                    }
                }
            }

            $d2['action_list']=$d;
            $d2['user']=$data;
            return apiSuccess(['timestamp'=>$timestamp],$d2);
        }else{
            return apiError('user_id不能为空');
        }
    }
    /**
     * 我的动态
     */
    public function myAction(){
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
        $time=date('Y-m-d H:i:s',$time);
        //判断用户是否存在
        $id=input('get.id');
        $token=input('get.token');
        $res=$this->checkToken($id,$token);
        if($res['status']){
            return apiError($res['msg'],'',$res['code']);
        }
        //2 判断用户是否已经通过认证
        $res=$this->isAccess($id);
        if($res['identity_status']!=2){
            return apiError($res['msg'],$res['identity_status']);
        }
        $res=Db::name('action')
            ->alias('a')
            ->join('user u','a.user_id=u.id','LEFT')
            ->join('skill s','a.skill_id=s.id','LEFT')
            ->join('redpacket red','a.id=red.did','LEFT')
            ->field('u.id user_id,u.true_name,u.nickname,u.head_pic,a.id action_id,red.red_token,red.type red_type,red.is_over,a.content,a.create_time,a.discuss_num,a.view_num,a.click_num,s.skill,a.action_pic,a.action_video')
            ->order('a.create_time desc')
            ->page($page,'10')
            ->where(['a.create_time'=>['<=',$time],'u.id'=>$id,'delete_time'=>null])
            ->where('is_show',0)
            ->select();
        if($res){
            foreach ($res as & $v){
                //响应红包类型
                if($v['red_type']===null){
                    $v['red_type']='null';
                }elseif($v['red_type']===0){
                    $v['red_type']='0';
                    //$v['red_type']='美女专属';
                }elseif($v['red_type']==1){
                    $v['red_type']='1';
                    // $v['red_type']='帅哥专属';
                }elseif($v['red_type']==2){
                    $v['red_type']='2';
                    //$v['red_type']='任何人可以领取';
                }
                //响应头像地址
                if($v['head_pic']){
                    $v['head_pic']=$this->addApiUrl($v['head_pic']);
                }
                if($v['action_video']){
                    $v['action_video']=$this->addApiUrl($v['action_video']);
                }
                //响应的发布时间
                $v['create_time']=$this->timeToHour($v['create_time']);
                //响应的浏览数量
                if($v['view_num']>1000){
                    $v['view_num']=round($v['view_num']/1000,1).'k';
                    if($v['view_num']>10000){
                        $v['view_num']=round($v['view_num']/10000,1).'w';
                    }
                }else{
                    $v['view_num']=(string)$v['view_num'];
                }
                //响应的评论数量
                $count=Db::name('discuss')
                    ->field('count(*) discuss_num')
                    ->where(['action_id'=>$v['action_id'],'group_id'=>['neq','null'],'delete_time'=>null])
                    ->select();
                if(!$count){
                    return apiError('响应评论数量有误');
                }
                $v['discuss_num']=$count[0]['discuss_num'];

                //响应的点赞数量
                $count=Db::name('click')
                    ->field('count(*) click_num')
                    ->where('action_id',$v['action_id'])
                    ->select();
                if(!$count){
                    return apiError('响应点赞数量有误');
                }
                $v['click_num']=$count[0]['click_num'];
                //响应动态的文字和 图片
                if($v['action_pic']){
                    $pic=explode(',',$v['action_pic']);
                    foreach ($pic as & $value){
                        $value=$this->addApiUrl($value);
                    }
                    $v['action_pic']=$pic;
                }
            }
        }
        $i=0;
        foreach ($res as &$v){

            if($v['action_pic']==''){
                $v['action_pic']=[];
            }
            $res[$i]['content']=$this->userTextDecode($res[$i]['content']);
            $res[$i]['action_pic_num']=count($v['action_pic']);
            $r=Db::name('click')->where(['user_id'=>$id,'action_id'=>$res[$i]['action_id']])->find();
            if($r){
                $res[$i]['is_click']=1;
            }else{
                $res[$i]['is_click']=0;
            }
            //判断是否已经领取过红包
            if($v['red_type']!='null'){
                if($id){
                    $v['red_type']=$this->getRedType($id,$v['action_id'],$v['red_type'],$v['is_over']);
                }
            }
            $i++;
        }
        return apiSuccess(['timestamp'=>$timestamp],$res);


    }

    /**
     * 举报，拉黑，发消息，删除
     * report black sendmsg delete
     */
    public function multiFunction(){
        $type=input('type'); //操作的类型report black delete
        $action_id=input('action_id');//操作的动态
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


        //4.判断处理类型 开始对应操作
        if($type=='report'){
            //举报
            if($user_id!=''&&$action_id!=''){
                if($user_id==$id){
                    return apiError('不能将自己举报');
                }
                //判断该用户和动态是否来自一条记录
                $a=Db::name('action')->where(['id'=>$action_id,'user_id'=>$user_id])->find();
                if(!$a){
                    return apiError('动态不是该用户发表');
                }
                //从动态列表传递
                //1 是否已经举报
                $yes=Db::name('report')->where(['report_user_id'=>$id,'action_user_id'=>$user_id,'action_id'=>$action_id])->find();
                if($yes){
                    return apiSuccess('举报成功','');
                }

//                $data['type']=1; //默认举报人
//                if($action_id!=''){
//                    //动态举报
//                    $data['type']=2;
//                }
                //将动态的id，发表动态的用户和举报人保存数据库
                $data=[
                    'action_id'=>$action_id,
                    'action_user_id'=>$user_id,
                    'report_user_id'=>$id,
                    'type'=>2,
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
            $user_id=Db::name('action')->where('id',$action_id)->value('user_id');
            if($user_id!=$id){
                return apiError('没有删除权利');
            }
            //2.判断该动态是否已经被删除；
            $action=Db::name('action')->where('id',$action_id)->value('delete_time');
            if($action){
                return apiError('该动态已经被删除');
            }
            //3.判断该动态是否有红包
            Db::startTrans(); //开启事务
            try{
                $red=Db::name('redpacket')->where('did',$action_id)->lock(true)->find();
                if($red){
                    //4.红包是否有余额
                    if($red['is_over']==1){
                        //无余额 直接删除 返回
                        $res=\app\apps2\model\Action::get($action_id)->delete(); //软删除
                        if(!$res){
                            return apiError('删除动态错误');
                        }
                        Db::commit();
                        return apiSuccess('删除动态成功','');

                    }
                    //有余额已经返回
                    if($red['is_back']==1){
                        //已经返回
                        $res=\app\apps2\model\Action::get($action_id)->delete(); //软删除
                        if(!$res){
                            return apiError('删除动态错误');
                        }
                        Db::commit();
                        return apiSuccess('删除动态成功','');
                    }
                    //余额退回账户
                    //分配的随机红包
                    $min=Db::name('redpacket')
                        ->where('did',$action_id)->field('id,data')->find();

                    $min2=json_decode($min['data']); //分配的红包
                    $m=0; //红包剩余总金额 没有领的红包总金额
                    if(!empty($min2)){
                        foreach ($min2 as $v){
                            if($v->uid==''){
                                $m+=$v->money;
                            }
                        }
                    }
                    //用户账户余额
                    $money2=Db::name('user_wallet')->where('uid',$id)->value('money');
                    //账户余额加上红包剩余
                    $money=$m+$money2;
                    //添加红包记录
                    //wallet_record
//                    $data=[
//                        'uid'=>$id,
//                        'rid'=>$min['id'],
//                        'sid'=>$action_id,
//                        'stype'=>2,
//                        'money'=>$m,
//                        'wallet'=>$money,
//                        'type'=>1,
//                        'status'=>4,
//                        'des'=>'红包退回',
//                        'time'=>date('Y-m-d H:i:s',time())
//                    ];
//                    $red=Db::name('wallet_record')->insert($data);
//                    if(!$red){
//                        return apiError('红包流水错误');
//                    }
                    $this->walletRecordToatl($id,1,$m,2,4);
                    //将余额添加到该账户的余额
                    //user_wallet
                    $res=Db::name('user_wallet')->where('uid',$id)->update(['money'=>$money]);
                    if(!$res){
                        return apiError();
                    }
                    //修改红包状态
                    $is=Db::name('redpacket')->where('did',$action_id)->update(['is_back'=>1]);
                    if(!$is){
                        return apiError('红包状态修改失败');
                    }
                }
                Db::commit();
            } catch (\Exception $e){
                Db::rollback();
                return apiError('删除动态错误');
           }
            $res=\app\apps2\model\Action::get($action_id)->delete(); //软删除
            if(!$res){
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
        //1.判断用户是否存在
//        $id = input('get.id');
//        $token = input('get.token');
//        if($id!=null && $token!=null){
//            $res=$this->checkToken($id,$token);
//            if($res['status']){
//                return apiError($res['msg'],'',$res['code']);
//            }
//        }
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



}
