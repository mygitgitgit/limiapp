<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/1/25 0025
 * Time: 10:25
 * linux定时计划任务
 */
namespace app\apps1_5\controller;
use think\Db;

class Task extends Common
{
    public function _initialize(){
     $token=input('get.token');
     if($token!='c1b2c27021bd4d9a69ad2b1128680e33'){
         $this->taskLog(['title'=>'定时返回未领取红包','content'=>'token错误，非法执行']);
         return;
     }
    }

    /**
     * Created by zyjun
     * Info:返回红包
     */
    public function backRedPacket(){
        $expire_time=Db::name('sys_set')->where('id',1)->value('data');
        $expire_time=json_decode($expire_time,TRUE);
        $expire_time=$expire_time['data'];
        $limit_time=date('Y-m-d H:i:s',time()-$expire_time);
        $res=Db::name('redpacket')->where('is_back',0)->where('is_over',0)->where('sent_time','<',$limit_time)->select();
        if(empty($res)){
            $this->taskLog(['title'=>'定时返回未领取红包','content'=>'红包表没查询到任何过期红包记录']);
            return;
        }
        foreach ($res as $key=>$val){ //红包数组
            $temp_red_packet=array(); //接收每个红包未领取的余额
            $money=$val['money']; //总额
            $uid=$val['uid']; //发红包的人
            $did=$val['did'];
            $red_packet_id=$val['id'];
            $red_packet_data=json_decode($val['data'],true);
            if(!empty($red_packet_data)){ //已经拆分为小红包了
                foreach ($red_packet_data as $key=>$val){  //小红包数组  小红包状态0 未解决  1已经解决， 1表示已经倍用户领取了，2表示没被领取，返回没被领取的部分
                    $temp_red_packet[$key]=$val['money'];
                    if($val['solve_status']==1&&$val['uid']!=''){
                        unset($temp_red_packet[$key]); //已经被领取的都销毁
                    }
                }
                $total_red_packet=array_sum($temp_red_packet); //小红包的值
            }else{ //只是塞入了钱包
                $total_red_packet=$money;
            }
            if($total_red_packet>$money){
                $this->redPacketLog($uid,array('red_packet_id'=>$red_packet_id,'des'=>'linux定时任务:返回红包异常,返回金额大于红包总额'));
                continue;
            }
            //开始返回金额
            $res=Db::name('user_wallet')->where('uid',$uid)->setInc('money',$total_red_packet);
            if(empty($res)){
                $this->redPacketLog($uid,array('red_packet_id'=>$red_packet_id,'des'=>'linux定时任务:返回红包异常,金额未成功返还到用户账户'));
            }
            $res=Db::name('redpacket')->where('id',$red_packet_id)->update(['is_over'=>0,'is_back'=>1]);
            if($res===false){
                $this->redPacketLog($uid,array('red_packet_id'=>$red_packet_id,'des'=>'linux定时任务:已返回红包金额到账户,但是未成功设置标注is_over=1，is_back=1'));
            }
            $wallet=$this->getNowWallet($uid);
            $this->redPacketRecordDetail($uid,$red_packet_id,1,$did,5,$total_red_packet,$wallet,1,'红包退回');
        }
        $this->taskLog(['title'=>'定时返回未领取红包','content'=>'执行完毕']);
    }

}