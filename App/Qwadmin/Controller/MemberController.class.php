<?php
/**
 *
 * 版权所有：新睿社区<qwadmin.010xr.com>
 * 作    者：素材水<hanchuan@010xr.com>
 * 日    期：2016-01-20
 * 版    本：1.0.0
 * 功能说明：用户控制器。
 *
 **/

namespace Qwadmin\Controller;

class MemberController extends ComController
{
    public function index()
    {

        $p = I('get.p', '1', 'intval');
        $field = I('get.field', '');
        $keyword = I('get.keyword', '', 'htmlentities');
        $order = I('get.order', 'DESC');
        $where = '';

        $prefix = C('DB_PREFIX');
        $order = $this->get_order($order, $prefix);
        if ($keyword <> '') {
            $where = $this->get_where($field, $keyword);
        }


        $user = M('member');
        $pagesize = 2;#每页数量
        // $offset = $pagesize * ($p - 1);//计算记录偏移量

        $count = $user->alias('m')
                ->join($prefix."auth_group_access aa on m.uid = aa.uid")
                ->join($prefix.'auth_group ag ON ag.id = aa.group_id')
                ->where($where)
                ->count();

        $list = $user->field("m.*,ag.id as gid,ag.title")
            ->alias('m')
            ->order($order)
            ->join("{$prefix}auth_group_access aa ON m.uid = aa.uid")
            ->join("{$prefix}auth_group ag ON ag.id = aa.group_id")
            ->where($where)
            // ->limit($offset . ',' . $pagesize)
            //使用page方法进行分页, 不用再计算$offset
            ->page($p.','.$pagesize)
            ->select();
        //$user->getLastSql();
        $page = new \Think\Page($count, $pagesize);
        $page = $page->show();
        $this->assign('list', $list);
        $this->assign('page', $page);
        $group = M('auth_group')->field('id,title')->select();
        $this->assign('group', $group);
        $this->display();
    }

    public function del()
    {

        $uids = 'POST' == REQUEST_METHOD ? I('post.uids', array()) : I('get.uid', '');

        //uid为1的禁止删除
        if ($uids == 1 or !$uids) {
            $this->error('参数错误！');
        }

        if (is_array($uids)) {
            foreach ($uids as $k => $v) {
                if ($v == 1) {//uid为1的禁止删除
                    unset($uids[$k]);
                }
                $uids[$k] = intval($v);
            }
            $uids = implode(',', $uids);
        }

        $map['uid'] = array('in', $uids);

        if (M('member')->where($map)->delete()) {
            M('auth_group_access')->where($map)->delete();
            addlog('删除会员UID:' . $uids);
            $this->success('删除成功');
        } else {
            $this->error('删除失败');
        }

    }

    public function edit()
    {
        $uid = I('get.uid', '', 'intval');

        if ($uid) {
            //$member = M('member')->where("uid='$uid'")->find();
            $prefix = C('DB_PREFIX');
            $user = M('member');
            //数据表要写全名, 加上表前缀
            $member = $user->field("{$prefix}member.*,{$prefix}auth_group_access.group_id")
                ->join("{$prefix}auth_group_access ON {$prefix}member.uid = {$prefix}auth_group_access.uid")
                ->where("{$prefix}member.uid=$uid")
                ->find();

        } else {
            $this->error('参数错误！');
        }

        $usergroup = M('auth_group')->field('id,title')->select();

        $this->assign('usergroup', $usergroup);

        $this->assign('member', $member);
        $this->display('form');
    }

    public function update($ajax = '')
    {

        if ($ajax == 'yes') {
            $uid = I('get.uid', 0, 'intval');
            $gid = I('get.gid', 0, 'intval');
            M('auth_group_access')->data(array('group_id' => $gid))->where("uid='$uid'")->save();
            die('1');
        }

        $uid = I('post.uid', '', 'intval');

        $user = I('post.user') ? htmlspecialchars(I('post.user', '', 'trim'), ENT_QUOTES) : '';

        // $group_id = isset($_POST['group_id']) ? intval($_POST['group_id']) : 0;
        $group_id = I('post.group_id', 0, 'intval');

        if (!$group_id) {
            $this->error('请选择用户组！');
        }
        // $password = isset($_POST['password']) ? trim($_POST['password']) : false;
        $password = I('post.password', '', 'trim');
        // 如果表单中不填写密码, 则不对密码更新
        if ($password) {
            $data['password'] = password($password);
        }

        $head = I('post.head', '', 'strip_tags');
        // $data['sex'] = isset($_POST['sex']) ? intval($_POST['sex']) : 0;
        $data['sex'] = I('post.sex', 0, 'intval');
        $data['head'] = $head ? $head : '';
        // $data['birthday'] = isset($_POST['birthday']) ? strtotime($_POST['birthday']) : 0;
        $data['birthday'] = I('post.birthday', 0, 'strtotime');

        // $data['phone'] = isset($_POST['phone']) ? trim($_POST['phone']) : '';
        $data['phone'] = I('post.phone', '', '/^\d{11}$/');
        if (!$data['phone']) {
            $this->error('手机号码格式不对');
        }
        $data['qq'] = isset($_POST['qq']) ? trim($_POST['qq']) : '';
        // $data['email'] = isset($_POST['email']) ? trim($_POST['email']) : '';
        //验证邮箱
        $data['email'] = I('post.email', '', 'validate_email');
        if (!$data['email']) {
            $this->error('邮箱格式不正确');
        }

        $data['t'] = time();

        if (!$uid) {
            if ($user == '') {
                $this->error('用户名称不能为空！');
            }
            if (!$password) {
                $this->error('用户密码不能为空！');
            }

            if (M('member')->where("user='$user'")->count()) {
                $this->error('用户名已被占用！');
            }
            $data['user'] = $user;

            $uid = M('member')->data($data)->add();

            M('auth_group_access')->data(array('group_id' => $group_id, 'uid' => $uid))->add();
            addlog('新增会员，会员UID：' . $uid);
        } else {
            $data['uid'] = $uid;
            // 修改用户信息
            M('member')->data($data)->save();

            M('auth_group_access')->data(array('group_id' => $group_id))->where("uid=$uid")->save();
            addlog('编辑会员信息，会员UID：' . $uid);


        }
        $this->success('操作成功！', U('/Member/index'));

    }


    public function add()
    {

        $usergroup = M('auth_group')->field('id,title')->select();

        $this->assign('usergroup', $usergroup);
        $this->display('form');
    }

    /**
     * @param $field
     * @param $prefix
     * @param $keyword
     * @return string
     */
    private function get_where($field, $keyword)
    {
        switch ($field){
            case 'user' :
                $where = "m.user LIKE '%$keyword%'";
                break;
            case 'phone' :
                $where = "m.phone LIKE '%$keyword%'";
                break;
            case 'qq' :
                $where = "m.qq LIKE '%$keyword%'";
                break;
            case 'email' :
                $where = "m.email LIKE '%$keyword%'";
                break;
        }

        return $where;
    }

    /**
     * @param $order
     * @param $prefix
     * @return string
     */
    private function get_order($order, $prefix)
    {
        if ($order == 'asc') {
            $order = "m.t asc";
        } elseif (($order == 'desc')) {
            $order = "m.t desc";
        } else {
            $order = "m.uid asc";
        }
        return $order;
    }
}
