<?php
use think\Db;


/*************************** 开发辅助函数 **********************/
/*不管接口是否正常执行逻辑，都返回http code=200.而不是国外那种错误有http code来表示，只用apierror和success来表示
1：用户和权限
   1000，表示需要登录权限    1001表示需要认证权限
   这里特殊一点需要根据不同权限跳转不同页面
3:数据处理状态码
   2000 表示数据格式错误，正在验证不通过或其他
2：业务逻辑
   3000 业务逻辑错误 【余额不足】
4：系统状态码
    4000 系统异常，比如程序捕捉异常catch
不返回状态码阿皮success，表示执行正常，error必须返回状态码，success只有在全部执行完逻辑才返回success
status为大权限状态码。如果是需要详细返回小的状态码，可以在msg里面返回子状态码，例如apiError('权限不足，code:100','',1001) 1001下的子权限100
error返回3000，则需要用户展示后端的msg,
2000时需要前端屏蔽，正常情况下，后端在不影响功能逻辑的情况下，宽裕处理，比如字段长度。针对类似正则验证，后端必须严格验证。
测试服务器后端发送错误字段和原因，正式服务器返回错误字段或字段+子状态码，每个函数根据自己的错误状态，返回独立的子状态码，比如长度，格式，规定的type类型等



 * 1,目前定义code=1000,为登陆code,检测到这个code则跳转登陆页面
  1001：，检测到1001就跳转认证页面。
  2000：约定客户端字段类型错误，正则验证错误，此类非法表单提交或程序异常执行都返回apierror接口，意在告知客户端数据验证异常，让客户端注意提交数据的严谨度,此类报错开发中可以
 输出到前端，但正式服务器，可以想办法屏蔽下。可以考虑输出子代码查询的方式提醒前端。让恶意访问者不知道报错信息
 如果还有认证这个操作，可以用1001，来识别

   下方2开头的为固定报错类型
  2,code=3000字段正常，规则验证正常，下的业务提醒，检测到3000，客户端友好提醒接口的msg信息，业务提醒【比如，余额不足，请先认真，视频仅管理员可见等】

  4，code=4000, 告知客户端执行出错了，比如出现异常，联系管理员处理，此类报错一般是数据库数据存在认为修改或者代码有bug,前端无法处理，只能联系后端处理

  只要接口目的目的没有实现，都返回apierror,比如用户想余额买东西，但是余额不足，没实现需求，则返回apierror,若果成功的扣款，买东西，那么返回success


关于返回值  是否按照字符返回"" ,数组返回[],  整形返回0，数据库默认是返回NULL；链表查询也不可能每个字段规定下返回为""，还是空数组
所以统一都返回NULL;但是接口处理时又有可能返回空数组，此类问题前端判断

关于返回的接口字段
status:  ERROR表示请求接口成功，但是没有达到接口要求。需要根据状态码处理问题   SUCCESS，只有完成接口逻辑，正常返回数据，才会返回success
msg:普通的消息提醒，用于告知客户端大致问题；
code:为全局状态码，比如1000，跳转登录 1001，需要认证  3000约定客户端显示后端消息   3001余额不足需要跳转到充值页面，此类status由code返回
sub_msg:记录该错误的详细问题信息，测试服务器开放原因，正式服务器会关闭sub_msg
sub_code:子code，每个接口都从0x01开始返回，便于快速定位问题位置。

1000:未登录，跳转登录页面   2000：客户端字段长度错误或者正则验证不通过或其他    3000：业务逻辑错误或不通过，检测到3000状态码，客户端需要展示出后端msg
4000:后端程序执行异常，一般用于展示通用错误页面或者toast提醒

*/

/**
 * Created by zyjun
 * Info:返回错误信息，json格式
 * 第1个参数：返回的消息  第2个参数：返回后台数据， 第3个参数：返回状态码
 */
function apiSuccess(){
    $msg=$data=$sub_msg='';
    $code=$sub_code=NULL;
    $vars=func_get_args();
    $length=count($vars);
    for($i=0;$i<$length;$i++){
        if($i==0){
            $msg=$vars[0];
        }
        if($i==1){
            $data=$vars[1];
        }
        if($i==2){
            $code=$vars[2];
        }
        if($i==3){
            $sub_msg=$vars[3];
        }
        if($i==4){
            $sub_code=$vars[4];
        }
    }
    #判断是否禁止显示详细信息
    if(!SubApiMsg){
        $sub_code=$sub_msg='';
    }
    $result = array(
        'status' => 'Success',
        'msg' => $msg,
        'code'=>$code,
        'data' => $data,
        'sub_msg' => $sub_msg,
        'sub_code'=>$sub_code,


    );
    print json_encode($result);
}
/**
 * @param null $msg  返回具体错误的提示信息
 * @param flag success CURD 操作失败
 * Function descript:返回标志信息 ‘Error'，和提示信息的json 数组
 */
function apiError(){
    $msg=$data=$sub_msg='';
    $code=$sub_code=NULL;
    $vars=func_get_args();
    $length=count($vars);
    for($i=0;$i<$length;$i++){
        if($i==0){
            $msg=$vars[0];
        }
        if($i==1){
            $data=$vars[1];
        }
        if($i==2){
            $code=$vars[2];
        }
        if($i==3){
            $sub_msg=$vars[3];
        }
        if($i==4){
            $sub_code=$vars[4];
        }
    }
    #判断是否禁止显示详细信息
    if(!SubApiMsg){
        $sub_code=$sub_msg='';
    }
    $result = array(
        'status' => 'Error',
        'msg' => $msg,
        'code'=>$code,
        'data' => $data,
        'sub_msg' => $sub_msg,
        'sub_code'=>$sub_code,
    );
    print json_encode($result);
}

/**
 * Created by ZYJUN
 * Datetime: 2017-09-21 16:58
 * Info:公共函数 存放常用正则表达式或PHP函数，不牵涉数据库的方法
 */

/*************************** 正则验证 **********************/
/**
 * Created by zyjun
 * Info: 验证手机
 */
function checkMobile($param){
    if(!preg_match('/^1[345789]{1}\d{9}$/',$param)){
        return true;
    } else {
        return false;
    }
}

/**
 * Created by zyjun
 * Info:登录验证码，4位纯数字
 */
function checkCode($param){
    if (!preg_match('/^[0-9]{4}$/',$param)){
        return true;
    }else{
        return false;
    }
}

/**
 * Created by zyjun
 * Info:验证时间 2017-02-02 12:00:00
 */
function checkDateTime($param){
    if (!preg_match('/^\d{4}-\d{1,2}-\d{1,2}\s\d{1,2}:\d{1,2}:\d{1,2}$/',$param)){
        return true;
    }else{
        return false;
    }
}

/**
 * Created by zyjun
 * Info:获取两个字符串之间的内容  匹配的字符里面可以是多对标签   '测试<p>截取的内容</p>测试测试测试<p>截取的内容222</p>'
 * $reg_exp 匹配正则表达式 ,$str 被匹配的字符串,$type 0返回包含匹配标签返回 1：返回不包含匹配标签  2：包含匹配标签和无匹配标签的2个数组
 * 返回数组
 */
 function getBetweenStrRegExp($reg_exp,$str,$type){
     preg_match($reg_exp, $str, $match);
    switch ($type){
        case 0:$data=$match[0];break;
        case 1:$data=$match[1];break;
        case 2:$data=$match;break;
        default :$data=$match;
    }
    return $data;
}

/**
 * Created by zyjun
 * Info:获取两个字符串之间的内容  匹配的字符里面只能有一对需替换的标签    比如获取<p></p>之间的内容  getBetweenStr($keyword,'<p>','</p>')  '测试<p>截取的内容</p>测试测试测试'
 * 返回字符串
 */
 function getBetweenStr($keyword,$str1,$str2){
    $start_length=strlen($str1);
    $start =stripos($keyword,$str1);
    $end =stripos($keyword,$str2);
    if(($start===false||$end===false)||$start>=$end){ //查询不到起始结束或者大于，都返回空
        return 0;
    }
    $str=substr($keyword,($start+$start_length),$end-$start_length);
    return $str;
}

/**
 * Created by zyjun
 * Info:获取标签内的字符串，标签对可以是多个,默认非贪婪匹配
 */
    function getTagStr($tag1,$tag2,$str,$type,$greed='?'){
     $reg_exp='/'.$tag1.'.*'.$greed.$tag2.'/';
     $content=getBetweenStrRegExp($reg_exp,$str,$type);
     return $content;
    }

/*************************** 正则验证结束 **********************/


function httpGetData($url)
{
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    //设置头文件的信息作为数据流输出
    curl_setopt($curl, CURLOPT_HEADER, 0); //不要header信息
    //设置获取的信息以文件流的形式返回，而不是直接输出。
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    //执行命令
    $data = curl_exec($curl);
    //http状态码
    $http_code=curl_getinfo($curl,CURLINFO_HTTP_CODE); //http返回状态码
    //关闭URL请求
    curl_close($curl);
    //显示获得的数据
    if($http_code!=200){
        $re['status']=1;
        $re['msg']='未知错误';
        return $re;
    }
    $data=json_decode($data,true);
    $re['status']=0;
    $re['msg']='获取成功';
    $re['data']=$data;
    return $re;
}

/**
 * 星座
 */
function calcAge($birthday) {
    $age = 0;
    $signs = array( array('20' => '水瓶座'), array('19' => '双鱼座'), array('21' => '白羊座'), array('20' => '金牛座'), array('21' => '双子座'), array('22' => '巨蟹座'), array('23' => '狮子座'), array('23' => '处女座'), array('23' => '天秤座'), array('24' => '天蝎座'), array('22' => '射手座'), array('22' => '摩羯座'));
    if (!empty($birthday)) {
        $age = strtotime($birthday);
        if ($age === false) {
            return 0;
        }

        list($y1, $m1, $d1) = explode("-", date("Y-m-d", $age));

        list($y2, $m2, $d2) = explode("-", date("Y-m-d"), time());

        $age = $y2 - $y1;
        //下面是判断月份大小，如果只是逄年份的可以去掉，如果算上月份的话，比如：2000年4月1日，那算出来是16算，要到了4月，算出来才是17岁
        if ((int)($m2 . $d2) < (int)($m1 . $d1)) {
            $age -= 1;
        }

        //星座
        $key = (int)$m1 - 1;
        list($startSign, $signName) = each($signs[$key]);
        if ($d1 < $startSign) {
            $key = $m1 - 2 < 0 ? $m1 = 11 : $m1 -= 2;
            list($startSign, $signName) = each($signs[$key]);
        }
        //return $signName;
    }
    $data=['age'=>$age,'con'=>$signName];
    return $data;
}









