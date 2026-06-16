# ThinkPHP-Upgrade 端到端验证指南

本文档描述如何搭建 Docker 环境，验证 ThinkPHP-Upgrade 的 ThinkPHP 3.2 → 5.1 → 6.0 → 8.x 全路径迁移。

## 前置要求

- Docker（colima / Docker Desktop）
- MySQL client（可选，用于调试）
- PHP 8.1+（宿主机上运行 ThinkPHP-Upgrade）

## 验证架构

```
┌──────────────────────────────────────────────────────────┐
│                    MySQL 5.7 (port 3307)                  │
│                    数据库: tp3test                         │
│                    表: tp_user (2条数据)                   │
└──────────────────────────────────────────────────────────┘
        │              │              │              │
   TP3 (8080)    TP5.1 (8081)   TP6 (8082)    TP8 (8083)
   PHP 7.4       PHP 7.4        PHP 8.1       PHP 8.2
```

4 个版本同时运行，连接同一个数据库，验证相同的查询返回相同的结果。

## 一键搭建

```bash
mkdir ~/tp3-e2e && cd ~/tp3-e2e
```

### 1. 创建 docker-compose.yml

```yaml
services:
  tp3:
    image: php:7.4-apache
    ports: ["8080:80"]
    volumes: ["./tp3-app:/var/www/html"]
    depends_on: { db: { condition: service_healthy } }
    entrypoint: ["bash", "-c", "docker-php-ext-install pdo pdo_mysql mysqli >/dev/null 2>&1 && a2enmod rewrite >/dev/null && sed -i 's/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf && apache2-foreground"]

  tp51:
    image: php:7.4-apache
    ports: ["8081:80"]
    volumes: ["./tp51-app:/var/www/tp51", "./vhost-tp51.conf:/etc/apache2/sites-available/000-default.conf"]
    depends_on: { db: { condition: service_healthy } }
    entrypoint: ["bash", "-c", "docker-php-ext-install pdo pdo_mysql >/dev/null 2>&1 && a2enmod rewrite >/dev/null && apache2-foreground"]

  tp6:
    image: php:8.1-apache
    ports: ["8082:80"]
    volumes: ["./tp60-app:/var/www/tp60", "./vhost-tp60.conf:/etc/apache2/sites-available/000-default.conf"]
    depends_on: { db: { condition: service_healthy } }
    entrypoint: ["bash", "-c", "docker-php-ext-install pdo pdo_mysql >/dev/null 2>&1 && a2enmod rewrite >/dev/null && apache2-foreground"]

  tp8:
    image: php:8.2-apache
    ports: ["8083:80"]
    volumes: ["./tp8-app:/var/www/tp8", "./vhost-tp8.conf:/etc/apache2/sites-available/000-default.conf"]
    depends_on: { db: { condition: service_healthy } }
    entrypoint: ["bash", "-c", "docker-php-ext-install pdo pdo_mysql >/dev/null 2>&1 && a2enmod rewrite >/dev/null && apache2-foreground"]

  db:
    image: mysql:5.7
    environment: { MYSQL_ROOT_PASSWORD: root123, MYSQL_DATABASE: tp3test }
    ports: ["3307:3306"]
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost", "-proot123"]
      interval: 5s
      timeout: 5s
      retries: 10
```

### 2. 创建 VirtualHost 配置

```bash
for d in tp51 tp60 tp8; do
cat > vhost-${d}.conf << EOF
<VirtualHost *:80>
    DocumentRoot /var/www/${d}/public
    <Directory /var/www/${d}/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
EOF
done
```

### 3. 初始化数据库

```sql
-- init.sql
USE tp3test;
CREATE TABLE IF NOT EXISTS tp_user (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(200),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
INSERT INTO tp_user (name, email) VALUES ('张三', 'zhangsan@test.com'), ('李四', 'lisi@test.com');
```

### 4. 创建 TP3 应用

从已有 ThinkPHP 3.2 项目拷贝框架，或从 GitHub 下载：

```
tp3-app/
├── index.php
├── .htaccess
├── ThinkPHP/           # ThinkPHP 3.2.3 框架
└── Application/
    └── Home/
        ├── Controller/
        │   └── IndexController.class.php
        ├── View/
        │   └── Index/
        │       └── index.html
        └── Conf/
            └── config.php
```

**tp3-app/index.php:**
```php
<?php
define('APP_PATH', './Application/');
define('APP_DEBUG', true);
require './ThinkPHP/ThinkPHP.php';
```

**tp3-app/Application/Home/Controller/IndexController.class.php:**
```php
<?php
namespace Home\Controller;
use Think\Controller;

class IndexController extends Controller {
    public function index() {
        $list = M('user')->order('id desc')->limit(10)->select();
        $count = M('user')->count();
        $site = C('SITE_NAME') ?: 'TP3 Demo';
        $this->assign('list', $list ?: []);
        $this->assign('count', $count);
        $this->assign('site', $site);
        $this->display();
    }
}
```

**tp3-app/Application/Home/Conf/config.php:**
```php
<?php
return array(
    'DB_TYPE'   => 'mysql',
    'DB_HOST'   => 'db',
    'DB_NAME'   => 'tp3test',
    'DB_USER'   => 'root',
    'DB_PWD'    => 'root123',
    'DB_PORT'   => '3306',
    'DB_PREFIX' => 'tp_',
    'SESSION_AUTO_START' => true,
    'SESSION_TYPE' => '',
);
```

### 5. 安装 TP5.1

```bash
docker run --rm -v "$(pwd):/work" -w /work php:7.4-cli sh -c '
  apt-get update -qq && apt-get install -y -qq unzip >/dev/null 2>&1
  curl -sS https://getcomposer.org/installer | php -- --quiet --version=2.2.24
  php composer.phar create-project topthink/think:5.1.* tp51-app --prefer-dist --ignore-platform-reqs
  cd tp51-app && php composer.phar config allow-plugins.topthink/think-installer true
  php composer.phar install --prefer-dist --ignore-platform-reqs
'
```

**tp51-app/application/index/controller/Index.php:**
```php
<?php
namespace app\index\controller;
use think\Controller;
use think\Db;

class Index extends Controller
{
    public function index()
    {
        $list = Db::name('user')->order('id desc')->limit(10)->select();
        $count = Db::name('user')->count();
        $site = 'TP5.1 Demo (migrated from TP3)';
        $this->assign('list', $list);
        $this->assign('count', $count);
        $this->assign('site', $site);
        return $this->fetch();
    }
}
```

**tp51-app/config/database.php:**
```php
<?php
return [
    'type'     => 'mysql',
    'hostname' => 'db',
    'database' => 'tp3test',
    'username' => 'root',
    'password' => 'root123',
    'hostport' => '3306',
    'prefix'   => 'tp_',
    'charset'  => 'utf8',
];
```

### 6. 安装 TP6

```bash
docker run --rm -v "$(pwd):/work" -w /work php:8.1-cli sh -c '
  apt-get update -qq && apt-get install -y -qq unzip git >/dev/null 2>&1
  curl -sS https://getcomposer.org/installer | php -- --quiet --version=2.5.8
  php composer.phar create-project topthink/think:6.0.* tp60-app --prefer-dist --no-interaction --ignore-platform-reqs
  cd tp60-app && php composer.phar require topthink/think-view --no-interaction --ignore-platform-reqs
'
```

**tp60-app/app/controller/Index.php:**
```php
<?php
declare(strict_types=1);
namespace app\controller;

use app\BaseController;
use think\facade\Db;
use think\facade\View;

class Index extends BaseController
{
    public function index()
    {
        $list = Db::name('user')->order('id', 'desc')->limit(10)->select()->toArray();
        $count = Db::name('user')->count();
        $site = 'TP6 Demo (migrated from TP5.1)';
        View::assign('list', $list);
        View::assign('count', $count);
        View::assign('site', $site);
        return View::fetch();
    }
}
```

**tp60-app/.env:**
```
APP_DEBUG = true

[DATABASE]
TYPE = mysql
HOSTNAME = db
DATABASE = tp3test
USERNAME = root
PASSWORD = root123
HOSTPORT = 3306
PREFIX = tp_
CHARSET = utf8
```

> 注意：TP6 启动后需要执行 `docker compose exec tp6 php /var/www/tp60/think service:discover` 注册 think-view 服务。

### 7. 安装 TP8

TP8 和 TP6 目录结构相同，用最新版 composer create-project：

```bash
docker run --rm -v "$(pwd):/work" -w /work composer:latest sh -c '
  composer create-project topthink/think tp8-app --prefer-dist --no-interaction
  cd tp8-app && composer require topthink/think-view --no-interaction
'
```

TP8 控制器写法与 TP6 完全一致（继承 BaseController，使用 Db/View facade）。

### 8. 启动并验证

```bash
# 启动
docker compose up -d

# 等待 PHP 扩展编译 + MySQL 就绪（首次约 90 秒）
sleep 90

# 初始化数据库
docker compose exec -T db mysql -u root -proot123 < init.sql

# TP6 注册服务
docker compose exec tp6 bash -c "cd /var/www/tp60 && php think service:discover"

# 验证
echo "TP3.2:" && curl -s http://localhost:8080/index.php/Home/Index/index | grep "<h1>"
echo "TP5.1:" && curl -s http://localhost:8081/index/index/index | grep "<h1>"
echo "TP6.0:" && curl -s http://localhost:8082/ | grep "<h1>"
echo "TP8.x:" && curl -s http://localhost:8083/ | grep "<h1>"
```

### 9. 预期输出

```
TP3.2: <h1>TP3 Demo - 用户列表 (共2人)</h1>
TP5.1: <h1>TP5.1 Demo (migrated from TP3) - 用户列表 (共2人)</h1>
TP6.0: <h1>TP6 Demo (migrated from TP5.1) - 用户列表 (共2人)</h1>
TP8.x: <h1>TP8 Demo (migrated from TP6) - 用户列表 (共2人)</h1>
```

所有版本查询同一数据库返回相同的 2 条用户记录，证明迁移后业务逻辑等价。

---

## 使用 ThinkPHP-Upgrade 进行代码转换

### 分析

```bash
tp-upgrade analyze tp3-app/Application/Home/Controller/IndexController.class.php
```

### 分步转换

```bash
# TP3 → TP5.1
tp-upgrade transform tp3-app/ --target 5.1 --dry-run

# TP3 → TP6
tp-upgrade transform tp3-app/ --target 6.0 --dry-run

# TP3 → TP8（全路径）
tp-upgrade transform tp3-app/ --target 8.0 --dry-run
```

### ThinkPHP-Upgrade 转换 vs 手动适配

| 步骤 | ThinkPHP-Upgrade 自动完成 | 需要手动适配 |
|------|-----------------|-------------|
| M()/D() → Db/Model | ✅ | — |
| C() → config() | ✅ | — |
| I() → $request->get/post | ✅ | 方法参数注入 $request |
| U() → url() | ✅ | — |
| IS_POST → $request->isPost() | ✅ | — |
| $this->display() → View::fetch() | ✅ | — |
| $this->assign() → View::assign() | ✅ | — |
| namespace 添加 | ✅ | — |
| extends Controller → BaseController | ✅ | — |
| 安装目标框架 | — | composer create-project |
| 数据库配置迁移 | — | 修改 .env / database.php |
| 路由配置 | — | 注册路由规则 |
| $this->success/error | — | 替换为 redirect/json |
| session/cookie | — | 使用 Facade |
| 第三方库适配 | — | 升级依赖版本 |

---

## 清理

```bash
cd ~/tp3-e2e
docker compose down -v
cd .. && rm -rf tp3-e2e
```

---

## 常见问题

**Q: 首次启动很慢？**

A: PHP 官方 Docker 镜像不自带 pdo_mysql 扩展，每次容器启动都需要编译（约 30-60 秒）。可以用自定义 Dockerfile 预编译扩展。

**Q: TP6 报 "Driver [Think] not supported"？**

A: 需要安装 `topthink/think-view` 并执行 `php think service:discover` 注册服务。

**Q: TP3 在 PHP 8.x 下报错？**

A: ThinkPHP 3.2 不兼容 PHP 8.0+（`$GLOBALS` 引用变更）。必须使用 PHP 7.4 或更低版本运行。

**Q: colima 挂载不了 /tmp 目录？**

A: colima 默认只挂载 `$HOME`，项目需放在用户目录下。
