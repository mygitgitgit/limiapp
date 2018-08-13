<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/5/29 0029
 * Time: 16:40
 */

namespace app\apps1_4\model;


use think\Model;
use traits\model\SoftDelete;

class VideoDiscuss extends Model
{
    use SoftDelete;
    protected $deleteTime = 'delete_time';
    //关闭更新时间字段
    protected $updateTime = false;

}