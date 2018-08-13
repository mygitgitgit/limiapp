<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/1/31 0031
 * Time: 16:47
 * info: 网易云信
 */

class ImApi{

    private $AppKey='525f6f12511728fc7805594e96587546';  //开发者平台分配的AppKey
    private $AppSecret='9552846d92e9';   //开发者平台分配的AppSecret,可刷新
    private $Nonce;					//随机数（最大长度128个字符）
    private $CurTime;             	//当前UTC时间戳，从1970年1月1日0点0 分0 秒开始到现在的秒数(String)
    private $CheckSum;				//SHA1(AppSecret + Nonce + CurTime),三个参数拼接的字符串，进行SHA1哈希计算，转化成16进制字符(String，小写)



    /**
     * Created by zyjun
     * Info:获取通讯token
     */
    public function getToken(){
    }

    /**
     * Created by zyjun
     * Info:API checksum校验
     */
    public function checkSum(){
        $this->Nonce=MD5(time().rand(1,100));
        $this->CurTime=time();
        $this->CheckSum=sha1($this->AppSecret.$this->Nonce.$this->CurTime);
    }

    public function postData($url,$data){
        //发送请求前需先生成checkSum
        $this->checkSum();
        //设置header
        $timeout = 5000;
        $header = array(
            'AppKey:'.$this->AppKey,
            'Nonce:'.$this->Nonce,
            'CurTime:'.$this->CurTime,
            'CheckSum:'.$this->CheckSum,
            'Content-Type:application/x-www-form-urlencoded;charset=utf-8'
        );
        //处理发送的数据
        $data=$this->handelPostData($data);
        //开始发送
        $ch = curl_init();
        curl_setopt ($ch, CURLOPT_URL, $url);
        curl_setopt ($ch, CURLOPT_POST, 1);
        curl_setopt ($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt ($ch, CURLOPT_HEADER, false );
        curl_setopt ($ch, CURLOPT_HTTPHEADER,$header);
        curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER,false); //处理http证书问题
        curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);

        $result = curl_exec($ch);
        if (false === $result) {
            $result =  curl_errno($ch);
             $redata['status']=1;
             $redata['data']=$result;
             $redata['msg']='CURLOPT链接失败';
             return $redata;
        }else{
            $redata['status']=0;
            $redata['data']=json_decode($result,true);
            $redata['msg']='链接成功';
            return $redata;
        }
        curl_close($ch);
    }

    /**
     * Created by zyjun
     * Info:处理传输数组格式
     */
    public function handelPostData($data){
        $post_str='';
        foreach ($data as $key=>$val){
            $post_str.=$key.'='.$val.'&';
        }
        $post_str=substr($post_str,0,-1);
        return $post_str;
    }

}