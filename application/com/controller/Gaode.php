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

class Gaode
{
    const KEY='2daffabda7cfdfe09dac6672460137b3';
    /**
     * Created by zyjun
     * Info:初始化
     */
    public function  __construct(){

    }

    /**
     * Created by zyjun
     * Info:获取地理位置信息，这个接口只能查询到一个具体的位置
     */
   public function getGeoData($data){
       $url="https://restapi.amap.com/v3/geocode/geo?key=".self::KEY;
       $res=$this->joinUrl($url,$data);
       if($res['status']){
           $re['msg']=$res['msg'];
           $re['status']=1;
           return $re;
       }
       $getUrl=$res['data'];
       $res=httpGetData($getUrl);
       if($res['status']){
           $re['msg']=$res['msg'];
           $re['status']=1;
           return $re;
       }
       $res= $res['data'];
       if($res['status']!=1){
           $re['msg']=$res['定位失败'];
           $re['status']=1;
           return $re;
       }
       $re['data']=$res['geocodes'];
       $re['status']=0;
       return $re;
   }

    /**
     * Created by zyjun
     * Info:获取POI位置信息,接口返回中文搜索相关的多个位置地点
     */
    public function getPoiData($data){
        $url="https://restapi.amap.com/v3/place/text?key=".self::KEY;
        $res=$this->joinUrl($url,$data);
        if($res['status']){
            $re['msg']=$res['msg'];
            $re['status']=1;
            return $re;
        }
        $getUrl=$res['data'];
        $res=httpGetData($getUrl);
        if($res['status']){
            $re['msg']=$res['msg'];
            $re['status']=1;
            return $re;
        }
        $res= $res['data'];
        if($res['status']!=1){
            $re['msg']='定位失败';
            $re['status']=1;
            return $re;
        }
        $re['data']=$res['pois'];
        $re['status']=0;
        return $re;
    }

    /**
     * Created by zyjun
     * Info:获取逆地理位置信息
     */
    public function getRgeoData($data){
        $url="https://restapi.amap.com/v3/geocode/regeo?key=".self::KEY;
        $res=$this->joinUrl($url,$data);
        if($res['status']){
            $re['msg']=$res['msg'];
            $re['status']=1;
            return $re;
        }
        $getUrl=$res['data'];
        $res=httpGetData($getUrl);
        if($res['status']){
            $re['msg']=$res['msg'];
            $re['status']=1;
            return $re;
        }
        $res= $res['data'];
        if($res['status']!=1){
            $re['msg']='定位失败';
            $re['status']=1;
            return $re;
        }
        $re['data']=$res['regeocode'];
        $re['status']=0;
        return $re;

    }

    /**
     * Created by zyjun
     * Info:组装url参数
     */
    public function joinUrl($url,$data){
        if(empty($data)){
            $re['msg']='参数错误';
            $re['status']=1;
            return $re;
        }
        foreach ($data as $key=>$val){
            $url=$url."&$key=$val";
        }
        $re['data']=$url;
        $re['status']=0;
        return $re;
    }


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

}




