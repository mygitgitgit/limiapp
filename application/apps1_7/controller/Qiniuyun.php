<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/1/30 0030
 * Time: 10:16
 */
namespace app\apps1_7\controller;
use think\Controller;
use think\Db;

require_once APP_EXTEND. 'Qiniuyun/autoload.php';
use \Qiniu\Auth;
use \Qiniu\Storage\UploadManager;
use Qiniu\Processing\Operation;
use Qiniu\Processing\PersistentFop;

class Qiniuyun extends Common
{
    /**
     * Created by zyjun
     * Info:初始化
     */
    public function _initialize(){
        $uid = input('post.id');
        $token = input('post.token');
        $res = $this->checkToken($uid, $token);
        if ($res['status']) {
            return apiError($res['msg'],'',$res['code']);
        }
    }

    /**
     * Created by zyjun
     * Info:请求token的类型，图片，视频，其他
     */
    public function getUploadToken(){
       $type=input('post.type');
       if(empty($type)){
           return apiError('缺少请求参数');
       }
       if(!in_array($type,array('image','video'))){
           return apiError('请求参数错误');
       }
        if($type=='image'){
           $limit_data=$this->getSysUploadLimit()['image'];
           $fsizeLimit=$limit_data['size'];
           $mimeLimit=$limit_data['type'];
        }
        if($type=='video'){
            $limit_data=$this->getSysUploadLimit()['video'];
            $fsizeLimit=$limit_data['size'];
            $mimeLimit=$limit_data['type'];
        }
        $data['token']=$this->createUploadToken($fsizeLimit,$mimeLimit);
        $data['size']=$fsizeLimit;
        $data['type']=$mimeLimit;
        apiSuccess('token信息',$data);
    }
    /**
     * Created by zyjun
     * Info:获取上传token
     */
    public function createUploadToken($fsizeLimit,$mimeLimit){
        $accessKey = 'V7EdW3HcScUhUMlZ5zhnDBhR7kXONV0TtNDFNmUy';
        $secretKey = 'iACpr_pGuf2Yjq8ktFCf67D4wefT3Et_m2wHr1Ru';
        $bucket = QiniuBucket;

        //带回调业务服务器的凭证（application/json）
        $policy = array(
            'fsizeLimit'=>$fsizeLimit*1024*1024,  //文件大小
            'mimeLimit'=>$mimeLimit,  //文件类型
        );
// 初始化Auth状态
        $auth = new Auth($accessKey, $secretKey);
// 自定义凭证有效期（示例1小时）
        $expires = 3600;
        $upToken = $auth->uploadToken($bucket, null, $expires, $policy, true);
        return $upToken;
    }

    public function getSysUploadLimit(){
        $data=Db::name('sys_set')->where('key',4)->value('data');
        if(empty($data)){
            $redata['image']['size']=5;
            $redata['image']['type']='image/jpeg;image/png;image/gif;image/HEIC';
            $redata['video']['size']=50;
            $redata['video']['type']='video/mp4;video/x-flv;video/avi;video/mov;video/quicktime';
            return $redata;
        }
        $data=json_decode($data,true);
        $redata['image']=$data['image'];
        $redata['video']=$data['video'];
        return $redata;
    }

    /**
     * Created by zyjun
     * Info:测试上传
     */
    public  function testUpload(){

// 要上传文件的本地路径
        $filePath=$_FILES['image']['tmp_name'];
// 上传到七牛后保存的文件名
        $key = 'test'.time().'png';

// 初始化 UploadManager 对象并进行文件的上传。
        $uploadMgr = new UploadManager();

// 调用 UploadManager 的 putFile 方法进行文件的上传。
        $accessKey = 'V7EdW3HcScUhUMlZ5zhnDBhR7kXONV0TtNDFNmUy';
        $secretKey = 'iACpr_pGuf2Yjq8ktFCf67D4wefT3Et_m2wHr1Ru';
        $bucket = 'limiapp';

// 初始化Auth状态
        $auth = new Auth($accessKey, $secretKey);
// 自定义凭证有效期（示例2小时）
        $expires = 3600;
        $policy = array(
            'fsizeLimit'=>33587200,  //100KB
            'mimeLimit'=>'video/mp4;video/x-flv;video/avi;video/mov;video/quicktime'  //文件类型
        );
        $expires = 7200;
        $upToken = $auth->uploadToken($bucket, null, $expires, $policy, true);
        list($ret, $err) = $uploadMgr->putFile($upToken, $key, $filePath);
        echo "\n====> putFile result: \n";
        if ($err !== null) {
            var_dump($err);
        } else {
            var_dump($ret);
        }
    }

    public function testSet(){
        $this->sys_set(1,array('video'=>array('size'=>100,'type'=>'video/mp4;video/x-flv;video/avi;video/mov;video/quicktime'),'image'=>array('size'=>10,'type'=>'image/jpeg;image/png;image/gif')),'七牛云文件上传限制');
    }

    //图片瘦身
    public function testbuildUrl()
    {
        $fops = 'imageView2/2/h/200';
        $fop = new Operation('testres.qiniudn.com');
        $url = $fop->buildUrl('gogopher.jpg', $fops);
        $this->assertEquals($url, 'http://testres.qiniudn.com/gogopher.jpg?imageView2/2/h/200');

        $fops = array('imageView2/2/h/200', 'imageInfo');
        $url = $fop->buildUrl('gogopher.jpg', $fops);
        $this->assertEquals($url, 'http://testres.qiniudn.com/gogopher.jpg?imageView2/2/h/200|imageInfo');
    }

}