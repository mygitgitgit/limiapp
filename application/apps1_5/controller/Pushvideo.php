<?php
/**
 * Created by PhpStorm.
 * User: zyjun
 * Date: 2018/5/18
 * Time: 12:05
 * 短视频内容推荐，
 * [不考慮性別，不考虑省份]直接以内容来推送。 比如男生爱看男生打篮球足球，不分省份，男生喜欢看美女跳舞，发闺蜜等,省外的也会喜欢。
 * 推荐按照关键词来推送70%喜好关键词，30%陌生关键词    按照推荐度来推荐。后台可自行设置推荐度，没有推荐度的按照
 * 评论+点赞+转发+播放量来生成一个推荐量值；目前暂定评论35%，点赞35%  播放40%，基数为100；例如评论1个，点赞2个，播放10个，
 * 基数=[1x0.35+2*0.35+10x0.40]x100=505；推荐度每天定时执行一次计算，上拉刷新只取最近1周的数据
 * 当没有关键词匹配时，直接返回陌生数据
 * 用户推荐的内容，user表默认保存7天，video表默认保存2个月，2个月前的数据从mysql读取，避免占用内存
 * redis推荐逻辑，user_id 表保存40条数据，客户端拉上刷新时，从redis list集合左侧读取当表中数据， 10条数据，读取完毕后删除，当发现list值<=20条时，启动推荐系统追加40-当前剩余条数据A到user_id表；
 *比如第一次是剩余40-20=20条，推荐A=20条，如果这20条去重，只有5条是新的，那么总剩下20+5=25；客户端刷新10条后，剩余15条，下次推荐就是A=40-15=25条进来；
 * 2次判断，第一次mysql要判断关键词取出来有没有A条，如果不满足A条，说明已经没有新的内容了，需要去陌生关键词补充内容进来。同样即便满足A条，如果user表剩余15条，但是去重后
 * 只有1条新的，那么加起来才15+1条，不足20条得继续去推荐；
 * 去重问题：去重不设置已推荐表，直接写入到user_recomm_id里面，数据200条，保证200条里面无重复数据。当user_recomm_id堆满200条后，不再推荐进来
 * 当list长度少于50时，再次启用推荐机制每次写入50条。直到写满
 * 当存在未登录用户时：新建一个有序合集，按照时间排序，值为videoid,然后用ZREVRANGE分页
 * video_id保存数据时，用hash，但是每个视频创建一个key,video_id1，video_id2，不在同一个key保存所有的是json数据，防止val太大超出512M
 *
 *              [有序集合，score存储时间，value存储视频id，用于普通查询]
 *         video_all
 *         video_id
 *              [hash类型存储单个视频详情，通过id到此表查询详情]
 *         user_recomm_id
 *              [用户推荐表，list集合固定40条，用于动态存储最新的个性化推荐数据,登录用户的个性化推荐用此表获取videoid,再去videoid表查询具体内容]
 *
 */

namespace app\apps1_5\controller;
use think\Controller;
use think\Db;
use app\com\controller\Redis;

class Pushvideo extends Common
{
    static $redis_db=RedisDb; //redis选择的数据库
    static $redis_pass='youhong@limiapp';
    static $redis_host='47.97.218.145';

    private $keyWord=0.7; //关键词
    private $comment=35; //评论
    private $click=35; //点赞
    private $play=35; //播放量


//    /**
//     * Created by zyjun
//     * Info:获取视频推荐
//     * 普通获取视频列表
//     */
//    public function getNomalVideoList($page,$time){
//        $where['create_time']=['<',$time];
//        $where['view_auth']=1;
//        $where['status']=0;
//        $res=Db::name('video')->where($where)->page($page,10)->field('id,user_id,title,video_cover,video,view_num,click_num,discuss_num,tags,music_id')->order('id desc')->select();
//        if(!empty($res)){
//            foreach ($res as $key=>$val){
//                $res[$key]['discuss_num']=$val['discuss_num'];
//                $res[$key]['click_num']=$val['click_num'];
//                $res[$key]['is_click']=0;
//                #获取音乐信息
//                $res[$key]['music_name']='';
//                $res[$key]['music_pic']='';
//                if(!empty($val['music_id'])){
//                    $music=Db::name('music')->where('id',$val['music_id'])->field('name,pic')->find();
//                    $res[$key]['music_name']=$music['name'];
//                    $res[$key]['music_pic']=$this->addMusicUrl($music['pic']);
//                }
//                #获取用户信息
//                $user=Db::name('user')->where('id',$val['user_id'])->field('nickname,head_pic')->find();
//                $res[$key]['user_nickname']=$user['nickname'];
//                $res[$key]['user_head_pic']=$this->addApiUrl($user['head_pic']);
//            }
//        }
//        return $res;
//    }
//
//
//    /**
//     * Created by zyjun
//     * Info:获取视频推荐
//     * 获取推荐视频列表
//     */
//    public function getRecommVideoList($page,$time,$uid){
//        $where['create_time']=['<',$time];
//        $where['view_auth']=1;
//        $where['status']=0;
//        $res=Db::name('video')->where($where)->page($page,10)->field('id,user_id,title,video_cover,video,view_num,click_num,discuss_num,tags,music_id')->order('id desc')->select();
//        if(!empty($res)){
//            foreach ($res as $key=>$val){
//                $res[$key]['click_num']=$val['click_num'];
//                $res[$key]['discuss_num']=$val['discuss_num'];
//                $res[$key]['is_click']=Db::name('video_click')->where('video_id',$val['id'])->where('user_id',$uid)->value('type');
//                if(empty($res[$key]['is_click'])){
//                    $res[$key]['is_click']=0;
//                }
//                $res[$key]['is_attention']=Db::name('user_relation')->where('user_id',$uid)->where('attention_id',$val['user_id'])->value('is_cancel');
//                if(empty( $res[$key]['is_attention'])){
//                    $res[$key]['is_attention']=0;
//                }
//                #获取音乐信息
//                $res[$key]['music_name']='';
//                $res[$key]['music_pic']='';
//                if(!empty($val['music_id'])){
//                    $music=Db::name('music')->where('id',$val['music_id'])->field('name,pic')->find();
//                    $res[$key]['music_name']=$music['name'];
//                    $res[$key]['music_pic']=$this->addMusicUrl($music['pic']);
//                }
//                #获取用户信息
//                $user=Db::name('user')->where('id',$val['user_id'])->field('nickname,head_pic')->find();
//                $res[$key]['user_nickname']=$user['nickname'];
//                $res[$key]['user_head_pic']=$this->addApiUrl($user['head_pic']);
//            }
//        }
//        return $res;
//    }

    /**
     * Created by zyjun
     * Info:获取视频推荐
     * 普通获取视频列表
     */
    public function getNomalVideoList($page,$time){
        $field=' a.id,a.user_id,a.title,a.video_cover,a.video,a.width,a.height,a.view_num,a.click_num,a.discuss_num,a.tags,a.music_id,a.music_type,b.id user_id,b.nickname user_nickname,CONCAT("'.ApiUrl.'",b.head_pic) AS user_head_pic,c.singer,c.name music_name,CONCAT("'.AliUrl.'",c.pic) AS music_pic,f.name college_name,f.coid college_id';
        $where='a.view_auth=1 AND a.status=0';
        $query="SELECT $field FROM limi_video AS a LEFT JOIN limi_user AS b ON a.user_id = b.id LEFT JOIN limi_music AS c ON (a.music_id = c.id AND a.music_type=0)  LEFT JOIN limi_college AS f ON b.college_id=f.coid WHERE $where ORDER BY RAND() LIMIT 20";
        $res=Db::query($query);
        $data=[];
        if(!empty($res)){
            foreach ($res as $key=>$val)  {
                $data[$key]['id']=$val['id'];
                $data[$key]['click_num']=$val['click_num'];
                $data[$key]['discuss_num']=$val['discuss_num'];
                $data[$key]['title']=$val['title'];

                $music=[];
                $music['music_id']=$val['music_id'];
                $music['music_type']=$val['music_type'];
                $music['name']=$val['music_name'];
                $music['pic']=$val['music_pic'];
                $music['singer']=$val['singer'];
                $data[$key]['music']=$music;

                $user=[];
                $user['user_id']=$val['user_id'];
                $user['head_pic']=$val['user_head_pic'];
                $user['nickname']=$val['user_nickname'];
                $data[$key]['user']=$user;

                $college=[];
                $college['id']=$val['college_id'];
                $college['name']=$val['college_name'];
                $data[$key]['user']['college']=$college;

                $video=[];
                $video['cover']=$val['video_cover'];
                $video['video']=$val['video'];
                $video['width']=$val['width'];
                $video['height']=$val['height'];
                $data[$key]['video']=$video;
            }
        }
        return $data;
    }


    /**
     * Created by zyjun
     * Info:获取视频推荐
     * 获取推荐视频列表
     */
    public function getRecommVideoList($page,$time,$uid){
        $url='http://video.youhongtech.com';
        $field=' a.id,a.user_id,a.title,a.video_cover,a.video,a.width,a.height,a.view_num,a.music_type, CASE d.type WHEN 1 THEN 1 WHEN 0 THEN 0 ELSE 0 END as is_click,CASE e.is_cancel WHEN 1 THEN 0 WHEN 0 THEN 1 ELSE 0 END as is_attention,a.click_num,a.discuss_num,a.tags,a.music_id,b.id user_id,b.nickname user_nickname,CONCAT("'.ApiUrl.'",b.head_pic) AS user_head_pic,c.singer,c.name music_name,CONCAT("'.AliUrl.'",c.pic) AS music_pic,f.name college_name,f.coid college_id';
        $child_sql="SELECT black_user_id FROM limi_user_black WHERE user_id=$uid";
        $where='a.view_auth=1 AND a.status=0 AND a.user_id NOT IN ('.$child_sql.')';
        $query="SELECT $field FROM limi_video AS a LEFT JOIN limi_user AS b ON a.user_id = b.id LEFT JOIN limi_music AS c ON (a.music_id = c.id AND a.music_type=0) LEFT JOIN limi_video_click AS d ON (a.id = d.video_id AND d.user_id=$uid ) LEFT JOIN limi_user_relation AS e ON (e.attention_id=a.user_id AND e.user_id=".$uid." ) LEFT JOIN limi_college AS f ON b.college_id=f.coid WHERE $where ORDER BY RAND() LIMIT 20";
        $res=Db::query($query);
        $data=[];
        if(!empty($res)){
            foreach ($res as $key=>$val)  {
                $data[$key]['id']=$val['id'];
                $data[$key]['click_num']=$val['click_num'];
                $data[$key]['discuss_num']=$val['discuss_num'];
                $data[$key]['title']=$val['title'];
                $data[$key]['is_attention']=$val['is_attention'];
                $data[$key]['is_click']=$val['is_click'];

                $music=[];
                $music['music_id']=$val['music_id'];
                $music['music_type']=$val['music_type'];
                $music['name']=$val['music_name'];
                $music['pic']=$val['music_pic'];
                $music['singer']=$val['singer'];
                $data[$key]['music']=$music;

                $user=[];
                $user['user_id']=$val['user_id'];
                $user['head_pic']=$val['user_head_pic'];
                $user['nickname']=$val['user_nickname'];
                $data[$key]['user']=$user;

                $college=[];
                $college['id']=$val['college_id'];
                $college['name']=$val['college_name'];
                $data[$key]['user']['college']=$college;

                $video=[];
                $video['cover']=$val['video_cover'];
                $video['video']=$val['video'];
                $video['width']=$val['width'];
                $video['height']=$val['height'];
                $data[$key]['video']=$video;
            }

        }
        return $data;
    }

    /**
     * Created by zyjun
     * Info:获取用户信息，用于个性化推荐
     */
    public function getUserInfo($uid){
        $res=Db::name('user')->where('id',1)->field('sex,keyword,province_id')->find();
        $data['keyWord']=explode(',',$res['keyword']);
        $data['sex']=$res['sex'];
        $data['province']=$res['province'];
        return $data;
    }





}
