<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/2/7 0007
 * Time: 11:18
 * info: 支付业务逻辑
 */

namespace app\apps2\controller;
use think\Controller;
use think\Db;
class Pay extends Common
{
    /**
     * Created by zyjun
     * Info:充值,产生支付宝交易信息，写入数据库
     */
    public function getRechargeOrderInfo()
    {
        $uid = $data['uid'] = input('post.id');
        $token = input('post.token');
        $money = input('post.money');
        $type = $data['order_type'] = input('post.type');  //
        $res = $this->checkToken($uid, $token);
        if ($res['status']) {
            return apiError($res['msg'], '', $res['code']);
        }
        //验证权限
        $res = $this->isAccess($uid);
        if ($res['identity_status'] != 2) {
            return apiError($res['msg'],$res['identity_status']);
        }
        //检测红包金额格式和余额
        if (!in_array($type, array(1, 2, 3))) {
            return apiError('支付类型错误！');
        }
        if ($this->checkRedPacket($money)) {
            return apiError('请输入整数或2位小数金额');
        }
        if ($money == '') {
            return apiError('请输入交易金额！');
        }
        if ($money < 0.01) {
            return apiError('交易金额需大于0.01元！');
        }
        if ($money > 50000) {
            return apiError('单次交易金额不能大于50000元');
        }
        $money=abs($money);
        $data['order_money'] =$money;
        $out_biz_no = $this->createBusinessNo(); //产生内部交易号
        $data['order_out_biz_no'] = $out_biz_no;
        $data['is_success'] = 0;
        $data['time'] = date('Y-m-d H:i:s', time());
        //支付宝支付
        if($type==1){
            $alipay = new Alipay();
            $response = $alipay->payOrder('充值-粒米校园', $money, $out_biz_no,'aliPayAsyncNotice');
            $res = Db::name('recharge')->insert($data);
            if (empty($res)) {
                return apiError('获取内部交易号失败');
            }
            apiSuccess('支付宝订单信息', $response);
        }
        //微信支付
        if($type==2){
            $order['body']='充值-粒米校园';
            $order['out_biz_no']=$out_biz_no;
            $order['trade_type']='APP';
            $order['total_fee']=$money; //订单总额
            $wxpay= new Wxpay();
            $res = $wxpay->payOrder($order,'wxPayAsyncNotice');
            if($res['status']){
                return apiError('获取内部交易号失败');
            }
            $response=$res['data'];
            $res = Db::name('recharge')->insert($data);
            if (empty($res)) {
                return apiError('获取内部交易号失败');
            }

            apiSuccess('微信订单信息', $response);
        }

    }


    /**
     * Created by zyjun
     * Info:支付成功后获取支付结果
     * 如果异步回调失败会发起查询
     */
    public function getPayStaus()
    {
        $uid = $data['uid'] = input('post.id');
        $token = input('post.token');
        $out_biz_no = input('post.out_biz_no'); //平台订单号【后台自己生成的】
        $type=input('post.type'); //1支付宝 2：微信
        $res = $this->checkToken($uid, $token);
        if ($res['status']) {
            return apiError($res['msg'], '', $res['code']);
        }
        if(!in_array($type,array(1,2))){
            return apiError('type参数错误');
        }
        //事务处理
        Db::startTrans();
        $res = Db::name('recharge')->where('order_out_biz_no', $out_biz_no)->field('uid,is_success,order_money')->lock(true)->find();
        if (empty($res)) {
            return apiError('支付失败');
        }
        if($uid!=$res['uid']){
            return apiError('非法查询');
        }
        $money = $res['order_money'];
        $is_success = $res['is_success'];
        if ($is_success == 0) { //支付失败或者没有收到回调通知，主动去查询支付状态
            if($type==1){ //查询支付宝状态
                $alipay = new Alipay();
                $res=$alipay->tradeStatus($out_biz_no);
                if($res['status']){ //查询失败，直接返回支付失败
                    return apiError('支付失败');
                }
                if($res['trade_status']!='TRADE_SUCCESS'){
                    return apiError('支付失败');
                }
                //支付成功 修改订单状态，添加账户金额
                $trade_data=$res['data'];
                $res=$this->addRechargeMoney($uid,$type,$out_biz_no,$trade_data);
                if($res['status']){
                    return apiError('支付失败');
                }
                Db::commit();  //提交事务。
            }
            if($type==2){ //查询微信支付状态
                $wxpay= new Wxpay();
                $res=$wxpay->tradeStatus($out_biz_no);
                if($res['status']){
                    return apiError('支付失败');
                }
                //支付成功 修改订单状态，添加账户金额
                $trade_data=$res['data'];
                $res=$this->addRechargeMoney($uid,$type,$out_biz_no,$trade_data);
                if($res['status']){
                    return apiError('支付失败');
                }
                Db::commit();  //提交事务。
            }
        }
        apiSuccess('支付成功', $money);
    }


    /**
     * Created by zyjun
     * Info:添加金额到钱包
     * 验证时，比对本地数据库和支付查询返回的金额和订单号以及验证用户id
     * $uid:登录用户id ,$out_biz_no:内部平台订单号,$pay_data：在线查询是否支付后返回的订单数据
     */
    public function addRechargeMoney($uid,$type,$out_biz_no,$trade_data){
        if($type==1){ //支付宝
            $this->recharge_log(1,$out_biz_no,$trade_data,'支付宝支付状态数据-查询');
            $res=Db::name('recharge')->where('order_out_biz_no', $out_biz_no)->field('uid,is_success,order_money')->find();
            if($uid!=$res['uid']){
                $re['status']=1;
                $re['msg']='非法查询';
                return $re;
            }
            $recharge_money=$res['order_money'];
            $trade_money=$trade_data['total_amount'];
            if($recharge_money!=$trade_money){ //正常的交易记录，2者相等，防止恶意输入订单号查询
                $re['status']=1;
                $re['msg']='非法查询';
                return $re;
            }
            //开始添加充值金额到钱包
            $wallet=$this->getNowWallet($uid);
            $wallet=$wallet+$recharge_money;
            //再次判断充值状态，防止异步回调重复写入
            $is_success=Db::name('recharge')->where('order_out_biz_no', $out_biz_no)->value('is_success');
            if($is_success){
                $re['status']=0;
                $re['msg']='支付成功';
                return $re;
            }
            $res=Db::name('user_wallet')->where('uid',$uid)->setField('money',$wallet); //写入钱包
            $recharge['order_no']=$trade_data['trade_no'];
            $recharge['order_time']=$trade_data['send_pay_date'] ; //支付时间
            $recharge['is_success']=1;
            //修改支付状态
            Db::name('recharge')->where('order_out_biz_no',$out_biz_no)->update($recharge);
            //钱包财务流水记录
            $wallet=$this->getNowWallet($uid);
            $this->walletRecordDetail($uid,'',1,1,'',$recharge_money,$wallet,1,'支付宝充值');
            //充值成功记录
            $this->recharge_log(1,$out_biz_no,$trade_data,'支付宝充值成功记录-查询');
            $re['status']=0;
            $re['msg']='支付成功';
            return $re;
        }
        if($type==2){ //微信支付
            $this->recharge_log(1,$out_biz_no,$trade_data,'微信支付状态数据-查询');
            $res=Db::name('recharge')->where('order_out_biz_no', $out_biz_no)->field('uid,is_success,order_money')->find();
            if($uid!=$res['uid']){
                $re['status']=1;
                $re['msg']='非法查询';
                return $re;
            }
            $recharge_money=$res['order_money'];
            $trade_money=$trade_data['cash_fee']/100;
            if($recharge_money!=$trade_money){ //正常的交易记录，2者相等，防止恶意输入订单号查询
                $re['status']=1;
                $re['msg']='非法查询';
                return $re;
            }
            //开始添加充值金额到钱包
            $wallet=$this->getNowWallet($uid);
            $wallet=$wallet+$recharge_money;
            //再次判断充值状态，防止异步回调重复写入
            $is_success=Db::name('recharge')->where('order_out_biz_no', $out_biz_no)->value('is_success');
            if($is_success){
                $re['status']=0;
                $re['msg']='支付成功';
                return $re;
            }
            $res=Db::name('user_wallet')->where('uid',$uid)->setField('money',$wallet); //写入钱包
            $recharge['order_no']=$trade_data['transaction_id'];
            $recharge['order_time']=date('Y-m-d H:i:s',time()) ; //支付时间
            $recharge['is_success']=1;
            //修改支付状态
            Db::name('recharge')->where('order_out_biz_no',$out_biz_no)->update($recharge);
            //钱包财务流水记录
            $wallet=$this->getNowWallet($uid);
            $this->walletRecordDetail($uid,'',1,1,'',$recharge_money,$wallet,1,'微信充值');
            //充值成功记录
            $this->recharge_log(1,$out_biz_no,$trade_data,'微信充值成功记录-查询');
            $re['status']=0;
            $re['msg']='支付成功';
            return $re;
        }
    }


    /**
     * Created by zyjun
     * Info:提现金额默认大于1元  大于200元进入审核
     */
    public function withdrawCash(){
        $uid = input('post.id');
        $token = input('post.token');
        $money=input('post.money');
        $account=input('post.account');
        $true_name=input('post.true_name');
        $code=input('post.code');
        $res = $this->checkToken($uid, $token);
        if ($res['status']) {
            return apiError($res['msg'],'',$res['code']);
        }
        //验证权限
        $res=$this->isAccess($uid);
        if($res['identity_status']!=2){
            return apiError($res['msg'],$res['identity_status']);
        }

        if(checkCode($code)){
            return apiError('验证码格式错误');
        }
        $res=Db::name('user')->where('id',$uid)->field('code,code_time')->find();
        if(empty($res)){
            apiError('请先发送验证码！');
            return;
        }
        if($code!=$res['code']){
            apiError('验证码错误！');
            return;
        }
        $time=time()-strtotime($res['code_time']);
        if($time>600){
            apiError('验证码已过期！');
            return;
        }
        //判断是否存在审核的提现申请
        Db::startTrans();
        $res=Db::name('withdraw_cash_verify')->where('uid',$uid)->where('status',0)->lock(true)->find();
        if(!empty($res)){
            return apiSuccess('您已提交过提现申请，请等待申请处理完毕后再提现');
        }
        //高并发处理，开启事务，锁表行，防止提现后个人钱包余额没能及时扣除，多次提现的情况
        $wallet=Db::name('user_wallet')->where('uid',$uid)->lock(true)->value('money');
        //先检测财务流水是否异常
        if($this->checkWalletWaterflow($uid)==false){
            $data['uid']=$uid;
            $data['order_account']=$account;
            $data['order_account_name']=$true_name;
            $data['order_money']=$money;
            $data['order_type']=1;
            $data['order_code']='';
            $data['order_msg']='财务流水异常，禁止提现';
            $data['order_out_biz_no']='';
            $data['is_success']=0;
            $data['time']=date('Y-m-d H:i:s',time());
            Db::name('withdraw_cash')->insert($data);   //记录提现失败原因
            Db::commit();
            return apiError('提现异常，请联系管理员处理');
        }
        $new_money=$wallet-$money;
        if($new_money<0){
            apiError('余额不足！');
            return;
        }

        if($this->checkInt($money,2,4)){
            return apiError('提现金额参数错误');
        }

        $money=abs($money);
        if($money<10){
            return apiError('最低提现金额为10元');
        }
        if($money>1000){
            return apiError('单次最高提现金额为1000元');
        }
        $alipay_account=$this->checkAccountType($account);
        if($alipay_account==3){
            return apiError('支付宝账户为邮箱或手机号');
        }
        if($alipay_account==1){
            if(checkMobile($account)){
                return apiError('手机号格式错误');
            }
        }
        if($alipay_account==2){
            $length=strlen($account);
            if($length>30){
                return apiError('邮箱长度超出限制');
            }
        }
        if($this->checkTrueName($true_name)){
            return apiError('请输入中文或英文姓名');
        }
        //限制提现错误次数
        $res=Db::name('withdraw_cash')->where('uid',$uid)->where('is_success',0)->whereTime('time', 'today')->select();
        if(!empty($res)){
            if(count($res)>5){
                return apiError('提现错误超过5次,请明日再试');
            }
        }
        $res=Db::name('withdraw_cash')->where('uid',$uid)->where('is_success',1)->whereTime('time', 'week')->select();
        if(!empty($res)){
            if(count($res)>0){
                return apiError('每周只能提现一次');
            }
        }
        if($money>200){ //大于200元进入后台审核
            $data['uid']=$uid;
            $data['mobile']=Db::name('user')->where('id',$uid)->value('mobile');
            $data['name']=$true_name;
            $data['type']=1;
            $data['account']=$account;
            $data['money']=$money;
            $data['create_time']=date('Y-m-d H:i:s',time());
            Db::name('withdraw_cash_verify')->insert($data);
            //扣除账户金额,不扣除他还可以用余额去发红包
            Db::name('user_wallet')->where('uid',$uid)->update(['money'=>$new_money]);  //先扣钱
            Db::commit();  //提交事务。
            $sms=new Sms();
            $sms->sysSmsNotice('金额'.$money.'元',2);
            return apiSuccess('提现申请已提交,资金将在审核通过后发放到支付宝账户','',100);
        }
        //开始事务处理，提现失败会事务回滚
        try{
            Db::name('user_wallet')->where('uid',$uid)->update(['money'=>$new_money]);  //先扣钱
            $this->walletRecordDetail($uid,'',1,'','',$money,$new_money,0,'支付宝提现'); //记录个人财务流水
            $alipay=new Alipay();
            $out_biz_no=$this->createBusinessNo();
            $res=$alipay->getCash($money,$account,$true_name,$out_biz_no);  //提现
            if($res['status']){
                Db::rollback(); //提现失败直接事务回滚,回滚后记录提现失败原因
                $data['uid']=$uid;
                $data['order_account']=$account;
                $data['order_account_name']=$true_name;
                $data['order_money']=$money;
                $data['order_type']=1;
                $data['order_code']=$res['code'];
                $data['order_msg']=$res['msg'];
                $data['order_out_biz_no']=$res['out_biz_no'];
                $data['is_success']=0;
                $data['time']=date('Y-m-d H:i:s',time());
                Db::name('withdraw_cash')->insert($data);   //记录提现失败原因
                return apiError('提现失败:'.$res['msg']);
            }
            // 提交事务
            Db::commit();  //扣钱完毕，提交事务。
        } catch (\Exception $e){
            // 回滚事务
            Db::rollback();
            return apiError('提现失败');
        }
        //记录财务流水
        $data['uid']=$uid;
        $data['order_account']=$account;
        $data['order_account_name']=$true_name;
        $data['order_money']=$money;
        $data['order_type']=1;
        $data['order_code']=$res['code'];
        $data['order_msg']=$res['msg'];
        $data['order_no']=$res['order_id'];
        $data['order_time']=$res['pay_date'];
        $data['order_out_biz_no']=$res['out_biz_no'];
        $data['is_success']=1;
        $data['time']=date('Y-m-d H:i:s',time());
        Db::name('withdraw_cash')->insert($data);  //记录交易流水

        $sms=new Sms();
        $sms->sysSmsNotice('金额'.$money.'元',1);
        return apiSuccess('提现成功,请注意查收支付宝通知');
    }


    /**
     * Created by zyjun
     * Info:在线支付订单生成，不涉及钱包充值,通用产品购买接口
     * order表负责记录不同类型的订单和订单状态，其余的比如weekend_order记录自身详细订单信息，不记录订单状态
     */
    public function  getOnlinePayOrderInfo($pay_type,$goods_name,$money,$out_biz_no){
        if($pay_type==1){
            $alipay = new Alipay();
//            $res=$alipay->createOrder($goods_name, $goods_name, $money, $out_biz_no); //估计是签约功能受限制了，暂时无法创建，到支付宝产品中心查看是否受限制了
//            if($res){
//                $re['status']=1;
//                $re['msg']='创建支付宝订单失败';
//                return $re;
//            }
            $response = $alipay->payOrder($goods_name,$money, $out_biz_no,'aliOnlinePayAsyncNotice');
            $re['status']=0;
            $re['msg']='支付宝订单信息';
            $re['data']=$response;
            return $re;
        }
        //微信支付
        if($pay_type==2){
            $order['body']=$goods_name;
            $order['out_biz_no']=$out_biz_no;
            $order['trade_type']='APP';
            $order['total_fee']=$money; //订单总额
            $wxpay= new Wxpay();
            $res = $wxpay->payOrder($order,'wxOnlinePayAsyncNotice');
            if ($res['status']) {
                $re['status']=1;
                $re['msg']='获取内部交易号失败';
                return $re;
            }
            $response=$res['data'];
            $re['status']=0;
            $re['msg']='微信订单信息';
            $re['data']=$response;
            return $re;
        }
    }


    /**
     * Created by zyjun
     * Info:支付成功后获取支付结果
     * 异步回调失败情况下会查询支付订单状态
     */
    public function getOnlinePayStaus()
    {
        $uid = $data['uid'] = input('post.id');
        $token = input('post.token');
        $out_biz_no = input('post.out_biz_no'); //平台订单号【后台自己生成的】
        $res = $this->checkToken($uid, $token);
        if ($res['status']) {
            return apiError($res['msg'], '', $res['code']);
        }
        //事务处理
        Db::startTrans();
        $res = Db::name('order')->where('order_out_biz_no', $out_biz_no)->field('uid,pay_status,money,pay_type')->lock(true)->find();
        if (empty($res)) {
            return apiError('支付失败');
        }
        $pay_type=$res['pay_type'];
        if($uid!=$res['uid']){
            return apiError('非法查询');
        }
        $money = $res['money'];
        $pay_status = $res['pay_status'];
        if ($pay_status == 0) { //支付失败或者没有收到回调通知，主动去查询支付状态
            if($pay_type==1){ //查询支付宝状态
                $alipay = new Alipay();
                $res=$alipay->tradeStatus($out_biz_no);
                if($res['status']){ //查询失败，直接返回支付失败
                    return apiError('支付失败');
                }
                if($res['trade_status']!='TRADE_SUCCESS'){
                    return apiError('支付失败');
                }
                $noticefy_data=$res['data'];
                //修改订单表支付状态
                $update['pay_status']=1;
                $update['order_status']=1;
                $update['order_no']=$noticefy_data['trade_no']; //保存交易单号
                $update['notify_time']=date('Y-m-d H:i:s',time());
                Db::name('order')->where('order_out_biz_no', $out_biz_no)->update($update);
                Db::commit();  //扣钱完毕，提交事务。
                return apiSuccess('支付成功', $money);
            }
            if($pay_type==2){ //查询微信支付状态
                $wxpay= new Wxpay();
                $res=$wxpay->tradeStatus($out_biz_no);
                if($res['status']){
                    return apiError('支付失败');
                }
                $noticefy_data=$res['data'];
                //支付成功 修改订单状态，
                $update['pay_status']=1;
                $update['order_status']=1;
                $update['order_no']=$noticefy_data['transaction_id']; //保存交易单号
                $update['notify_time']=date('Y-m-d H:i:s',time());
                Db::name('order')->where('order_out_biz_no', $out_biz_no)->update($update);
                Db::commit();  //提交事务。
                return apiSuccess('支付成功', $money);
            }
        }
        apiSuccess('支付成功');

    }

    /**
     * Created by zyjun
     * Info:重新支付订单
     * 重新支付订单可以，重新选择支付方式【支付宝，微信】，支付完毕后更新order表里面的order_pay_type
     */
    public function orderRePayment(){
        $uid=input('post.id');
        $token=input('post.token');
        $shop_order_no = input('post.shop_order_no'); //平台订单号【后台自己生成的】
        $pay_type = input('post.pay_type');  //重新支付的方式
        //用户验证
        $res = $this->checkToken($uid, $token);
        if ($res['status']) {
            return apiError($res['msg'], '', $res['code']);
        }
        //验证订单号格式
        if ($this->checkShopOrderNo($shop_order_no)){
            return apiError('订单号格式错误');
        }
        $res = Db::name('order')->where('shop_order_no', $shop_order_no)->field('uid,order_out_biz_no,pay_status,money,order_status,pay_type')->find();
        if (empty($res)) {
            return apiError('订单不存在');
        }
        if($uid!=$res['uid']){
            return apiError('非法操作');
        }
        if($res['order_status']!=0){ //必须订单业务逻辑状态是未支付，才去发起支付
            return apiError('订单状态异常');
        }
        if($res['pay_status']!=0){ //支付逻辑里也必须是未支付，不然可能存在已经支付，但是订单逻辑状态没更新的情况
            return apiError('订单支付状态异常');
        }
        $money=$res['money'];
        if($money<0||$money>100000){
            return apiError('订单价格异常');
        }
        if(!in_array($pay_type,[1,2])){  //交易类型  1：支付宝  2：微信  3：银行卡
            return apiError('支付方式参数错误');
        }
        //重新发起支付,如果订单价格变动了【比如手动修改了订单价格】,微信重新生成$out_biz_no，，微信价格变化会提示订单重复。所以这里全部重新生成平台单号提交
        $out_biz_no=$this->createBusinessNo();
        $updata['pay_type']=$pay_type;
        $updata['order_out_biz_no']=$out_biz_no;
        Db::name('order')->where('shop_order_no', $shop_order_no)->update($updata); //重新记录支付方式
        $order_name="商品购买-粒米校园"; //默认都用这个
        $pay = new Pay();
        $res=$pay->getOnlinePayOrderInfo($pay_type,$order_name,$money,$out_biz_no);
        if($res['status']){
            return apiError($res['msg']);
        }
        apiSuccess($res['msg'],$res['data']);
    }


    /**
     * Created by zyjun
     * Info:检测财务流水是否异常，每次提现之前检测，提现之前检测
     */
    public function checkWalletWaterflow($uid){
        $in_money=Db::name('wallet_record')->where('uid', $uid)->where('type', 1)->sum('money');
        $out_money=Db::name('wallet_record')->where('uid', $uid)->where('type', 0)->sum('money');
        $in_money=round($in_money,2); //流水总进账
        $out_money=round($out_money,2);//流水总出账
        $wallet=round($this->getNowWallet($uid),2); //钱包余额
        $wallet2=round($in_money-$out_money,2);
        if($wallet2==$wallet){
            return true;
        }
        return false;
    }
    /**
     * Created by zyjun
     * Info:获取在线支付名称，客户端显示的商品名称
     * 多个订单的时候注意合并订单名称，不超过255个字符
     * 待定
//     */
//    public function getOrderGoodsName($shop_order_no){
//        switch ($goods_type){
//            case 1:$dbname='weekend';break;
//            case 2:$dbname='';break;
//            default:$dbname='';break;
//        }
//        if($dbname==''){
//            $re['status']=1;
//            $re['msg']='订单状态异常';
//            return $re;
//        }
//        if($dbname=='weekend'){
//            $weekend_id=Db::name('weekend_order')->where('order_num',$out_biz_no)->value('weekend_id'); //查询订单对应的详情订单ID
//            if(empty($weekend_id)){
//                $re['status']=1;
//                $re['msg']='订单状态异常';
//                return $re;
//            }
//            $order_name=Db::name('weekend')->where('id',$weekend_id)->value('name'); //查询订单对应的详情商品名称
//            if(empty($order_name)){
//                $re['status']=1;
//                $re['msg']='订单参数异常';
//                return $re;
//            }
//            if(strlen($order_name)>255){
//                $re['status']=1;
//                $re['msg']='订单参数异常';
//                return $re;
//            }
//            $re['status']=0;
//            $re['msg']='订单名称';
//            $re['data']=$order_name;
//            return $re;
//        }

//    }
}