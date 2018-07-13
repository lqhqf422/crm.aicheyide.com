<?php

namespace app\admin\controller\plan;

use app\common\controller\Backend;

/**
 * 以租代购
 *
 * @icon fa fa-circle-o
 */
class Planacar extends Backend
{
    
    /**
     * PlanAcar模型对象
     * @var \app\admin\model\PlanAcar
     */
    protected $model = null;
    protected $multiFields = 'ismenu';

    public function _initialize()
    {
        parent::_initialize();
        $this->model = model('PlanAcar');
        $this->view->assign("nperlistList", $this->model->getNperlistList());
        $this->view->assign("ismenuList", $this->model->getIsmenuList());
    }
    
    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将application/admin/library/traits/Backend.php中对应的方法复制到当前控制器,然后进行修改
     */
    

    /**
     * 查看
     */
    public function index()
    {
        //当前是否为关联查询
        $this->relationSearch = true;
        //设置过滤方法
        $this->request->filter(['strip_tags']);
        if ($this->request->isAjax())
        {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField'))
            {
                
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $total = $this->model
                    ->with(['models','financialplatform'])
                    ->where($where)
                    ->order($sort, $order)
                    ->count();

            $list = $this->model
                    ->with(['models','financialplatform'])
                    ->where($where)
                    ->order($sort, $order)
                    ->limit($offset, $limit)
                    ->select();

            foreach ($list as $row) {
                $row->visible(['id','payment','monthly','nperlist','margin','tail_section','gps','note','ismenu','createtime','updatetime']);
                $row->visible(['models']);
				$row->getRelation('models')->visible(['name']);
				$row->visible(['financialplatform']);
				$row->getRelation('financialplatform')->visible(['name']);
            }
            $list = collection($list)->toArray();
            $result = array("total" => $total, "rows" => $list);

            return json($result);
        }
        return $this->view->fetch();
    }
       /**
     * 编辑
     */
 
}
