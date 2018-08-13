<?php

/*************************** 开发辅助函数 **********************/
/**
 * Created by zyjun
 * Info:返回错误信息，json格式
 * 第1个参数：返回的消息  第2个参数：返回后台数据， 第3个参数：返回状态码
 */
function Success(){
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
function Error(){
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




