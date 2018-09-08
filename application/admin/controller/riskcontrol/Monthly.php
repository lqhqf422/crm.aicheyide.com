<?php
/**
 * Created by PhpStorm.
 * User: glen9
 * Date: 2018/9/4
 * Time: 11:59
 */

namespace app\admin\controller\riskcontrol;
use app\common\controller\Backend;

class Monthly extends Backend
{
    /**
     * @var null
     */
    protected $model = null;

    /**
     *
     */
    public  function _initialize()
    {
        return parent::_initialize(); // TODO: Change the autogenerated stub
    }

    /**
     * 风控
     * @return \think\response\Json|void
     */
    public function index()
    {
        $this->loadlang('riskcontrol/monthly');
        $this->model = new \app\admin\model\NewcarMonthly;
        $this->view->assign([
//            'did_total' => $this->model->where(['monthly_data'=>'failure','monthly_status'=>null])->count(),
            'has_total' => $this->model->where('monthly_status', 'has_been_sent')->count(),
            'dedu_total' => $this->model->where('monthly_data', 'success')->count()

        ]);
        return $this->view->fetch();
    }

    /**
     * 新车月供管理 （扣款失败 并且已发送到风控）has_been_sent
     * @return string
     * @throws \think\Exception
     */
    public function newcarMonthly()
    {
        $this->model = new \app\admin\model\NewcarMonthly;
        $this->view->assign("monthlyDataList", $this->model->getMonthlyDataList());
        if ($this->request->isAjax()) {

            $result = $this->commontMethod('has_been_sent');
            return json($result);
        }
        return $this->view->fetch('index');
    }



    /**
     * 扣款成功  deductions_succ
     * @return string|\think\response\Json
     * @throws \think\Exception
     *
     */
    public function deductionsSucc()
    {
        $this->model = new \app\admin\model\NewcarMonthly;

        $this->view->assign("monthlyDataList", $this->model->getMonthlyDataList());
        if ($this->request->isAjax()) {

            $result = $this->commontMethod('success');

            return json($result);
        }
        return $this->view->fetch('index');
    }

    /**
     *
     * 封装查询
     * @param $status 扣款状态 failure=失败 success=成功  has_been_sent=已发送给风控
     * @return array|\think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function commontMethod($statusD)
    {
        $this->model = new \app\admin\model\NewcarMonthly;
        //设置过滤方法
        $this->request->filter(['strip_tags']);
        //如果发送的来源是Selectpage，则转发到Selectpage
        if ($this->request->request('keyField')) {
            return $this->selectpage();
        }
        list($where, $sort, $order, $offset, $limit) = $this->buildparams('monthly_name');
        $total = $this->model
            ->where($where)
            ->where(function ($query) use ($statusD) {
                //默认风控显示的是扣款失败 failure 和 已发送状态 has_been_sent
                if ($statusD === 'has_been_sent') {
                    $query->where(['monthly_status' => 'has_been_sent']);
                }
                //扣款成功客户
                if ($statusD === 'success') {
                    $query->where(['monthly_data' => 'success']);
                }
            })
            ->order($sort, $order)
            ->count();

        $list = $this->model
            ->where($where)
            ->where(function ($query) use ($statusD) {
                //如果等于扣款失败客户
                if ($statusD === 'failure') {
                    $query->where(['monthly_data' =>['=','failure'],'monthly_status'=>null]);
                } //如果等于已发送到风控
                if ($statusD ==='has_been_sent') {
                    $query->where(['monthly_status' => 'has_been_sent']);
                }
                if ($statusD === 'success') {
                    $query->where(['monthly_data' => 'success']);
                }
            })
            ->order($sort, $order)
            ->limit($offset, $limit)
            ->select();

        $list = collection($list)->toArray();
        $result = array("total" => $total, "rows" => $list);
        return $result;

    }

}