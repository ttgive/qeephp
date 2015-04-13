# 表数据入口基础类 QDB\_Table #

表数据入口封装对一个数据表的操作。

  * 表数据入口提供记录的 create、update 和 delete 操作。
  * 记录的查询操作委托给查询对象 [QDB\_Table\_Select](QDB_Table_Select.md) 完成。
  * create、update、delete 分为单个记录和批量记录操作两种方式。
  * 表数据入口继承类可以定义多个关联。定义关联后，可以在操作表数据入口时，间接的操作关联的数据表。
  * 表数据入口提供一系列辅助方法，以便表数据入口扩展、关联处理、查询对象和应用程序能够针对数据表进行各种操作。
  * 每个表数据入口对象都包含对应数据表的元数据。元数据包含表名称、表的完全限定名、表所属的 schema、表的所有字段及字段元数据、表的主键字段。


## 表数据入口的主要方法和属性 ##

属性：
  * $schema 数据表所属 schema（可选）
  * $table\_name 要操作的数据表（必须）
  * $full\_table\_name 包含前缀的数据表名称（由 QDB\_Table 自动设置），如果指定了 full\_table\_name，则 QDB\_Table 不会根据全局数据表前缀在 table\_name 之前添加前缀
  * $qtable\_name 包含前缀的数据表完全限定名
  * $pk 主键字段（可选，QDB\_Table 实例化时可以自行确定主键），如果有多个主键字段，则以逗号分隔或使用数组表示
  * $qpk 转义后的主键字段名
  * $created\_time\_fields 创建记录时，要填充当前时间的字段（必须，但已有默认设置）
  * $updated\_time\_fields 更新记录时，要填充当前时间的字段（必须，但已有默认设置）
  * $has\_one、$has\_many、$belongs\_to、$many\_to\_many 定义数据表关联（可选），关联的详细定义，参考 [数据表关联](QTable_Link.md)


查询：
  * find() 创建一个查询对象 [QDB\_Table\_Select](QDB_Table_Select.md)
  * findBySql() 直接执行一个 SQL 语句，并返回结果集（数组）

创建：
  * create() 创建一条新记录，返回新记录的主键值
  * createRowset() 批量创建新记录，并返回包含新记录主键值的数组

更新：
  * update() 更新一条记录，返回被更新记录的总数
  * updateRowset() 批量更新记录，返回被更新记录的总数
  * updateWhere() 更新所有符合条件的记录，返回被更新记录的总数
  * updateBySQL() 执行一个 UPDATE 操作，并且确保同时更新记录的 updated 字段，返回被更新记录的总数
  * incrWhere() 增加所有符合条件记录的指定字段值，返回被更新记录的总数
  * decrWhere() 减小所有符合条件记录的指定字段值，返回被更新记录的总数

创建或更新：
  * save() 根据是否包含主键字段值，创建或更新一条记录，返回记录的主键值
  * saveRowset() 批量保存记录集，返回所有记录的主键值
  * replace() 用 SQL 的 REPLACE 操作替换一条现有记录或插入新记录，返回被影响的记录数
  * replaceRowset() 替换一组现有记录或插入新记录，返回被影响的记录总数

删除：
  * remove() 删除指定主键值的记录，返回被删除的记录总数
  * removeWhere() 删除所有符合条件的记录，返回被删除的记录总数

元数据：
  * columns() 返回所有字段的元数据

辅助操作：
  * nextID() 为当前数据表产生一个新的主键值
  * getDBO() 返回该表数据入口对象使用的数据访问对象
  * setDBO() 设置数据库访问对象
  * isConnected() 确认是否已经连接到数据库
  * isCompositePK() 确认是否是复合主键
  * connect() 连接到数据库
  * qfields() 转义字段
  * parseSQL() 分析SQL，并处理SQL中的字段名和参数占位符

## 查询条件 ##

QDB\_Table 所有以 where 结尾的方法，例如 updateWhere() 都支持同样的查询条件表达式。
查询条件表达式的具体信息，请参考 [QDB\_Table\_Select](QDB_Table_Select.md) 查询对象中的说明。