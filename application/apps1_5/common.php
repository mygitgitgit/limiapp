<?php
use think\Db;


/*************************** 开发辅助函数 **********************/
/**
 * Created by zyjun
 * Info:返回错误信息，json格式
 * 第1个参数：返回的消息  第2个参数：返回后台数据， 第3个参数：返回状态码
 */
function apiSuccess(){
    $msg=$data=$code='';
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
    }
    $result = array(
        'status' => 'Success',
        'msg' => $msg,
        'code'=>$code,
        'data' => $data,

    );
    print json_encode($result);
}
/**
 * @param null $msg  返回具体错误的提示信息
 * @param flag success CURD 操作失败
 * Function descript:返回标志信息 ‘Error'，和提示信息的json 数组
 */
function apiError(){
    $msg=$data=$code='';
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
    }
    $result = array(
        'status' => 'Error',
        'msg' => $msg,
        'code'=>$code,
        'data' => $data,
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









