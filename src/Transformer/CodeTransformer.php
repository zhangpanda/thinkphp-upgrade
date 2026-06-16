<?php

declare(strict_types=1);

namespace ThinkUpgrade\Transformer;

use PhpParser\Error;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use ThinkUpgrade\RuleSet\RuleInterface;

final class CodeTransformer
{
    private \PhpParser\Parser $parser;
    private Standard $printer;

    public function __construct()
    {
        $this->parser = (new ParserFactory())->createForNewestSupportedVersion();
        $this->printer = new Standard();
    }

    /** @param list<RuleInterface> $rules */
    public function transformFile(string $filePath, array $rules, bool $dryRun = true): TransformResult
    {
        $originalCode = @file_get_contents($filePath);
        if ($originalCode === false) {
            throw new \RuntimeException("Cannot read file: {$filePath}");
        }

        try {
            $ast = $this->parser->parse($originalCode);
        } catch (Error $e) {
            // Skip files with syntax errors — don't crash the whole batch
            return new TransformResult($filePath, $originalCode, $originalCode, changed: false);
        }

        if ($ast === null) {
            return new TransformResult($filePath, $originalCode, $originalCode, changed: false);
        }

        $baseline = $this->printer->prettyPrintFile($ast);

        $applied = [];
        foreach ($rules as $rule) {
            $traverser = new NodeTraverser();
            $traverser->addVisitor($rule->getTransformVisitor());
            $ast = $traverser->traverse($ast);
            $applied[] = $rule->name();
        }

        $newCode = $this->printer->prettyPrintFile($ast);
        $changed = $newCode !== $baseline;

        if ($changed && !$dryRun) {
            if (@file_put_contents($filePath . '.bak', $originalCode) === false) {
                throw new \RuntimeException("Cannot create backup: {$filePath}.bak");
            }
            if (@file_put_contents($filePath, $newCode) === false) {
                throw new \RuntimeException("Cannot write file: {$filePath}");
            }
        }

        return new TransformResult($filePath, $originalCode, $newCode, $applied, $changed);
    }
}
