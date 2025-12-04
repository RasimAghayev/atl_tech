<?php

declare(strict_types=1);

namespace App\Support\Filters;

use Illuminate\Http\Request;

class ApiFilter
{
    protected array $safeParms = [];

    protected array $columnMap = [];

    protected array $operatorMap = [
        'eq' => '=',
        'lt' => '<',
        'lte' => '<=',
        'gt' => '>',
        'gte' => '>=',
        'ne' => '!=',
        'lk' => 'LIKE',
        'ilk' => 'ILIKE',
        'nlk' => 'NOT LIKE',
        'inlk' => 'NOT ILIKE',
        'bt' => 'BETWEEN',
        'nbt' => 'NOT BETWEEN',
        'in' => 'IN',
        'nin' => 'NOT IN',
        'json' => 'JSON_CONTAINS',
    ];

    public function transform(Request $request): array
    {
        $eloQuery = [];

        foreach ($this->safeParms as $parm => $operators) {
            $query = $request->query($parm);
            if (!$query) {
                continue;
            }

            $column = $this->columnMap[$parm] ?? $parm;

            foreach ($operators as $operator) {
                if (isset($query[$operator]) && $result = $this->buildQuery($column, $operator, $query[$operator])) {
                    $eloQuery[] = $result;
                }
            }
        }

        return $eloQuery;
    }

    protected function buildQuery($column, $operator, $value): ?array
    {
        $mappedOperator = $this->operatorMap[$operator] ?? null;
        if (!$mappedOperator) {
            return null;
        }

        return match($operator) {
            'lk', 'nlk', 'ilk', 'inlk' => [$column, $mappedOperator, '%'.$value.'%'],
            'bt', 'nbt' => is_array($value) && count($value) === 2
                ? [$column, $mappedOperator, $value]
                : null,
            'in', 'nin' => is_array($value)
                ? [$column, $mappedOperator, $value]
                : null,
            'json' => ['JSON_CONTAINS', $column, $value],
            default => [$column, $mappedOperator, $value]
        };
    }
}
