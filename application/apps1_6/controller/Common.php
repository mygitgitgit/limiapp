<?php
namespace app\apps1_6\controller;
use app\apps1_6\controller\Auth;
use think\Controller;
use think\Request;
use think\Db;

class Common extends Controller
{
    public function _initialize(){

    }

    /**
     * Created by zyjun
     * Info:根据用户手机号+随机数产生token，用户手机登陆后的访问token
     * $param:手机号
     * return:
     */
    public function createToken($param){
         $password=sha1(MD5($param.time().rand(1,9999)));
         return $password;
    }

    /**
     * Created by zyjun
     * Info:生成支付密码
     * $param:用户密码
     * return:
     */
    public function createPayPassword($param){
        $password=sha1(MD5($param.'limi@8268'));
        return $password;
    }

    /**
     * Created by zyjun
     * Info:支付订单编号，充值，提现产生，也用于用户回调通知,随机产生后缀
     */
    public function createBusinessNo(){
        $out_biz_no=date('YmdHis',time()).rand(1000,9999);
        return $out_biz_no;
    }

    /**
     * Created by zyjun
     * Info:支付订单编号，充值，提现产生，也用于用户回调通知,随机产生后缀
     */
    public function createShopOrderNo(){
        $out_biz_no=date('YmdHis',time()).rand(10000,99999);
        return $out_biz_no;
    }

    /**
     * Created by zyjun
     * Info:阿里云内容鉴别任务id
     */
    public function createAliGreenDataid(){
        $dataid=date('YmdHis',time()).rand(100,999);
        return $dataid;
    }

    /**
     * Created by zyjun
     * Info:验证用户是否已经注册过
     */
    public function isReg($param){
        $res=Db::name('user')->where('mobile',$param)->lock(true)->find();
        if($res){
            $data['msg']='手机号已经注册，请登录！';
            $data['status']=1;
            return $data;
        }
        $data['msg']='手机号未注册';
        $data['status']=0;
        return $data;
    }

    /**
     * Created by zyjun
     * Info:判断用户状态，每次请求都返回
     */
    public function userStatus($param){
        $res=Db::name('user')->where('mobile',$param)->find();

    }

    /**
     * Created by zyjun
     * Info:验证用户token
     */
    public function checkToken($id,$token){
        $login_error_code=1000;
        if(empty($id)){
            $data['msg']='error:用户ID异常';
            $data['status']=1;
            $data['code']=$login_error_code;
            return $data;
        }
        if(empty($token)){
            $data['msg']='error:用户Token异常';
            $data['status']=1;
            $data['code']=$login_error_code;
            return $data;
        }
        if($this->checkInt($id,'','')){
            $data['msg']='用户ID非法';
            $data['status']=1;
            $data['code']=$login_error_code;
            return $data;
        }
        if($this->checkUserToken($token)){
            $data['msg']='用户TOKEN非法';
            $data['status']=1;
            $data['code']=$login_error_code;
            return $data;
        }
        $user_token=Db::name('user')->where('id',$id)->value('token');
        if(empty($user_token)){
            $data['msg']='用户TOKEN信息异常';
            $data['status']=1;
            $data['code']=$login_error_code;
            return $data;
        }

        if($user_token!=$token){
            $data['msg']='用户TOKEN验证失败！';
            $data['status']=1;
            $data['code']=$login_error_code;
            return $data;
        }
        #临时添加检测是否登录
        $is_login=Db::name('user')->where('id',$id)->value('is_login');
        if(empty($is_login)){
            $data['msg']='请先登录！';
            $data['status']=1;
            $data['code']=$login_error_code;
            return $data;
        }
        $data['msg']='用户身份验证成功！';
        $data['status']=0;
        return $data;
    }

    /**
     * Created by zyjun
     * Info:验证用户token，选择学校的时候用的
     */
    public function checkToken2($id,$token){
        $login_error_code=1000;
        if(empty($id)){
            $data['msg']='error:用户ID异常';
            $data['status']=1;
            $data['code']=$login_error_code;
            return $data;
        }
        if(empty($token)){
            $data['msg']='error:用户Token异常';
            $data['status']=1;
            $data['code']=$login_error_code;
            return $data;
        }
        if($this->checkInt($id,'','')){
            $data['msg']='用户ID非法';
            $data['status']=1;
            $data['code']=$login_error_code;
            return $data;
        }
        if($this->checkUserToken($token)){
            $data['msg']='用户TOKEN非法';
            $data['status']=1;
            $data['code']=$login_error_code;
            return $data;
        }
        $user_token=Db::name('user')->where('id',$id)->value('token');
        if(empty($user_token)){
            $data['msg']='用户信息异常';
            $data['status']=1;
            $data['code']=$login_error_code;
            return $data;
        }

        if($user_token!=$token){
            $data['msg']='用户身份验证失败！';
            $data['status']=1;
            $data['code']=$login_error_code;
            return $data;
        }
        $data['msg']='用户身份验证成功！';
        $data['status']=0;
        return $data;
    }


    /**
     * Created by zyjun
     * Info:获取大学列表
     */
    public function getCollegeList($param){
        $coids=[22001,22002,22003,22004,22005,22006,22007,22008,22009,22010,22011,22013,22014,22015,22016,22017,22033,22037,22073,22081,22086,22087,22088,22094,22095,22096,22097,22098,22099,22100];
        $res=Db::name('college')->where('provinceID',$param)->where('coid','in',$coids)->select();//'510000'
        return $res;
    }

    /**
     * Created by zyjun
     * Info:获取学院列表
     */
    public function getSchoolList($param){
        $res=Db::name('school')->where('collegeID',$param)->select();
        return $res;
    }

    /**
     * Created by zyjun
     * Info:获取年级列表
     */
    public function getGradeList(){
        $res=Db::name('grade')->select();
        return $res;
    }

    /**
     * Created by zyjun
     * Info:获取某个人的大学名称
     * $param:用户id
     */
    public function getUserCollege($param){
        $college_id=Db::name('user')->where('id',$param)->value('college_id');
        if(empty($college_id)){
            return '';
        }
        $college=Db::name('college')->where('coid',$college_id)->field('coid,name,provinceID')->find();
        if(empty($college)){
            return '';
        }
        return $college;
    }

    /**
     * Created by zyjun
     * Info:获取获取某个人的学院名称
     *  $param:用户id
     */
    public function getUserSchool($param){
        $school_id=Db::name('user')->where('id',$param)->value('school_id');
        if(empty($school_id)){
            return '';
        }
        $school=Db::name('school')->where('scid',$school_id)->field('scid,name,collegeID')->find();
        if(empty($school)){
            return '';
        }
        return $school;
    }

    /**
     * Created by zyjun
     * Info:获取获取年级
     *  $param:用户id
     */
    public function getUserGrade($param){
        $grade_id=Db::name('user')->where('id',$param)->value('grade_id');
        if(empty($grade_id)){
            return '';
        }
        $grade=Db::name('grade')->where('id',$grade_id)->field('id,name')->find();
        if(empty($grade)){
            return '';
        }
        return $grade;
    }

/*****************正则验证函数开始********************/
    /**
     * Created by zyjun
     * Info:中文或者英文  10个汉字
     */
    public function checkTrueName($param){
        if((preg_match('/^[\x{4e00}-\x{9fa5}]{2,10}$/u', $param))||(preg_match('/^[A-Za-z\\s]{2,10}$/u', $param))){
            return false;
        } else {
            return true;
        }
    }
    /**
     * Created by zyjun
     * Info:昵称检测  中文字母数字空格有一个空格
     */
    public function checkNickName($param){
        if(!preg_match('/^[\x{4e00}-\x{9fa5}A-Za-z0-9\\s]{1,20}$/u', $param)||preg_match('/\s{2,}/', $param)){
            return true;
        } else {
            return false;
        }
    }
    /**
     * Created by zyjun
     * Info:验证输入的红包
     */
    public function checkRedPacket($param){

        if(preg_match('/^([1-9]\d*|0)(\.\d{1,2})?$/', $param)){
            return false;
        } else {
            return true;
        }
    }

    /**
     * Created by zyjun
     * Info:检测正整数 自定义长度
     */
    public function checkInt($param,$start,$end){
        if($start&&$end){
          $reg='/^\d{'.$start.','.$end.'}$/';
        }else{
          $reg='/^\d*$/';
        }
        if(!preg_match($reg, $param)){
            return true;
        } else {
            return false;
        }
    }

    /**
 * Created by zyjun
 * Info:检查密文是否合法，用户token数字,字母40位
 */
    public function checkUserToken($param){
        if(preg_match('/^[a-z0-9]{40}$/', $param)){
            return false;
        } else {
            return true;
        }
    }

    /**
     * Created by zyjun
     * Info:检查密文是否合法，红包token数字,字母32位
     */
    public function checkRedToken($param){
        if(preg_match('/^[a-z0-9]{32}$/',$param)){
            return false;
        } else {
            return true;
        }
    }

    /**
     * Created by zyjun
     * Info:检查点播视频ID
     */
    public function checkVideo($param){
        if(preg_match('/^[a-z0-9]{32}$/',$param)){
            return false;
        } else {
            return true;
        }
    }

    /**
     * Created by zyjun
     * Info:验证支付密码，6位纯数字
     */
  public  function checkPayCode($param){
        if (!preg_match('/^[0-9]{6}$/',$param)){
            return true;
        }else{
            return false;
        }
    }

        /**
     * Created by zyjun
     * Info:验证短视频封面url
     */
  public  function checkVideoCover($param){
        if (!preg_match('/^(http:\/\/video.youhongtech.com\/).*$/',$param)){
            return true;
        }else{
            return false;
        }
    }


    /**
     * Created by zyjun
     * Info:验证短视频封面url
     */
    public  function checkGreenDataid($param){
        if (!preg_match('/^[0-9]{17}$/',$param)){
            return true;
        }else{
            return false;
        }
    }



    /**
     * Created by zyjun
     * Info:邮箱验证
     */
    public function checkEmail($param){
        if (!preg_match('/^[a-zA-Z0-9_-]+@[a-zA-Z0-9_-]+(\.[a-zA-Z0-9_-]+)+$/',$param)){
            return true;
        }else{
            return false;
        }
    }

    /**
     * Created by zyjun
     * Info:判断账号类型 邮箱，手机号
     */
    public function checkAccountType($account){
        if(preg_match('/^[0-9]*$/',$account)){
            return 1;
        }
        if(strpos('XX'.$account,'@')>0){
            return 2;
        }
        if(!preg_match('/^[0-9]*$/',$account)&&strpos('XX'.$account,'@')==false){
            return 3;
        }
    }

    /**
     * Created by zyjun
     * Info:验证订单号格式 18位数字
     */
    public function checkBusinessNo($param){
        if (!preg_match('/^[0-9]{18}$/',$param)){
            return true;
        }else{
            return false;
        }
    }

    /**
     * Created by zyjun
     * Info:验证订单号格式 19位数字
     */
    public function checkShopOrderNo($param){
        if (!preg_match('/^[0-9]{19}$/',$param)){
            return true;
        }else{
            return false;
        }
    }

    public function checkStrTime($dateTime){
         if(!preg_match("/^d{4}-d{2}-d{2} d{2}:d{2}:d{2}$/s",$dateTime)){
             return true;
         }else{
             return false;
         }
    }

/*****************正则验证函数结束********************/

/******************写入日志文件函数******************************/
    /**
     * Created by zyjun
     * Info:$id用户ID,$red_packed_id:红包ID,$content:错误内容，
     */
    public function redPacketLog($uid,$content){
        Db::name('redpacket_log')->insert(['uid'=>$uid,'content'=>json_encode($content),'create_time'=>date('Y-m-d H:i:s',time())]);
    }

    /**
     * Created by zyjun
     * Info:linux定时计划日志
     */
    public function taskLog($content){
        Db::name('task_log')->insert(['data'=>json_encode($content),'create_time'=>date('Y-m-d H:i:s',time())]);
    }

    /**
     * Created by zyjun
     * Info:记录红包流水
     * $uid:用户id  $rid:
     */
    public function redPacketRecordDetail($uid,$rid,$stype,$sid,$status,$money,$wallet,$type,$des){
        Db::name('wallet_record')->insert(['uid'=>$uid,'rid'=>$rid,'stype'=>$stype,'sid'=>$sid,'status'=>$status,'money'=>$money,'wallet'=>$wallet,'type'=>$type,'des'=>$des,'time'=>date('Y-m-d H:i:s')]);
        $this->walletRecordToatl($uid,$type,$money,$stype,$status);
    }

    /**
     * Created by zyjun
     * Info:记录财务流水
     * $uid:用户id  $rid:
     */
    public function walletRecordDetail($uid,$rid,$stype,$sid,$status,$money,$wallet,$type,$des){
        Db::name('wallet_record')->insert(['uid'=>$uid,'rid'=>$rid,'stype'=>$stype,'sid'=>$sid,'status'=>$status,'money'=>$money,'wallet'=>$wallet,'type'=>$type,'des'=>$des,'time'=>date('Y-m-d H:i:s')]);
        $this->walletRecordToatl($uid,$type,$money,$stype,$status);
    }

    /**
     * Created by zyjun
     * Info:临时放在这里 设置后台系统变量
     */
    public function sys_set($uid,$content,$des){
        $time=date('Y-m-d H:i:s',time());
        Db::name('sys_set')->insert(['uid'=>$uid,'data'=>json_encode($content),'des'=>$des,'time'=>$time]);
    }

    /**
     * Created by zyjun
     * Info:记录支付充值回调详细参数
     * order_out_biz_no内部订单号    $type交易类型  1：支付宝  2：微信  3：银行卡     $content 回调数组内容   $des 描述
     */
    public function recharge_log($type,$order_out_biz_no,$content,$des){
        $data['order_type']=$type;
        $data['order_out_biz_no']=$order_out_biz_no;
        $data['content']=json_encode($content);
        $data['des']=$des;
        $data['create_time']=date('Y-m-d H:i:s',time());
        Db::name('recharge_log')->insert($data);
    }

    /**
     * Created by zyjun
     * Info:记录在线支付，商品购买详细参数
     * order_out_biz_no内部订单号    $type交易类型  1：支付宝  2：微信  3：银行卡     $content 回调数组内容   $des 描述
     */
    public function orderPayLog($type,$order_out_biz_no,$content,$des){
        $data['order_type']=$type;
        $data['order_out_biz_no']=$order_out_biz_no;
        $data['content']=json_encode($content);
        $data['des']=$des;
        $data['create_time']=date('Y-m-d H:i:s',time());
        Db::name('order_pay_log')->insert($data);
    }
/*************************************************************/
    /**
     * 认证是否通过
     */
    public function isAccess($id)
    {
        //查询数据库 提交审核时间
        $res=Db::name('user')->where('id',$id)->field('user_info_status,identity_status,identity_time')->find();
        $identity_status=$res['identity_status'];
        $user_info_status=$res['user_info_status'];
        if($identity_status==0){
            $data['msg']='未认证身份信息';
            $data['identity_status']=0;
            $data['status']=1;
            return $data;
        }
        if($identity_status==1){
            $data['msg']='等待审核通过';
            $data['identity_status']=1;
            $data['status']=1;
            return $data;
        }
        if($identity_status==2){
            $data['msg']='身份认证成功';
            $data['identity_status']=2;
            $data['status']=0;
            return $data;
        }
        if($identity_status==3){
            $data['msg']='身份认证失败';
            $data['identity_status']=3;
            $data['status']=1;
            return $data;
        }
        $data['msg']='认证信息异常';
        return $data;
    }

    /**
     * Created by zyjun
     * Info:接收上传文件  图片,视频,其他  接收单个值
     * $file_type:字符串 参数image,video,或者'image,video'
     */
    public function uploadFiles($file,$file_type){
        if(empty($file)){
            $data['msg']='上传文件不能为空';
            $data['status']=1;
            return $data;
        }
        //允许上传的类型
        switch ($file_type){
            case 'image':$allow_type=array('image/jpg','image/gif','image/jpeg','image/png');break;
            case 'video':$allow_type=array('video/swf','video/flv','video/avi','video/mp4');break;
            default :$allow_type=array('image/jpg','image/gif','image/jpeg','image/png','video/swf','video/flv','video/avi','video/mp4');
        }
        $type=$file['type'];
        if(!in_array($type,$allow_type)){
            $data['msg']='上传文件格式错误';
            $data['status']=1;
            return $data;
        }
        //限制上传大小
        $limit_size=0;
        if(stripos($type,'video')!==false){ //视频上传
            $file_path_name="videos";
            $limit_size=300;  //限制上传大小  暂取默认值 300M
        }
        if(stripos($type,'image')!==false){ //图片上传
            $file_path_name="images";
            $limit_size=5;
        }
        $limit_size=$limit_size*1024*1024;
        if($file['size']>$limit_size){
            $data['msg']='上传文件不能超过'.$limit_size.'M';
            $data['status']=1;
            return $data;
        }
        //开始创建目录
        $temp_file=$file['tmp_name']; //服务器临时文件路径
        $time=time();
        $new_file = APP_PUBLIC."uploads/user/".$file_path_name."/".date('Ymd',$time)."/";
        $save_url="/uploads/user/".$file_path_name."/".date('Ymd',$time)."/";
        if(!file_exists($new_file))
        {//检查是否有该文件夹，如果没有就创建，并给予最高权限
            mkdir($new_file, 0700);
        }
        $rand=strval(rand(1000,9999));
        $file_type=explode('/',$file['type'])[1]; //文件后缀
        $new_file = $new_file.$time.'_'.$rand.'.'.$file_type;
        $save_url=$save_url.$time.'_'.$rand.'.'.$file_type;
        //拷贝文件到正式文件夹
        if (file_put_contents($new_file,file_get_contents($temp_file))){//必须先file_get_contents接受临时图片
            $data['msg']='保存成功';
            $data['status']=0;
            $data['file_url']=$save_url;
            return $data;  //保存成功
        }else{
            $data['msg']='上传失败';
            $data['status']=1;
            return $data;
        }
    }

    /**
     * Created by zyjun
     * Info:给图片，视频等加上url地址
     */
    public function addApiUrl($para){
        $file_path=ApiUrl.'/uploads/';
        $str='/uploads/';
        if(!empty($para)){
            $para=str_replace($str,$file_path,$para);
        }
        return $para.'?imageslim';
    }

    /**
     * Created by zyjun
     * Info:给音乐加上url地址
     */
    public function addMusicUrl($para){
        $file_path=AliUrl.'/music/';
        $str='/music/';
        if(!empty($para)){
            $para=str_replace($str,$file_path,$para);
        }
        return $para;
    }

    public function removeApiUrl($para){
        $length=strpos($para, '/uploads');
        if($length===false){
            return $para;
        }else{
            return substr($para,$length,-1);
        }
    }

    /**
     * 用户的基本信息
     * user_id,birthday,city_id,sex, head_pic, college,
     */
    public function userInfo($id){
        $res=Db::name('user u')
            ->join('college col','col.coid=u.college_id','LEFT')
            ->join('city c','c.cityID=u.city_id','LEFT')
            ->join('province p','c.provinceID=p.provinceID','LEFT')
            ->field('u.id user_id,u.id_code,u.city_id,u.province_id,p.pname,u.user_info_status,u.send_status,u.clickVL_status,u.fansL_status,u.attentionL_status,u.nickname,sex,birthday,signature,head_pic,back_pic,col.name college,col.coid coid,c.city')
            ->where('u.id',$id)
            ->find();
        if($res['head_pic']){
            $res['head_pic']=$this->addApiUrl($res['head_pic']);
        }
        if($res['back_pic']){
            $res['back_pic']=$this->addApiUrl($res['back_pic']);
        }else{
            $res['back_pic']=$this->addApiUrl('/uploads/user/images/head_pic.png');
        }
        if($res['signature']){
            $res['signature']=$this->userTextDecode($res['signature']);
        }
        return $res;
    }

    /**
     * 简化用户基本信息 id 头像 昵称 性别
     */
    public function userInfo2($id){
        $res=Db::name('user')
            ->field(['id user_id,nickname,sex,head_pic'])
            ->where('id',$id)
            ->find();
        if($res['sex']=='0'){
            $res['sex']='女';
        }else{
            $res['sex']='男';
        }
        if($res['head_pic']){
            $res['head_pic']=$this->addApiUrl($res['head_pic']);
        }
        return $res;
    }

    /**
     * 查询全部信息列表
     * @param $type
     * @param $page
     * @param array $data
     * @return array|false|int|\PDOStatement|string|\think\Collection
     */
    public function getActionList($page,$data=[]){
        $res=Db::name('action')
            ->alias('a')
            ->join('user u','a.user_id=u.id','LEFT')
            ->join('skill s','a.skill_id=s.id','LEFT')
            ->join('school sch','sch.scid=u.school_id','LEFT')
            ->join('college col','col.coid=u.college_id','LEFT')
            ->join('redpacket red','a.id=red.did','LEFT')
            ->field('a.id action_id,u.id user_id,u.true_name,u.nickname,u.sex,u.head_pic,col.name college,sch.name school,red.red_token,red.type red_type,red.is_over,a.content,a.create_time,a.discuss_num,a.view_num,a.click_num,s.skill,a.action_pic,a.action_video')
            ->order('a.create_time desc')
            ->page($page,'10')
            ->where($data)
            ->where('is_show',0)
            ->select();
        //dump($res);die;
        if($res){
            foreach($res as & $v){
                //dump($v);die;
                if($v['sex']=='1'){
                    $v['sex']='男';
                }elseif($v['sex']=='0'){
                    $v['sex']='女';
                }
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
                    //$v['action_pic_num']=count($v['action_pic']);
                }
            }
        }
        return $res;
    }
    public function getSkillList($page,$data=[]){
        //$data['skill']='not null';
        $res=Db::name('action')
            ->alias('a')
            ->join('user u','a.user_id=u.id','LEFT')
            ->join('skill s','a.skill_id=s.id','LEFT')
            ->join('school sch','sch.scid=u.school_id','LEFT')
            ->join('college col','col.coid=u.college_id','LEFT')
            ->join('redpacket red','a.id=red.did','LEFT')
            ->field('a.id action_id,u.id user_id,u.true_name,u.nickname,u.sex,u.head_pic,col.name college,sch.name school,red.red_token,red.type red_type,red.is_over,a.content,a.create_time,a.discuss_num,a.view_num,a.click_num,s.skill,a.action_pic,a.action_video')
            ->order('a.create_time desc')
            ->page($page,'10')
            ->where($data)
            ->where('is_show',0)
            ->where('skill','not null')
            ->select();
        if($res){
            foreach($res as & $v){
                //dump($v);die;
                if($v['sex']=='1'){
                    $v['sex']='男';
                }elseif($v['sex']=='0'){
                    $v['sex']='女';
                }
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
                    //$v['action_pic_num']=count($v['action_pic']);
                }
            }
        }
return $res;
    }
    public function totalInfoList($type,$page,$data=[]){
        $dat=$data;

        //判断是动态还是发现 分别返回数
        //$res=$this->getActionList($page,$dat);
        if($type=='action'){
            $res=$this->getActionList($page,$dat);
            //所有动态信息 浏览次数加10
            foreach ($res as & $v){
                Db::name('action')->where('id',$v['action_id'])->setInc('view_num',rand(0,3));
            }
            return $res;
        }elseif ($type=='skill'){
            $skill=$this->getSkillList($page,$dat);
            foreach ($skill as & $v){
                Db::name('action')->where('id',$v['action_id'])->setInc('view_num',rand(0,3));
            }
            return $skill;
        }
    }

    /**
     * 查询一条动态信息 评论 用
     * @param $action_id
     * @return array|false|\PDOStatement|string|\think\Model|void
     */
    public function oneInfo($action_id){
        $res=Db::name('action')
            ->alias('a')
            ->join('user u','a.user_id=u.id','LEFT')
            ->join('skill s','a.skill_id=s.id','LEFT')
            ->join('school sch','sch.scid=u.school_id','LEFT')
            ->join('college col','col.coid=u.college_id','LEFT')
            ->join('redpacket red','a.id=red.did','LEFT')
            ->field('u.id user_id,u.true_name,u.nickname,u.sex sex,u.head_pic,col.name college,sch.name school,a.id action_id,red.red_token,red.type red_type,red.is_over,a.content,a.create_time,a.discuss_num,a.view_num,a.click_num,s.skill,a.action_pic,a.action_video')
            ->where('a.id',$action_id)
            ->where('is_show',0)
            ->find();
        if($res){
            if($res['sex']=='1'){
                $res['sex']='男';
            }elseif($res['sex']=='0'){
                $res['sex']='女';
            }
            //响应红包类型
            if($res['red_type']===null){
                $res['red_type']='null'; //没有红包
            }elseif($res['red_type']===0){
                $res['red_type']='0';
                //$res['red_type']='美女专属';
            }elseif($res['red_type']==1){
                $v['red_type']='1';
                // $v['red_type']='帅哥专属';
            }elseif($res['red_type']==2){
                $res['red_type']='2';
                //$res['red_type']='任何人可以领取';
            }
            if($res['head_pic']){
                $res['head_pic']=$this->addApiUrl($res['head_pic']);
            }
            if($res['action_video']){
                $res['action_video']=$this->addApiUrl($res['action_video']);
            }
            //响应的发布时间
            $res['create_time']=$this->timeToHour($res['create_time']);
            //响应的浏览数量
            if($res['view_num']>1000){
                $res['view_num']=round($res['view_num']/1000,1).'k';
                if($res['view_num']>10000){
                    $res['view_num']=round($res['view_num']/10000,1).'w';
                }
            }else{
                $res['view_num']=(string)$res['view_num'];
            }
            //响应的评论数量
            $count=Db::name('discuss')
                ->field('count(*) discuss_num')
                ->where(['action_id'=>$res['action_id'],'group_id'=>['neq','null'],'delete_time'=>null])
                ->select();
            if(!$count){
                return apiError('响应评论数量有误');
            }
            $res['discuss_num']=$count[0]['discuss_num'];

            //响应的点赞数量
            $count=Db::name('click')
                ->field('count(*) click_num')
                ->where('action_id',$res['action_id'])
                ->select();
            if(!$count){
                return apiError('响应点赞数量有误');
            }
            $res['click_num']=$count[0]['click_num'];

            //响应动态的文字和 图片
            if($res['action_pic']){
                $pic=explode(',',$res['action_pic']);
                foreach ($pic as & $value){
                    $value=$this->addApiUrl($value);
                }
                $res['action_pic']=$pic;
            }

        }
        return $res;
    }


    /**
     * 查询个人详情信息下面的动态信息 详情和需求 无学校年级 性别
     * @param $type
     * @param $page
     * @param array $data 筛选用户id和被删除
     * @return array|false|int|\PDOStatement|string|\think\Collection
     */
    public function myInfoList($type,$page,$data=[]){
        $res=Db::name('action')
            ->alias('a')
            ->join('user u','a.user_id=u.id','LEFT')
            ->join('skill s','a.skill_id=s.id','LEFT')
            ->join('redpacket red','a.id=red.did','LEFT')
            ->field('u.id user_id,u.nickname,u.head_pic,a.id action_id,red.red_token,red.type red_type,red.is_over,a.content,a.create_time,a.discuss_num,a.view_num,a.click_num,s.skill,a.action_pic,a.action_video')
            ->order('a.create_time desc')
            ->page($page,'10')
            ->where($data)
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
        //判断是动态还是发现 分别返回数据
        if($type=='action'){
            //所有动态信息 浏览次数加10
            foreach ($res as & $v){
                Db::name('action')->where('id',$v['action_id'])->setInc('view_num',rand(0,3));
            }
            return $res;
        }elseif ($type=='skill'){
            //筛选出含有skill的信息 浏览次数加10
            $skill=[];
            foreach ($res as & $v){
                if(!empty($v['skill'])){
                    $skill[]=$v;
                    Db::name('action')->where('id',$v['action_id'])->setInc('view_num',rand(0,3));
                }
            }
            return $skill;
        }
    }

    /**
     * 个人详情，我的，列表查询
     */
    public function videoList($page,$data=[]){
            $res=Db::name('video v')
                ->join('user u','v.user_id=u.id','LEFT')
                ->join('college col','col.coid=u.college_id','LEFT')
                ->field('v.id,v.user_id,v.challenge_id,v.notify_extra,v.challenge_name,v.publish_addr,v.title,v.video,v.video_cover,v.height,v.width,v.create_time v_create_time,u.nickname user_nickname,u.head_pic user_head_pic,col.name college,col.coid coid,v.music_id,v.click_num,v.discuss_num,v.music_type')
                ->order('v.create_time desc')
                ->page($page,'15')
                ->where($data)
                //->where('v.status',0)
                ->select();
            if($res){
                foreach ($res as $k=>&$v){
                    $v['user_head_pic']=ApiUrl.$v['user_head_pic'];
                    $v['v_create_time']=$this->timeToHour($v['v_create_time']);
                    if($v['music_type']===0&&$v['music_id']!=0){
                        $music=Db::name('music')->where('id',$v['music_id'])->field('name,pic,singer')->find();
                        $v['music_name']=$music['name'];
                        $v['music_singer']=$music['singer'];
                        $v['music_pic']=AliUrl.$music['pic'];
                    }elseif($v['music_type']===1){
                        $v['music_name']=$v['user_nickname'].'原创';
                        $v['music_singer']=null;
                        $v['music_pic']=$v['user_head_pic'];
                    }else{
                        $v['music_name']=$v['user_nickname'].'原创';
                        $v['music_singer']=null;
                        $v['music_pic']=$v['user_head_pic'];
                    }
                    $v['title']=$this->userTextDecode($v['title']);
                }
            }
            return $res;
    }
    /**
     * 关注可和同校列表查询
     */
    public function videoList2($data,$page){
        $res=Db::name('video v')
            ->join('user u','v.user_id=u.id','LEFT')
            //->join('music m','v.music_id=m.id','LEFT')
            ->join('college col','col.coid=u.college_id','LEFT')
            ->field('v.id,v.user_id,v.challenge_id,v.notify_extra,v.challenge_name,v.publish_addr,v.title,v.video,v.video_cover,v.height,v.width,v.create_time v_create_time,u.nickname user_nickname,u.head_pic user_head_pic,col.name college,col.coid coid,v.music_id,v.click_num,v.discuss_num,v.music_type')
            ->order('v.create_time desc')
            ->where($data)
            ->where(['v.status'=>0,'v.delete_time'=>null])
            ->page($page,20)
            ->select();
        if($res){
            foreach ($res as $k=>&$v){
                $v['user_head_pic']=ApiUrl.$v['user_head_pic'];
                $v['v_create_time']=$this->timeToHour($v['v_create_time']);
                if($v['music_type']===0&&$v['music_id']!=0){
                    $music=Db::name('music')->where('id',$v['music_id'])->field('name,pic,singer')->find();
                    $v['music_name']=$music['name'];
                    $v['music_singer']=$music['singer'];
                    $v['music_pic']=AliUrl.$music['pic'];
                }elseif($v['music_type']===1){
                    $v['music_name']=$v['user_nickname'].'原创';
                    $v['music_singer']=null;
                    $v['music_pic']=$v['user_head_pic'];
                }else{
                    $v['music_name']=$v['user_nickname'].'原创';
                    $v['music_singer']=null;
                    $v['music_pic']=$v['user_head_pic'];

                }
                $v['title']=$this->userTextDecode($v['title']);
            }
        }
        return $res;
    }
    /**
     * Created by zyjun
     * Info:获取红包过期时间
     */
    public function getRedpacketExpireTime(){
        //判断红包期限是否期限 1小时=3600  1天=86400  1周604800
        $expire_time=Db::name('sys_set')->where('id',1)->value('data');
        if(empty($expire_time)){
            return 0;
        }
        $expire_time=json_decode($expire_time,true);
        $expire_time=$expire_time['data'];
        return $expire_time;
    }

    /**
     * Created by zyjun
     * Info:确认支付密码
     */
    public function confirmPayPassword($uid,$passwd){
        $res=Db::name('user')->where('id',$uid)->field('pay_password,pay_password_status')->find();
        $pay_password=$res['pay_password'];
        $pay_password_status=$res['pay_password_status'];
        if(empty($pay_password)){
            $data['msg']='请先设置支付密码';
            $data['code']=1;
            $data['status']=1;
            return $data;
        }
        if($pay_password_status==1){
            $data['msg']='支付功能已被锁定,请到个人中心重置支付密码';
            $data['code']=2;
            $data['status']=1;
            return $data;
        }
        if(empty($passwd)){
            $data['msg']='请输入支付密码';
            $data['status']=1;
            $data['code']='';
            return $data;
        }
        if($this->checkPayCode($passwd)){
            $data['msg']='请输入6位纯数字支付密码';
            $data['status']=1;
            $data['code']='';
            return $data;
        }
        $passwd=$this->createPayPassword($passwd);
        if($passwd!=$pay_password){
            $errors=$this->recordPayPasswordError($uid);
            if($errors>=6){ //超过限制，锁定支付功能
              Db::name('user')->where('id',$uid)->setField('pay_password_status',1);
                $data['msg']='支付密码错误次数超过6次,支付功能已被锁定，请到个人中心重置支付密码';
                $data['status']=1;
                $data['code']=2;
                $data['errors']=$errors;
                return $data;
            }
            $num=6-$errors;
            $data['msg']='支付密码错误,您还可以输入'.$num.'次';
            $data['status']=1;
            $data['code']=3;
            $data['errors']=$errors;
            return $data;
        }
        Db::name('user')->where('id',$uid)->update(['pay_password_error_time'=>null,'pay_password_errors'=>0]);
        $data['msg']='支付密码正确';
        $data['code']='';
        $data['status']=0;
        return $data;

    }


    /**
     * Created by zyjun
     * Info:记录支付密码错误次数,并返回当前错误次数
     */
    public function recordPayPasswordError($uid){
        Db::name('user')->where('id',$uid)->setField('pay_password_error_time',date('Y-m-d H:i:s'));
        Db::name('user')->where('id',$uid)->setInc('pay_password_errors');
        $errors=Db::name('user')->where('id',$uid)->whereTime('pay_password_error_time', 'today')->value('pay_password_errors');
        if(empty($errors)){
            return 0;
        }
        return $errors;
    }

    /**
    把用户输入的文本转义（主要针对特殊符号和emoji表情）
     */
    function userTextEncode($str){
        if(!is_string($str))return $str;
        if(!$str || $str=='undefined')return '';

        $text = json_encode($str); //暴露出unicode
        $text = preg_replace_callback("/(\\\u[ed][0-9a-f]{3})/i",function($str){
            return addslashes($str[0]);
        },$text); //将emoji的unicode留下，其他不动，这里的正则比原答案增加了d，因为我发现我很多emoji实际上是\ud开头的，反而暂时没发现有\ue开头。
        return json_decode($text);
    }
    /**
    解码上面的转义
     */
    function userTextDecode($str){
        $text = json_encode($str); //暴露出unicode
        $text = preg_replace_callback('/\\\\\\\\/i',function($str){
            return '\\';
        },$text); //将两条斜杠变成一条，其他不动
        return json_decode($text);
    }

    /**
     * Created by zyjun
     * Info:获取当前钱包金额
     */
    public function getNowWallet($uid){
      $wallet=Db::name('user_wallet')->where('uid',$uid)->value('money');
      if(empty($wallet)){
          return 0;
      }
      return $wallet;
    }

    /**
     * 查询附近人列表信息
     * @param $page请求页码
     * @param $where 筛选条件
     */
    public function nearList($user_id,$where=[]){
        $res=Db::name('user_location')
            ->alias('l')
            ->join('user u','l.user_id=u.id','LEFT')
            ->field('l.lng,l.lat,u.id user_id,u.nickname,u.head_pic,u.sex,l.content,l.create_time')
            ->where($where)
            ->where('lat','neq','null')
            ->where('l.user_id','neq',$user_id)
            ->select();
        if($res){
            foreach($res as & $v){
                //响应头像地址
                if($v['head_pic']){
                    $v['head_pic']=$this->addApiUrl($v['head_pic']);
                }
            }
        }
        return $res;

    }
//计算距离
    //经度 ，纬度   经度，纬度
    public function getDistance($lng1, $lat1, $lng2, $lat2) {
        // 将角度转为狐度
        $radLat1 = deg2rad($lat1); //deg2rad()函数将角度转换为弧度
        $radLat2 = deg2rad($lat2);
        $radLng1 = deg2rad($lng1);
        $radLng2 = deg2rad($lng2);
        $a = $radLat1 - $radLat2;
        $b = $radLng1 - $radLng2;
        $s = 2 * asin(sqrt(pow(sin($a / 2), 2) + cos($radLat1) * cos($radLat2) * pow(sin($b / 2), 2))) * 6378.137 * 1000;
        return $s;
    }


/*************************点赞、评论、@好友相关功能**********************************/
    /**
     * Created by zyjun
     * Info:点赞消息总表，用于生成唯一点赞消息id,取出消息id号，网易云信发送后，客户端统计消息数
     */
    public function totalClick($data){
        $msg_id=Db::name('message_click')->insertGetId($data);
        return $msg_id;
    }

    /**
     * Created by zyjun
     * Info:点赞消息总表，删除某个id记录   $type 0 需求    1：话题
     */
    public function totalClickDel($uid,$type,$type_id){
        Db::name('message_click')->where(['from'=>$uid,'type'=>$type,'type_id'=>$type_id])->delete();
    }


    /**
     * Created by zyjun
     * Info:评论消息总表，用于生成唯一评论消息id,取出消息id号，网易云信发送后，客户端统计消息数
     */
    public function totalComment($data){
        $msg_id=Db::name('message_comment')->insertGetId($data);
        return $msg_id;
    }

    /**
     * Created by zyjun
     * Info:公共方法，记录点赞，评论，@好友；把IM发送的数据，记录到数据库
     */
    public function totalNotice($data){
        $msg_id=Db::name('message_notice')->insertGetId($data);
        return $msg_id;
    }


    /**
     * Created by zyjun
     * Info:通用消息发送函数，只负责IM发消息，不负责记录消息到数据库，可用于发送点赞，评论，@好友的功能
     * $from_uid发送者uid   $to_uid接收者uid   $type消息类型 0 点赞、1评论 、2@好友   3粉丝【客户端的约定】 $msg_id客户端收到的消息id,此id为数据库记录插入后的id，
     * $attach为网易需要组装的字段，如果只是用于前端统计小数点，只发$attach[id]=插入消息id，若要有锁屏通知则需要添加设置IM sendMessage()里面的其他字段内容
     * 前端点开消息，查询数据库数据，只发
     *
     */
    public function sentImMsgs($from_uid,$to_uid,$type,$msg_id){
        $im=new Im();
        $from=Db::name('im_user')->where('uid',$from_uid)->value('accid');
        $to=Db::name('im_user')->where('uid',$to_uid)->value('accid');
        if($from_uid!=$to_uid){ //自己不能给自己发IM消息
            $attach['msg_id']=$msg_id; //评论消息总表id
            $res=$im->sendMessage($from,$to,$type,$attach);
            if($res['status']){
                $re['status']=1;
                $re['msg']='发送消息失败';
                return $re;
            }
            $re['status']=0;
            $re['msg']='发送成功';
            return $re;
        }
    }

    /*************************点赞、评论、@好友相关功能 结束**********************************/

    /**
     * Created by zyjun
     * Info:转换时间为时分秒
     */
    public function timeToHour($time){
        $time=round((time()-strtotime($time))/60); //分钟
        if($time<1){
            $time='刚刚';
        }
        if($time>=1){
            $time=$time.'分钟前';
        }
        if($time>59){
            //大于60分钟
            $time=ceil($time/60).'小时前'; //小时
            //大于24小时
            if($time>23){
                $time=ceil($time/24).'天前'; //天
                //大于30天
                if($time>29){
                    $time=ceil($time/30).'月前'; //月
                    //大于12月
                    if($time>11) {
                        $time ='n年前'; //月
                    }

                }
            }
        }
        return $time;
    }
    /**
     * 返回红包状态
     */
    public function getRedType($id,$action_id,$red_type,$is_over){
        $sex=Db::name('user')->where('id',$id)->value('sex');
        if($sex==$red_type||$red_type==2){
            $red=Db::name('redpacket')->where('did',$action_id)->find();
            //判断是否已经过期
            $expire_time=$this->getRedpacketExpireTime();
            $sent_time=strtotime($red['sent_time']);
            if(time()-$sent_time>$expire_time){
                //红包已经过期
                $red_type='4';
                //过期之后 是否抢到过
                $red_data=json_decode($red['data'],true);
                foreach ($red_data as &$rd){
                    if($rd['uid']==$id){
                        $red_type='3'; //
                    }
                }
            }else{
                //没有过期 判断是否抢完了
                if($is_over==1){
                    //抢完了
                    $red_type='5';
                    $red_data=json_decode($red['data'],true);
                    foreach ($red_data as &$rd){
                        if($rd['uid']==$id){
                            $red_type='3'; //
                        }
                    }
                }else{
                    //没有抢完
                    $red_data=json_decode($red['data'],true);
                    foreach ($red_data as &$rd){
                        if($rd['uid']==$id){
                            $red_type='3'; //
                        }
                    }
                }
            }
        }
        return $red_type;
    }
    /**
     * Created by zyjun
     * Info:七牛云图片鉴黄
     * $url图片地址传入数组地址或者单个图片地址
     */
    public function isSexyImg($url){
        $lable=''; //图片违规类型
        if(is_array($url)){
            foreach ($url as $key=>$val) {
                $url=ApiUrl.$val.'?qpulp';
                $res=httpGetData($url);
                if(!$res['status']){ //返回正常数据才进入
                    $data=$res['data']['result'];
                    if($data['label']==0){ //色情
                        $re['status']=1;
                        $re['msg']='包含色情或者性感图片';
                        $re['code']=$data['label'];
                        return $re;
                    }
                    if($lable==''){ //只检测一次，避免循环覆盖
                        if($data['label']==1){
                            $lable=1; //性感  检测性感图片不返回，只记录类型为性感
                        }
                    }
                }
            }
            $re['status']=0;
            $re['msg']='无违规图片';
            $re['code']=$lable;
            return $re;
        }else{
            $url=ApiUrl.$url.'?qpulp';
            $res=httpGetData($url);
            if(!$res['status']){ //返回正常数据才进入
                $data=$res['data']['result'];
                if($data['label']==0){ //色情或者性感
                    $re['status']=1;
                    $re['msg']='包含色情或者性感图片';
                    $re['code']=$data['label'];
                    return $re;
                }
                if($data['label']==1){
                    $lable=1; //性感  检测性感图片不返回，只记录类型为性感
                }
            }
            $re['status']=0;
            $re['msg']='无违规图片';
            $re['code']=$lable;
            return $re;
        }

    }

    /**
     * 我的关注粉丝订单数量
     */
    public function myNum($id){
        //粉丝数量
        $data['fans_num']=Db::name('user_relation')->where(['attention_id'=>$id,'is_cancel'=>0])->count();
        //我的关注数量
        $data['attention_num']=Db::name('user_relation')->where(['user_id'=>$id,'is_cancel'=>0])->count();
        //我拉黑数量
        $data['black_num']=Db::name('user_black')->where(['user_id'=>$id,'is_cancel'=>0])->count();
        return $data;
    }
    /**
     * 我的订单数量
     */
    public function order_num($id){
        //判断用户是否有订单
        $res=Db::name('order')->where('uid',$id)->select();
        if($res){
            $order_num=Db::name('order o')
                ->where(['uid'=>$id,'order_status'=>1]) //已经付款未完成的订单数量
                ->count();
                return $order_num;
        }
    }
    /**
     *评论父级子级排列
     */
    public function tree($rows,$id=0)
    {
        static $tree = [];
        foreach($rows as $row) {
            if ($id== $row['parent_id']) {
                $tree[] = $row;
                $this->tree($rows, $row['id']);
            }
        }
        return $tree;
    }

    /**
     * Created by zyjun
     * Info:获取用户app版本号和手机类型ios，安卓
     * 需要做版本兼容的地方获取，没有版本号的默认为版本1.0
     */
    public function getUserAppInfo($id){
        $data=Db::name('user')->where('id',$id)->field('app_version,app_device')->find();
        return $data;
    }

    /**
     * Created by zyjun
     * Info:记录个人总收入
     * $money交易金额
     */
    public function walletRecordToatl($uid,$type,$money,$stype,$status){
        if($type==1){ //进账
            if(in_array($status,[3,4,5])&&$stype==2){ //退款的时候，不再往in_money里面累加钱，但是out_money里面要减少返回的钱
                $out_money=Db::name('user_wallet')->where('uid',$uid)->value('out_money');
                $out_money=$out_money-$money; //减少
                Db::name('user_wallet')->where('uid',$uid)->setField('out_money',$out_money);
            }else{ //其余的充值，领取红包需要加钱进去
                $in_money=Db::name('user_wallet')->where('uid',$uid)->value('in_money');
                $in_money=$in_money+$money;
                Db::name('user_wallet')->where('uid',$uid)->setField('in_money',$in_money);
            }
        }
        if($type==0){ //出账
            $out_money=Db::name('user_wallet')->where('uid',$uid)->value('out_money');
            $out_money=$out_money+$money;
            Db::name('user_wallet')->where('uid',$uid)->setField('out_money',$out_money);
        }

    }




    /**
     * 点赞 评论数量转字符串
     * numtostring
     */
    public function numToString($num){
        if($num>1000){
            $num=round($num/1000,1).'k';
            if($num>10000){
                $num=round($num/10000,1).'w';
            }
        }else{
            $num=(string)$num;
        }
        return $num;
    }

    /**
     * 视频详情显示
     */
    public function oneVideoInfo($video_id){
        $video_info=Db::name('video v')
            ->join('user u','v.user_id=u.id','LEFT')
            ->join('music m','v.music_id=m.id','LEFT')
            ->where(['v.id'=>$video_id])
            ->field('v.id v_id,v.title,v.video,v.video_cover,u.id u_id,u.nickname,u.head_pic,m.name m_name,m.pic m_pic,m.music,v.click_num,v.discuss_num')
            ->find();
        if($video_info){
            $video_info['head_pic']=ApiUrl.$video_info['head_pic'];
            if($video_info['music']){
                $video_info['m_pic']=AliUrl.$video_info['m_pic'];
                $video_info['music']=AliUrl.$video_info['music'];
            }
            $video_info['title']=$this->userTextDecode($video_info['title']);
            $video_info['click_num']=$this->numToString($video_info['click_num']);
            $video_info['discuss_num']=$this->numToString($video_info['discuss_num']);
        }
        return $video_info;

    }

    /**
     * Created by zyjun
     * Info:app点击事件监控事件码
     */
    public function statisticsCode($code){
        $codes=array(
            '100'=>'跳过昵称，性别，头像设置',
            '101'=>'保存昵称，性别，头像设置',
        );
        $des='未知事件';
        foreach ($codes as $key=>$val){
             if($code==$key){
                 $des=$val;
                 break;
             }
        }
        if(empty($des)){
            $re['status']=1;
            $re['msg']='未知事件';
        }else{
            $re['status']=0;
            $re['data']=$des;
        }
        return $re;
    }

    /**
     * Created by zyjun
     * Info:设置粒米号
     */
    public function  setUserIdCode($uid){
        $id_code=Db::name('user_code')->where('status',0)->order('id asc')->value('code');
        Db::name('user')->where('id',$uid)->setField('id_code',$id_code);
        return $id_code;
    }

    /**
     * 判断用户是否成功登录
     */
    public function is_login($id){
        if(empty($id)){
            return false;
        }
        $res=Db::name('user')->where('id',$id)->value('is_login');
        if($res==1){
            return true;
        }else{
            return false;
        }
    }

    /**
     * Created by zyjun
     * Info:公共权限继承
     */
    public function Auth(){
        $auth=new Auth();
        $auth->auth();
    }

    /**
     * Created by zyjun
     * Info:格式化输出视频格式，注意$data格式，内容不完全是来源于默认字段名称
     * 一维数组传入返回一维数组值，二维数组返回二维数组值
     */
    public function formatVideo($data){
        if(empty($data)){
            return NULL;
        }
        if (count($data) == count($data, 1)) {
            $rdata['id']=$data['id'];
            $rdata['click_num']=$data['click_num'];
            $rdata['discuss_num']=$data['discuss_num'];
            $rdata['title']=$this->handleVideoTitle($data['notify_extra'],$data['title']);
            $rata['notify_extra']=$this->handleVideoNoticeExtra($data['notify_extra']);
            $rdata['challenge_id']=$data['challenge_id'];
            $rdata['challenge']=$data['challenge_name'];
            $rdata['publish_addr']=$data['publish_addr'];

            if(isset($data['is_attention'])){ //只有登陆用户才会返回is_attention，is_click
                $rdata['is_attention']=$data['is_attention'];
            }
            if(isset($data['is_click'])){
                $rdata['is_click']=$data['is_click'];
            }


            $music=[];
            $music['music_id']=$data['music_id'];
            $music['music_type']=$data['music_type'];
            $music['name']=$data['music_name'];
            $music['pic']=$data['music_pic'];
            $music['singer']=$data['singer'];
            $rdata['music']=$music;

            $user=[];
            $user['user_id']=$data['user_id'];
            $user['head_pic']=$data['user_head_pic'];
            $user['nickname']=$data['user_nickname'];
            $rdata['user']=$user;

            $college=[];
            $college['id']=$data['college_id'];
            $college['name']=$data['college_name'];
            $rdata['user']['college']=$college;

            $video=[];
            $video['cover']=$data['video_cover'];
            $video['video']=$data['video'];
            $video['width']=$data['width'];
            $video['height']=$data['height'];
            $rdata['video']=$video;
        }else{
            foreach ($data as $key=>$val){
                $rdata[$key]['id']=$val['id'];
                $rdata[$key]['click_num']=$val['click_num'];
                $rdata[$key]['discuss_num']=$val['discuss_num'];
                $rdata[$key]['title']=$this->handleVideoTitle($val['notify_extra'],$val['title']);
                $rata[$key]['notify_extra']=$this->handleVideoNoticeExtra($val['notify_extra']);
                $rdata[$key]['challenge_id']=$val['challenge_id'];
                $rdata[$key]['challenge']=$val['challenge_name'];
                $rdata[$key]['publish_addr']=$val['publish_addr'];

                if(isset($val['is_attention'])){ //只有登陆用户才会返回is_attention，is_click
                    $rdata[$key]['is_attention']=$val['is_attention'];
                }
                if(isset($val['is_click'])){
                    $rdata[$key]['is_click']=$val['is_click'];
                }


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
        }

        return $rdata;
    }

    /**
     * Created by zyjun
     * Info:获取单个ID视频详情
     * 处理登陆，未登录下的视频播放权限
     */
    public function  dealVideoDetail($video_id,$id,$token){
        #验证id
        if(empty($video_id)){
            $re['status']=1;
            $re['msg']='视频参数错误';
            $re['sub_msg']='视频id不能为空';
            $re['sub_code']=0x01;
            return $re;
        }
        if($this->checkInt($video_id,'','')){
            $re['status']=1;
            $re['msg']='视频参数错误';
            $re['sub_msg']='视频id格式错误';
            $re['sub_code']=0x02;
            return $re;
        }
        #验证资源是否存在，状态是否正常
        $res=Db::name('video')->where('id',$video_id)->where('status',0)->field('id,view_auth,user_id')->find();
        if(empty($res)){
            $re['status']=1;
            $re['msg']='视频不存在或已被删除';
            $re['code']=3000;
            $re['sub_msg']='视频审核中、审核不通过、或者已删除';
            $re['sub_code']=0x03;
            return $re;
        }
        $view_auth=$res['view_auth'];
        $publish_user=$res['user_id'];
          #先判断用户是否登陆，权限为1的视频，不登陆也返回视频详情，但是其他权限必须让起登陆后再次判断；且登陆后的视频详情包含是否关注，点赞字段
        $is_login=$this->is_login($id);
        if($is_login){ //登陆，链表查询登陆信息
            #登陆需要检测id,token
            $res=$this->checkToken($id,$token);
            if($res['status']){
                $re['status']=1;
                $re['msg']='身份验证失败';
                $re['code']=$res['code'];
                $re['sub_msg']=$res['msg'];
                $re['sub_code']=0x04;
                return $re;
            }
            if($view_auth==1){
                $video_data=$this->getLoginVideoInfo($video_id,$id);
                $video_data=$this->formatVideo($video_data);
                $re['status']=0;
                $re['data']=$video_data;
                return $re;
            }
            if($view_auth==2){
                #判断是否是粉丝
                $res1=Db::name('user_relation')->where('user_id',$id)->where('attention_id',$publish_user)->where('is_cancel',0)->find();
                if(empty($res1)){
                    $re['status']=1;
                    $re['msg']='该视频仅粉丝可见';
                    $re['code']=3000;
                    $re['sub_code']=0x05;
                    return $re;
                }
                $video_data=$this->getLoginVideoInfo($video_id,$id);
                $video_data=$this->formatVideo($video_data);
                $re['status']=0;
                $re['data']=$video_data;
                return $re;
            }
            if($view_auth==3){
                if($id!=$publish_user){
                    $re['status']=1;
                    $re['msg']='该视频仅作者可见';
                    $re['code']=3000;
                    $re['sub_code']=0x06;
                    return $re;
                }
                $video_data=$this->getLoginVideoInfo($video_id,$id);
                $video_data=$this->formatVideo($video_data);
                $re['status']=0;
                $re['data']=$video_data;
                return $re;
            }

        }else{ //没登陆，链表查询未登陆信息
            if($view_auth==1){
                $video_data=$this->getUnLoginVideoInfo($video_id);
                $video_data=$this->formatVideo($video_data);
                $re['status']=0;
                $re['data']=$video_data;
                return $re;
            }
            if($view_auth==2){
                $re['status']=1;
                $re['code']=3000;
                $re['msg']='该视频仅粉丝可见';
                $re['sub_code']=0x07;
                return $re;
            }
            if($view_auth==3){
                $re['status']=1;
                $re['code']=3000;
                $re['msg']='该视频仅作者可见';
                $re['sub_code']=0x08;
                return $re;
            }
        }

    }

    /**
     * Created by zyjun
     * Info:未登录状态下查询单个视频信息
     */
    public function getUnLoginVideoInfo($video_id){
        $field=' a.id,a.user_id,a.title,a.notify_extra,a.video_cover,a.video,a.width,a.height,a.view_num,a.click_num,a.discuss_num,a.tags,a.music_id,a.music_type,a.challenge_id,a.challenge_name ,a.publish_addr,b.id user_id,b.nickname user_nickname,CONCAT("'.ApiUrl.'",b.head_pic) AS user_head_pic,c.singer,c.name music_name,CONCAT("'.AliUrl.'",c.pic) AS music_pic,f.name college_name,f.coid college_id';
        $where="a.id=$video_id";
        $query="SELECT $field FROM limi_video AS a LEFT JOIN limi_user AS b ON a.user_id = b.id LEFT JOIN limi_music AS c ON (a.music_id = c.id AND a.music_type=0)  LEFT JOIN limi_college AS f ON b.college_id=f.coid WHERE $where  LIMIT 1";
        $res=Db::query($query);
        if(empty($res)){
            return [];
        }
        return $res[0];
    }

    /**
     * Created by zyjun
     * Info:已登录状态下查询单个视频信息
     * $video_id:视频id,$uid:登陆用户id
     */
    public function getLoginVideoInfo($video_id,$uid){
        $url='http://video.youhongtech.com';
        $field=' a.id,a.user_id,a.title,a.notify_extra,a.video_cover,a.video,a.width,a.height,a.view_num,a.music_type,a.challenge_id,a.challenge_name,a.publish_addr, CASE d.type WHEN 1 THEN 1 WHEN 0 THEN 0 ELSE 0 END as is_click,CASE e.is_cancel WHEN 1 THEN 0 WHEN 0 THEN 1 ELSE 0 END as is_attention,a.click_num,a.discuss_num,a.tags,a.music_id,b.id user_id,b.nickname user_nickname,CONCAT("'.ApiUrl.'",b.head_pic) AS user_head_pic,c.singer,c.name music_name,CONCAT("'.AliUrl.'",c.pic) AS music_pic,f.name college_name,f.coid college_id';
        $where="a.id=$video_id";
        $query="SELECT $field FROM limi_video AS a LEFT JOIN limi_user AS b ON a.user_id = b.id LEFT JOIN limi_music AS c ON (a.music_id = c.id AND a.music_type=0) LEFT JOIN limi_video_click AS d ON (a.id = d.video_id AND d.user_id=$uid ) LEFT JOIN limi_user_relation AS e ON (e.attention_id=a.user_id AND e.user_id=".$uid." ) LEFT JOIN limi_college AS f ON b.college_id=f.coid WHERE $where  LIMIT 1";
        $res=Db::query($query);
        if(empty($res)){
            return [];
        }
        return $res[0];
    }

    /**
     * Created by zyjun
     * Info:处理video表Extra字段，如果是老数据，直接返回$title,如果是新数据，则解析$extra，包含标题和呢称
     * ios客户端要求分2个字段返回title和@信息。
     * $param=Extra值Extra字段，则返回表里的title字段值
     */
    public function handleVideoTitle($extra,$title){
     if(!empty($extra)){
         $extra=json_decode($extra,true);
         $new_title='';
         foreach ($extra as $key=>$val){
              if($val['type']==0){
                  $new_title.=$this->userTextDecode($val['text']);
              }
             if($val['type']==1){
                 $new_title.='@'.Db::name('user')->where('id',$val['id'])->value('nickname');
             }
         }
         return $new_title;

     }else{
         return $title;
     }
    }

    /**
     * Created by zyjun
     * Info:处理video表Extra字段，如果是老数据，直接返回$title,如果是新数据，则解析$extra，包含标题和呢称
     * ios客户端要求分2个字段返回title和@信息。
     * $param=Extra值Extra字段，则返回表里的title字段值
     */
    public function handleVideoNoticeExtra($extra){
        if(!empty($extra)){
            $extra=json_decode($extra,true);
            foreach ($extra as $key=>$val){
                if($val['type']==0){
                    $extra[$key]['text']=$this->userTextDecode($val['text']);
                }
                if($val['type']==1){
                    $extra[$key]['text']=Db::name('user')->where('id',$val['id'])->value('nickname');
                }
            }
            return ($extra);

        }else{
            return '';
        }
    }
}
