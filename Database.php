<?php

namespace FpDbTest;

use Exception;
use mysqli;

class Database implements DatabaseInterface
{
    const IDENTIFIER_PATTERN = '/\?d|\?f|\?a|\?#|\?/';
    const NESTED_BLOCKS_PATTERN = '/\{.*?\{.*?\}.*?\}/s';

    private mysqli $mysqli;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    /**
     * Проверка вложенных блоков и удаление
     */
    protected function processNestedBlocks(string $query, array &$args): string
    {
        if (preg_match(self::NESTED_BLOCKS_PATTERN, $query) == false) {
            return $query;
        }

        //Получаем последнее схождение
        preg_match_all(self::NESTED_BLOCKS_PATTERN, $query, $blocks);
        preg_match_all(self::IDENTIFIER_PATTERN, end(current($blocks)), $argsDel);
        $argsDel = current($argsDel);

        //Замена для удаления неподходящих значений
        $changeQuery = preg_replace_callback('/\{([^}]*)\}/', function ($matches) use (&$values) {
            return preg_replace(self::IDENTIFIER_PATTERN, ParameterType::SKIP, $matches[1]);
        }, $query);

        preg_match_all('/\?d|\?f|\?a|\?#|\?|' . ParameterType::SKIP . '/', $changeQuery, $skipsArg);
        $skipsArg = current($skipsArg);

        if (count($skipsArg) != count($args)) {
            throw new Exception('Разная длинна значений после нахождения вложенного блока');
        }

        $newArg = [];
        //Откидываю skip аргументы
        for ($i = 0; $i < count($skipsArg); $i++) {
            if ($skipsArg[$i] !== ParameterType::SKIP) {
                $newArg[] = $args[$i];
            }
        }

        $args = $newArg;

        //Удаляю вложенные блоки и обрезаю пробелы
        $query = trim(preg_replace(self::NESTED_BLOCKS_PATTERN, '', $query));

        return $query;
    }

    public function buildQuery(string $query, array $args = []): string
    {
        $query = $this->processNestedBlocks($query, $args);

        preg_match_all(self::IDENTIFIER_PATTERN, $query, $matches);
        $matches = current($matches);

        if (count($matches) != count($args)) {
            throw new Exception('Разная длинна значений и аргументов');
        }

        $matchToArg = [];
        for ($i = 0; $i < count($matches); $i++) {
            $matchToArg[] = [
                'match' => $matches[$i],
                'arg' => $args[$i],
            ];
        }

        foreach ($matchToArg as $k => $v) {
            if ($v['match'] == ParameterType::STRING && is_array($v['arg'])) {
                throw new Exception('Неверный аргумент');
            }
            if ($v['arg'] === ParameterType::SKIP) {
                $query = str_replace($k, '', $query);
                $query = preg_replace('/\{.*?\}/s', '', $query, 1);
                continue;
            }
        }

        $this->processPlaceholder($matchToArg, $query);

        $query = str_replace(['{', '}'], '', $query);
        $query = preg_replace('/\s{2,}/', ' ', $query);

        return $query;
    }

    /**
     * Замена спецификатора на значение
     */
    protected function processPlaceholder(array $matchToArg, string &$query): void
    {
        foreach ($matchToArg as $comb) {

            $arg = $comb['arg'];
            $match = $comb['match'];

            $errText = 'Неверный формат под аргумент ' . $comb['match'];

            switch ($comb['match']) {
                case ParameterType::INTEGER:
                    if (!is_int((int) $arg)) throw new Exception($errText);
                    break;
                case ParameterType::FLOAT:
                    if (!is_float($arg)) throw new Exception($errText);
                    break;
                case ParameterType::STRING:
                    if (is_array($arg)) throw new Exception($errText);
                    $arg = $this->formateValue($arg);
                    break;
                case ParameterType::ARRAY:
                    if (!is_array($arg)) throw new Exception($errText);
                    $arg = $this->formateArrayParameter($arg);
                    break;
                case ParameterType::IDENTIFIER:
                    if (!is_array($arg) && !is_string($arg)) throw new Exception($errText);
                    $arg = is_array($arg) ? $this->formateArrayParameter($arg, true) : $this->formateParameter($arg);
                    break;
            }

            $match = preg_quote($match, '/');
            $query = preg_replace("/$match/", $arg, $query, 1);
        }
    }

    public function skip()
    {
        return ParameterType::SKIP;
    }

    protected function formateArrayParameter(array $array, bool $processIdentifiers = false): string
    {
        $result = [];

        foreach ($array as $k => $v) {
            if (is_int($k)) {
                $result[] = $processIdentifiers ? $this->formateParameter($v) : $this->formateValue($v);
            } else {
                $result[] = $this->formateParameter($k) . ' = ' . $this->formateValue($v); //+
            }
        }

        return implode(', ', $result);
    }

    protected function formateParameter($value): string
    {
        return '`' . $value . '`';
    }
    
    protected function formateValue($value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_int($value) || is_float($value) || is_bool($value)) {
            return $this->mysqli->real_escape_string($value);
        }
        return '\'' . $this->mysqli->real_escape_string($value) . '\'';
    }
}
