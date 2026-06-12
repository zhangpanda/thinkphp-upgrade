<?php
/**
 * 用户模型 - ThinkPHP 3.2
 */
class UserModel extends Model
{
    protected $tableName = 'user';

    protected $_validate = array(
        array('username', 'require', '用户名不能为空'),
        array('username', '3,20', '用户名长度3-20位', 0, 'length'),
        array('email', 'email', '邮箱格式不正确', 2),
        array('mobile', '/^1[3-9]\d{9}$/', '手机号格式不正确', 2, 'regex'),
    );

    protected $_auto = array(
        array('password', 'md5', 1, 'function'),
        array('create_time', 'time', 1, 'function'),
        array('update_time', 'time', 3, 'function'),
    );

    public function login($username, $password)
    {
        $where = array(
            'username' => $username,
            'password' => md5($password),
            'status' => 1,
        );

        $user = $this->where($where)->find();

        if ($user) {
            session('user_id', $user['id']);
            session('username', $user['username']);
            $this->where(array('id' => $user['id']))->save(array(
                'last_login_time' => time(),
                'last_login_ip' => get_client_ip(),
            ));
            return true;
        }

        return false;
    }

    public function getList($page = 1, $limit = 20)
    {
        $offset = ($page - 1) * $limit;
        return $this->where(array('status' => 1))
            ->order('id desc')
            ->limit($offset, $limit)
            ->select();
    }

    public function getUserCount()
    {
        return $this->where(array('status' => 1))->count();
    }
}
