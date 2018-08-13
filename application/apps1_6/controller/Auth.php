<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/2/7 0007
 * Time: 11:18
 * info: 单个版本接口全局权限,由于先前的接口在函数在方法内部单独验证，所以下面的接口只要在设置了的就验证
 */

namespace app\apps1_6\controller;
use think\Controller;
use think\Db;
use think\Request;

class Auth extends Common
{
    private $uid;
    private $token;
    private $controller;
    private $action;

    public function _initialize(){
        $this->uid=input('id');
        $this->token=input('token');
        $this->controller=Request::instance()->controller();
        $this->action=Request::instance()->action();
    }

    /**
     * Created by zyjun
     * Info:配置不同接口的权限
     */
    private  function config(){
        $config['Video']=array('getPoiData' =>['login'],'getRegeoData' =>['login'],'publishVideo' =>['login'],'getHotChallenge' =>['login'],
                           'searchChallenge'=>['login']);
        $config['Message']=array('noticeList'=>['login'],'clearMessage'=>['login']);

        return $config;
    }

    /**
     * Created by zyjun
     * Info:处理接口权限
     */
    public   function auth(){
        $config=$this->config();
        #判断控制器是否设置权限
        if(isset($config["$this->controller"])){
            #方法是否设置权限
            if(isset($config["$this->controller"]["$this->action"])){
                #权限不为空
                $actions=$config["$this->controller"]["$this->action"];
                if(!empty($actions)){
                   foreach ($actions as $key=>$val){
                       #登陆验证
                       if($val=='login'){
                            $res=$this->is_login($this->uid);
                            if($res==false){
                                apiError('请先登陆',1000);
                                exit(0);
                            }
                            #如果已经登陆继续验证token是否合法
                            $res=$this->checkToken($this->uid,$this->token);
                            if($res['status']){
                                apiError($res['msg'],1000);
                                exit(0);
                            }
                       }
                       #身份认证
                       if($val=='identify'){
                           $res=$this->isAccess($this->uid);
                           if($res['status']){
                                apiError($res['msg'],1001);
                               exit(0);
                           }
                           #如果已经登陆继续验证token是否合法
                           $res=$this->checkToken($this->uid,$this->token);
                           if($res['status']){
                               apiError($res['msg'],1000);
                               exit(0);
                           }
                       }
                   }
                }
             }
        }
        #不返回表示检测通过
    }

    /**
     * Created by zyjun
     * Info:验证用户token
     * 发生身份信息验证错误，返回1000错误码，让客户端重新登陆
     */
    public function checkToken($id,$token){
        $login_error_code=1000;
        if(empty($id)){
            $data['msg']='error:用户ID异常';
            $data['status']=1;
            $data['code']=$login_error_code;
            return $data;
        }
        if(empty($token)){
            $data['msg']='error:用户Token异常';
            $data['status']=1;
            $data['code']=$login_error_code;
            return $data;
        }
        if($this->checkInt($id,'','')){
            $data['msg']='用户ID非法';
            $data['status']=1;
            $data['code']=$login_error_code;
            return $data;
        }
        if($this->checkUserToken($token)){
            $data['msg']='用户TOKEN非法';
            $data['status']=1;
            $data['code']=$login_error_code;
            return $data;
        }
        $user_token=Db::name('user')->where('id',$id)->value('token');
        if(empty($user_token)){
            $data['msg']='用户信息异常';
            $data['status']=1;
            $data['code']=$login_error_code;
            return $data;
        }

        if($user_token!=$token){
            $data['msg']='用户身份验证失败！';
            $data['status']=1;
            $data['code']=$login_error_code;
            return $data;
        }
        #临时添加检测是否登录
        $is_login=Db::name('user')->where('id',$id)->value('is_login');
        if(empty($is_login)){
            $data['msg']='请先登录！';
            $data['status']=1;
            $data['code']=$login_error_code;
            return $data;
        }
        $data['msg']='用户身份验证成功！';
        $data['status']=0;
        return $data;
    }

}