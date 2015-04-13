# 表数据入口 #

表数据入口由下列几个部分组成：

[表数据入口基础类 QDB\_Table](QDB_Table.md)

实现对数据表的基本操作，包括 create、update、delete，以及一些常用的辅助方法。查询操作通过 find() 方法构造一个查询对象来完成。表数据入口基础类还存储数据表的各项元数据，提供给其他服务使用。

[查询对象 QDB\_Table\_Select](QDB_Table_Select.md)

查询对象提供完善的查询功能，特别是对复杂查询条件的支持。并且可以将查询结果直接返回为需要的 [QActiveRecord 对象](QDB_ActiveRecord_Abstract.md)。

[数据表关联处理](QDB_Table_Link.md)

实现 has\_one、has\_many、belongs\_to 和 many\_to\_many 关联。四种关联关系提供对关联数据的 find、create、update 和 delete 操作。并且支持外键约束。

[表数据入口扩展](QDB_Table_Extension.md)

表数据入口扩展可以透明的改变表数据入口的行为，并且扩展其功能。