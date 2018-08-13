<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/1/19 0019
 * Time: 15:46
 */

namespace app\apps1_7\model;
use think\Model;
use traits\model\SoftDelete;

class Action extends Model
{
    //使用软删除
    use SoftDelete;
    //关闭更新时间字段
    protected $updateTime = false;

}