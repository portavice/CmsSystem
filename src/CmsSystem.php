<?php

namespace Portavice\CmsSystem;

use ArgumentCountError;
use BadMethodCallException;
use Carbon\Carbon;
use Closure;
use Exception;
use Illuminate\Database\Eloquent\Collection;

class CmsSystem
{
    private string $content;

    private string $prefixError;
    private string $suffixError;
    private bool $deleteOnError;

    private array $elements;
    private array $methods;

    protected Collection $params;

    private string $pattern;
    private string $patternWithEnd;
    private string $patternElse;
    private string $patternElseIf;
    private string $patternCase;
    private string $patternDefault;

    public function __construct()
    {
        $this->content = '';
        $this->params = new Collection();

        $prefix = (string)config('cms-system.replacer.prefix', '{{');
        $suffix = (string)config('cms-system.replacer.suffix', '}}');
        $endBlockPrefix = (string)config('cms-system.replacer.end_block_prefix', 'end');

        $this->prefixError = (string)config('cms-system.replacer.prefix_error', '{!{');
        $this->suffixError = (string)config('cms-system.replacer.suffix_error', '}!}');
        $this->deleteOnError = (bool)config('cms-system.replacer.delete_on_error', false);

        $this->elements = (array)config('cms-system.replacer.elements', []);
        $this->methods = (array)config('cms-system.replacer.methods', []);
        $this->pattern = '/' . $prefix . '\s*(?<method>\w+)\s*(?<args>[^}]*)\s*' . $suffix . '/s';
        $this->patternWithEnd = '/' . $prefix . '\s*(?<method>\w+)\s*(?<args>[^}]*)\s*' . $suffix .
            '(?<content>.*?)' . $prefix . '\s*' . $endBlockPrefix . '\k<method>\s*' . $suffix . '/s';
        $this->patternElse = '/' . $prefix . '\s*else\s*' . $suffix .
            '(?<content>.*?)' . $prefix . '\s*(elseif|else|' . $endBlockPrefix . 'if)\s*' . $suffix . '/s';
        $this->patternElseIf = '/' . $prefix . '\s*elseif\s*' . $suffix .
            '(?<content>.*?)' . $prefix . '\s*(elseif|else|' . $endBlockPrefix . 'if)\s*' . $suffix . '/s';
        $this->patternCase = '/' . $prefix . '\s*case\s*(?<args>[^}]*)\s*' . $suffix .
            '(?<content>.*?)' . $prefix . '\s*(case|break|default|' . $endBlockPrefix . 'switch)\s*' . $suffix . '/s';
        $this->patternDefault = '/' . $prefix . '\s*default\s*' . $suffix .
            '(?<content>.*?)' . $prefix . '\s*(case|break|default|' . $endBlockPrefix . 'switch)\s*' . $suffix . '/s';
    }

    #region Setters
    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    public function setParams(array $params): self
    {
        $this->params = new Collection();
        foreach ($params as $key => $value) {
            $this->params->put($key, $value);
        }
        return $this;
    }

    public function setParam(string $key, mixed $value): self
    {
        $this->params->put($key, $value);
        return $this;
    }

    public function removeParam(string $key): self
    {
        $this->params->forget($key);
        return $this;
    }

    public function splitPattern(string $content): array
    {
        preg_match_all($this->patternWithEnd, $content, $matchesWithEnd, PREG_SET_ORDER);
        return $matchesWithEnd;
    }
    #endregion

    public function replace(?string $content = null): string
    {
        $content = html_entity_decode($this->decode($content ?? $this->content));
        while (preg_match($this->patternWithEnd, $content)) {
            $content = preg_replace_callback($this->patternWithEnd, function ($matches) {
                return $this->replaceBlock($matches['method'], $matches['args'] ?? '', $matches['content'] ?? null);
            }, $content);
        }
        while (preg_match($this->pattern, $content)) {
            $content = preg_replace_callback($this->pattern, function ($matches) {
                return $this->replaceBlock($matches['method'], $matches['args'] ?? '', null);
            }, $content);
        }
        return $content;
    }

    #region String Cleanup
    protected function decode(string $string): string
    {
        return str_replace(['&lt;', '&#x22;', '&#34;', '&quot;', '&gt;'], ['<', '"', '"', '"', '>'], $string);
    }

    protected function args(string $argString = ''): array
    {
        $argString = trim($argString ?? '');
        $args = [];
        $arrays = [];
        $regexArray = '/(?<args>\[[^\]]*\])/';
        $regexArraySplit = '/(?<key>[^=>,]*)\s*(?:=>)?\s*(?<value>[^=>,]*)/';

        while (preg_match_all($regexArray, $argString, $argsMatches)) {
            $args = [];
            $argString = preg_replace_callback($regexArray, function ($matches) use ($regexArraySplit, &$args) {
                $arrayArgs = [];
                if (preg_match_all($regexArraySplit, $matches['args'], $arrayArgsMatches)) {
                    $iMax = count($arrayArgsMatches['value'] ?? []);
                    for ($i = 0; $i < $iMax; $i++) {
                        $key = $this->cleanValue(trim(str_replace(['[', ']'], '', $arrayArgsMatches['key'][$i])));
                        $value = $this->cleanValue(trim(str_replace(['[', ']'], '', $arrayArgsMatches['value'][$i])));
                        if ($value === '') {
                            $arrayArgs[] = $key;
                        } else {
                            $arrayArgs[$key] = $value;
                        }
                    }
                }
                $args[] = array_filter($arrayArgs);
                return '';
            }, $argString);
            $arrays[] = $args;
        }

        $method = '';
        $regex = '/(?<args>\S+)/';
        if (str_contains($argString, '"') || str_contains($argString, '\'') || str_contains($argString, '`')) {
            $regex = '/(?<method>\S+)\s*(?<args>(?:"(?:[^"]|\\")*")|(?:\'(?:[^\']|\\\')*\')|(?:\`(?:[^\`]|\\\`)*\`))/';
        }
        if (preg_match_all($regex, $argString, $argsMatches)) {
            $method = $argsMatches['method'][0] ?? '';
            $args = array_merge($args, $argsMatches['args'] ?? []);
        }
        array_unshift($args, str_replace('(', '', $method ?? ''));

        $args = array_map(fn ($value) => $this->cleanValue($value), array_filter($args));
        return array_merge($args, ...$arrays);
    }

    protected function cleanValue(string $value): string|int|float
    {
        if (str_starts_with($value, '"') && str_ends_with($value, '"')) {
            $value = substr($value, 1, -1);
        }
        if (str_starts_with($value, '\'') && str_ends_with($value, '\'')) {
            $value = substr($value, 1, -1);
        }
        if (str_starts_with($value, '`') && str_ends_with($value, '`')) {
            $value = substr($value, 1, -1);
        }
        $value = trim($value);
        if (str_starts_with($value, '$') || str_starts_with($value, '#') || str_starts_with($value, ';')) {
            $value = $this->params->get(substr($value, 1), $value);
        }
        if (str_ends_with($value, ';') || str_ends_with($value, ',')) {
            $value = $this->params->get(substr($value, 1), $value);
        }

        if (is_numeric($value)) {
            if (($value . '') !== (((int)$value) . '')) {
                return (float)$value;
            }
            return (int)$value;
        }
        return $value;
    }
    #endregion

    protected function replaceBlock(string $method, string|array $args, ?string $content): string
    {
        $args = $this->args($args);
        $method = strtolower((string)$this->cleanValue($method));
        $result = null;

        if ($this->methods[$method] ?? true) {
            $result = match ($method) {
                'config' => config($args[1], $args[2] ?? $content ?? null),
                'trans', '__', 'translate' => ($this->methods['translate'] ?? true) ? trans(...$args) : null,
                'var' => $this->var($content, $args),
                'setvar' => $this->setVar($content, $args),
                'unsetvar' => $this->unsetVar($content, $args),
                'if' => $this->if($content, $args),
                'isset' => $this->isset($content, $args),
                'empty' => $this->empty($content, $args),
                'switch' => $this->switch($content, $args),
                'for' => $this->for($content, $args),
                'foreach' => $this->foreach($content, $args),
                default => null,
            };
        }
        if ($result !== null) {
            return $this->convertResult($result, ...$args);
        }
        $result = $this->elements[$method] ?? $this->replaceError($method, $args, $content);
        if (is_callable($result)) {
            return $this->convertResult($result($args));
        }
        return $this->convertResult($result);
    }

    protected function replaceError(string $method, array $args, string $content): string
    {
        if ($this->deleteOnError) {
            return '';
        }
        if ($content !== '') {
            return $content;
        }
        return $this->prefixError . $method . ' ' . implode(' ', $args) . $this->suffixError;
    }

    protected function convertResult(mixed $result, mixed ...$args): ?string
    {
        if ($result === null) {
            return null;
        }
        if (is_callable($result)) {
            $result = $result(...$args);
        }
        if ($result instanceof Carbon) {
            return $result->format('d.m.Y H:i');
        }
        if (is_array($result)) {
            return implode(', ', $result);
        }
        if (is_object($result)) {
            return null;
        }
        if (!is_string($result)) {
            try {
                return '' . $result;
            } catch (Exception) {
                return null;
            }
        }
        return $result;
    }

    protected function value(mixed ...$args): mixed
    {
        $key = null;
        $method = null;
        $splitCount = 1;
        if (count($args) > 0) {
            $regex = '/(?<key>[a-zA-Z0-9_]*)\s*(?<spliter>\.|::|->|=)\s*(?<method>[a-zA-Z0-9_]*)/';
            if (preg_match($regex, $args[0], $matches)) {
                $key = $matches['key'];
                $method = $matches['method'] ?? null;
            } else {
                $key = $args[0];
                $method = $args[1] ?? null;
                $splitCount = $method === null ? 1 : 2;
            }
        }
        $args = array_slice($args, $splitCount);
        if ($key === null) {
            return null;
        }
        $param = $this->params[$key] ?? null;
        if ($param === null) {
            return $key;
        }
        if ($method === null) {
            try {
                return $this->isFunction($param) ? $param(...$args) : $param;
            } catch (Exception | BadMethodCallException | ArgumentCountError) {
                return $param;
            }
        }
        $method = str_replace(['get_', 'set_'], '', $method);
        try {
            return $this->isFunction($param->$method) ? $param->$method(...$args) : $param->$method;
        } catch (Exception | BadMethodCallException | ArgumentCountError) {
            return $param->$method(...$args);
        }
    }

    protected function isFunction(mixed $func): bool
    {
        try {
            return (is_string($func) && function_exists($func))
                || (($func instanceof Closure));
        } catch (ArgumentCountError | BadMethodCallException) {
            return true;
        }
    }

    protected function validateIf(mixed ...$args): bool
    {
        $regex = '/(?<key>[a-zA-Z0-9_]+)(\.|-|->|::|=)(?<method>[a-zA-Z0-9_]+)?/';
        $first = $args[0] ?? null;

        if ($first !== null) {
            if (preg_match($regex, $args[0])) {
                $first = $this->value($args[0] ?? null);
                $args = array_slice($args, 1);
            } elseif (count($args) === 3) {
                $args = array_slice($args, 1);
            } else {
                $first = $this->value($args[0] ?? null, $args[1] ?? null);
                $args = array_slice($args, 2);
            }
        }
        $operator = '===';
        $operatorRegex = '/(?<operator>==|===|!=|!==|>|<|>=|<=|eq|neq|gt|lt|ge|le|==!|===!|>!|<!|>=!|<=!)(?<equal>=)?/';
        if (preg_match($operatorRegex, $args[0] ?? '', $matches)) {
            $operator = $matches['operator'] . ($matches['equal'] ?? '');
            if ($operator === '=') {
                $operator = '===';
            }
            $args = array_slice($args, 1);
        }
        if (count($args) < 1) {
            return $first;
        }
        $second = $args[0] ?? null;
        if ($second === null) {
            return $first;
        }
        if (preg_match($regex, $second)) {
            $second = $this->value($second);
        } else {
            $second = $this->value($second, $args[1] ?? null);
        }
        return match ($operator) {
            '==', '===', 'eq' => $first === $second,
            '!=', '!==', 'neq', '==!', '===!' => $first !== $second,
            '>', 'gt', '<=!' => $first > $second,
            '<', 'lt', '>=!' => $first < $second,
            '>=', 'ge', '<!' => $first >= $second,
            '<=', 'le', '>!' => $first <= $second,
            default => false,
        };
    }

    #region methods
    private function var(?string $content, mixed ...$args): ?string
    {
        if (!($this->methods['var'] ?? true)) {
            return null;
        }
        return $this->value(...$args) ?? $content ?? null;
    }

    private function setVar(mixed ...$args): ?string
    {
        if (!($this->methods['setvar'] ?? true)) {
            return null;
        }
        $value = $this->value(...array_slice($args, 1));
        $this->params[$args[0]] = $value;
        return '';
    }

    private function unsetVar(mixed ...$args): ?string
    {
        if (!($this->methods['unsetvar'] ?? true)) {
            return null;
        }
        unset($this->params[$args[0]]);
        return '';
    }

    private function if(?string $content, mixed ...$args): ?string
    {
        if (!($this->methods['if'] ?? true)) {
            return null;
        }
        if ($content === null) {
            return null;
        }
        if ($this->validateIf($args)) {
            return $this->replace($this->ifReplace($content));
        }
        if ($this->methods['elseif'] ?? true) {
            while (preg_match($this->patternElseIf, $content)) {
                $content = preg_replace_callback($this->patternElseIf, function ($matches) {
                    if ($this->validateIf(...$this->args($matches['args']))) {
                        return $this->replace($this->ifReplace($matches['content']));
                    }
                    return '';
                }, $content);
            }
        }
        if (!($this->methods['else'] ?? true)) {
            return '';
        }
        return preg_replace_callback($this->patternElse, function ($matches) {
                return $this->replace($this->ifReplace($matches['content']));
        }, $content);
    }

    private function ifReplace(string $content): string
    {
        $content = preg_replace($this->patternElseIf, '', $content);
        return preg_replace($this->patternElse, '', $content);
    }

    private function isset(?string $content, mixed ...$args): ?string
    {
        if (!($this->methods['isset'] ?? true)) {
            return null;
        }
        if ($content === null) {
            return null;
        }
        $first = $this->value(...$args);

        return isset($first) && $first !== '' ? $content : '';
    }

    private function empty(?string $content, mixed ...$args): ?string
    {
        if (!($this->methods['empty'] ?? true)) {
            return null;
        }
        if ($content === null) {
            return null;
        }
        return empty($this->value(...$args)) ? $content : '';
    }

    private function switch(?string $content, mixed ...$args): ?string
    {
        if (!($this->methods['switch'] ?? true)) {
            return null;
        }
        if ($content === null) {
            return null;
        }
        $switch = $this->value(...$args);
        while (preg_match($this->patternCase, $content)) {
            $content = preg_replace_callback($this->patternCase, function ($matches) use ($switch) {
                $case = $this->value(...$this->args($matches['args']));
                if ($switch === $case) {
                    $switchContent = $matches['content'];
                    while (preg_match($this->patternCase, $switchContent)) {
                        $switchContent = preg_replace($this->patternCase, '', $switchContent);
                    }
                    while (preg_match($this->patternDefault, $switchContent)) {
                        $switchContent = preg_replace($this->patternDefault, '', $switchContent);
                    }
                    return $this->replace($switchContent);
                }
                return '';
            }, $content);
        }
        if (preg_match($this->patternDefault, $content)) {
            $content = preg_replace_callback($this->patternDefault, function ($matches) {
                $content = $matches['content'];
                while (preg_match($this->patternDefault, $content)) {
                    $content = preg_replace($this->patternDefault, '', $content);
                }
                return $this->replace($content);
            }, $content);
        }
        return preg_replace($this->patternDefault, '', $content);
    }

    private function for(?string $content, mixed ...$args): ?string
    {
        if (!($this->methods['for'] ?? true)) {
            return null;
        }
        if ($content === null) {
            return null;
        }
        try {
            $key = $args[0];
            $startValue = (int)($args[2] ?? 0);
            $endOperator = $args[5] ?? '<';
            $endValue = (int)($args[6] ?? 0);
            $stepOperator = $args[9] ?? '++';
            $stepValue = (int)($args[10] ?? 1);
            if ($stepOperator === '--' || $stepOperator === '-=') {
                $stepValue = -$stepValue;
            }
            $forContent = '';
            $this->params[$key] = $startValue;
            for ($i = $startValue; $this->validateIf($key, $endOperator, $endValue); $i += $stepValue) {
                $this->params[$key] = $i;
                $forContent .= $this->replace($content);
            }

            return $forContent;
        } catch (Exception) {
            return null;
        }
    }

    private function foreach(?string $content, mixed ...$args): ?string
    {
        if (!($this->methods['foreach'] ?? true)) {
            return null;
        }
        if ($content === null) {
            return null;
        }
        $keyValue = array_search('as', $args, false);
        $array = $this->value(...array_slice($args, 0, $keyValue));
        $args = array_slice($args, $keyValue + 1);
        $paramKey = null;
        $paramValue = null;
        if (count($array) > 1) {
            $paramKey = $args[0];
            $paramValue = $args[2] ?? $args[1];
        } else {
            $paramValue = $args[0] ?? null;
        }
        if (is_string($array) || $array === null) {
            return null;
        }
        $foreachContent = '';
        foreach ($array as $key => $value) {
            if ($paramKey !== null) {
                $this->params[$paramKey] = $key;
            }
            if ($paramValue !== null) {
                $this->params[$paramValue] = $value;
            }
            $foreachContent .= $this->replace($content);
        }
        return $foreachContent;
    }
    #endregion
}
