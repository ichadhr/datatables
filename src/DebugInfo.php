<?php

namespace Ichadhr\Datatables;

class DebugInfo
{
    protected $info = [];
    protected $calls = [];

    protected $indexedColumns = [];
    protected $compositeIndexes = [];

    const SLOW_QUERY_THRESHOLD_MS = 100;

    public function setIndexedColumns(array $columns)
    {
        $this->indexedColumns = array_map('strtolower', $columns);
    }

    public function setCompositeIndexes(array $indexes)
    {
        // Each $index is an array of column names, e.g. ['status', 'created_at']
        $this->compositeIndexes = array_map(function($idx) {
            return array_map('strtolower', $idx);
        }, $indexes);
    }

    /**
     * Add a key-value pair to the debug info.
     */
    public function add($key, $value)
    {
        $this->info[$key] = $value;
        return $this;
    }

    /**
     * Add multiple key-value pairs to the debug info.
     */
    public function addArray(array $data)
    {
        $this->info = array_merge($this->info, $data);
        return $this;
    }

    /**
     * Log a method/function call with context and timestamp.
     */
    public function logCall($stage, $context = [])
    {
        $this->calls[] = [
            'stage' => $stage,
            'context' => $context,
            'timestamp' => microtime(true)
        ];
        return $this;
    }

    /**
     * Get all collected debug info and call traces.
     */
    public function get()
    {
        $out = $this->info;
        if (!empty($this->calls)) {
            $out['calls'] = $this->calls;
        }
        return $out;
    }

    /**
     * Returns an array of pattern definitions for SQL suggestion checks.
     * Each pattern is an array with 'regex' or 'callback', 'message', and 'severity'.
     *
     * @return array
     */
    protected function getSqlSuggestionPatterns(): array
    {
        return [
            // UNION/UNION ALL
            [
                'regex' => '/\bUNION(\s+ALL)?\b/i',
                'message' => "Query uses UNION or UNION ALL. These can be slow on large tables if not indexed or if used with complex subqueries. Consider optimizing or using alternative approaches if performance is an issue.",
                'severity' => 'warning'
            ],
            // MySQL-style backticks
            [
                'regex' => '/`[^`]+`/',
                'message' => "Query uses MySQL-style backtick quoting for identifiers. For cross-database compatibility, use double quotes or no quotes as appropriate for your DBMS.",
                'severity' => 'warning'
            ],
            // MySQL-specific functions
            [
                'regex' => '/\b(STR_TO_DATE|DATE_FORMAT|CONCAT_WS)\b/i',
                'message' => "Query uses MySQL-specific functions (e.g., STR_TO_DATE, DATE_FORMAT, CONCAT_WS). These may not work in other databases.",
                'severity' => 'warning'
            ],
            // LIMIT without OFFSET
            [
                'regex' => '/LIMIT\s+\d+\s*;?$/i',
                'message' => "Query uses LIMIT without OFFSET. Ensure this is compatible with your database.",
                'severity' => 'warning',
                'extra' => function($sql) { return stripos($sql, 'OFFSET') === false; }
            ],
            // CTEs and temp tables
            [
                'regex' => '/\bWITH\s+([a-zA-Z0-9_]+)\s+AS\s*\(/i',
                'message' => "Query uses Common Table Expressions (CTEs) or temporary tables. These can impact performance on large datasets. Consider materializing results or optimizing the CTE/temp table usage.",
                'severity' => 'warning'
            ],
            [
                'regex' => '/CREATE\s+TEMP(TABLE)?\b/i',
                'message' => "Query uses Common Table Expressions (CTEs) or temporary tables. These can impact performance on large datasets. Consider materializing results or optimizing the CTE/temp table usage.",
                'severity' => 'warning'
            ],
            // OUTER APPLY/CROSS APPLY (SQL Server)
            [
                'regex' => '/\b(OUTER|CROSS)\s+APPLY\b/i',
                'message' => "Query uses OUTER APPLY or CROSS APPLY (SQL Server). These can be slow if not indexed or if used with complex subqueries. Consider optimizing or using alternative approaches if performance is an issue.",
                'severity' => 'warning'
            ],
            // Old-style (comma) joins
            [
                'regex' => '/FROM\s+[^\s,]+,\s*[^\s,]+/i',
                'message' => "Query uses old-style (comma) joins. Use explicit JOIN ... ON ... syntax for better readability and cross-database compatibility.",
                'severity' => 'warning'
            ],
            // SELECT *
            [
                'regex' => '/SELECT\s+\*/i',
                'message' => "Query uses SELECT *. For better performance, select only the columns you actually need (e.g., SELECT id, name FROM ...).",
                'severity' => 'warning'
            ],
            // LIKE '%term%'
            [
                'regex' => '/LIKE\s*\'%.*%\'/i',
                'message' => "Query uses LIKE '%term%'. For large tables, consider adding a full-text index to the searched column(s) for much better performance.",
                'severity' => 'warning'
            ],
            // ORDER BY on expression
            [
                'callback' => function($sql) {
                    if (preg_match('/ORDER BY\s+.*([a-zA-Z0-9_]+\s*\+\s*[a-zA-Z0-9_]+)/i', $sql, $m)) {
                        return "Query uses ORDER BY on an expression (e.g., {$m[1]}). This can prevent index use and slow down sorting. Consider sorting on raw column values or using computed/generated columns with indexes.";
                    }
                    return null;
                },
                'severity' => 'warning'
            ],
            // Non-SARGable predicates
            [
                'callback' => function($sql) {
                    if (preg_match('/WHERE\s+.*(\+|\-|\*|\/|SUBSTR|SUBSTRING|LEFT|RIGHT|UPPER|LOWER|TRIM|CAST|CONVERT|DATE|YEAR|MONTH|DAY)\s*\(/i', $sql)) {
                        return "Query uses non-SARGable predicates (e.g., functions or expressions on columns in WHERE). This can prevent index use and slow down queries. Consider rewriting predicates to be SARGable (searchable arguments).";
                    }
                    return null;
                },
                'severity' => 'warning'
            ],
            // Add more patterns/callbacks as needed
        ];
    }

    /**
     * Analyze all logged SQL queries and context to generate developer suggestions.
     * Suggestions are de-duplicated and sorted by severity.
     *
     * @return array Array of ['message' => ..., 'severity' => ...] sorted by severity
     */
    public function getSuggestions()
    {
        $suggestions = [];
        $suggestedColumns = [];
        $patternSuggestions = [];

        // --- Pattern-driven SQL suggestions ---
        $patterns = $this->getSqlSuggestionPatterns();

        foreach ($this->calls as $call) {
            if ($call['stage'] !== 'db_query' || !isset($call['context']['sql'])) continue;
            $sql = $call['context']['sql'];

            // Apply all regex/callback patterns
            foreach ($patterns as $pattern) {
                if (isset($pattern['regex']) && preg_match($pattern['regex'], $sql)) {
                    if (!isset($pattern['extra']) || $pattern['extra']($sql)) {
                        $patternSuggestions[] = [
                            'message' => $pattern['message'],
                            'severity' => $pattern['severity']
                        ];
                    }
                } elseif (isset($pattern['callback'])) {
                    $msg = $pattern['callback']($sql);
                    if ($msg) {
                        $patternSuggestions[] = [
                            'message' => $msg,
                            'severity' => $pattern['severity']
                        ];
                    }
                }
            }

            // --- Context-based suggestions (indexes, columns, etc.) ---
            // Slow query suggestion
            if (
                $call['stage'] === 'db_query' &&
                isset($call['context']['duration_ms']) &&
                $call['context']['duration_ms'] > self::SLOW_QUERY_THRESHOLD_MS
            ) {
                $suggestion = "Query took {$call['context']['duration_ms']}ms. For better performance, consider adding indexes to frequently filtered";
                if (isset($call['context']['sql']) && stripos($call['context']['sql'], 'ORDER BY') !== false) {
                    $suggestion .= " or ordered columns (especially those in ORDER BY clauses)";
                } else {
                    $suggestion .= " columns";
                }
                $suggestion .= ", simplifying the query, or reviewing your JOINs and WHERE clauses.";
                $suggestions[] = $suggestion;
            }

            // Full Table Scan Warning: SELECT *
            if (
                $call['stage'] === 'db_query' &&
                isset($call['context']['sql']) &&
                stripos($call['context']['sql'], 'SELECT *') !== false
            ) {
                $suggestions[] = "Query uses SELECT *. For better performance, select only the columns you actually need (e.g., SELECT id, name FROM ...).";
            }

            // Unindexed ORDER BY column suggestion
            $orderCols = [];
            if (
                $call['stage'] === 'db_query' &&
                isset($call['context']['sql']) &&
                preg_match('/ORDER BY\\s+([\\w,\\s`\"]+)/i', $call['context']['sql'], $matches)
            ) {
                $orderCols = array_map('trim', explode(',', $matches[1]));
                foreach ($orderCols as $col) {
                    $colName = strtolower(trim(preg_replace('/[`"\']/', '', preg_replace('/\s+(asc|desc)\b/i', '', $col))));
                    if ($colName && !in_array($colName, $this->indexedColumns, true)) {
                        $suggestions[] = "Column '{$colName}' is used in ORDER BY. For best performance on large datasets, ensure this column is indexed in your database.";
                        $suggestedColumns[$colName] = true;
                    }
                }
            }

            // Unindexed WHERE/search/filter column suggestion
            $whereCols = [];
            if (
                $call['stage'] === 'db_query' &&
                isset($call['context']['sql']) &&
                preg_match_all('/WHERE\\s+(.+?)(ORDER BY|GROUP BY|LIMIT|$)/is', $call['context']['sql'], $whereMatches)
            ) {
                $whereClause = $whereMatches[1][0] ?? '';
                if (preg_match_all('/([a-zA-Z0-9_]+)\\s*(=|LIKE|>|<|IN|BETWEEN)/', $whereClause, $colMatches)) {
                    foreach ($colMatches[1] as $colName) {
                        $colName = strtolower($colName);
                        $whereCols[] = $colName;
                        if ($colName && !in_array($colName, $this->indexedColumns, true) && !isset($suggestedColumns[$colName])) {
                            $suggestions[] = "Column '{$colName}' is used in search/filter; make sure it is indexed for best performance.";
                            $suggestedColumns[$colName] = true;
                        }
                    }
                }
            }

            // Composite index suggestion
            if (!empty($whereCols) && !empty($orderCols)) {
                $composite = array_unique(array_merge($whereCols, $orderCols));
                $compositeFound = false;
                foreach ($this->compositeIndexes as $idx) {
                    if ($idx === $composite) {
                        $compositeFound = true;
                        break;
                    }
                }
                if (!$compositeFound) {
                    $suggestions[] = "Consider adding a composite index on (" . implode(', ', $composite) . ") for optimal filtering and ordering performance.";
                }
            }

            // Full-text index suggestion for LIKE '%term%'
            if (
                $call['stage'] === 'db_query' &&
                isset($call['context']['sql']) &&
                preg_match("/LIKE\\s*'%.+%'/i", $call['context']['sql'])
            ) {
                $suggestions[] = "Query uses LIKE '%term%'. For large tables, consider adding a full-text index to the searched column(s) for much better performance.";
            }

            // Joins without index suggestion
            if (
                $call['stage'] === 'db_query' &&
                isset($call['context']['sql'])
            ) {
                // Match all JOIN ... ON ... clauses
                if (preg_match_all('/JOIN\\s+\\S+\\s+ON\\s+([^\\s]+)\\s*=\\s*([^\\s]+)/i', $call['context']['sql'], $joinMatches, PREG_SET_ORDER)) {
                    foreach ($joinMatches as $join) {
                        $leftCol = strtolower(preg_replace('/[`"\']/', '', $join[1]));
                        $rightCol = strtolower(preg_replace('/[`"\']/', '', $join[2]));
                        $leftIndexed = in_array($leftCol, $this->indexedColumns, true);
                        $rightIndexed = in_array($rightCol, $this->indexedColumns, true);
                        if (!$leftIndexed || !$rightIndexed) {
                            $notIndexed = [];
                            if (!$leftIndexed) $notIndexed[] = $leftCol;
                            if (!$rightIndexed) $notIndexed[] = $rightCol;
                            $suggestions[] = "Join condition uses column(s) '" . implode("', '", $notIndexed) . "'. For best performance, make sure these columns are indexed in your database.";
                        }
                    }
                }
            }

            // Functions in WHERE clause warning
            if (
                $call['stage'] === 'db_query' &&
                isset($call['context']['sql']) &&
                preg_match('/WHERE\\s+.*?(\\b[A-Z_]+)\\s*\\(\\s*([a-zA-Z0-9_\\.]+)\\s*\\)/i', $call['context']['sql'], $funcMatch)
            ) {
                $func = $funcMatch[1];
                $col = $funcMatch[2];
                $suggestions[] = "Query uses the function '{$func}()' on column '{$col}' in the WHERE clause. This can prevent index use and slow down queries. Consider filtering on raw column values or using generated columns/indexes if supported by your database.";
            }

            // Functions in ORDER BY clause warning
            if (
                $call['stage'] === 'db_query' &&
                isset($call['context']['sql']) &&
                preg_match('/ORDER BY\s+.*\b([A-Z_]+)\s*\(\s*([a-zA-Z0-9_\.]+)\s*\)/i', $call['context']['sql'], $funcMatch)
            ) {
                $func = $funcMatch[1];
                $col = $funcMatch[2];
                $suggestions[] = "Query uses the function '{$func}()' on column '{$col}' in the ORDER BY clause. This can prevent index use and slow down sorting. Consider sorting on raw column values or using generated columns/indexes if supported by your database.";
            }

            // Suggestion: SELECT only visible columns
            if (
                $call['stage'] === 'db_query' &&
                isset($call['context']['sql']) &&
                isset($this->info['columns']) // visible columns
            ) {
                // Extract columns from SELECT clause
                if (preg_match('/SELECT\s+(.*?)\s+FROM/i', $call['context']['sql'], $selectMatch)) {
                    $selectedCols = array_map('trim', explode(',', $selectMatch[1]));
                    $selectedCols = array_map(function($col) {
                        // Remove aliases and table prefixes
                        $col = preg_replace('/\s+AS\s+.*/i', '', $col);
                        $col = preg_replace('/.*\./', '', $col);
                        $col = preg_replace('/[`"\']/', '', $col);
                        return strtolower(trim($col));
                    }, $selectedCols);

                    $visibleCols = array_map('strtolower', $this->info['columns']);
                    $extraCols = array_diff($selectedCols, $visibleCols);

                    if (!empty($extraCols)) {
                        $suggestions[] = "Your query selects columns (" . implode(', ', $extraCols) . ") that are marked as hidden in the backend configuration. For best performance, only select columns that are visible—unless you need hidden columns for searching, ordering, custom rendering, or export features. Note: This suggestion is based on backend config and may not reflect dynamic changes on the frontend.";
                    }
                }
            }

            // Deprecated/Non-standard SQL detection
            if (
                $call['stage'] === 'db_query' &&
                isset($call['context']['sql'])
            ) {
                $sql = $call['context']['sql'];

                // Old-style join
                if (preg_match('/FROM\s+[^\s,]+,\s*[^\s,]+/i', $sql) && stripos($sql, 'JOIN') === false) {
                    $suggestions[] = "Query uses old-style (comma) joins. Use explicit JOIN ... ON ... syntax for better readability and cross-database compatibility.";
                }

                // MySQL-specific backticks
                if (preg_match('/`[^`]+`/', $sql)) {
                    $suggestions[] = "Query uses MySQL-style backtick quoting for identifiers. For cross-database compatibility, use double quotes or no quotes as appropriate for your DBMS.";
                }

                // MySQL-specific functions
                if (preg_match('/\\b(STR_TO_DATE|DATE_FORMAT|CONCAT_WS)\\b/i', $sql)) {
                    $suggestions[] = "Query uses MySQL-specific functions (e.g., STR_TO_DATE, DATE_FORMAT, CONCAT_WS). These may not work in other databases.";
                }

                // LIMIT without OFFSET (or vice versa)
                if (preg_match('/LIMIT\\s+\\d+\\s*;?$/i', $sql) && stripos($sql, 'OFFSET') === false) {
                    $suggestions[] = "Query uses LIMIT without OFFSET. Ensure this is compatible with your database.";
                }
            }

            // Subqueries in SELECT clause warning
            if (
                $call['stage'] === 'db_query' &&
                isset($call['context']['sql']) &&
                preg_match('/SELECT\s+.*\(\s*SELECT\s+/is', $call['context']['sql'])
            ) {
                $suggestions[] = "Query uses subqueries in the SELECT clause. This can be slow for large tables. Consider using JOINs or pre-aggregated data for better performance.";
            }

            // GROUP BY without aggregates warning
            if (
                $call['stage'] === 'db_query' &&
                isset($call['context']['sql']) &&
                preg_match('/SELECT\s+(.*?)\s+FROM/is', $call['context']['sql'], $selectMatch) &&
                preg_match('/GROUP BY\s+(.*?)(ORDER BY|LIMIT|$)/is', $call['context']['sql'], $groupByMatch)
            ) {
                $selectCols = array_map('trim', explode(',', $selectMatch[1]));
                $groupByCols = array_map('trim', explode(',', $groupByMatch[1]));

                // Remove aliases, table prefixes, and aggregate functions
                $nonAggregated = [];
                foreach ($selectCols as $col) {
                    $colNoAlias = preg_replace('/\s+AS\s+.*/i', '', $col);
                    $colNoFunc = preg_replace('/\b(COUNT|SUM|AVG|MIN|MAX|GROUP_CONCAT|ARRAY_AGG|STRING_AGG)\s*\(.+\)/i', '', $colNoAlias);
                    $colNoPrefix = preg_replace('/.*\./', '', $colNoFunc);
                    $colClean = preg_replace('/[`"\']/', '', trim($colNoPrefix));
                    if ($colClean && !in_array($colClean, $groupByCols, true) && $colNoFunc === $colNoAlias) {
                        $nonAggregated[] = $colClean;
                    }
                }

                if (!empty($nonAggregated)) {
                    $suggestions[] = "Query uses GROUP BY without aggregate functions for column(s): " . implode(', ', $nonAggregated) . ". This is non-standard SQL and may cause errors or unpredictable results in some databases. Use aggregate functions or include all selected columns in GROUP BY.";
                }
            }

            // UNION/UNION ALL detection
            if (
                $call['stage'] === 'db_query' &&
                isset($call['context']['sql']) &&
                preg_match('/\bUNION(\s+ALL)?\b/i', $call['context']['sql'])
            ) {
                $suggestions[] = "Query uses UNION or UNION ALL. These can be slow on large tables if not indexed or if used with complex subqueries. Consider optimizing or using alternative approaches if performance is an issue.";
            }

            // Temporary Tables or CTEs detection
            if (
                $call['stage'] === 'db_query' &&
                isset($call['context']['sql']) &&
                (preg_match('/\bWITH\s+([a-zA-Z0-9_]+)\s+AS\s*\(/i', $call['context']['sql']) || preg_match('/CREATE\s+TEMP(TABLE)?\b/i', $call['context']['sql']))
            ) {
                $suggestions[] = "Query uses Common Table Expressions (CTEs) or temporary tables. These can impact performance on large datasets. Consider materializing results or optimizing the CTE/temp table usage.";
            }

            // OUTER APPLY/CROSS APPLY detection (SQL Server)
            if (
                $call['stage'] === 'db_query' &&
                isset($call['context']['sql']) &&
                preg_match('/\b(OUTER|CROSS)\s+APPLY\b/i', $call['context']['sql'])
            ) {
                $suggestions[] = "Query uses OUTER APPLY or CROSS APPLY (SQL Server). These can be slow if not indexed or if used with complex subqueries. Consider optimizing or using alternative approaches if performance is an issue.";
            }

            // Non-SARGable Predicates detection
            if (
                $call['stage'] === 'db_query' &&
                isset($call['context']['sql']) &&
                preg_match('/WHERE\s+.*(\+|\-|\*|\/|SUBSTR|SUBSTRING|LEFT|RIGHT|UPPER|LOWER|TRIM|CAST|CONVERT|DATE|YEAR|MONTH|DAY)\s*\(/i', $call['context']['sql'])
            ) {
                $suggestions[] = "Query uses non-SARGable predicates (e.g., functions or expressions on columns in WHERE). This can prevent index use and slow down queries. Consider rewriting predicates to be SARGable (searchable arguments).";
            }

            // ORDER BY on Expression detection
            if (
                $call['stage'] === 'db_query' &&
                isset($call['context']['sql']) &&
                preg_match('/ORDER BY\s+.*([a-zA-Z0-9_]+\s*\+\s*[a-zA-Z0-9_]+)/i', $call['context']['sql'])
            ) {
                $suggestions[] = "Query uses ORDER BY on an expression (e.g., col1 + col2). This can prevent index use and slow down sorting. Consider sorting on raw column values or using computed/generated columns with indexes.";
            }
        }

        // --- Refined: Suggest which columns to index for count queries based on actual SQL ---
        foreach ($this->calls as $call) {
            if ($call['stage'] === 'db_query' && isset($call['context']['sql'])) {
                $sql = $call['context']['sql'];
                $whereCols = [];
                $orderCols = [];
                $joinCols = [];
                // Extract columns from WHERE
                if (preg_match_all('/WHERE\s+(.+?)(ORDER BY|GROUP BY|LIMIT|$)/is', $sql, $whereMatches)) {
                    $whereClause = $whereMatches[1][0] ?? '';
                    if (preg_match_all('/([a-zA-Z0-9_]+)\s*(=|LIKE|>|<|IN|BETWEEN)/', $whereClause, $colMatches)) {
                        foreach ($colMatches[1] as $colName) {
                            $whereCols[] = strtolower($colName);
                        }
                    }
                }
                // Extract columns from ORDER BY
                if (preg_match('/ORDER BY\s+([\w,\s`"\.]+)/i', $sql, $orderMatches)) {
                    $orderParts = array_map('trim', explode(',', $orderMatches[1]));
                    foreach ($orderParts as $part) {
                        $col = preg_replace('/[`"\']/', '', preg_replace('/\s+(asc|desc)\b/i', '', $part));
                        $col = preg_replace('/.*\./', '', $col); // remove table prefix
                        if ($col) $orderCols[] = strtolower($col);
                    }
                }
                // Extract columns from JOIN ... ON ...
                if (preg_match_all('/JOIN\s+\S+\s+ON\s+([^\s]+)\s*=\s*([^\s]+)/i', $sql, $joinMatches, PREG_SET_ORDER)) {
                    foreach ($joinMatches as $join) {
                        $leftCol = strtolower(preg_replace('/[`"\']/', '', $join[1]));
                        $rightCol = strtolower(preg_replace('/[`"\']/', '', $join[2]));
                        $joinCols[] = $leftCol;
                        $joinCols[] = $rightCol;
                    }
                }
                $allRelevantCols = array_unique(array_merge($whereCols, $orderCols, $joinCols));
                if (!empty($allRelevantCols)) {
                    $patternSuggestions[] = [
                        'message' => "For optimal count and filter performance, index only these columns used in WHERE, ORDER BY, or JOIN: {" . implode(', ', $allRelevantCols) . "}.",
                        'severity' => 'info'
                    ];
                }
            }
        }

        // Suggest indexing for all used columns not indexed (do this ONCE, not per call)
        if (!empty($this->info['columns'])) {
            $notIndexed = array_diff(
                array_map('strtolower', $this->info['columns']),
                $this->indexedColumns,
                array_keys($suggestedColumns)
            );
            if (!empty($notIndexed)) {
                $suggestions[] = "Columns {" . implode(', ', $notIndexed) . "} are used in search/filter/order. For best performance, make sure these columns are indexed in your database.";
            }
        }

        // --- De-duplicate and sort by severity ---
        $allSuggestions = array_merge($patternSuggestions, $suggestions); // $suggestions from context-based logic
        $allSuggestions = array_map('serialize', $allSuggestions);
        $allSuggestions = array_unique($allSuggestions);
        $allSuggestions = array_map('unserialize', $allSuggestions);
        // Filter out any unserialized values that are not arrays
        $allSuggestions = array_filter($allSuggestions, 'is_array');

        $sevOrder = ['error' => 0, 'warning' => 1, 'info' => 2];
        usort($allSuggestions, function($a, $b) use ($sevOrder) {
            return $sevOrder[$a['severity']] <=> $sevOrder[$b['severity']];
        });
        return $allSuggestions;
    }
}
