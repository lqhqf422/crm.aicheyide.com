<?php

namespace app\admin\controller\order;

use app\common\controller\Backend;
use app\admin\model\PlanAcar;
use app\admin\model\Models;
use think\Db;
use think\Session;
/**
 * 订单列管理
 *
 * @icon fa fa-circle-o
 */
class Salesorder extends Backend
{
    
    /**
     * SalesOrder模型对象
     * @var \app\admin\model\SalesOrder
     */
    protected $model = null;
    protected $dataLimitField = 'admin_id'; //数据关联字段,当前控制器对应的模型表中必须存在该字段
    protected  $dataLimit = 'false'; //表示显示当前自己和所有子级管理员的所有数据
    public function _initialize()
    {
        parent::_initialize();
        $this->model = model('SalesOrder');
        $this->view->assign("genderdataList", $this->model->getGenderdataList());
        $this->view->assign("customerSourceList", $this->model->getCustomerSourceList());
        $this->view->assign("reviewTheDataList", $this->model->getReviewTheDataList());
    }
    public function index()
    {
        //设置过滤方法
        $this->request->filter(['strip_tags']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $total = $this->model
                ->where($where)
                ->order($sort, $order)
                ->count();

            $list = $this->model
                ->where($where)
                ->order($sort, $order)
                ->limit($offset, $limit)
                ->select();

            $list = collection($list)->toArray();
            $result = array("total" => $total, "rows" => $list);

            return json($result);
        }
       $this->assignconfig('num',1);

        return $this->view->fetch();
    }
    public function add()
    { 
        
       
        $newRes = array();
        //品牌
        $res = Db::name('brand')->field('id as brandid,name as brand_name,brand_logoimage')->select();
        // pr(Session::get('admin'));die;
        foreach ((array)$res as $key=>$value) {
            $sql = Db::name('models')->alias('a')
            ->join('plan_acar b','b.models_id=a.id')
            ->join('financial_platform c','b.financial_platform_id=c.id')
            ->field('a.name as models_name,b.id,b.payment,b.monthly,b.gps,b.tail_section,c.name as financial_platform_name')
            ->where(['a.brand_id'=>$value['brandid'],'b.ismenu'=>1])
            
            ->select() ;
            $newB =[];
            foreach((array)$sql as $bValue){ 
                $bValue['models_name'] =$bValue['models_name'].'【首付'.$bValue['payment'].'，'.'月供'.$bValue['monthly'].'，'.'GPS '.$bValue['gps'].'，'.'尾款 '.$bValue['tail_section'].'】'.'---'.$bValue['financial_platform_name'];
                $newB[] = $bValue;
            }
          

            $newRes[]=array( 
                'brand_name' => $value['brand_name'],
                // 'brand_logoimage'=>$value['brand_logoimage'],
                'data'=>$newB
            );
     

        }  
        $this->view->assign('newRes',$newRes);
   
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            //生成订单编号
            $params['order_no'] = 'JY_'.date('Ymdhis').rand(1000,9999);
             //把当前销售员所在的部门的内勤id 入库
             
             //message8=>销售一部顾问，message13=>内勤一部
             //message9=>销售二部顾问，message20=>内勤二部
            // $adminRule =Session::get('admin')['rule_message'];  //测试完后需要把注释放开
            $adminRule = 'message8'; //测试数据
            if($adminRule=='message8'){
                $params['backoffice_id'] = Db::name('admin')->where(['rule_message'=>'message13'])->find()['id'];
                // return true;
            }
            if($adminRule=='message9'){
                $params['backoffice_id'] = Db::name('admin')->where(['rule_message'=>'message13'])->find()['id'];
                // return true;

            } 
            if ($params) {
                if ($this->dataLimit && $this->dataLimitFieldAutoFill) {
                    $params[$this->dataLimitField] = $this->auth->id;
                }
                try {
                    //是否采用模型验证
                    if ($this->modelValidate) {
                        $name = basename(str_replace('\\', '/', get_class($this->model)));
                        $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.add' : true) : $this->modelValidate;
                        $this->model->validate($validate);
                    }
                    $result = $this->model->allowField(true)->save($params);
                    if ($result !== false) {
                       
                        $this->success();
                    } else {
                        $this->error($this->model->getError());
                    }
                } catch (\think\exception\PDOException $e) {
                    $this->error($e->getMessage());
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        return $this->view->fetch();
    }
 
    
    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将application/admin/library/traits/Backend.php中对应的方法复制到当前控制器,然后进行修改
     */
    

}