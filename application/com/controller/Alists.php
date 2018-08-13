<?php
namespace app\com\controller;
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/1/31 0031
 * Time: 16:47
 * info: 阿里云颁发STS临时授权
 */
include_once APP_EXTEND.'aliyunOpenapi/aliyun-php-sdk-core/Config.php';
use Sts\Request\V20150401 as Sts;


class Alists{

    /**
     * Created by zyjun
     * Info:$client_name:客户的ID作为会话名称     $duration_seconds:授权有效期限秒
     */
    public function createSts($access_key_id,$access_key_secret,$region_id,$endpoint,$role,$client_name,$duration_seconds){
#只允许子用户使用角色
        \DefaultProfile::addEndpoint($region_id, $region_id, "Sts", $endpoint);
        $iClientProfile = \DefaultProfile::getProfile($region_id, $access_key_id, $access_key_secret);
        $client = new \DefaultAcsClient($iClientProfile);
#角色资源描述符，在RAM的控制台的资源详情页上可以获取
        $roleArn = $role;
#在扮演角色(AssumeRole)时，可以附加一个授权策略，进一步限制角色的权限；
# 详情请参考《RAM使用指南》
# 此授权策略表示读取所有OSS的只读权限
//$policy=<<<POLICY
//     {
//  "Statement": [
//    {
//      "Action": "sts:AssumeRole",
//      "Effect": "Allow",
//      "Principal": {
//        "RAM": [
//          "acs:ram::1324203554625576:root"
//        ]
//      }
//    }
//  ],
//  "Version": "1"
//}
//POLICY;

        $request = new Sts\AssumeRoleRequest();
# RoleSessionName即临时身份的会话名称，用于区分不同的临时身份
# 您可以使用您的客户的ID作为会话名称
        $request->setRoleSessionName($client_name);
        $request->setRoleArn($roleArn);
//        $request->setPolicy($policy);
        $request->setDurationSeconds($duration_seconds);
        try {
            $response = $client->getAcsResponse($request);
            $response=$this->objectToArray($response);
            $re['status']=0;
            $re['data']=$response['Credentials'];
            return $re;
        } catch(\ServerException $e) {
            print "Error: " . $e->getErrorCode() . " Message: " . $e->getMessage() . "\n";
        } catch(\ClientException $e) {
            print "Error: " . $e->getErrorCode() . " Message: " . $e->getMessage() . "\n";
        }

    }




    /*************************辅助函数******************************/

    /**
     * 对象 转 数组
     *
     * @param object $obj 对象
     * @return array
     */
    private function objectToArray($obj) {
        $obj = (array)$obj;
        foreach ($obj as $k => $v) {
            if (gettype($v) == 'resource') {
                return;
            }
            if (gettype($v) == 'object' || gettype($v) == 'array') {
                $obj[$k] = (array)$this->objectToArray($v);
            }
        }
        return $obj;
    }
}





