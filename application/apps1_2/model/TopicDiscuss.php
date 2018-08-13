<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/4/16 0016
 * Time: 15:56
 */

namespace app\apps1_2\model;


use think\Model;
use traits\model\SoftDelete;

class TopicDiscuss extends Model
{
    use SoftDelete;
    //关闭更新时间字段
    protected $updateTime = false;
}