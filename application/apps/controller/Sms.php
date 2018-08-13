<?php
namespace app\apps\controller;
use think\Controller;
use think\Db;
ini_set("display_errors", "on");
require_once APP_EXTEND. 'Alidayu/api_sdk/vendor/autoload.php';
use Aliyun\Core\Config;
use Aliyun\Core\Profile\DefaultProfile;
use Aliyun\Core\DefaultAcsClient;
use Aliyun\Api\Sms\Request\V20170525\SendSmsRequest;
use Aliyun\Api\Sms\Request\V20170525\QuerySendDetailsRequest;

// 加载区域结点配置
Config::load();

/**
 * Class SmsDemo
 *
 * @property \Aliyun\Core\DefaultAcsClient acsClient
 */
class Sms extends Common
{

    /**
     * 构造器
     *
     * @param string $accessKeyId 必填，AccessKeyId
     * @param string $accessKeySecret 必填，AccessKeySecret
     */
    public function __construct($accessKeyId='LTAIdUDh7bLbUAqe', $accessKeySecret='z8drl5bB3dRhA3Jn9qwPgoztTrhIf7')
    {

        // 短信API产品名
        $product = "Dysmsapi";

        // 短信API产品域名
        $domain = "dysmsapi.aliyuncs.com";

        // 暂时不支持多Region
        $region = "cn-hangzhou";

        // 服务结点
        $endPointName = "cn-hangzhou";

        // 初始化用户Profile实例
        $profile = DefaultProfile::getProfile($region, $accessKeyId, $accessKeySecret);

        // 增加服务结点
        DefaultProfile::addEndpoint($endPointName, $region, $product, $domain);

        // 初始化AcsClient用于发起请求
        $this->acsClient = new DefaultAcsClient($profile);
    }

    /**
     * 发送短信范例
     *
     * @param string $signName <p>
     * 必填, 短信签名，应严格"签名名称"填写，参考：<a href="https://dysms.console.aliyun.com/dysms.htm#/sign">短信签名页</a>
     * </p>
     * @param string $templateCode <p>
     * 必填, 短信模板Code，应严格按"模板CODE"填写, 参考：<a href="https://dysms.console.aliyun.com/dysms.htm#/template">短信模板页</a>
     * (e.g. SMS_0001)
     * </p>
     * @param string $phoneNumbers 必填, 短信接收号码 (e.g. 12345678901)
     * @param array|null $templateParam <p>
     * 选填, 假如模板中存在变量需要替换则为必填项 (e.g. Array("code"=>"12345", "product"=>"阿里通信"))
     * </p>
     * @param string|null $outId [optional] 选填, 发送短信流水号 (e.g. 1234)
     * @return stdClass
     */
    public function sendSms($signName, $templateCode, $phoneNumbers, $templateParam, $outId = null) {

        // 初始化SendSmsRequest实例用于设置发送短信的参数
        $request = new SendSmsRequest();

        // 必填，设置雉短信接收号码
        $request->setPhoneNumbers($phoneNumbers);

        // 必填，设置签名名称
        $request->setSignName($signName);

        // 必填，设置模板CODE
        $request->setTemplateCode($templateCode);

        // 可选，设置模板参数
        if($templateParam) {
            $request->setTemplateParam(json_encode($templateParam));
        }

        // 可选，设置流水号
        if($outId) {
            $request->setOutId($outId);
        }

        // 发起访问请求
        $acsResponse = $this->acsClient->getAcsResponse($request);

        // 打印请求结果
        // var_dump($acsResponse);
        date_default_timezone_set('PRC');   //设置时区为中国
        return $acsResponse;

    }

    /**
     * Created by zyjun
     * Info:获取短信验证码
     */
    public function getSendSms()
    {
        $signName = '粒米'; //签名
        $templateCode = 'SMS_121145445';//短信模板
        $mobile = input('phone');//传递的号码
        if ($mobile == "") {
            apiError('手机号码不能为空');
            return;
        }
        if (checkMobile($mobile)) {
            apiError('手机号码格式不正确');
            return;
        }
        $phoneNumbers = $mobile;
        $verifycode = strval(rand(1000, 9999));//验证码数字4位
        $templateParam = array('code' => $verifycode);
        $result=$this->sendSms($signName, $templateCode, $phoneNumbers, $templateParam, $outId = null);
        $result =  json_decode( json_encode( $result),true);
        if($result['Message']!='OK'){
            apiError('验证码发送失败:'.$result['Message']);
            return;
        }
        return $verifycode;
    }

    //调用发送短信接口,暂时放这里啦
    public function startSendSms(){
        $signName='粒米'; //签名
        $templateCode='SMS_121145445';//短信模板
        $mobile=input('phone');//传递的号码
        if($mobile==""){
            apiError('手机号码不能为空');
            return;
        }
        if(checkMobile($mobile)){
          apiError('手机号码格式不正确');
          return;
        }
        if($mobile=='15983155261'){
            return apiSuccess('验证码发送成功,请注意查收');
        }
        $phoneNumbers=$mobile;
        $verifycode =strval(rand(1000,9999));//验证码数字4位
        $templateParam =array('code'=>$verifycode);
        $result=$this->sendSms($signName, $templateCode, $phoneNumbers, $templateParam, $outId = null);
        $result =  json_decode( json_encode( $result),true);
        if($result['Message']!='OK'){
            if($result['Code']=='isv.BUSINESS_LIMIT_CONTROL'){
                apiError('发送过于频繁，请稍后再试!');
                return;
            }else{
                apiError('发送失败，请稍后再试!');
                return;
            }

        }
        $date['mobile']=$mobile;
        $date['code']=$verifycode;
        $date['code_time']=date('Y-m-d H:i:s',time()+600); //比对数据库时间，默认10分钟有效期 程序计算
        $date['create_time']=date('Y-m-d H:i:s',time()); //注册日期
        $date['status']=0; //正常用户
        $res=$this->isReg($mobile);
        if($res['status']){
            $date2['code']=$date['code'];
            $date2['code_time']=$date['code_time'];  //注册和登录都靠短信登录，每次接收短信后更新code,code_time
            $res=Db::name('user')->where('mobile',$mobile)->update($date2);
        }else{
            $res=Db::name('user')->insert($date);
            $id=Db::name('user')->where('mobile',$mobile)->value('id');
            //创建用户钱包
            Db::name('user_wallet')->insert(['uid'=>$id,'money'=>0]);
            //创建网易通讯token
            $Im=new Im();
            $Im->regToken($id);
        }
        apiSuccess('验证码已发送');
    }

    /**
     * Created by zyjun
     * Info:短信通知
     */
    public function smsNotice($mobile,$name,$type){
        $signName='粒米'; //签名
        switch($type){
            case 1:$templateCode='SMS_133180062';break; //钱包提现通知
            case 2:$templateCode='SMS_133180118';break; //提现申请短信通知
            default :$templateCode='';
        }

        if($mobile==""){
            apiError('手机号码不能为空');
            return;
        }
        if(checkMobile($mobile)){
            apiError('手机号码格式不正确');
            return;
        }
        $phoneNumbers=$mobile;
        $templateParam =array('name'=>$name,'time'=>date('Y-m-d H:i:s',time()));
        $result=$this->sendSms($signName, $templateCode, $phoneNumbers, $templateParam, $outId = null);
        $result =  json_decode( json_encode( $result),true);
        if($result['Message']!='OK'){
            if($result['Code']=='isv.BUSINESS_LIMIT_CONTROL'){
                apiError('发送过于频繁，请稍后再试!');
                return;
            }else{
                apiError('发送失败，请稍后再试!');
                return;
            }
        }
    }

    /**
     * Created by zyjun
     * Info:管理员短信提醒
     */
    function sysSmsNotice($name,$type){
        $mobiles=['18581882458'];
        foreach ($mobiles as $key=>$val){
            $this->smsNotice($val,$name,$type);
        }
    }

}

