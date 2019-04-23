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
 * Date: 2016-05-27
 */

namespace Admin\Controller;
use Admin\Logic\StoreLogic;
use Common\Controller\QrcodeController;
use Think\Exception;
use Think\Model;
use Think\AjaxPage;
use OSS\OssClient;
use OSS\Core\OssException;
require_once "./ThinkPHP/Library/Vendor/oss/autoload.php";

class StoreController extends BaseController{
	
	//店铺等级
	public function store_grade(){
		$model =  M('store_grade');
		$count = $model->where('1=1')->count();
		$Page = new \Think\Page($count,10);
		$list = $model->order('sg_id')->limit($Page->firstRow.','.$Page->listRows)->select();
		$this->assign('list',$list);
		$show = $Page->show();
		$this->assign('page',$show);
		$this->display();
	}
	
	public function grade_info(){
		$sg_id = I('sg_id');
		if($sg_id){
			$info = M('store_grade')->where("sg_id=$sg_id")->find();
			$this->assign('info',$info);
		}
		$this->display();
	}
	
	public function grade_info_save(){
		$data = I('post.');
		if($data['sg_id'] > 0 || $data['act']=='del'){
			if($data['act'] == 'del'){
				if(M('store')->where(array('grade_id'=>$data['del_id']))->count()>0){
					respose('该等级下有开通店铺，不得删除');
				}else{
					$r = M('store_grade')->where("sg_id=".$data['del_id'])->delete();
					respose(1);
				}
			}else{
				$r = M('store_grade')->where("sg_id=".$data['sg_id'])->save($data);
			}
		}else{
			$r = M('store_grade')->add($data);
		}
		if($r){
			$this->success('编辑成功',U('Store/store_grade'));
		}else{
			$this->error('提交失败');
		}
	}
	
	public function store_class(){
		$model =  M('store_class');
		$keywords = I('keywords','');
		$map = '1=1';
		if (!empty($keywords))
		    $map = array('sc_name'=>array('like',"%$keywords%"));
		$count = $model->where($map)->count();
		$Page = new \Think\Page($count,10);
		$list = $model->where($map)->order('sc_id')->limit($Page->firstRow.','.$Page->listRows)->select();
		$this->assign('list',$list);
		$show = $Page->show();
		$this->assign('page',$show);
		$this->display();
	}
	
	//店铺分类
	public function class_info(){
		$sc_id = I('sc_id');
		if($sc_id){
			$info = M('store_class')->where("sc_id=$sc_id")->find();
			$this->assign('info',$info);
		}
		$this->display();
	}
	
	public function class_info_save(){
		$data = I('post.');
		if($data['sc_id'] > 0 || $data['act']=='del'){
			if($data['act']== 'del'){
				if(M('store')->where(array('sc_id'=>$data['del_id']))->count()>0){
					respose('该分类下有开通店铺，不得删除');
				}else{
					$r = M('store_class')->where("sc_id=".$data['del_id'])->delete();
					respose(1);
				}
			}else{
				$r = M('store_class')->where("sc_id=".$data['sc_id'])->save($data);
			}
		}else{
			$r = M('store_class')->add($data);
		}
		if($r){
			$this->success('编辑成功',U('Store/store_class'));
		}else{
			$this->error('提交失败');
		}
	}
	
	//普通店铺列表
	public function store_list(){
	    $role_id = I('role_id') ? intval(I('role_id')) : 1;
		$model =  M('store');
		$map['is_own_shop'] = 0 ;
		$map['role_id'] = $role_id;
		$grade_id = I("grade_id");
		if($grade_id>0) $map['grade_id'] = $grade_id;
		$sc_id =I('sc_id');
		if($sc_id>0) $map['sc_id'] = $sc_id;
		$store_state = I("store_state");
		if($store_state>0)$map['store_state'] = $store_state;
		$seller_name = I('seller_name');
		if($seller_name) $map['seller_name'] = array('like',"%$seller_name%");
		$store_name = I('store_name');
		if($store_name) $map['store_name'] = array('like',"%$store_name%");
		$count = $model->where($map)->count();
		$Page = new \Think\Page($count,10);
		$list = $model->where($map)->order('store_id DESC')->limit($Page->firstRow.','.$Page->listRows)->select();
		$this->assign('list',$list);
		
		$show = $Page->show();
		$this->assign('page',$show);
		$store_grade = M('store_grade')->getField('sg_id,sg_name');
		$this->assign('store_grade',$store_grade);
		$this->assign('store_class',M('store_class')->getField('sc_id,sc_name'));

		$role_list = M('distribution_role')->order('role_id asc')->select();
		$this->assign('role_list',$role_list);
        $this->assign('role_name',get_column('distribution_role',array('role_id'=>$role_id),'name'));
        $this->assign('role_id',$role_id);
        $this->assign('role_num', get_max_provincial()); //下线限制
		$this->display();
	}
    /**
     * 店铺金额明细
     * wj
     * 2018-7-21
     */
    public function store_money_list(){
        $store_id = I('store_id');
        $map['store_id'] = array('eq',$store_id);
        $count = M('AccountLogStore')->where($map)->count();
        $Page = new \Think\Page($count,10);
        $list = M('AccountLogStore')->where($map)->order('log_id desc')->limit($Page->firstRow.','.$Page->listRows)->select();
        $this->assign('list',$list);
        $show = $Page->show();
        $this->assign('page',$show);
        $this->display();
    }
	/*添加店铺*/
	public function store_add(){
		if(IS_POST){
			$store_name = I('store_name');
			$user_name = I('user_name');
			$seller_name = I('seller_name');
			if(M('store')->where("store_name='$store_name'")->count()>0){
				$this->error("店铺名称已存在");
			}
			if(M('seller')->where("seller_name='$seller_name'")->count()>0){
				$this->error("此名称已被占用");
			}
			$user_id = M('users')->where("email='$user_name' or mobile='$user_name'")->getField('user_id');
			if($user_id){
				if(M('store')->where(array('user_id'=>$user_id))->count()>0){
					$this->error("该会员已经申请开通过店铺");
				}
			}
            #region 下线个数控制
            //平台控制下线
            $max_num = get_max_provincial();
            $count_store = get_count('users',array('first_leader'=>0,'role_id'=>1));
            if ($max_num>0 && $count_store >= $max_num){ //数量为0则不限制
                $this->error('下线数量已满'.$max_num.'个',U('Mobile/Index/index'));die;
            }
            #endregion
			$store = array('store_name'=>$store_name,'user_name'=>$user_name,'store_state'=>1,
					'seller_name'=>$seller_name,'password'=>I('password'),
					'is_wholesaler'=>I('is_wholesaler') === 'on'?1:0,
					'store_time'=>time(),'is_own_shop'=>I('is_own_shop')
			);
			$role_id = I('role_id') ? intval(I('role_id')) : 0;
			$storeLogic = new StoreLogic();
			if($storeLogic->addStore($store,$role_id)){
				if(I('is_own_shop') == 1){
					$this->success('店铺添加成功',U('Store/store_own_list'));
				}else{
					$this->success('店铺添加成功',U('Store/store_list'));
				}
				exit;
			}else{
				$this->error("店铺添加失败");
			}
		}
		$is_own_shop = I('is_own_shop',1);
		$this->assign('is_own_shop',$is_own_shop);

        #region 分销角色获取
        if (!$is_own_shop){
            $role = M('distribution_role')->where(array('role_id'=>1))->find();
            $this->assign('role',$role);
        }
        #endregion

		$this->display();
	}
	
	/*验证店铺名称，店铺登陆账号*/
	public function store_check(){
		$store_name = I('store_name');
		$seller_name = I('seller_name');
		$user_name = I('user_name');
		$res = array('stat'=>'ok');
		if($store_name && M('store')->where("store_name='$store_name'")->count()>0){
			$res = array('stat'=>'fail','msg'=>'店铺名称已存在');
		}
		
		if(!empty($user_name)){
			if(!check_email($user_name) && !check_mobile($user_name)){
				$res = array('stat'=>'fail','msg'=>'店主账号格式有误');
			}
			if(M('users')->where("email='$user_name' or mobile='$user_name'")->count()>0){
				$res = array('stat'=>'fail','msg'=>'会员名称已被占用');
			}
		}

		if($seller_name && M('seller')->where("seller_name='$seller_name'")->count()>0){
			$res = array('stat'=>'fail','msg'=>'此账号名称已被占用');
		}
		respose($res);
	}


	
	/*编辑自营店铺*/
	public function store_edit(){
		if(IS_POST){
			$data = I('post.');
			$pwd = $data['password'];
            unset($data['password']);

			if(M('store')->where("store_id=".$data['store_id'])->save($data) !== false){
				if (!empty($pwd)){
				    $user_id = get_column('store',array('store_id'=>$data['store_id']),'user_id');
				    if (!empty($user_id)){
                        M('users')->where(array('user_id'=>$user_id))->setField(array('password'=>encrypt($pwd)));
                    }
                }
			    $this->success('编辑店铺成功');
				exit;
			}else{
				$this->error('编辑失败');
			}
		}

		$store_id = I('store_id',0);
		$store = M('store')->where("store_id=$store_id")->find();
		$this->assign('store',$store);
		$this->display();
	}
	
	//编辑外驻店铺
	public function store_info_edit(){
		if(IS_POST){
			$map = I('post.');
			$store = $map['store'];
			unset($map['store']);
			$a = M('store')->where(array('store_id'=>$map['store_id']))->save($store);
			$b = M('store_apply')->where(array('user_id'=>$map['user_id']))->save($map);
			if($b || $a){
				if($store['store_state'] == 0){
					//关闭店铺，同时下架店铺所有商品
					M('goods')->where(array('store_id'=>$map['store_id']))->save(array('is_on_sale'=>0));
				}
				$this->success('编辑店铺成功',U('Store/store_list'));
				exit;
			}else{
				$this->error('编辑失败');
			}
		}
		$store_id = I('store_id');
		if($store_id>0){
			$store = M('store')->where("store_id=$store_id")->find();
			$this->assign('store',$store);
			$apply = M('store_apply')->where('user_id='.$store['user_id'])->find();
			$this->assign('apply',$apply);
		}
		$this->assign('store_grade',M('store_grade')->getField('sg_id,sg_name'));
		$this->assign('store_class',M('store_class')->getField('sc_id,sc_name'));
		$province = M('region')->where(array('parent_id'=>0,'level'=>1))->select();
		$this->assign('province',$province);
		$this->display();
	}
	/*删除店铺*/
	public function store_del(){
		$store_id = I('del_id');
		try{
            if($store_id > 1){
                $map = array('store_id'=>$store_id);
                $store = M('store')->where($map)->find();
                if(M('goods')->where($map)->count()>0){
                    throw new Exception('该店铺有发布商品，不得删除');
                }else if ( get_count('store',array('first_leader'=>$store_id)) >0 ){
                    throw new Exception('该'.get_column('distribution_role',array('role_id'=>1),'name').'存在下线,无法删除');
                }else if (get_count('order',array('store_id'=>$store_id)) >0 ){
                    throw new Exception('该'.get_column('distribution_role',array('role_id'=>1),'name').'存在订单,无法删除');
                }else if (get_count('card_order',array('store_id'=>$store_id)) >0 ){
                    throw new Exception('该'.get_column('distribution_role',array('role_id'=>1),'name').'存在卡券订单,无法删除');
                } else{
                    //店铺日志记录
                    if (!$store['is_own_shop'] && $store['role_id']>0)
                        store_log($store_id,$store['first_leader']?$store['first_leader']:0,'删除商户:'.$store['store_name'],$log_type=0);
                    //删除店铺信息
                    $map = array('store_id'=>$store_id);
                    M('store')->where($map)->delete();
                    M('store_extend')->where($map)->delete();
                    M('seller')->where($map)->delete();
                    //清除上级信息
                    M('users')->where(array('user_id'=>$store['user_id']))->save(array('first_leader'=>0,'iskaidian'=>0,'role_id'=>0));

                    adminLog("删除店铺".$store['store_name']);

                    $this->ajaxReturn(array('status'=>1,'msg'=>'删除成功'));
                }
            }else{
                throw new Exception('基础自营店，不得删除');
            }
        }catch(Exception $e){
            $this->ajaxReturn(array('status'=>0,'msg'=>$e->getMessage()));
        }
	}
	
	//店铺信息
	public function store_info(){
		$store_id = I('store_id');
		$store = M('store')->where("store_id=".$store_id)->find();
		$this->assign('store',$store);
		$apply = M('store_apply')->where("user_id=".$store['user_id'])->find();
		$this->assign('apply',$apply);
		$bind_class_list = M('store_bind_class')->where("store_id=".$store_id)->select();
		$goods_class = M('goods_category')->getField('id,name');
		for($i = 0, $j = count($bind_class_list); $i < $j; $i++) {
			$bind_class_list[$i]['class_1_name'] = $goods_class[$bind_class_list[$i]['class_1']];
			$bind_class_list[$i]['class_2_name'] = $goods_class[$bind_class_list[$i]['class_2']];
			$bind_class_list[$i]['class_3_name'] = $goods_class[$bind_class_list[$i]['class_3']];
		}
		$this->assign('bind_class_list',$bind_class_list);
		$this->display();
	}
	
	//自营店铺列表
	public function store_own_list(){
		$model =  M('store');
		$map['is_own_shop'] = 1 ;
		$grade_id = I("grade_id");
		if($grade_id>0) $map['grade_id'] = $grade_id;
		$sc_id =I('sc_id');
		if($sc_id>0) $map['sc_id'] = $sc_id;
		$store_state = I("store_state");
		if($store_state>0)$map['store_state'] = $store_state;
		$seller_name = I('seller_name');
		if($seller_name) $map['seller_name'] = array('like',"%$seller_name%");
		$store_name = I('store_name');
		if($store_name) $map['store_name'] = array('like',"%$store_name%");
		$count = $model->where($map)->count();

		$Page = new \Think\Page($count,10);
		$list = $model->where($map)->order('store_id DESC')->limit($Page->firstRow.','.$Page->listRows)->select();
		$this->assign('list',$list);	
		$show = $Page->show();
		$this->assign('page',$show);
		$store_grade = M('store_grade')->getField('sg_id,sg_name');
		$this->assign('store_grade',$store_grade);
		$this->assign('store_class',M('store_class')->field('sc_id,sc_name')->select());
		$this->display();
	}
	
	//店铺申请列表
	public function apply_list(){
		$model =  M('store_apply');
		$map['apply_state'] = array('neq',1);
		$grade_id = I("grade_id");
		if($grade_id>0) $map['grade_id'] = $grade_id;
		$sc_id =I('sc_id');
		if($sc_id>0) $map['sc_id'] = $sc_id;
		$seller_name = I('seller_name');
		if($seller_name) $map['seller_name'] = array('like',"%$seller_name%");
		$store_name = I('store_name');
		if($store_name) $map['store_name'] = array('like',"%$store_name%");
		$count = $model->where($map)->count();
		$Page = new \Think\Page($count,10);
		$list = $model->where($map)->order('add_time desc')->limit($Page->firstRow.','.$Page->listRows)->select();
		$this->assign('list',$list);
		$show = $Page->show();
		$this->assign('page',$show);
		$this->assign('store_grade',M('store_grade')->getField('sg_id,sg_name'));
		$this->assign('store_class',M('store_class')->getField('sc_id,sc_name'));
		$this->display();
	}
	
	public function apply_del(){
		$id = I('del_id');
		if($id && M('store_apply')->where(array('id'=>$id))->delete()){
			$this->success('操作成功',U('Store/apply_list'));
		}else{
			$this->error('操作失败');
		}
	}
	//经营类目申请列表
	public function apply_class_list(){
		$state = I('state');
		$store_name = trim(I('store_name',''));
		if($state != ""){
			$bind_class = M('store_bind_class')->where(array('state'=>$state))->select();
		}else{
			$bind_class = M('store_bind_class')->select();
		}		
		$goods_class = M('goods_category')->getField('id,name');
		for($i = 0, $j = count($bind_class); $i < $j; $i++) {
			$bind_class[$i]['class_1_name'] = $goods_class[$bind_class[$i]['class_1']];
			$bind_class[$i]['class_2_name'] = $goods_class[$bind_class[$i]['class_2']];
			$bind_class[$i]['class_3_name'] = $goods_class[$bind_class[$i]['class_3']];
			$map = array();
			$map['store_id'] = $bind_class[$i]['store_id'];
			if (!empty($store_name)) $map['store_name'] = array('like',"%$store_name%");
			$store = M('store')->where($map)->find();

			if (!empty($store)){
                $bind_class[$i]['store_name'] = $store['store_name'];
                $bind_class[$i]['seller_name'] = $store['seller_name'];
            }else{
			    unset($bind_class[$i]);
            }
		}
		$this->assign('bind_class',$bind_class);
		$this->display();
	}
	
	//查看-添加店铺经营类目
	public function store_class_info(){
		$store_id = I('store_id');
		$store = M('store')->where(array('store_id'=>$store_id))->find();
		$this->assign('store',$store);
		if(IS_POST){
			$data = I('post.');
			$data['state'] = empty($store['is_own_shop']) ? 0 : 1;
			$where = 'class_3 ='.$data['class_3'].' and store_id='.$store_id;
			if(M('store_bind_class')->where($where)->count()>0){
				$this->error('该店铺已申请过此类目');
			}
			if(M('store_bind_class')->add($data)){
				adminLog('添加店铺经营类目，类目编号:'.$data['class_3'].',店铺编号:'.$data['store_id']);
				$this->success('添加经营类目成功');exit;
			}else{
				$this->error('操作失败');
			}
		}
		$bind_class_list = M('store_bind_class')->where('store_id='.$store_id)->select();
		$goods_class = M('goods_category')->getField('id,name');
		for($i = 0, $j = count($bind_class_list); $i < $j; $i++) {
			$bind_class_list[$i]['class_1_name'] = $goods_class[$bind_class_list[$i]['class_1']];
			$bind_class_list[$i]['class_2_name'] = $goods_class[$bind_class_list[$i]['class_2']];
			$bind_class_list[$i]['class_3_name'] = $goods_class[$bind_class_list[$i]['class_3']];
		}
		$this->assign('bind_class_list',$bind_class_list);
		$cat_list = M('goods_category')->where("parent_id = 0")->select();
		$this->assign('cat_list',$cat_list);
		$this->display();
	}
	
	
	public function apply_class_save(){
		$data = I('post.');
		if($data['act']== 'del'){
			$r = M('store_bind_class')->where("bid=".$data['del_id'])->delete();
			respose(1);
		}else{
			$data = I('get.');
			$r = M('store_bind_class')->where("bid=".$data['bid'])->save(array('state'=>1));
		}
		if($r){
			$this->success('操作成功',U('Store/apply_class_list'));
		}else{
			$this->error('提交失败');
		}
	}
	
	//店铺申请信息详情
	public function apply_info(){
		$id = I('id');
		$apply = M('store_apply')->where("id=$id")->find();
		$goods_cates = M('goods_category')->getField('id,name,commission');
		if(!empty($apply['store_class_ids'])){
			$store_class_ids = unserialize($apply['store_class_ids']);
			foreach ($store_class_ids as $val){
				$cat = explode(',', $val);
				$bind_class_list[] = array('class_1'=>$goods_cates[$cat[0]]['name'],'class_2'=>$goods_cates[$cat[1]]['name'],
						'class_3'=>$goods_cates[$cat[2]]['name'].'(分佣比例：'.$goods_cates[$cat[2]]['commission'].'%)',
						'value'=>$val,
				);
			}
			$this->assign('bind_class_list',$bind_class_list);
		}
		$this->assign('apply',$apply);
		$apply_log = M('admin_log')->where(array('log_type'=>1))->select();
		$this->assign('apply_log',$apply_log);
		$this->assign('store_grade',M('store_grade')->select());
		$this->display();
	}
	
	//审核店铺开通申请
	public function review(){
		$data = I('post.');
		if($data['id']){
			$apply = M('store_apply')->where(array('id'=>$data['id']))->find();
			if(M('store_apply')->where("id=".$data['id'])->save($data)){
				if($data['apply_state'] == 1){
					$users = M('users')->where(array('user_id'=>$apply['user_id']))->find();
					if(empty($users)) $this->error('申请会员不存在或已被删除，请检查');
					$store = array('user_id'=>$apply['user_id'],'seller_name'=>$apply['seller_name'],
							'user_name'=>empty($users['email']) ? $users['mobile'] : $users['email'],
							'grade_id'=>$data['sg_id'],'store_name'=>$apply['store_name'],'sc_id'=>$apply['sc_id'],
							'company_name'=>$apply['company_name'],'store_phone'=>$apply['store_person_mobile'],
							'store_address'=>empty($apply['store_address']) ? '待完善' : $apply['store_address'] ,
							'store_time'=>time(),'store_state'=>1,'store_qq'=>$apply['store_person_qq'],							
					);
					$store_id = M('store')->add($store);//通过审核开通店铺
					if($store_id){
						$seller = array('seller_name'=>$apply['seller_name'],'user_id'=>$apply['user_id'],
							'group_id'=>0,'store_id'=>$store_id,'is_admin'=>1
						);
						M('seller')->add($seller);//点击店铺管理员
						//绑定商家申请类目
						if(!empty($apply['store_class_ids'])){
							$goods_cates = M('goods_category')->where(array('level'=>3))->getField('id,name,commission');
							$store_class_ids = unserialize($apply['store_class_ids']);
							foreach ($store_class_ids as $val){
								$cat = explode(',', $val);
								$bind_class = array('store_id'=>$store_id,'commis_rate'=>$goods_cates[$cat[2]]['commission'],
										'class_1'=>$cat[0],'class_2'=>$cat[1],'class_3'=>$cat[2],'state'=>1);
								M('store_bind_class')->add($bind_class);
							}
						}
					}
					adminLog($apply['store_name'].'开店申请审核通过',1);
				}else if($data['apply_state'] == 2){
					adminLog($apply['store_name'].'开店申请审核未通过，备注信息：'.$data['review_msg'],1);
				}
				$this->success('操作成功',U('Store/apply_list'));
			}else{
				$this->error('提交失败');
			}
		}
	}
	
	
	public function reopen_list(){
		$this->assign('store_class',M('store_class')->getField('sc_id,sc_name'));
		$this->display();
	}

	//省级推广二维码
    public function share_code(){
        $end_time = time() + shop_share_time();
        //$ShareLink = urlencode("http://{$_SERVER[HTTP_HOST]}/index.php?m=Mobile&c=NewJoin&a=index&first_leader=0&is_admin=1&iskaidian=1&end_time={$end_time}"); //默认分享链接
        $ShareLink = "http://{$_SERVER[HTTP_HOST]}/index.php?m=Mobile&c=NewJoin&a=index&first_leader=0&is_admin=1&iskaidian=1&end_time={$end_time}"; //默认分享链接
        //$qr_url = U('Home/Index/qr_code',array('data'=>$ShareLink),'',true);
        //if(get_column('users',array('user_id'=>$this->_seller['user_id']),'is_distribut') == 1)
        $logo = M('wx_user')->getField('headerpic');
        if (!empty($logo)) $logo = 'http://'.$_SERVER['HTTP_HOST'].$logo;
        $qrcode = new QrcodeController();
        $qr_url = $qrcode->create_qrcode($ShareLink,4, 'L',$logo);
        //$qr_url = $qrcode->create_qrcode(urlencode($ShareLink),4, 'L',$logo);
        $this->assign('qr_url',$qr_url);
        $this->display();
    }

    public function share_bg(){
        $qrcode = new QrcodeController();
        $qrcode->qrcode_bg(urldecode(I('code')),'',105,115);
    }

    //店铺审核
    public function change_column(){
        $store_id = I('id');
        $value = I('value');
        $res = set_field('store',$store_id,array('store_state'=>$value));
        if ($res !== false)
            $this->ajaxReturn(array('status'=>1,'msg'=>'审核成功'));
        $this->ajaxReturn(array('status'=>0,'msg'=>'审核失败'));

    }

    //商户入住日志
    public function store_log(){
        //商户入住日志
        $Log = M('store_log');
        $p = I('p',1);
        $map = array('sl.first_leader'=>0);
        $alias = 'sl';
        $logs = $Log->alias($alias)->where($map)
           /* ->join(array('LEFT JOIN __STORE__ as s ON s.store_id =sl.store_id'))*/
            ->order('log_time DESC')->page($p.',20')->select();
        $this->assign('list',$logs);
        $count = $Log->alias($alias)->where($map)->count();
        $Page = new \Think\Page($count,20);
        $show = $Page->show();
        $this->assign('page',$show);
        $this->display();
    }

    //更换商家信息
    public function store_change(){
        if (IS_POST) {
            try{
                $data = $this->check_change_data(); //检测数据
                $res = $this->_store_change($data); //更改账户信息
                if ($res == false) throw new Exception('更改失败');
                $this->ajaxReturn(array('status'=>1,'msg'=>'更改成功','url'=>U('store_list')));
            }catch(Exception $e){
                $this->ajaxReturn(array('status'=>0,'msg'=>$e->getMessage()));
            }
        }
        //region GET
        $store_id = I('store_id');
        if (empty($store_id)) {
            redirect(U('store_list'));die;
        }
        $store = M('store')->where(array('store_id'=>$store_id))->find();
        $user = M('users')->where(array('user_id'=>$store['user_id']))->find();
        $seller = M('seller')->where(array('store_id'=>$store_id))->find();
        //region 非商户等级以上的普通用户列表
        $map = array();
        $map['role_id'] = array('eq',0);
        $map['first_leader'] = array('eq',0);
        $user_list = M('users')->where($map)->select();
        //region 去除自营店店铺列表
        $store_user = M('store')->where(array('is_own_shop'=>1))->field('user_id')->select();
        if (!empty($store_user)) {
            foreach ($store_user as $key => $vo){
                foreach ($user_list as $k => $v){
                    if ($vo['user_id'] == $v['user_id']){
                        unset($user_list[$k]);
                    }
                }
            }
        }
        //endregion
        $this->assign('user_list',$user_list);
        //endregion
        $this->assign('store',$store);
        $this->assign('user',$user);
        $this->assign('seller',$seller);
        $this->display();
        //endregion
    }

    //检测更换商家信息
    protected function check_change_data(){
        $data = I('post.');
        if (empty($data) || $data['store_id'] <=0 ) throw new Exception('请重新填写数据');
        if(M('store')->where(array('store_name'=>$data['store_name'],'store_id'=>array('neq',$data['store_id'])))->count()>0){
            throw new Exception('店铺名称已存在');
        }
        if(M('seller')->where(array('seller_name'=>$data['seller_name'],'store_id'=>array('neq',$data['store_id'])))->count()>0){
            throw new Exception('此名称已被占用');
        }
        if(M('store')->where(array('user_id'=>$data['user_id']))->count()>0){
            throw new Exception('该会员已经申请开通过店铺');
        }
        if (strlen($data['new_password']) <6 || strlen($data['new_password'])>16)
            throw new Exception('密码为6-16位字母数字组合');

        return $data;
    }

    //更换店主
    protected function _store_change($data=[]){
        $map = array('store_id'=>$data['store_id']);
        //获取 旧店铺信息
        $store = M('store')->where($map)->find();
        if (empty($store)) throw new Exception('该店铺不存在');
        //获取 旧店主信息
        $seller = M('seller')->where($map)->find();
        if (empty($seller)) throw new Exception('商户卖家不存在');
        //获取 旧店主会员信息
        $old_user = M('users')->where(array('user_id'=>$store['user_id']))->find();
        //获取 新店主会员信息
        $new_user = M('users')->where(array('user_id'=>$data['user_id']))->find();
        //判断是否未更改店主
        if ($old_user['user_id'] == $new_user['user_id']) throw new Exception('店主未更换，信息未改动');

        //执行更改操作
        try{
            $model = new Model();
            $model->startTrans(); //开启事务
            //region 修改店铺信息
            $store['store_name']    = $data['store_name'];
            $store['seller_name']   = $data['seller_name'];
            $store['user_name']     = $new_user['mobile'] ? $new_user['mobile'] : $new_user['nickname'];
            $store['user_id']       = $new_user['user_id'];
            $res = $model->table(C('DB_PREFIX').'store')->where(array('store_id'=>$store['store_id']))->save($store);
            if ($res === false) throw new Exception('修改店铺信息失败');
            //endregion
            //region 修改店主信息
            $seller['user_id']      = $new_user['user_id'];
            $seller['seller_name']  = $data['seller_name'];
            $res = $model->table(C('DB_PREFIX').'seller')->where(array('store_id'=>$store['store_id']))->save($seller);
            if ($res === false) throw new Exception('修改店主信息失败');
            //endregion
            //region 修改新店主会员信息
            $new_user['role_id']    = $old_user['role_id'];//角色信息更改
            $new_user['password']   = encrypt($data['new_password']);//角色信息更改
            $new_user['iskaidian']  = $old_user['iskaidian'];//开店信息
            $new_user['first_leader']=$old_user['first_leader'];//上一级用户id
            $res = $model->table(C('DB_PREFIX').'users')->where(array('user_id'=>$new_user['user_id']))->save($new_user);
            if ($res === false) throw new Exception('修改店主信息失败');
            //endregion
            //region 修改旧店主会员信息 清除信息
            $old_user['role_id']    = 0;
            $old_user['iskaidian']  = 0;
            $old_user['first_leader']=0;
            $res = $model->table(C('DB_PREFIX').'users')->where(array('user_id'=>$old_user['user_id']))->save($old_user);
            if ($res === false) throw new Exception('修改店主信息失败');
            //endregion
            //region 将所有的下线移植到新的店主下
            $res = $model->table(C('DB_PREFIX').'users')->where(array('first_leader'=>$old_user['user_id']))->save(array('first_leader'=>$new_user['user_id']));
            if ($res === false) throw new Exception('移植店铺下级信息失败');
            //endregion

            //region 将店铺资金加入旧店主个人资金中  改
            $res = $model->table(C('DB_PREFIX').'users')->where(array('user_id'=>$old_user['user_id']))->setInc('user_money',$store['store_money']);
            if ($res === false) throw new Exception('店铺资金结算旧店主失败');
            /* 插入帐户变动记录 */
            $account_log = array(
                'user_id' => $old_user['user_id'],
                'user_money' => $store['store_money'],
                'pay_points' => 0,
                'change_time' => time(),
                'desc' => '店主更换，店铺资金结算',
            );
            M('account_log')->add($account_log);
            //endregion
            //region 清除旧店铺结算资金
            $res = $model->table(C('DB_PREFIX').'store')->where(array('store_id'=>$store['store_id']))->save(array('store_money'=>0));
            if ($res === false) throw new Exception('店铺资金清除失败');
            store_log($store['store_id'],$store['first_leader'],'资金店铺清除，资金转移旧店主：'.$data['seller_name']);
            //endregion

            $model->commit(); //提交事务
            //店铺操作日志
            store_log($store['store_id'],$store['first_leader'],'后台店主更换：'.$data['seller_name']);
            return true;
        }catch(Exception $e){
            $model->rollback();//事务回滚
            throw new Exception($e->getMessage());
        }
    }

    /**
     * 店铺信息审核列表
     */
    public function store_review_list(){

        if (IS_POST){
            $condition = array();
            I('store_name') ? $condition['store_name'] =array('like',"%".I('store_name')."%") : false;
            I('type') ? $condition['type'] = I('type') : false;
            $condition['d.status'] = I('status')?I('status'):0;
            $model = M('store_description d');
            $count = $model->where($condition)->count();
            $Page  = new AjaxPage($count,10);
            foreach($condition as $key=>$val) {
                $Page->parameter[$key]   =   urlencode($val);
            }
            $db_prefix = C('DB_PREFIX');
            $list = $model
                    ->where($condition)
                    ->join('LEFT JOIN '.$db_prefix.'store s ON s.store_id = d.store_id')
                    ->order('create_time desc')
                    ->limit($Page->firstRow.','.$Page->listRows)->select();
            $this->assign('list',$list);
            $this->display('info_review_data');
        } else {
            $this->display('info_review');
        }
    }

    public function review_detail(){
        $id = I('id');
        $detail = M('store_description')->where(array('id'=>$id))->find();
        $this->assign('detail',$detail);
        $this->display('info_review_detail');
    }
    public function info_review(){
        $approval = boolval(I('approval'));
        $type = intval(I('type',1));
        if ($approval === true){
            $id = I('get.id');
            $db_prefix = C('DB_PREFIX');
            $model = M();
            $model->startTrans();
            if ($type == 2){
//                $previous = M('store_description')->where(array('type'=>$type,'status'=>1))->getField('content');
            }
//            $deleteId = $model->table($db_prefix.'store_description')->where(array('type'=>$type,'status'=>1))->delete();
            $saveId = $model->table($db_prefix.'store_description')->where(array('id'=>$id))->save(array('status'=>1));
            if ($saveId !== false){
                $model->commit();
                if (!empty($previous)){
                    unlink('.'.$previous);
                }
                $this->success('保存成功',U('Store/store_review_list'));
            } else {
                $model->rollback();
                $this->error('保存失败',U('Store/review_detail',array('id'=>$id)));
            }
        } else{
            $id = I('get.id');
            M('store_description')->where(array('id'=>$id))->save(array('status'=>2));
            $msg = '店铺图文简介';
            if ($type === 2){
                $msg = '店铺视频';
            }
            $reason = I('reason');
            $store_msg = array(
                'store_id' => I('store_id'),
                'content' => "您提交的\"{$msg}\"审核未通过,原因:{$reason}",
                'addtime' => time(),
            );

            M('store_msg')->add($store_msg);
            $this->success('操作成功',U('Store/store_review_list'));
        }
    }

    /**
     * 删除店铺的简介
     */
    public function store_description_del()
    {
        $id = I('del_id');
        $item = M('store_description')->where(array('id'=>$id))->find();
        //删除阿里云的视频
        if ($item['type'] == 2 && substr( $item['content'], 0, 4 ) === "http"){
            try{
                $this->deleteOSSVideo($item['content']);
            }catch (\Exception $e){

            }
        }
        M('store_description')->where(array('id' => $id))->delete();
        $this->ajaxReturn(array('status' => 1, 'msg' => '删除成功'));
    }

    private function deleteOSSVideo($object){
        $path = parse_url($object)['path'];
        $object = substr($path,1,strlen($path)-1);
        $accessKeyId = "LTAIz8rEfxzCc2Gq";
        $accessKeySecret = "uNYCBkBoOQT26MjI4yUZl2EDNZQtfu";
        $endpoint = "http://oss-cn-hangzhou.aliyuncs.com";
        $bucket= "jhvideo";
        try {
            $ossClient = new OssClient($accessKeyId, $accessKeySecret, $endpoint);
            $ossClient->deleteObject($bucket, $object);
        } catch(OssException $e) {
            return;
        }
    }

    /**
     * 审核店铺
     */
    public function review_store(){
        $store_id = I('id');
        $result = array('status'=>1,'msg'=>'操作成功');
        M('store')->where(array('store_id' => $store_id))->save(array('store_state' => 1));
        exit(json_encode($result));
        $distributLogic = new \Common\Logic\DistributLogic();
        $distributLogic->wholesalerRegisterRebate();
    }
}