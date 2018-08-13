<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/2/23 0023
 * Time: 11:56
 */

namespace app\apps2\controller;



use think\Db;

class Weekend extends Common
{
    //周末游前五条数据
    public function CircleList(){
        $weekend_list=Db::name('weekend')
            ->order('create_time desc')
            ->limit(5)
            ->field('id,pic,name,price')
            ->where('status',1)
            ->select();
        foreach ($weekend_list as &$v){
            if($v['pic']){
                $pic=explode(',',$v['pic']);
                foreach ($pic as & $value){
                    $value=$this->addApiUrl($value);
                }
                $v['pic']=$pic[0];
            }
            $title=$v['name'];
            $length=strlen($title);
            if($length>25){
                $title=mb_substr($title,0,18);
                $v['name']=$title.'...';
            }
        }
        return apiSuccess('',$weekend_list);

    }
    //周日游首页
    //num数量订单数量 weekend_order表中该活动的订单数量
    public function WeekendIndex(){
        $page=input('get.page');
        if ($page == '' || $page < 1) {
            apiError('请求页码有误');
            return;
        }
        $weekend_list=Db::name('weekend')
            ->field('id,pic,name,feature,price,to,time,sham_order')
            ->where('status',1)
            ->order('create_time desc')
            ->page($page,20)
            ->select();
        foreach ($weekend_list as $k=>&$v){
            $num=Db::name('weekend_order_goods')
                ->where('weekend_id',$weekend_list[$k]['id'])
                ->field('sum(num) num')
                ->find();

            $weekend_list[$k]['num']=(int)$num['num']+$v['sham_order'];
            if($v['pic']){
                $pic=explode(',',$v['pic']);
                foreach ($pic as & $value){
                    $value=$this->addApiUrl($value);
                }
                $v['pic']=$pic[0];
            }
        }
        for($i=0;$i<count($weekend_list)-1;$i++){
            for($j=0;$j<count($weekend_list)-1-$i;$j++){
                if($weekend_list[$j]['num']<$weekend_list[$j+1]['num']){
                    $tamp=$weekend_list[$j];
                    $weekend_list[$j]=$weekend_list[$j+1];
                    $weekend_list[$j+1]=$tamp;
                }
            }
        }
        return apiSuccess('',$weekend_list);
    }
    //活动详情显示
    public function WeekendInfo(){
        $weekend_id=input('get.weekend_id');
        //查询活动详情信息
        //活动名称，活动特色，活动图片，时间，单价，起始地，目的地，活动简介，活动流程，费用包含，商家名称，商家地址，logo
        $weekend=Db::name('weekend')
            ->find($weekend_id);
            if($weekend['pic']){
                $pic=explode(',',$weekend['pic']);
                foreach ($pic as & $value){
                    $value=$this->addApiUrl($value);
                }
                $weekend['pic']=$pic;
            }
        if($weekend['logo']) {
            $weekend['logo'] = $this->addApiUrl($weekend['logo']);
        }

        return apiSuccess('',$weekend);
    }
    //提交订单页面展示
    public function WeekendOrder(){
        //判断用户是否存在
        $id=input('get.id');
        $token=input('get.token');
        $weekend_id=input('get.weekend_id');
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
        if($weekend_id!=''){
            $weekend=Db::name('weekend')
                ->field('name,feature,pic,price')
                ->find($weekend_id);
            if($weekend['pic']){
                $pic=explode(',',$weekend['pic']);
                foreach ($pic as & $value){
                    $value=$this->addApiUrl($value);
                }
                $weekend['pic']=$pic[0];
            }
            return apiSuccess('',$weekend);
        }
        return apiError('$weekend_id不能为空');
    }

    /**
     * Created by zyjun
     * Info:提交订单到数据库，完成支付功能，如果是购物车不同类型提交，那么要写多张订单表
     */
    public function orderAction()
    {   $uid=input('post.id');
        $token=input('post.token');
        $goods_id = input('post.goods_id'); //活动id
        $goods_num = input('post.goods_num'); //商品数量
        $note = input('post.text'); //订单备注
        $time = input('post.time'); //预定日期
        $mobile=input('post.mobile'); //客户预留电话
        $pay_type = input('post.pay_type');  //支付方式
        //用户验证
        $res = $this->checkToken($uid, $token);
        if ($res['status']) {
            return apiError($res['msg'], '', $res['code']);
        }
        //2 判断用户是否已经通过认证
        $res=$this->isAccess($uid);
        if($res['identity_status']!=2){
            return apiError($res['msg'],$res['identity_status']);
        }
        //验证数据
        if ($this->checkInt($goods_num, '', '')) {
            return apiError('参数错误');
        }
        $goods_num=(int)$goods_num;
        if($goods_num<=0||$goods_num>100){
            return apiError('购买数量为1-100个');
        }
        if(checkMobile($mobile)){
            return apiError('手机号码格式错误');
        }
        if(checkDateTime($time)){
            return apiError('预定时间格式错误');
        }
        if(strlen($note)>255){
            return apiError('备注说明最大长度为255个字符');
        }
        if(!in_array($pay_type,[1,2])){  //交易类型  1：支付宝  2：微信  3：银行卡
            return apiError('支付方式参数错误');
        }
        $res=Db::name('weekend')->where('id',$goods_id)->field('price,name')->find();
        if(empty($res)){
            return apiError('获取商品信息异常');
        }
        $price=$res['price'];
        if($price<0||$price>100000){
            return apiError('商品价格异常');
        }
        $money=$goods_num*$price;
        //生成订单
        $data['order_num']=$shop_order_no=$this->createShopOrderNo();
        $data['user_id']=$uid;
        $data['user_phone']=$mobile;
        $data['money']=$money;
        $data['time']=$time;
        $data['text']=$note;
        $data['create_time']=date('Y-m-d H:i:s');
        $res=Db::name('weekend_order')->insert($data); //周末游玩商城订单生成  1
        if(empty($res)){
            return apiError('订单提交失败,请联系管理员处理');
        }
        $order_id=Db::name('weekend_order')->where('order_num',$shop_order_no)->value('id');
        $order_goods=Db::name('weekend')->where('id',$goods_id)->find();
        $order_goods['num']=$goods_num; //在订单商品表写入购买数量和周末游ID，如果以后有多个商品，记得写多次
        $order_goods['weekend_id']=$goods_id;
        $order_goods['order_id']=$order_id;
        unset($order_goods['id']);
        unset( $order_goods['sham_order']);
        $res=Db::name('weekend_order_goods')->insert($order_goods); //周末游玩订单商品详情  2
        if(empty($res)){
            return apiError('订单提交失败,请联系管理员处理');
        }
        $order['uid'] = $uid;
        $order['pay_type'] = $pay_type;
        $order['money'] =$money;
        $order['order_out_biz_no'] = $out_biz_no=$this->createBusinessNo();
        $order['shop_order_no'] = $shop_order_no;
        $order['pay_status'] = 0;
        $order['time'] = date('Y-m-d H:i:s', time());
        $res=Db::name('order')->insert($order); //周末游玩支付订单生成  3
        if(empty($res)){
            return apiError('订单提交失败,请联系管理员处理');
        }
        //发起支付
        $order_name='商品购买-粒米校园';
        $pay = new Pay();
        $res=$pay->getOnlinePayOrderInfo($pay_type,$order_name,$money,$out_biz_no);
        if($res['status']){
            return apiError($res['msg']);
        }
        apiSuccess($res['msg'],$res['data']);

    }

    /**
     * Created by zyjun
     * Info:返回订单价格  暂时不用，前台计算
     */
    public function getGoodsPrice()
    {   $uid=input('post.id');
        $token=input('post.token');
        $goods_id=input('post.goods_id');
        $goods_num = '2';
        //用户验证
        $res = $this->checkToken($uid, $token);
        if ($res['status']) {
            return apiError($res['msg'], '', $res['code']);
        }
        //参数验证
        if ($this->checkInt($goods_num, '', '')) {
         return apiError('参数错误');
        }
        $goods_num=(int)$goods_num;
        if($goods_num<=0||$goods_num>100){
            return apiError('购买数量为1-100个');
        }
        $res=Db::name('weekend')->where('id',1)->field('price')->find();
        if(empty($res)){
            return apiError('获取商品信息异常');
        }
        $price=$res['price'];
        if($price<0||$price>100000){
            return apiError('商品价格异常');
        }
        $money=$goods_num*$price;
        apiSuccess('商品价格',$money);
    }
}