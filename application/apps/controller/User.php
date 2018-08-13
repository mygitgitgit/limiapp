<?php
namespace app\apps\controller;
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
        if($code==''){
            apiError('验证码不能为空');
            return;
        }
        if(checkCode($code)){
            apiError('验证码格式错误');
            return;
        }
        $res=Db::name('user')
            ->where('mobile',$mobile)
            ->field('id,code,code_time,identity_time,status,user_info_status,identity_status,code_errors num,code_error_time error_time,pay_password,pay_password_status')
            ->find();
        if(empty($res)){
            apiError('请先发送验证码！');
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
        //其他方面禁用
        if($res['status']=='1'){
            apiError('此账号已被禁用！');
            return;
        }
        $time=time()-strtotime($res['code_time']);
        if($time>600){
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
     *
     */
    public function perfectUserBasicInfo(){
        $id=input('id');
        $token=input('token');
        $res=$this->checkToken($id,$token);
        if($res['status']){
            return apiError($res['msg'],'',$res['code']);
        }
        $true_name=$data['true_name']=input('true_name');
        $data['nickname']=$true_name;
        $sex=$data['sex']=input('sex');
        if($true_name==''){
            apiError('真实姓名不能为空');
            return;
        }
        if($sex==''){
            apiError('性别不能为空');
            return;
        }
        if($this->checkTrueName($true_name)){
            apiError('请输入2-10位纯中文或纯英文姓名');
            return;
        }
//        $res=Db::name('user')->where('nickname',$true_name)->find();
//        if($res){
//            return apiError('App版本与新版本存在(真实姓名,昵称)冲突，请升级到最新版本使用');
//        }
        $res=Db::name('user')->where('id',$id)->update($data);
        if($res===false){
            apiError('保存失败');
            return;
        }
        $res=Db::name('user')->where('id',$id)->field('sex,true_name,head_pic')->find();
        if($res['sex']!==''&&$res['true_name']!==''){
            Db::name('user')->where('id',$id)->update(['user_info_status'=>'1']); //完成了头像性别真实姓名
        }
        $res=Db::name('user')->where('id',$id)->field('id,user_info_status')->find();
        apiSuccess('保存成功',$res);
    }

    /**
     * Created by zyjun
     * Info:注册时完善大学，学院，年级信息，
     *
     */
    public function perfectUserInfo(){
        $id=input('id');
        $token=input('token');
        $res=$this->checkToken($id,$token);
        if($res['status']){
            return apiError($res['msg'],'',$res['code']);
        }
        $college=$data['college_id']=input('college');
        $school=$data['school_id']=input('school');
        $grade=$data['grade_id']=input('grade');
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
        //判断是否上传头像
        $data['user_info_status']=2;  //完善了所有信息
        $data['identity_status']=1;   //状态修改了认证中
        $data['identity_time']=date('Y-m-d H:i:s');
        $res=Db::name('user')->where('id',$id)->update($data);
        if($res===false){
            apiError('保存失败');
            return;
        }
         apiSuccess('保存成功','');
    }


    /**
     * Created by zyjun
     * Info:个人中心完善身份认证信息，
     *
     */
    public function centerPerfectUserInfo(){
        $id=input('id');
        $token=input('token');
        $res=$this->checkToken($id,$token);
        if($res['status']){
            return apiError($res['msg'],'',$res['code']);
        }
        $true_name=$data['true_name']=input('true_name');
        $sex=$data['sex']=input('sex');
        $college=$data['college_id']=input('college');
        $school=$data['school_id']=input('school');
        $grade=$data['grade_id']=input('grade');
        if($true_name==''){
            apiError('真实姓名不能为空');
            return;
        }
        if($sex==''){
            apiError('性别不能为空');
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
        if($this->checkTrueName($true_name)){
            apiError('请输入2-10位纯中文或纯英文姓名');
            return;
        }
        $data['user_info_status']='2';  //完善了所有信息
        $data['identity_status']='1';   //状态修改了认证中
        $data['identity_time']=date('Y-m-d H:i:s');
        $data['nickname']=$true_name;
        $res=Db::name('user')->where('id',$id)->update($data);
        if($res===false){
            apiError('保存失败');
            return;
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
        $province_id=input('get.provinceID');
        if(empty($province_id)){
            apiError('provinceID参数错误');
            return;
        }
        $res=$this->getCollegeList($province_id);
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
        $res=$this->isSexyImg($image);
        if($res['status']){//包含色情或者性感图片
            $sex_status=$res['code'];
            if($sex_status==0){
                return apiError('图片可能包含敏感信息，请重新上传');
            }
        }

         if($type=='back'){
             $data['back_pic']=$image;
         }elseif($type=='head'){
             $data['head_pic']=$image;
         }
         $res=Db::name('user')->where('id',$id)->update($data);
         if($res===false){
             return apiError('保存失败');
         }
         //更新网易云头像
        $res=$this->isAccess($id);
         if($res['identity_status']==2){
             $Im=new Im();
             $res=Db::name('user')->where('id', $id)->field('true_name,head_pic,sex')->find();
             $accid=Db::name('im_user')->where('uid', $id)->value('accid');
             if(!empty($res)){
                 $Im->updateImUinfo($accid,'',$res['head_pic'],'','','','','','');
             }
         }
        apiSuccess('保存成功','');
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
        $id=input('id');
        $token=input('token');
        $res=$this->checkToken($id,$token);
        if($res['status']){
            return apiError($res['msg'],'',$res['code']);
        }
        //用户的基本信息
        $user_info=$this->userInfo($id);
        //用户认证信息
        $is_access=$this->isAccess($id);
        //现金
        $money=Db::name('user_wallet')->where('uid',$id)->value('money');
        if($money==null){
            $money=0;
        }

        //我的动态
        $my_action=Db::name('action')
            ->where('user_id',$id)
            ->where('delete_time','null')
            ->field('count(*) action_num')
            ->select();
        //用户反馈

        //关于粒米
        $data=[
            'user_info'=>$user_info,
            'is_access'=>$is_access,
            'money'=>$money,
            'my_action'=>$my_action[0]['action_num'],
            'feedback'=>'',
            'with_limi'=>''
        ];
        //判断是否需要升级

//        $version=input('version');
//        $device=input('device');
//
//        $sys= Db::name('sys_set')->where('key',3)->value('data');
//        $sys= json_decode($sys,true);
//        $android=end($sys['android']);dump($android);
//        $ios=end($sys['ios']);
//        if($device=='android'){
//            if($version!=$android['version']){
//                $data['with_limi']=1;
//            }else{
//                $data['with_limi']=0;
//            }
//        }elseif($device=='ios'){
//            if($version!=$ios['version']){
//                $data['with_limi']=1;
//            }else{
//                $data['with_limi']=0;
//            }
//        }


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
        $user_info=$this->userInfo($id);
        return apiSuccess('信息列表显示',$user_info);
    }
    /**
     * 编辑个人信息
     */
    public function editUser()
    {
        $id = input('id');
        $token = input('token');
        $res = $this->checkToken($id, $token);
        if ($res['status']) {
            return apiError($res['msg'],'',$res['code']);

        }
        //请求修改哪个字段？？
        $field = input('field');
        $value = input('value');
        if($field==''){
            apiError('修改字段不能为空');
            return;
        }
        if($value==''){
            //如果没有填写默认返回 不修改
            apiSuccess('','');
            return;
        }
        if ($field == 'true_name') {
            if($this->checkTrueName($value)){
                apiError('请输入2-10位纯中文或纯英文姓名');
                return;
            }
//            $res=Db::name('user')->where('nickname',$value)->find();
//            if($res){
//                return apiError('App版本与新版本存在(真实姓名,昵称)冲突，请升级到最新版本使用');
//            }
            $res = Db::name('user')->where('id', $id)->update(['true_name' => $value,'nickname' => $value]);
            $user_info = $this->userInfo($id);
            if ($res) {
                apiSuccess('修改成功', $user_info['true_name']);

            }
        } elseif ($field == 'sex') {
            $res = Db::name('user')->where('id', $id)->update(['sex' => $value]);
            $user_info = $this->userInfo($id);
            if ($res) {
                apiSuccess('修改成功', $user_info['sex']);
            }
        }
        //更新网易云信息
        $Im=new Im();
        $res=Db::name('user')->where('id', $id)->field('true_name,head_pic,sex')->find();
        $accid=Db::name('im_user')->where('uid', $id)->value('accid');
        if(!empty($res)){
            $Im->updateImUinfo($accid,$res['true_name'],$res['head_pic'],$res['sex'],'','','','','');
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
                    return apiError('没有修改任何数据密码重置失败');
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

    /**
     * Created by zyjun
     * Info:我的钱包
     */
    public function mycash(){
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


    /**
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

    /**
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
            foreach ($res as $v){

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
}
