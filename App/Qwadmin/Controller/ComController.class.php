<?php
/**
 *
 * 版权所有：新睿社区<qwadmin.010xr.com>
 * 作    者：素材水<hanchuan@010xr.com>
 * 日    期：2015-09-17
 * 版    本：1.0.0
 * 功能说明：后台公用控制器。
 *
 **/

namespace Qwadmin\Controller;

use Common\Controller\BaseController;
use Think\Auth;


class ComController extends BaseController
{
    public $USER;

    public function _initialize()
    {
        C(setting());
        if (!C("COOKIE_SALT")) {
            $this->error('请配置COOKIE_SALT信息');
        }
        /**
         * 不需要登录控制器
         */
        if (in_array(CONTROLLER_NAME, array("Login"))) {
            return true;
        }
        // dd(CONTROLLER_NAME);
        //检测是否登录
        $flag =  $this->check_login();
        $url = U("login/index");

        if (!$flag) {
            header("Location: {$url}");
            exit(0);
        }
        // $m = M();
        $prefix = C('DB_PREFIX');

        $UID = $this->USER['uid'];
        // $userinfo = $m->query("SELECT * FROM {$prefix}auth_group g left join {$prefix}auth_group_access a on g.id=a.group_id where a.uid=$UID");
        $userauth = M('auth_group')
                    ->alias('g')
                    ->join("{$prefix}auth_group_access a on g.id = a.group_id" )
                    ->where("a.uid = $UID")->find();
        // dd($userauth);
        $Auth = new Auth();

        $allow_controller_name = array('Upload');//放行控制器名称

        $allow_action_name = array();//放行函数名称

       // CONTROLLER_NAME . '/' . ACTION_NAME 获取当前页码的控制器名和方法名
        if ($userauth['group_id'] != 1 && !$Auth->check(CONTROLLER_NAME . '/' . ACTION_NAME,
                $UID) && !in_array(CONTROLLER_NAME, $allow_controller_name) && !in_array(ACTION_NAME,
                $allow_action_name)
        ) {
            $this->error('没有权限访问本页面!');
        }

        $user = member(intval($UID));

        $this->assign('user', $user);


        $current_action_name = ACTION_NAME == 'edit' ? "index" : ACTION_NAME;
        // $current = M()->query("SELECT s.id,s.title,s.name,s.tips,s.pid,p.pid as ppid,p.title as ptitle FROM {$prefix}auth_rule s left join {$prefix}auth_rule p on p.id=s.pid where s.name='" . CONTROLLER_NAME . '/' . $current_action_name . "'");
        //固定按照 field、alias、join、where、order、limit 、select ；
        $current = M('auth_rule')
                    ->field('s.id,s.title,s.name,s.tips,s.pid,p.pid as ppid,p.title as ptitle')
                    ->alias('s')
                    ->join("{$prefix}auth_rule p on p.id=s.pid")
                    ->where("s.name='".  CONTROLLER_NAME . '/' . $current_action_name ."'")
                    ->find();


        // $this->assign('current', $current[0]);
        $this->assign('current', $current);


        $menu_access_id = $userauth['rules'];

        if ($userauth['group_id'] != 1) {

            $menu_where = "AND id in ($menu_access_id)";

        } else {

            $menu_where = '';
        }
        // 显示符合权限的类别
        $menu = M('auth_rule')
                ->field('id,title,pid,name,icon')
                ->where("islink=1 $menu_where ")
                ->order('o ASC')
                ->select();


        $menu = $this->getMenu($menu);
        // dd($menu);
        $this->assign('menu', $menu);

    }


    protected function getMenu($items, $id = 'id', $pid = 'pid', $son = 'children')
    {
        $tree = array();    //分类目录
        $tmpMap = array();
        //修复父类设置islink=0，但是子类仍然显示的bug @感谢linshaoneng提供代码
        foreach( $items as $item ){
            // 获取所有一级目录的id
            if( $item['pid']==0 ){
                $father_ids[] = $item['id'];
            }
        }
        //----
        // 设置临时的类别数组, 将所有目录按id存放到新数组,并以其id为键值, 成一维数组
        foreach ($items as $item) {
            $tmpMap[$item[$id]] = $item;
        }
        // return $tmpMap;
        foreach ($items as $item) {
            //修复父类设置islink=0，但是子类仍然显示的bug by shaoneng @感谢linshaoneng提供代码
            // 判断是否是二级目录并且其对应的一级目录是激活的, 如果不是, 则跳出循环, 进入下一次循环
            if( $item['pid']<>0 && !in_array( $item['pid'], $father_ids )){
                continue;
            }
            //----
            /**
             * isset($tmpMap[$item[$pid]])检查$tmpMap数组中, 是否有下标为$item[$pid]的数据,
             * 如果有的话, 为这个数据添加$son字段为子数组, 并为数组赋值
             * $tmpMap[$item[$id]]获取每个目录的数据
             */

            if (isset($tmpMap[$item[$pid]])) {
                $tmpMap[$item[$pid]][$son][] = &$tmpMap[$item[$id]];    //将值赋值给一级目录下的children数组
            } else {
                $tree[] = &$tmpMap[$item[$id]];
            }

        }

        return $tree;
    }

    public function check_login(){
        session_start();
        $flag = false;
        $salt = C("COOKIE_SALT");
        $ip = get_client_ip();
        $ua = $_SERVER['HTTP_USER_AGENT'];
        $auth = cookie('auth');
        $uid = session('uid');
        if ($uid) {
            $user = M('member')->where(array('uid' => $uid))->find();

            if ($user) {
                if ($auth ==  password($uid.$user['user'].$ip.$ua.$salt)) {
                    $flag = true;
                    $this->USER = $user;
                }
            }
        }
        return $flag;
    }
}