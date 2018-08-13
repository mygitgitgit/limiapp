<?php
namespace app\apps1_2\controller;
use think\Db;
use think\Request;

class User extends Common
{
    /**
     * Created by zyjun
     * Info:初始化
     */
    public function _initialize(){

    }

    /**
     * Created by zyjun
     * Info:用户登录,不完善信息
     */
    public function login(){
        $mobile=input('phone');
        $code=input('code');
        if($mobile==''){
            apiError('手机号不能为空');
            return;
        }
        if(checkMobile($mobile)){
            apiError('手机号格式错误');
            return;
        }
        $res=Db::name('user')
            ->where('mobile',$mobile)
            ->field('id,code,code_time,identity_time,status,user_info_status,identity_status,code_errors num,code_error_time error_time,pay_password,pay_password_status')
            ->find();
        if($res['status']=='1'){
            apiError('此账号已被禁用！');
            return;
        }
        if(empty($res)){
            apiError('请先发送验证码！');
            return;
        }
        if($code==''){
            apiError('验证码不能为空');
            return;
        }
        if(checkCode($code)){
            apiError('验证码格式错误');
            return;
        }
        if($code!=$res['code']){
            //判断是否被禁用{}
            if($res['num']==5){
                //被禁用了 判断是否超过30
                if(time()-strtotime($res['error_time'])<30*60){
                    return apiError('账号已被锁定30分钟后再试');
                }else{
                    //大于30分钟解除禁用 次数归零 重新增加
                    Db::name('user')->where('mobile',$mobile)->update(['code_errors'=>0,'code_error_time'=>null]);
                }
            }
            //没有被禁用记录错误次数
            $res1=Db::name('user')->where('mobile',$mobile)->setInc('code_errors',1);
            $res2=Db::name('user')->where('mobile',$mobile)->update(['code_error_time'=>date('Y-m-d H:i:s',time())]);
            if($res1&&$res2){
                //判断错误次数
                $num=Db::name('user')->where('mobile',$mobile)->value('code_errors');
                if($num>=5){
                    return apiError('账号已被锁定30分钟后再试');
                }
                return apiError('还有'.(5-$num).'次机会');
            }
            return apiError('验证码错误！');
        }else{  // 验证码正确判断是否在禁用时间内如果在禁用时间内验证码无效
            //判断是否被禁用{}
            if($res['num']==5){
                //被禁用了 判断是否超过30
                if(time()-strtotime($res['error_time'])<30*60){
                    return apiError('账号已被锁定30分钟后再试');
                }else{
                    //大于30分钟解除禁用 次数归零 重新增加
                    Db::name('user')->where('mobile',$mobile)->update(['code_errors'=>0,'code_error_time'=>null]);
                }
            }else{
                Db::name('user')->where('mobile',$mobile)->update(['code_errors'=>0,'code_error_time'=>null]);
            }
        }
        $time=time()-strtotime($res['code_time']);
        if($time>600){    //十分钟内有效
            apiError('验证码已过期！');
            return;
        }
        //判断支付密码状态
        $pay_password=$res['pay_password'];
        $pay_password_status=$res['pay_password_status'];
        $data['pay_password_status']=0;
        if(empty($pay_password)){
            $data['pay_password_status']=1; //没设置支付密码
        }
        if($pay_password_status==1){
            $data['pay_password_status']=2; //支付密码倍禁用
        }
        //注册后返回基本信息 id+token验证用户
        $data['token']=$this->createToken($mobile);
        $data['id']=$res['id'];
        $data['user_info_status']=$res['user_info_status'];
        $data['identity_status']=$res['identity_status'];
        $update['token']=$data['token'];
//        $update['code']=''; //清空code
        Db::name('user')->where('mobile',$mobile)->update($update); //生成token
        apiSuccess('登录成功',$data);
    }

    /**
     * Created by zyjun
     * Info:注册时完善用户基本信息，
     *接收参数为 nickname
     * true_name改 nickname head_pic 不能为空
     */
    public function perfectUserBasicInfo(){
        $id=input('id');
        $token=input('token');
        $res=$this->checkToken($id,$token);
        if($res['status']){
            return apiError($res['msg'],'',$res['code']);
        }
        $nickname=$data['nickname']=input('nickname');
        $sex=$data['sex']=input('sex');
        if($nickname==''){
            apiError('昵称不能为空');
            return;
        }
        if($sex==''){
            apiError('性别不能为空');
            return;
        }
        if($this->checkNickName($nickname)){
            apiError('请输入2-12位纯中文或纯英文姓名');
            return;
        }
        $res=Db::name('user')->where('nickname',$nickname)->find();
        if($res){
            return apiError('此昵称已被占用');
        }
        $res=Db::name('user')->where('id',$id)->update($data);
        if($res===false){
            apiError('保存失败');
            return;
        }
        $res=Db::name('user')->where('id',$id)->field('sex,nickname,head_pic')->find();
        if($res['head_pic']==''){
            return apiError('头像不能为空');
        }
        if($res['sex']!==''&&$res['nickname']!==''&&$res['head_pic']!==''){
            Db::name('user')->where('id',$id)->update(['user_info_status'=>'1']); //完成了头像性别真实姓名
        }
        $res=Db::name('user')->where('id',$id)->field('id,user_info_status')->find();
        apiSuccess('保存成功',$res);
    }

    /**
     * Created by zyjun
     * Info认证接口，
     *
     */
    public function perfectUserInfo(){
        $id=input('id');
        $token=input('token');
        $res=$this->checkToken($id,$token);
        if($res['status']){
            return apiError($res['msg'],'',$res['code']);
        }
        $true_name=$data['true_name']=input('true_name');
        $college=$data['college_id']=input('college');
        $school=$data['school_id']=input('school');
        $grade=$data['grade_id']=input('grade');
        $identity_pic=$data['identity_pic']=input('identity_pic');
        if($true_name==''){
            apiError('姓名不能为空');
            return;
        }
        if($college==''){
            apiError('学校名称不能为空');
            return;
        }
        if($school==''){
            apiError('学院名称不能为空');
            return;
        }
        if($grade==''){
            apiError('年级不能为空');
            return;
        }
        if($identity_pic==''){
            apiError('认证图片不能为空');
            return;
        }
        if($this->checkTrueName($true_name)){
            apiError('请输入2-10位纯中文或纯英文姓名');
            return;
        }
        $data['user_info_status']=2;  //完善了所有信息
        //$data['identity_status']=1;   //状态修改了认证中
        $data['identity_status']=2;   //状态修改了认证
        $data['identity_time']=date('Y-m-d H:i:s');
        $res=Db::name('user')->where('id',$id)->update($data);
        if($res===false){
            apiError('保存失败');
            return;
        }
        $Im=new Im();
        $res=Db::name('user')->where('id',$id)->field('nickname,head_pic,sex')->find();
        $accid=Db::name('im_user')->where('uid',$id)->value('accid');
        if(empty($accid)){
            $data['im_update']=1;
            $data['im_update_msg']='未查询到IM用户';
            $data['identity_status']=2;
            return apiError('',$data);
        }
        $res2=$Im->updateImUinfo($accid,$res['nickname'],$res['head_pic'],$res['sex'],'','','','','');
        if($res2['status']){ //网易云通讯默认头像昵称更新失败
            $data['im_update']=1;
            $data['im_update_msg']=$res2['msg'];
        }
         apiSuccess('保存成功','');
    }

    /**
     * Created by zyjun
     * Info:个人中心查看身份认证信息
     *
     */
    public function centerShowUserInfo(){
        $id=input('id');
        $token=input('token');
        $res=$this->checkToken($id,$token);
        if($res['status']){
            return apiError($res['msg'],'',$res['code']);
        }
        $res=Db::name('user')->where('id',$id)->field('true_name,sex,head_pic')->find();

        $data['true_name']=$res['true_name'];
        if($res['sex']=='0'){
            $data['sex']='女';
        }else{
            $data['sex']='男';
        }
        $data['head_pic']=$this->addApiUrl($res['head_pic']);
        $data['college']=$this->getUserCollege($id);
        $data['school']=$this->getUserSchool($id);
        $data['grade']=$this->getUserGrade($id);
        $identity=$this->isAccess($id);
        $data['identity_status']=$identity['identity_status'];
        apiSuccess('用户信息',$data);
    }


    /**
     * Created by zyjun
     * Info:获取大学列表
     */
    public function collegeList(){
        $college=input('college');
//        $province_id=input('get.provinceID');
//        if(empty($province_id)){
//            apiError('provinceID参数错误');
//            return;
//        }
        $where=[];
        if($college!=''){
            $where['name']=['like','%'.$college.'%'];
        }

        //$res=$this->getCollegeList($province_id);
        $res=Db::name('college')->where($where)->select();
        apiSuccess('大学信息',$res);
    }

    /**
     * Created by zyjun
     * Info:获取学院列表
     */
    public function schoolList(){
        $college_id=input('get.collegeID');
        if(empty($college_id)){
            apiError('collegeID参数错误');
            return;
        }
        $res=$this->getSchoolList($college_id);
        apiSuccess('学院信息',$res);
    }

    /**
     * Created by zyjun
     * Info:获取年级列表
     */
    public function gradeList(){
        $res=$this->getGradeList();
        apiSuccess('年级信息',$res);
    }

    /**
     * Created by zyjun
     * Info:上传用户头像
     */
    public function uploadUserHeadImg(){
        $id=input('id');
        $token=input('token');
        $type=input('type','head'); //默认是上传头像
        $image=input('images');
        $res=$this->checkToken($id,$token);
        if($res['status']){
            return apiError($res['msg'],'',$res['code']);
        }
        if($image==''){
            return apiError('图片不能为空');
        }
        $res=$this->isSexyImg($image);
        if($res['status']){//包含色情或者性感图片
            $sex_status=$res['code'];
            if($sex_status==0){
                return apiError('图片可能包含敏感信息，请重新上传');
            }
        }
        $data=array();
        $d=array();
        if($type=='back'){
            $data['back_pic']=$image;
            $d['url']=$this->addApiUrl($image);
        }elseif($type=='head'){
            $data['head_pic']=$image;
            $d['url']=$this->addApiUrl($image);
        }
        $res=Db::name('user')->where('id',$id)->update($data);
        if($res===false){
             return apiError('保存失败');
        }
         //更新网易云头像
        $res=$this->isAccess($id);
         if($res['identity_status']==2){
             $Im=new Im();
             $res=Db::name('user')->where('id', $id)->field('nickname,head_pic,sex')->find();
             $accid=Db::name('im_user')->where('uid', $id)->value('accid');
             if(!empty($res)){
                 $Im->updateImUinfo($accid,'',$res['head_pic'],'','','','','','');
             }
         }
        apiSuccess('保存成功',$d);
    }

    /**
     * Created by zyjun
     * Info:是否认证页面接口
     */
        public function identityStatus(){
            $id=input('id');
            $token=input('token');
            $res=$this->checkToken($id,$token);
            if($res['status']){
                return apiError($res['msg'],'',$res['code']);
            }
            $status=$this->isAccess($id);
            apiSuccess($status['msg'],'');
        }

    /**
     * 用户个人中心
     */
    public function myCenter(){
        $id=input('get.id');
        $token=input('get.token');
        //$type=input('get.type','action');
        //$page=input('get.page');
        $res=$this->checkToken($id,$token);
        if($res['status']){
            return apiError($res['msg'],'',$res['code']);
        }
//        if($page<1||$page==''){
//            return apiError('页码错误');
//        }
        //用户的基本信息
        $user_info=Db::name('user')
            ->where('id',$id)
            ->field('nickname,head_pic,back_pic,sex,signature')
            ->find();
        if($user_info['sex']=='0'){
            $user_info['sex']='女';
        }else{
            $user_info['sex']='男';
        }
        if($user_info['head_pic']){
            $user_info['head_pic']=$this->addApiUrl($user_info['head_pic']);
        }
        if($user_info['back_pic']){
            $user_info['back_pic']=$this->addApiUrl($user_info['back_pic']);
        }else{
            $user_info['back_pic']=$this->addApiUrl('/uploads/user/images/head_pic.png');
        }
        //用户认证信息
        $is_access=$this->isAccess($id);
        //现金
        $money=Db::name('user_wallet')->where('uid',$id)->value('money');
        if($money==null){
            $money=0;
        }
        //我的动态数量
        $my_action=Db::name('action')
            ->where('user_id',$id)
            ->where('delete_time','null')
            ->field('count(*) action_num')
            ->select();
        //$order_num=$this->order_num($id);
        $num=$this->myNum($id);
        //我的动态
//        $my_action=$this->myInfoList($type,$page,['u.id'=>$id,'delete_time'=>null]);
//        foreach ($my_action as $k=>&$v){
//            if($v['action_pic']==''){
//                $v['action_pic']=[];
//            }
//            $v['content']=$this->userTextDecode($v['content']);
//            $v['action_pic_num']=count($v['action_pic']);
//            $r=Db::name('click')->where(['user_id'=>$id,'action_id'=>$v['action_id']])->find();
//            if($r){
//                $v['is_click']=1;
//            }else{
//                $v['is_click']=0;
//            }
//            //判断是否已经领取过红包
//            if($v['red_type']!='null'){
//                $sex=Db::name('user')->where('id',$id)->value('sex');
//                if($sex==$v['red_type']||$v['red_type']==2){
//                    $red=Db::name('redpacket')->where('did',$v['action_id'])->find();
//                    //判断是否已经过期
//                    $expire_time=$this->getRedpacketExpireTime();
//                    $sent_time=strtotime($red['sent_time']);
//                    if(time()-$sent_time>$expire_time){
//                        //红包已经过期
//                        $v['red_type']='4';
//                        //过期之后 是否抢到过
//                        $red_data=json_decode($red['data'],true);
//                        foreach ($red_data as &$d){
//                            if($d['uid']==$id){
//                                $v['red_type']='3'; //
//                            }
//                        }
//                    }else{
//                        //没有过期 判断是否抢完了
//                        if($v['is_over']==1){
//                            //抢完了
//                            $v['red_type']='5';
//                            $red_data=json_decode($red['data'],true);
//                            foreach ($red_data as &$d){
//                                if($d['uid']==$id){
//                                    $v['red_type']='3'; //
//                                }
//                            }
//                        }else{
//                            //没有抢完
//                            $red_data=json_decode($red['data'],true);
//                            foreach ($red_data as &$d){
//                                if($d['uid']==$id){
//                                    $v['red_type']='3'; //
//                                }
//                            }
//                        }
//                    }
//                }
//            }
//        }
        //整理数据
        $data=[
            'nickname'=>$user_info['nickname'],
            'head_pic'=>$user_info['head_pic'],
            'signature'=>$this->userTextDecode($user_info['signature']),
            'back_pic'=>$user_info['back_pic'],
            'sex'=>$user_info['sex'],
            'is_access'=>$is_access['identity_status'],
            'attention_num'=>$num['attention_num'],
            'fans_num'=>$num['fans_num'],
//            'action_num'=>$my_action[0]['action_num'],
//            'money'=>$money
        ];

        return apiSuccess('',$data);
    }

    /**
     * 个人信息列表
     */
    public function infoList(){
        $id=input('id');
        $token=input('token');
        $res=$this->checkToken($id,$token);
        if($res['status']){
            return apiError($res['msg'],'',$res['code']);
        }
        $is_access=$this->isAccess($id);
        $user_info=$this->userInfo($id);
        $user_info['is_access']=$is_access['identity_status'];
        return apiSuccess('信息列表显示',$user_info);
    }
    /**
     * 编辑个人信息
     */
    public function editUser()
    {
        $id = input('post.id');
        $token = input('post.token');
        $res = $this->checkToken($id, $token);
        if ($res['status']) {
            return apiError($res['msg'],'',$res['code']);
        }
        $signature=input('post.signature');
        $nickname=input('post.nickname');
        $sex=input('post.sex');
        $data=array();
        if($signature!=''){
            $signature = preg_replace('/\s*/', '', $signature);
            if(strlen($signature)>60){
                return apiError('不超过20个字');
            }
            $signature=$this->userTextEncode($signature);
            $res=Db::name('user')->where('id',$id)->value('signature');
            if($res!=$signature){
                $data['signature']=$signature;
            }
        }
        if($nickname!=''){
            if($this->checkNickName($nickname)){
                apiError('请输入2-12中文字母');
                return;
            }
            $res=Db::name('user')->where('nickname',$nickname)->find();
            if($res){
                return apiError('此昵称已被占用');
            }
            $res=Db::name('user')->where('id',$id)->value('nickname');
            if($res!=$nickname){
                $data['nickname']=$nickname;
            }
        }
        if($sex!=''){
            $res=Db::name('user')->where('id',$id)->value('sex');
            if($res!=$sex){
                $data['sex']=$sex;
            }
        }
        if ($data) {
            $res = Db::name('user')->where('id', $id)->update($data);
            //$user_info = $this->userInfo($id);
            if ($res) {
                //更新网易云信息
                $Im=new Im();
                $res=Db::name('user')->where('id', $id)->field('nickname,head_pic,sex')->find();
                $accid=Db::name('im_user')->where('uid', $id)->value('accid');
                if(!empty($res)){
                    $Im->updateImUinfo($accid,$res['nickname'],$res['head_pic'],$res['sex'],'','','','','');
                }
                return apiSuccess('修改成功');
            }else{
                return apiError('修改失败');
            }
        }else{
            //如果没有字段不修改
            return apiSuccess('修改成功');
        }

    }

    /**
     * 设置、重置支付密码
     */
    public function paymentCodeAction(){
        //判断用户是否存在
        $id=input('id');
        $token=input('token');
        $res=$this->checkToken($id,$token);
        if($res['status']){
            return apiError($res['msg'],'',$res['code']);
        }
        //2.判断用户是否已经通过认证 在我的现金里
        $res=$this->isAccess($id);
        if($res['identity_status']!=2){
            return apiError($res['msg'],$res['identity_status']);
        }
        //处理数据
        $code=input('code');
        if(!preg_match('/\d{4}/',$code)){
            return apiError('验证码格式不正确');
        }
        $password1=input('password1');
        $password2=input('password2');
        $preg1=$this->checkPayCode($password1);
        if($preg1){
            return apiError('密码格式不正确');
        }
        $preg2=$this->checkPayCode($password2);
        if($preg2){
            return apiError('密码格式不正确');
        }
        if($password1!=$password2){
            return apiError('两次支付密码不一致');
        }
        //检测验证码是否正确
        $res=Db::name('user')
            ->where('id',$id)
            ->field('id,code,code_time,identity_time,status,user_info_status,identity_status,code_errors num,code_error_time error_time,pay_password,pay_password_status')
            ->find();
        $time=time()-strtotime($res['code_time']);
        if($time>600){
            apiError('验证码已过期！');
            return;
        }
        if($code!=$res['code']){
            //判断是否被禁用{}
            if($res['num']==5){
                //被禁用了 判断是否超过30
                if(time()-strtotime($res['error_time'])<30*60){
                    return apiError('账号已被锁定请30分钟后再试');
                }else{
                    //大于30分钟解除禁用 次数归零 重新增加
                    Db::name('user')->where('id',$id)->update(['code_errors'=>0,'code_error_time'=>null]);
                }
            }
            //没有被禁用记录错误次数
            $res1=Db::name('user')->where('id',$id)->setInc('code_errors',1);
            $res2=Db::name('user')->where('id',$id)->update(['code_error_time'=>date('Y-m-d H:i:s',time())]);
            if($res1&&$res2){
                //判断错误次数
                $num=Db::name('user')->where('id',$id)->value('code_errors');
                if($num>=5){
                    return apiError('账号已被锁定请30分钟后再试');
                }
                return apiError('还有'.(5-$num).'次机会');
            }
            return apiError('验证码错误！');
        }else{  // 验证码正确判断是否在禁用时间内如果在禁用时间内验证码无效
            //判断是否被禁用{}
            if($res['num']==5){
                //被禁用了 判断是否超过30
                if(time()-strtotime($res['error_time'])<30*60){
                    return apiError('账号已被锁定请30分钟后再试');
                }else{
                    //大于30分钟解除禁用 次数归零 重新增加
                    Db::name('user')->where('id',$id)->update(['code_errors'=>0,'code_error_time'=>null]);
                }
            }else{
                Db::name('user')->where('id',$id)->update(['code_errors'=>0,'code_error_time'=>null]);
            }
        }
        // 判断密码是否存在 存在表示重置 不存在表示 设置
        $password1=$this->createPayPassword($password1);
        if($res['pay_password']===null){
            //设置密码
            //将密码保存到数据库
            $d=Db::name('user')->where('id',$id)->update(['pay_password'=>$password1]);
            if($d){
                return apiSuccess('密码设置成功');
            }
        }else{
            //重置密码
                $data=[
                    'pay_password'=>$password1,
                    'pay_password_errors'=>0,
                    'pay_password_error_time'=>null,
                    'pay_password_status'=>0
                ];
                $res=Db::name('user')->where('id',$id)->update($data);
                if(!$res){
                    return apiError('不能与原密码一致');
                }
                return apiSuccess('密码重置成功');

        }

    }

    /**
     * 交易记录
     */
    public function myRecord(){
        //判断用户是否存在
        $id=input('get.id');
        $token=input('get.token');
        $page=input('get.page');
        $res=$this->checkToken($id,$token);
        if($res['status']){
            return apiError($res['msg'],'',$res['code']);
        }
        if($page<1||$page==''){
            return apiError('页码错误');
        }
        //2.判断用户是否已经通过认证
        $res=$this->isAccess($id);
        if($res['identity_status']!=2){
            return apiError($res['msg'],$res['identity_status']);
        }
        $record=Db::name('wallet_record')
            ->where('uid',$id)
            ->field('des,money,type,time')
            ->order('time desc')
            ->page($page,20)
            ->select();
        if(empty($record)){
            return apiSuccess('','记录为空');
        }
        $data=[];
        $i=0;
        foreach ($record as $v){
            $data[$i]['des']=$v['des'];
            if($v['type']==1){
                $data[$i]['money']='+'.$v['money'];
            }elseif ($v['type']==0){
                $data[$i]['money']='-'.$v['money'];
            }
            $data[$i]['time']=date('Y.m.d H:i:s',strtotime($v['time']));
            $i++;
        }
        return apiSuccess('',$data);
    }

    /**11111
     * Created by zyjun
     * Info:我的钱包
     */
    public function myCash(){
        $uid = input('post.id');
        $token = input('post.token');
        $res = $this->checkToken($uid, $token);
        if ($res['status']) {
            return apiError($res['msg'],'',$res['code']);
        }
        //验证权限
        $res=$this->isAccess($uid);
        if($res['identity_status']!=2){
            return apiError($res['msg'],$res['identity_status']);
        }
        $wallet=Db::name('user_wallet')->where('uid',$uid)->value('money');
        if(empty($wallet)){
            $data['money']=0;
        }else{
            $data['money']=$wallet;
        }
        $is_set_passwd=Db::name('user')->where('id',$uid)->value('pay_password');
        if(empty($is_set_passwd)){
            $data['is_set_passwd']=1;
        }else{
            $data['is_set_passwd']=0;
        }
        $res=Db::name('sys_set')->where('id',2)->find();
        if(!empty($res)){
            $data['content']=json_decode($res['data'],true);
        }else{
            $data['content']='';
        }
        return apiSuccess('我的现金',$data);
    }
    /**44444
     * 用户反馈
     */
    public function feedBack(){
        $id = input('post.id');
        $token = input('post.token');
        $res = $this->checkToken($id, $token);
        if ($res['status']) {
            return apiError($res['msg'],'',$res['code']);
        }
//        //验证权限
//        $res=$this->isAccess($id);
//        if($res['identity_status']!=2){
//            return apiError('请先认证个人身份信息！');
//        }
        $type=input('post.type',3);
        $info=input('post.info');
        $phone=input('post.phone',0);
        $pic=input('post.pic','');
        if($type!=1&&$type!=2&&$type!=3){
            return apiError('请输入正确的反馈类型');
        }
        if(empty(trim($info))){
            return apiError('反馈信息不能为空');
        }
//        if($phone!=''){
//            if(checkMobile($phone)){
//                apiError('手机号码格式不正确');
//                return;
//            }
//        }
        if(strpos($pic,',')!==false){ //多图上传
            $length=count(explode(',',$pic));
            if($length>9){
                return apiError('最多只能上传9张图片');
            }
        }
        $data=[
            'type'=>$type,
            'uid'=>$id,
            'info'=>$this->userTextEncode($info),
            'contact'=>$phone,
            'pic'=>$pic,
            'create_time'=>date('Y-m-d H:i:s',time())
        ];
        $res=Db::name('feedback')->insert($data);
        if(!$res){
            return apiError('反馈信息失败');
        }
        return apiSuccess('反馈成功正在处理');
    }

    /**22222
     * 我的订单
     */
    public function myOrderList(){
        $id = input('get.id');
        $token = input('get.token');
        $page=input('get.page');
        $res = $this->checkToken($id, $token);
        if ($res['status']) {
            return apiError($res['msg'],'',$res['code']);
        }
        //验证权限
        $res=$this->isAccess($id);
        if($res['identity_status']!=2){
            return apiError($res['msg'],$res['identity_status']);
        }
        if($page<1||$page==''){
            return apiError('页码错误');
        }
        //判断用户是否有订单
        $res=Db::name('order')->where('uid',$id)->select();
        if($res){
            foreach ($res as $value){

                $order_list=Db::name('order o')
                    ->join('weekend_order wo','o.shop_order_no=wo.order_num','LEFT')
                    ->join('weekend_order_goods w','wo.id=w.order_id','LEFT')
                    ->field('w.pic,o.shop_order_no,w.order_id order_id,w.weekend_id weekend_id,o.uid user_id,w.name,w.feature,w.time,w.to,o.money,w.num,o.order_status')
                    ->where('o.uid',$id)
                    ->order('o.time desc')
                    ->page($page,20)
                    ->select();
                $data=[];
                foreach ($order_list as $k=>&$v){
                    if($v['pic']){
                        $pic=explode(',',$v['pic']);
                        foreach ($pic as & $value){
                            $value=$this->addApiUrl($value);
                        }
                        $v['pic']=$pic[0];
                    }
                    if($v['order_status']===0||$v['order_status']==3){
                        continue;
                    }
                    $data[]=$v;
                    // 0未支付 1 已经支付 2交易完成 3交易关闭 4已经退款

                }
                return apiSuccess('',$data);
            }
        }else{
            return apiSuccess('暂时没有订单');
        }

    }
    /**5555
     * 我的黑名单
     */
    public function myBlackList(){
        $id=input('get.id');
        $token=input('get.token');
        $page=input('get.page');
        $res = $this->checkToken($id, $token);
        if ($res['status']) {
            return apiError($res['msg'],'',$res['code']);
        }
        //验证权限
        $res=$this->isAccess($id);
        if($res['identity_status']!=2){
            return apiError($res['msg'],$res['identity_status']);
        }
        if($page<1||$page==''){
            return apiError('页码错误');
        }
        $user_list=Db::name('user_black')
            ->where(['user_id'=>$id,'delete_time'=>null,'is_cancel'=>0])
            ->order('create_time desc')
            ->page($page,10)
            ->select();
        if(!$user_list){
            return apiSuccess('没有黑名单');
        }
        $black_users=array();
        foreach ($user_list as $key=>$value){
            $user_info=$this->userInfo($value['black_user_id']);
            $black_users[$key]['user_id']=$user_info['user_id'];
            $black_users[$key]['nickname']=$user_info['nickname'];
            $black_users[$key]['head_pic']=$user_info['head_pic'];
            $black_users[$key]['college']=$user_info['college'];
        }
        return apiSuccess('',$black_users);
    }
    /**
     *取消拉黑
     */
    public function deleteBlackUser(){
        $id=input('post.id');
        $token=input('post.token');
        $black_id=input('post.black_id');
        $res = $this->checkToken($id, $token);
        if ($res['status']) {
            return apiError($res['msg'],'',$res['code']);
        }
        //验证权限
        $res=$this->isAccess($id);
        if($res['identity_status']!=2){
            return apiError($res['msg'],$res['identity_status']);
        }
        $is_cancel=Db::name('user_black')->where(['user_id'=>$id,'black_user_id'=>$black_id])->value('is_cancel');
        if($is_cancel==1){
            return apiSuccess('取消拉黑成功');
        }
        $res=Db::name('user_black')
            ->where(['user_id'=>$id,'black_user_id'=>$black_id])
            ->update(['is_cancel'=>1]);
        if($res){
           return apiSuccess('取消拉黑成功');
        }
    }
    /**
     * 添加/取消 关注
     */
    public function addAttention(){
        $data['user_id']=$id=input('get.id');
        $token=input('get.token');
        $data['attention_id']=$attention_id=input('attention_id');
        $res = $this->checkToken($id, $token);
        if ($res['status']) {
            return apiError($res['msg'],'',$res['code']);
        }
        //验证权限
        $res=$this->isAccess($id);
        if($res['identity_status']!=2){
            return apiError($res['msg'],$res['identity_status']);
        }
        //查询是否有关注记录
        $res=Db::name('user_relation')->where($data)->find();
        //有关注记录修改表
        if($res){
            $res1=Db::name('user_relation')->where($data)->value('is_cancel');
            if($res1==1){
                //判断是否在黑名单内
                $result=Db::name('user_black')
                    ->where(['user_id'=>$id,'black_user_id'=>$attention_id,'is_cancel'=>0])
                    ->find();
                if($result){
                    return apiError('黑名单用户不可关注');
                }
                //判断是否认证
                $res=$this->isAccess($attention_id);
                if($res['identity_status']!=2){
                    return apiError('该用户没有认证不能关注');
                }
                //改为0
                $d['is_attention']=0;
                $res11=Db::name('user_relation')->where($data)->update(['is_cancel'=>0,'create_time'=>date('Y-m-d H:i:s',time())]);
                if($res11){
                    $r1=Db::name('user_relation')->where(['user_id'=>$id,'attention_id'=>$attention_id,'is_cancel'=>0])->find();
                    $r2=Db::name('user_relation')->where(['user_id'=>$attention_id,'attention_id'=>$id,'is_cancel'=>0])->find();
                    if($r1){
                        $d['is_attention']=1;
                    }
                    if($r1&&$r2){
                        $d['is_attention']=2; //互关注
                    }
                    //关注通知消息
                    $this->attentionImMsg($id,$attention_id);
                    return apiSuccess('关注成功',$d);
                }
            }elseif($res1===0){
                //改为1
                $d['is_attention']=0;
                $res12=Db::name('user_relation')->where($data)->update(['is_cancel'=>1]);
                if($res12){
                    $r1=Db::name('user_relation')->where(['user_id'=>$id,'attention_id'=>$attention_id,'is_cancel'=>0])->find();
                    $r2=Db::name('user_relation')->where(['user_id'=>$attention_id,'attention_id'=>$id,'is_cancel'=>0])->find();
                    if($r1){
                        $d['is_attention']=1;
                    }
                    if($r1&&$r2){
                        $d['is_attention']=2; //互关注
                    }
                    return apiSuccess('取消成功',$d);
                }
            }
        }else{
            //判断是否在黑名单内
            $result=Db::name('user_black')
                ->where(['user_id'=>$id,'black_user_id'=>$attention_id,'is_cancel'=>0])
                ->find();
            if($result){
                return apiError('黑名单用户不可关注');
            }
            //判断是否认证
            $res=$this->isAccess($attention_id);
            if($res['identity_status']!=2){
                return apiError('该用户没有认证不能关注');
            }
            //没有关注记录添加表
            $data['is_cancel']=0;
            $data['create_time']=date('Y-m-d H:i:s',time());
            $res2=Db::name('user_relation')->insert($data);
            if($res2){
                $r1=Db::name('user_relation')->where(['user_id'=>$id,'attention_id'=>$attention_id,'is_cancel'=>0])->find();
                $r2=Db::name('user_relation')->where(['user_id'=>$attention_id,'attention_id'=>$id,'is_cancel'=>0])->find();
                $d=array();
                if($r1){
                    $d['is_attention']=1;
                }
                if($r1&&$r2){
                    $d['is_attention']=2; //互关注
                }
                //关注通知消息
                $this->attentionImMsg($id,$attention_id);
                return apiSuccess('关注成功',$d);
            }
        }
    }
    /**
     * 关注列表
     */
    public function myAttentionList(){
        $id=input('get.id');
        $token=input('get.token');
        $page=input('get.page');
        $res = $this->checkToken($id, $token);
        if ($res['status']) {
            return apiError($res['msg'],'',$res['code']);
        }
        //验证权限
        $res=$this->isAccess($id);
        if($res['identity_status']!=2){
            return apiError($res['msg'],$res['identity_status']);
        }
        if($page<1||$page==''){
            return apiError('页码错误');
        }
        $list=Db::name('user_relation')
            ->where(['user_id'=>$id,'is_cancel'=>0])
            ->order('create_time desc')
            ->page($page,10)
            ->column('attention_id');
        $attention_users=array();
        if($list){
            foreach ($list as$key=>&$v){
                $list_info[]=$this->userInfo($v);
                $user_info=$this->userInfo($v);
                $my_num=$this->myNum($v);
                $attention_users[$key]['user_id']=$user_info['user_id'];
                $attention_users[$key]['nickname']=$user_info['nickname'];
                $attention_users[$key]['head_pic']=$user_info['head_pic'];
                $attention_users[$key]['college']=$user_info['college'];
                $attention_users[$key]['fans_num']=$my_num['fans_num'];
                $res1=Db::name('user_relation')->where(['user_id'=>$id,'attention_id'=>$v,'is_cancel'=>0])->find();
                $res2=Db::name('user_relation')->where(['user_id'=>$v,'attention_id'=>$id,'is_cancel'=>0])->find();
                $attention_users[$key]['is_attention']=0;
                if($res1){
                    $attention_users[$key]['is_attention']=1; //已关注
                }
                if($res1&&$res2){
                    $attention_users[$key]['is_attention']=2; //互关注
                }
            }
            return apiSuccess('',$attention_users);
        }else{
            return apiSuccess();
        }
    }
    /**
     * 粉丝列表
     */
    public function myFansList(){
        $id=input('get.id');
        $token=input('get.token');
        $page=input('get.page');
        $res = $this->checkToken($id, $token);
        if ($res['status']) {
            return apiError($res['msg'],'',$res['code']);
        }
        //验证权限
        $res=$this->isAccess($id);
        if($res['identity_status']!=2){
            return apiError($res['msg'],$res['identity_status']);
        }
        if($page<1||$page==''){
            return apiError('页码错误');
        }
        $list=Db::name('user_relation')
            ->where(['attention_id'=>$id,'is_cancel'=>0])
            ->order('create_time desc')
            ->page($page,10)
            ->column('user_id');
        $fans_users=array();
        if($list){
            foreach ($list as$key=>&$v){
                $user_info=$this->userInfo($v);
                $my_num=$this->myNum($v);
                $fans_users[$key]['user_id']=$user_info['user_id'];
                $fans_users[$key]['nickname']=$user_info['nickname'];
                $fans_users[$key]['head_pic']=$user_info['head_pic'];
                $fans_users[$key]['college']=$user_info['college'];
                $fans_users[$key]['fans_num']=$my_num['fans_num'];
                $res1=Db::name('user_relation')->where(['user_id'=>$id,'attention_id'=>$v,'is_cancel'=>0])->find();
                $res2=Db::name('user_relation')->where(['user_id'=>$v,'attention_id'=>$id,'is_cancel'=>0])->find();
                $fans_users[$key]['is_attention']=0; //未关注
                if($res1){
                    $fans_users[$key]['is_attention']=1; //已关注
                }
                if($res1&&$res2){
                    $fans_users[$key]['is_attention']=2; //互关注
                }
            }
            return apiSuccess('',$fans_users);
        }else{
            return apiSuccess();
        }
    }
    /**
     * 昵称搜索
     */
    public function searchUser(){
        $id=input('post.id');
        $token=input('post.token');

        $nickname=input('post.nickname');
        if($nickname==''){
            return apiError('搜索内容不能为空');
        }
        $data['identity_status']=2; //搜索已经认证之后的用户
        //$data['binary nickname']=['like','%'.$nickname.'%']; //区分大小写
        $data['nickname']=['like','%'.$nickname.'%'];
        if($id!=''){
            $data['id']=['neq',$id]; //不能搜索到自己
        }
        $user_list=Db::name('user')
            ->where($data)
            ->field('id')
            ->select();
        $data2=array();
        if($user_list){
            foreach ($user_list as$k=>&$v){
                $user_info=$this->userInfo($v['id']);
                $my_num=$this->myNum($v['id']);
                $data2[$k]['user_id']=$user_info['user_id'];
                $data2[$k]['nickname']=$user_info['nickname'];
                $data2[$k]['head_pic']=$user_info['head_pic'];
                $data2[$k]['college']=$user_info['college'];
                $data2[$k]['fans_num']=$my_num['fans_num'];
                //判断是否加关注
                $res1=Db::name('user_relation')->where(['user_id'=>$id,'attention_id'=>$v['id'],'is_cancel'=>0])->find();
                $res2=Db::name('user_relation')->where(['user_id'=>$v['id'],'attention_id'=>$id,'is_cancel'=>0])->find();
                $data2[$k]['is_attention']=0; //未关注
                if($res1){
                    $data2[$k]['is_attention']=1; //已关注
                }
                if($res1&&$res2){
                    $data2[$k]['is_attention']=2; //互关注
                }
            }
        }
        return apiSuccess('',$data2);
    }
    /**
     * 关注推荐
     */
    public function topAttentionList(){
        $id=input('get.id');
        $where1=array();
        $where2=array();
        if($id!=''){
          $where2['attention_id']=['neq',$id];
          $attention_user=Db::name('user_relation')->where(['user_id'=>$id,'is_cancel'=>0])->column('attention_id');
          $where1['attention_id']=['not in',$attention_user];
        }
        $res=Db::name('user_relation')
            ->where($where1)  //去掉自己已经关注的
            ->where($where2)  //不能推荐自己
            ->where(['is_cancel'=>0])
            ->group('attention_id')  //按照被关注的人分组
            ->order('count(*) desc')   //按照 粉丝数量降序排列
            ->field('count(*) fans_num,attention_id')
            ->limit(20)
            ->select();
        if(!$res){
            return apiSuccess('暂时无推荐');
        }
        $top_users=array();
        foreach ($res as $key=>&$v){
            $user_info=$this->userInfo($v['attention_id']);
            $top_users[$key]['user_id']=$user_info['user_id'];
            $top_users[$key]['nickname']=$user_info['nickname'];
            $top_users[$key]['head_pic']=$user_info['head_pic'];
            $top_users[$key]['college']=$user_info['college'];
            if($v['fans_num']>=10000){
                $v['fans_num']=round($v['fans_num']/10000,1).'万';
            }
            $top_users[$key]['fans_num']=(string)$v['fans_num'];
            $top_users[$key]['is_attention']=0; //未关注
            if($id!=''){
                $res1=Db::name('user_relation')->where(['user_id'=>$id,'attention_id'=>$v['attention_id'],'is_cancel'=>0])->find();
                $res2=Db::name('user_relation')->where(['user_id'=>$v['attention_id'],'attention_id'=>$id,'is_cancel'=>0])->find();
                if($res1){
                    $top_users[$key]['is_attention']=1; //已关注
                }
                if($res1&&$res2){
                    $top_users[$key]['is_attention']=2; //互关注
                }
            }
        }
        return apiSuccess('',$top_users);
    }

    /**
     * Created by zyjun
     * Info:关注好友通知
     */
    public function attentionImMsg($attention_fromid,$attention_id){
        //判断用户app版本，低于1.3不发关注消息
        $version=$this->getUserAppInfo($attention_id)['app_version'];
        $version=str_replace('V','',$version);
        if(strcmp($version,'1.3')>=0){
            $from=Db::name('im_admin')->where('id',4)->value('accid');
            $to=Db::name('im_user')->where('uid',$attention_id)->value('accid');
            $form_nickname=Db::name('user')->where('id',$attention_fromid)->value('nickname');
            $content['title']=$form_nickname.'关注了你';
            $im=new Im();
            $im->sentImMsg(100,8,$from,$to,$content);
        }
    }



}
