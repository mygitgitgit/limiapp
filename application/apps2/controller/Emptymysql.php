<?php
namespace app\apps2\controller;
use think\Db;
use think\Request;

class Emptymysql extends Common
{
  public function index(){
      $table_name_arr = Db::query("SELECT table_name FROM information_schema.TABLES WHERE TABLE_SCHEMA='limiapp'");
      $table_name_arr = array_column($table_name_arr, 'table_name');
      foreach ($table_name_arr as $key=>$val){
          Db::query('truncate table '.$val);
      }
      apiSuccess('OK');
  }
}

