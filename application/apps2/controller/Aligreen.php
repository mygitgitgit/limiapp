<?php
namespace app\apps2\controller;
use think\Controller;
use think\Db;
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/1/31 0031
 * Time: 16:47
 * info: 阿里云内容安全监测
 */
include_once APP_EXTEND.'Aligreen/aliyuncs/aliyun-php-sdk-core/Config.php';
use Green\Request\V20170112 as Green;
date_default_timezone_set("PRC");


class Aligreen extends Common
{   public $accessKeyId='LTAIdUDh7bLbUAqe';
    public $accessKeySecret='z8drl5bB3dRhA3Jn9qwPgoztTrhIf7';
    /**
     * Created by zyjun
     * Info:视频鉴黄url方式
     */
    public function videoUrl($url,$dataid){
        $iClientProfile = \DefaultProfile::getProfile("cn-shanghai", $this->accessKeyId, $this->accessKeySecret); // TODO
        \DefaultProfile::addEndpoint("cn-shanghai", "cn-shanghai", "Green", "green.cn-shanghai.aliyuncs.com");
        $client = new \DefaultAcsClient($iClientProfile);

        $request = new Green\VideoAsyncScanRequest();
        $request->setMethod("POST");
        $request->setAcceptFormat("JSON");

        $tasks['dataId']=$dataid; //视频唯一任务id
        $tasks['url']='http://cloud.youhongtech.com'.$url;    //视频唯一url地址
        $tasks['interval']=5; //视频截图间隔1-60
        $data['tasks']=array($tasks); //json格式

        $data['scenes']=['porn']; //视频场景
        $data['callback']=AliGreenNoticefyUrl.'/Noticefy/aliActionVideoDis'; //回调地址
        $data['seed']='a4888d9e3be036c9408de9cc22f036fe'; //签名，用于回调验证 自己生成固定
        $request->setContent(json_encode($data));
        try {
            $response = $client->getAcsResponse($request);
            $response=(array)$response;
            if($response['code']!=200){
                $re['status']=1;
                $re['msg']='响应状态错误';
                return $re;
            }
            $re['status']=0;
            $re['msg']='提交鉴定成功';
            return $re;
        } catch (\Exception $e) {
            $re['status']=1;
            $re['msg']=$e;
            return $re;
        }
    }

    /**
     * Created by zyjun
     * Info:文本垃圾信息处理  //分类https://help.aliyun.com/document_detail/53423.html?spm=a2c4g.11186623.6.573.oCRsBD
     */
    public function  checkText($text,$dataid){
        $iClientProfile = \DefaultProfile::getProfile("cn-shanghai", $this->accessKeyId, $this->accessKeySecret); // TODO
        \DefaultProfile::addEndpoint("cn-shanghai", "cn-shanghai", "Green", "green.cn-shanghai.aliyuncs.com");
        $client = new \DefaultAcsClient($iClientProfile);

        $request = new Green\TextScanRequest();
        $request->setMethod("POST");
        $request->setAcceptFormat("JSON");

        $tasks['dataId']=$dataid; //任务id
        $tasks['content']=$text['content'];   //检测文本
        $tasks['time']=time(); //内容创建时间
        $tasks['category']=$text['category']; //内容类别[“post”, “reply”, “comment”, “title”, “others”];
        $tasks['action']=$text['action'];//[“new”, “edit”, “share”, “others”]；
        $data['tasks']=array($tasks); //json格式

        $data['scenes']=['antispam']; //文本场景
        $request->setContent(json_encode($data));
        try {
            $response = $client->getAcsResponse($request);
            $response=(array)$response;
            if($response['code']!=200){
                $re['status']=1;
                $re['msg']='响应状态错误';
                return $re;
            }
            $re['status']=0;
            $re['msg']='提交鉴定成功';
            return $re;
        } catch (\Exception $e) {
            $re['status']=1;
            $re['msg']=$e;
            return $re;
        }
    }


    /*****************************输入内容处理*****************************/
    /**
     * Created by zyjun
     * Info:阿里云文本检测
     * $content：只接受字符串
     * $content文字长度为4000个字符，汉字建议不超出1300个;单个项目，比如类似表单提交的内容，建议合并后一起检测，降低费用成本
     */
    public function inputText($content,$category,$action){
        if(empty($content)){
            $re['status']=0;
            $re['msg']='未传递检测文本';
            return $re;  //空内容不进行检测，默认通过
        }
        if(!in_array($category,array('','post', 'reply', 'comment', 'title', 'others'))){
            $re['status']=1;
            $re['msg']='文本category参数未填写';
            return $re;
        }
        if(!in_array($action,array('','new', 'edit', 'share', 'others'))){
            $re['status']=1;
            $re['msg']='文本action参数未填写';
            return $re;
        }
        if(strlen($content)>6000){
            $re['status']=1;
            $re['msg']='文本大于6000个字符，无法进行阿里绿网文本检测';
            return $re;
        }
        //开始处理数据
        $aligreen=new Aligreen();
        $dataid=$this->createAliGreenDataid();
        $text['category']=$category;
        $text['action']=$action;
        $text['content']=$content;
        $res=$this->checkText($text,$dataid);






    }


    /**
     * Created by zyjun
     * Info:阿里云内容鉴别任务id
     */
    public function createAliGreenDataid(){
        $dataid=date('YmdHis',time()).rand(100,999);
        return $dataid;
    }

}




