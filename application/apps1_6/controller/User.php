<?php
namespace app\apps1_6\controller;
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
     *
     * Created by zyjun
     * Info:用户登录，设置登陆状态为1；通过是否有粒米号判断用户是否是登陆
     * 第一次登陆，【实际就是注册，因为ui上没注册页面，接口复用】创建粒米号，网易账号，个人钱包
     * 第二次登陆，【真实登陆】
     * 特殊的逻辑，如果是第一次注册，如果没有填写学校，那么不算注册成功。第二次进来要继续引导填写学校。昵称和认证可以不强制引导
     */
    public function login(){
        $mobile=input('phone');
        $code=input('code');
        if($mobile==''){
            apiError('请输入正确的手机号');
            return;
        }
        if(checkMobile($mobile)){
            apiError('请输入正确的手机号');
            return;
        }
        if($code==''){
            apiError('请输入正确的验证码');
            return;
        }
        $res=Db::name('user')->where('mobile',$mobile)->find();
        if(empty($res)){
            return apiError('请先发送验证码');
        }
        #验证码是否过期
        $time=time()-strtotime($res['code_time']);
        if($time>600){    //十分钟内有效
            return apiError('验证码已过期');
        }
        $uid=$res['id'];
        $code_error_num=$res['code_errors'];
        $code_error_time=$res['code_error_time'];
        #不管验证码是否正确，只要锁定了，就必须30分钟后重试;
        if($code_error_num>=5){
            if(strtotime($code_error_time)-time()>0){ //还在锁定期间
                $remain_time=strtotime($code_error_time)-time()/60;
                return apiError('账号已被锁定'.$remain_time.'分钟后重试');
            }else{ //超出锁定期，自动解锁
                $update['code_errors']=0;
                $update['code_error_time']=NULL;
                Db::name('user')->where('mobile',$mobile)->update($update);
            }
        }
        #验证码输入错误，记录错误次数时间
        if($code!=$res['code']){
            $num=Db::name('user')->where('mobile',$mobile)->value('code_errors');
            Db::name('user')->where('mobile',$mobile)->setInc('code_errors',1);
            Db::name('user')->where('mobile',$mobile)->update(['code_error_time'=>date('Y-m-d H:i:s',time())]);
            if($num>=5){
                return apiError('账号已被锁定,30分钟后重试');
            }else{
                return apiError('验证码错误，还有'.(5-$num).'次机会');
            }
        }
        #如果在小于5次内输入了正确的验证码，或者说每次正常登陆都清空错误记录
        $update=[];
        $update['code_errors']=0;
        $update['code_error_time']=NULL;

        if($res['status']==1){
            return apiError('此账号已被禁用！');
        }
        #登录只判断登录状态，不判断user_info_status
        if($res['status']==0){   #正常状态，但是不一定填写了学校
            $update['code']=''; //清空短信验证码
            $update['is_login']=1; //已登录
            Db::name('user')->where('mobile',$mobile)->update($update);
            #登录后返回状态
            $data['id']=$res['id'];
            $data['token']=$res['token'];
            $data['is_login']=1;
            apiSuccess('登录成功',$data);
        }
        #注册的时候判断登录状态和user_info_status，如果用户注册时强制退出，那么第二次登陆也要跳转到选择学校页面
        if($res['status']==2){   #未激活状态登陆，生成粒米号，网易IM账号，钱包，以及登陆状态
            try{
                if($res['user_info_status']==0){//第一次注册
                    Db::startTrans();
                    //创建用户钱包
                    Db::name('user_wallet')->insert(['uid'=>$uid,'money'=>0]);
                    #创建粒米号
                    $id_code=$this->setUserIdCode($uid);
                    Db::name('user_code')->where('code',$id_code)->setField('status',1);
                    #注册用户会生成token
                    $data['token']=$this->createToken($mobile);
                    $update['is_login']=0; //未登录
                    $update['user_info_status']=1; //已经生成账号信息
                    $update['token']=$data['token'];
                    if(!$mobile=='15983155261'){
                        $update['code']=''; //清空短信验证码
                    }
                    $update['nickname']=$id_code; //昵称默认等于粒米号
                    $update['back_pic']='/uploads/user/images/head_pic.png'; //个人中心头像背景图
                    $update['head_pic']='/uploads/user/images/user_head_pic.png'; //个人中心头像
                    Db::name('user')->where('mobile',$mobile)->update($update);
                    //创建网易通讯token,同时设置默认头像昵称
                    $Im=new Im();
                    $Im->regToken($uid);
                    $accid=Db::name('im_user')->where('uid',$uid)->value('accid');
                    $Im->updateImUinfo($accid,$id_code,$update['head_pic'],'','','','','','');
                    Db::commit();
                    #注册成功后返回注册成功，但登陆为未登录状态
                    $data['id']=$uid;
                    $data['user_info_status']=1; //生成了账户信息
                    $data['is_login']=0;
                    apiSuccess('请进行下一步注册',$data);
                }
                if($res['user_info_status']==1){//已经注册过，只是没选择学校
                    $data['id']=$uid;
                    $data['user_info_status']=1;
                    $data['is_login']=0;
                    $data['token']=$res['token'];
                    apiSuccess('请进行下一步注册',$data);
                }
            }catch (\Exception $e){
               return apiError('注册失败');
            }
        }
    }

    /**
     * Created by zyjun
     * Info:注册时完善用户基本信息，zxq
     * 接收参数为 nickname sex
     */
    public function perfectUserBasicInfo(){
        $id=input('id');
        $token=input('token');
        $res=$this->checkToken($id,$token);
        if($res['status']){
            return apiError($res['msg'],'',$res['code']);
        }
        $data=[];
        //昵称
        $nickname=input('nickname');
        if($nickname!=''){
            if($this->checkNickName($nickname)){
                apiError('昵称格式错误');
                return;
            }
            $data['nickname']=$nickname;
        }
        //性别
        $sex=input('sex');
        if($sex!=''){
            $data['sex']=$sex;
        }
        $res=Db::name('user')->where('id',$id)->update($data);
        if($res===false){
            apiError('保存失败');
            return;
        }
        #修改网易IM信息
        $Im=new Im();
        $accid=Db::name('im_user')->where('uid',$id)->value('accid');
        $Im->updateImUinfo($accid,$nickname,'',$sex,'','','','','');
        apiSuccess('保存成功');
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
//        $true_name=$data['true_name']=input('true_name');
//        $college=$data['college_id']=input('college');
//        $school=$data['school_id']=input('school');
//        $grade=$data['grade_id']=input('grade');
        $identity_pic=$data['identity_pic']=input('identity_pic');
        $data['identity_status']=1;  //每次提交需要重现审核
//        if($true_name==''){
//            apiError('姓名不能为空');
//            return;
//        }
//        if($college==''){
//            apiError('学校名称不能为空');
//            return;
//        }
//        if($school==''){
//            apiError('学院名称不能为空');
//            return;
//        }
//        if($grade==''){
//            apiError('年级不能为空');
//            return;
//        }
        if($identity_pic==''){
            apiError('认证图片不能为空');
            return;
        }
//        if($this->checkTrueName($true_name)){
//            apiError('请输入2-10位纯中文或纯英文姓名');
//            return;
//        }
        $res=Db::name('user')->where('id',$id)->update($data);
        if($res===false){
            apiError('保存失败');
            return;
        }
//        $Im=new Im();
//        $res=Db::name('user')->where('id',$id)->field('nickname,head_pic,sex')->find();
//        $accid=Db::name('im_user')->where('uid',$id)->value('accid');
//        if(empty($accid)){
//            $data['im_update']=1;
//            $data['im_update_msg']='未查询到IM用户';
//            $data['identity_status']=2;
//            return apiError('',$data);
//        }
//        $res2=$Im->updateImUinfo($accid,$res['nickname'],$res['head_pic'],$res['sex'],'','','','','');
//        if($res2['status']){ //网易云通讯默认头像昵称更新失败
//            $data['im_update']=1;
//            $data['im_update_msg']=$res2['msg'];
//        }
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
     * 获取省份列表
     */
    public function provinceList(){
        $redis=new \Redis();
        $redis->connect('127.0.0.1',6379);
        $redis->auth('youhong@limiapp');
        $res=unserialize($redis->get('province'));
        if(!$res){
            $res=Db::name('province')->select();
            $redis->set('province',serialize($res));
        }
        //$res=Db::name('province')->select();
        $data=[];
        if($res){
            foreach ($res as $k=>&$v){
                $data[$k]['id']=$v['provinceID'];
                $data[$k]['name']=$v['pname'];
            }
            return apiSuccess('省份列表',$data);
        }
    }

    /**
     *获取城市列表
     */
    public function cityList(){
        $redis=new \Redis();
        $redis->connect('127.0.0.1',6379);
        $redis->auth('youhong@limiapp');
        $provinceID=input('get.provinceID');
        if($provinceID==''){
            return apiError('provinceID不能为空');
        }
        //$redis->delete('city'.$provinceID);die;
        $res=unserialize($redis->get('city'.$provinceID));
        if(!$res){
            $res=Db::name('city c')
                ->join('province p','c.provinceID=p.provinceID','LEFT')
                ->where('c.provinceID',$provinceID)
                ->field('c.cityID,c.city,c.provinceID,p.pname')
                ->select();
            $redis->set('city'.$provinceID,serialize($res));
        }
//        $res=Db::name('city c')
//            ->join('province p','c.provinceID=p.provinceID','LEFT')
//            ->where('c.provinceID',$provinceID)
//            ->field('c.cityID,c.city,c.provinceID,p.pname')
//            ->select();
        $data=[];
        if($res){
            foreach ($res as $k=>&$v){
                $data[$k]['id']=$v['cityID'];
                $data[$k]['name']=$v['city'];
                $data[$k]['province']['id']=$v['provinceID'];
                $data[$k]['province']['name']=$v['pname'];
            }
            return apiSuccess('市区列表',$data);
        }
    }
    /**
     * 年龄+星座
     * date=时间戳
     */
    public function getConstellation(){
        $date=input('get.date');    //时间戳
        $date=date('Y-m-d',$date);
        $res=calcAge($date);
        if($res){
            return apiSuccess('年龄星座',$res);
        }
    }
    /**
     * Created by zyjun
     * Info:获取大学列表
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
        $data=[];
        if($res){
            foreach ($res as $k=>&$v){
                $data[$k]['id']=$v['coid'];
                $data[$k]['name']=$v['name'];
            }
        }
        apiSuccess('大学信息',$data);
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
         $Im=new Im();
         $res=Db::name('user')->where('id', $id)->field('nickname,head_pic,sex')->find();
         $accid=Db::name('im_user')->where('uid', $id)->value('accid');
         if(!empty($res)){
             $Im->updateImUinfo($accid,'',$res['head_pic'],'','','','','','');
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
        $res=$this->checkToken($id,$token);
        if($res['status']){
            return apiError($res['msg'],'',$res['code']);
        }
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

        $click_num=Db::name('video')
            ->where(['user_id'=>$id])
            ->sum('click_num');
        $num=$this->myNum($id);
        $data=[
            'nickname'=>$user_info['nickname'],
            'head_pic'=>$user_info['head_pic'],
            'signature'=>$this->userTextDecode($user_info['signature']),
            'back_pic'=>$user_info['back_pic'],
            'sex'=>$user_info['sex'],
            'is_access'=>$is_access['identity_status'],
            'attention_num'=>$num['attention_num'],
            'fans_num'=>$num['fans_num'],
            'click_num'=>$click_num
        ];

        return apiSuccess('个人中心',$data);
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
        $birthday=input('post.birthday');
        $cityID=input('post.city_id');
        $provinceID=input('post.province_id');
        $college_id=input('post.college_id');
        $data=array();
        //签名
        if($signature!=''){
            $signature = preg_replace('/\s*/', '', $signature);
            if(strlen($signature)>60){
                return apiError('不超过20个字');
            }
            $signature=$this->userTextEncode($signature);
            $data['signature']=$signature;
        }
        //昵称
        if($nickname!=''){
            if($this->checkNickName($nickname)){
                apiError('请输入2-12中文字母数字');
                return;
            }
            $data['nickname']=$nickname;
        }
        //性别
        if($sex!=''){
            $data['sex']=$sex;
        }
        //出生日期
        if($birthday!=''){
            $data['birthday']=date('Y-m-d',$birthday);
        }
        //城市
        if($cityID!=''&&$provinceID!=''){
            $data['city_id']=$cityID;
            $data['province_id']=$provinceID;
        }
        //学校
        if($college_id!=''){
            $data['college_id']=$college_id;
        }
        $res = Db::name('user')->where('id', $id)->update($data);
        if ($res===false) {
            return apiError('修改失败');
        }else{
            //更新网易云信息
            $Im=new Im();
            $res=Db::name('user')->where('id', $id)->field('nickname,head_pic,sex')->find();
            $accid=Db::name('im_user')->where('uid', $id)->value('accid');
            if(!empty($res)){
                $Im->updateImUinfo($accid,$res['nickname'],$res['head_pic'],$res['sex'],'','','','','');
            }
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
//        $res=$this->isAccess($id);
//        if($res['identity_status']!=2){
//            return apiError($res['msg'],$res['identity_status']);
//        }
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
//        $res=$this->isAccess($id);
//        if($res['identity_status']!=2){
//            return apiError($res['msg'],$res['identity_status']);
//        }
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
//        $res=$this->isAccess($uid);
//        if($res['identity_status']!=2){
//            return apiError($res['msg'],$res['identity_status']);
//        }
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
//        $res=$this->isAccess($id);
//        if($res['identity_status']!=2){
//            return apiError($res['msg'],$res['identity_status']);
//        }
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
//        $res=$this->isAccess($id);
//        if($res['identity_status']!=2){
//            return apiError($res['msg'],$res['identity_status']);
//        }
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
//        $res=$this->isAccess($id);
//        if($res['identity_status']!=2){
//            return apiError($res['msg'],$res['identity_status']);
//        }
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
//        $res=$this->isAccess($id);
//        if($res['identity_status']!=2){
//            return apiError($res['msg'],$res['identity_status']);
//        }
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
                    //关注通知消息   默认无关注来源  不设置type和type_id
                    $notice['from']=$id;
                    $notice['to']=$attention_id;
                    $msg_id=$this->totalNotice($notice);
                    $this->sentImMsgs($id,$attention_id,NULL,$msg_id);
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
                //关注通知消息   默认无关注来源  不设置type和type_id
                $notice['from']=$id;
                $notice['to']=$attention_id;
                $msg_id=$this->totalNotice($notice);
                $this->sentImMsgs($id,$attention_id,NULL,$msg_id);
                return apiSuccess('关注成功',$d);
            }
        }
    }
    /**
     * 关注列表
     * id token page
     */
    public function myAttentionList(){
        $id=input('get.id');
        $token=input('get.token');
        $name=input('get.name');
        $page=input('get.page');
        if($page<1||$page==''){
            return apiError('页码错误');
        }
        if($name){
            $fans_users = array();
            $list = Db::name('user_relation')
                ->where(['user_id' => $id, 'is_cancel' => 0])
                ->column('attention_id');
            if ($list) {
                $res=Db::name('user u')
                    ->join('college col','col.coid=u.college_id','LEFT')
                    ->join('city c','c.cityID=u.city_id','LEFT')
                    ->join('province p','c.provinceID=p.provinceID','LEFT')
                    ->field('u.id user_id,u.id_code,u.city_id,u.province_id,p.pname,u.user_info_status,u.send_status,u.clickVL_status,u.fansL_status,u.attentionL_status,u.nickname,sex,birthday,signature,head_pic,back_pic,col.name college,col.coid coid,c.city')
                    ->where(['u.id'=>['in',$list],'nickname'=>['like','%'.$name.'%']])
                    ->select();
                foreach($res as $key=>&$v){
                    if($v['head_pic']){
                        $v['head_pic']=$this->addApiUrl($v['head_pic']);
                    }
                    if($v['back_pic']){
                        $v['back_pic']=$this->addApiUrl($v['back_pic']);
                    }else{
                        $v['back_pic']=$this->addApiUrl('/uploads/user/images/head_pic.png');
                    }
                    if($v['signature']){
                        $v['signature']=$this->userTextDecode($v['signature']);
                    }
                    $my_num = $this->myNum($v['user_id']);
                    $fans_users[$key]['user_id'] = $v['user_id'];
                    $fans_users[$key]['nickname'] = $v['nickname'];
                    $fans_users[$key]['head_pic'] = $v['head_pic'];
                    $fans_users[$key]['college'] = $v['college'];
                    $fans_users[$key]['fans_num'] = $my_num['fans_num'];
                    $res1 = Db::name('user_relation')->where(['user_id' => $id, 'attention_id' => $v['user_id'], 'is_cancel' => 0])->find();
                    $res2 = Db::name('user_relation')->where(['user_id' => $v['user_id'], 'attention_id' => $id, 'is_cancel' => 0])->find();
                    $fans_users[$key]['is_attention'] = 0; //未关注
                    if ($res1) {
                        $fans_users[$key]['is_attention'] = 1; //已关注
                    }
                    if ($res1 && $res2) {
                        $fans_users[$key]['is_attention'] = 2; //互关注
                    }

                }
                return apiSuccess('关注搜索列表', $fans_users);

            }
            return apiSuccess('关注搜索列表', $fans_users);
        }
        $attention_users=array();
            $list = Db::name('user_relation')
                ->where(['user_id' => $id, 'is_cancel' => 0])
                ->order('create_time desc')
                ->page($page, 10)
                ->column('attention_id');
            if ($list) {
                foreach ($list as $key => &$v) {
                    $user_info = $this->userInfo($v);
                    $my_num = $this->myNum($v);
                    $attention_users[$key]['user_id'] = $user_info['user_id'];
                    $attention_users[$key]['nickname'] = $user_info['nickname'];
                    $attention_users[$key]['head_pic'] = $user_info['head_pic'];
                    $attention_users[$key]['college'] = $user_info['college'];
                    $attention_users[$key]['fans_num'] = $my_num['fans_num'];
                    $res1 = Db::name('user_relation')->where(['user_id' => $id, 'attention_id' => $v, 'is_cancel' => 0])->find();
                    $res2 = Db::name('user_relation')->where(['user_id' => $v, 'attention_id' => $id, 'is_cancel' => 0])->find();
                    $attention_users[$key]['is_attention'] = 0;
                    if ($res1) {
                        $attention_users[$key]['is_attention'] = 1; //已关注
                    }
                    if ($res1 && $res2) {
                        $attention_users[$key]['is_attention'] = 2; //互关注
                    }
                }
            }
            return apiSuccess('关注列表',$attention_users);
    }
    /**
     * 粉丝列表
     * id token page
     */
    public function myFansList()
    {
        $id = input('get.id');

        $token = input('get.token');
        $page = input('get.page');
        if ($page < 1 || $page == '') {
            return apiError('页码错误');
        }

        $fans_users = array();
            $list = Db::name('user_relation')
                ->where(['attention_id' => $id, 'is_cancel' => 0])
                ->order('create_time desc')
                ->page($page, 10)
                ->column('user_id');
            if ($list) {
                foreach ($list as $key => &$v) {
                    $user_info = $this->userInfo($v);
                    $my_num = $this->myNum($v);
                    $fans_users[$key]['user_id'] = $user_info['user_id'];
                    $fans_users[$key]['nickname'] = $user_info['nickname'];
                    $fans_users[$key]['head_pic'] = $user_info['head_pic'];
                    $fans_users[$key]['college'] = $user_info['college'];
                    $fans_users[$key]['fans_num'] = $my_num['fans_num'];
                    $res1 = Db::name('user_relation')->where(['user_id' => $id, 'attention_id' => $v, 'is_cancel' => 0])->find();
                    $res2 = Db::name('user_relation')->where(['user_id' => $v, 'attention_id' => $id, 'is_cancel' => 0])->find();
                    $fans_users[$key]['is_attention'] = 0; //未关注
                    if ($res1) {
                        $fans_users[$key]['is_attention'] = 1; //已关注
                    }
                    if ($res1 && $res2) {
                        $fans_users[$key]['is_attention'] = 2; //互关注
                    }
                }
            }
            return apiSuccess('粉丝列表', $fans_users);
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
     * Info:关注好友通知  废弃
     */
//    public function attentionImMsg($attention_fromid,$attention_id){
//        //判断用户app版本，低于1.3不发关注消息   1.6接口采用和关注，点赞相同的接口  这里屏蔽用新接口
////        $version=$this->getUserAppInfo($attention_id)['app_version'];
////        $version=str_replace('V','',$version);
////        if(strcmp($version,'1.3')>=0){
////            $from=Db::name('im_admin')->where('id',4)->value('accid');
////            $to=Db::name('im_user')->where('uid',$attention_id)->value('accid');
////            $form_nickname=Db::name('user')->where('id',$attention_fromid)->value('nickname');
////            $content['title']=$form_nickname.'关注了你';
////            $im=new Im();
////            $im->sentImMsg(100,8,$from,$to,$content);
////        }
//
//    }








    /**
     * Created by zyjun
     * Info:预设用户粒米号
     */
    public function createUserIdCode(){
        for($i=999999;$i>0;$i--){
            $code1=mt_rand(1,9);
            $code2=mt_rand(0,9);
            $code3=mt_rand(0,9);
            $code4=mt_rand(0,9);
            $code5=mt_rand(0,9);
            $code6=mt_rand(0,9);
            $code=(int)($code1.$code2.$code3.$code4.$code5.$code6);
            $code_arr=[$code1,$code2,$code3,$code4,$code5,$code6];
            #去掉特殊号码
            $protect=[123456,234567,345678,456789,987654,876543,765432,654321,112233,223344,334455,445566,556677,667788,778899,998877,887766,776655,665544,554433,443322,332211];
            if(in_array($code,$protect)){
                continue;
            }

            #去掉3位连号
            if((($code_arr[0]==$code_arr[1])&&($code_arr[1]==$code_arr[2]))||(($code_arr[1]==$code_arr[2])&&($code_arr[2]==$code_arr[3]))||(($code_arr[2]==$code_arr[3])&&($code_arr[3]==$code_arr[4]))||(($code_arr[3]==$code_arr[4])&&($code_arr[4]==$code_arr[5]))){
                continue;
            }
            $data['code']=$code;
            $res=Db::name('user_code')->where('code',$code)->find();
            if(empty($res)){
                Db::name('user_code')->insert($data);
            }
        }
    }

    /**
     * Created by zyjun
     * Info:选择学校，只有选择了学校才算注册完成，status由未激活修改为激活状态
     */
    public function setSchool(){
         $uid=input('post.id');
         $token=input('post.token');
         $college_id=input('post.college_id');
        $res=$this->checkToken2($uid,$token);
        if($res['status']){
            return apiError($res['msg'],'',$res['code']);
        }
        $update['college_id']=$college_id;
        $update['status']=0; //已激活正式注册
        $update['is_login']=1;
        $update['user_info_status']=2;
        $res=Db::name('user')->where('id',$uid)->update($update);
        if($res===false){
            return apiError('保存失败');
        }
        $res=Db::name('user')->where('id',$uid)->field('status,is_login,token,id')->find();
        apiSuccess('保存成功',$res);
    }

    /**
     * 隐私设置
     */
    public function privacyAction(){
        $id=input('post.id');
        $token=input('post.token');
        $type=input('post.type');// send,click,fans,attention
        $value=input('post.value'); // 0 关  1 开
        $res = $this->checkToken($id, $token);
        if ($res['status']) {
            return apiError($res['msg'],'',$res['code']);
        }
        $data=[];
        if($type==''){
            return apiError('type不能为空');
        }
        if($type=='send'){
            $data['send_status']=$value;
        }
        if($type=='click'){
            $data['clickVL_status']=$value;
        }
        if($type=='fans'){
            $data['fansL_status']=$value;
        }
        if($type=='attention'){
            $data['attentionL_status']=$value;
        }
        $res=Db::name('user')->where('id',$id)->update($data);
        if($res===false){
            return apiError('设置失败');
        }
        return apiSuccess('设置成功');
    }
    /**
     * 隐私状态表(废弃)
     */
//    public function privacyList(){
//        $id=input('get.id');
//        $token=input('get.token');
//        $res = $this->checkToken($id, $token);
//        if ($res['status']) {
//            return apiError($res['msg'],'',$res['code']);
//        }
//        $res=Db::name('user')->where('id',$id)
//            ->field('send_status,clickVL_status,fansL_status,attentionL_status')
//            ->find();
//        if($res){
//            $data=[
//                'send'=>$res['send_status'],
//                'click'=>$res['clickVL_status'],
//                'fans'=>$res['fansL_status'],
//                'attention'=>$res['attentionL_status']
//            ];
//            return apiSuccess('隐私状态列表',$data);
//        }
//    }
    /**
     * Created by zyjun
     * Info:用户退出登录
     */
    public function loginOut(){
        $uid=input('post.id');
        $token=input('post.token');
        $res=$this->checkToken($uid,$token);
        if($res['status']){
            return apiError($res['msg'],'',$res['code']);
        }
        #退出清空如下信息
        $update['is_login']=0;
        $update['token']='';
        $update['code']='';
        $res=Db::name('user')->where('id',$uid)->update($update);
        if($res===false){
            return apiError('退出失败');
        }
        return apiSuccess('退出成功');
    }

}
