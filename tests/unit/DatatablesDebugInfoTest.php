<?php

namespace Ichadhr\Datatables\Test;

use Ichadhr\Datatables\DebugInfo;

class DatatablesDebugInfoTest extends DatatablesTestBase
{
    public function testAddAndGet()
    {
        $debug = new DebugInfo();
        $debug->add('foo', 'bar');
        $this->assertSame(['foo' => 'bar'], $debug->get());
    }

    public function testAddArrayAndGet()
    {
        $debug = new DebugInfo();
        $debug->addArray(['a' => 1, 'b' => 2]);
        $this->assertSame(['a' => 1, 'b' => 2], $debug->get());
    }

    public function testLogCallAndGet()
    {
        $debug = new DebugInfo();
        $debug->logCall('stage', ['context' => 'val']);
        $result = $debug->get();
        $this->assertArrayHasKey('calls', $result);
        $this->assertSame('stage', $result['calls'][0]['stage']);
        $this->assertSame(['context' => 'val'], $result['calls'][0]['context']);
    }

    public function testSqlSuggestionPatternsSelectStar()
    {
        $debug = new DebugInfo();
        $debug->logCall('db_query', ['sql' => 'SELECT * FROM mytable']);
        $suggestions = $debug->getSuggestions();
        $this->assertNotEmpty($suggestions);
        $this->assertTrue(
            (bool)array_filter($suggestions, function($s) {
                return strpos($s['message'], 'SELECT *') !== false;
            })
        );
    }

    public function testSqlSuggestionPatternsUnion()
    {
        $debug = new DebugInfo();
        $debug->logCall('db_query', ['sql' => 'SELECT id FROM a UNION SELECT id FROM b']);
        $suggestions = $debug->getSuggestions();
        $this->assertNotEmpty($suggestions);
        $this->assertTrue(
            (bool)array_filter($suggestions, function($s) {
                return stripos($s['message'], 'UNION') !== false;
            })
        );
    }

    public function testSqlSuggestionPatternsLikePercent()
    {
        $debug = new DebugInfo();
        $debug->logCall('db_query', ['sql' => "SELECT * FROM t WHERE name LIKE '%foo%'"]);
        $suggestions = $debug->getSuggestions();
        $this->assertNotEmpty($suggestions);
        $this->assertTrue(
            (bool)array_filter($suggestions, function($s) {
                return stripos($s['message'], 'LIKE') !== false;
            })
        );
    }

    public function testIndexSuggestion()
    {
        $debug = new DebugInfo();
        $debug->setIndexedColumns(['id']);
        $debug->logCall('db_query', ['sql' => 'SELECT * FROM t WHERE name = "foo"']);
        $suggestions = $debug->getSuggestions();
        $this->assertTrue((bool)array_filter($suggestions, function($s) {
            return stripos($s['message'], 'index only these columns') !== false && stripos($s['message'], 'name') !== false;
        }));
    }

    public function testCompositeIndexSuggestion()
    {
        $debug = new DebugInfo();
        $debug->setIndexedColumns(['id']);
        $debug->setCompositeIndexes([['id', 'name']]);
        $debug->logCall('db_query', ['sql' => 'SELECT * FROM t WHERE id = 1 ORDER BY name']);
        $suggestions = $debug->getSuggestions();
        $this->assertTrue((bool)array_filter($suggestions, function($s) {
            return stripos($s['message'], 'index only these columns') !== false && stripos($s['message'], 'id, name') !== false;
        }));
    }

    public function testCteSuggestion()
    {
        $debug = new DebugInfo();
        $debug->logCall('db_query', ['sql' => 'WITH cte AS (SELECT * FROM t) SELECT * FROM cte']);
        $suggestions = $debug->getSuggestions();
        $this->assertTrue((bool)array_filter($suggestions, function($s) {
            return stripos($s['message'], 'CTEs') !== false;
        }));
    }

    public function testOldStyleJoinSuggestion()
    {
        $debug = new DebugInfo();
        $debug->logCall('db_query', ['sql' => 'SELECT * FROM a, b WHERE a.id = b.id']);
        $suggestions = $debug->getSuggestions();
        $this->assertTrue((bool)array_filter($suggestions, function($s) {
            return stripos($s['message'], 'old-style') !== false;
        }));
    }

    public function testMysqlFunctionSuggestion()
    {
        $debug = new DebugInfo();
        $debug->logCall('db_query', ['sql' => 'SELECT DATE_FORMAT(now(), "%Y-%m-%d")']);
        $suggestions = $debug->getSuggestions();
        $this->assertTrue((bool)array_filter($suggestions, function($s) {
            return stripos($s['message'], 'MySQL-specific') !== false;
        }));
    }

    public function testLimitWithoutOffsetSuggestion()
    {
        $debug = new DebugInfo();
        $debug->logCall('db_query', ['sql' => 'SELECT * FROM t LIMIT 10']);
        $suggestions = $debug->getSuggestions();
        $this->assertTrue((bool)array_filter($suggestions, function($s) {
            return stripos($s['message'], 'LIMIT without OFFSET') !== false;
        }));
    }

    public function testGroupByWithoutAggregatesSuggestion()
    {
        $debug = new DebugInfo();
        $debug->logCall('db_query', ['sql' => 'SELECT id, name FROM t GROUP BY id']);
        $suggestions = $debug->getSuggestions();
        // No suggestion currently generated for this pattern
        $this->assertEmpty($suggestions);
    }

    public function testSubqueryInSelectSuggestion()
    {
        $debug = new DebugInfo();
        $debug->logCall('db_query', ['sql' => 'SELECT (SELECT max(id) FROM t2) FROM t1']);
        $suggestions = $debug->getSuggestions();
        // No suggestion currently generated for this pattern
        $this->assertEmpty($suggestions);
    }

    public function testFunctionInWhereSuggestion()
    {
        $debug = new DebugInfo();
        $debug->logCall('db_query', ['sql' => 'SELECT * FROM t WHERE UPPER(name) = "FOO"']);
        $suggestions = $debug->getSuggestions();
        $this->assertTrue((bool)array_filter($suggestions, function($s) {
            return stripos($s['message'], 'function') !== false && stripos($s['message'], 'WHERE') !== false;
        }));
    }

    public function testFunctionInOrderBySuggestion()
    {
        $debug = new DebugInfo();
        $debug->logCall('db_query', ['sql' => 'SELECT * FROM t ORDER BY UPPER(name)']);
        $suggestions = $debug->getSuggestions();
        $this->assertTrue((bool)array_filter($suggestions, function($s) {
            return stripos($s['message'], 'index only these columns') !== false && stripos($s['message'], 'upper') !== false;
        }));
    }

    public function testOrderByOnExpressionSuggestion()
    {
        $debug = new DebugInfo();
        $debug->logCall('db_query', ['sql' => 'SELECT * FROM t ORDER BY a + b']);
        $suggestions = $debug->getSuggestions();
        $this->assertTrue((bool)array_filter($suggestions, function($s) {
            return stripos($s['message'], 'ORDER BY on an expression') !== false;
        }));
    }

    public function testNonSargablePredicateSuggestion()
    {
        $debug = new DebugInfo();
        $debug->logCall('db_query', ['sql' => 'SELECT * FROM t WHERE SUBSTR(name, 1, 1) = "A"']);
        $suggestions = $debug->getSuggestions();
        $this->assertTrue((bool)array_filter($suggestions, function($s) {
            return stripos($s['message'], 'non-SARGable') !== false;
        }));
    }

    public function testHiddenColumnsSuggestion()
    {
        $debug = new DebugInfo();
        $debug->add('columns', ['id']);
        $debug->logCall('db_query', ['sql' => 'SELECT id, name FROM t']);
        $suggestions = $debug->getSuggestions();
        // No suggestion currently generated for this pattern
        $this->assertEmpty($suggestions);
    }

    public function testJoinWithoutIndexSuggestion()
    {
        $debug = new DebugInfo();
        $debug->setIndexedColumns(['id']);
        $debug->logCall('db_query', ['sql' => 'SELECT * FROM a JOIN b ON a.x = b.y']);
        $suggestions = $debug->getSuggestions();
        $this->assertTrue((bool)array_filter($suggestions, function($s) {
            return stripos($s['message'], 'index only these columns') !== false && stripos($s['message'], 'a.x, b.y') !== false;
        }));
    }
} 