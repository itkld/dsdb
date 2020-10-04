<?php
/**
 * Created by PhpStorm.
 * User: zhangyi
 * Date: 2020-08-22
 * Time: 09:23
 */

namespace Lib;

use PHPSQLParser\PHPSQLParser;

class SQLTracer
{
    private $undoSqls = [];
    private $db;

    public function __construct(Database $db)
    {
        $this->startMonit();
        $this->db = $db;
    }

    public function startMonit() {
        $this->undoSqls = [];
    }

    public function endMonit() {
        $undoSqls = $this->undoSqls;
        $this->undoSqls = [];
        return $undoSqls;
    }

    public function record($sql) {
        $parser = new PHPSQLParser();
        $parsed = $parser->parse($sql);
        $opKey = array_keys($parsed)[0];
        $tableInfo = $parsed[$opKey][0];
        $tableName = $tableInfo['table'];
        switch ($opKey) {
            case 'INSERT':
                break;
            case 'DELETE':
                break;
            case 'UPDATE':
                $whereInfo = $parsed['WHERE'];
                // 生成where查询
                $where = [];
                array_map(function($item) use (&$where) {
                    $where[] = $item['base_expr'];
                }, $whereInfo);

                // set使用的字段
                $setFields = [];

                // set表达式，生成undo, 使用query到的字段进行替换
                $setExpression = [];
                $setInfo = $parsed['SET'];
                //var_dump($setInfo);
                foreach ($setInfo as $subSet) {
                    foreach($subSet['sub_tree'] as $idx => $item) {
                        // 存储字段
                        if($item['expr_type'] == 'colref') {
                            $setFields[] = $item['base_expr'];
                        }

                        // 对常量进行替换
                        if($item['expr_type'] == 'const') {
                            $setExpression[] = sprintf("'{%s}',", $setFields[count($setFields) - 1]);
                        }
                        else {
                            $setExpression[] = $item['base_expr'];
                        }
                    }
                }

                // 构造redoSql时需要查出原始值
                $querySql = sprintf("SELECT %s FROM %s WHERE %s", implode(',', $setFields), $tableName, implode(' ', $where));
                $orgData = $this->db->getRow($querySql);
                $updatePartial = substr(implode(' ', $setExpression), 0, -1);
                $undoSql = sprintf("UPDATE %s SET %s WHERE %s", $tableName, $updatePartial, implode(' ', $where));
                foreach($orgData as $key => $val) {
                    unset($orgData[$key]);
                    $key = sprintf("{%s}", $key);
                    $orgData[$key] = $val;
                }

                $undoSql = strtr($undoSql, $orgData);
                break;
        }
        $this->undoSqls[] = $undoSql;
    }
}