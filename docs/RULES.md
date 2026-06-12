# PHPLift 规则手册

## 规则总览

PHPLift 内置 13 条迁移规则，覆盖 ThinkPHP 3.2 → 8.0 全路径。

## ThinkPHP 3.2 → 5.1

### tp3-add-namespace (优先级: 0)

为无命名空间的 TP3 文件添加命名空间声明。

```php
// Before
class UserController extends Controller { }

// After
namespace app\controller;
class UserController extends Controller { }
```

### tp3-controller-migration (优先级: 15)

控制器继承关系和视图方法迁移。

```php
// Before
class User extends Controller {
    $this->assign('key', $val);
    $this->display();
}

// After
class User extends \think\BaseController {
    \think\facade\View::assign('key', $val);
    \think\facade\View::fetch();
}
```

### tp3-model-call (优先级: 30)

M()/D() 快捷函数转为模型静态调用。

```php
// Before
$user = M('User')->where('id', 1)->find();
$order = D('Order')->create($data);

// After
$user = \app\model\User::query()->where('id', 1)->find();
$order = \app\model\Order::query()->create($data);
```

### tp3-config-call (优先级: 35)

C() 配置读取函数转为 config()，含键名映射。

```php
// Before
$host = C('DB_HOST');

// After
$host = config('database.connections.mysql.hostname');
```

内置映射表：

| TP3 键名 | TP6 路径 |
|---------|---------|
| DB_HOST | database.connections.mysql.hostname |
| DB_NAME | database.connections.mysql.database |
| DB_USER | database.connections.mysql.username |
| DB_PWD | database.connections.mysql.password |
| DB_PORT | database.connections.mysql.hostport |
| DB_PREFIX | database.connections.mysql.prefix |
| DB_CHARSET | database.connections.mysql.charset |
| DB_TYPE | database.connections.mysql.type |
| 其他 | app.{小写键名} |

### tp3-input-call (优先级: 38)

I() 输入获取函数转为 Request 方法调用。

```php
// Before
$id = I('get.id', 0, 'intval');
$data = I('post.');

// After
$id = $request->get('id', 0);
$data = $request->post();
```

### tp3-url-generate (优先级: 40)

U() URL 生成函数转为 url()，自动去除模块段。

```php
// Before
$url = U('Admin/User/index');
$url = U('Order/detail', array('id' => 1));

// After
$url = url('user/index');
$url = url('order/detail', array('id' => 1));
```

### tp3-is-post (优先级: 42)

IS_POST/IS_GET/IS_AJAX 常量转为 Request 方法。

```php
// Before
if (IS_POST) { ... }
if (IS_AJAX) { ... }

// After
if ($request->isPost()) { ... }
if ($request->isAjax()) { ... }
```

---

## ThinkPHP 5.1 → 6.0

### tp5-module-removal (优先级: 5)

TP6 移除模块概念，删除命名空间中的模块段。

```php
// Before
namespace app\admin\controller;

// After
namespace app\controller;
```

### tp5-facade-import (优先级: 35)

助手函数转为 Facade 静态调用。

```php
// Before
$val = cache('key');
session('user');

// After
$val = \think\facade\Cache::get('key');
\think\facade\Session::get('user');
```

### tp5-container-access (优先级: 30)

容器访问方式更新。

```php
// Before
$user = model('User');

// After
$user = \app\model\User::query();
```

---

## ThinkPHP 6.0 → 8.0

### tp6-constructor-promotion (优先级: 70)

PHP 8 构造器属性提升。

```php
// Before
class Service {
    protected $repo;
    public function __construct(UserRepo $repo) {
        $this->repo = $repo;
    }
}

// After
class Service {
    public function __construct(protected UserRepo $repo) {}
}
```

### tp6-typed-property (优先级: 75)

根据默认值自动添加属性类型声明。

```php
// Before
protected $name = '';
protected $items = [];

// After
protected string $name = '';
protected array $items = [];
```

### tp6-match-expression (优先级: 80)

简单 switch/case 转为 PHP 8 match 表达式。

```php
// Before
switch($status) {
    case 1: return '待付款';
    case 2: return '已付款';
    default: return '未知';
}

// After
return match($status) {
    1 => '待付款',
    2 => '已付款',
    default => '未知',
};
```

---

## 自定义规则

参考 [CONTRIBUTING.md](../CONTRIBUTING.md) 了解如何编写和贡献自定义规则。
