<?php
/**
 * Pembungkus sederhana MySQLi untuk memudahkan pemakaian query di seluruh aplikasi.
 * Koneksi database tetap menggunakan ekstensi MySQLi dan prepared statement MySQLi.
 */
class Database
{
    private mysqli $connection;
    public function __construct(mysqli $connection) { $this->connection = $connection; }
    public function prepare(string $sql): DatabaseStatement
    {
        // MySQLi hanya menerima placeholder positional (?). Ubah placeholder
        // bernama gaya PDO (:email) menjadi ? dan simpan urutan parameternya.
        $parameterNames = [];
        $sql = preg_replace_callback(
            '/(?<!:):([a-zA-Z_][a-zA-Z0-9_]*)/',
            static function (array $match) use (&$parameterNames): string {
                $parameterNames[] = $match[1];
                return '?';
            },
            $sql
        );
        $statement = $this->connection->prepare($sql);
        if (!$statement) throw new mysqli_sql_exception($this->connection->error, $this->connection->errno);
        return new DatabaseStatement($statement, null, $parameterNames);
    }
    public function query(string $sql): DatabaseStatement
    {
        $result = $this->connection->query($sql);
        if ($result === false) throw new mysqli_sql_exception($this->connection->error, $this->connection->errno);
        return new DatabaseStatement(null, $result);
    }
    public function exec(string $sql): int
    {
        if (!$this->connection->query($sql)) throw new mysqli_sql_exception($this->connection->error, $this->connection->errno);
        return $this->connection->affected_rows;
    }
    public function lastInsertId(): string { return (string)$this->connection->insert_id; }
    public function beginTransaction(): bool { return $this->connection->begin_transaction(); }
    public function commit(): bool { return $this->connection->commit(); }
    public function rollBack(): bool { return $this->connection->rollback(); }
    public function inTransaction(): bool { return $this->connection->errno === 0 && $this->connection->thread_id > 0 && $this->transactionActive(); }
    private function transactionActive(): bool
    {
        $result = $this->connection->query("SELECT @@in_transaction AS active");
        if (!$result) return false;
        $row = $result->fetch_assoc(); $result->free();
        return (bool)($row['active'] ?? false);
    }
}

class DatabaseStatement
{
    private ?mysqli_stmt $statement;
    private ?mysqli_result $result;
    private int $affected = 0;
    private array $parameterNames;

    public function __construct(?mysqli_stmt $statement, ?mysqli_result $result = null, array $parameterNames = [])
    { $this->statement=$statement; $this->result=$result; $this->parameterNames=$parameterNames; }

    public function execute(array $params = []): bool
    {
        if (!$this->statement) return true;
        if ($this->result instanceof mysqli_result) $this->result->free();
        $this->result = null;

        if ($this->parameterNames) {
            $ordered=[];
            foreach ($this->parameterNames as $name) {
                if (!array_key_exists($name,$params)) throw new InvalidArgumentException('Parameter SQL :'.$name.' belum diberikan.');
                $ordered[]=$params[$name];
            }
            $params=$ordered;
        } elseif ($params && !array_is_list($params)) $params=array_values($params);

        if (count($params)!==$this->statement->param_count) {
            throw new InvalidArgumentException('Jumlah parameter tidak sesuai. SQL membutuhkan '.$this->statement->param_count.', tetapi menerima '.count($params).'.');
        }
        if ($params) {
            $types=''; $values=[];
            foreach ($params as $value) {
                if (is_int($value)||is_bool($value)) { $types.='i'; $values[]=(int)$value; }
                elseif (is_float($value)) { $types.='d'; $values[]=$value; }
                else { $types.='s'; $values[]=$value; }
            }
            $refs=[];
            foreach ($values as $key=>&$value) $refs[$key]=&$value;
            unset($value);
            if (!$this->statement->bind_param($types,...$refs)) throw new mysqli_sql_exception($this->statement->error,$this->statement->errno);
        }
        if (!$this->statement->execute()) throw new mysqli_sql_exception($this->statement->error,$this->statement->errno);
        $this->affected=$this->statement->affected_rows;

        // SELECT mempunyai result set; INSERT/UPDATE/DELETE tidak.
        if ($this->statement->field_count>0) {
            $result=$this->statement->get_result();
            if (!$result instanceof mysqli_result) throw new RuntimeException('Hasil SELECT tidak dapat dibaca. Pastikan ekstensi mysqlnd aktif pada PHP XAMPP.');
            $this->result=$result;
        }
        return true;
    }
    public function fetch(): array|false {
        if (!$this->result instanceof mysqli_result) return false;
        $row=$this->result->fetch_assoc(); return is_array($row)?$row:false;
    }
    public function fetchAll(): array { return $this->result instanceof mysqli_result?$this->result->fetch_all(MYSQLI_ASSOC):[]; }
    public function fetchColumn(int $column=0): mixed {
        if (!$this->result instanceof mysqli_result) return false;
        $row=$this->result->fetch_row(); return is_array($row)&&array_key_exists($column,$row)?$row[$column]:false;
    }
    public function rowCount(): int { return $this->result instanceof mysqli_result?$this->result->num_rows:$this->affected; }
    public function close(): void {
        if ($this->result instanceof mysqli_result) { $this->result->free(); $this->result=null; }
        if ($this->statement instanceof mysqli_stmt) { $this->statement->close(); $this->statement=null; }
    }
    public function __destruct() { $this->close(); }
}