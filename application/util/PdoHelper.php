<?php
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 快乐是福 <815856515@qq.com>
// +----------------------------------------------------------------------
// | Date: 2016/4/23
// +----------------------------------------------------------------------

namespace app\util;


class PdoHelper
{
    private $sqlPrefix = "pre_";//SQL数据表前缀识别字符
    private $db;
    private $fetchStyle = \PDO::FETCH_ASSOC;
    private $prefix;

    /**
     * PdoHelper constructor.
     *
     * @param string $_dbHost 数据库地址
     * @param int $_dbPort int 数据库端口
     * @param string $_dbName 数据库库名
     * @param string $_dbUser 数据库用户名
     * @param string $dbPwd 数据库密码
     */
    function __construct()
    {
        $_database = C('database');
        $_dbHost = $_database['hostname'];
        $_dbPort = $_database['hostport'];
        $_dbName = $_database['database'];
        $_dbUser = $_database['username'];
        $_dbPwd = $_database['password'];
        $this->prefix = $_database['prefix'];
        try {
            $this->db = new \PDO("mysql:host={$_dbHost};dbname={$_dbName};port={$_dbPort}", $_dbUser, $_dbPwd);
        } catch (Exception $e) {
            exit('链接数据库失败:' . $e->getMessage());
        }
        $this->db->exec("set names utf8");
    }

    /**
     * 设置结果集方式
     *
     * @param string $_style
     */
    public function setFetchStyle($_style)
    {
        $this->fetchStyle = $_style;
    }

    /**
     * 替换数据表前缀
     * @param $_sql
     *
     * @return mixed
     */
    private function dealPrefix($_sql){
        return str_replace($this->sqlPrefix,$this->prefix,$_sql);
    }

    /**
     * 查询一条结果
     *
     * @param string $_sql string
     * @param array $_array array
     *
     * @return mixed
     */
    public function find($_sql, $_array = null)
    {
        $_sql = $this->dealPrefix($_sql);
        if (is_array($_array)) {
            $stmt = $this->db->prepare($_sql);
            $stmt->execute($_array);
        } else {
            $stmt = $this->db->query($_sql);
        }
        return $stmt->fetch($this->fetchStyle);
    }

    /**
     * 获取所有结果
     *
     * @param string $_sql
     * @param array $_array
     *
     * @return array
     */
    public function selectAll($_sql, $_array = null)
    {
        $_sql = $this->dealPrefix($_sql);
        if (is_array($_array)) {
            $stmt = $this->db->prepare($_sql);
            $stmt->execute($_array);
        } else {
            $stmt = $this->db->query($_sql);
        }
        return $stmt->fetchAll($this->fetchStyle);
    }

    /**
     * 获取PDOStatement
     * @param string $_sql
     * @param array $_array
     *
     * @return \PDOStatement
     */
    public function getStmt($_sql, $_array = null)
    {
        $_sql = $this->dealPrefix($_sql);
        if (is_array($_array)) {
            $stmt = $this->db->prepare($_sql);
            $stmt->execute($_array);
        } else {
            $stmt = $this->db->query($_sql);
        }
        return $stmt;
    }

    /**
     * 获取结果数
     * @param string $_sql
     * @param array $_array
     *
     * @return int
     */
    public function getCount($_sql, $_array = null)
    {
        $_sql = $this->dealPrefix($_sql);
        $stmt = $this->db->prepare($_sql);
        $stmt->execute($_array);
        return $stmt->rowCount();
    }

    /**
     * 执行语句
     * @param string $_sql
     * @param array $_array
     *
     * @return int|\PDOStatement
     */
    public function execute($_sql, $_array = null)
    {
        $_sql = $this->dealPrefix($_sql);
        if (is_array($_array)) {
            $stmt = $this->db->prepare($_sql);
            return $stmt->execute($_array);
        } else {
            return $this->db->exec($_sql);
        }
    }

    function __get($name)
    {
        return $this->$name;
    }

    function __destruct()
    {
        $this->db = null;
    }


}