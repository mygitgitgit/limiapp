<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/2/5 0005
 * Time: 13:06
 */

namespace app\apps1_2\model;


use think\Model;
use traits\model\SoftDelete;

class TopicAction extends Model
{

    //使用软删除
    use SoftDelete;
    //关闭更新时间字段
    protected $updateTime = false;
}