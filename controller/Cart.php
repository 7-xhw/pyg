<?php

namespace app\home\controller;

use think\Controller;

class Cart extends Base
{
    //加入购物车 表单提交
    public function addcart()
    {
        if (request()->isGet()) {
            //如果是get请求 跳转到首页
            $this->redirect('home/index/index');
        }
        //接受数据
        $params = input();
        //参数检测
        $validate = $this->validate($params, [
            'goods_id' => 'require|integer|gt:0',
            'number' => 'require|integer|gt:0',
            'spec_goods_id' => 'integer|gt:0'
        ]);
        if ($validate !== true) {
            $this->error($validate);
        }
        //处理数据，调用封装好的方法
        \app\home\logic\CartLogic::addCart($params['goods_id'], $params['spec_goods_id'], $params['number']);
        //结果页面显示
        //查询商品相关信息以及sku信息
        $goods = \app\common\model\Goods::getGoodsWithSpec($params['spec_goods_id'], $params['goods_id']);
        return view('addcart', ['goods' => $goods, 'number' => $params['number']]);
    }

    /**
     * 购物车列表
     */
    public function index()
    {
        //查询所有的购物记录
        $list = \app\home\logic\CartLogic::getAllCart();
        //对每一条购物记录 查询商品相关信息
        foreach ($list as &$v) {
            $v['goods'] = \app\common\model\Goods::getGoodsWithSpec($v['spec_goods_id'], $v['goods_id'])->toArray();
        }
        unset($v);
        return view('index', ['list' => $list]);
    }

    public function changenum()
    {
        //接受参数
        $params = input();
        //参数检测
        $validate = $this->validate($params, [
            'id' => 'require',
            'number' => 'require|integer|gt:0'
        ]);
        if ($validate !== true) {
            $res = ['code' => 400, 'msg' => '参数错误'];
            echo json_encode($res);
            die;
        }
        //处理数据
        \app\home\logic\CartLogic::changeNum($params['id'], $params['number']);
        //返回数据
        $res = ['code' => 200, 'msg' => 'success'];
        echo json_encode($res);
        die;
    }

    public function delcart()
    {
        //接受参数
        $params = input();
        //参数检测
        if (!isset($params['id']) || empty($params['id'])) {
            $res = ['code' => 400, 'msg' => '参数错误'];
            echo jsom_encode($res);
            die;
        }
        //处理数据
        \app\home\logic\CartLogic::delCart($params['id']);
        //返回数据
        $res = ['code' => 200, 'msg' => 'success'];
        echo json_encode($res);
        die;
    }

    /**
     *
     */
    public function changestatus()
    {
        //接受参数
        $params = input();
        //参数检测
        $validate = $this->validate($params, [
            'id' => 'require',
            'status' => 'require|in:0,1'
        ]);
        if ($validate !== true) {
            $res=['code'=>400,'msg'=>$validate];
            echo json_encode($res);die;
        }
        //处理数据
        \app\home\logic\CartLogic::changeStatus($params['id'],$params['status']);
        //返回数据
        $res=['code'=>200,'msg'=>'success'];
        echo json_encode($res);die;
    }
}
