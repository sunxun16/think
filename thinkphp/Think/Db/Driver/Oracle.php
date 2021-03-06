<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2012 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
namespace Think\Db\Driver;
use Think\Db\Driver;
/**
 * Oracle数据库驱动
 * @category   Extend
 * @package  Extend
 * @subpackage  Driver.Db
 * @author    ZhangXuehun <zhangxuehun@sohu.com>
 */
class Oracle extends Driver{

    private     $table        = '';
    protected   $selectSql    = 'SELECT * FROM (SELECT thinkphp.*, rownum AS numrow FROM (SELECT  %DISTINCT% %FIELD% FROM %TABLE%%JOIN%%WHERE%%GROUP%%HAVING%%ORDER%) thinkphp ) %LIMIT%%COMMENT%';

    /**
     * 执行语句
     * @access public
     * @param string $str  sql指令
     * @return integer
     */
    public function execute($str,$bind=[]) {
        $this->initConnect(true);
        if ( !$this->_linkID ) return false;
        $this->queryStr = $str;
        $flag = false;
        if(preg_match("/^\s*(INSERT\s+INTO)\s+(\w+)\s+/i", $this->queryStr, $match)) {
            $this->table = C("DB_SEQUENCE_PREFIX").str_ireplace(C("DB_PREFIX"), "", $match[2]);
            $flag = (boolean)$this->query("SELECT * FROM user_sequences WHERE sequence_name='" . strtoupper($this->table) . "'");
        }
        //释放前次的查询结果
        if ( !empty($this->PDOStatement) ) $this->free();
        $this->executeTimes++;
        // 记录开始执行时间
        $this->debug(true);
        $this->PDOStatement	=	$this->_linkID->prepare($str);
        if(false === $this->PDOStatement) {
            E($this->error());
        }
        $result	=	$this->PDOStatement->execute($bind);
        $this->debug(false);
        if ( false === $result) {
            $this->error();
            return false;
        } else {
            $this->numRows = $this->PDOStatement->rowCount();
            if($flag || preg_match("/^\s*(INSERT\s+INTO|REPLACE\s+INTO)\s+/i", $str)) {
                $this->lastInsID = $this->getLastInsertId();
            }
            return $this->numRows;
        }
    }

    /**
     * 取得数据表的字段信息
     * @access public
     */
     public function getFields($tableName) {
        $result = $this->query("select a.column_name,data_type,decode(nullable,'Y',0,1) notnull,data_default,decode(a.column_name,b.column_name,1,0) pk "
                  ."from user_tab_columns a,(select column_name from user_constraints c,user_cons_columns col "
          ."where c.constraint_name=col.constraint_name and c.constraint_type='P'and c.table_name='".strtoupper($tableName)
          ."') b where table_name='".strtoupper($tableName)."' and a.column_name=b.column_name(+)");
        $info   =   [];
        if($result) {
            foreach ($result as $key => $val) {
                $info[strtolower($val['column_name'])] = [
                    'name'    => strtolower($val['column_name']),
                    'type'    => strtolower($val['data_type']),
                    'notnull' => $val['notnull'],
                    'default' => $val['data_default'],
                    'primary' => $val['pk'],
                    'autoinc' => $val['pk'],
                ];
            }
        }
        return $info;
    }

    /**
     * 取得数据库的表信息（暂时实现取得用户表信息）
     * @access public
     */
    public function getTables($dbName='') {
        $result = $this->query("select table_name from user_tables");
        $info   =   [];
        foreach ($result as $key => $val) {
            $info[$key] = current($val);
        }
        return $info;
    }

    /**
     * SQL指令安全过滤
     * @access public
     * @param string $str  SQL指令
     * @return string
     */
    public function escapeString($str) {
        return str_ireplace("'", "''", $str);
    }

    /**
     * 获取最后插入id ,仅适用于采用序列+触发器结合生成ID的方式
     * 在config.php中指定
     'DB_TRIGGER_PREFIX'	=>	'tr_',
     'DB_SEQUENCE_PREFIX' =>	'ts_',
     * eg:表 tb_user
     相对tb_user的序列为：
     -- Create sequence
     create sequence TS_USER
     minvalue 1
     maxvalue 999999999999999999999999999
     start with 1
     increment by 1
     nocache;
     相对tb_user,ts_user的触发器为：
     create or replace trigger TR_USER
     before insert on "TB_USER"
     for each row
     begin
     select "TS_USER".nextval into :NEW.ID from dual;
     end;
     * @access public
     * @return integer
     */
    public function getLastInsertId() {
        if(empty($this->table)) {
            return 0;
        }
        $sequenceName = $this->table;
        $vo = $this->query("SELECT {$sequenceName}.currval currval FROM dual");
        return $vo?$vo[0]["currval"]:0;
    }

    /**
     * limit
     * @access public
     * @return string
     */
	public function parseLimit($limit) {
        $limitStr    = '';
        if(!empty($limit)) {
            $limit	=	explode(',',$limit);
            if(count($limit)>1)
                $limitStr = "(numrow>" . $limit[0] . ") AND (numrow<=" . ($limit[0]+$limit[1]) . ")";
            else
                $limitStr = "(numrow>0 AND numrow<=".$limit[0].")";
        }
        return $limitStr?' WHERE '.$limitStr:'';
    }

    /**
     * 设置锁机制
     * @access protected
     * @return string
     */
    protected function parseLock($lock=false) {
        if(!$lock) return '';
        return ' FOR UPDATE NOWAIT ';
    }
}