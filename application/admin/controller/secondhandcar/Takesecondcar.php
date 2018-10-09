<?php

namespace app\admin\controller\secondhandcar;

use app\common\controller\Backend;
use think\Db;
use think\Config;
use app\common\library\Email;
use app\admin\controller\Sharedetailsdatas;

/**
 * 短信验证码管理
 *
 * @icon fa fa-circle-o
 */
class Takesecondcar extends Backend
{

    /**
     * Sms模型对象
     * @var \app\admin\model\Sms
     */
    protected $model = null;
    protected $noNeedRight = ['index', 'secondtakecar', 'takecar', 'seconddetails'];

    public function _initialize()
    {
        parent::_initialize();

    }


    public function index()
    {
        $total = Db::name("second_sales_order")
            ->where("review_the_data", ["=", "for_the_car"], ["=", "the_car"], "or")
            ->count();

        $this->view->assign('total', $total);
        return $this->view->fetch();
    }


    /**待车管确认
     * @return string|\think\response\Json
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function secondtakecar()
    {
        $this->model = new \app\admin\model\SecondSalesOrder;
        //设置过滤方法
        $this->request->filter(['strip_tags']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams('username', true);

            $total = $this->model
                ->with(['plansecond' => function ($query) {
                    $query->withField('companyaccount,licenseplatenumber,newpayment,monthlypaymen,periods,totalprices,bond,tailmoney');
                }, 'admin' => function ($query) {
                    $query->withField('nickname');
                }, 'models' => function ($query) {
                    $query->withField('name');
                }])
                ->where($where)
                ->where("review_the_data", ["=", "for_the_car"], ["=", "the_car"], "or")
                ->order($sort, $order)
                ->count();


            $list = $this->model
                ->with(['plansecond' => function ($query) {
                    $query->withField('companyaccount,licenseplatenumber,newpayment,monthlypaymen,periods,totalprices,bond,tailmoney');
                }, 'admin' => function ($query) {
                    $query->withField(['nickname', 'avatar', 'id']);
                }, 'models' => function ($query) {
                    $query->withField('name');
                }])
                ->where($where)
                ->where("review_the_data", ["=", "for_the_car"], ["=", "the_car"], "or")
                ->order($sort, $order)
                ->limit($offset, $limit)
                ->select();
            foreach ($list as $k => $row) {
                $row->visible(['id', 'order_no', 'username', 'detailed_address', 'createtime', 'phone', 'id_card', 'amount_collected', 'downpayment', 'review_the_data']);
                $row->visible(['plansecond']);
                $row->getRelation('plansecond')->visible(['newpayment', 'licenseplatenumber', 'companyaccount', 'monthlypaymen', 'periods', 'totalprices', 'bond', 'tailmoney',]);
                $row->visible(['admin']);
                $row->getRelation('admin')->visible(['nickname', 'avatar', 'id']);
                $row->visible(['models']);
                $row->getRelation('models')->visible(['name']);
            }

            $list = collection($list)->toArray();
            foreach ($list as $k => $v) {
                $department = Db::name('auth_group_access')
                    ->alias('a')
                    ->join('auth_group b', 'a.group_id = b.id')
                    ->where('a.uid', $v['admin']['id'])
                    ->value('b.name');
                $list[$k]['admin']['department'] = $department;
            }
            $result = array('total' => $total, "rows" => $list);
            return json($result);
        }

        return $this->view->fetch();

    }

    /**查看二手车单详细资料 */
    public function seconddetails($ids = null)
    {
        $secondCarDetailsDatas = new  Sharedetailsdatas();
        pr($secondCarDetailsDatas->second_car_share_data($ids));die;
    }


    /**确认提车
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function takecar()
    {
        if ($this->request->isAjax()) {

            $id = $this->request->post('id');

            $result = Db::name('second_sales_order')->where('id', $id)->setField('review_the_data', 'the_car');

            $second_car_id = Db::name('second_sales_order')->where('id', $id)->value('second_car_id');

            if ($result !== false) {

                $result_s = Db::name('secondcar_rental_models_info')->where('id', $second_car_id)->setField('status_data', 'the_car');

                if ($result_s !== false) {

                    $order_info = Db::name('second_sales_order')
                        ->where('id', $id)
                        ->field('username,admin_id')
                        ->find();

                    $data = sales_inform($order_info['username']);

                    $peccancy = Db::name('second_sales_order')
                        ->alias('so')
                        ->join('models m', 'so.models_id = m.id')
                        ->join('secondcar_rental_models_info mi', 'so.plan_car_second_name = mi.id')
                        ->where('so.id', $id)
                        ->field('so.username,so.phone,m.name as models,mi.licenseplatenumber as license_plate_number,mi.vin as frame_number,mi.engine_number')
                        ->find();

                    $peccancy['car_type'] = 2;
                    $result_peccancy = Db::name('violation_inquiry')->insert($peccancy);
                    if ($result_peccancy) {
                        $this->success();
                    } else {
                        $this->error('违章信息添加失败');
                    }

                    $email = new Email();

                    $receiver = Db::name('admin')->where('id', $order_info['admin_id'])->value('email');

                    $email
                        ->to($receiver)
                        ->subject($data['subject'])
                        ->message($data['message'])
                        ->send();

                    $seventtime = \fast\Date::unixtime('day', -6);
                    $secondonesales = $secondtwosales = $secondthreesales = [];
                    $month = date("Y-m", $seventtime);
                    $day = date('t', strtotime("$month +1 month -1 day"));
                    for ($i = 0; $i < 8; $i++)
                    {
                        $months = date("Y-m", $seventtime + (($i+1) * 86400 * $day));
                        $firstday = strtotime(date('Y-m-01', strtotime($month)));
                        $secondday = strtotime(date('Y-m-01', strtotime($months)));
                        //销售一部
                        $one_sales = Db::name('auth_group_access')->where('group_id', '18')->select();
                        foreach($one_sales as $k => $v){
                            $one_admin[] = $v['uid'];
                        }
                        $secondonetake = Db::name('second_sales_order')
                                ->where('review_the_data', 'the_car')
                                ->where('admin_id', 'in', $one_admin)
                                ->where('delivery_datetime', 'between', [$firstday, $secondday])
                                ->count();
                        //销售二部
                        $two_sales = Db::name('auth_group_access')->where('group_id', '22')->field('uid')->select();
                        foreach($two_sales as $k => $v){
                            $two_admin[] = $v['uid'];
                        }
                        $secondtwotake = Db::name('second_sales_order')
                                ->where('review_the_data', 'the_car')
                                ->where('admin_id', 'in', $two_admin)
                                ->where('delivery_datetime', 'between', [$firstday, $secondday])
                                ->count();
                        //销售二部
                        $three_sales = Db::name('auth_group_access')->where('group_id', '37')->field('uid')->select();
                        foreach($three_sales as $k => $v){
                            $three_admin[] = $v['uid'];
                        }
                        $secondthreetake = Db::name('second_sales_order')
                                ->where('review_the_data', 'the_car')
                                ->where('admin_id', 'in', $three_admin)
                                ->where('delivery_datetime', 'between', [$firstday, $secondday])
                                ->count();
                        //销售一部
                        $secondonesales[$month . '(月)'] = $secondonetake;
                        //销售二部
                        $secondtwosales[$month . '(月)'] = $secondtwotake;
                        //销售三部
                        $secondthreesales[$month . '(月)'] = $secondthreetake;

                        $month = date("Y-m", $seventtime + (($i+1) * 86400 * $day));
                
                        $day = date('t', strtotime("$months +1 month -1 day"));

                    }
                    Cache::set('secondonesales', $secondonesales);
                    Cache::set('secondtwosales', $secondtwosales);
                    Cache::set('secondthreesales', $secondthreesales);




                } else {
                    $this->error('提交失败', null, $result);
                }

            } else {
                $this->error('提交失败', null, $result);

            }
        }
    }

    /**
     * 编辑
     */
    public function edit($ids = NULL)
    {

        $row = Db::name('second_sales_order')
            ->where('id', $ids)
            ->field('mortgage_registration_id,downpayment,service_charge,registry_registration_id')
            ->find();

        $images = Db::name('second_sales_order')
        ->where('id',$ids)
        ->field('id_cardimages,residence_bookletimages,housingimages,bank_cardimages,crime_undertakingimages,credit_reportimages,deposit_contractimages')
        ->find();

        foreach ($images as $k=>$v){
            if($images[$k]==''){
                $images[$k] = null;
            }
        }

        if ($row['mortgage_registration_id']) {
            $mortgage_registration = Db::name('mortgage_registration')
                ->where('id', $row['mortgage_registration_id'])
                ->field('contract_number,withholding_service,other_lines,collect_account')
                ->find();

            $row = array_merge($row, $mortgage_registration);
        }

        if($row['registry_registration_id']){
            $registry_registration = Db::name('registry_registration')
            ->where('id',$row['registry_registration_id'])
            ->field('id_card,registered_residence,marry_and_divorceimages,credit_reportimages,halfyear_bank_flowimages,guarantee,
            residence_permitimages,driving_license,residence_permit,renting_contract,company_contractimages,lift_listimages,
            deposit,truth_management_protocolimages,confidentiality_agreementimages,supplementary_contract_agreementimages,explain_situation,
            tianfu_bank_cardimages,crime_promise,buy_rent,customer_query,fengbang_rent,maximum_guarantee_contractimages,transfer_agreement')
            ->find();

            $row = array_merge($row, $registry_registration);
        }


        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            $orders = $this->request->post("order/a");
            $registration = $this->request->post("registration/a");
            $params['classification'] = 'used';
            $registration['classification'] = 'used';
            if ($params) {
                try {
                    if ($row['mortgage_registration_id']) {
                        Db::name('mortgage_registration')
                            ->where('id', $row['mortgage_registration_id'])
                            ->update($params);
                    } else {
                        Db::name('mortgage_registration')->insert($params);
                        $orders['mortgage_registration_id'] = Db::name('mortgage_registration')->getLastInsID();

                    }

                    if($row['registry_registration_id']){
                        Db::name('registry_registration')
                        ->where('id',$row['registry_registration_id'])
                        ->update($registration);
                    }else{
                        Db::name('registry_registration')->insert($registration);
                        $orders['registry_registration_id'] = Db::name('registry_registration')->getLastInsID();
                    }

                    $result = Db::name('second_sales_order')
                        ->where('id', $ids)
                        ->update($orders);


                    if ($result !== false) {
                        $this->success();
                    } else {
                        $this->error($row->getError());
                    }
                } catch (\think\exception\PDOException $e) {
                    $this->error($e->getMessage());
                } catch (\think\Exception $e) {
                    $this->error($e->getMessage());
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $this->view->assign([
            "row"=> $row,
            'images'=>$images
        ]);
        return $this->view->fetch();
    }

}
