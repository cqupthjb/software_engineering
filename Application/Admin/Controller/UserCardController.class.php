<?php
/**
 * Created by PhpStorm.
 * User: yq
 * Date: 2017/10/11 0011
 * Time: 9:20
 */

namespace Admin\Controller;


use Think\AjaxPage;
use Think\Exception;
use Think\Page;

class UserCardController extends BaseController
{
    #region 会员卡管理
    //会员卡列表
    public function card_index(){
        C('TOKEN_ON',false);
        try{
            $status = I('status',0);
            $row = I('row',10);
            $map = array();
            $map['c.status'] =  array('eq',$status) ;
            $level_id = I('level_id',0);
            if (!empty($level_id)){
                $map['c.level_id'] = $level_id;
            }
            //时间筛选
            //$timegap = I('timegap','');
            $_begin = I('begin','');
            $_end = I('end','');
            if (!empty($_begin) && !empty($_end)){
                //$gap = explode('-', urldecode($timegap));
                //$begin = strtotime($gap[0]);
                //$end = strtotime($gap[1]);
                $begin = strtotime($_begin);
                $end = strtotime($_end);
                if ($status == 1){
                    $map['use_time'] = array('between', "$begin,$end");
                }else{
                    $map['create_time'] = array('between', "$begin,$end");
                }
                $begin = $_begin;
                $end = $_end;
            }else{
                $begin = date('Y-m-d', (time() - 30 * 60 * 60 * 24));//30天前
                $end = date('Y-m-d', strtotime('+1 days'));
            }

            $card_list = $this->get_card_list($map,true,$row);
            $level_list = M('user_card_level')->select();
            $this->assign('level_list',$level_list);
            $this->assign('card_list',$card_list);
            $this->assign('status',$status);// 赋值分页输出
            //$this->assign('timegap', $begin . '-' . $end);
            $this->assign('begin',$begin);
            $this->assign('end',$end);
            $this->assign('level_id',$level_id ? $level_id : 0);
            $this->assign('row',$row);
            $this->display();
        }catch(Exception $e){
            $this->error($e->getMessage());
        }
    }
    //获取会员卡首页分页数据 is_page 代表是否分页
    protected function get_card_list($map = [],$is_page = true,$row = 10){
        if (empty($map)){
            $map = array();
            $map['c.status'] = 0;  //状态正常的
        }
        $alias = 'c';
        $join = array('JOIN __USER_CARD_LEVEL__ as level ON level.level_id = c.level_id');
        $field = 'c.* ,level.level_name,level.money';  //注意使用了count后需要使用group分组查询

        if ($row > 5000){
            ini_set('max_execution_time', '0');//解除时间限制
        }

        //分页信息
        if ($is_page == true){
            $count = M('user_card')->alias($alias)
                ->join($join)->where($map)->field($field)
                ->count();
            $Page  = new Page($count,$row);
            $show = $Page->show();
            $this->assign('page',$show);// 赋值分页输出
        }

        //联合查询
        M('user_card')->alias($alias)
            ->join($join)->where($map)->field($field)
            ->order('c.card_id desc');

        //利用thinkphp 联动特性
        if ($is_page == true){ //分页  首页需要
            M('user_card')->limit($Page->firstRow.','.$Page->listRows);
        }
        $card_list = M('user_card')->select();

        if ($row > 5000){
            ini_set('max_execution_time', 30);//恢复时间限制
        }
        return $card_list;
    }

    //添加 | 修改会员卡
    public function add_card(){
        $card_id = I('card_id',0);
        if (!empty($card_id)){
            $card = M('user_card')->where(array('card_id'=>$card_id))->find();
            if (empty($card))
                $this->error('会员卡不存在');
            $this->assign('row',$card);
        }
        $this->initEditor();
        //卡券等级
        $card_level = M('user_card_level')->order('level_id asc')->select();
        $this->assign('card_level',$card_level);
        $this->initEditor();
        $this->display('add_card');
    }

    /**
     * 获取8位数字密码
     * @param $base_num     基数
     * @param int $time     生成次数
     * @param int $bit      生成位数
     * @param bool $is_count  是否验证
     * @return bool|string  密码
     * @throws Exception
     */
    protected function _card_pwd($base_num,$bit=12,$time=1,$is_count = false){
        if ($time<1)
            throw new Exception('密码生成重复率过高,请更换规则');
        $num = uninum($base_num,$bit);
        if ($is_count && get_count('user_card',array('card_pwd'=>$num)) > 0){
            $num = $this->_card_pwd($base_num,$bit,--$time);
        }
        return $num;
    }
    /**
     * 获取8位数字卡号
     * @param $base_num     基数
     * @param int $time     生成次数
     * @param int $bit      生成位数
     * @param int $level_id 面值id判断用
     * @param bool $is_count  是否验证
     * @return bool|string  密码
     * @throws Exception
     */
    protected function _card_no($base_num,$bit=8,$time=1,$level_id=0,$is_count = false){
        if ($time<1)
            throw new Exception('卡号生成重复率过高,请更换规则');
        $num = build_card_no($base_num,$bit);
        if ($is_count && get_count('user_card',array('card_no'=>$num,'level_id'=>$level_id)) > 0){
            $num = $this->_card_no($base_num,$bit,--$time,$level_id);
        }
        return $num;
    }
    //添加 | 修改
    public function save_card(){
        try{
            $data = $this->_check_card();
            if (empty($data['card_id'])){
                $cards = array();
                //获取当前最大卡号
                $old_max_no = M('user_card')->where(array('level_id'=>$data['level_id']))->order('card_id desc')->getField('card_no');
                $old_max_id = M('user_card')->where(array('level_id'=>$data['level_id']))->order('card_id desc')->getField('card_id');
                if (empty($old_max_no)){
                    $now_max_no = 1;
                }else{
                    $now_max_no = intval($old_max_no) + 1;
                }
                if (empty($old_max_id)){
                    $now_max_id = 1;
                }else{
                    $now_max_id = intval($old_max_id) + 1;
                }
                //region 循环制卡
                set_time_limit(0);
                ini_set("error_reporting","E_ALL & ~E_NOTICE");//错误显示
                ini_set('max_execution_time', '0');//解除时间限制
                for ($i = 0; $i < $data['num'];$i++){
                    $card = array(
                        'level_id'      => $data['level_id'],        //面值
                        'card_no'       => $this->_card_no($now_max_no),//卡号
                        'card_pwd'      => $this->_card_pwd($now_max_id,12),//密码  12 位数值
                        'status'        => 0,//添加时为未激活状态
                        'create_time'   => time(),//创建时间
                        'description'   => $data['description'] ? $data['description'] : '',//描述
                        'card_token'    => rand(0,9).uniqid(rand(0,9)).rand(10,99),//备用字段
                    );
                    //添加会员卡  单条添加  为防止密码重复
                    //$res = M('user_card')->add($card);
                    //if ($res === false)
                    //    throw new Exception('添加失败');
                    ++$now_max_no;
                    ++$now_max_id;
                    $cards[] = $card;
                }
                $res = M('user_card')->addAll($cards);
                if ($res === false)
                    throw new Exception('添加失败');
                ini_set('max_execution_time', 30);//恢复时间限制
                //endregion
            }else{
                if (!empty($data['description'])){
                    $res = M('user_card')->where(array('card_id'=>$data['card_id']))->save(array('description'=>$data['description']));
                    if ($res === false)
                        throw new Exception('操作失败');
                }
            }
            $this->ajaxReturn(array('status'=>1,'msg'=>'操作成功'));
        }catch(Exception $e){
            //$this->error($e->getMessage());
            $this->ajaxReturn(array('status'=>0,'msg'=>$e->getMessage()));
        }
    }

    //检测数据
    protected function _check_card(){
        $data = I('post.');
        if (empty($data))
            throw new Exception('请填写会员卡信息');
        if (!isset($data['card_id'])){
            if (empty($data['level_id']))
                throw new Exception('请选择会员卡面值');
            //如果添加状态 & 未指定生成卡券数量 默认为:1
            if (empty($data['num']) || $data['num']<=0)
                $data['num'] = 1;
        }

        //region 备用
        //if (empty($data['name']))
        //    throw new Exception('请填写会员卡名称');
        //if (empty($data['cover']))
        //    throw new Exception('请上会员卡传图片');
        //if (empty($data['start_time']) || empty($data['end_time']))
        //    throw new Exception('请选择领取会员卡时间');
        //if ($data['end_time'] < $data['start_time'])
        //    throw new Exception('结束时间请大于开始时间');
        //if ($data['start_time'] < time() || $data['end_time'] < time())
        //    throw new Exception('领取时间请大于当前时间');
        //$data['start_time'] = strtotime($data['start_time']);
        //$data['end_time'] = strtotime($data['end_time']);
        //endregion
        return $data;
    }

    #endregion

    #region 会员卡面值|等级管理
    //会员卡面值|面值首页
    public function level_index(){
        try{
            $map = array();
            $model = M('user_card_level');
            $count = $model->where($map)->count();
            $Page  = new Page($count,20);
            $show = $Page->show();
            $order_str = 'level_id desc';
            $level_list= $model->where($map)->order($order_str)->limit($Page->firstRow.','.$Page->listRows)->select();
            $this->assign('list',$level_list);
            $this->assign('page',$show);// 赋值分页输出
            $this->display();
        }catch(Exception $e){
            $this->error($e->getMessage());
        }
    }
    //添加面值
    public function add_level(){
        $level_id = I('level_id',0);
        if (!empty($level_id)){
            $level = M('user_card_level')->where(array('level_id'=>$level_id))->find();
            if (empty($level))
                $this->error('面值不存在');
            $this->assign('row',$level);
        }
        $this->display('add_level');
    }
    //修改面值
    public function save_level(){
        try{
            $data = $this->_check_level();
            if (empty($data['level_id'])){
                $res = M('user_card_level')->add($data);
            }else{
                $res = M('user_card_level')->save($data);
            }
            if ($res === false)
                throw new Exception('操作失败');
            $this->ajaxReturn(array('status'=>1,'msg'=>'操作成功'));
        }catch(Exception $e){
            $this->ajaxReturn(array('status'=>0,'msg'=>$e->getMessage()));
        }
    }
    //检测数据
    public function _check_level(){
        $data = I('post.');
        if (empty($data))
            throw new Exception('请填写会员卡面值信息');
        if (empty($data['level_name']))
            throw new Exception('请填写面值名称');
        if (empty($data['money']) || $data['money'] <= 0)
            throw new Exception('请仔细填写面值金额');
        return $data;
    }
    //删除面值
    public function remove_level(){
        try{
            $level_id = I('level_id',0);
            if (empty($level_id))
                throw new Exception('缺少面值参数');
            $count = get_count('user_card',array('level_id'=>$level_id));
            if ($count > 0)
                throw new Exception('该面值存在会员卡信息，可切换会员卡面值再删除');
            $res = M('user_card_level')->where(array('level_id'=>$level_id))->delete();
            if ($res === false)
                throw new Exception('删除失败');
            $this->ajaxReturn(array('status'=>1,'msg'=>'删除成功'));
        }catch(Exception $e){
            $this->ajaxReturn(array('status'=>0,'msg'=>$e->getMessage()));
        }
    }
    #endregion

    #region 会员卡激活信息
    public function log_index(){
        C('TOKEN_ON',false);
        try{
            $row = I('row',10);
            $map = array();
            $level_id = I('level_id',0);
            if (!empty($level_id)){
                $map['c.level_id'] = $level_id;
            }
            //时间筛选
            //$timegap = I('timegap','');
//            $_begin = I('begin','');
//            $_end = I('end','');
//            if (!empty($_begin) && !empty($_end)){
//                $begin = strtotime($_begin);
//                $end = strtotime($_end);
//                $map['log.create_time'] = array('between', "$begin,$end");
//                $begin = $_begin;
//                $end = $_end;
//            }else{
//                $begin = date('Y-m-d', (time() - 30 * 60 * 60 * 24));//30天前
//                $end = date('Y-m-d', strtotime('+1 days'));
//            }

            $card_list = $this->get_log_list($map,true,$row);

            $level_list = M('user_card_level')->select();
            $this->assign('level_list',$level_list);
            $this->assign('card_list',$card_list);
//            $this->assign('begin',$begin);
//            $this->assign('end',$end);
            $this->assign('level_id',$level_id ? $level_id : 0);
            $this->assign('row',$row);
            $this->display();
        }catch(Exception $e){
            $this->error($e->getMessage());
        }
    }
    //获取会员卡首页分页数据 is_page 代表是否分页
    protected function get_log_list($map = [],$is_page = true,$row = 10){
        $alias = 'log';
        $join = array('JOIN __USER_CARD__ as c ON c.card_id = log.card_id','LEFT JOIN __USER_CARD_LEVEL__ as level on level.level_id = c.level_id ');
        $field = 'log.*,level.level_name,level.money';  //注意使用了count后需要使用group分组查询

        if ($row > 5000){
            ini_set('max_execution_time', '0');//解除时间限制
        }

        //分页信息
        if ($is_page == true){
            $count = M('user_card_log')->alias($alias)
                ->join($join)->where($map)->field($field)
                ->count();
            $Page  = new Page($count,$row);
            $show = $Page->show();
            $this->assign('page',$show);// 赋值分页输出
        }

        //联合查询
        M('user_card_log')->alias($alias)
            ->join($join)->where($map)->field($field)
            ->order('log.log_id desc');

        //利用thinkphp 联动特性
        if ($is_page == true){ //分页  首页需要
            M('user_card_log')->limit($Page->firstRow.','.$Page->listRows);
        }
        $card_list = M('user_card_log')->select();

        if ($row > 5000){
            ini_set('max_execution_time', 30);//恢复时间限制
        }
        return $card_list;
    }
    #endregion

    #region 辅助操作
    public function status_name($status){
        $res = '';
        switch ($status){
            case '0' : $res ='未激活';break;
            case '1' : $res ='已激活';break;
            case '-1' :$res ='已废弃';break;
        }
        return $res;
    }

    //记录导出//Excel导出
    public function export()
    {
        //region 条件组合
        $_begin = I('begin','');
        $_end = I('end','');
        $status = I('status',0);
        $ids = I('ids','');
        $level_id = I('level_id',0);
        $map = array();
        $map['c.status'] =  $status ;
        if (!empty($level_id)){
            $map['c.level_id'] = $level_id;
        }
        if (!empty($ids)){
            if (strstr($ids,'-') !== false){
                $ids = str2arr($ids,'-');
                $map['c.card_id'] = array('between',array($ids[0],$ids[1]));
            }else{
                $map['c.card_id'] = array('in',$ids);
            }
        }
        if (!empty($_begin) && !empty($_end)){
            $begin = strtotime($_begin);
            $end = strtotime($_end);
            if ($status == 1){
                $map['use_time'] = array('between', "$begin,$end");
            }else{
                $map['create_time'] = array('between', "$begin,$end");
            }
        }
        //endregion
        $log_list = $this->get_card_list($map,false);

        $strTable ='<table width="500" border="1">';
        $strTable .= '<tr>';
        $strTable .= '<th style="text-align:center;font-size:14px;" >编号</th>';
        $strTable .= '<th style="text-align:center;font-size:14px;" >会员卡</th>';
        $strTable .= '<th style="text-align:center;font-size:14px;" >面值</th>';
        $strTable .= '<th style="text-align:center;font-size:14px;" >卡号</th>';
        $strTable .= '<th style="text-align:center;font-size:14px;" >卡密</th>';
        $strTable .= '<th style="text-align:center;font-size:14px;" >状态</th>';
        if ($status == 1){
            $strTable .= '<th style="text-align:center;font-size:14px;" >用户编号</th>';
            $strTable .= '<th style="text-align:center;font-size:14px;" >激活时间</th>';
        }
        $strTable .= '<th style="text-align:center;font-size:14px;" >创建时间</th>';
        $strTable .= '</tr>';

        foreach($log_list as $k=>$val){
            $strTable .= '<tr>';
            $strTable .= '<td style="text-align:center;font-size:14px;">&nbsp;'.$val['card_id'].'</td>';
            $strTable .= '<td style="text-align:center;font-size:14px;">&nbsp;'.$val['level_name'].'</td>';
            $strTable .= '<td style="text-align:center;font-size:14px;">&nbsp;'.$val['money'].'</td>';
            $strTable .= '<td style="text-align:center;font-size:14px;">&nbsp;'.$val['card_no'].'</td>';
            $strTable .= '<td style="text-align:center;font-size:14px;">&nbsp;'.$val['card_pwd'].'</td>';
            $strTable .= '<td style="text-align:center;font-size:14px;">&nbsp;'.$this->status_name($val['status']) .'</td>';
            if ($status == 1){
                $strTable .= '<td style="text-align:center;font-size:14px;">&nbsp;'.$val['user_id'].'</td>';
                $strTable .= '<td style="text-align:center;font-size:14px;">&nbsp;'.date('Y/m/d H:i:s',$val['use_time']).'</td>';
            }
            $strTable .= '<td style="text-align:center;font-size:14px;">&nbsp;'.date('Y/m/d H:i:s',$val['create_time']).'</td>';
            $strTable .= '</tr>';
        }
        $strTable .='</table>';
        downloadExcel($strTable,'user_card');
        exit();
    }

    //记录导出//Excel导出
    public function export_log()
    {
        //region 条件组合

        $ids = I('ids','');
        $level_id = I('level_id',0);
        $map = array();

        if (!empty($level_id)){
            $map['c.level_id'] = $level_id;
        }
        if (!empty($ids)){
            if (strstr($ids,'-') !== false){
                $ids = str2arr($ids,'-');
                $map['c.card_id'] = array('between',array($ids[0],$ids[1]));
            }else{
                $map['c.card_id'] = array('in',$ids);
            }
        }

        //endregion
        $log_list = $this->get_log_list($map,false);

        $strTable ='<table width="500" border="1">';
        $strTable .= '<tr>';
        $strTable .= '<th style="text-align:center;font-size:14px;" >编号</th>';
        $strTable .= '<th style="text-align:center;font-size:14px;" >会员卡</th>';
        $strTable .= '<th style="text-align:center;font-size:14px;" >面值</th>';
        $strTable .= '<th style="text-align:center;font-size:14px;" >卡号</th>';
        $strTable .= '<th style="text-align:center;font-size:14px;" >卡密</th>';
        $strTable .= '<th style="text-align:center;font-size:14px;" >用户编号</th>';
        $strTable .= '<th style="text-align:center;font-size:14px;" >激活时间</th>';
        $strTable .= '</tr>';

        foreach($log_list as $k=>$val){
            $strTable .= '<tr>';
            $strTable .= '<td style="text-align:center;font-size:14px;">&nbsp;'.$val['card_id'].'</td>';
            $strTable .= '<td style="text-align:center;font-size:14px;">&nbsp;'.$val['level_name'].'</td>';
            $strTable .= '<td style="text-align:center;font-size:14px;">&nbsp;'.$val['money'].'</td>';
            $strTable .= '<td style="text-align:center;font-size:14px;">&nbsp;'.$val['card_no'].'</td>';
            $strTable .= '<td style="text-align:center;font-size:14px;">&nbsp;'.$val['card_pwd'].'</td>';
            $strTable .= '<td style="text-align:center;font-size:14px;">&nbsp;'.$val['user_id'].'</td>';
            $strTable .= '<td style="text-align:center;font-size:14px;">&nbsp;'.date('Y/m/d H:i:s',$val['create_time']).'</td>';
            $strTable .= '</tr>';
        }
        $strTable .='</table>';
        downloadExcel($strTable,'user_card_log');
        exit();
    }


    //批量删除
    public function remove_by_ids(){
        $ids = I('ids','');
        try{
            if (empty($ids))
                throw new Exception('未选中任何数据');
            //region 过滤已激活的卡
            $map = array();
            $map['card_id'] = array('in',$ids);
            ini_set('max_execution_time', '0');//解除时间限制
            //查找激活过的卡号
            $_ids = M('user_card_log')->where($map)->getField('card_id',true);
            if (!empty($_ids)){
                $ids = str2arr($ids,',');//字符串转数组
                $ids = array_diff($ids,$_ids); //获取差集（只第一个数组出现的数据）
            }
            $map['card_id'] = array('in',$ids);
            $res = M('user_card')->where($map)->delete();
            if ($res === false)
                throw new Exception('操作失败');
            //endregion
            $res = array('status'=>1,'msg'=>'操作成功');
        }catch(Exception $e){
            $res = array('status'=>0,'msg'=>$e->getMessage());
        }
        ini_set('max_execution_time', 30);//恢复时间限制
        $this->ajaxReturn($res);
    }

    //单个删除
    public function remove(){
        $card_id = I('id');
        $map = array('card_id'=>$card_id);
        try{
            $status = M('user_card')->where($map)->getField('status');
            if ($status == 0) //为0 假删除
                $res = M('user_card')->where($map)->setField('status',-1);
            else if ($status == -1){//为-1 真删除
                if (get_count('user_card_log',$map)>0)
                    throw new Exception('存在激活记录，无法删除该卡');
                $res = M('user_card')->where($map)->delete();
            }else{
                $res = true;
            }
            if ($res === false)
                throw new Exception('操作失败');
            $res =array('status'=>1,'msg'=>'操作成功');
        }catch (Exception $e){
            $res = array('status'=>0,'msg'=>$e->getMessage());
        }
       $this->ajaxReturn($res);
    }

    //更改字段状态
    public function change_column(){
        $card_id = I('id');
        $map = array('card_id'=>$card_id);
        $value = I('value');
        try{
            //$status = M('card')->where($map)->getField('status');
            //if($value==0 && $status==0) //为0 真删除
            //    $res = M('card')->where($map)->delete();
            // else
            $res = M('user_card')->where($map)->setField('status',$value);
            if ($res === false)
                throw new Exception('操作失败');
            $res = array('status'=>1,'msg'=>'操作成功');
        }catch(Exception $e){
            $res = array('status'=>0,'msg'=>$e->getMessage());
        }
        $this->ajaxReturn($res);
    }

    /**
     * 初始化编辑器链接
     */
    private function initEditor()
    {
        $this->assign("URL_upload", U('Admin/Ueditor/imageUp',array('savepath'=>'user_card')));
        $this->assign("URL_fileUp", U('Admin/Ueditor/fileUp',array('savepath'=>'user_card')));
        $this->assign("URL_scrawlUp", U('Admin/Ueditor/scrawlUp',array('savepath'=>'user_card')));
        $this->assign("URL_getRemoteImage", U('Admin/Ueditor/getRemoteImage',array('savepath'=>'user_card')));
        $this->assign("URL_imageManager", U('Admin/Ueditor/imageManager',array('savepath'=>'user_card')));
        $this->assign("URL_imageUp", U('Admin/Ueditor/imageUp',array('savepath'=>'user_card')));
        $this->assign("URL_getMovie", U('Admin/Ueditor/getMovie',array('savepath'=>'user_card')));
        $this->assign("URL_Home", "");
    }
    #endregion

}