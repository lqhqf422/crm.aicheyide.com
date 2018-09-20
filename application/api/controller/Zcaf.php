<?php
/**
 * Created by PhpStorm.
 * User: glen9
 * Date: 2018/9/20
 * Time: 17:12
 */

namespace app\api\controller;
use app\common\controller\Api;

class Zcaf extends Api
{

    //如果$noNeedLogin为空表示所有接口都需要登录才能请求
    //如果$noNeedRight为空表示所有接口都需要验证权限才能请求
    //如果接口已经设置无需登录,那也就无需鉴权了
    //
    // 无需登录的接口,*表示全部
    protected $noNeedLogin = ['test', 'test1'];
    // 无需鉴权的接口,*表示全部
    protected $noNeedRight = ['test2'];

    public function share(){

    }
}
