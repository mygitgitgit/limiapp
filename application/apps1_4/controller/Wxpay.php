<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/2/7 0007
 * Time: 11:18
 * info: 微信支付
 */

namespace app\apps1_4\controller;
use think\Controller;
use think\Db;

require_once APP_EXTEND. 'WxpayApi/lib/WxPay.Api.php';

class Wxpay extends Common
{
    const  APPID = 'wxb750b949790baabc';
	const  MCHID = '1498442582';
    /**
     * Created by zyjun
     * Info:订单生成并支付,微信再次支付，要求body和商品价格，out_trade_no都不能变，不然会提示订单号重复
     * 也就是说如果要显示商品名称，那么如果商家修改了名称就无法再次付款了。这里如果创建新订单逻辑不合理。所以固定名称。
     * 在详情detail字段显示具体的商品内容。
     */
  public function payOrder($param,$notify_url){
      $order = new \WxPayUnifiedOrder();
      $order->SetBody($param['body']);
      $order->SetOut_trade_no($param['out_biz_no']);
      $order->SetTotal_fee($param['total_fee']*100); //放大100倍 微信单位是分
      $order->SetNotify_url(AppUrl."/index.php/apps/Wxpay/".$notify_url);
      $order->SetTrade_type("APP");
      $api=new \WxPayApi();
      $res=$api->unifiedOrder($order);
      $return_code=$res['return_code'];
      if($return_code!='SUCCESS'){
          $re['status']=1;
          $re['msg']=$res['return_msg'];
          return $re;
      }

      $data['appid']=$res['appid'];
      $data['partnerid']=$res['mch_id'];
      $data['noncestr']=$res['nonce_str'];
      $data['prepayid']=$res['prepay_id'];
      $data['package']='Sign=WXPay';
      $data['timestamp']=time();  //换成秒(10位数字)
      $DataBase=new \DataBase();  //自己创建一个类，继承基础WxPayDataBase
      $DataBase->getValue($data); //传入需要签名的值
      $data['sign'] =$DataBase->MakeSign(); //开始签名
      $data['out_biz_no']=$param['out_biz_no'];
      $re['status']=0;
      $re['msg']='创建微信订单成功';
      $re['data']=$data;
      return $re;
  }

    /**
     * Created by zyjun
     * Info:微信支付异步回调
     */
  public function wxPayAsyncNotice(){
      /**
       * Created by zyjun
       * Info:微信回调通知处理函数
       */
      //获取通知的数据,即便是签名错误也记录日志
      $api=new \WxPayApi();
      $DataBase=new \DataBase();
      $xml = file_get_contents('php://input');
      $input=$DataBase->FromXml($xml);
      $this->recharge_log(2,$input['out_trade_no'],$input,'微信支付回调数据1-异步');
      //验证回调通知
      $res=$api::notify(array('WxPayApi', 'callbackMethod'),$param);
        if($res==false){
            $this->recharge_log(2,$input['out_trade_no'],$input,'微信支付回调数据2-签名错误-异步');
            return;
        }
       $data=$res;
        //返回数据正常，处理逻辑
       if($data['result_code']!='SUCCESS'){ //支付失败，这里也返给微信信息
           $this->recharge_log(2,$data['out_trade_no'],$data,'微信支付回调数据3-支付状态错误-异步');
           $return['return_code']='SUCCESS'; //回复给微信收到响应，但是支付状态错误，业务逻辑停止执行
           $return['return_msg']='';
           $DataBase->getValue($return); //传入需要签名的值
           echo $DataBase->ToXml();
           return;
       }
       $appid=$data['appid'];
       $mch_id=$data['mch_id'];
       $total_amount=$data['total_fee']/100; //总金额 //微信传过来是分
       $out_biz_no=$data['out_trade_no']; //内部单号
       if($appid!=$this::APPID){
           $return['return_code']='FAIL'; //APPID错误
           $return['return_msg']='';
           $DataBase->getValue($return);
           echo $DataBase->ToXml($return);
           return;
       }
      if($mch_id!=$this::MCHID){  //MCHID错误
          $return['return_code']='FAIL';
          $return['return_msg']='';
          $DataBase->getValue($return);
          echo $DataBase->ToXml($return);
          return;
      }
      $res=Db::name('recharge')->where('order_out_biz_no',$out_biz_no)->field('order_money,uid')->find();
      if(empty($res)){
          $this->recharge_log(2,$out_biz_no,$data,'微信支付回调数据4-找不到内部订单-异步');
          return ; //都找不到订单
      }
      $order_money=$res['order_money']; //内部订单号金额
      $uid=$res['uid'];                 //内部订单购买人
      if($order_money!=$total_amount){
          $this->recharge_log(2,$out_biz_no,$data,'微信支付回调数据5-订单金额不匹配-异步');
          return ; //订单金额不匹配
      }
      //判断相同的订单是否已经存在了  用途trade_no判断，保存post信息到单独的表，app查询时用单独的接口
      $is_success=Db::name('recharge')->where('order_out_biz_no',$out_biz_no)->value('is_success');
      if($is_success==1){
          $return['return_code']='SUCCESS';
          $return['return_msg']='OK';
          $DataBase->getValue($return);
          echo $DataBase->ToXml($return);   //已经支付成功了，屏蔽重复通知
          return;
      }
      //验证成功之后，写入支付信息，设置支付状态为成功。给用户钱包加钱。
      $recharge['order_no']=$data['transaction_id'];
      $recharge['order_time']=$data['time_end']; //支付完成时间
      $recharge['is_success']=1;
      Db::name('recharge')->where('order_out_biz_no',$out_biz_no)->update($recharge);
      //给用户加钱
      $res=Db::name('user_wallet')->where('uid',$uid)->field('money')->find();
      if(empty($res)){ //没有账户
          Db::name('user_wallet')->insert(['uid'=>$uid,'money'=>0]);
      }
      $money=$res['money'];
      $money=$money+$total_amount;
      $res=Db::name('user_wallet')->where('uid',$uid)->setField('money',$money);
      $wallet=$this->getNowWallet($uid);
      $this->walletRecordDetail($uid,'',1,2,'',$total_amount,$wallet,1,'微信充值');
      //给微信返回信息
      $return['return_code']='SUCCESS';
      $return['return_msg']='OK';
      $DataBase->getValue($return);
      echo $DataBase->ToXml($return);
      $this->recharge_log(2,$out_biz_no,$data,'微信支付回调数据6-支付成功-异步');
  }

    /**
     * Created by zyjun
     * Info:微信回调通知处理函数  在线支付
     */
    public function wxOnlinePayAsyncNotice(){
        //获取通知的数据,即便是签名错误也记录日志
        $api=new \WxPayApi();
        $DataBase=new \DataBase();
        $xml = file_get_contents('php://input');
        $input=$DataBase->FromXml($xml);
        $this->orderPayLog(2,$input['out_trade_no'],$xml,'微信支付回调数据1-异步');
        //验证回调通知
        $res=$api::notify(array('WxPayApi', 'callbackMethod'),$param);
        if($res==false){
            $this->orderPayLog(2,$input['out_trade_no'],$input,'微信支付回调数据2-签名错误-异步');
            return;
        }
        $data=$res;
        //返回数据正常，处理逻辑
        if($data['result_code']!='SUCCESS'){ //支付失败，这里也返给微信信息
            $this->orderPayLog(2,$data['out_trade_no'],$data,'微信支付回调数据3-支付状态错误-异步');
            $return['return_code']='SUCCESS'; //回复给微信收到响应，但是支付状态错误，业务逻辑停止执行
            $return['return_msg']='';
            $DataBase->getValue($return); //传入需要签名的值
            echo $DataBase->ToXml();
            return;
        }
        $appid=$data['appid'];
        $mch_id=$data['mch_id'];
        $total_amount=$data['total_fee']/100; //总金额 //微信传过来是分
        $out_biz_no=$data['out_trade_no']; //内部单号
        if($appid!=$this::APPID){
            $return['return_code']='FAIL'; //APPID错误
            $return['return_msg']='';
            $DataBase->getValue($return);
            echo $DataBase->ToXml($return);
            return;
        }
        if($mch_id!=$this::MCHID){  //MCHID错误
            $return['return_code']='FAIL';
            $return['return_msg']='';
            $DataBase->getValue($return);
            echo $DataBase->ToXml($return);
            return;
        }
        $res=Db::name('order')->where('order_out_biz_no',$out_biz_no)->field('money,uid')->find();
        if(empty($res)){
            $this->orderPayLog(2,$out_biz_no,$data,'微信支付回调数据4-找不到内部订单-异步');
            return ; //都找不到订单
        }
        $order_money=$res['money']; //内部订单号金额
        $uid=$res['uid'];                 //内部订单购买人
        if($order_money!=$total_amount){
            $this->orderPayLog(2,$out_biz_no,$data,'微信支付回调数据5-订单金额不匹配-异步');
            return ; //订单金额不匹配
        }
        //判断相同的订单是否已经存在了  用途trade_no判断，保存post信息到单独的表，app查询时用单独的接口
        $pay_status=Db::name('order')->where('order_out_biz_no',$out_biz_no)->value('pay_status');
        if($pay_status==1){
            $return['return_code']='SUCCESS';
            $return['return_msg']='OK';
            $DataBase->getValue($return);
            echo $DataBase->ToXml($return);   //已经支付成功了，屏蔽重复通知
            return;
        }
        //验证成功之后，写入支付信息，设置支付状态为成功。
        $order['order_no']=$data['transaction_id'];
        $order['notify_time']=$data['time_end']; //支付完成时间
        $order['pay_status']=1;
        $order['order_status']=1;
        Db::name('order')->where('order_out_biz_no',$out_biz_no)->update($order);
        $this->orderPayLog(2,$input['out_trade_no'],$data,'微信支付回调数据6-支付成功-异步');
        //给微信返回信息
        $return['return_code']='SUCCESS';
        $return['return_msg']='OK';
        $DataBase->getValue($return);
        echo $DataBase->ToXml($return);


    }

    /**
     * Created by zyjun
     * Info:微信订单查询
     * $out_trade_no内部平台订单号
     */
  public function tradeStatus($out_biz_no){
      $input = new \WxPayOrderQuery();
      $input->SetOut_trade_no($out_biz_no);
      $api=new \WxPayApi();
      $res=$api->orderQuery($input);
      if($res['trade_state']!='SUCCESS'){
          $re['status']=1;
          $re['msg']='支付状态错误';
          return $re;
      }
      $appid=$res['appid'];
      $mch_id=$res['mch_id'];
     if($appid!=$this::APPID){
         $re['status']=1;
         $re['msg']='支付APPID错误';
         return $re;
     }
      if($mch_id!=$this::MCHID){
          $re['status']=1;
          $re['msg']='支付MCHID错误';
          return $re;
      }
      $re['status']=0;
      $re['msg']='支付成功';
      $re['data']=$res;
      return $re;

  }



}



