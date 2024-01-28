<?php

namespace FpDbTest;

use Exception;
use mysqli;


class Database implements DatabaseInterface
{
    const IDENTIFIER_PATTERN = '/\?d|\?f|\?a|\?#|\?/';
    const NESTED_BLOCKS_PATTERN = '/\{.*?\{.*?\}.*?\}/s';

    private mysqli $mysqli;
    private DatabaseFormatter $formatter;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
        $this->formatter = new DatabaseFormatter($mysqli);
    }

    /**
     * Проверка вложенных блоков и удаление
     */
    protected function processNestedBlocks(string $query, array &$args): string
    {
        if (!preg_match(self::NESTED_BLOCKS_PATTERN, $query)) {
            return $query;
        }

        //Замена для удаления неподходящих значений
        $changeQuery = preg_replace_callback(self::NESTED_BLOCKS_PATTERN, function ($matches) {
            return preg_replace(self::IDENTIFIER_PATTERN, ParameterType::SKIP, end($matches));
        }, $query);

        preg_match_all('/\?d|\?f|\?a|\?#|\?|' . ParameterType::SKIP . '/', $changeQuery, $skipsMatch);
        $skipsMatch = current($skipsMatch);
     
        if (count($skipsMatch) != count($args)) {
            throw new Exception('Разная длинна значений после нахождения вложенного блока');
        }

        $newArg = [];
        //Откидываю skip аргументы
        for ($i = 0; $i < count($skipsMatch); $i++) {
            if ($skipsMatch[$i] !== ParameterType::SKIP) {
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
        foreach($args as $k => $arg){
            if ($arg === ParameterType::SKIP) {
                $query = preg_replace('/\{.*?\}/s', '', $query, 1);
                continue;
            }

            $matchToArg[] = [
                'match' => $matches[$k],
                'arg' => $args[$k],
            ];
        }

        $this->processPlaceholder($matchToArg, $query);

        //чищу запрос от блоков и пробелов
        return preg_replace('/\s{2,}/', ' ', str_replace(['{', '}'], '', $query));
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
                    $arg = $this->getFormatter()->value($arg);
                    break;
                case ParameterType::ARRAY:
                    if (!is_array($arg)) throw new Exception($errText);
                    $arg = $this->getFormatter()->arrayParameter($arg);
                    break;
                case ParameterType::IDENTIFIER:
                    if (!is_array($arg) && !is_string($arg)) throw new Exception($errText);
                    $arg = is_array($arg)
                        ? $this->getFormatter()->arrayParameter($arg, true)
                        : $this->getFormatter()->parameter($arg);
                    break;
            }

            $match = preg_quote($match, '/');
            $query = preg_replace("/$match/", $arg, $query, 1);
        }
    }

    public function skip(): string
    {
        return ParameterType::SKIP;
    }

    public function getFormatter(): DatabaseFormatter
    {
        return $this->formatter;
    }
}
