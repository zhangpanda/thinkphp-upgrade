<?php
/**
 * 订单控制器 - ThinkPHP 3.2
 */
class OrderController extends Controller
{
    public function index()
    {
        $Order = M('Order');
        $where = array();
        $where['status'] = I('get.status', 1, 'intval');
        $where['user_id'] = session('user_id');

        $list = $Order->where($where)->order('create_time desc')->limit(20)->select();
        $count = $Order->where($where)->count();

        $page_config = C('PAGE_SIZE');

        $this->assign('list', $list);
        $this->assign('count', $count);
        $this->assign('page_config', $page_config);
        $this->display();
    }

    public function detail()
    {
        $id = I('get.id', 0, 'intval');
        $order = M('Order')->where(array('id' => $id))->find();

        if (empty($order)) {
            $this->error('订单不存在');
        }

        $items = M('OrderItem')->where(array('order_id' => $id))->select();
        $this->assign('order', $order);
        $this->assign('items', $items);
        $this->display();
    }

    public function create()
    {
        if (IS_POST) {
            $data = I('post.');
            $Order = D('Order');

            if ($Order->create($data)) {
                $order_id = $Order->add();
                if ($order_id) {
                    $this->success('下单成功', U('Order/detail', array('id' => $order_id)));
                } else {
                    $this->error('下单失败');
                }
            } else {
                $this->error($Order->getError());
            }
        } else {
            $address = M('Address')->where(array('user_id' => session('user_id')))->select();
            $this->assign('address', $address);
            $this->display();
        }
    }

    public function cancel()
    {
        $id = I('get.id', 0, 'intval');
        $result = M('Order')->where(array('id' => $id, 'status' => 1))->save(array('status' => 5));

        if ($result) {
            $this->success('取消成功', U('Order/index'));
        } else {
            $this->error('取消失败');
        }
    }

    public function pay()
    {
        $id = I('get.id', 0, 'intval');
        $order = M('Order')->find($id);

        if (empty($order) || $order['status'] != 1) {
            $this->error('订单状态异常');
        }

        $payment_type = C('DEFAULT_PAYMENT');
        $notify_url = U('Payment/notify');

        $this->assign('order', $order);
        $this->assign('payment_type', $payment_type);
        $this->assign('notify_url', $notify_url);
        $this->display();
    }

    public function export()
    {
        if (!IS_AJAX) {
            $this->error('非法请求');
        }

        $start = I('post.start_date');
        $end = I('post.end_date');

        $list = M('Order')->where(array(
            'create_time' => array('between', array($start, $end)),
        ))->select();

        $this->ajaxReturn(array('code' => 0, 'data' => $list));
    }
}
