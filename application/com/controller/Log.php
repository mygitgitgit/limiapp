<?php
namespace app\com\controller;
use think\Controller;
use think\Db;
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/1/31 0031
 * Time: 16:47
 * info: Log 日志，不具备通用移植性；后期可能其他功能，比如日志先写到本地缓存，再定期读入mysql
 */

class Log
{

    /**
     * Created by zyjun
     * Info:初始化
     */
    public function  __construct(){

    }

    /**
     * Created by zyjun
     * Info:写入日志到数据库
     * (array)$param   key=>val, key值可选为 level,code,item,des,(json)content
     * level：info['普通日志信息，比如回调详细数据，普通操作记录等']，warn['不影响业务，但是长期错误可能会导致错误产生']<Error[错误：已经影响了业务的进行，数据产生错误]
     * code:100-199:用户注册登录权限等日志 200-299：短视频日志    300-399：支付相关
     */
    static function write($level,$code,$item,$des,$content){
        try{
            $param['level']=$level;
            $param['code']=$code;
            $param['item']=$item;
            $param['des']=$des;
            $param['content']=$content;
            $param['time']=date('Y-m-d H:i:s',time());
            Db::name('applog')->insert($param);
        }catch (\Exception $e){
            return;
        }

    }


//    private function config(){
//        $config=array
//    }

}




