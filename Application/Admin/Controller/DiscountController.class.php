<?php
/**
 * Created by PhpStorm.
 * User: yq
 * Date: 2017/7/14
 * Time: 9:59
 */

namespace Admin\Controller;


use Think\Exception;

class DiscountController extends BaseController
{
    //折扣列表
    public function index(){
        $list = M('discount')->order('discount_id asc')->select();
        $this->assign('list',$list);
        $this->display();
    }

    //折扣 添加 | 修改
    public function info(){
        $discount_id = I('get.discount_id');
        if($discount_id){
            $row = M('discount')->where(array('discount_id'=>$discount_id))->find();
            $this->assign('row',$row);
        }
        $this->display();
    }

    //存取折扣信息
    public function discountSave(){
        $model = 'discount';
        try{
            $data = $this->check_discount();
            if(empty($data['discount_id'])){
                if ( get_count('discount',array('name'=>$data['name'])) )
                    throw new Exception('改折扣已存在');
                $r = M($model)->add($data);
            }else{
                $r = M($model)->where(array('discount_id'=>$data['discount_id']))->save($data);
            }
            if($r !== false){
                $this->success("操作成功!",U('Admin/Discount/index'));
            }else{
                $this->success("操作失败!",U('Admin/Discount/info',array('discount_id'=>$data['discount_id'])));
            }
        }catch(Exception $e){
            $this->error($e->getMessage());
        }
    }

    //验证数据
    protected function check_discount(){
        $discount = I('post.');
        if (empty($discount))
            throw new Exception('请填写折扣数据');
        if (empty($discount['name']))
            throw new Exception('请填写折扣名称');
        if ($discount['platform_rate'] < 0)
            throw new Exception('平台返现比例请在0-100之间,百分比');
        if ($discount['province_rate'] < 0)
            throw new Exception('省级返现比例请在0-100之间,百分比');
        if ($discount['city_rate'] < 0)
            throw new Exception('地级返现比例请在0-100之间,百分比');
        if ($discount['county_rate'] < 0)
            throw new Exception('县级返现比例请在0-100之间,百分比');
        if ($discount['distributor_rate'] < 0)
            throw new Exception('经销商返现比例请在0-100之间,百分比');
        if ($discount['user_rate'] < 0)
            throw new Exception('用户返现比例请在0-100之间,百分比');

        return $discount;
    }

    //删除
    public function remove(){
        $discount_id = I('post.discount_id');
        $store = M('store')->where(array('discount_id'=>$discount_id))->find();
        if($store){
            exit(json_encode("该折扣还有店铺在使用"));
        }else{
            $d = $store = M('discount')->where(array('discount_id'=>$discount_id))->delete();
            if($d!==false){
                exit(json_encode(1));
            }else{
                exit(json_encode("删除失败"));
            }
        }
    }

    //修改字段
    public function change_column(){
        try{
            $discount_id = intval(I('discount_id'));
            $rate = intval(I('rate'));
            $column = I('column');
            if ($rate < 0 || $rate >100) throw new Exception('比例在0-100之间');
            $res = M('discount')->where(array('discount_id'=>$discount_id))->setField(array($column=>$rate));
            if ($res === false){
                throw new Exception('修改失败');
            }
            $this->ajaxReturn(array('status'=>1,'msg'=>'修改成功'),'json');
        }catch(Exception $e){
            $this->ajaxReturn(array('status'=>0,'msg'=>$e->getMessage()),'json');
        }
    }

}