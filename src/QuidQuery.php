<?php

namespace Quid;

use PDO;
use PDOException;
use InvalidArgumentException;

/**
 * QUID - Query Ultra-Intuitive Database
 * 
 * Simple and elegant database operations with 2-parameter WHERE syntax
 * 
 * @package Erilshk\QUID
 * @author erilshk <erilandocarvalho@gmail.com>
 */
class QUID
{
    protected string $table;
    protected string $fields;
    protected ?PDO $pdo;
    protected array $wheres = [];
    protected array $params = [];
    protected array $joins = [];
    protected array $orWheres = [];
    protected ?string $order = null;
    protected ?string $limit = null;
    protected ?string $group = null;

    // Operadores suportados
    private const OPERATORS = [
        '>=', '<=', '<>', '!=', '>', '<', '=', 
        'LIKE', 'NOT LIKE', 'ILIKE', 'NOT ILIKE',
        'IS', 'IS NOT', 'IN', 'BETWEEN'
    ];

    public function __construct(string $table, string $fields, ?PDO $pdo)
    {
        $this->validateTableName($table);
        $this->table = $table;
        $this->select($fields);
        $this->pdo = $pdo;
    }

    /**
     * Valida nome da tabela
     */
    private function validateTableName(string $table): void
    {
        $table = trim($table, '`');
        $pattern = '/^\s*([a-zA-Z_][a-zA-Z0-9_]*(\s+[a-zA-Z_][a-zA-Z0-9_]*)?)\s*$/i';

        if (!preg_match($pattern, $table)) {
            throw new InvalidArgumentException("QUID: Invalid table name: `$table`");
        }
    }

    // -----------------------
    // SELECT
    // -----------------------
    public function select(string $fields): self
    {
        $pattern = '/^\s*(\*|[a-zA-Z_][a-zA-Z0-9_\.]*(\s+(AS\s+)?[a-zA-Z_][a-zA-Z0-9_]*)?)(\s*,\s*[a-zA-Z_][a-zA-Z0-9_\.]*(\s+(AS\s+)?[a-zA-Z_][a-zA-Z0-9_]*)?)*\s*$/i';

        if (!empty($fields) && !preg_match($pattern, $fields)) {
            throw new InvalidArgumentException("QUID: Invalid fields in SELECT: $fields");
        }

        $this->fields = $fields;
        return $this;
    }

    // -----------------------
    // JOIN Methods (simplificados)
    // -----------------------
    public function join(string|array $table, string $on, string $type = 'INNER'): self
    {
        $tableName = is_array($table) ? "{$table[0]} AS {$table[1]}" : $table;
        $this->joins[] = strtoupper($type) . " JOIN $tableName ON $on";
        return $this;
    }

    public function joinLeft(string|array $table, string $on): self
    {
        return $this->join($table, $on, 'LEFT');
    }

    public function joinRight(string|array $table, string $on): self
    {
        return $this->join($table, $on, 'RIGHT');
    }

    public function joinInner(string|array $table, string $on): self
    {
        return $this->join($table, $on, 'INNER');
    }

    protected function buildJoin(): string
    {
        return $this->joins ? ' ' . implode(' ', $this->joins) : '';
    }

    // -----------------------
    // WHERE ULTRA-SIMPLES (2 parâmetros)
    // -----------------------
    public function where(string $columnWithOperator, mixed $value): self
    {
        $this->addCondition($columnWithOperator, $value, 'wheres');
        return $this;
    }

    public function orWhere(string $columnWithOperator, mixed $value): self
    {
        $this->addCondition($columnWithOperator, $value, 'orWheres');
        return $this;
    }

    /**
     * Adiciona condição inteligente com 2 parâmetros
     */
    private function addCondition(string $columnWithOperator, mixed $value, string $target): void
    {
        [$column, $operator] = $this->extractColumnAndOperator($columnWithOperator);
        
        // Lógica inteligente baseada no operador e valor
        switch ($operator) {
            case 'IS':
                if ($value === null) {
                    $this->{$target}[] = "$column IS NULL";
                } else {
                    $this->{$target}[] = "$column IS ?";
                    $this->params[] = $value;
                }
                break;
                
            case 'IS NOT':
                if ($value === null) {
                    $this->{$target}[] = "$column IS NOT NULL";
                } else {
                    $this->{$target}[] = "$column IS NOT ?";
                    $this->params[] = $value;
                }
                break;
                
            case 'IN':
                if (is_array($value)) {
                    $placeholders = implode(', ', array_fill(0, count($value), '?'));
                    $this->{$target}[] = "$column IN ($placeholders)";
                    $this->params = array_merge($this->params, $value);
                } else {
                    throw new InvalidArgumentException("QUID: IN operator requires an array");
                }
                break;
                
            case 'BETWEEN':
                if (is_array($value) && count($value) === 2) {
                    $this->{$target}[] = "$column BETWEEN ? AND ?";
                    $this->params[] = $value[0];
                    $this->params[] = $value[1];
                } else {
                    throw new InvalidArgumentException("QUID: BETWEEN operator requires array with 2 values");
                }
                break;
                
            default:
                $this->{$target}[] = "$column $operator ?";
                $this->params[] = $value;
                break;
        }
    }

    /**
     * Extrai coluna e operador da string combinada
     */
    private function extractColumnAndOperator(string $columnWithOperator): array
    {
        // Ordena operadores por tamanho (maiores primeiro)
        $operators = self::OPERATORS;
        usort($operators, fn($a, $b) => strlen($b) - strlen($a));
        
        foreach ($operators as $operator) {
            $operatorWithSpaces = " $operator ";
            if (str_contains($columnWithOperator, $operatorWithSpaces)) {
                $parts = explode($operatorWithSpaces, $columnWithOperator, 2);
                if (count($parts) === 2) {
                    return [trim($parts[0]), trim($operator)];
                }
            }
        }
        
        // Se não encontrou operador, assume "="
        return [trim($columnWithOperator), '='];
    }

    // -----------------------
    // WHERE HELPERS (métodos específicos para máxima simplicidade)
    // -----------------------
    public function whereLike(string $column, string $value): self
    {
        return $this->where("$column LIKE", $value);
    }

    public function orWhereLike(string $column, string $value): self
    {
        return $this->orWhere("$column LIKE", $value);
    }

    public function whereIn(string $column, array $values): self
    {
        return $this->where("$column IN", $values);
    }

    public function orWhereIn(string $column, array $values): self
    {
        return $this->orWhere("$column IN", $values);
    }

    public function whereBetween(string $column, mixed $start, mixed $end): self
    {
        return $this->where("$column BETWEEN", [$start, $end]);
    }

    public function orWhereBetween(string $column, mixed $start, mixed $end): self
    {
        return $this->orWhere("$column BETWEEN", [$start, $end]);
    }

    public function whereNull(string $column): self
    {
        return $this->where("$column IS", null);
    }

    public function orWhereNull(string $column): self
    {
        return $this->orWhere("$column IS", null);
    }

    public function whereNotNull(string $column): self
    {
        return $this->where("$column IS NOT", null);
    }

    public function orWhereNotNull(string $column): self
    {
        return $this->orWhere("$column IS NOT", null);
    }

    // -----------------------
    // BUILD WHERE
    // -----------------------
    protected function buildWhere(): string
    {
        $whereParts = [];
        $orParts = [];

        foreach ($this->wheres as $where) {
            $whereParts[] = $where;
        }

        foreach ($this->orWheres as $where) {
            $orParts[] = $where;
        }

        if (!$whereParts && !$orParts) return '';

        $sql = ' WHERE ';
        
        if ($whereParts) {
            $sql .= implode(' AND ', $whereParts);
        }
        
        if ($orParts) {
            if ($whereParts) {
                $sql .= ' AND (' . implode(' OR ', $orParts) . ')';
            } else {
                $sql .= implode(' OR ', $orParts);
            }
        }

        return $sql;
    }

    // -----------------------
    // GROUP BY
    // -----------------------
    public function groupBy(string $field): self
    {
        $this->group = "GROUP BY $field";
        return $this;
    }

    protected function buildGroup(): string
    {
        return $this->group ? " {$this->group}" : '';
    }

    // -----------------------
    // ORDER & LIMIT
    // -----------------------
    public function orderBy(string $field, string $sort = 'ASC'): self
    {
        $sort = strtoupper(trim($sort));
        $this->order = "ORDER BY $field $sort";
        return $this;
    }

    public function limit(int $limit, int $offset = 0): self
    {
        $this->limit = "LIMIT $offset, $limit";
        return $this;
    }

    // -----------------------
    // EXECUTION METHODS
    // -----------------------
    public function get(): array
    {
        $sql = $this->buildSQL();

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($this->params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new \RuntimeException("QUID Query failed: " . $e->getMessage());
        }
    }

    public function first(): array
    {
        $this->limit(1);
        $result = $this->get();
        return $result[0] ?? [];
    }

    public function count(): int
    {
        $originalFields = $this->fields;
        $this->fields = "COUNT(*) as count";
        
        try {
            $result = $this->first();
            $this->fields = $originalFields;
            return (int) ($result['count'] ?? 0);
        } catch (\Exception $e) {
            $this->fields = $originalFields;
            throw $e;
        }
    }

    public function exists(): bool
    {
        return $this->count() > 0;
    }

    public function paginate(int $perPage, int $page = 1): array
    {
        $offset = ($page - 1) * $perPage;
        $data = $this->limit($perPage, $offset)->get();
        $total = $this->count();

        return [
            'data' => $data,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => ceil($total / $perPage)
            ]
        ];
    }

    // -----------------------
    // SQL BUILDING
    // -----------------------
    protected function buildSQL(): string
    {
        $sql = "SELECT {$this->fields} FROM {$this->table}"
            . $this->buildJoin()
            . $this->buildWhere()
            . $this->buildGroup();

        if ($this->order) $sql .= " {$this->order}";
        if ($this->limit) $sql .= " {$this->limit}";

        return $sql;
    }

    public function toSql(): string
    {
        return $this->buildSQL();
    }

    public function getBindings(): array
    {
        return $this->params;
    }

    // -----------------------
    // STATIC FACTORY
    // -----------------------
    public static function query(string $table, string $select = '*'): self
    {
        // Você precisará passar o PDO de alguma forma
        // Pode ser via injeção ou singleton
        $pdo = self::getConnection();
        return new self($table, $select, $pdo);
    }

    /**
     * Método para obter conexão PDO
     * Você pode modificar este método conforme sua configuração
     */
    protected static function getConnection(): PDO
    {
        // Exemplo básico - você deve adaptar para seu ambiente
        static $pdo = null;
        
        if ($pdo === null) {
            $pdo = new PDO(
                'mysql:host=localhost;dbname=test', 
                'username', 
                'password'
            );
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
        
        return $pdo;
    }
}