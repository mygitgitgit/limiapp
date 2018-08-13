<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/5/17 0017
 * Time: 17:54
 */

namespace app\apps1_6\controller;
use app\com\controller\Aliyunoss;
use app\com\controller\MusicFile;
use think\Db;

class Music extends Common
{
    public function _initialize(){

    }

    /**
     * 搜索、分类
     */
    public function musicList(){

        $page=input('get.page');
        $type=input('get.type');
        $m_id=input('get.m_id');
        // 2最新 1最热 0收藏         0经典、1校园、2舞蹈、3洗脑、4搞怪、5励志、6爱情、7游戏、8运动、9电音
        $name=input('get.name');
        $id=input('get.id');
        $token=input('get.token');

        if($name!=''){
            $d=[];
            $d['name']=['like','%'.$name.'%'];
            $res=Db::name('music')
                ->where($d)
                ->field('id music_id,name,singer,music,pic,time')
                ->select();
            if($res){
                foreach ($res as $k=>&$v){
                    $music_id=Db::name('user_music')
                        ->where(['music_id'=>$v['music_id'],'user_id'=>$id,'status'=>1])
                        ->value('id');
                    if($music_id){
                        $v['is_collect']=1;
                    }else{
                        $v['is_collect']=0;
                    }
                    if($v['pic']){
                        $v['pic']=AliUrl.$v['pic'];
                    }
                    if($v['music']){
                        $v['music']=AliUrl.$v['music'];
                    }

                }
                return apiSuccess('搜索结果',$res);
            }else{
                return apiSuccess('没有相关的内容');
            }
        }
        if($page==''||$page<1){
            apiError('请求页码有误');
            return;
        }
        $res=[];
        if($type==0){
            if($m_id==2){ //2 最新
                $res=Db::name('music')
                    ->where(['delete_time'=>null])
                    ->page($page,21)
                    ->order('create_time desc')
                    ->field('id music_id,name,singer,music,pic,time')
                    ->select();
            }elseif($m_id==1){  //1飙升

                $time=date('Y-m-d H:i:s',time()-7*24*3600);

                $res=Db::name('music')
                    ->where(['delete_time'=>null,'update_time'=>['>',$time]])
                    ->page($page,21)
                    ->order('down_num desc,create_time desc')
                    ->field('id music_id,name,singer,music,pic,time')
                    ->select();
            }elseif($m_id==0) {  //0收藏
                $re=$this->checkToken($id,$token);
                if($re['status']){
                    return apiError($re['msg'],'',$re['code']);
                }
                $list = Db::name('user_music')
                    ->where(['user_id' => $id, 'status' => 1])
                    ->field('music_id,music_type')
                    ->page($page,21)
                    ->select();
                if ($list) {
                    foreach ($list as &$v) {
                        if ($v['music_type'] === 0) {
                            $res1 = Db::name('music')
                                ->where('id', $v['music_id'])->where(['delete_time' => null])
                                ->field('id,name,singer,music,pic,time')
                                ->find();
                            $v['name'] = $res1['name'];
                            $v['singer'] = $res1['singer'];
                            $v['pic'] = AliUrl . $res1['pic'];
                            $v['music'] = AliUrl . $res1['music'];
                            $v['time'] = $res1['time'];
                            $v['is_collect'] = 1;
                        } elseif ($v['music_type'] == 1) {
                            $res2 = Db::name('music_user m')
                                ->join('user u', 'm.uid=u.id', 'LEFT')
                                ->where('m.id', $v['music_id'])->where(['m.delete_time' => null])
                                ->field('u.nickname,u.head_pic,m.music,m.time')
                                ->find();

                            $v['name'] = $res2['nickname'];
                            $v['singer'] = null;
                            $v['pic'] = ApiUrl . $res2['head_pic'];
                            $v['music'] = $res2['music'];
                            $v['time'] = $res2['time'];
                            $v['is_collect'] = 1;
                        }
                    }
                    return apiSuccess('收藏列表', $list);
                } else {
                    return apiSuccess('收藏列表', []);
                }
            }
        }elseif($type==1){
            if($m_id>=0&&$m_id<=9){
                $num=($page-1)*21;
                $res=Db::query("select id music_id,name,`singer`,music,pic,time from limi_music WHERE find_in_set($m_id,type) ORDER by create_time desc limit $num,21");
            }
        }
        if(!$res){
            return apiSuccess('音乐列表',$res);
        }
        foreach ($res as $k=>&$v){
            $music_id=Db::name('user_music')
                ->where(['music_id'=>$v['music_id'],'user_id'=>$id,'status'=>1])
                ->value('id');
            if($music_id){
                $v['is_collect']=1;
            }else{
                $v['is_collect']=0;
            }
            if($v['pic']){
                $v['pic']=AliUrl.$v['pic'];
            }
            if($v['music']){
                $v['music']=AliUrl.$v['music'];
            }
            $v['music_type']=0;
        }
        return apiSuccess('音乐列表',$res);

    }

    /**
     * 音乐种类
     */
    public function musicType(){
        $type1=['收藏','飙升','最新'];
        $type2=['经典','校园','舞蹈','洗脑','搞怪','励志','爱情','游戏','运动','电音'];
        $data1=array();
        $data2=array();
        foreach ($type1 as $k=>$v){
            $data1[]=[
                'type'=>0,
                'm_id'=>$k,
                'name'=>$v
            ];
        }
        foreach ($type2 as $k=>$v){
            $data2[]=[
                'type'=>1,
                'm_id'=>$k,
                'name'=>$v
            ];
        }
        $data=array_merge($data1,$data2);
        return apiSuccess('音乐种类',$data);
    }
    /**
     * 音乐取消收藏
     */
    public function musicCollect(){
        $music_id=input('post.music_id');
        $music_type=input('post.music_type'); //0  1
        $id=input('post.id');
        $token=input('post.token');
        $res=$this->checkToken($id,$token);
        if($res['status']){
            return apiError($res['msg'],'',$res['code']);
        }
        if($music_id==''){
            return apiError('音乐id不能为空');
        }
        if($music_type==''){
            return apiError('music_type不能为空');
        }
        $data=[
            'user_id'=>$id,
            'music_id'=>$music_id,
            'music_type'=>$music_type,
        ];
        //判断是否添加
        $res=Db::name('user_music')->where($data)->find();
        if(!$res){
            $data['create_time']=date('Y-m-d H:i:s',time());
            $res=Db::name('user_music')->insert($data);
            if($res===false)
            {return apiError('添加失败');}
            return apiSuccess('收藏成功');
        }else{
            $status=Db::name('user_music')->where($data)->value('status');
            if($status==1){
                $data2=[
                    'status'=>0,
                    'update_time'=>date('Y-m-d H:i:s',time())
                ];
                $res=Db::name('user_music')->where($data)->update($data2);
                if($res===false)
                {return apiError('取消失败');}
               return apiSuccess('取消收藏');
            }else{
                $data2=[
                    'status'=>1,
                    'update_time'=>date('Y-m-d H:i:s',time())
                ];
                $res=Db::name('user_music')->where($data)->update($data2);
                if($res===false)
                {return apiError('收藏失败');}
                return apiSuccess('收藏成功');
            }
        }

    }



}