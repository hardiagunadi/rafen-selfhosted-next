<?php

namespace Database\Seeders\MixRadius;

class MixRadiusSqlParser
{
    private string $sqlPath;

    /** @var array<string, string[]> */
    private array $columnCache = [];

    public function __construct()
    {
        $this->sqlPath = database_path('backup_mixradius.sql');
    }

    /**
     * Extract all rows from a specific table's INSERT statements.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getTableData(string $tableName): array
    {
        $columns = $this->getColumnNamesFromFile($tableName);

        if (empty($columns)) {
            return [];
        }

        return $this->extractInsertRows($tableName, $columns);
    }

    /**
     * Read the file line by line to find CREATE TABLE and get column names.
     *
     * @return string[]
     */
    private function getColumnNamesFromFile(string $tableName): array
    {
        if (isset($this->columnCache[$tableName])) {
            return $this->columnCache[$tableName];
        }

        $handle = fopen($this->sqlPath, 'r');
        if (! $handle) {
            return [];
        }

        $columns = [];
        $inTable = false;
        $createStart = 'CREATE TABLE `'.$tableName.'`';

        while (($line = fgets($handle)) !== false) {
            $trimmed = trim($line);

            if (! $inTable && str_contains($trimmed, $createStart)) {
                $inTable = true;
                continue;
            }

            if ($inTable) {
                // End of CREATE TABLE
                if (str_starts_with($trimmed, ') ENGINE')) {
                    break;
                }

                // Match column definition: `colname` type ...
                if (preg_match('/^\s*`([^`]+)`\s+/', $trimmed) &&
                    ! preg_match('/^\s*(PRIMARY|KEY|UNIQUE|CONSTRAINT|INDEX)/i', $trimmed)) {
                    if (preg_match('/^\s*`([^`]+)`/', $trimmed, $m)) {
                        $columns[] = $m[1];
                    }
                }
            }
        }

        fclose($handle);

        $this->columnCache[$tableName] = $columns;

        return $columns;
    }

    /**
     * Read the file line by line, collecting INSERT INTO `tableName` VALUES lines.
     *
     * @param  string[]  $columns
     * @return array<int, array<string, mixed>>
     */
    private function extractInsertRows(string $tableName, array $columns): array
    {
        $handle = fopen($this->sqlPath, 'r');
        if (! $handle) {
            return [];
        }

        $rows = [];
        $insertPrefix = 'INSERT INTO `'.$tableName.'` VALUES ';

        while (($line = fgets($handle)) !== false) {
            $trimmed = ltrim($line);

            if (! str_starts_with($trimmed, $insertPrefix)) {
                continue;
            }

            // Extract the VALUES part (after the prefix, strip trailing semicolon)
            $valuesBlock = substr($trimmed, strlen($insertPrefix));
            $valuesBlock = rtrim($valuesBlock);
            if (str_ends_with($valuesBlock, ';')) {
                $valuesBlock = substr($valuesBlock, 0, -1);
            }

            $parsedRows = $this->parseValueRows($valuesBlock);
            foreach ($parsedRows as $rowValues) {
                if (count($rowValues) === count($columns)) {
                    $rows[] = array_combine($columns, $rowValues);
                }
            }
        }

        fclose($handle);

        return $rows;
    }

    /**
     * Parse individual row tuples from a VALUES block.
     *
     * @return array<int, array<int, mixed>>
     */
    private function parseValueRows(string $valuesBlock): array
    {
        $rows = [];
        $valuesBlock = trim($valuesBlock);

        $i = 0;
        $len = strlen($valuesBlock);

        while ($i < $len) {
            // Skip whitespace and commas between rows
            while ($i < $len && in_array($valuesBlock[$i], [' ', "\n", "\r", "\t", ','])) {
                $i++;
            }

            if ($i >= $len) {
                break;
            }

            if ($valuesBlock[$i] !== '(') {
                $i++;
                continue;
            }

            // Parse one tuple
            $row = $this->parseTuple($valuesBlock, $i);
            if ($row !== null) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * Parse a single tuple starting at position $pos (which should be '(').
     * Advances $pos past the closing ')'.
     *
     * @return array<int, mixed>|null
     */
    private function parseTuple(string $str, int &$pos): ?array
    {
        if ($str[$pos] !== '(') {
            return null;
        }

        $pos++; // skip opening (
        $values = [];
        $len = strlen($str);

        while ($pos < $len && $str[$pos] !== ')') {
            $value = $this->parseValue($str, $pos);
            $values[] = $value;

            // Skip comma between values
            if ($pos < $len && $str[$pos] === ',') {
                $pos++;
            }
        }

        $pos++; // skip closing )

        return $values;
    }

    /**
     * Parse a single SQL value (string, number, NULL, etc.) at position $pos.
     */
    private function parseValue(string $str, int &$pos): mixed
    {
        $len = strlen($str);

        // Skip leading whitespace
        while ($pos < $len && in_array($str[$pos], [' ', "\t"])) {
            $pos++;
        }

        if ($pos >= $len) {
            return null;
        }

        // NULL
        if (substr($str, $pos, 4) === 'NULL') {
            $pos += 4;

            return null;
        }

        // Quoted string
        if ($str[$pos] === "'") {
            return $this->parseQuotedString($str, $pos);
        }

        // Number or other unquoted value
        $start = $pos;
        while ($pos < $len && ! in_array($str[$pos], [',', ')'])) {
            $pos++;
        }
        $raw = trim(substr($str, $start, $pos - $start));

        if (is_numeric($raw)) {
            return str_contains($raw, '.') ? (float) $raw : (int) $raw;
        }

        return $raw;
    }

    /**
     * Parse a MySQL single-quoted string, handling escape sequences.
     */
    private function parseQuotedString(string $str, int &$pos): string
    {
        $pos++; // skip opening quote
        $result = '';
        $len = strlen($str);

        while ($pos < $len) {
            $char = $str[$pos];

            if ($char === '\\') {
                $pos++;
                if ($pos < $len) {
                    $escaped = $str[$pos];
                    $result .= match ($escaped) {
                        'n'     => "\n",
                        'r'     => "\r",
                        't'     => "\t",
                        '\\'    => '\\',
                        "'"     => "'",
                        '"'     => '"',
                        default => $escaped,
                    };
                    $pos++;
                }
            } elseif ($char === "'") {
                // Check for escaped single quote ''
                if (($pos + 1) < $len && $str[$pos + 1] === "'") {
                    $result .= "'";
                    $pos += 2;
                } else {
                    $pos++; // skip closing quote
                    break;
                }
            } else {
                $result .= $char;
                $pos++;
            }
        }

        return $result;
    }
}
