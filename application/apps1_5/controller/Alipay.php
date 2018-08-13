<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/1/29 0029
 * Time: 10:50
 * info: 支付宝提现
 */
namespace app\apps1_5\controller;
use think\Controller;
use think\Db;

require_once APP_EXTEND. 'Alipay/aop/AopClient.php';
require_once APP_EXTEND. 'Alipay/aop/request/AlipayFundTransToaccountTransferRequest.php';
require_once APP_EXTEND. 'Alipay/aop/SignData.php';
require_once APP_EXTEND. 'Alipay/aop/request/AlipayTradeAppPayRequest.php';
require_once APP_EXTEND. 'Alipay/aop/request/AlipayTradeQueryRequest.php';
require_once APP_EXTEND. 'Alipay/aop/request/AlipayTradeCreateRequest.php';

class Alipay extends Common
{   public $gatewayUrl='https://openapi.alipay.com/gateway.do';
    //appid
    public $appId='2017101609332281';
    //支付宝私钥
    public $rsaPrivateKey='MIIEowIBAAKCAQEA1Kd4TAc/mwGID3iSFpoD4OHq35a0jOdT/ZTgMGAy7RXgt9N0QlJP1DSmQiM4JsCh1iuNQxOzXrwfPMqaECKMYUKzI0iqXLUgGw9wl0nSXILXtLbOze9yUJPfeQohM0bf4Vts2ktPV8Al502P2StkczQLsRjnxvYAQilwg7s2FF+wqfVPxy437VbzeAwNjLbJvPbIm3Le1y66aIVLbXE/QVrRKF4PPnCrdd4h8UW2srE6X72FIC+tNC6UHv22DFdHrCmWWOJ34H+V9q5JeyzGBrNnOoOICxFZkoqwkllZWcm56ZJ3udVr5WbxMfdTOcWpWQlRVYyRUyHomv8fhLOrEwIDAQABAoIBAE4KY6BrLJGDH16mHd7DiNbXse16DiqDnpQ6NYgrVaUiWUS9CjZopLk41ScCa9H08a96bi4GpdYHbeNOavmu5BuxcbJqMXMyWWT05pnu0o92yRid2glDbY1apzFxYTvDL9gxSCJYbvbCImbfVQIa5ZNNr1i/zhd7Ih8lvA/r/W2+AM1FknJc14GZ1Zrsq1njHhKXKLRge3hC60lWN8/xUzPaI1sCWUZelFGV+SwDCadGMUK0pKmuZFXnvBMwx188dCtmWOfZsXkWJSlfw8Yt8QsKib6fZhhWQJ8Hcm0hOOFaKR5aF4BUF8Wteg3IEBl2H6a2M20k4HcTLdTD+Iz4iaECgYEA9TdQ3c+Zkmdv/vaRqGymjG3DflDwMZQRz4bynY11RMci1vaPeoSeKRxTL2dyFCBld8A7KdrdZmD51CksaHUoAUAJcvfFDp0i7C8AbvBexNgA3NeC3ufDXjj9mifg10Pt04FMWysGEHTvhw9Yjv2NF1Upnx2z2GrP8oN+AHaRHUMCgYEA3gGRELwDc9eys4bD6RhJzYzLhKr/K9VQwkK/bNlG7qkflDZdokoVJ6Zr7uivKVUa3c8e/YvmyEDkaKXcbkc9NC4WJpzwTxTEMfnIDA8U0S2NZSbyhIvmZPe7SdzzgrqC5m8NSNfgA5iwCzV4NFUwhbOj+0kuuD+CZIj415oy9fECgYBRkGe2kAIN/5fyH8PNWO6BEVWQY42xgAX4mHOE0nOqP+6nv/VzlD8jf4dv4iHA7hGyJl/HiURRdHpFBrj9udJnsAw0kJOcS8o881lajVuIcCzBSHIAgOisI5q/NvqDv9WQn5ZtUL9ApBS0QPd9AHt4wlwI0BFtMAIhMXms38NfDwKBgHJVrDhKHB0VAVukFFF/yMKruETjK/ePLMBfT+bnH7jaMQFL3n0uWibJdtzbyRooUmXZvcQmwPxxLzEV+qhw1/x/n7jTKpAPydtTIMvVGIuCQkfN/yh0RHvLehFYUbEKDVBP8S+Kvjwb7s5XA0kwdoTlN5a64ezSCH1ubXncWFKBAoGBANdTqViKaoEdVHqEMj9TZXgeqJWBCjl+QJgbPBd8w+/n9sOp6nk5RtcHJk9fYaxFBDtKv+guf+P8yZ3o0Ils7+AGNeQKRQZsCBkCrD0AkW/DbE9lE84ADRDMUMuj9ejM3a24ZEJE33jU1jnpUtekQo8REC9jTeXYSLjhIdRmIlmx';
    //支付宝公钥
    public $alipayrsaPublicKey='MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAunrqmbwc8Y6OKO4FkZbBIPsZTC7b34S27fft0IR6JwCrlCrvZzQd9X8gYhNsUBLJT5XC6pLG5hHWNIMh+fcNiw7Zj9Ty0j4MR+rw15wX5wvRylzqJgwhqC8pxap08wzV9Y2QwQangNF84012bh8sa/h4vrteVYNDmflABh91OlFWqrSeIWr4+LD9abrFfgx4cW6YQzw7fte/4g2dp9d8wv0AA4jAMXRnJsVHX8gKVj1S8zid+JWJHkKhubUE67IND0W9sB+D+xARNtHHnP9r53vRwTXpgR14QKnjFdvRS6EFbrbh8SIXTC1A0SuH7pUlzB5Rlg5dBE0Sqopu3yXtNwIDAQAB';

    public $sellerEmail='mdmiduo@sina.com'; //商家邮箱
    public $sellerId='2088821305387494'; //商家合作ID
    /**
     * Created by zyjun
     * Info:提现接口
     */
    public function getCash($money,$account,$true_name,$out_biz_no){
        $aop = new \AopClient();
        $aop->gatewayUrl =$this->gatewayUrl;
        $aop->appId = $this->appId;
        $aop->rsaPrivateKey = $this->rsaPrivateKey;
        $aop->alipayrsaPublicKey=$this->alipayrsaPublicKey;
        $aop->apiVersion = '1.0';
        $aop->signType = 'RSA2';
        $aop->postCharset='UTF-8';
        $aop->format='json';
        $request = new \AlipayFundTransToaccountTransferRequest ();
        $request->setBizContent("{" .
            "\"out_biz_no\":\"$out_biz_no\"," .    //商户转账唯一订单号。发起转账来源方定义的转账单据ID，用于将转账回执通知给来源方。
            "\"payee_type\":\"ALIPAY_LOGONID\"," .   //收款方账户类型
            "\"payee_account\":\"$account\"," .  //收款账号
            "\"payee_real_name\":\"$true_name\"," . //收款方真实姓名
            "\"amount\":\"$money\"," .                 //金额
            "\"payer_show_name\":\"成都佑宏科技有限公司\"," . //付款方名称
            "\"remark\":\"粒米校园钱包提现\"" .          //备注
            "}");
        $result = $aop->execute ( $request);
        $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
        $resultCode = $result->$responseNode->code;
        if(!empty($resultCode)&&$resultCode == 10000){
            $data['code']=$result->$responseNode->code; //支付宝code
            $data['msg']=$result->$responseNode->msg; //支付宝返回消息
            $data['order_id']=$result->$responseNode->order_id; //支付宝订单id
            $data['pay_date']=$result->$responseNode->pay_date; //支付宝code 支付宝返回成交日期
            $data['out_biz_no']=$result->$responseNode->out_biz_no;  //内部自己定义的流水号
            $data['status']=0;
        } else {
            $data['code']=$result->$responseNode->code; //支付宝code
            $data['msg']=$result->$responseNode->sub_msg; //支付宝返回消息
            $data['out_biz_no']=$result->$responseNode->out_biz_no;  //内部自己定义的流水号
            $data['status']=1;
        }
        return $data;
    }

    /**
     * Created by zyjun
     * Info:创建支付宝订单
     */
    public function createOrder($order_name,$order_detail,$money,$out_trade_no){
        $aop = new \AopClient ();
        $aop->gatewayUrl =$this->gatewayUrl;
        $aop->appId = $this->appId;
        $aop->rsaPrivateKey = $this->rsaPrivateKey;
        $aop->alipayrsaPublicKey=$this->alipayrsaPublicKey;
        $aop->apiVersion = '1.0';
        $aop->signType = 'RSA2';
        $aop->postCharset='UTF-8';
        $aop->format='json';
        $request = new \AlipayTradeCreateRequest ();
        $request->setBizContent("{" .
            "\"out_trade_no\":\"$out_trade_no\"," .
            "\"total_amount\":$money," .
            "\"subject\":\"$order_name\"," .
            "\"buyer_id\":\"490825318@qq.com\"," .   //买家支付宝id
            "}");
        $result = $aop->execute ( $request);
        $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
        $resultCode = $result->$responseNode->code;
        if(!empty($resultCode)&&$resultCode == 10000){
           return 0;  //成功
        } else {
            return 1; //失败
        }
    }


    /**
     * Created by zyjun
     * $out_biz_no 内部平台订单号
     * Info:订单支付，别和统一支付接口搞错了，这个文档在https://docs.open.alipay.com/204/105465/
     */
    public function payOrder($order_name,$money,$out_biz_no,$notify_url){
        $param['app_id'] = $this->appId;
        $param['method'] = 'alipay.trade.app.pay';//接口名称，固定值
        $param['charset'] = 'UTF-8';//请求使用的编码格式
        $param['sign_type'] = 'RSA2';//商户生成签名字符串所使用的签名算法类型
        $param['timestamp'] = date("Y-m-d H:i:s");//发送请求的时间
        $param['version'] = '1.0';//调用的接口版本，固定为：1.0
        $param['notify_url'] = AppUrl.'/index.php/apps/Alipay/'.$notify_url;
        $param['biz_content'] = "{\"subject\": \"$order_name\","         //商品的标题/交易标题/订单标题/订单关键字等。
            . "\"out_trade_no\": \"$out_biz_no\","
            . "\"total_amount\": \"$money\","
            . "\"product_code\":\"QUICK_MSECURITY_PAY\""
            . "}";
        $Client = new \AopClient();
        $paramStr = $Client->getSignContent($param);//组装请求签名参数
        $sign = $Client->alonersaSign($paramStr, $this->rsaPrivateKey, 'RSA2', false);//生成签名
        $param['sign'] = $sign;
        $str = $Client->getSignContentUrlencode($param);//最终请求参数
        return $str;
    }

    /**
     * Created by zyjun
     * Info:异步通知验签名，支付成功，写入支付数据信息，状态修改为success
     */
    public function rsaCheck($param){
        $aop = new \AopClient();
        $aop->alipayrsaPublicKey = $this->alipayrsaPublicKey;
        $flag = $aop->rsaCheckV1($param, NULL, "RSA2");
        return $flag;
    }

    /**
     * Created by zyjun
     * Info:支付宝回调通知处理函数,【在线充值】
     */
    public function aliPayAsyncNotice(){
        $data=$_POST;
        $this->recharge_log(1,$data['out_trade_no'],$data,'支付宝回调数据-异步');
        $flag=$this->rsaCheck($data);
        if(!$flag){ //验签失败 直接返回
           return ;
        }
        //验证签名通过，继续验证其他参数
        if($this->appId!=$data['app_id']){
            return ;
        }
        if($this->sellerEmail!=$data['seller_email']){
            return ;
        }
        if($this->sellerId!=$data['seller_id']){
            return ;
        }
        $out_biz_no=$data['out_trade_no']; //支付宝返回的平台内部订单号  非支付宝交易号
        $total_amount=$data['total_amount']; //订单金额
        $trade_status=$data['trade_status'];
        $res=Db::name('recharge')->where('order_out_biz_no',$out_biz_no)->field('order_money,uid')->find();
        if(empty($res)){
            $this->recharge_log(1,$out_biz_no,$data,'找不到内部订单');
            return ; //都找不到订单
        }
        $order_money=$res['order_money']; //内部订单号金额
        $uid=$res['uid'];                 //内部订单购买人

        if($order_money!=$total_amount){
            $this->recharge_log(1,$out_biz_no,$data,'订单金额不匹配');
            return ; //订单金额不匹配
        }
        if($trade_status!='TRADE_SUCCESS'){
            $this->recharge_log(1,$out_biz_no,$data,'订单支付状态错误');
            return;
        }
        //判断相同的订单是否已经存在了  用trade_no判断，保存post信息到单独的表，app查询时用单独的接口
        $is_success=Db::name('recharge')->where('order_out_biz_no',$out_biz_no)->value('is_success');
        if($is_success==1){
            echo 'success';   //已经支付成功了，屏蔽重复通知
            return;
        }
        //验证成功之后，写入支付信息，设置支付状态为成功。给用户钱包加钱。
        $recharge['order_no']=$data['trade_no'];
        $recharge['order_time']=$data['gmt_payment']; //支付时间
        $recharge['is_success']=1; //支付时间
        Db::name('recharge')->where('order_out_biz_no',$out_biz_no)->update($recharge);
        //给用户加钱
        $res=Db::name('user_wallet')->where('uid',$uid)->field('money')->find();
        if(empty($res)){ //没有账户
          Db::name('user_wallet')->insert(['uid'=>$uid,'money'=>0]);
        }
        $money=$res['money'];
        $money=$money+$total_amount;
        $res=Db::name('user_wallet')->where('uid',$uid)->setField('money',$money);
        //钱包流水记录
        $wallet=$this->getNowWallet($uid);
        $this->walletRecordDetail($uid,'',1,1,'',$total_amount,$wallet,1,'支付宝充值');
        //充值记录
        $this->recharge_log(1,$data['out_trade_no'],$data,'支付宝充值成功记录-异步');
        echo 'success';  //给支付宝返回信息
    }

    /**
     * Created by zyjun
     * Info:支付宝回调通知处理函数,【在线支付】
     */
    public function aliOnlinePayAsyncNotice(){
        $data=$_POST;
        $this->orderPayLog(1,$data['out_trade_no'],$data,'支付宝回调数据-异步');
        $flag=$this->rsaCheck($data);
        if(!$flag){ //验签失败 直接返回
            return ;
        }
        //验证签名通过，继续验证其他参数
        if($this->appId!=$data['app_id']){
            return ;
        }
        if($this->sellerEmail!=$data['seller_email']){
            return ;
        }
        if($this->sellerId!=$data['seller_id']){
            return ;
        }
        $out_biz_no=$data['out_trade_no']; //支付宝返回的内部订单号
        $total_amount=$data['total_amount']; //订单金额
        $trade_status=$data['trade_status'];
        $res=Db::name('order')->where('order_out_biz_no',$out_biz_no)->field('money,uid')->find();
        if(empty($res)){
            $this->orderPayLog(1,$out_biz_no,$data,'找不到内部订单');
            return ; //都找不到订单
        }
        $order_money=$res['money']; //内部订单号金额
        $uid=$res['uid'];                 //内部订单购买人

        if($order_money!=$total_amount){
            $this->orderPayLog(1,$out_biz_no,$data,'订单金额不匹配');
            return ; //订单金额不匹配
        }
        if($trade_status!='TRADE_SUCCESS'){
            $this->orderPayLog(1,$out_biz_no,$data,'订单支付状态错误');
            return;
        }
        //判断相同的订单是否已经存在了  用trade_no判断，保存post信息到单独的表，app查询时用单独的接口
        $is_success=Db::name('order')->where('order_out_biz_no',$out_biz_no)->value('pay_status');
        if($is_success==1){
            echo 'success';   //已经支付成功了，屏蔽重复通知
            return;
        }
        //验证成功之后，写入支付信息，设置支付状态为成功。订单记录的状态由前端发起请求后修改
        $order['order_no']=$data['trade_no'];
        $order['notify_time']=$data['gmt_payment']; //支付时间
        $order['pay_status']=1;
        $order['order_status']=1;
        Db::name('order')->where('order_out_biz_no',$out_biz_no)->update($order);
        //支付记录
        $this->orderPayLog(1,$data['out_trade_no'],$data,'支付宝支付成功记录-异步');
        echo 'success';  //给支付宝返回信息
    }

    /**
     * Created by zyjun
     * Info:支付宝交易状态查询
     * trade_no 支付宝交易号
     * 交易状态：WAIT_BUYER_PAY（交易创建，等待买家付款）、TRADE_CLOSED（未付款交易超时关闭，或支付完成后全额退款）、TRADE_SUCCESS（交易支付成功）、TRADE_FINISHED（交易结束，不可退款）
     */
    public function  tradeStatus($out_biz_no){
        $aop = new \AopClient();
        $aop->gatewayUrl =$this->gatewayUrl;
        $aop->appId = $this->appId;
        $aop->rsaPrivateKey = $this->rsaPrivateKey;
        $aop->alipayrsaPublicKey=$this->alipayrsaPublicKey;
        $aop->apiVersion = '1.0';
        $aop->signType = 'RSA2';
        $aop->postCharset='UTF-8';
        $aop->format='json';
        $request = new \AlipayTradeQueryRequest ();
        $request->setBizContent("{" .
            "\"out_trade_no\":\"$out_biz_no\"" .
            "}");
        $result = $aop->execute ( $request);
        if($result->alipay_trade_query_response->code!='10000'){
            $re['status']=1;
            $re['msg']=$result->alipay_trade_query_response->sub_msg;
            return $re;
        }
        $re['status']=0;
        $re['trade_status']=$result->alipay_trade_query_response->trade_status;
        $re['data']=(array)$result->alipay_trade_query_response;
        return $re; //返回交易状态和交易值

    }

}