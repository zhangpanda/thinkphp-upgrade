<?php
/**
 * 商品控制器 - ThinkPHP 3.2
 */
class GoodsController extends Controller
{
    public function index()
    {
        $cate_id = I('get.cate_id', 0, 'intval');
        $keyword = I('get.keyword', '', 'htmlspecialchars');

        $where = array('status' => 1);
        if ($cate_id > 0) {
            $where['cate_id'] = $cate_id;
        }
        if ($keyword) {
            $where['title'] = array('like', '%' . $keyword . '%');
        }

        $Goods = M('Goods');
        $list = $Goods->where($where)->order('sort asc, id desc')->limit(C('PAGE_SIZE'))->select();
        $total = $Goods->where($where)->count();

        $categories = M('Category')->where(array('status' => 1))->order('sort asc')->select();

        $this->assign('list', $list);
        $this->assign('total', $total);
        $this->assign('categories', $categories);
        $this->assign('cate_id', $cate_id);
        $this->display();
    }

    public function detail()
    {
        $id = I('get.id', 0, 'intval');
        $goods = D('Goods')->relation(true)->find($id);

        if (empty($goods) || $goods['status'] != 1) {
            $this->error('商品不存在', U('Goods/index'));
        }

        // 更新浏览量
        M('Goods')->where(array('id' => $id))->setInc('view_count');

        // 相关商品
        $related = M('Goods')->where(array(
            'cate_id' => $goods['cate_id'],
            'id' => array('neq', $id),
            'status' => 1,
        ))->limit(4)->select();

        $this->assign('goods', $goods);
        $this->assign('related', $related);
        $this->display();
    }

    public function search()
    {
        if (IS_POST) {
            $keyword = I('post.keyword', '', 'htmlspecialchars');
            redirect(U('Goods/index', array('keyword' => $keyword)));
        }
        $this->display();
    }

    public function addToCart()
    {
        if (!IS_AJAX) {
            $this->error('非法请求');
        }

        $goods_id = I('post.goods_id', 0, 'intval');
        $num = I('post.num', 1, 'intval');

        $goods = M('Goods')->find($goods_id);
        if (empty($goods)) {
            $this->ajaxReturn(array('code' => 1, 'msg' => '商品不存在'));
        }

        if ($goods['stock'] < $num) {
            $this->ajaxReturn(array('code' => 1, 'msg' => '库存不足'));
        }

        $cart_data = array(
            'user_id' => session('user_id'),
            'goods_id' => $goods_id,
            'num' => $num,
            'price' => $goods['price'],
        );

        $result = M('Cart')->add($cart_data);
        if ($result) {
            $this->ajaxReturn(array('code' => 0, 'msg' => '加入购物车成功'));
        } else {
            $this->ajaxReturn(array('code' => 1, 'msg' => '操作失败'));
        }
    }
}
