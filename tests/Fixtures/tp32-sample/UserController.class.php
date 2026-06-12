<?php

class UserController extends Controller
{
    public function index()
    {
        $User = M('User');
        $list = $User->where('status=1')->select();
        $count = M('User')->count();

        $dbHost = C('DB_HOST');
        $dbName = C('DB_NAME');

        $this->assign('list', $list);
        $this->assign('count', $count);
        $this->display();
    }

    public function edit()
    {
        $id = I('get.id', 0, 'intval');
        $info = D('User')->find($id);
        $this->assign('info', $info);
        $this->display();
    }

    public function delete()
    {
        $id = I('get.id', 0, 'intval');
        if (M('User')->delete($id)) {
            $this->success('deleted', U('Admin/User/index'));
        }
    }
}
