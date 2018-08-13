<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/5/31 0031
 * Time: 10:52
 */

namespace app\apps1_6\model;


use traits\model\SoftDelete;

class Video
{
//使用软删除
    use SoftDelete;
    //关闭更新时间字段
    protected $updateTime = false;
}