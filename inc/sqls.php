<?php
/**
 * 本文件功能: 数据库操作类（使用mysqli）
 * 作者信息: 15058593138@qq.com(手机号同微信)
 */

class DB {
    private $conn;
    
    /**
     * 构造函数，建立数据库连接
     */
    public function __construct() {
        $this->conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($this->conn->connect_error) {
            die('数据库连接失败: ' . $this->conn->connect_error);
        }
        $this->conn->set_charset(DB_CHARSET);
    }
    
    /**
     * 获取数据库连接
     */
    public function getConn() {
        return $this->conn;
    }
    
    /**
     * 执行查询
     * @param string $sql SQL语句
     * @return mysqli_result|bool
     */
    public function query($sql) {
        return $this->conn->query($sql);
    }
    
    /**
     * 查询单条记录
     * @param string $sql SQL语句
     * @return array|null
     */
    public function getRow($sql) {
        $result = $this->query($sql);
        if ($result && $result->num_rows > 0) {
            return $result->fetch_assoc();
        }
        return null;
    }
    
    /**
     * 查询多条记录
     * @param string $sql SQL语句
     * @return array
     */
    public function getAll($sql) {
        $result = $this->query($sql);
        $data = [];
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
        }
        return $data;
    }
    
    /**
     * 查询单个值
     * @param string $sql SQL语句
     * @return mixed
     */
    public function getOne($sql) {
        $result = $this->query($sql);
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_array();
            return $row[0];
        }
        return null;
    }
    
    /**
     * 插入数据
     * @param string $table 表名
     * @param array $data 数据数组
     * @return int 返回插入ID
     */
    public function insert($table, $data) {
        $fields = [];
        $values = [];
        foreach ($data as $key => $value) {
            $fields[] = "`{$key}`";
            $values[] = "'" . $this->conn->real_escape_string($value) . "'";
        }
        $sql = "INSERT INTO `{$table}` (" . implode(',', $fields) . ") VALUES (" . implode(',', $values) . ")";
        $this->query($sql);
        return $this->conn->insert_id;
    }
    
    /**
     * 更新数据
     * @param string $table 表名
     * @param array $data 数据数组
     * @param string $where 条件
     * @return bool
     */
    public function update($table, $data, $where) {
        $sets = [];
        foreach ($data as $key => $value) {
            $sets[] = "`{$key}`='" . $this->conn->real_escape_string($value) . "'";
        }
        $sql = "UPDATE `{$table}` SET " . implode(',', $sets) . " WHERE {$where}";
        return $this->query($sql);
    }
    
    /**
     * 删除数据
     * @param string $table 表名
     * @param string $where 条件
     * @return bool
     */
    public function delete($table, $where) {
        $sql = "DELETE FROM `{$table}` WHERE {$where}";
        return $this->query($sql);
    }
    
    /**
     * 统计记录数
     * @param string $table 表名
     * @param string $where 条件
     * @return int
     */
    public function count($table, $where = '1=1') {
        $sql = "SELECT COUNT(*) FROM `{$table}` WHERE {$where}";
        return intval($this->getOne($sql));
    }
    
    /**
     * 分页查询
     * @param string $table 表名
     * @param string $where 条件
     * @param int $page 页码
     * @param int $pageSize 每页数量
     * @param string $order 排序
     * @return array
     */
    public function getPage($table, $where = '1=1', $page = 1, $pageSize = PAGE_SIZE, $order = 'id DESC') {
        $total = $this->count($table, $where);
        $totalPage = ceil($total / $pageSize);
        $offset = ($page - 1) * $pageSize;
        
        $sql = "SELECT * FROM `{$table}` WHERE {$where} ORDER BY {$order} LIMIT {$offset}, {$pageSize}";
        $list = $this->getAll($sql);
        
        return [
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'pageSize' => $pageSize,
            'totalPage' => $totalPage
        ];
    }
    
    /**
     * 关闭数据库连接
     */
    public function close() {
        if ($this->conn) {
            $this->conn->close();
        }
    }
    
    /**
     * 析构函数
     */
    public function __destruct() {
        $this->close();
    }
}
