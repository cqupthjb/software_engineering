<?php
/**
 * Created by PhpStorm.
 * User: yq
 * Date: 2017/9/14 0014
 * Time: 17:53
 */

namespace App\Controller;


use Think\AjaxPage;
use Think\Exception;

class ActivityCardController extends MobileBaseController
{
    //活动票首页？
    public function index(){

    }

    //详情
    public function detail(){
        try{
            $log_id = I('log_id',0);
            if (empty($log_id))
                throw new Exception('缺少活动票参数');
            $map = array();
            $map['log.user_id'] = $this->_uid;
            $map['log.log_id']  = $log_id;
            $alias = 'c';
            $field = 'c.*,log.create_time,u.nickname,level.level_name,level.need_price';
            $join = array('LEFT JOIN __ACTIVITY_CARD_LOG__ as log ON log.card_id = c.card_id',
                'LEFT JOIN __USERS__ as u ON u.user_id = log.user_id',
                'LEFT JOIN __ACTIVITY_CARD_LEVEL__ as level ON level.level_id = c.level_id');
            $card = M('activity_card')->alias($alias)->field($field)->join($join)->where($map)->find();
            if (empty($card))
                throw new Exception('活动票不存在');
            $this->assign('card',$card);
            $this->display();
        }catch(Exception $e){
            $this->error($e->getMessage());
        }
    }

    //我的活动票
    public function my_card(){
        $map                = array();
        $map['log.user_id'] = $this->_uid;
        $card_list = $this->get_card_list($map,true);
        $this->assign('card_list',$card_list);
        if (IS_AJAX){
            $this->display('_my_card');die;
        }
        $this->display();
    }

    //获取有效的活动票 或者活动票首页分页数据 type 代表是否是卡券首页
    protected function get_card_list($map = [],$is_page = false){
        if (empty($map)){
            $map = array();
            $map['c.store_count'] = array('gt',0);  //有库存的
            $map['c.status']      = array('eq',1);  //状态正常的
            $map['c.end_time']    = array('egt',strtotime(date('Y/m/d',time()))); //时间未结束的
        }
        $alias = 'c';
        $join = array('LEFT JOIN __ACTIVITY_CARD_LEVEL__ as level ON level.level_id = c.level_id','LEFT JOIN __ACTIVITY_CARD_LOG__ as log ON log.card_id = c.card_id');
        $field = 'c.* ,level.level_name,level.need_price,count(log.log_id) as user_count,log.log_id,log.create_time';  //注意使用了count后需要使用group分组查询

        //分页信息
        if ($is_page == true){
            $count = M('activity_card')->alias($alias)
                ->join($join)->where($map)->field($field)
                ->group('c.card_id')->count();
            $Page  = new AjaxPage($count,10);
            $show = $Page->show();
            $this->assign('page',$show);// 赋值分页输出
        }

        //联合查询
        M('activity_card')->alias($alias)
            ->join($join)->where($map)->field($field)
            ->group('c.card_id')->order('level.need_price desc');

        //利用thinkphp 联动特性
        if ($is_page == true){ //分页  首页需要
            M('activity_card')->limit($Page->firstRow.','.$Page->listRows);
        }

        $card_list = M('activity_card')->select();
        return $card_list;
    }
}