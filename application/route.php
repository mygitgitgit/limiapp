<?php
use think\Route;
use think\Request;
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

/********识别外部版本********/
$path= Request::instance()->path(); //判断来源版本
$path_arr=explode('/',$path);
if(count($path_arr)<3){//非法的请求 正常请求包含模块/控制器/方法
    return;
}
$moudle=$path_arr[0];
if(in_array($moudle,['apps','apps2','apps1_2','app1_3'])){ //老版本接口不做路由处理
    Error('请升级到最新版本使用');
   exit();
}
$controller=$path_arr[1];
$action=$path_arr[2];

/****************不同版本号对接不同模块****************/
//Route::rule('app1_3','apps1_2/'.$controller.'/'.$action);
//1.4版本接口
Route::rule('app1_4','apps1_4/'.$controller.'/'.$action);

//1.5版本接口
Route::rule('app1_5','apps1_5/'.$controller.'/'.$action);

//1.6版本接口
Route::rule('app1_6','apps1_6/'.$controller.'/'.$action);

//1.7版本接口
Route::rule('app1_7','apps1_7/'.$controller.'/'.$action);



