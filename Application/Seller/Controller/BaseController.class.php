<?php

/**
 * tpshop
 * ============================================================================
 * 版权所有 2015-2027 深圳搜豹网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.tp-shop.cn
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * Author: 当燃
 * Date: 2016-06-09
 */

namespace Seller\Controller;
use Think\Controller;
use Admin\Logic\UpgradeLogic;
class BaseController extends Controller {

    //成员属性  登录角色信息
    public $_seller;    //管理员信息
    public $_seller_id; //管理员id
    public $_store_id;  //店铺id
    /**
     * 析构函数
     */
    function __construct() 
    {
        parent::__construct();
        $upgradeLogic = new UpgradeLogic();
        $upgradeMsg = $upgradeLogic->checkVersion(); //升级包消息        
        $this->assign('upgradeMsg',$upgradeMsg);    
        //用户中心面包屑导航
        $navigate_admin = navigate_admin();
        $this->assign('navigate_admin',$navigate_admin);
        tpversion();        
   }    
    
    /*
     * 初始化操作
     */
    public function _initialize() 
    {
        $this->assign('action',ACTION_NAME);
        //过滤不需要登陆的行为
        if(in_array(ACTION_NAME,array('login','logout','vertify','register')) || in_array(CONTROLLER_NAME,array('Ueditor','Uploadify','Newjoin'))){
        	//return;
        }else{
        	if(session('seller_id') > 0 ){
        	    #region 成员属性
        	    $this->_seller_id = session('seller_id');//赋值给成员属性方便调用
        	    $this->_seller    = session('seller');
        	    $this->_store_id   = session('store_id');
        	    $this->assign('_seller_id',$this->_seller_id);
        	    $this->assign('_seller',$this->_seller);
        	    $this->assign('_store_id',$this->_store_id);
        	    #endregion
                define('STORE_ID',session('store_id')); //将当前的session_id保存为常量，供其它方法调用
        		$this->check_priv();//检查管理员菜单操作权限
        	}else{
        		$this->error('请先登陆',U('Admin/login'),1);
        	}
        }
        $this->public_assign();
    }
    
    /**
     * 保存公告变量到 smarty中 比如 导航 
     */
    public function public_assign()
    {
       $tpshop_config = array();
       $tp_config = M('config')->select();       
       foreach($tp_config as $k => $v)
       {
          $tpshop_config[$v['inc_type'].'_'.$v['name']] = $v['value'];
       }
       $this->assign('tpshop_config', $tpshop_config);       
    }
    
    public function check_priv()
    {	
    	$seller = session('seller');
    	if($seller['is_admin'] == 0){
    		$ctl = CONTROLLER_NAME;
    		$act = ACTION_NAME;
    		$act_list = $seller['act_limits'];
    		//无需验证的操作
    		$uneed_check = array('login','logout','vertifyHandle','vertify','imageUp','upload','login_task');
    		if($ctl == 'Index' || $act_list == 'all'){
    			//后台首页控制器无需验证,超级管理员无需验证
    			return true;
    		}elseif(strpos('ajax',$act) || in_array($act,$uneed_check)){
    			//所有ajax请求不需要验证权限
    			return true;
    		}else{
    			$role_right = explode(',', $act_list);
    			//检查是否拥有此操作权限
    			if(!in_array($ctl.'@'.$act, $role_right)){
    				$this->error('您没有操作权限,请联店铺超级管理员分配权限',U('Index/welcome'));
    			}
    		}
    	}
    	return true;
    }
    
}