<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/4/9 0009
 * Time: 17:01
 */

namespace app\apps2\model;


use think\Model;
use traits\model\SoftDelete;

class Discuss extends Model
{
    use SoftDelete;
    //关闭更新时间字段
    protected $updateTime = false;
}