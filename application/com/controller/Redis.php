<?php
namespace app\com\controller;
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/1/31 0031
 * Time: 16:47
 * info: Redis二次封装，适用于本APP场景，不具备通用移植性
 * 暂时不用缓存，直接mysql处理
 */

class Redis
{
    public $Redis;
    /**
     * Created by zyjun
     * Info:初始化
     */
    public function  __construct($redis_db,$redis_pass,$redis_host){
        $redis = new \Redis();
        $redis->connect($redis_host, 6379);
        $redis->auth($redis_pass);
        $redis->select($redis_db); //选择数据库
        $this->Redis=$redis;
    }

#***************hash表****************#
    /**
     * Created by zyjun
     * Info:向hash表写入单个或者多个key_val键值对
     * (array)$data
     */
    public function wHashData($redis_key,$data,$expire){
          foreach ($data as $key=>$val){
              $this->Redis->hMSET($redis_key,[$key=$val]);
          }
         $this->Redis->expire($redis_key,$expire);
    }





#***************list表****************#





#***************有序列表****************#
    /**
     * Created by zyjun
     * Info:向有序列表写一个数据或者多个数据
     *$param[score,value] 如果score=''.那么score按照自然正整数排序
     * 要求$param,要么有score值，要么都没值，好用于排序
     */
public function wZsetData($redis_key,$param){
    if (count($param) == count($param, 1)) { //一维数组 单个数据
        if($param['score']==''){
            #获取最后一个score
            $last_score=$this->getLastZsetScore($redis_key);
            $param['score']=$last_score+1;
        }
        #判断缓存是否达到1万条
        $len=$this->Redis->zCard($redis_key);
        if($len>=10000){
            #删除左边数据再写入
            $this->Redis->zRemRangeByRank($redis_key,0,0);
            $this->Redis->zAdd($redis_key,$param['score'],$param['value']);
        }else{
            $this->Redis->zAdd($redis_key,$param['score'],$param['value']);
        }
    } else { //二位数组多个数据
        foreach ($param as $key=>$val){
            if($val['score']==''){
                #获取最后一个score
                $last_score=$this->getLastZsetScore($redis_key);
                $val['score']=$last_score+1;
            }
            #判断缓存是否达到1万条
            $len=$this->Redis->zCard($redis_key);
            if($len>=10000){
                #删除左边数据再写入
                $this->Redis->zRemRangeByRank($redis_key,0,0);
                $this->Redis->zAdd($redis_key,$val['score'],$val['value']);
            }else{
                $this->Redis->zAdd($redis_key,$val['score'],$val['value']);
            }
        }
    }

}


    /**
     * Created by zyjun
     * Info:获取有序列表
     */
    public function getLastZsetScore($redis_key){
        $count=$this->Redis->zCard($redis_key);
        $score=0;
        if($count==0){
            $score=0; //列表没数据，默认score=0
        }else{
            $count=$count-1;
            $res=$this->Redis->zRange($redis_key,$count,$count,true);
            foreach ($res as $key=>$val){
                $score=$res[$key];
            }
        }
        return $score;
    }

}




