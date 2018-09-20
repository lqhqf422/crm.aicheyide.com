<?php

namespace app\admin\controller\salesmanagement;

use app\admin\validate\rental\Order;
use app\common\controller\Backend;
use app\admin\model\PlanAcar as planAcarModel;
use app\admin\model\Models as modelsModel;
use app\admin\model\SalesOrder as salesOrderModel;
use fast\Tree;
use think\Db;
use think\Config;
use app\common\library\Email;
use think\Session;
use think\Cache;

/**
 * 订单列管理
 *
 * @icon fa fa-circle-o
 */
class Orderlisttabs extends Backend
{

    /**
     * Ordertabs模型对象
     * @var \app\admin\model\Ordertabs
     */
    protected $model = null;
    // protected $multiFields = 'fulldel';
    protected $noNeedRight = ['index', 'orderAcar', 'orderRental', 'orderSecond', 'orderFull', 'sedAudit', 'details', 'rentaldetails', 'seconddetails', 'fulldetails',
        'add', 'edit', 'planacar', 'planname', 'reserve', 'rentalplanname', 'rentaladd', 'rentaledit', 'rentaldel', 'control', 'setAudit', 'secondadd',

        'secondedit', 'fulladd', 'fulledit', 'submitCar', 'del', 'fulldel', 'seconddel', 'newreserve', 'newreserveedit', 'newcontroladd', 'newinformation', 'newinformtube',
        'secondreserve', 'secondaudit', 'page'];


    protected $dataLimitField = 'admin_id'; //数据关联字段,当前控制器对应的模型表中必须存在该字段
    protected $dataLimit = 'auth'; //表示显示当前自己和所有子级管理员的所有数据
    // protected  $dataLimit = 'false'; //表示显示当前自己和所有子级管理员的所有数据
    // protected $relationSearch = true;
    static protected $token = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = model('SalesOrder');
        $this->view->assign('genderdataList', $this->model->getGenderdataList());
        $this->view->assign('customerSourceList', $this->model->getCustomerSourceList());
        $this->view->assign('reviewTheDataList', $this->model->getReviewTheDataList());
    }

    public function index()
    {
//        $this->view->assign('total', model('SalesOrder')->count());
//        $this->view->assign('total1', model('RentalOrder')->count());
//        $this->view->assign('total2', model('SecondSalesOrder')->count());
//        $this->view->assign('total3', model('FullParmentOrder')->count());
        return $this->view->fetch();
    }

    /**
     * 以租代购（新车）
     * @return string|\think\response\Json
     * @throws \think\Exception
     */
    public function orderAcar()
    {
        $this->model = model('SalesOrder');
        //当前是否为关联查询
        $this->view->assign("genderdataList", $this->model->getGenderdataList());
        $this->view->assign("customerSourceList", $this->model->getCustomerSourceList());
        $this->view->assign("reviewTheDataList", $this->model->getReviewTheDataList());
        // pr(collection($this->model->with('planacar.models')->select())->toArray());die();


        //设置过滤方法
        $this->request->filter(['strip_tags']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams('username', true);
            $total = $this->model
                ->with(['planacar' => function ($query) {
                    $query->withField('payment,monthly,nperlist,margin,tail_section,gps');
                }, 'admin' => function ($query) {
                    $query->withField('nickname');
                }, 'models' => function ($query) {
                    $query->withField('name');
                }, 'newinventory' => function ($query) {
                    $query->withField('licensenumber');
                }])
                ->where($where)
                ->order($sort, $order)
                ->count();


            $list = $this->model
                ->with(['planacar' => function ($query) {
                    $query->withField('payment,monthly,nperlist,margin,tail_section,gps');
                }, 'admin' => function ($query) {
                    $query->withField('nickname');
                }, 'models' => function ($query) {
                    $query->withField('name');
                }, 'newinventory' => function ($query) {
                    $query->withField('licensenumber');
                }])
                ->where($where)
                ->order($sort, $order)
                ->limit($offset, $limit)
                ->select();
            foreach ($list as $k => $row) {
                $row->visible(['id', 'order_no', 'financial_name', 'username', 'createtime', 'phone', 'id_card', 'amount_collected', 'downpayment', 'review_the_data',
                    'id_cardimages', 'drivers_licenseimages', 'bank_cardimages', 'undertakingimages', 'accreditimages', 'faceimages', 'informationimages']);
                $row->visible(['planacar']);
                $row->getRelation('planacar')->visible(['payment', 'monthly', 'margin', 'nperlist', 'tail_section', 'gps',]);
                $row->visible(['admin']);
                $row->getRelation('admin')->visible(['nickname']);
                $row->visible(['models']);
                $row->getRelation('models')->visible(['name']);
                $row->visible(['newinventory']);
                $row->getRelation('newinventory')->visible(['licensenumber']);
            }


            $list = collection($list)->toArray();

            $result = array('total' => $total, "rows" => $list);
            return json($result);
        }

        return $this->view->fetch('index');

    }

    /**
     * 纯租订单
     * @return string|\think\response\Json
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function orderRental()
    {

        $this->model = new \app\admin\model\RentalOrder;
        $this->view->assign("genderdataList", $this->model->getGenderdataList());
        //设置过滤方法
        $this->request->filter(['strip_tags']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams('username', true);
            $total = $this->model
                ->with(['admin' => function ($query) {
                    $query->withField('nickname');
                }, 'models' => function ($query) {
                    $query->withField('name');
                }, 'carrentalmodelsinfo' => function ($query) {
                    $query->withField('licenseplatenumber,vin');
                }])
                ->where($where)
                ->order($sort, $order)
                ->count();

            $list = $this->model
                ->with(['admin' => function ($query) {
                    $query->withField('nickname');
                }, 'models' => function ($query) {
                    $query->withField('name');
                }, 'carrentalmodelsinfo' => function ($query) {
                    $query->withField('licenseplatenumber,vin');
                }])
                ->where($where)
                ->order($sort, $order)
                ->limit($offset, $limit)
                ->select();

            foreach ($list as $k => $v) {
                $v->visible(['id', 'order_no', 'username', 'phone', 'id_card', 'cash_pledge', 'rental_price', 'tenancy_term', 'genderdata', 'review_the_data', 'createtime', 'delivery_datetime']);
                $v->visible(['admin']);
                $v->getRelation('admin')->visible(['nickname']);
                $v->visible(['models']);
                $v->getRelation('models')->visible(['name']);
                $v->visible(['carrentalmodelsinfo']);
                $v->getRelation('carrentalmodelsinfo')->visible(['licenseplatenumber', 'vin']);
            }


            $list = collection($list)->toArray();


            $result = array("total" => $total, "rows" => $list);
            return json($result);
        }

        return $this->view->fetch('index');

    }

    /**
     * 以租代购（二手车）
     * @return string|\think\response\Json
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */

    public function orderSecond()
    {

        $this->model = new \app\admin\model\SecondSalesOrder;
        $this->view->assign("genderdataList", $this->model->getGenderdataList());
        $this->view->assign("customerSourceList", $this->model->getCustomerSourceList());
        $this->view->assign("buyInsurancedataList", $this->model->getBuyInsurancedataList());
        $this->view->assign("reviewTheDataList", $this->model->getReviewTheDataList());
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
                    $query->withField('newpayment,monthlypaymen,periods,totalprices,bond,tailmoney,licenseplatenumber');
                }, 'admin' => function ($query) {
                    $query->withField('nickname');
                }, 'models' => function ($query) {
                    $query->withField('name');
                }])
                ->where($where)
                ->order($sort, $order)
                ->count();


            $list = $this->model
                ->with(['plansecond' => function ($query) {
                    $query->withField('newpayment,monthlypaymen,periods,totalprices,bond,tailmoney,licenseplatenumber');
                }, 'admin' => function ($query) {
                    $query->withField('nickname');
                }, 'models' => function ($query) {
                    $query->withField('name');
                }])
                ->where($where)
                ->order($sort, $order)
                ->limit($offset, $limit)
                ->select();
            foreach ($list as $k => $row) {
                $row->visible(['id', 'order_no', 'username', 'genderdata', 'createtime', 'phone', 'id_card', 'amount_collected', 'downpayment', 'review_the_data',
                    'id_cardimages', 'drivers_licenseimages']);
                $row->visible(['plansecond']);
                $row->getRelation('plansecond')->visible(['newpayment', 'monthlypaymen', 'periods', 'totalprices', 'bond', 'tailmoney', 'licenseplatenumber']);
                $row->visible(['admin']);
                $row->getRelation('admin')->visible(['nickname']);
                $row->visible(['models']);
                $row->getRelation('models')->visible(['name']);
            }


            $list = collection($list)->toArray();

            $result = array('total' => $total, "rows" => $list);
            return json($result);
        }

        return $this->view->fetch('index');

    }

    /**全款 */
    public function orderFull()
    {
        $this->model = new \app\admin\model\FullParmentOrder;
        $this->view->assign("genderdataList", $this->model->getGenderdataList());
        //设置过滤方法
        $this->request->filter(['strip_tags']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams('username', true);
            $total = $this->model
                ->with(['planfull' => function ($query) {
                    $query->withField('full_total_price');
                }, 'admin' => function ($query) {
                    $query->withField('nickname');
                }, 'models' => function ($query) {
                    $query->withField('name');
                }])
                ->where($where)
                ->order($sort, $order)
                ->count();


            $list = $this->model
                ->with(['planfull' => function ($query) {
                    $query->withField('full_total_price');
                }, 'admin' => function ($query) {
                    $query->withField('nickname');
                }, 'models' => function ($query) {
                    $query->withField('name');
                }])
                ->where($where)
                ->order($sort, $order)
                ->limit($offset, $limit)
                ->select();

            foreach ($list as $k => $row) {
                $row->visible(['id', 'order_no', 'detailed_address', 'city', 'username', 'genderdata', 'createtime', 'phone', 'id_card', 'amount_collected', 'review_the_data']);
                $row->visible(['planfull']);
                $row->getRelation('planfull')->visible(['full_total_price']);
                $row->visible(['admin']);
                $row->getRelation('admin')->visible(['nickname']);
                $row->visible(['models']);
                $row->getRelation('models')->visible(['name']);
            }


            $list = collection($list)->toArray();

            $result = array('total' => $total, "rows" => $list);
            return json($result);
        }

        return $this->view->fetch();

    }

    /**
     * 根据方案id查询 车型名称，首付、月供等
     */
    public function getPlanAcarData($planId)
    {

        return Db::name('plan_acar')->alias('a')
            ->join('models b', 'a.models_id=b.id')
            ->join('financial_platform c', 'a.financial_platform_id= c.id')
            ->field('a.id,a.payment,a.monthly,a.nperlist,a.margin,a.tail_section,a.gps,a.note,
                        b.name as models_name')
            ->where('a.id', $planId)
            ->find();

    }

    /**提交内勤 */
    public function sedAudit()
    {
        $this->model = model('SalesOrder');

        if ($this->request->isAjax()) {
            $id = $this->request->post('id');

            $result = $this->model->isUpdate(true)->save(['id' => $id, 'review_the_data' => 'inhouse_handling']);
            //销售员
            $admin_name = DB::name('admin')->where('id', $this->auth->id)->value('nickname');

            $models_id = $this->model->where('id', $id)->value('models_id');

            $backoffice_id = $this->model->where('id', $id)->value('backoffice_id');
            //车型
            $models_name = DB::name('models')->where('id', $models_id)->value('name');
            //客户姓名
            $username = $this->model->where('id', $id)->value('username');

            if ($result !== false) {
                // //推送模板消息给风控
                // $sedArr = array(
                //     'touser' => 'oklZR1J5BGScztxioesdguVsuDoY',
                //     'template_id' => 'LGTN0xKp69odF_RkLjSmCltwWvCDK_5_PuAVLKvX0WQ', /**以租代购新车模板id */
                //     "topcolor" => "#FF0000",
                //     'url' => '',
                //     'data' => array(
                //         'first' =>array('value'=>'你有新客户资料待审核','color'=>'#FF5722') ,
                //         'keyword1' => array('value'=>$params['username'],'color'=>'#01AAED'),
                //         'keyword2' => array('value'=>'以租代购（新车）','color'=>'#01AAED'),
                //         'keyword3' => array('value'=>Session::get('admin')['nickname'],'color'=>'#01AAED'),
                //         'keyword4' =>array('value'=>date('Y年m月d日 H:i:s'),'color'=>'#01AAED') , 
                //         'remark' => array('value'=>'请前往系统进行查看操作')
                //     )
                // );
                // $sedResult= posts("https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=".self::$token,json_encode($sedArr));
                // if( $sedResult['errcode']==0 && $sedResult['errmsg'] =='ok'){
                //     $this->success('提交成功，请等待审核结果'); 
                // }else{
                //     $this->error('微信推送失败',null,$sedResult);
                // }

                $channel = "demo-sales";
                $content = "销售员" . $admin_name . "发出新车销售请求，请处理";
                goeary_push($channel, $content);

                $data = newinternal_inform($models_name, $admin_name, $username);
                // var_dump($data);
                // die;
                $email = new Email;
                // $receiver = "haoqifei@cdjycra.club";
                $receiver = DB::name('admin')->where('id', $backoffice_id)->value('email');
                $result_s = $email
                    ->to($receiver)
                    ->subject($data['subject'])
                    ->message($data['message'])
                    ->send();
                if ($result_s) {
                    $this->success();
                } else {
                    $this->error('邮箱发送失败');
                }


            } else {
                $this->error('提交失败', null, $result);

            }
        }
    }

    /**查看详细资料 */
    public function details($ids = null)
    {
        $this->model = model('SalesOrder');
        $row = $this->model->get($ids);
        if (!$row)
            $this->error(__('No Results were found'));
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds)) {
            if (!in_array($row[$this->dataLimitField], $adminIds)) {
                $this->error(__('You have no permission'));
            }
        }

        if ($row['admin_id']) {
            $row['sales_name'] = Db::name("admin")
                ->where("id", $row['admin_id'])
                ->value("nickname");

        }

        //定金合同（多图）
        $deposit_contractimages = $row['deposit_contractimages'] == ''? [] : explode(',', $row['deposit_contractimages']);
        foreach ($deposit_contractimages as $k => $v) {
            $deposit_contractimages[$k] = Config::get('upload')['cdnurl'] . $v;
        }

        //定金收据上传
        $deposit_receiptimages = $row['deposit_receiptimages'] == ''? [] : explode(',', $row['deposit_receiptimages']);
        foreach ($deposit_receiptimages as $k => $v) {
            $deposit_receiptimages[$k] = Config::get('upload')['cdnurl'] . $v;
        }
        //身份证正反面（多图）
        $id_cardimages = $row['id_cardimages'] == ''? [] : explode(',', $row['id_cardimages']);
        foreach ($id_cardimages as $k => $v) {
            $id_cardimages[$k] = Config::get('upload')['cdnurl'] . $v;
        }
        //驾照正副页（多图）
        $drivers_licenseimages = $row['drivers_licenseimages'] ==''? [] : explode(',', $row['drivers_licenseimages']);
        foreach ($drivers_licenseimages as $k => $v) {
            $drivers_licenseimages[$k] = Config::get('upload')['cdnurl'] . $v;
        }
        //户口簿【首页、主人页、本人页】
        $residence_bookletimages = $row['residence_bookletimages']==''? [] : explode(',', $row['residence_bookletimages']);
        foreach ($residence_bookletimages as $k => $v) {
            $residence_bookletimages[$k] = Config::get('upload')['cdnurl'] . $v;
        }
        //住房合同/房产证（多图）
        $housingimages = $row['housingimages'] == ''? [] : explode(',', $row['housingimages']);
        foreach ($housingimages as $k => $v) {
            $housingimages[$k] = Config::get('upload')['cdnurl'] . $v;
        }
        //银行卡照（可多图）
        $bank_cardimages = $row['bank_cardimages'] == ''? [] :  explode(',', $row['bank_cardimages']);
        foreach ($bank_cardimages as $k => $v) {
            $bank_cardimages[$k] = Config::get('upload')['cdnurl'] . $v;
        }
        //申请表（多图）
        $application_formimages = $row['application_formimages'] == ''? [] : explode(',', $row['application_formimages']);
        foreach ($application_formimages as $k => $v) {
            $application_formimages[$k] = Config::get('upload')['cdnurl'] . $v;
        }
        //通话清单（文件上传）
        $call_listfiles = $row['call_listfiles'] == ''? [] : explode(',', $row['call_listfiles']);
        foreach ($call_listfiles as $k => $v) {
            $call_listfiles[$k] = Config::get('upload')['cdnurl'] . $v;
        }
        /**不必填 */
        //保证金收据
        $new_car_marginimages = $row['new_car_marginimages'] == '' ? [] : explode(',', $row['new_car_marginimages']);
        if ($new_car_marginimages) {
            foreach ($new_car_marginimages as $k => $v) {
                $new_car_marginimages[$k] = Config::get('upload')['cdnurl'] . $v;
            }
        }
        $this->view->assign(
            [
                'row' => $row,
                'cdn' => Config::get('upload')['cdnurl'],
                'deposit_contractimages_arr' => $deposit_contractimages,
                'deposit_receiptimages_arr' => $deposit_receiptimages,
                'id_cardimages_arr' => $id_cardimages,
                'drivers_licenseimages_arr' => $drivers_licenseimages,
                'residence_bookletimages_arr' => $residence_bookletimages,
                'housingimages_arr' => $housingimages,
                'bank_cardimages_arr' => $bank_cardimages,
                'application_formimages_arr' => $application_formimages,
                'call_listfiles_arr' => $call_listfiles,
                'new_car_marginimages_arr' => $new_car_marginimages,
            ]
        );
        return $this->view->fetch();
    }


    /**
     * 根据方案id查询 车型名称，首付、月供等
     *
     *
     */
    public function getPlanCarRentalData($planId)
    {

        return Db::name('car_rental_models_info')->alias('a')
            ->join('models b', 'a.models_id=b.id')
            ->field('a.id,a.licenseplatenumber,
                        b.name as models_name')
            ->where('a.id', $planId)
            ->find();

    }

    /**查看纯租详细资料 */
    public function rentaldetails($ids = null)
    {
        $this->model = new \app\admin\model\RentalOrder;
        $row = $this->model->get($ids);
        if (!$row)
            $this->error(__('No Results were found'));
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds)) {
            if (!in_array($row[$this->dataLimitField], $adminIds)) {
                $this->error(__('You have no permission'));
            }
        }
        $row['plan'] = Db::name('sales_order')->alias('a')
            ->join('plan_acar b', 'a.plan_acar_name = b.id')
            ->join('models c', 'b.models_id=c.id');

        //身份证正反面（多图）
        $id_cardimages = $row['id_cardimages'] == ''? [] : explode(',', $row['id_cardimages']);

        //驾照正副页（多图）
        $drivers_licenseimages = $row['drivers_licenseimages'] == ''? [] : explode(',', $row['drivers_licenseimages']);

        //户口簿【首页、主人页、本人页】
        $residence_bookletimages = $row['residence_bookletimages'] == ''? [] : explode(',', $row['residence_bookletimages']);

        //通话清单（文件上传）
        $call_listfilesimages = explode(',', $row['call_listfilesimages']);

        $this->view->assign(
            [
                'row' => $row,
                'cdn' => Config::get('upload')['cdnurl'],
                'id_cardimages' => $id_cardimages,
                'drivers_licenseimages' => $drivers_licenseimages,
                'residence_bookletimages' => $residence_bookletimages,
                'call_listfilesimages' => $call_listfilesimages,
            ]
        );
        return $this->view->fetch();
    }


    /**
     * 根据方案id查询 车型名称，首付、月供等
     */
    public function getPlanCarSecondData($planId)
    {

        return Db::name('secondcar_rental_models_info')->alias('a')
            ->join('models b', 'a.models_id=b.id')
            ->field('a.id,a.licenseplatenumber,a.newpayment,a.monthlypaymen,a.periods,a.totalprices,
                        b.name as models_name')
            ->where('a.id', $planId)
            ->find();

    }

    /**查看二手车单详细资料 */
    public function seconddetails($ids = null)
    {
        $this->model = new \app\admin\model\SecondSalesOrder;
        $row = $this->model->get($ids);
        if (!$row)
            $this->error(__('No Results were found'));
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds)) {
            if (!in_array($row[$this->dataLimitField], $adminIds)) {
                $this->error(__('You have no permission'));
            }
        }

        if ($row['admin_id']) {
            $row['sales_name'] = Db::name("admin")
                ->where("id", $row['admin_id'])
                ->value("nickname");

        }

        //定金合同（多图）
        $deposit_contractimages = $row['deposit_contractimages'] == ''? [] : explode(',', $row['deposit_contractimages']);
        foreach ($deposit_contractimages as $k => $v) {
            $deposit_contractimages[$k] = Config::get('upload')['cdnurl'] . $v;
        }
        //定金收据上传
        $deposit_receiptimages = $row['deposit_receiptimages'] == ''? [] : explode(',', $row['deposit_receiptimages']);
        foreach ($deposit_receiptimages as $k => $v) {
            $deposit_receiptimages[$k] = Config::get('upload')['cdnurl'] . $v;
        }
        //身份证正反面（多图）
        $id_cardimages = $row['id_cardimages'] == ''? [] : explode(',', $row['id_cardimages']);
        foreach ($id_cardimages as $k => $v) {
            $id_cardimages[$k] = Config::get('upload')['cdnurl'] . $v;
        }
        //驾照正副页（多图）
        $drivers_licenseimages = $row['drivers_licenseimages'] == ''? [] : explode(',', $row['drivers_licenseimages']);
        foreach ($drivers_licenseimages as $k => $v) {
            $drivers_licenseimages[$k] = Config::get('upload')['cdnurl'] . $v;
        }
        //户口簿【首页、主人页、本人页】
        $residence_bookletimages = $row['residence_bookletimages'] == ''? [] : explode(',', $row['residence_bookletimages']);
        foreach ($residence_bookletimages as $k => $v) {
            $residence_bookletimages[$k] = Config::get('upload')['cdnurl'] . $v;
        }
        //住房合同/房产证（多图）
        $housingimages = $row['housingimages']==''? [] : explode(',', $row['housingimages']);
        foreach ($housingimages as $k => $v) {
            $housingimages[$k] = Config::get('upload')['cdnurl'] . $v;
        }
        //银行卡照（可多图）
        $bank_cardimages = $row['bank_cardimages'] == ''? [] : explode(',', $row['bank_cardimages']);
        foreach ($bank_cardimages as $k => $v) {
            $bank_cardimages[$k] = Config::get('upload')['cdnurl'] . $v;
        }
        //申请表（多图）
        $application_formimages = $row['application_formimages'] == ''? [] : explode(',', $row['application_formimages']);
        foreach ($application_formimages as $k => $v) {
            $application_formimages[$k] = Config::get('upload')['cdnurl'] . $v;
        }
        //通话清单（文件上传）
        $call_listfiles = explode(',', $row['call_listfiles']);
        foreach ($call_listfiles as $k => $v) {
            $call_listfiles[$k] = Config::get('upload')['cdnurl'] . $v;
        }
        /**不必填 */
        //保证金收据
        $new_car_marginimages = $row['new_car_marginimages'] == '' ? [] : explode(',', $row['new_car_marginimages']);
        if ($new_car_marginimages) {
            foreach ($new_car_marginimages as $k => $v) {
                $new_car_marginimages[$k] = Config::get('upload')['cdnurl'] . $v;
            }
        }
        $this->view->assign(
            [
                'row' => $row,
                'cdn' => Config::get('upload')['cdnurl'],
                'deposit_contractimages_arr' => $deposit_contractimages,
                'deposit_receiptimages_arr' => $deposit_receiptimages,
                'id_cardimages_arr' => $id_cardimages,
                'drivers_licenseimages_arr' => $drivers_licenseimages,
                'residence_bookletimages_arr' => $residence_bookletimages,
                'housingimages_arr' => $housingimages,
                'bank_cardimages_arr' => $bank_cardimages,
                'application_formimages_arr' => $application_formimages,
                'call_listfiles_arr' => $call_listfiles,
                'new_car_marginimages_arr' => $new_car_marginimages,
            ]
        );
        return $this->view->fetch();
    }


    /**
     * 根据方案id查询 车型名称，首付、月供等
     */
    public function getPlanCarFullData($planId)
    {

        return Db::name('plan_full')->alias('a')
            ->join('models b', 'a.models_id=b.id')
            ->field('a.id,a.full_total_price,
                        b.name as models_name')
            ->where('a.id', $planId)
            ->find();

    }

    /**查看全款单详细资料 */
    public function fulldetails($ids = null)
    {
        $this->model = new \app\admin\model\FullParmentOrder;
        $row = $this->model->get($ids);
        if (!$row)
            $this->error(__('No Results were found'));
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds)) {
            if (!in_array($row[$this->dataLimitField], $adminIds)) {
                $this->error(__('You have no permission'));
            }
        }
        if ($row['admin_id']) {
            $row['sales_name'] = Db::name("admin")
                ->where("id", $row['admin_id'])
                ->value("nickname");

        }



            //身份证正反面（多图）
            $id_cardimages = $row['id_cardimages'] == ''? [] :  explode(',', $row['id_cardimages']);
            foreach ($id_cardimages as $k => $v) {
                $id_cardimages[$k] = Config::get('upload')['cdnurl'] . $v;
            }


        //驾照正副页（多图）
        $drivers_licenseimages = $row['drivers_licenseimages'] == ''? [] : explode(',', $row['drivers_licenseimages']);
        foreach ($drivers_licenseimages as $k => $v) {
            $drivers_licenseimages[$k] = Config::get('upload')['cdnurl'] . $v;
        }
        //申请表（多图）
        $application_formimages = $row['application_formimages'] == ''? [] : explode(',', $row['application_formimages']);
        foreach ($application_formimages as $k => $v) {
            $application_formimages[$k] = Config::get('upload')['cdnurl'] . $v;
        }
        /**不必填 */
        //银行卡照（可多图）
        $bank_cardimages = $row['bank_cardimages'] == '' ? [] : explode(',', $row['bank_cardimages']);
        foreach ($bank_cardimages as $k => $v) {
            $bank_cardimages[$k] = Config::get('upload')['cdnurl'] . $v;
        }
        //通话清单（文件上传）
        $call_listfiles = $row['call_listfiles'] == '' ? [] : explode(',', $row['call_listfiles']);
        foreach ($call_listfiles as $k => $v) {
            $call_listfiles[$k] = Config::get('upload')['cdnurl'] . $v;
        }
        $this->view->assign(
            [
                'row' => $row,
                'cdn' => Config::get('upload')['cdnurl'],
                'id_cardimages_arr' => $id_cardimages,
                'drivers_licenseimages_arr' => $drivers_licenseimages,
                'application_formimages_arr' => $application_formimages,
                'bank_cardimages_arr' => $bank_cardimages,
                'call_listfiles_arr' => $call_listfiles,
            ]
        );
        return $this->view->fetch();
    }

    /**
     * 以租代购（新车）订车
     */
    public function newreserve()
    {
        $this->model = model('SalesOrder');
        //销售方案类别
        $category = DB::name('scheme_category')->field('id,name')->select();
        // pr($category);
        // die;

        $this->view->assign('category', $category);

        if ($this->request->isPost()) {
            $params = $this->request->post('row/a');
            //方案id
            $params['plan_acar_name'] = Session::get('plan_id');
            //方案重组名字
            $params['plan_name'] = Session::get('plan_name');
            //models_id
            $params['models_id'] = Session::get('models_id');
            //生成订单编号
            $params['order_no'] = date('Ymdhis');
//pr($params);die();
            $data = DB::name('plan_acar')->where('id', $params['plan_acar_name'])->field('payment,monthly,nperlist,gps,margin,tail_section')->find();

            $params['car_total_price'] = $data['payment'] + $data['monthly'] * $data['nperlist'];
            $params['downpayment'] = $data['payment'] + $data['monthly'] + $data['margin'] + $data['gps'];



            //把当前销售员所在的部门的内勤id 入库

            //message8=>销售一部顾问，message13=>内勤一部
            //message9=>销售二部顾问，message20=>内勤二部
            $adminRule = Session::get('admin')['rule_message'];  //测试完后需要把注释放开
            // $adminRule = 'message8'; //测试数据
            if ($adminRule == 'message8') {
                $params['backoffice_id'] = Db::name('admin')->where(['rule_message' => 'message13'])->find()['id'];
                // return true;
            }
            if ($adminRule == 'message9') {
                $params['backoffice_id'] = Db::name('admin')->where(['rule_message' => 'message20'])->find()['id'];
                // return true;
            }
            if ($adminRule == 'message23') {
                $params['backoffice_id'] = Db::name('admin')->where(['rule_message' => 'message24'])->find()['id'];
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

                        if(Session::has('appoint_sale')){
                              Db::name('plan_acar')
                              ->where('id',Session::get('plan_id'))
                              ->setField('acar_status',2);
                              
                              Session::delete('appoint_sale');
                        }

                        //如果添加成功,将状态改为提交审核
                        $result_s = $this->model->isUpdate(true)->save(['id' => $this->model->id, 'review_the_data' => 'send_to_internal']);
                        if ($result_s) {
                            $this->success();
                        } else {
                            $this->error('更新状态失败');
                        }
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
     *  以租代购（新车）预定编辑.
     */
    public function newreserveedit($ids = null)
    {
        $this->model = model('SalesOrder');

        $row = $this->model->get($ids);
        if ($row) {
            //关联订单于方案
            $result = Db::name('sales_order')->alias('a')
                ->join('plan_acar b', 'a.plan_acar_name = b.id')
                ->join('models c', 'c.id=b.models_id')
                ->field('b.id as plan_id,b.category_id as category_id,b.payment,b.monthly,b.nperlist,b.gps,b.margin,b.tail_section,c.name as models_name')
                ->where(['a.id' => $row['id']])
                ->find();
        }

        $result['downpayment'] = $result['payment'] + $result['monthly'] + $result['gps'] + $result['margin'];

        $category = DB::name('scheme_category')->field('id,name')->select();

        $this->view->assign('category', $category);

        $this->view->assign('result', $result);

        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds)) {
            if (!in_array($row[$this->dataLimitField], $adminIds)) {
                $this->error(__('You have no permission'));
            }
        }
        if ($this->request->isPost()) {
            $params = $this->request->post('row/a');

            if ($params) {
                try {
                    //是否采用模型验证
                    if ($this->modelValidate) {
                        $name = basename(str_replace('\\', '/', get_class($this->model)));
                        $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.edit' : true) : $this->modelValidate;
                        $row->validate($validate);
                    }
                    $result = $row->allowField(true)->save($params);
                    if ($result !== false) {
                        $this->success();
                    } else {
                        $this->error($row->getError());
                    }
                } catch (\think\exception\PDOException $e) {
                    $this->error($e->getMessage());
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $this->view->assign('row', $row);

        return $this->view->fetch('newreserveedit');

    }

    /**
     *  以租代购（新车）审核资料录入.
     */
    public function newcontroladd($ids = null)
    {
        $this->model = model('SalesOrder');

        $row = $this->model->get($ids);
        if ($row) {
            //关联订单于方案
            $result = Db::name('sales_order')->alias('a')
                ->join('plan_acar b', 'a.plan_acar_name = b.id')
                ->join('models c', 'c.id=b.models_id')
                ->field('b.id as plan_id,b.category_id as category_id,b.payment,b.monthly,b.nperlist,b.gps,b.margin,b.tail_section,c.name as models_name')
                ->where(['a.id' => $row['id']])
                ->find();
        }

        $result['downpayment'] = $result['payment'] + $result['monthly'] + $result['gps'] + $result['margin'];

        $category = DB::name('scheme_category')->field('id,name')->select();

        $this->view->assign('category', $category);

        $this->view->assign('result', $result);

        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds)) {
            if (!in_array($row[$this->dataLimitField], $adminIds)) {
                $this->error(__('You have no permission'));
            }
        }
        if ($this->request->isPost()) {
            $params = $this->request->post('row/a');

            if ($params) {
                try {
                    //是否采用模型验证
                    if ($this->modelValidate) {
                        $name = basename(str_replace('\\', '/', get_class($this->model)));
                        $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.edit' : true) : $this->modelValidate;
                        $row->validate($validate);
                    }
                    $result = $row->allowField(true)->save($params);
                    if ($result !== false) {
                        $this->success();
                    } else {
                        $this->error($row->getError());
                    }
                } catch (\think\exception\PDOException $e) {
                    $this->error($e->getMessage());
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $this->view->assign('row', $row);

        return $this->view->fetch('newcontroladd');

    }

    /**
     * 以租代购（新车）客户提车资料录入
     */
    public function newinformation($ids = null)
    {
        $this->model = model('SalesOrder');

        $row = $this->model->get($ids);
        if ($row) {
            //关联订单于方案
            $result = Db::name('sales_order')->alias('a')
                ->join('plan_acar b', 'a.plan_acar_name = b.id')
                ->join('models c', 'c.id=b.models_id')
                ->field('b.id as plan_id,b.category_id as category_id,b.payment,b.monthly,b.nperlist,b.gps,b.margin,b.tail_section,c.name as models_name')
                ->where(['a.id' => $row['id']])
                ->find();
        }

        $result['downpayment'] = $result['payment'] + $result['monthly'] + $result['gps'] + $result['margin'];

        $category = DB::name('scheme_category')->field('id,name')->select();

        $this->view->assign('category', $category);

        $this->view->assign('result', $result);

        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds)) {
            if (!in_array($row[$this->dataLimitField], $adminIds)) {
                $this->error(__('You have no permission'));
            }
        }
        if ($this->request->isPost()) {
            $params = $this->request->post('row/a');

            if ($params) {
                try {
                    //是否采用模型验证
                    if ($this->modelValidate) {
                        $name = basename(str_replace('\\', '/', get_class($this->model)));
                        $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.edit' : true) : $this->modelValidate;
                        $row->validate($validate);
                    }
                    $result_ss = $row->allowField(true)->save($params);
                    if ($result_ss !== false) {

                        $this->model->isUpdate(true)->save(['id' => $row['id'], 'review_the_data' => 'inform_the_tube']);

                        $this->success();

                    } else {
                        $this->error($row->getError());
                    }
                } catch (\think\exception\PDOException $e) {
                    $this->error($e->getMessage());
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $this->view->assign('row', $row);

        return $this->view->fetch('newinformation');

    }

    //资料已补全，提交车管进行提车
    public function newinformtube($ids = null)
    {
        $this->model = model('SalesOrder');

        if ($this->request->isAjax()) {
            $id = $this->request->post('id');

            $result = $this->model->isUpdate(true)->save(['id' => $id, 'review_the_data' => 'send_the_car']);
            //销售员
            $admin_name = DB::name('admin')->where('id', $this->auth->id)->value('nickname');

            $models_id = $this->model->where('id', $id)->value('models_id');

            $backoffice_id = $this->model->where('id', $id)->value('backoffice_id');
            //车型
            $models_name = DB::name('models')->where('id', $models_id)->value('name');
            //客户姓名
            $username = $this->model->where('id', $id)->value('username');

            if ($result !== false) {

                $channel = "demo-newsend_car";
                $content = "客户：" . $username . "对车型：" . $models_name . "的购买，资料已经补全，可以进行提车，请及时登陆后台进行处理 ";
                goeary_push($channel, $content);

                $data = newsend_car($models_name, $username);
                // var_dump($data);
                // die;
                $email = new Email;
                // $receiver = "haoqifei@cdjycra.club";
                $receiver = Db::name('admin')->where('rule_message', 'message14')->value('email');
                $result_s = $email
                    ->to($receiver)
                    ->subject($data['subject'])
                    ->message($data['message'])
                    ->send();
                if ($result_s) {
                    $this->success();
                } else {
                    $this->error('邮箱发送失败');
                }


            } else {
                $this->error('提交失败', null, $result);

            }
        }
    }

    //显示方案列表
    public function planacar()
    {
        if ($this->request->isAjax()) {


            $category_id = input("category_id");
            $category_id = json_decode($category_id, true);

            $result = DB::name('plan_acar')->alias('a')
                ->join('models b', 'b.id=a.models_id')
                ->join('scheme_category s', 'a.category_id = s.id')
                ->where([
                    'a.category_id'=> $category_id,
                    'a.acar_status'=> 1
                ])
//                ->where('sales_id', NULL)
//                ->whereOr('sales_id', $this->auth->id)
                ->field('a.id,a.payment,a.monthly,a.nperlist,a.margin,a.tail_section,a.gps,a.sales_id,a.note,b.name as models_name,b.id as models_id,s.category_note')
                ->order('id desc')
                ->select();
            foreach ($result as $k => $v) {

                $result[$k]['downpayment'] = $v['payment'] + $v['monthly'] + $v['margin'] + $v['gps'];
                $result[$k]['admin_id'] = $this->auth->id;
            }

            $result = json_encode($result);

            return $result;
        }
    }


    //分页
    public function page()
    {
        $category_id = input("category_id");
        $category_id = json_decode($category_id, true);

        $num = $this->request->post('num');

        $num = intval($num);
        $limit_number = $num * 15;


        $result = DB::name('plan_acar')->alias('a')
            ->join('models b', 'b.id=a.models_id')
            ->join('scheme_category s', 'a.category_id = s.id')
            ->where([
                'a.category_id'=> $category_id,
                'a.acar_status'=> 1
            ])
//            ->where('sales_id', NULL)
//            ->whereOr('sales_id', $this->auth->id)
            ->field('a.id,a.payment,a.monthly,a.nperlist,a.margin,a.tail_section,a.gps,a.note,a.sales_id,b.name as models_name,b.id as models_id,s.category_note')
            ->limit($limit_number, 15)
            ->order('id desc')
            ->select();

        foreach ($result as $k => $v) {

            $result[$k]['downpayment'] = $v['payment'] + $v['monthly'] + $v['margin'] + $v['gps'];
            $result[$k]['admin_id'] = $this->auth->id;
        }

        echo json_encode($result);


    }

    //方案组装
    public function planname()
    {
        if ($this->request->isAjax()) {


            $plan_id = input("id");
            $plan_id = json_decode($plan_id, true);
            $sql = Db::name('models')->alias('a')
                ->join('plan_acar b', 'b.models_id=a.id')
                ->field('a.name as models_name,b.id,b.payment,b.monthly,b.gps,b.tail_section,b.margin,b.category_id,b.models_id,b.sales_id')
                ->where(['b.ismenu' => 1, 'b.id' => $plan_id])
                ->find();
            $plan_name = $sql['models_name'] . '【首付' . $sql['payment'] . '，' . '月供' . $sql['monthly'] . '，' . 'GPS ' . $sql['gps'] . '，' . '尾款 ' . $sql['tail_section'] . '，' . '保证金' . $sql['margin'] . '】';

            Session::set('plan_id', $plan_id);
            Session::set('plan_name', $plan_name);
            Session::set('models_id', $sql['models_id']);
            if ($sql['sales_id']) {
                Session::set('appoint_sale', $sql['sales_id']);
            }
        }
    }

    /**
     *  以租代购（新车）提车资料编辑.
     */
    public function edit($ids = null, $posttype = null)
    {
        $this->model = model('SalesOrder');
        /**如果是点击的提交保证金按钮 */
        if ($posttype == 'the_guarantor') {
            $row = $this->model->get($ids);
            if ($row) {
                //关联订单于方案
                $result = Db::name('sales_order')->alias('a')
                    ->join('plan_acar b', 'a.plan_acar_name = b.id')
                    ->join('models c', 'c.id=b.models_id')
                    ->field('b.id as plan_id,b.category_id as category_id,b.payment,b.monthly,b.nperlist,b.gps,b.margin,b.tail_section,c.name as models_name')
                    ->where(['a.id' => $row['id']])
                    ->find();
            }

            $result['downpayment'] = $result['payment'] + $result['monthly'] + $result['gps'] + $result['margin'];

            $category = DB::name('scheme_category')->field('id,name')->select();

            $this->view->assign('category', $category);

            $this->view->assign('result', $result);

            if (!$row) {
                $this->error(__('No Results were found'));
            }
            $adminIds = $this->getDataLimitAdminIds();
            if (is_array($adminIds)) {
                if (!in_array($row[$this->dataLimitField], $adminIds)) {
                    $this->error(__('You have no permission'));
                }
            }
            if ($this->request->isPost()) {
                $params = $this->request->post('row/a');
                if ($params) {
                    try {
                        //是否采用模型验证
                        if ($this->modelValidate) {
                            $name = basename(str_replace('\\', '/', get_class($this->model)));
                            $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.edit' : true) : $this->modelValidate;
                            $row->validate($validate);
                        }
                        $result_ss = $row->allowField(true)->save($params);
                        if ($result_ss !== false) {
                            //如果添加成功,将状态改为提交审核
                            $result_s = $this->model->isUpdate(true)->save(['id' => $row['id'], 'review_the_data' => 'is_reviewing_true']);

                            $models_name = Db::name('models')->where('id', $row['models_id'])->value('name');

                            $channel = "demo-newdata_cash";
                            $content = "客户：" . $row['username'] . "对车型：" . $models_name . "的购买，保证金收据已上传，请及时登陆后台进行处理 ";
                            goeary_push($channel, $content);

                            $data = newdata_cash($models_name, $row['username']);
                            // var_dump($data);
                            // die;
                            $email = new Email;
                            // $receiver = "haoqifei@cdjycra.club";
                            $receiver = Db::name('admin')->where('rule_message', 'message7')->value('email');
                            $result_sss = $email
                                ->to($receiver)
                                ->subject($data['subject'])
                                ->message($data['message'])
                                ->send();
                            if ($result_sss) {
                                $this->success();
                            } else {
                                $this->error('邮箱发送失败');
                            }

                        } else {
                            $this->error($this->model->getError());
                        }
                    } catch (\think\exception\PDOException $e) {
                        $this->error($e->getMessage());
                    }
                }
                $this->error(__('Parameter %s can not be empty', ''));
            }
            //复制$row的值区分编辑和保证金收据

            $this->view->assign('row', $row);

            return $this->view->fetch('new_the_guarantor');
        }
        if ($posttype == 'edit') {
            /**点击的编辑按钮 */
            $row = $this->model->get($ids);
            if ($row) {
                //关联订单于方案
                $result = Db::name('sales_order')->alias('a')
                    ->join('plan_acar b', 'a.plan_acar_name = b.id')
                    ->join('models c', 'c.id=b.models_id')
                    ->field('b.id as plan_id,b.category_id as category_id,b.payment,b.monthly,b.nperlist,b.gps,b.margin,b.tail_section,c.name as models_name')
                    ->where(['a.id' => $row['id']])
                    ->find();
            }

            $result['downpayment'] = $result['payment'] + $result['monthly'] + $result['gps'] + $result['margin'];

            $category = DB::name('scheme_category')->field('id,name')->select();

            $this->view->assign('category', $category);

            $this->view->assign('result', $result);

            if (!$row) {
                $this->error(__('No Results were found'));
            }
            $adminIds = $this->getDataLimitAdminIds();
            if (is_array($adminIds)) {
                if (!in_array($row[$this->dataLimitField], $adminIds)) {
                    $this->error(__('You have no permission'));
                }
            }
            if ($this->request->isPost()) {
                $params = $this->request->post('row/a');
                // pr($params);
                // die;

                if ($params) {
                    try {
                        //是否采用模型验证
                        if ($this->modelValidate) {
                            $name = basename(str_replace('\\', '/', get_class($this->model)));
                            $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.edit' : true) : $this->modelValidate;
                            $row->validate($validate);
                        }
                        $result = $row->allowField(true)->save($params);
                        if ($result !== false) {
                            $this->success();
                        } else {
                            $this->error($row->getError());
                        }
                    } catch (\think\exception\PDOException $e) {
                        $this->error($e->getMessage());
                    }
                }
                $this->error(__('Parameter %s can not be empty', ''));
            }
            $this->view->assign('row', $row);

            return $this->view->fetch('newedit');
        }
    }

    /**
     *  租车.
     */

    //租车预定
    public function reserve()
    {
        $this->model = new \app\admin\model\RentalOrder;

        $result = DB::name('car_rental_models_info')->alias('a')
            ->join('models b', 'b.id=a.models_id')
            ->field('a.id,a.licenseplatenumber,a.kilometres,a.Parkingposition,a.companyaccount,a.cashpledge,a.threemonths,a.sixmonths,a.manysixmonths,a.note,b.name as models_name')
            ->where('a.status_data', '')
            ->select();

        $this->view->assign('result', $result);

        if ($this->request->isPost()) {
            $params = $this->request->post('row/a');
            // $ex = explode(',', $params['plan_acar_name']);


            $params['plan_car_rental_name'] = Session::get('plan_id');

            $params['car_rental_models_info_id'] = $params['plan_car_rental_name'];

            $params['plan_name'] = Session::get('plan_name');


            $models_id = DB::name('car_rental_models_info')->where('id', $params['plan_car_rental_name'])->value('models_id');

            $params['models_id'] = $models_id;
            // pr($params);die;

            //把当前销售员所在的部门的内勤id 入库

            //message8=>销售一部顾问，message13=>内勤一部
            //message9=>销售二部顾问，message20=>内勤二部
            $adminRule = Session::get('admin')['rule_message'];  //测试完后需要把注释放开
            // $adminRule = 'message8'; //测试数据
            if ($adminRule == 'message8') {
                $params['backoffice_id'] = Db::name('admin')->where(['rule_message' => 'message13'])->find()['id'];
                // return true;
            }
            if ($adminRule == 'message9') {
                $params['backoffice_id'] = Db::name('admin')->where(['rule_message' => 'message20'])->find()['id'];
                // return true;
            }
            if ($adminRule == 'message23') {
                $params['backoffice_id'] = Db::name('admin')->where(['rule_message' => 'message24'])->find()['id'];
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
                        // 如果添加成功,将状态改为车管正在审核
                        $result_s = $this->model->isUpdate(true)->save(['id' => $this->model->id, 'review_the_data' => 'is_reviewing_true']);

                        $admin_nickname = DB::name('admin')->alias('a')->join('rental_order b', 'b.admin_id=a.id')->where('b.id', $this->model->id)->value('a.nickname');

                        Session::set('rental_id', $this->model->id);

                        $this->model = model('car_rental_models_info');

                        $this->model->isUpdate(true)->save(['id' => Session::get('plan_id'), 'status_data' => 'is_reviewing']);

                        if ($result_s) {

                            $channel = "demo-reserve";
                            $content = "销售员" . $admin_nickname . "提交的租车单，请及时处理";
                            goeary_push($channel, $content);

                            $data = Db::name("rental_order")->where('id', Session::get('rental_id'))->find();

                            //车型
                            $models_name = DB::name('models')->where('id', $data['models_id'])->value('name');
                            //销售员

                            $admin_name = DB::name('admin')->where('id', $data['admin_id'])->value('nickname');
                            //客户姓名
                            $username = $data['username'];

                            $data = rentalcar_inform($models_name, $admin_name, $username);
                            // var_dump($data);
                            // die;
                            $email = new Email;
                            // $receiver = "haoqifei@cdjycra.club";
                            $receiver = DB::name('admin')->where('rule_message', 'message15')->value('email');

                            $result_ss = $email
                                ->to($receiver)
                                ->subject($data['subject'])
                                ->message($data['message'])
                                ->send();
                            if ($result_ss) {
                                $this->success();
                            } else {
                                $this->error('邮箱发送失败');
                            }

                        } else {
                            $this->error('更新状态失败');
                        }
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

    //方案组装
    public function rentalplanname()
    {
        if ($this->request->isAjax()) {


            $plan_id = input("id");
            $plan_id = json_decode($plan_id, true);

            $sql = Db::name('models')->alias('a')
                ->join('car_rental_models_info b', 'b.models_id=a.id')
                ->field('a.id,b.licenseplatenumber,b.companyaccount,b.cashpledge,b.threemonths,b.sixmonths,b.manysixmonths,b.note,a.name as models_name')
                ->where(['b.id' => $plan_id])
                ->find();

            $plan_name = $sql['models_name'] . '【押金' . $sql['cashpledge'] . '，' . '3月内租金（元）' . $sql['threemonths'] . '，' . '6月内租金（元） ' . $sql['sixmonths'] . '，' . '6月以上租金（元） ' . $sql['manysixmonths'] . '】';

            Session::set('plan_id', $plan_id);

            Session::set('plan_name', $plan_name);

        }
    }

    //租车客户信息的补全
    public function rentaladd($ids = null)
    {
        $this->model = new \app\admin\model\RentalOrder;
        $row = $this->model->get($ids);
        if ($row) {

            $result = DB::name('rental_order')->alias('a')
                ->join('car_rental_models_info b', 'b.id=a.plan_car_rental_name')
                ->join('models c', 'c.id=b.models_id')
                ->field('a.id,a.username,a.plan_car_rental_name,a.phone,a.deposit_receiptimages,a.down_payment,a.plan_name,b.licenseplatenumber,b.kilometres,b.Parkingposition,b.companyaccount,b.cashpledge,b.threemonths,b.sixmonths,b.manysixmonths,b.note,c.name as models_name')
                ->where('a.id', $row['id'])
                ->find();
        }

        $this->view->assign('result', $result);

        if ($this->request->isPost()) {

            $params = $this->request->post('row/a');
            //生成订单编号
            $params['order_no'] = date('Ymdhis');
            $params['plan_car_rental_name'] = $result['plan_car_rental_name'];
            $params['username'] = $result['username'];
            $params['phone'] = $result['phone'];
            $params['plan_name'] = $result['plan_name'];
            $params['deposit_receiptimages'] = $result['deposit_receiptimages'];
            $params['down_payment'] = $result['down_payment'];

            if ($params) {
                if ($this->dataLimit && $this->dataLimitFieldAutoFill) {
                    $params[$this->dataLimitField] = $this->auth->id;
                }
                try {
                    //是否采用模型验证
                    if ($this->modelValidate) {
                        $name = basename(str_replace('\\', '/', get_class($this->model)));
                        $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.edit' : true) : $this->modelValidate;
                        $row->validate($validate);
                    }
                    $result = $row->allowField(true)->save($params);
                    if ($result !== false) {
                        //如果添加成功,将状态改为暂不提交风控审核
                        $result_s = $row->isUpdate(true)->save(['id' => $row['id'], 'review_the_data' => 'is_reviewing_false']);
                        if ($result_s) {
                            $this->success();
                        } else {
                            $this->error('更新状态失败');
                        }
                    } else {
                        $this->error($row->getError());
                    }
                } catch (\think\exception\PDOException $e) {
                    $this->error($e->getMessage());
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }

        return $this->view->fetch();
    }

    // 租车订单修改
    public function rentaledit($ids = NULL)
    {
        $this->model = new \app\admin\model\RentalOrder;
        $row = $this->model->get($ids);
        if ($row) {

            $result = DB::name('rental_order')->alias('a')
                ->join('car_rental_models_info b', 'b.id=a.plan_car_rental_name')
                ->join('models c', 'c.id=b.models_id')
                ->field('a.id,a.username,a.plan_car_rental_name,a.phone,a.deposit_receiptimages,a.down_payment,a.plan_name,b.licenseplatenumber,b.kilometres,b.Parkingposition,b.companyaccount,b.cashpledge,b.threemonths,b.sixmonths,b.manysixmonths,b.note,c.name as models_name')
                ->where('a.id', $row['id'])
                ->find();
        }

        $this->view->assign('result', $result);

        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            if ($params) {
                try {
                    //是否采用模型验证
                    if ($this->modelValidate) {
                        $name = basename(str_replace('\\', '/', get_class($this->model)));
                        $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.edit' : true) : $this->modelValidate;
                        $row->validate($validate);
                    }
                    $result = $row->allowField(true)->save($params);
                    if ($result !== false) {
                        $this->success();
                    } else {
                        $this->error($row->getError());
                    }
                } catch (\think\exception\PDOException $e) {
                    $this->error($e->getMessage());
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $this->view->assign("row", $row);
        return $this->view->fetch();
    }

    /**
     * 租车删除
     */
    public function rentaldel($ids = "")
    {
        $this->model = new \app\admin\model\RentalOrder;
        if ($ids) {
            $pk = $this->model->getPk();
            $plan_car_rental_name = DB::name('rental_order')->where('id', $ids)->value('plan_car_rental_name');
            $adminIds = $this->getDataLimitAdminIds();
            if (is_array($adminIds)) {
                $count = $this->model->where($this->dataLimitField, 'in', $adminIds);
            }
            $list = $this->model->where($pk, 'in', $ids)->select();
            $count = 0;
            foreach ($list as $k => $v) {
                $count += $v->delete();
            }
            if ($count) {
                DB::name('car_rental_models_info')->where('id', $plan_car_rental_name)->setField('status_data', '');
                $this->success();
            } else {
                $this->error(__('No rows were deleted'));
            }
        }
        $this->error(__('Parameter %s can not be empty', 'ids'));
    }

    //提交风控审核
    public function control()
    {
        $this->model = new \app\admin\model\RentalOrder;

        if ($this->request->isAjax()) {
            $id = $this->request->post('id');

            $admin_nickname = DB::name('admin')->alias('a')->join('rental_order b', 'b.admin_id=a.id')->where('b.id', $id)->value('a.nickname');

            $result = DB::name('rental_order')->where('id', $id)->setField('review_the_data', 'is_reviewing_control');

            if ($result !== false) {

                $channel = "demo-rental_control";
                $content = "销售员" . $admin_nickname . "提交的租车单，请及时进行审核处理";
                goeary_push($channel, $content);


                $data = Db::name("rental_order")->where('id', $id)->find();
                //车型
                $models_name = DB::name('models')->where('id', $data['models_id'])->value('name');
                //销售员
                $admin_id = $data['admin_id'];
                $admin_name = DB::name('admin')->where('id', $data['admin_id'])->value('nickname');
                //客户姓名
                $username = $data['username'];

                $data = rentalcontrol_inform($models_name, $admin_name, $username);
                // var_dump($data);
                // die;
                $email = new Email;
                // $receiver = "haoqifei@cdjycra.club";
                $receiver = DB::name('admin')->where('rule_message', "message7")->value('email');
                $result_s = $email
                    ->to($receiver)
                    ->subject($data['subject'])
                    ->message($data['message'])
                    ->send();
                if ($result_s) {
                    $this->success();
                } else {
                    $this->error('邮箱发送失败');
                }


                // //推送模板消息给风控
                // $sedArr = array(
                //     'touser' => 'oklZR1J5BGScztxioesdguVsuDoY',
                //     'template_id' => 'LGTN0xKp69odF_RkLjSmCltwWvCDK_5_PuAVLKvX0WQ', /**以租代购新车模板id */
                //     "topcolor" => "#FF0000",
                //     'url' => '',
                //     'data' => array(
                //         'first' =>array('value'=>'你有新客户资料待审核','color'=>'#FF5722') ,
                //         'keyword1' => array('value'=>$params['username'],'color'=>'#01AAED'),
                //         'keyword2' => array('value'=>'以租代购（新车）','color'=>'#01AAED'),
                //         'keyword3' => array('value'=>Session::get('admin')['nickname'],'color'=>'#01AAED'),
                //         'keyword4' =>array('value'=>date('Y年m月d日 H:i:s'),'color'=>'#01AAED') , 
                //         'remark' => array('value'=>'请前往系统进行查看操作')
                //     )
                // );
                // $sedResult= posts("https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=".self::$token,json_encode($sedArr));
                // if( $sedResult['errcode']==0 && $sedResult['errmsg'] =='ok'){
                //     $this->success('提交成功，请等待审核结果'); 
                // }else{
                //     $this->error('微信推送失败',null,$sedResult);
                // }


            } else {
                $this->error('提交失败', null, $result);

            }
        }
    }

    /**
     * 以租代购（二手车）订车
     */
    public function secondreserve()
    {
        $this->model = new \app\admin\model\SecondSalesOrder;
        $this->view->assign("genderdataList", $this->model->getGenderdataList());
        $this->view->assign("customerSourceList", $this->model->getCustomerSourceList());
        $this->view->assign("buyInsurancedataList", $this->model->getBuyInsurancedataList());
        $this->view->assign("reviewTheDataList", $this->model->getReviewTheDataList());
        $newRes = array();
        //品牌
        $res = Db::name('brand')->field('id as brandid,name as brand_name,brand_logoimage')->select();
        // pr(Session::get('admin'));die;
        foreach ((array)$res as $key => $value) {
            $sql = Db::name('models')->alias('a')
                ->join('secondcar_rental_models_info b', 'b.models_id=a.id')
                ->field('a.id,a.name as models_name,b.id,b.newpayment,b.monthlypaymen,b.periods,b.totalprices,b.bond')
                ->where(['a.brand_id' => $value['brandid'], 'b.status_data' => '', 'b.shelfismenu' => 1])
                ->whereOr('sales_id', $this->auth->id)
                ->select();
            $newB = [];
            foreach ((array)$sql as $bValue) {
                $bValue['models_name'] = $bValue['models_name'] . '【新首付' . $bValue['newpayment'] . '，' . '月供' . $bValue['monthlypaymen'] . '，' . '期数（月）' . $bValue['periods'] . '，' . '总价（元）' . $bValue['totalprices'] . '】';
                $newB[] = $bValue;
            }
            $newRes[] = array(
                'brand_name' => $value['brand_name'],
                // 'brand_logoimage'=>$value['brand_logoimage'],
                'data' => $newB,
            );
        }
        $this->view->assign('newRes', $newRes);

        if ($this->request->isPost()) {
            $params = $this->request->post('row/a');
            $ex = explode(',', $params['plan_car_second_name']);

            $result = DB::name('secondcar_rental_models_info')->where('id', $params['plan_car_second_name'])->field('newpayment,monthlypaymen,periods,bond,models_id')->find();

            $params['car_total_price'] = $result['newpayment'] + $result['monthlypaymen'] * $result['periods'];
            $params['downpayment'] = $result['newpayment'] + $result['monthlypaymen'] + $result['bond'];

            $params['plan_car_second_name'] = reset($ex); //截取id
            $params['plan_name'] = addslashes(end($ex)); //
            //生成订单编号
            $params['order_no'] = date('Ymdhis');
            $params['models_id'] = $result['models_id'];
            //把当前销售员所在的部门的内勤id 入库

            //message8=>销售一部顾问，message13=>内勤一部
            //message9=>销售二部顾问，message20=>内勤二部
            $adminRule = Session::get('admin')['rule_message'];  //测试完后需要把注释放开
            // $adminRule = 'message8'; //测试数据
            if ($adminRule == 'message8') {
                $params['backoffice_id'] = Db::name('admin')->where(['rule_message' => 'message13'])->find()['id'];
                // return true;
            }
            if ($adminRule == 'message9') {
                $params['backoffice_id'] = Db::name('admin')->where(['rule_message' => 'message20'])->find()['id'];
                // return true;
            }
            if ($adminRule == 'message23') {
                $params['backoffice_id'] = Db::name('admin')->where(['rule_message' => 'message24'])->find()['id'];
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
                        //如果添加成功,将状态改为提交审核
                        $result_s = $this->model->isUpdate(true)->save(['id' => $this->model->id, 'review_the_data' => 'is_reviewing']);
                        if ($result_s) {
                            $this->success();
                        } else {
                            $this->error('更新状态失败');
                        }
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
     * 以租代购（二手车）审核资料上传
     */
    public function secondaudit($ids = "")
    {
        $this->model = new \app\admin\model\SecondSalesOrder;
        $this->view->assign("genderdataList", $this->model->getGenderdataList());
        $this->view->assign("customerSourceList", $this->model->getCustomerSourceList());
        $this->view->assign("buyInsurancedataList", $this->model->getBuyInsurancedataList());
        $this->view->assign("reviewTheDataList", $this->model->getReviewTheDataList());
        $row = $this->model->get($ids);
        if ($row) {
            //关联订单于方案
            $result = Db::name('second_sales_order')->alias('a')
                ->join('secondcar_rental_models_info b', 'a.plan_car_second_name = b.id')
                ->field('b.id as plan_id')
                ->where(['a.id' => $row['id']])
                ->find();
        }
        $newRes = array();
        //品牌
        $res = Db::name('brand')->field('id as brandid,name as brand_name,brand_logoimage')->select();
        // pr(Session::get('admin'));die;
        foreach ((array)$res as $key => $value) {
            $sql = Db::name('models')->alias('a')
                ->join('secondcar_rental_models_info b', 'b.models_id=a.id')
                ->field('a.name as models_name,b.id,b.newpayment,b.monthlypaymen,b.periods,b.totalprices')
                ->where(['a.brand_id' => $value['brandid'], 'b.shelfismenu' => 1])
                ->whereOr('sales_id', $this->auth->id)
                ->select();
            $newB = [];
            foreach ((array)$sql as $bValue) {
                $bValue['models_name'] = $bValue['models_name'] . '【新首付' . $bValue['newpayment'] . '，' . '月供' . $bValue['monthlypaymen'] . '，' . '期数（月）' . $bValue['periods'] . '，' . '总价（元）' . $bValue['totalprices'] . '】';
                $newB[] = $bValue;
            }
            $newRes[] = array(
                'brand_name' => $value['brand_name'],
                // 'brand_logoimage'=>$value['brand_logoimage'],
                'data' => $newB,
            );
        }
        // pr($newRes);die;
        $this->view->assign('newRes', $newRes);
        $this->view->assign('result', $result);

        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds)) {
            if (!in_array($row[$this->dataLimitField], $adminIds)) {
                $this->error(__('You have no permission'));
            }
        }
        if ($this->request->isPost()) {
            $params = $this->request->post('row/a');
            if ($params) {
                try {
                    //是否采用模型验证
                    if ($this->modelValidate) {
                        $name = basename(str_replace('\\', '/', get_class($this->model)));
                        $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.edit' : true) : $this->modelValidate;
                        $row->validate($validate);
                    }
                    $result = $row->allowField(true)->save($params);
                    if ($result !== false) {
                        $this->success();
                    } else {
                        $this->error($row->getError());
                    }
                } catch (\think\exception\PDOException $e) {
                    $this->error($e->getMessage());
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $this->view->assign('row', $row);

        return $this->view->fetch('secondaudit');
    }

    /**
     *  二手车.
     */
    /**提交内勤处理 */
    public function setAudit()
    {
        $this->model = new \app\admin\model\SecondSalesOrder;
        if ($this->request->isAjax()) {

            $id = $this->request->post('id');
            $result = $this->model->save(['review_the_data' => 'is_reviewing_true'], function ($query) use ($id) {
                $query->where('id', $id);
            });

            if ($result) {

                $this->model = model('secondcar_rental_models_info');

                $plan_car_second_name = DB::name('second_sales_order')->where('id', $id)->value('plan_car_second_name');

                $this->model->isUpdate(true)->save(['id' => $plan_car_second_name, 'status_data' => 'for_the_car']);

                $channel = "demo-second_backoffice";
                $content = "提交的二手车单，请尽快进行处理";
                goeary_push($channel, $content);

                $data = Db::name("second_sales_order")->where('id', $id)->find();
                //车型
                $models_name = DB::name('models')->where('id', $data['models_id'])->value('name');
                //内勤
                $backoffice_id = $data['backoffice_id'];

                $admin_name = DB::name('admin')->where('id', $data['admin_id'])->value('nickname');
                //客户姓名
                $username = $data['username'];

                $data = secondinternal_inform($models_name, $admin_name, $username);
                // var_dump($data);
                // die;
                $email = new Email;
                // $receiver = "haoqifei@cdjycra.club";
                $receiver = DB::name('admin')->where('id', $backoffice_id)->value('email');
                $result_s = $email
                    ->to($receiver)
                    ->subject($data['subject'])
                    ->message($data['message'])
                    ->send();
                if ($result_s) {
                    $this->success();
                } else {
                    $this->error('邮箱发送失败');
                }


            } else {

                $this->error();
            }

        }
    }

    /**
     *  二手车编辑.
     */
    public function secondedit($ids = null, $posttype = null)
    {
        $this->model = new \app\admin\model\SecondSalesOrder;
        $this->view->assign("genderdataList", $this->model->getGenderdataList());
        $this->view->assign("customerSourceList", $this->model->getCustomerSourceList());
        $this->view->assign("buyInsurancedataList", $this->model->getBuyInsurancedataList());
        $this->view->assign("reviewTheDataList", $this->model->getReviewTheDataList());
        /**如果是点击的提交保证金按钮 */
        if ($posttype == 'the_guarantor') {
            $row = $this->model->get($ids);
            if ($row) {
                //关联订单于方案
                $result = Db::name('second_sales_order')->alias('a')
                    ->join('secondcar_rental_models_info b', 'a.plan_car_second_name = b.id')
                    ->field('b.id as plan_id')
                    ->where(['a.id' => $row['id']])
                    ->find();
            }
            $newRes = array();
            //品牌
            $res = Db::name('brand')->field('id as brandid,name as brand_name,brand_logoimage')->select();
            // pr(Session::get('admin'));die;
            foreach ((array)$res as $key => $value) {
                $sql = Db::name('models')->alias('a')
                    ->join('secondcar_rental_models_info b', 'b.models_id=a.id')
                    ->field('a.name as models_name,b.id,b.newpayment,b.monthlypaymen,b.periods,b.totalprices')
                    ->where(['a.brand_id' => $value['brandid'], 'b.shelfismenu' => 1])
                    ->whereOr('sales_id', $this->auth->id)
                    ->select();
                $newB = [];
                foreach ((array)$sql as $bValue) {
                    $bValue['models_name'] = $bValue['models_name'] . '【新首付' . $bValue['newpayment'] . '，' . '月供' . $bValue['monthlypaymen'] . '，' . '期数（月）' . $bValue['periods'] . '，' . '总价（元）' . $bValue['totalprices'] . '】';
                    $newB[] = $bValue;
                }
                $newRes[] = array(
                    'brand_name' => $value['brand_name'],
                    // 'brand_logoimage'=>$value['brand_logoimage'],
                    'data' => $newB,
                );
            }
            // pr($newRes);die;
            $this->view->assign('newRes', $newRes);
            $this->view->assign('result', $result);

            if (!$row) {
                $this->error(__('No Results were found'));
            }
            $adminIds = $this->getDataLimitAdminIds();
            if (is_array($adminIds)) {
                if (!in_array($row[$this->dataLimitField], $adminIds)) {
                    $this->error(__('You have no permission'));
                }
            }
            if ($this->request->isPost()) {
                $params = $this->request->post('row/a');
                if ($params) {
                    try {
                        //是否采用模型验证
                        if ($this->modelValidate) {
                            $name = basename(str_replace('\\', '/', get_class($this->model)));
                            $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.edit' : true) : $this->modelValidate;
                            $row->validate($validate);
                        }
                        $result = $row->allowField(true)->save($params);
                        if ($result !== false) {
                            //如果添加成功,将状态改为提交审核
                            $result_s = $this->model->isUpdate(true)->save(['id' => $row['id'], 'review_the_data' => 'is_reviewing_true']);

                            $admin_nickname = DB::name('admin')->alias('a')->join('second_sales_order b', 'b.admin_id=a.id')->where('b.id', $row['id'])->value('a.nickname');


                            //请求地址
                            $uri = "http://goeasy.io/goeasy/publish";
                            // 参数数组
                            $data = [
                                'appkey' => "BC-04084660ffb34fd692a9bd1a40d7b6c2",
                                'channel' => "demo-second-the_guarantor",
                                'content' => "销售员" . $admin_nickname . "提交的二手单已经提供保证金，请及时处理"
                            ];
                            $ch = curl_init();
                            curl_setopt($ch, CURLOPT_URL, $uri);//地址
                            curl_setopt($ch, CURLOPT_POST, 1);//请求方式为post
                            curl_setopt($ch, CURLOPT_HEADER, 0);//不打印header信息
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);//返回结果转成字符串
                            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);//post传输的数据。
                            $return = curl_exec($ch);
                            curl_close($ch);
                            // print_r($return);

                            if ($result_s) {
                                $this->success();
                            } else {
                                $this->error('更新状态失败');
                            }
                        } else {
                            $this->error($this->model->getError());
                        }
                    } catch (\think\exception\PDOException $e) {
                        $this->error($e->getMessage());
                    }
                }
                $this->error(__('Parameter %s can not be empty', ''));
            }
            //复制$row的值区分编辑和保证金收据

            $this->view->assign('row', $row);

            return $this->view->fetch('secondthe_guarantor');
        }
        if ($posttype == 'edit') {
            /**点击的编辑按钮 */
            $row = $this->model->get($ids);
            if ($row) {
                //关联订单于方案
                $result = Db::name('second_sales_order')->alias('a')
                    ->join('secondcar_rental_models_info b', 'a.plan_car_second_name = b.id')
                    ->field('b.id as plan_id')
                    ->where(['a.id' => $row['id']])
                    ->find();
            }
            $newRes = array();
            //品牌
            $res = Db::name('brand')->field('id as brandid,name as brand_name,brand_logoimage')->select();
            // pr(Session::get('admin'));die;
            foreach ((array)$res as $key => $value) {
                $sql = Db::name('models')->alias('a')
                    ->join('secondcar_rental_models_info b', 'b.models_id=a.id')
                    ->field('a.name as models_name,b.id,b.newpayment,b.monthlypaymen,b.periods,b.totalprices')
                    ->where(['a.brand_id' => $value['brandid'], 'b.shelfismenu' => 1])
                    ->whereOr('sales_id', $this->auth->id)
                    ->select();
                $newB = [];
                foreach ((array)$sql as $bValue) {
                    $bValue['models_name'] = $bValue['models_name'] . '【新首付' . $bValue['newpayment'] . '，' . '月供' . $bValue['monthlypaymen'] . '，' . '期数（月）' . $bValue['periods'] . '，' . '总价（元）' . $bValue['totalprices'] . '】';
                    $newB[] = $bValue;
                }
                $newRes[] = array(
                    'brand_name' => $value['brand_name'],
                    // 'brand_logoimage'=>$value['brand_logoimage'],
                    'data' => $newB,
                );
            }
            // pr($newRes);die;
            $this->view->assign('newRes', $newRes);
            $this->view->assign('result', $result);

            if (!$row) {
                $this->error(__('No Results were found'));
            }
            $adminIds = $this->getDataLimitAdminIds();
            if (is_array($adminIds)) {
                if (!in_array($row[$this->dataLimitField], $adminIds)) {
                    $this->error(__('You have no permission'));
                }
            }
            if ($this->request->isPost()) {
                $params = $this->request->post('row/a');
                if ($params) {
                    try {
                        //是否采用模型验证
                        if ($this->modelValidate) {
                            $name = basename(str_replace('\\', '/', get_class($this->model)));
                            $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.edit' : true) : $this->modelValidate;
                            $row->validate($validate);
                        }
                        $result = $row->allowField(true)->save($params);
                        if ($result !== false) {
                            $this->success();
                        } else {
                            $this->error($row->getError());
                        }
                    } catch (\think\exception\PDOException $e) {
                        $this->error($e->getMessage());
                    }
                }
                $this->error(__('Parameter %s can not be empty', ''));
            }
            $this->view->assign('row', $row);

            return $this->view->fetch('secondedit');
        }
    }

    /**
     *  二手车添加.
     */
    public function secondadd()
    {
        $this->model = new \app\admin\model\SecondSalesOrder;
        $this->view->assign("genderdataList", $this->model->getGenderdataList());
        $this->view->assign("customerSourceList", $this->model->getCustomerSourceList());
        $this->view->assign("buyInsurancedataList", $this->model->getBuyInsurancedataList());
        $this->view->assign("reviewTheDataList", $this->model->getReviewTheDataList());
        $newRes = array();
        //品牌
        $res = Db::name('brand')->field('id as brandid,name as brand_name,brand_logoimage')->select();
        // pr(Session::get('admin'));die;
        foreach ((array)$res as $key => $value) {
            $sql = Db::name('models')->alias('a')
                ->join('secondcar_rental_models_info b', 'b.models_id=a.id')
                ->field('a.id,a.name as models_name,b.id,b.newpayment,b.monthlypaymen,b.periods,b.totalprices,b.bond')
                ->where(['a.brand_id' => $value['brandid'], 'b.shelfismenu' => 1])
                ->whereOr('sales_id', $this->auth->id)
                ->select();
            $newB = [];
            foreach ((array)$sql as $bValue) {
                $bValue['models_name'] = $bValue['models_name'] . '【新首付' . $bValue['newpayment'] . '，' . '月供' . $bValue['monthlypaymen'] . '，' . '期数（月）' . $bValue['periods'] . '，' . '总价（元）' . $bValue['totalprices'] . '】';
                $newB[] = $bValue;
            }
            $newRes[] = array(
                'brand_name' => $value['brand_name'],
                // 'brand_logoimage'=>$value['brand_logoimage'],
                'data' => $newB,
            );
        }
        $this->view->assign('newRes', $newRes);

        if ($this->request->isPost()) {
            $params = $this->request->post('row/a');
            $ex = explode(',', $params['plan_car_second_name']);

            $result = DB::name('secondcar_rental_models_info')->where('id', $params['plan_car_second_name'])->field('newpayment,monthlypaymen,periods,bond,models_id')->find();

            $params['car_total_price'] = $result['newpayment'] + $result['monthlypaymen'] * $result['periods'];
            $params['downpayment'] = $result['newpayment'] + $result['monthlypaymen'] + $result['bond'];

            $params['plan_car_second_name'] = reset($ex); //截取id
            $params['plan_name'] = addslashes(end($ex)); //
            //生成订单编号
            $params['order_no'] = date('Ymdhis');
            $params['models_id'] = $result['models_id'];
            //把当前销售员所在的部门的内勤id 入库

            //message8=>销售一部顾问，message13=>内勤一部
            //message9=>销售二部顾问，message20=>内勤二部
            $adminRule = Session::get('admin')['rule_message'];  //测试完后需要把注释放开
            // $adminRule = 'message8'; //测试数据
            if ($adminRule == 'message8') {
                $params['backoffice_id'] = Db::name('admin')->where(['rule_message' => 'message13'])->find()['id'];
                // return true;
            }
            if ($adminRule == 'message9') {
                $params['backoffice_id'] = Db::name('admin')->where(['rule_message' => 'message20'])->find()['id'];
                // return true;
            }
            if ($adminRule == 'message23') {
                $params['backoffice_id'] = Db::name('admin')->where(['rule_message' => 'message24'])->find()['id'];
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
                        //如果添加成功,将状态改为提交审核
                        $result_s = $this->model->isUpdate(true)->save(['id' => $this->model->id, 'review_the_data' => 'is_reviewing']);
                        if ($result_s) {
                            $this->success();
                        } else {
                            $this->error('更新状态失败');
                        }
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
     * 二手车删除
     */
    public function seconddel($ids = "")
    {
        $this->model = new \app\admin\model\SecondSalesOrder;
        if ($ids) {
            $pk = $this->model->getPk();
            $adminIds = $this->getDataLimitAdminIds();
            if (is_array($adminIds)) {
                $count = $this->model->where($this->dataLimitField, 'in', $adminIds);
            }
            $list = $this->model->where($pk, 'in', $ids)->select();
            $count = 0;
            foreach ($list as $k => $v) {
                $count += $v->delete();
            }
            if ($count) {
                $this->success();
            } else {
                $this->error(__('No rows were deleted'));
            }
        }
        $this->error(__('Parameter %s can not be empty', 'ids'));
    }

    /**
     *  全款车.
     */
    /**
     * 添加.
     */
    public function fulladd()
    {
        $this->model = new \app\admin\model\FullParmentOrder;
        $this->view->assign("genderdataList", $this->model->getGenderdataList());
        $this->view->assign('customerSourceList', $this->model->getCustomerSourceList());
        $newRes = array();
        //品牌
        $res = DB::name('brand')->field('id as brandid,name as brand_name,brand_logoimage')->select();
        // pr(Session::get('admin'));die;
        foreach ((array)$res as $key => $value) {
            $sql = Db::name('models')->alias('a')
                ->join('plan_full b', 'b.models_id=a.id')
                ->field('a.name as models_name,b.id,b.full_total_price')
                ->where(['a.brand_id' => $value['brandid'], 'b.ismenu' => 1])
                ->select();
            $newB = [];
            foreach ((array)$sql as $bValue) {
                $bValue['models_name'] = $bValue['models_name'] . '【全款总价' . $bValue['full_total_price'] . '】';
                $newB[] = $bValue;
            }

            $newRes[] = array(
                'brand_name' => $value['brand_name'],
                // 'brand_logoimage'=>$value['brand_logoimage'],
                'data' => $newB,
            );
        }
        $this->view->assign('newRes', $newRes);

        if ($this->request->isPost()) {
            $params = $this->request->post('row/a');

            if ($params['customer_source'] == "straight") {
                $params['introduce_name'] = null;
                $params['introduce_phone'] = null;
                $params['introduce_card'] = null;
            }

            $ex = explode(',', $params['plan_plan_full_name']);

            $result = DB::name('plan_full')->where('id', $params['plan_plan_full_name'])->field('models_id')->find();

            $params['plan_plan_full_name'] = reset($ex); //截取id
            $params['plan_name'] = addslashes(end($ex)); //
            //生成订单编号
            $params['order_no'] = date('Ymdhis');

            $params['models_id'] = $result['models_id'];
            //把当前销售员所在的部门的内勤id 入库

            //message8=>销售一部顾问，message13=>内勤一部
            //message9=>销售二部顾问，message20=>内勤二部
            $adminRule = Session::get('admin')['rule_message'];  //测试完后需要把注释放开
            // $adminRule = 'message8'; //测试数据
            if ($adminRule == 'message8') {
                $params['backoffice_id'] = Db::name('admin')->where(['rule_message' => 'message13'])->find()['id'];
                // return true;
            }
            if ($adminRule == 'message9') {
                $params['backoffice_id'] = Db::name('admin')->where(['rule_message' => 'message20'])->find()['id'];
                // return true;
            }
            if ($adminRule == 'message23') {
                $params['backoffice_id'] = Db::name('admin')->where(['rule_message' => 'message24'])->find()['id'];
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
                        //如果添加成功,将状态改为提交审核
                        $result_s = $this->model->isUpdate(true)->save(['id' => $this->model->id, 'review_the_data' => 'send_to_internal']);
                        if ($result_s) {
                            $this->success();
                        } else {
                            $this->error('更新状态失败');
                        }
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
     * 编辑.
     */
    public function fulledit($ids = NULL)
    {
        $this->model = new \app\admin\model\FullParmentOrder;
        $this->view->assign("genderdataList", $this->model->getGenderdataList());
        $this->view->assign('customerSourceList', $this->model->getCustomerSourceList());

        $row = $this->model->get($ids);

        //关联订单于方案
        $result = Db::name('full_parment_order')->alias('a')
            ->join('plan_full b', 'a.plan_plan_full_name = b.id')
            ->field('b.id as plan_id')
            ->where(['a.id' => $row['id']])
            ->find();

        $newRes = array();
        //品牌
        $res = DB::name('brand')->field('id as brandid,name as brand_name,brand_logoimage')->select();
        // pr(Session::get('admin'));die;
        foreach ((array)$res as $key => $value) {
            $sql = Db::name('models')->alias('a')
                ->join('plan_full b', 'b.models_id=a.id')
                ->field('a.name as models_name,b.id,b.full_total_price')
                ->where(['a.brand_id' => $value['brandid'], 'b.ismenu' => 1])
                ->select();
            $newB = [];
            foreach ((array)$sql as $bValue) {
                $bValue['models_name'] = $bValue['models_name'] . '【全款总价' . $bValue['full_total_price'] . '】';
                $newB[] = $bValue;
            }

            $newRes[] = array(
                'brand_name' => $value['brand_name'],
                // 'brand_logoimage'=>$value['brand_logoimage'],
                'data' => $newB,
            );
        }

        $this->view->assign(
            [
                "newRes" => $newRes,
                "result" => $result
            ]
        );

        if (!$row)
            $this->error(__('No Results were found'));
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds)) {
            if (!in_array($row[$this->dataLimitField], $adminIds)) {
                $this->error(__('You have no permission'));
            }
        }
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            $ex = explode(',', $params['plan_plan_full_name']);

            if ($params['customer_source'] == "straight") {
                $params['introduce_name'] = null;
                $params['introduce_phone'] = null;
                $params['introduce_card'] = null;
            }
            $result = DB::name('plan_full')->where('id', $params['plan_plan_full_name'])->field('models_id')->find();

            $params['plan_plan_full_name'] = reset($ex); //截取id
            $params['plan_name'] = addslashes(end($ex));
            $params['models_id'] = $result['models_id'];

            if ($params) {
                try {
                    //是否采用模型验证
                    if ($this->modelValidate) {
                        $name = basename(str_replace('\\', '/', get_class($this->model)));
                        $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.edit' : true) : $this->modelValidate;
                        $row->validate($validate);
                    }
                    $result = $row->allowField(true)->save($params);
                    if ($result !== false) {
                        $this->success();
                    } else {
                        $this->error($row->getError());
                    }
                } catch (\think\exception\PDOException $e) {
                    $this->error($e->getMessage());
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $this->view->assign("row", $row);
        return $this->view->fetch();
    }

    //提交内勤
    public function submitCar()
    {
        $this->model = new \app\admin\model\FullParmentOrder;
        if ($this->request->isAjax()) {
            $id = $this->request->post('id');

            $admin_nickname = DB::name('admin')->alias('a')->join('full_parment_order b', 'b.admin_id=a.id')->where('b.id', $id)->value('a.nickname');

            $result = $this->model->isUpdate(true)->save(['id' => $id, 'review_the_data' => 'inhouse_handling']);

            if ($result !== false) {

                $channel = "demo-full_backoffice";
                $content = "销售员" . $admin_nickname . "提交的全款车单，请尽快进行金额录入";
                goeary_push($channel, $content);

                $data = Db::name("full_parment_order")->where('id', $id)->find();
                //车型
                $models_name = DB::name('models')->where('id', $data['models_id'])->value('name');
                //销售员
                $backoffice_id = $data['backoffice_id'];
                $admin_name = DB::name('admin')->where('id', $data['admin_id'])->value('nickname');
                //客户姓名
                $username = $data['username'];

                $data = fullinternal_inform($models_name, $admin_name, $username);
                // var_dump($data);
                // die;
                $email = new Email;
                // $receiver = "haoqifei@cdjycra.club";
                $receiver = DB::name('admin')->where('id', $backoffice_id)->value('email');
                $result_s = $email
                    ->to($receiver)
                    ->subject($data['subject'])
                    ->message($data['message'])
                    ->send();
                if ($result_s) {
                    $this->success();
                } else {
                    $this->error('邮箱发送失败');
                }

            } else {
                $this->error('提交失败', null, $result);

            }
        }
    }

    /**
     * 全款删除
     */
    public function fulldel($ids = "")
    {
        $this->model = new \app\admin\model\FullParmentOrder;
        if ($ids) {
            $pk = $this->model->getPk();
            $adminIds = $this->getDataLimitAdminIds();
            if (is_array($adminIds)) {
                $count = $this->model->where($this->dataLimitField, 'in', $adminIds);
            }
            $list = $this->model->where($pk, 'in', $ids)->select();
            $count = 0;
            foreach ($list as $k => $v) {
                $count += $v->delete();
            }
            if ($count) {
                $this->success();
            } else {
                $this->error(__('No rows were deleted'));
            }
        }
        $this->error(__('Parameter %s can not be empty', 'ids'));
    }


}
