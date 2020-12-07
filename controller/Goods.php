<?php

namespace app\home\controller;

use think\Controller;

class Goods extends Base
{
    //分类下的商品列表
    public function index($id = 0)
    {
        //接收参数
        $keywords = input('keywords');
        if (empty($keywords)) {
            //获取指定分类下商品列表
            if (!preg_match('/^\d+$/', $id)) {
                $this->error('参数错误');
            }
            //查询分类下的商品
            $list = \app\common\model\Goods::where('cate_id', $id)->order('id desc')->paginate(10);
            //查询分类名称
            $category_info = \app\common\model\Category::find($id);
            $cate_name = $category_info['cate_name'];
        } else {
            try {
                //从ES中搜索
                $list = \app\home\logic\GoodsLogic::search();
                $cate_name = $keywords;
            } catch (\Exception $e) {
                $this->error('服务器异常');
            }
        }
        return view('index', ['list' => $list, 'cate_name' => $cate_name]);
    }

    /**
     * 商品详情页
     */
    public function detail($id)
    {
        //$id 是商品id
        //查询商品信息、商品相册、规格商品sku
        $goods = \app\common\model\Goods::with('goods_images,spec_goods')->find($id);
        //将商品的第一个规格商品的信息，替换到$goods中
        if (!empty($goods['spec_goods'])) {
            if ($goods['spec_goods'][0]['price'] > 0) {
                $goods['goods_price'] = $goods['spec_goods'][0]['price'];
            }
            if ($goods['spec_goods'][0]['cost_price'] > 0) {
                $goods['cost_price'] = $goods['spec_goods'][0]['cost_price'];
            }
            if ($goods['spec_goods'][0]['store_count'] > 0) {
                $goods['store_count'] = $goods['spec_goods'][0]['store_count'];
            } else {
                $goods['store_count'] = 0;
            }
        }
        //转化商品属性为json为数组
        $goods['goods_attr'] = json_decode($goods['goods_attr'], true);
        //取出所有相关的规格值id
        $value_ids = array_unique(explode('_', implode('_', array_column($goods['spec_goods'], 'value_ids'))));
        //查询spec_value表
        $spec_values = \app\common\model\SpecValue::with('spec')->where('id', 'in', $value_ids)->select();
        //对数组结构进行转化
        $res = [];
        foreach ($spec_values as $v) {
            $res[$v['spec_id']] = [
                'spec_id' => $v['spec_id'],
                'spec_name' => $v['spec_name'],
                'spec_values' => []
            ];
        }
        foreach ($spec_values as $v) {
            $res[$v['spec_id']]['spec_values'][] = $v;
        }
        $value_ids_map = [];
        foreach ($goods['spec_goods'] as $v) {
            $row = [
                'id' => $v['id'],
                'price' => $v['price']
            ];
            $value_ids_map[$v['value_ids']] = $row;
        }
        //数据最终在js中使用 ，转化为json格式，用于输出到js中
        $value_ids_map = json_encode($value_ids_map);
        return view('detail', ['goods' => $goods, 'specs' => $res, 'value_ids_map' => $value_ids_map]);
    }

    protected static function init()
    {
        //实例化ES工具类
        $es = new \tools\es\MyElasticsearch();
        //设置新增回调
        self::afterInsert(function($goods)use($es){
            //添加文档
            $doc = $goods->visible(['id', 'goods_name', 'goods_desc', 'goods_price'])->toArray();
            $doc['cate_name'] = $goods->category->cate_name;
            $es->add_doc($goods->id, $doc, 'goods_index', 'goods_type');
        });
        //设置更新回调
        self::afterUpdate(function($goods)use($es){
            //修改文档
            $doc = $goods->visible(['id', 'goods_name', 'goods_desc', 'goods_price', 'cate_name'])->toArray();
            $doc['cate_name'] = $goods->category->cate_name;
            $body = ['doc' => $doc];
            $es->update_doc($goods->id, 'goods_index', 'goods_type', $body);
        });
        //设置删除回调
        self::afterDelete(function($goods)use($es){
            //删除文档
            $es->delete_doc($goods->id, 'goods_index', 'goods_type');
        });
    }
}
