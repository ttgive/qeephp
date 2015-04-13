# 查询对象 [QDB\_Table\_Select](QDB_Table_Select.md) #

查询对象用于从数据库查询符合条件的记录，并返回为数组或者 [ActiveRecord](QDB_ActiveRecord_Abstract.md) 对象实例。

查询对象使用方法链来构造灵活的查询表达式，例如：

```
$table = new QDB_Table(...);

$rowset = $table->find(...)
                ->all(...)
                ->order(...)
                ->query();
```

## 查询对象的主要方法 ##

指定查询行为：
  * select() 指定 SELECT 子句后要查询的内容（字段、表达式）
  * where() 添加查询条件
  * order() 指定查询的排序
  * all() 指示查询所有符合条件的记录
  * limit() 限制查询结果总数
  * limitPage() 设置分页查询
  * getPageInfo() 获得查询后的分页信息
  * group() 指定 GROUP BY 子句
  * having() 指定 HAVING 子句的条件
  * forUpdate() 是否构造一个 FOR UPDATE 查询
  * distinct() 是否构造一个 DISTINCT 查询

[ActiveRecord](QDB_ActiveRecord_Abstract.md) 相关：
  * asObject() 指示将返回的记录封装为特定的 [ActiveRecord](QDB_ActiveRecord_Abstract.md) 对象

统计功能：
  * count() 统计符合条件的记录数
  * avg() 统计平均值
  * max() 统计最大值
  * min() 统计最小值
  * sum() 统计合计

执行：

  * query() 执行查询，并返回结果

辅助方法：
  * toString() 返回完整的 SQL 语句

# 对复杂查询条件的支持 #

where() 支持的查询条件模式包括：

  * 模式1：where(字符串, [查询参数1, 查询参数2, ...])
  * 模式2：where(数组, [查询参数1, 查询参数2, ...])


## where() 的两种解析模式 ##

如果第一个参数是字符串，则按照基本模式1进行解析；
如果第一个参数是数组，则按照模式2解析。

## 模式1的解析规则 ##

### 查询条件中字段名的解析： ###

  * 如果直接书写字段名，则不会进行任何处理
  * 如果用“[ ]”（方括号）符号包括字段名，则该字段名会被提取出来进行转义处理
  * 对于提取出来的字段名，还会解析是否包含表名称或关联名称
  * 如果字段名包含关联名称，则字段名会转义为“关联表的表名称.字段名”

例如：

```
level_ix = 1 AND credits > 1000
[name] = 'php'
[posts.author] = 'dualface' AND [tags.name] = 'php'
```

会被解析为（假定当前表是 q\_members，而 posts 关联和 tags 关联对应的数据表分别是 q\_posts 和 q\_tags）：

```
level_ix = 1 AND credits > 1000
q_members.name = 'php'
q_posts.author = 'dualface' AND q_tags.name = 'php'
```


### 查询条件中参数占位符的解析： ###

可以在查询条件中使用两种形式的参数占位符，分别是：

  * “**?**”（问号）匿名参数
  * “**:**”（冒号）开头的命名参数

例如：

```
level_ix = ?

`name` = :name

`post_id` IN (?)

`posts.author` = :author AND `tags.name` IN (:tags_name)
```

**注意：不能在查询条件中混用匿名参数和命名参数**


### 查询参数的解析： ###

如果查询条件使用匿名参数，那么查询参数则按顺序处理。

例如：

```
where('level_ix = ? AND credits > ?', $level_ix, $credits)
```

如果查询条件使用命名参数，则 where() 方法的第二个参数必须是数组，并且以查询参数名为键名。

例如：

```
where(
    `posts.author` = :author AND `tags.name` IN :tags_name[]', 
    array(
        'author' => 'dualface',
        'tags_name' => array('php', 'book'),
    )
)
```


## 模式2的解析规则 ##

  * 数组的每一个元素定义查询条件的一部分
  * 如果元素是一个名值对，则假定为 字段名 对应 查询值
    * 解析字段名是否包含表名称或关联名称
    * 如果字段名包含关联名称，则字段名会转义为“关联表的表名称.字段名”
    * 否则字段名转义为“当前表名称.字段名”
  * 如果元素仅仅包含值，则假定为字符串查询条件
    * 如果直接书写字段名，则不会进行任何处理
    * 如果用“[ ]”（方括号）符号包括字段名，则该字段名会被提取出来进行转义处理
    * 对于提取出来的字段名，还会解析是否包含表名称或关联名称
    * 如果字段名包含关联名称，则字段名会转义为“关联表的表名称.字段名”
    * 会解析字符串查询条件中包含的参数占位符

例如：

```
where(
    array('user_id' => $user_id)
)
// table.user_id = $user_id

where(
    array('user_id' => $user_id, 'level_ix' => 1)
)
// table.user_id = $user_id AND table.level_ix = 1

where(
    array('(', 'user_id' => $user_id, 'OR', 'level_ix' => $level_ix, ')', 'created > ?')
)
// (table.user_id = $user_id OR table.level_ix) AND created > ?

where(
    array('user_id' => array($id1, $id2, $id3))
)
// table.user_id IN ($id1, $id2, $id3)
```