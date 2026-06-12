<?php

declare(strict_types=1);

namespace PHPLift\Analyzer;

use PhpParser\Error;
use PhpParser\Node;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use PHPLift\RuleSet\RuleInterface;

final class CodeAnalyzer
{
    private \PhpParser\Parser $parser;
    /** @var list<RuleInterface> */
    private array $rules = [];

    public function __construct()
    {
        $this->parser = (new ParserFactory())->createForNewestSupportedVersion();
    }

    /** @param list<RuleInterface> $rules */
    public function setRules(array $rules): void
    {
        $this->rules = $rules;
        usort($this->rules, fn(RuleInterface $a, RuleInterface $b) => $a->priority() <=> $b->priority());
    }

    public function analyzeFile(string $filePath): FileAnalysis
    {
        $code = @file_get_contents($filePath);
        if ($code === false) {
            return new FileAnalysis($filePath);
        }

        try {
            $ast = $this->parser->parse($code);
        } catch (Error) {
            return new FileAnalysis($filePath);
        }

        if ($ast === null) {
            return new FileAnalysis($filePath);
        }

        $issues = [];
        foreach ($this->rules as $rule) {
            $visitor = new MatchCollector($rule);
            $traverser = new NodeTraverser();
            $traverser->addVisitor($visitor);
            $traverser->traverse($ast);
            $issues = array_merge($issues, $visitor->getIssues());
        }

        return new FileAnalysis($filePath, $issues);
    }
}

/** @internal */
final class MatchCollector extends NodeVisitorAbstract
{
    /** @var list<Issue> */
    private array $issues = [];

    public function __construct(private readonly RuleInterface $rule) {}

    public function enterNode(Node $node): ?int
    {
        if ($node instanceof FuncCall && $node->name instanceof Name) {
            $name = $node->name->toString();
            if ($this->ruleMatchesFuncCall($name)) {
                $this->issues[] = new Issue(
                    ruleName: $this->rule->name(),
                    description: $this->rule->description(),
                    line: $node->getStartLine(),
                    originalCode: $name . '(...)',
                    autoFixable: $this->rule->isAutoFixable(),
                );
            }
        }

        if ($node instanceof ConstFetch) {
            $name = $node->name->toString();
            if ($this->ruleMatchesConstFetch($name)) {
                $this->issues[] = new Issue(
                    ruleName: $this->rule->name(),
                    description: $this->rule->description(),
                    line: $node->getStartLine(),
                    originalCode: $name,
                    autoFixable: $this->rule->isAutoFixable(),
                );
            }
        }

        return null;
    }

    private function ruleMatchesFuncCall(string $funcName): bool
    {
        return match ($this->rule->name()) {
            'tp3-model-call' => in_array($funcName, ['M', 'D'], true),
            'tp3-config-call' => $funcName === 'C',
            'tp3-url-generate' => $funcName === 'U',
            'tp3-input-call' => $funcName === 'I',
            'tp3-add-namespace', 'tp3-controller-migration', 'tp3-is-post' => false,
            default => false,
        };
    }

    private function ruleMatchesConstFetch(string $constName): bool
    {
        return match ($this->rule->name()) {
            'tp3-is-post' => in_array($constName, ['IS_POST', 'IS_GET', 'IS_AJAX'], true),
            default => false,
        };
    }

    /** @return list<Issue> */
    public function getIssues(): array
    {
        return $this->issues;
    }
}
