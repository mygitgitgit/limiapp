<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/5/21 0021
 * Time: 10:14
 */

namespace app\com\controller;

use OSS\Core\OssException;
use OSS\OssClient;

require_once APP_EXTEND. 'Aliyunoss/autoload.php';
class Aliyunoss
{
    private $accessKeyId = "LTAIdUDh7bLbUAqe";
    private $accessKeySecret = "z8drl5bB3dRhA3Jn9qwPgoztTrhIf7";
    private $endpoint = "http://oss-cn-hangzhou.aliyuncs.com";
    private $bucket= "limiapp";

    public function downloadFile($object){
        //下载到内存；
        $ossClient= new OssClient($this->accessKeyId, $this->accessKeySecret, $this->endpoint);
        try{
            $content = $ossClient->getObject($this->bucket, $object);
            $data['message']='下载成功';
            $data['content']=$content;
            $data['status']=1;
            return $data;
        } catch(OssException $e) {
            $data['message']=$e->getMessage();
            $data['content']='';
            $data['status']=0;
            return $data;
        }
    }
    public function downloadfile2($object,$localfile){

        $ossClient= new OssClient($this->accessKeyId, $this->accessKeySecret, $this->endpoint);
        $options = array(
            OssClient::OSS_FILE_DOWNLOAD => $localfile,
        );
        try{
            $ossClient->getObject($this->bucket, $object, $options);
            $data['message']='下载成功';
            $data['status']=1;
            return $data;
        } catch(OssException $e) {
            $data['message']=$e->getMessage();
            $data['status']=0;
            return $data;
        }
    }

}