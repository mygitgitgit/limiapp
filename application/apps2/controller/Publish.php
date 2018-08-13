<?php
namespace app\apps2\controller;
use think\Controller;
use think\Db;
class Publish extends Common
{
    static $pre_key='red_'; //redis红包key前缀
    static $redis_db=RedisDb; //redis选择的数据库
    static $redis_pass='youhong@limiapp';
    static $redis_host='47.97.218.145';
    /**
     * Created by zyjun
     * Info:初始化
     */
    public function _initialize(){
        $ss=0;
    }


    /**
     * Created by zyjun
     * Info:1:发布动态
     * 2:拆分红包到mysql
     *
     */
    public function publishDynamic(){
        $is_verify=0;//默认不审核标志
        //验证登录
        $id = input('post.id');
        $token = input('post.token');
        $res = $this->checkToken($id, $token);
        if ($res['status']) {
            return apiError($res['msg'],'',$res['code']);
        }
        //临时处理1.1ios路径bug
        $appInfo=$this->getUserAppInfo($id);
        if($appInfo['app_version']=='V1.1'&&$appInfo['app_device']=='ios'){
            return apiError('视频功能维护中,请等待v1.11更新');
        }
        //验证权限
        $res=$this->isAccess($id);
        if($res['identity_status']!=2){
            return apiError($res['msg'],$res['identity_status']);
        }
        $red_token=input('post.red_token');
        $data['skill_id']=$skill_id=input('post.skill_id');
        $data['content']=$this->userTextEncode(input('post.content'));
        $data['action_pic']=$action_pic=input('post.images');
        $data['action_video']=$action_video=input('post.video');
        if($data['content']==''&&$data['action_pic']==''&&$data['action_video']==''){
            return apiError('请先填写发布内容');
        }
        $length=strlen($data['content']);
        if($length>600){
            return apiError('动态内容字数不能超过200字');
        }
        if($action_video!=''&&$action_pic!=''){
            return apiError('不能同时上传图片和视频');
        }
        $imgs=$action_pic;
        if(strpos($action_pic,',')!==false){ //多图上传
            $imgs=explode(',',$action_pic);
            $length=count($imgs);
            if($length>9){
                return apiError('最多只能上传9张图片');
            }
        }
        //鉴别色情图片
        if($imgs!=''){
            $res=$this->isSexyImg($imgs);
            $sex_status=$res['code'];
            $data['image_check_status']=$sex_status;
            if($res['status']){//包含色情或者性感图片
                if($sex_status==0){
                    $data['is_verify']=1; //色情图片审核中
                    $data['is_show']=1; //审核完毕后修改is_show=0;
                    $is_verify=1;
                }
            }
        }
        //视频
        if(strpos($action_video,',')!==false){ //视频
            $length=count(explode(',',$action_video));
            if($length>1){
                return apiError('最多只能上传1个视频');
            }
        }
        //视频鉴黄
        if($action_video!=""){
            $dataid=$this->createAliGreenDataid();
            $green=new Aligreen();
            $res=$green->videoUrl($action_video,$dataid); //如果返回状态失败，也直接后台审核
            if($res['status']){
                $data['video_check_status']=2; //视频检测失败
            }
            $data['is_verify']=1; //发视频一律需要审核后显示
            $data['is_show']=1; //审核完毕后修改is_show=0;
            $data['green_dataid']=$dataid; //写入数据库，用于回调修改发布状态
            $is_verify=1;
        }

        if(!empty($skill_id)){
            if($this->checkInt($skill_id,'','')){
                return apiError('技能标签ID非法');
            }
        }
        if(!empty($red_token)){
            if($this->checkRedToken($red_token)){
                return apiError('红包Token非法');
            }
            //开启事务处理，防止表单多次提交，锁住单个红包行
            Db::startTrans();
            try{
                $res=Db::name('redpacket')->where('red_token',$red_token)->lock(true)->find();
                if(empty($res)){
                    return apiError('发布失败,红包已使用或失效');
                }
                if($id!=$res['uid']){
                    return apiError('红包Token非法');
                }
                $res=Db::name('redpacket')->where('red_token',$red_token)->field('id,money,money_num,is_solve')->find();
                $red_packet_id=$res['id'];
                $money=$res['money'];
                $money_num=$res['money_num'];
                $is_solve=$res['is_solve'];
                $receive_red_token=MD5(time().$red_packet_id.rand(1,9999));//根据红包id生成新的领取token
                $res=Db::name('redpacket')->where('red_token',$red_token)->setField('red_token',$receive_red_token); //更新为领取token防止再次用于发布动态
                if(empty($res)){
                    Db::rollback(); //恢复token
                    return apiError('发布动态失败');
                }
                Db::commit();
            } catch (\Exception $e){
                // 回滚事务
                Db::rollback();
                return apiError('发布动态失败');
            }
        }
        $data['user_id']=$id;
        $data['skill_id']=$skill_id;
//         $data['view_num']=rand(150,300);
//         $data['click_num']=round($data['view_num']/3)+rand(1,49);
        $data['create_time']=date('Y-m-d H:i:s');
        $res=Db::name('action')->insert($data);
        if(empty($res)){
            return apiError('发布动态失败');
            $this->redPacketLog($id,array('red_packet_id'=>$red_packet_id,'des'=>'动态发布失败'));
        }
        $did=Db::name('action')->getLastInsID();
        //需求有塞入钱包，给红包表写入需求id
        if(!empty($red_token)){
            $res=Db::name('redpacket')->where('id',$red_packet_id)->setField('did',$did);
            if(empty($res)){
                $this->redPacketLog($id,array('red_packet_id'=>$red_packet_id,'des'=>'发布需求后，红包表未成功写入需求did'));
                return apiError('发布动态失败');
            }
            //开始分配红包
            $res=$this->splitRedPacked($id,$red_packet_id,$money,$money_num,0.01);
            if($res['status']){
                return apiError('发布动态失败');
            }
            $min_moneys=$res['data'];
            if(empty($min_moneys)){
                return apiError('发布动态失败');  //没有小红包
            }
            //保存小红包到mysql,用键值对的形式保存，扩展is_solve字段，判断需求是否已经解决
            $redpacket_data=array();
            $data=array();
            if($is_solve==0){
                $solve_status=1; //不需要解决问题的需求，默认解决状态都为1
            }else{
                $solve_status=0;
            }
            foreach ($min_moneys as $key=>$val){
                $redpacket_data[$key]['money']=$val;
                $redpacket_data[$key]['uid']='';
                $redpacket_data[$key]['solve_status']=$solve_status;
            }
            $data['data']=json_encode($redpacket_data); //红包数量
            $res=Db::name('redpacket')->where('id',$red_packet_id)->update($data);
            if($res===false){
                $this->redPacketLog($id,array('red_packet_id'=>$red_packet_id,'des'=>'小红包写入表失败'));
                return apiError('发布动态失败');
            }
            //红包拆分保存完毕
        }
        if($is_verify==1){
            return apiSuccess('发布成功，等待审核通过后显示',100);
        }else{
            return apiSuccess('发布成功');
        }
    }

    /**
     * Created by zyjun
     * Info:上传动态图片视频
     */
    public function uploadDynamicImg(){
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
        $file=$_FILES['file'];
        $res=$this->uploadFiles($file,'all');
        if($res['status']){
            return apiError($res['msg']);
        }
        apiSuccess('上传成功',$res['file_url']);
    }


    /**
     * Created by zyjun
     * Info:给需求塞入红包
     * 1：过滤不合法数据， 检测用户余额是否充足
     *:2：生成红包表，生成红包token,发布需求时携带id,token,red_token,发布后清除red_token
     * 3：如果有未使用的红包，linux系统自动退回到个人账户。后期可以考虑辅助显示个人未使用或者未领取完毕的红包
     *:4：这一步只生成红包数据到表redpacket,发布的时候才开始拆分红包数据到mysql.
     * 5:领取红包的时候呀直接写用户uid到redis,再通过redis保存的用户，写表到mysql永久存储.
     */
    public function sendRedpacket(){
//        //验证登录
        $id = input('id');
        $token = input('token');
        $money=input('post.money');
        $money_num=input('post.num');
        $type=input('post.type');  //帅哥美女专属
        $trade_no=input('post.trade_no');  //在线交易的传入交易号
        $pay_password=input('password');
        $is_solve=input('post.is_solve'); //是否需要解决问题才能领取红包

        $res = $this->checkToken($id, $token);
        if ($res['status']) {
            return apiError($res['msg'],'',$res['code']);
        }
        //验证权限
        $res=$this->isAccess($id);
        if($res['identity_status']!=2){
            return apiError($res['msg'],$res['identity_status']);
        }

        if(empty($money)){
            return apiError('请输入红包金额');
        }
        if(empty($money_num)){
            return apiError('请输入红包个数');
        }
        if($money==0){
            return apiError('请输入红包金额');
        }
        if($money_num==0){
            return apiError('请输入红包个数');
        }
        //检测红包金额格式和余额
        if($this->checkRedPacket($money)){
            return apiError('红包金额错误，请输入整数或2位小数红包');
        }
        if($money<0.01){
            return apiError('单个红包金额不能小于0.01元');
        }
        $money=abs($money);  //转换为整型
        if($money>1000){
            return apiError('超出红包金额1000元上限');
        }
        //开启事务处理 必须
        Db::startTrans();
        try{
        $res=Db::name('user_wallet')->lock(true)->where('uid',$id)->find();
        if(empty($res)){
            return apiError('余额不足,请充值','',4);  //没充值过，或者没抢过红包,账户为空
        }
        $wallet=$res['money'];
        if(empty($wallet)){
            return apiError('余额不足,请充值','',4);  //没有余额
        }
        $new_money=$wallet-$money;
        if($new_money<0){
            return apiError('余额不足,请充值','',4);  //没有余额
        }

        //检测红包个数
        if($this->checkInt($money_num,'','')){
            return apiError('红包个数格式错误');
        }
        $money_num=abs($money_num);
        if($money_num<=0||$money_num>200){
            return apiError('红包个数为1-200个');
        }
        //检测每个小红包是否大于0.01元
        if($money/$money_num<0.01){
            return apiError('单个红包金额需大于0.01元，请重新输入');
        }
        //检测红包专属
        if(!in_array($type,array(0,1,2))){
            return apiError('红包领取类型错误');
        }

        //在线支付的不验证支付密码
        $online_pay=Db::name('recharge')->where('order_out_biz_no',$trade_no)->find();
        if(empty($online_pay)){
            $res=$this->confirmPayPassword($id,$pay_password);
            if($res['status']){
                Db::commit();
                return apiError($res['msg'],'',$res['code']);
            }
        }

        //写一条记录到红包发布表  发布需求的时候先查看是否验证了支付密码，验证了的直接扣除账户金额
        $redpacket['uid']=$id;
        $redpacket['money']=$money;
        $redpacket['money_num']=$money_num;
        $redpacket['type']=$type;
        $redpacket['is_solve']=0; //0 不需要坚决问题，就可以领取红包
        $redpacket['red_token']=MD5(time().$id.rand(1,9999)); //产生唯一的token,用于发布的时候验证，验证后清除token
        $redpacket['sent_time']=date('Y-m-d H:i:s');

        //抵扣账户金额,插入红包到表
        $wallet=Db::name('user_wallet')->where('uid',$id)->value('money');
        $new_wallet=$wallet-$money;
        $res=Db::name('user_wallet')->where('uid',$id)->update(['money'=>$new_wallet]);
        if($res===false){
            Db::commit();
            return apiError('塞入钱包失败');  //扣除账户失败
        }
        $res=Db::name('redpacket')->insert($redpacket);
        $red_packet_id=Db::name('redpacket')->getLastInsID();
        if(empty($res)){//插入失败
            $res=Db::name('user_wallet')->where('uid',$id)->setInc('money',$money); //返回账户
            if($res===false){
                $this->redPacketLog($id,array('red_packet_id'=>$red_packet_id,'des'=>'塞入钱包失败,红包金额自动返回账户失败'));
                Db::commit();
                return apiError('塞入钱包失败,红包金额未自动返回账户,请联系客服处理');
            }
            Db::commit();
            return apiError('塞入钱包失败');
        }
        //写入redis 红包id
        $key=self::$pre_key.$red_packet_id;//红包前缀
        $redis = new \Redis();
        $redis->connect(self::$redis_host, 6379);
        $redis->auth(self::$redis_pass);
        $redis->select(self::$redis_db); //选择数据库
        $redis->sAdd($key,''); //只写入个key,不写入值
        $redis->expire($key,85680); //redis有效期23.8小时，红包过期时间24小时
        $this->redPacketRecordDetail($id,$red_packet_id,2,'',2,$money,$new_wallet,0,'发送红包');
        $return['red_token']=$redpacket['red_token'];
        $return['money']=$redpacket['money'];
        apiSuccess('塞入钱包成功',$return);  //返回token
        Db::commit();
        } catch (\Exception $e){
            // 回滚事务
            Db::rollback();
            return apiError('发送红包失败');
        }
    }


    /**
     * Created by zyjun
     * Info:点击取消发布，要求退回已经抵扣的红包
     */
    public function cancelDynamic(){
        //验证登录
        $id = input('post.id');
        $token = input('post.token');
        $res = $this->checkToken($id, $token);
        if ($res['status']) {
            return apiError($res['msg'],'',$res['code']);
        }
        //验证权限
        $res=$this->isAccess($id);
        if($res['identity_status']!=2){
            return apiError($res['msg'],$res['identity_status']);
        }
        $red_token=input('post.red_token');
        if(!empty($red_token)){
            //查看是否真的有红包未使用
            Db::startTrans();
            try{
                $res=Db::name('redpacket')->where('red_token',$red_token)->lock(true)->find();
                if(empty($res)){
                    return apiError('红包已使用或失效');
                }
                if($id!=$res['uid']){ //判断请求id与红包uid是否相同
                    return apiError('红包Token非法');
                }
                $money=$res['money'];
                $red_packet_id=$res['id'];
                $res=Db::name('redpacket')->where('red_token',$red_token)->update(['red_token'=>NULL,'is_back'=>1]);
                if(empty($res)){
                    $this->redPacketLog($id,array('red_token'=>$red_token,'des'=>'取消发布需求，成功返还红包金额到用户账户,但未清除红包red_token'));
                    return apiError('红包金额返回账户失败,请手动撤回或者24小时后系统自动返还');
                }
                $res=Db::name('user_wallet')->where('uid',$id)->setInc('money',$money);
                if(empty($res)){
                    $this->redPacketLog($id,array('red_token'=>$red_token,'des'=>'取消发布需求，未成功返还红包金额到用户账户'));
                    return apiError('红包金额返回账户失败,请联系管理员处理');
                }
                $wallet=$this->getNowWallet($id);
                $this->redPacketRecordDetail($id,$red_packet_id,2,'',3,$money,$wallet,1,'红包退回');
                Db::commit();
                return apiSuccess('取消成功');
            } catch (\Exception $e){
                Db::rollback();
                return apiError('领取红包失败');
            }

        }
        apiSuccess('取消成功');
    }




    /**
     * Created by zyjun
     * Info:红包算法 划分随机红包
     *$total_money红包总额; $total_num红包数量 ;$min_money;最小红包；
     */
    public function splitRedPacked($uid,$red_packet_id,$total_money,$total_num,$min_money){
        $total_money=(float)$total_money;
        $total_money=$total_money*100; //放大100倍,rand()只计算整数
        $min_money=$min_money*100;
        $average_money=$total_money/$total_num; //平均值
        $min_red_packets=0; //第一次计算的小红包总合
        if($total_money/$total_num==$min_money){ //刚好等于最小红包，直接返回
            while($total_num--){
                $data_money[]=$min_money/100;
            }
        }

        if($total_money/$total_num>$min_money){ //说明可以平分，还有多余
            for($i=0;$i<$total_num;$i++){
                $min_red_packet=rand($min_money,$average_money); //计算出最小红包到平均值之间的随机小红包
                $data_money[]=$min_red_packet; //保存每个小红包
            }
            //计算第一次筛选后的余额。
            while(1){
                $remain_money=$total_money-array_sum($data_money); //每次重新计算剩下的金额
                $res=$this->addMinRedPacket($remain_money,$total_num,$min_money);
                if($res['status']){
                    $data_money[0]=$data_money[0]+$res['money']; //余额平分不足0.01元
                    break;
                }
                for($i=0;$i<$total_num;$i++){
                    $data_money[$i]=$data_money[$i]+$res['money']; //追加平均红包
                }
            }
            //计算完毕后最后一次将数组里的值去掉小数
            for($i=0;$i<$total_num;$i++){
                $data_money[$i]=floor($data_money[$i])/100; //过滤小数点,把分后面一位过滤掉，过滤掉的值在后面加
            }
            $total_red_packet=array_sum($data_money);
            $remain_money=$total_money-$total_red_packet; //除不尽的时候计算剩下还有几毛钱;
            $total_money=$total_money/100;
            $remain_money=round($total_money-$total_red_packet,2);
            if( $remain_money>0){
                $data_money[0]=$data_money[0]+$remain_money; //追加平均红包给第一个红包
            }
            //最后判断红包是否相等
            $total_red_packet=array_sum($data_money);
            if($total_red_packet > $total_money+0.01){
                $return['msg']='塞入钱包失败';
                $return['status']=1;
                $this->redPacketLog($uid,array('red_packet_id'=>$red_packet_id,'des'=>'分配红包出现异常'));
                return $return;
            }

        }
        shuffle($data_money); //随机排序
        $return['msg']='分配红包成功';
        $return['status']=0;
        $return['data']=$data_money;
        return $return;
    }

    /**
     * Created by zyjun
     * Info:判断余额是否还可以平分红包，如果可以平分，那么就继续把平分的均值，添加到上一个红包集合里面去，直到平分后的最小余额小于$min_money
     *
     */
    public function addMinRedPacket($remain_money,$total_num,$min_money){
        $average_money=$remain_money/$total_num;
        if($average_money>$min_money){ //还可以平分 返回平分均值
            $data['money']=$average_money;
            $data['status']=0;
        }else{ //不可以平分，直接算到第一个数组上。最大100人，不足0.01才1元钱。
            $data['money']=$remain_money; //最后一次传进来的余额
            $data['status']=1;
        }
        return $data;
    }


    /**
     * Created by zyjun
     * Info:需求技能列表
     */
    public function skillList(){
        $res=Db::name('skill')->select();
        apiSuccess('技能列表',$res);

    }


    /**
     * Created by zyjun
     * 这个接口只用来查询红包领取数据，或者过期，抢光了，自己领取了等数据，不涉及抢操作，不涉及并发处理
     *
     */
    public function getRedPacked(){
        $uid = input('post.id');
        $token = input('post.token');
        $receive_red_token=input('post.red_token');
        $res = $this->checkToken($uid, $token);
        if ($res['status']) {
            return apiError($res['msg'],'',$res['code']);
        }
        //验证权限
        $res=$this->isAccess($uid);
        if($res['identity_status']!=2){
            return apiError($res['msg'],$res['identity_status']);
        }
        if($receive_red_token==''){
            return apiError('红包Token异常！');
        }
        if($this->checkRedToken($receive_red_token)){
            return apiError('红包Token非法！');
        }
        $res=Db::name('redpacket')->where('red_token',$receive_red_token)->find();
        $rid=$res['id'];
        $is_over=$res['is_over'];
        $is_back=$res['is_back'];
        $money_num=$res['money_num'];
        if(empty($res)){
            return apiError('红包不存在');
        }

        //判断红包有效期 1小时=3600  1天=86400  1周  //过期的红包也可能是自己领取过的，需要给他返回领取数据
        //开始处理
        $redis = new \Redis();
        $redis->connect(self::$redis_host, 6379);
        $redis->auth(self::$redis_pass);
        $redis->select(self::$redis_db);
        //判断key是否存在
        $key=self::$pre_key.$rid;
        $res=$redis->exists($key);
        if(empty($res)){ //redis数据已经被删除了或者过期自动删除了，直接返回过期，理论上长期不领取的红包领取不会进入到这一步
            $recive_data=$this->redPacketReciveData($uid,$rid);
            if($recive_data['is_get']){
                return apiError('已经领取过红包啦',$recive_data,203);
            }else{
                return apiError('红包已过期',$recive_data,200);
            }
        }
        //是否抢过红包
        $res=$redis->sIsMember($key,$uid);//判断某个key是否包含某个成员
        if($res){//
            $recive_data=$this->redPacketReciveData($uid,$rid);
            return apiError('已经领取过红包啦',$recive_data,203);
        }
        //判断是否抢光了，没抢光，没过期，但是用户删除了红包，或者红包被退回了，都提示红包抢光了
        if($is_over==1){
            $recive_data=$this->redPacketReciveData($uid,$rid);
            if($recive_data['is_get']){
                return apiError('已经领取过红包啦',$recive_data,203);
            }else{
                return apiSuccess('红包已经抢光啦',$recive_data,201);
            }
        }
        if($is_back==1){
            $recive_data=$this->redPacketReciveData($uid,$rid);
            if($recive_data['is_get']){
                return apiError('已经领取过红包啦',$recive_data,203);
            }else{
                return apiSuccess('红包已经抢光啦',$recive_data,201);
            }
        }
        //不满足上面要求表示有红包，不掉这个接口
    }

    /**
     * Created by zyjun
     * 只处理了并发下的超发问题，没处理超发对服务器的影响，仍然在进行mysql查询
     *
     */
    public function openRedPacked(){
        $uid = input('post.id');
        $token = input('post.token');
        $receive_red_token=input('post.red_token');
        $res = $this->checkToken($uid, $token);
        if ($res['status']) {
            return apiError($res['msg'],'',$res['code']);
        }
        //验证权限
        $res=$this->isAccess($uid);
        if($res['identity_status']!=2){
            return apiError($res['msg'],$res['identity_status']);
        }
        if($receive_red_token==''){
            return apiError('红包Token异常！');
        }
        if($this->checkRedToken($receive_red_token)){
            return apiError('红包Token非法！');
        }
        $res=Db::name('redpacket')->where('red_token',$receive_red_token)->find();
        if(empty($res)){
            return apiError('红包不存在');
        }
        $rid=$res['id'];
        $sent_time=$res['sent_time'];
        $is_over=$res['is_over'];
        $money_num=$res['money_num'];
        $type=$res['type'];
        $user_sex=Db::name('user')->where('id',$uid)->value('sex');
        if($type!=3){
            if($user_sex!=$type){
                if($type==0){
                    return apiError('此红包美女才能领取哦','',100);
                }
                if($type==1){
                    return apiError('此红包帅哥才能领取哦','',101);
                }
            }
        }
        //开始处理
        $redis = new \Redis();
        $redis->connect(self::$redis_host, 6379);
        $redis->auth(self::$redis_pass);
        $redis->select(self::$redis_db);
        //判断key是否存在
        $key=self::$pre_key.$rid;
        $res=$redis->exists($key);
        if(empty($res)){ //redis数据已经被删除了或者过期自动删除了，直接返回过期，理论上长期不领取的红包领取不会进入到这一步
            $recive_data=$this->redPacketReciveData($uid,$rid);
            if($recive_data['is_get']){
                return apiError('已经领取过红包啦',$recive_data,203);
            }else{
                return apiError('红包已过期',$recive_data,200);
            }
        }
        //不管多少人就来都开始写redis,直到红包个数相等,通过这里redis快速缓存，可以抵挡大部分流量
        $nums=$redis->sCard($key);  //取出集合里的个数
        $money_nums=$money_num+1;
        if($nums==$money_nums){ //表示已经领取完毕了，
            $recive_data=$this->redPacketReciveData($uid,$rid);
            if($recive_data['is_get']){
                return apiError('已经领取过红包啦',$recive_data,203);
            }else{
                return apiSuccess('红包已经抢光啦',$recive_data,201);
            }
        }
        //没过期没抢完毕，写入红包
        $redis->sAdd($key,$uid); //写入用户数据
        //上面这一步出现超发几率几乎没有，因为是单进程，redis也是单进程，但是下面仍然用mysql排它锁来严格执行，mysql事务中再判断下红包个数
        //开始事务处理，查询用户是否领取过红包，没有领取过就，先判断红包是否领取完毕，然后再写入领取数据，
        Db::startTrans();
        $res=Db::name('redpacket')->where('id',$rid)->lock(true)->find();
        $recive_data=$this->redPacketReciveData($uid,$rid);
        if($recive_data['is_get']){
            return apiError('已经领取过红包啦',$recive_data,203);
        }
        if($res['is_over']==1){
            if($recive_data['is_get']){
                return apiError('已经领取过红包啦',$recive_data,203);
            }else{
                return apiSuccess('红包已经抢光啦',$recive_data,201);
            }
        }
        //把用户写入mysql,同时给用户账户加钱
        try{
            $res=$this->setPacketReciveDataUid($rid,$uid);  ////把用户写入mysql
            if($res){
                // 回滚事务
                Db::rollback();
                return apiError('领取红包失败');
            }
            //给领取的用户加红包到钱包里
            $res=$this->addRedPacketToWallet($rid,$uid);
            if($res['status']){
                // 回滚事务
                Db::rollback();
                return apiError($res['msg']);
            }
            //写入后判断写入的uid个数，等于红包个数，设置红包over
            $uids_num=$this->redpacket_isover($rid,$uid);
            if($uids_num==$money_num){
                Db::name('redpacket')->where('id', $rid)->update(['is_over' => 1]); //抢完了
            }
            //返回红包领取数据
            $recive_data=$this->redPacketReciveData($uid,$rid);
            apiSuccess('红包领取数据',$recive_data,202);
            // 提交事务
            Db::commit();
        } catch (\Exception $e){
            // 回滚事务
            Db::rollback();
            return apiError('领取红包失败');
        }
    }

    /**
     * Created by zyjun
     * Info:返回红包领取数据
     */
    public function redPacketReciveData($user_id,$rid){
        $res=Db::name('redpacket')->where('id',$rid)->value('data');
        $res=json_decode($res,true);
        if(!empty($res)){
            $self=array('money'=>'','uid'=>'','head_pic'=>'');
            $data=array();
            foreach ($res as $key=>$val){
                $uid=$val['uid'];
                if(!empty($uid)){
                    $data[$key]['money']=$val['money'];
                    $data[$key]['uid']=$val['uid'];
                    $data[$key]['true_name']=$data[$key]['nickname']=Db::name('user')->where('id',$uid)->value('nickname');
                    $head_pic=Db::name('user')->where('id',$uid)->value('head_pic');
                    $data[$key]['head_pic']='';
                    if(!empty($head_pic)){
                        $data[$key]['head_pic']=$this->addApiUrl($head_pic);
                    }
                    if($uid==$user_id){
                        $self=$data[$key]; //保存个人的数据
                    }
                }
            }
            $return['data_list']=$data;
            $return['self']=$self;
            if($return['self']['uid']!=''){
                $return['is_get']=1;
            }else{
                $return['is_get']=0;
            }
            return $return;
        }else{
            $return['data_list']='';
            $return['is_get']=0;
            return $return;
        }
    }

    /**
     * Created by zyjun
     * Info:写入领取人的uid
     */
    public function setPacketReciveDataUid($rid,$uid){
        $res=Db::name('redpacket')->where('id',$rid)->value('data');
        $res=json_decode($res,true);
        if(!empty($res)){
            foreach ($res as $key=>$val){
                if($val['uid']==''){ //查询到为空时，在这个key插入uid
                    $res[$key]['uid']=$uid;
                    break;
                }
            }
            $data['data']=json_encode($res);
            $res=Db::name('redpacket')->where('id',$rid)->update($data); //写入数据
            if($res===false){
                return 1;
            }
        }
        return 0;
    }

    /**
     * Created by zyjun
     * Info:给领取红包的用户，添加红包到账户
     */
    public function addRedPacketToWallet($rid,$uid){
        $res=Db::name('redpacket')->where('id',$rid)->field('id,did,money,data,is_solve')->find();
        $money=$res['money'];
        $red_packet='';
        $red_packet_id=$res['id'];
        $sid=$res['did'];
        $is_solve=$res['is_solve'];
        $recive_data=json_decode($res['data'],true);
        if(!empty($recive_data)){
            foreach ($recive_data as $key=>$val){
                if($val['uid']==$uid){
                    $red_packet= $val['money'];
                    break;
                }
            }
        }
        if($is_solve==0){ //不需要解决，立刻添加到钱包 ,需要解决处理的直接返回领取成功，在问题真正解决的时候再转入钱包
            if($red_packet>$money){
                $return['msg']='领取红包失败';  //红包金额异常
                $return['status']=1;
                return $return;
            }
            $wallet=Db::name('user_wallet')->where('uid',$uid)->find(); //添加红包到账户
            if(empty($wallet)){ //用户钱包还未创建
                $wallet_data['uid']=$uid;
                $wallet_data['money']='';
                Db::name('user_wallet')->insert($wallet_data);
            }
            $new_money=$red_packet+$this->getNowWallet($uid);
            $wallet=Db::name('user_wallet')->where('uid',$uid)->update(['money'=>$new_money]);
            if(empty($wallet)) {
                $return['msg']='领取红包失败';  //红包金额异常
                $return['status']=1;
                return $return;
            }
            $this->redPacketRecordDetail($uid,$red_packet_id,2,$sid,1,$red_packet,$new_money,1,'领取红包');
        }
        $return['msg']='领取红包成功';  //红包金额异常
        $return['status']=0;
        return $return;
    }

    //再次查看红包里是否领取完毕了
    public function redpacket_isover($rid,$uid){
        $res=Db::name('redpacket')->where('id',$rid)->value('data');
        $res=json_decode($res,true);
        $uids=0;
        if(!empty($res)){
            foreach ($res as $key=>$val){
                if($val['uid']!=''){
                    $uids=$uids+1;
                }
            }
        }
        return $uids;
    }


}
