<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/2/6 0006
 * Time: 10:13
 */

namespace app\apps1_2\controller;


use think\Db;

class Near extends Common
{
    /**
     * 保存用户位置信息
     */
    public function addSite(){
        $id=input('id');
        $token=input('token');

        $lat=input('lat');  //纬度
        $lng=input('lng');  //经度

        //1 判断用户是否存在
        $res=$this->checkToken($id,$token);
        if($res['status']){
            return apiError($res['msg'],'',$res['code']);
        }
        //2 判断用户是否已经通过认证
        $res=$this->isAccess($id);
        if($res['identity_status']!=2){
            return apiError($res['msg'],$res['identity_status']);
        }
        if($lat==''){
            return apiError('纬度不能为空');
        }
        if($lng==''){
            return apiError('经度不能为空');
        }
        //整理数据保存数据库
        //判断是更新还是添加位置信息
        $data=[
            'user_id'=>$id,
            'lat'=>$lat,
            'lng'=>$lng,
            'create_time'=>date('Y-m-d H:i:s',time())
        ];
        $yes=Db::name('user_location')->where('user_id',$id)->find();
        if($yes){
            //更新位置信息
            $res=Db::name('user_location')->where('user_id',$id)
                ->update(['lat'=>$lat,'lng'=>$lng,'create_time'=>date('Y-m-d H:i:s',time())]);
            if($res){
                return apiSuccess('更新成功');
            }
        }else{
            $res=Db::name('user_location')->insert($data);
            if($res){
                return apiSuccess('添加成功');
            }
        }
    }


    /**
     * 附近的人列表信息
     */
    public function nearUserList(){
        $user_id=input('get.id');
        $token=input('get.token');
        $page=input('get.page');
        $lat=input('get.lat');  //纬度
        $lng=input('get.lng');  //经度

        $sex=input('sex','');
        //1 判断用户是否存在
        $res=$this->checkToken($user_id,$token);
        if($res['status']){
            return apiError($res['msg'],'',$res['code']);
        }
        //2 判断用户是否已经通过认证
        $res=$this->isAccess($user_id);
        if($res['identity_status']!=2){
            return apiError($res['msg'],$res['identity_status']);
        }
        if($lat==''){
            return apiError('纬度不能为空');
        }
        if($lng==''){
            return apiError('经度不能为空');
        }
        if($page==''||$page<1){
            apiError('请求页码有误');
            return;
        }
        $dat=[
            'user_id'=>$user_id,
            'lat'=>$lat,
            'lng'=>$lng,
            'create_time'=>date('Y-m-d H:i:s',time())
        ];
        //判断位置表里面是否有用户的位置信息
        $location=Db::name('user_location')->where('user_id',$user_id)->find();
        if(!$location){
            //没有 添加用户位置信息
            $res=Db::name('user_location')->insert($dat);
            if(!$res){
                return apiError('添加位置失败');
            }
        }else{
            //如果有位置信息，更新位置信息
            $res=Db::name('user_location')->where('user_id',$user_id)
                ->update(['lat'=>$lat,'lng'=>$lng,'create_time'=>date('Y-m-d H:i:s',time())]);
            if(!$res){
                return apiError('更新失败');
            }
        }
        // 查询附近用户信息列表
        if($sex!=''){
            //有筛选条件
            $where['sex']=$sex;
            $allLocation=$this->nearList($user_id,$where);
        }else{
            $allLocation=$this->nearList($user_id);
        }
        $data=array();
        foreach ($allLocation as $k=>$value){
            if((time()-strtotime($value['create_time']))>3600*12*3){
                continue;
            }
            $data[]=[
                'user_id'=>$value['user_id'],
                'nickname'=>$value['nickname'],
                'head_pic'=>$value['head_pic'],
                'content'=>$this->userTextDecode($value['content']),
                'sex'=>(int)$value['sex'],
                'distance'=>round($this->getDistance($lng,$lat,$value['lng'],$value['lat']))
            ];
        }
        //dump($data);die;
        //按照距离 重新排序
        for($i=0;$i<count($data)-1;$i++){
            for($j=0;$j<count($data)-1-$i;$j++){
                if($data[$j]['distance']>$data[$j+1]['distance']){
                    $tamp=$data[$j];
                    $data[$j]=$data[$j+1];
                    $data[$j+1]=$tamp;
                }
            }
        }
        foreach ($data as $k=>&$value){
            if($value['distance']>=1000){
                $value['distance']=round($value['distance']/1000).'km';
            }else{
                if($value['distance']<=0){
                    $value['distance']='1m';
                }else{
                    $value['distance']=$value['distance'].'m';
                }

            }
            //$data[$k]=array_merge($allLocation[$k],$user);
        }
        $d=array();
        $maxp=ceil(count($data)/20);
        if($page>$maxp){
            return apiSuccess();
        }
        if($page==$maxp){
            for($i=($page-1)*20;$i<count($data);$i++){
                $d[]=$data[$i];
            }
        }else{
            for($i=($page-1)*20;$i<=($page-1)*20+19;$i++){
                $d[]=$data[$i];
            }
        }
        return apiSuccess('',$d);
    }

    /**
     * 编辑个性签名
     */
    public function editContent(){
        $user_id=input('get.id');
        $token=input('get.token');
        //1 判断用户是否存在
        $res=$this->checkToken($user_id,$token);
        if($res['status']){
            return apiError($res['msg'],'',$res['code']);
        }
        //2 判断用户是否已经通过认证
        $res=$this->isAccess($user_id);
        if($res['identity_status']!=2){
            return apiError($res['msg'],$res['identity_status']);
        }
        // 3判断是否有用户的位置信息
//        $location=Db::name('user_location')->where('user_id',$user_id)->find();
//        if(!$location){
//            return apiError('没有该用户位置信息');
//        }
        $content=Db::name('user_location')->where('user_id',$user_id)->value('content');
        $content=$this->userTextDecode($content);
        if($content){
            return apiSuccess('',$content);
        }else{
            return apiSuccess('签名为空');
        }

    }

    /**
     * 提交个人签名
     */
    public function updateContent(){
        $user_id=input('id');
        $token=input('token');
        //1 判断用户是否存在
        $res=$this->checkToken($user_id,$token);
        if($res['status']){
            return apiError($res['msg'],'',$res['code']);
        }
        //2 判断用户是否已经通过认证
        $res=$this->isAccess($user_id);
        if($res['identity_status']!=2){
            return apiError($res['msg'],$res['identity_status']);
        }
        $content=input('content');
        if(empty(trim($content))){
            return apiError('不能为空');
        }
        $content = preg_replace('/\s*/', '', $content);
        if(strlen($content)>60){
            return apiError('不超过20个字');
        }
        $content=$this->userTextEncode($content);
        $content2=Db::name('user_location')->where('user_id',$user_id)->value('content');
        if($content==$content2){
            return apiSuccess('保存成功');
        }else{
            $res=Db::name('user_location')->where('user_id',$user_id)->update(['content'=>$content]);
            if($res){
                return apiSuccess('保存成功');
            }
        }
    }
    /**
     *清除位置信息
     */
    public function clearLocation(){
        $user_id=input('get.id');
        $token=input('get.token');
        //1 判断用户是否存在

        $res=$this->checkToken($user_id,$token);
        if($res['status']){
            return apiError($res['msg'],'',$res['code']);
        }
        //2 判断用户是否已经通过认证
        $res=$this->isAccess($user_id);
        if($res['identity_status']!=2){
            return apiError($res['msg'],$res['identity_status']);
        }
        //清除位置表中该用户的位置信息
        $yes=Db::name('user_location')->where('user_id',$user_id)->find();
        if($yes){
            $res=Db::name('user_location')->where('user_id',$user_id)->update(['lat'=>null,'lng'=>null,'content'=>'']);
            if($res){
                return apiSuccess('清除成功');
            }
        }else{
            return apiSuccess('已经清除成功');
        }


    }

}