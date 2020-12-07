<?php

namespace app\home\controller;

use think\Controller;

class Login extends Controller
{
    /**
     *显示页面
     */
    public function login()
    {
        $this->view->engine->layout(false);
        return view();
    }

    /**
     * 注册页面展示
     */
    public function register()
    {
        $this->view->engine->layout(false);
        return view();
    }

    /**
     * 手机号注册
     */
    public function phone()
    {
        //接受数据
        $params = input();
        //参数检测
        $validate = $this->validate($params, [
            'phone|手机号' => 'require|regex:1[3-9]\d{9}|unique:user,phone',
            'code|验证码' => 'require|length:4',
            'password|密码' => 'require|length:6,20|confirm:repassword'
        ]);
        if ($validate !== true) {
            $this->error($validate);
        }
        //验证码效验
        $code = cache('register_code_' . $params['phone']);
        if ($code != $params['code']) {
            $this->error('验证码错误');
        }
        //验证码成功一次失效
        cache('register_code_' . $params['phone'], null);
        //注册用户（添加操作）
        //密码加密
        $params['password'] = encrypt_password($params['password']);
        $params['username'] = $params['phone'];
        $params['nickname']=encrypt_phone($params['phone']);
        \app\common\model\User::create($params, true);
        $this->redirect('home/login/login');
    }

    /**
     *
     */
    public function sendcode()
    {
        //接受参数
        $params = input();
        //参数检测
        $validate = $this->validate($params, [
            'phone' => 'require|regex:1[3-9]\d{9}'
        ]);
        if ($validate !== true) {
            //验证失败
            $res = [
                'code' => 400,
                'msg' => '参数错误'
            ];
            echo json_encode($res);
            die;
        }
        //同一个手机号 一分钟只能发一次
        $last_time = cache('register_time_' . $params['phone']);
        if (time() - $last_time < 60) {
            $res = [
                'code' => 500,
                'msg' => '发送太频繁'
            ];
            echo json_encode($res);
            die;
        }
        //发送验证码（生成验证码、生成短信内容、发短信）
        $code = mt_rand(1000, 9999);
        $content = "【创信】你的验证码是：{$code},3分钟内有效";
        //$result=sendmsg($params['phone'],$content);
        //开发测试时，不用真正发短信
        $result = true;
        //返回结果
        if ($result == true) {
            //发送成功，将验证码储存到缓存，用于后续效验
            cache('register_code_' . $params['phone'], $code, 180);
            $res = [
                'code' => 200,
                'msg' => '短信发送成功',
                'data' => $code
            ];
            echo json_encode($res);
            die;

        }
    }

    /**
     * 登录表单提交
     */
    public function dologin()
    {
        //接受参数
        $params = input();
        //参数检测
        $validate = $this->validate($params, [
            'username' => 'require',
            'password' => 'require|length:6,20'
        ]);
        if ($validate !== true) {
            $this->error($validate);
        }
        $password = encrypt_password($params['password']);
        //查询用户表
        $info = \app\common\model\User::where('phone', $params['username'])->whereOr('email', $params['username'])->find();
        if ($info && $info['password'] == $password) {
            //设置登录标识
            session('user_info', $info->toArray());
            //迁移cookie购物车数据到数据表
            \app\home\logic\CartLogic::cookieToDb();
            //页面跳转
            //从session取跳转地址
            $back_url=session('back_url')?:'home/index/index';
            $this->redirect($back_url);
        } else {
            $this->error('用户名或密码错误');
        }
    }

    /**
     * 退出
     */
    public function logout()
    {
        //清空session
        session(null);
        //页面跳转
        $this->redirect('home/login/login');
    }
    /**
     * qq
     */
    public function qqcallback(){
        require_once ("./plugins/qq/API/qqConnectAPI.php");
        $qc=new \QC();
        $access_token=$qc->qq_callback();
        $openid=$qc->get_openid();
        //获取用户信息
        $qc=new \QC($access_token,$openid);
        $info=$qc->get_user_info();
        //自动注册登录
        $user=\app\common\model\User::where('open_type','qq')->where('openid',$openid)->find();
        if($user){
            //非第一次登录 同步昵称
            $user->nickname=$info['nickname'];
            $user->save();
        }else{
            //第一次登录 创建新用户
            \app\common\model\User::create(['open_type'=>'qq','openid'=>$openid,'nickname'=>$info['nickname']]);
        }
        //设置登录标识
        $user=\app\common\model\User::where('open_type','qq')->where('openid',$openid)->find();
        session('user_info',$user->toArray());
        //迁移cookie购物车数据到数据表
        \app\home\logic\CartLogic::cookieToDb();
        //页面跳转
        //从session取跳转地址
        $back_url=session('back_url')?:'home/index/index';
        $this->redirect($back_url);
        $this->redirect('home/index/index');
    }

}
