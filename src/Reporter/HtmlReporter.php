<?php

declare(strict_types=1);

namespace PHPLift\Reporter;

use PHPLift\Analyzer\FileAnalysis;

final class HtmlReporter implements ReporterInterface
{
    public function generate(array $analyses): string
    {
        $totalIssues = 0;
        $autoFixable = 0;
        $byRule = [];
        $fileRows = '';

        foreach ($analyses as $analysis) {
            foreach ($analysis->issues as $issue) {
                $totalIssues++;
                if ($issue->autoFixable) {
                    $autoFixable++;
                }
                $byRule[$issue->ruleName] = ($byRule[$issue->ruleName] ?? 0) + 1;
            }

            if ($analysis->issues !== []) {
                $count = count($analysis->issues);
                $path = htmlspecialchars(basename($analysis->filePath));
                $fileRows .= "<tr><td>{$path}</td><td>{$count}</td></tr>\n";
            }
        }

        $manual = $totalIssues - $autoFixable;
        arsort($byRule);
        $ruleRows = '';
        foreach ($byRule as $rule => $count) {
            $ruleRows .= "<tr><td>{$rule}</td><td>{$count}</td></tr>\n";
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<title>ThinkPHP-Upgrade 迁移报告</title>
<style>
body { font-family: -apple-system, "Microsoft YaHei", sans-serif; max-width: 900px; margin: 2em auto; padding: 0 1em; color: #333; }
h1 { border-bottom: 2px solid #1677ff; padding-bottom: 0.5em; }
.stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1em; margin: 1.5em 0; }
.stat { background: #f5f5f5; border-radius: 8px; padding: 1em; text-align: center; }
.stat .num { font-size: 2em; font-weight: bold; }
.stat.green .num { color: #52c41a; }
.stat.red .num { color: #ff4d4f; }
.stat.blue .num { color: #1677ff; }
table { width: 100%; border-collapse: collapse; margin: 1em 0; }
th, td { border: 1px solid #e8e8e8; padding: 8px 12px; text-align: left; }
th { background: #fafafa; }
</style>
</head>
<body>
<h1>📊 ThinkPHP-Upgrade 迁移报告</h1>

<div class="stats">
  <div class="stat blue"><div class="num">{$totalIssues}</div><div>总问题数</div></div>
  <div class="stat green"><div class="num">{$autoFixable}</div><div>可自动修复</div></div>
  <div class="stat red"><div class="num">{$manual}</div><div>需手动/AI</div></div>
</div>

<h2>按规则分组</h2>
<table><tr><th>规则</th><th>数量</th></tr>{$ruleRows}</table>

<h2>涉及文件</h2>
<table><tr><th>文件</th><th>问题数</th></tr>{$fileRows}</table>

<footer><p>由 <a href="https://github.com/zhangpanda/thinkphp-upgrade">PHPLift</a> 生成</p></footer>
</body>
</html>
HTML;
    }
}
