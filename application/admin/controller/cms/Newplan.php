<?php

namespace app\admin\controller\cms;

use app\common\controller\Backend;
use app\common\library\Email;
//use app\common\model\Config;
use think\Config;
use think\Session;
use think\Db;

/**
 * 多表格示例
 *
 * @icon fa fa-table
 * @remark 当一个页面上存在多个Bootstrap-table时该如何控制按钮和表格
 */
class Newplan extends Backend
{

    protected $model = null;
    protected $multiFields = ['recommendismenu','flashviewismenu','specialismenu','subjectismenu'];

    protected $noNeedRight = ['index', 'firstedit','getBrandName','dragsort'];

    public function _initialize()
    {
        parent::_initialize();
        $this->model = model('PlanAcar');
    }

    /**
     * Notes:新车方案
     * User: glen9
     * Date: 2018/9/6
     * Time: 21:47
     * @return string|\think\response\Json
     * @throws \think\Exception
     */
    public function index()
    {
        $this->model = model('PlanAcar');
        $this->view->assign("nperlistList", $this->model->getNperlistList());
        $this->view->assign("ismenuList", $this->model->getIsmenuList());
        //当前是否为关联查询
        // $this->relationSearch = true;
        //设置过滤方法
        $this->request->filter(['strip_tags']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams('models.name', true);
            $total = $this->model
                ->with(['models' => function ($query) {
                    $query->withField('name');
                }, 'admin' => function ($query) {
                    $query->withField('nickname');
                }, 'schemecategory' => function ($query) {
                    $query->withField('name,category_note');
                },'financialplatform'=>function($query){
                    $query->withField('name');
                },'subject'=>function($query){
                    $query->withField('title,coverimages');
                },'label'=>function($query){
                    $query->withField('name,lableimages');
                },'companystore'=>function($query){
                    $query->withField('store_name');
                }])
                ->where($where)
                ->where('category_id', 'NEQ', '10')
                ->order($sort, $order)
                ->count();

            $list = $this->model
                ->with(['models' => function ($query) {
                    $query->withField('name');
                }, 'admin' => function ($query) {
                    $query->withField('nickname');
                }, 'schemecategory' => function ($query) {
                    $query->withField('name,category_note');
                },'financialplatform'=>function($query){
                    $query->withField('name');
                },'subject'=>function($query){
                    $query->withField('title,coverimages');
                },'label'=>function($query){
                    $query->withField('name,lableimages');
                },'companystore'=>function($query){
                    $query->withField('store_name');
                }])
                ->where($where)
                ->where('category_id', 'NEQ', '10')
                ->order($sort, $order)
                ->limit($offset, $limit)
                ->select();
           

            foreach ($list as $key => $row) {

                $row->visible(['id', 'payment', 'monthly', 'brand_name','brand_log', 'match_plan', 'nperlist', 'margin', 'tail_section', 'gps', 'note', 'createtime', 
                                'updatetime', 'category_id', 'recommendismenu', 'flashviewismenu','specialismenu','subjectismenu','specialimages','models_main_images','modelsimages','weigh']);
                $row->visible(['models']);
                $row->getRelation('models')->visible(['name']);
                $row->visible(['admin']);
                $row->getRelation('admin')->visible(['nickname']);
                $row->visible(['schemecategory']);
                $row->getRelation('schemecategory')->visible(['name', 'category_note']);
                $row->visible(['financialplatform']);
                $row->getRelation('financialplatform')->visible(['name']);
                $row->visible(['subject']);
                $row->getRelation('subject')->visible(['title', 'coverimages']);
                $row->visible(['label']);
                $row->getRelation('label')->visible(['name', 'lableimages']);
                $row->visible(['companystore']);
                $row->getRelation('companystore')->visible(['store_name']);
                $list[$key]['brand_name'] = array_keys(self::getBrandName($row['id'])); //获取品牌
                $list[$key]['brand_log'] =Config::get('upload')['cdnurl'].array_values(self::getBrandName($row['id']))[0]; //获取logo图片


            }
            $list = collection($list)->toArray();
            $result = array("total" => $total, "rows" => $list);
            Session::set('row', $list);
            return json($result);
        }

        return $this->view->fetch('index');
    }

    /**
     * 关联品牌名称
     * @param $plan_id 方案id
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function getBrandName($plan_id = null)
    {
        return Db::name('plan_acar')->alias('a')
            ->join('models b', 'a.models_id = b.id')
            ->join('brand c', 'b.brand_id=c.id')
            ->where('a.id', $plan_id)
            ->column('c.name,c.brand_logoimage');
    }

    /**
     * 新车方案编辑
     */
    public function edit($ids = NULL)
    {
        $this->model = model('PlanAcar');
        $row = $this->model->get($ids);

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
            //专题
            $plan_id = Db::name('cms_subject')->where('id',$params['subject_id'])->field('plan_id')->find();
            $plan_id = json_decode($plan_id['plan_id'], true); 
            
            if($plan_id){
                if(!in_array($ids,$plan_id['plan_id'])){
                    array_push($plan_id['plan_id'],$ids);
                }
            }
            else{
                $plan_id['plan_id'][] = $ids;
            }

            $plan_id = json_encode($plan_id);        

            $result_s = Db::name('cms_subject')->where('id', $params['subject_id'])->update(['plan_id' =>$plan_id]);

            $params['subjectismenu'] = '1';
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
                } catch (\think\Exception $e) {
                    $this->error($e->getMessage());
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $this->view->assign([
            "row" => $row,
            'subject' => $this->getSubject(),
            'label'  => $this->getLabel(),
            'store'  => $this->getStore()
        ]);

        return $this->view->fetch();
    }

    //专题标题
    public function getSubject()
    {
        $result = Db::name('cms_subject')->select();

        return $result;
    }

    //标签名称
    public function getLabel()
    {
        $result = Db::name('cms_label')->select();

        return $result;
    }

    //标签名称
    public function selectpage()
    {
        $result = Db::name('cms_label')->field('name')->select();
        
        foreach($result as $k => $v){
            $data[] = $v['name'];
        }

        return $data;
    }

    //门店名称
    public function getStore()
    {
        $result = Db::name('cms_company_store')->select();

        return $result;
    }

    /**
     * 批量更新
     */
    public function multi($ids = "")
    {
        $ids = $ids ? $ids : $this->request->param("ids");
        if ($ids) {
            if ($this->request->has('params')) {
                parse_str($this->request->post("params"), $values);
                $values = array_intersect_key($values, array_flip(is_array($this->multiFields) ? $this->multiFields : explode(',', $this->multiFields)));
                if ($values) {
                    $data = $this->model->where('id',$ids)->field('subject_id')->find();
                    if($values['subjectismenu'] == '0'){
                        
                        $plan_id = Db::name('cms_subject')->where('id',$data['subject_id'])->field('plan_id')->find();
                        $plan_id = json_decode($plan_id['plan_id'], true); 
                        // pr($plan_id);
                        // die;
                        if(in_array($ids,$plan_id['plan_id'])){

                            foreach ($plan_id['plan_id'] as $k=>$v){
                                if ($v === $ids)
                                    unset($plan_id['plan_id'][$k]);
                            }

                            $plan_id = json_encode($plan_id);        
                            // pr($plan_id);
                            // die;
                            $result_s = Db::name('cms_subject')->where('id', $data['subject_id'])->setField('plan_id', $plan_id);
                                        
                        }
                    }
                    else{
                        //专题
                        $plan_id = Db::name('cms_subject')->where('id',$data['subject_id'])->field('plan_id')->find();
                        $plan_id = json_decode($plan_id['plan_id'], true); 
                        
                        if($plan_id){
                            if(!in_array($ids,$plan_id['plan_id'])){
                                array_push($plan_id['plan_id'],$ids);
                            }
                        }
                        else{
                            $plan_id['plan_id'][] = $ids;
                        }

                        $plan_id = json_encode($plan_id);        

                        $result_s = Db::name('cms_subject')->where('id', $data['subject_id'])->setField('plan_id', $plan_id);
                    }
                    $adminIds = $this->getDataLimitAdminIds();
                    if (is_array($adminIds)) {
                        $this->model->where($this->dataLimitField, 'in', $adminIds);
                    }
                    $count = 0;
                    $list = $this->model->where($this->model->getPk(), 'in', $ids)->select();
                    foreach ($list as $index => $item) {
                        $count += $item->allowField(true)->isUpdate(true)->save($values);
                    }
                    if ($count) {
                        $this->success();
                    } else {
                        $this->error(__('No rows were updated'));
                    }
                } else {
                    $this->error(__('You have no permission'));
                }
            }
        }
        $this->error(__('Parameter %s can not be empty', 'ids'));
    }
    
    //拖拽排序---改变权重
    public function dragsort($ids = NULL)
    {
        pr($ids);
        $data = Session::get('row');
        foreach($data as $k => $v){
            $data_ids[] = $data[$k]['id'];
        }
        pr($data_ids);
        die;
        $row = $this->model->get($ids);
    }
    
}