<?php

declare(strict_types=1);

namespace PHPLift\Template;

/**
 * Migrates ThinkPHP 3.x template syntax to ThinkPHP 6.x/8.x native syntax.
 *
 * Converts:
 *   <volist>      → {volist}
 *   <if>/<eq>     → {if}/{eq}
 *   <include>     → {include}
 *   {:func()}     → already TP6 compatible (kept)
 *   {$var}        → already TP6 compatible (kept)
 *   __URL__       → {:url('...')}
 *   __PUBLIC__    → /static
 */
final class TemplateMigrator
{
    /** @var list<TemplateRule> */
    private array $rules;

    public function __construct()
    {
        $this->rules = self::defaultRules();
    }

    public function migrate(string $content): TemplateResult
    {
        $original = $content;
        $applied = [];

        foreach ($this->rules as $rule) {
            $result = preg_replace($rule->pattern, $rule->replacement, $content);
            if ($result !== null && $result !== $content) {
                $applied[] = $rule->name;
                $content = $result;
            }
        }

        return new TemplateResult($original, $content, $applied, $content !== $original);
    }

    public function migrateFile(string $filePath, bool $dryRun = true): TemplateResult
    {
        $content = @file_get_contents($filePath);
        if ($content === false) {
            throw new \RuntimeException("Cannot read template file: {$filePath}");
        }

        $result = $this->migrate($content);

        if ($result->changed && !$dryRun) {
            if (@file_put_contents($filePath . '.bak', $result->original) === false) {
                throw new \RuntimeException("Cannot create backup: {$filePath}.bak");
            }
            if (@file_put_contents($filePath, $result->transformed) === false) {
                throw new \RuntimeException("Cannot write migrated file: {$filePath}");
            }
        }

        return $result;
    }

    /** @return list<TemplateRule> */
    private static function defaultRules(): array
    {
        return [
            // <volist name="list" id="vo"> → {volist name="list" id="vo"}
            new TemplateRule(
                'volist-open',
                '/<volist\s+name=["\']([^"\']+)["\']\s+id=["\']([^"\']+)["\']([^>]*)>/i',
                '{volist name="$1" id="$2"$3}',
            ),
            new TemplateRule('volist-close', '#</volist>#i', '{/volist}'),

            // <foreach name="list" item="vo"> → {foreach name="list" item="vo"}
            new TemplateRule(
                'foreach-open',
                '/<foreach\s+name=["\']([^"\']+)["\']\s+item=["\']([^"\']+)["\']([^>]*)>/i',
                '{foreach name="$1" item="$2"$3}',
            ),
            new TemplateRule('foreach-close', '#</foreach>#i', '{/foreach}'),

            // <if condition="..."> → {if condition="..."}
            new TemplateRule('if-open', '/<if\s+condition=["\']([^"\']+)["\']>/i', '{if condition="$1"}'),
            new TemplateRule('elseif', '/<elseif\s+condition=["\']([^"\']+)["\']\\s*\/?>/i', '{elseif condition="$1" /}'),
            new TemplateRule('else', '#<else\\s*/?>|<else\\s*>#i', '{else /}'),
            new TemplateRule('if-close', '#</if>#i', '{/if}'),

            // <eq name="var" value="val"> → {eq name="var" value="val"}
            new TemplateRule(
                'eq-open',
                '/<eq\s+name=["\']([^"\']+)["\']\s+value=["\']([^"\']+)["\']>/i',
                '{eq name="$1" value="$2"}',
            ),
            new TemplateRule('eq-close', '#</eq>#i', '{/eq}'),

            // <neq> same pattern
            new TemplateRule(
                'neq-open',
                '/<neq\s+name=["\']([^"\']+)["\']\s+value=["\']([^"\']+)["\']>/i',
                '{neq name="$1" value="$2"}',
            ),
            new TemplateRule('neq-close', '#</neq>#i', '{/neq}'),

            // <empty name="var"> / <notempty>
            new TemplateRule('empty-open', '/<empty\s+name=["\']([^"\']+)["\']>/i', '{empty name="$1"}'),
            new TemplateRule('empty-close', '#</empty>#i', '{/empty}'),
            new TemplateRule('notempty-open', '/<notempty\s+name=["\']([^"\']+)["\']>/i', '{notempty name="$1"}'),
            new TemplateRule('notempty-close', '#</notempty>#i', '{/notempty}'),

            // <include file="..." /> → {include file="..." /}
            new TemplateRule(
                'include',
                '/<include\s+file=["\']([^"\']+)["\']\s*\/?>/i',
                '{include file="$1" /}',
            ),

            // __URL__ → {:url(\'/\')}
            new TemplateRule('magic-url', '#__URL__#', '{:url(\'/\')}'),
            // __PUBLIC__ → /static
            new TemplateRule('magic-public', '#__PUBLIC__#', '/static'),
            // __ROOT__ → /
            new TemplateRule('magic-root', '#__ROOT__#', '/'),
        ];
    }
}
