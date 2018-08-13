<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/4/9 0009
 * Time: 17:01
 */

namespace app\apps1_5\model;


use think\Model;
use traits\model\SoftDelete;

class Discuss extends Model
{
    use SoftDelete;
    protected $deleteTime = 'delete_time';
    //关闭更新时间字段
    protected $updateTime = false;
}