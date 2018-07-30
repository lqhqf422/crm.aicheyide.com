<?php

namespace app\admin\controller\backoffice;

use app\common\controller\Backend;
use app\admin\controller\wechat\WechatMessage;
use app\admin\model\Admin as adminModel;
use think\Config;
use think\Db;


/**
 * 多表格示例
 *
 * @icon fa fa-table
 * @remark 当一个页面上存在多个Bootstrap-table时该如何控制按钮和表格
 */
class Custominfotabs extends Backend
{

    protected $model = null;
    static protected $token = null;

//    protected $multiFields = 'batch';
    public function _initialize()
    {
        parent::_initialize();
        self::$token= $this->getAccessToken();
    }

    /**
     * 查看
     */
    public function index()
    {
        $this->model = model('CustomerResource');
        $this->loadlang('backoffice/custominfotabs');
        $newTotal = $this->model
            ->with(['platform'])
            ->where(function ($query) {
                $query->where('backoffice_id', $this->auth->id)
                    ->where('sales_id', 'null')
                    ->where('platform_id', 'in', [2, 3, 4]);

            })
            ->count();


        $assignedTotal = $this->model
            ->with(['platform'])
            ->where(function ($query) {
                $query->where('backoffice_id', $this->auth->id)
                    ->where('sales_id', 'not null')
                    ->where('platform_id', 'in', [2, 3, 4]);
            })
            ->count();

        $this->view->assign([
            'newTotal' => $newTotal,
            'assignedTotal' => $assignedTotal
        ]);
        return $this->view->fetch();
    }

    //新客户
    public function newCustomer()
    {
        $this->model = model('CustomerResource');

        $this->view->assign("genderdataList", $this->model->getGenderdataList());
        //当前是否为关联查询
        $this->relationSearch = true;

        //设置过滤方法
        $this->request->filter(['strip_tags']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $total = $this->model
                ->with(['platform'])
                ->where($where)
                ->where(function ($query) {
                    $query->where('backoffice_id', $this->auth->id)
                        ->where('sales_id', 'null')
                        ->where('platform_id', 'in', [2, 3, 4]);

                })
                ->order($sort, $order)
                ->count();


            $list = $this->model
                ->with(['platform'])
                ->where($where)
                ->order($sort, $order)
                ->where(function ($query) {
                    $query->where('backoffice_id', $this->auth->id)
                        ->where('sales_id', 'null')
                        ->where('platform_id', 'in', [2, 3, 4]);

                })
                ->limit($offset, $limit)
                ->select();

            foreach ($list as $row) {

                $row->getRelation('platform')->visible(['name']);
            }
            $list = collection($list)->toArray();
            $result = array("total" => $total, "rows" => $list);

            return json($result);
        }

        return $this->view->fetch('index');
    }

    //已分配给销售的用户
    public function assignedCustomers()
    {
        $this->model = model('CustomerResource');

        $this->view->assign("genderdataList", $this->model->getGenderdataList());
        //当前是否为关联查询
        $this->relationSearch = true;
        //设置过滤方法
        $this->request->filter(['strip_tags']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $total = $this->model
                ->with(['platform'])
                ->where($where)
                ->where(function ($query) {
                    $query->where('backoffice_id', $this->auth->id)
                        ->where('sales_id', 'not null')
                        ->where('platform_id', 'in', [2, 3, 4]);
                })
                ->order($sort, $order)
                ->count();


            $list = $this->model
                ->with(['platform'])
                ->where($where)
                ->order($sort, $order)
                ->where(function ($query) {
                    $query->where('backoffice_id', $this->auth->id)
                        ->where('sales_id', 'not null')
                        ->where('platform_id', 'in', [2, 3, 4]);
                })
                ->limit($offset, $limit)
                ->select();

            foreach ($list as $row) {

                $row->getRelation('platform')->visible(['name']);
            }
            $list = collection($list)->toArray();
            $result = array("total" => $total, "rows" => $list);

            return json($result);
        }

        return $this->view->fetch('index');
    }

    //分配客户资源给销售
    //单个分配
    //销售  message8=>销售一部，message9=>销售二部
    public function admeasure($ids = NULL)
    {
        $this->model = model('CustomerResource');
        $id = $this->model->get(['id' => $ids]);

        $sale = Db::name('admin')->field('id,nickname,rule_message')->where(function ($query) {
            $query->where('rule_message', 'message8')->whereOr('rule_message', 'message9');
        })->select();
        $saleList = array();

        if (count($sale) > 0) {

            $firstCount = 0;
            $secondCount = 0;

            foreach ($sale as $k => $v) {
                switch ($v['rule_message']) {
                    case 'message8':
                        $saleList['message8'][$firstCount]['nickname'] = $v['nickname'];
                        $saleList['message8'][$firstCount]['id'] = $v['id'];
                        $firstCount++;
                        break;
                    case 'message9':
                        $saleList['message9'][$secondCount]['nickname'] = $v['nickname'];
                        $saleList['message9'][$secondCount]['id'] = $v['id'];
                        $secondCount++;
                        break;
                }
            }

        }

        if (empty($saleList['message8'])) {
            $saleList['message8'] = null;
        }

        if (empty($saleList['message9'])) {
            $saleList['message9'] = null;
        }

        $this->view->assign('firstSale', $saleList['message8']);
        $this->view->assign('secondSale', $saleList['message9']);


        $this->assignconfig('id', $id->id);

        if ($this->request->isPost()) {


            $params = $this->request->post('row/a');


            $result = $this->model->save(['sales_id' => $params['id'], 'distributsaletime' => time()], function ($query) use ($id) {
                $query->where('id', $id->id);
            });
            if ($result) {
                //这里开始调用微信推送
                //1、use  wechat/WechatMessage  这个类
                //2、实例化并传参
                //推送给内勤：温馨提示：你有新客户导入，请登陆系统查看。
                //  $sendmessage = new WechatMessage(Config::get('wechat')['APPID'],Config::get('wechat')['APPSECRET'], $token,'oklZR1J5BGScztxioesdguVsuDoY','测试测试5555');#;实例化
                //dump($sendmessage->sendMsgToAll());exit;

                $token = self::$token;
                $getAdminOpenid = adminModel::get(['id'=>$params['id']])->toArray();
                $openid = $getAdminOpenid['openid'];
                $sendmessage = new WechatMessage(Config::get('wechat')['APPID'],Config::get('wechat')['APPSECRET'], $token,$openid,'温馨提示：你有新客户导入，请登陆系统查看。');
                $msg = $sendmessage->sendMsgToAll();


                if($msg['errcode']==0){
                    $this->success();
                }else{
                    $this->error("消息推送失败");
                }


            } else {
                $this->error();
            }
        }

        return $this->view->fetch();

    }


    //分配客户资源给销售
    //批量分配
    //销售  message8=>销售一部，message9=>销售二部
    public function batch($ids = null)
    {


        $this->model = model('CustomerResource');


        $sale = Db::name('admin')->field('id,nickname,rule_message')->where(function ($query) {
            $query->where('rule_message', 'message8')->whereOr('rule_message', 'message9');
        })->select();
        $saleList = array();

        if (count($sale) > 0) {

            $firstCount = 0;
            $secondCount = 0;

            foreach ($sale as $k => $v) {
                switch ($v['rule_message']) {
                    case 'message8':
                        $saleList['message8'][$firstCount]['nickname'] = $v['nickname'];
                        $saleList['message8'][$firstCount]['id'] = $v['id'];
                        $firstCount++;
                        break;
                    case 'message9':
                        $saleList['message9'][$secondCount]['nickname'] = $v['nickname'];
                        $saleList['message9'][$secondCount]['id'] = $v['id'];
                        $secondCount++;
                        break;
                }
            }

        }

        if (empty($saleList['message8'])) {
            $saleList['message8'] = null;
        }

        if (empty($saleList['message9'])) {
            $saleList['message9'] = null;
        }

        $this->view->assign('firstSale', $saleList['message8']);
        $this->view->assign('secondSale', $saleList['message9']);

        if ($this->request->isPost()) {

            $params = $this->request->post('row/a');

            $result = $this->model->save(['sales_id' => $params['id'], 'distributsaletime' => time()], function ($query) use ($ids) {
                $query->where('id', 'in', $ids);
            });
            if ($result) {
                //这里开始调用微信推送
                //1、use  wechat/WechatMessage  这个类
                //2、实例化并传参
                //推送给内勤：温馨提示：你有新客户导入，请登陆系统查看。
                //  $sendmessage = new WechatMessage(Config::get('wechat')['APPID'],Config::get('wechat')['APPSECRET'], $token,'oklZR1J5BGScztxioesdguVsuDoY','测试测试5555');#;实例化
                //dump($sendmessage->sendMsgToAll());exit;
                $token = self::$token;
                $getAdminOpenid = adminModel::get(['id'=>$params['id']])->toArray();
                $openid = $getAdminOpenid['openid'];
                // // var_dump($openid);
                // // die;
                $sendmessage = new WechatMessage(Config::get('wechat')['APPID'],Config::get('wechat')['APPSECRET'], $token,$openid,'温馨提示：你有新客户导入，请登陆系统查看。');#;实例化

                $msg = $sendmessage->sendMsgToAll();
                // dump($msg);
                // die;
                if($msg['errcode'] == 0){
                    $this->success();
                }
                else {
                    $this->error('消息推送失败');
                }
            } else {

                $this->error();
            }
        }
        return $this->view->fetch();
    }


}
