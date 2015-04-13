# QDB\_ActiveRecord\_Abstract 概述 #

QDB\_ActiveRecord\_Abstract 实现了一个扩展的 ActiveRecord 模式。

主要特征包括：

  * 静态的查找 find() 和删除方法 delete()，避免在领域对象中混入基础架构代码
  * 以[表数据入口](QDB_Table.md)为基础的 CRUD 操作，具备丰富的功能
  * 对象属性可以设置为只读模式，并且可以指定属性的读写方法
  * 丰富的事件回调
  * 可透明扩展的行为插件机制
  * 可以使用 BLOB 字段序列换存储值对象
  * 支持 has one、has many、belongs to 和 many to many 四种关联，自动处理对象间的关联操作

## 主要的静态方法 ##

  * QDB\_ActiveRecord\_Interface::define() 返回 ActiveRecord 对象的定义
  * QDB\_ActiveRecord\_Interface::find() 开启一个查询，查找符合条件的对象或对象集合
  * QDB\_ActiveRecord\_Interface::deleteWhere() 删除符合条件的对象
  * QDB\_ActiveRecord\_Interface::destroyWhere() 实例化所有符合条件的对象，并调用这些对象的 destroy() 方法

## 主要的动态方法 ##

  * save() 保存对象到数据库
  * validate() 验证对象属性
  * destroy() 销毁一个对象及其数据库记录
  * id() 获得对象ID（对象在数据库中的主键值）
  * toArray() 获得包含对象所有属性的数组
  * attach() 将对象附着到一个包含对象属性的数组，等同于将数组的属性值复制到对象
  * getTable() 返回该对象使用的表数据入口对象

# 如何定义一个 ActiveRecord #

所有 ActiveRecord 类，都必须从 QDB\_ActiveRecord\_Abstract 继承，并且提供需要的静态方法：

```
<?php
/**
 * Member 封装了用户的领域逻辑
 *
 * @package domain
 */
class Member extends QDB_ActiveRecord_Abstract
{
    /**
     * 开启一个查询，查找符合条件的对象或对象集合
     *
     * @static
     *
     * @return QDB_ActiveRecord_Select
     */
    static function find()
    {
        $args = func_get_args();
        return parent::__find(__CLASS__, $args);
    }

    /**
     * 删除符合条件的对象，返回成功删除的对象的数量
     *
     * @static
     *
     * @param mixed $where
     *
     * @return int
     */
    static function deleteWhere($where)
    {
        $args = func_get_args();
        array_shift($args);
        return parent::__deleteWhere(__CLASS__, $where, $args);
    }

    /**
     * 实例化所有符合条件的对象，并调用这些对象的 destroy() 方法，返回成功删除的对象的数量
     *
     * @static
     *
     * @param mixed $where
     *
     * @return int
     */
    static function destroyWhere()
    {
        $args = func_get_args();
        array_shift($args);
        return parent::__destroyWhere(__CLASS__, $where, $args);
    }

    /**
     * 返回对象的定义
     *
     * @static
     *
     * @return array
     */
    static function __define()
    {
        return array(
            // 指定该 ActiveRecord 要使用的行为插件
            'behaviors' => 'acluser, fakeuuid',

            // 用什么数据表保存 Member 对象的数据
            // 如果不使用 table_name 指定数据表，可以用 table_class 指定负责数据库操作的表数据入口对象
            'table_name' => 'members',

            // 指定数据表记录字段与对象属性之间的映射关系
            // 没有在此处指定的字段，QeePHP 会自动设置将字段映射为对象的可读写属性
            'fields' => array(
                // member_id 属性应该是只读，确保领域对象的“不变量”
                'member_id'             => array('readonly' => true),
                // 其他的只读属性
                'username'              => array('readonly' => true),
                'created'               => array('readonly' => true),
                'updated'               => array('readonly' => true),
                'last_login'            => array('readonly' => true),
                'login_count'           => array('readonly' => true),
                'last_password_updated' => array('readonly' => true),
                'register_ip'           => array('readonly' => true),
                'last_login_ip'         => array('readonly' => true),
                'credits'               => array('readonly' => true),
                'profile_completed'     => array('readonly' => true),

                /**
                 * 由于密码是加密后存储，所以允许直接读取密码属性；
                 * 对密码属性赋值时则会调用 setPassword() 方法进行。
                 *
                 * 例如：
                 * $member->password = 123456;
                 * 实际上会用 123456 作为参数调用 setPassword() 方法，并将 setPassword() 的返回结果赋值给 password 属性。
                 */
                'password' => array('setter' => 'setPassword'),

                /**
                 * 当读取 email 属性时，会自动调用 getEmail() 方法
                 *
                 * 例如 email 属性的实际值是 liaoyulei@qeeyuan.com
                 * 但通过 $member->email 获得的值却是通过 getEmail() 方法编码过后的。
                 */
                'email' => array('getter' => 'getEmail'),

                // 一个 Member 对象，关联一个 Profile 对象
                'profile' => array('has_one' => 'Profile'),

                // 一个 Member 对象，关联多个 Order 对象
                'orders' => array(
                    'has_many' => 'Order',
                    // on_save 指示保存 Member 对象时，如何处理关联的 Order 对象
                    // 这里设置为 only_create 表示仅仅保存新建的 Order 对象，已有的则不更新
                    'on_save' => 'only_create',
                    // on_delete 指示从数据库删除 Member 对象时，如何处理关联的 Order 对象
                    //这里设置为 'skip' 表示不删除关联的 Order 对象
                    'on_delete' => 'skip',
                ),

                // 一个 Member 对象，关联一个 Group 对象
                'group' => array('belongs_to' => 'Group'),

                // 一个 Member 对象，关联多个 Member 对象，实现“我的好友”功能
                // “我的好友”是单向的。也就是说 A 的好友列表包含 B，但不代表 B 的好友列表中就有 A。
                // 因此需要用一个单独的属性指定这个好友关系是属于哪一个 Member 对象的
                'friends' => array(
                    'many_to_many' => 'Member',
                    // restrict 用于限定关联的 crud 操作必须符合指定条件
                    // %MID_TABLE% 等用“%”括起来的字符串是预定义宏
                    'restrict' => '[%MID_TABLE%.owner_id]' => '[%MAIN_TABLE%.member_id]',
                ),
            ),
        );
    }

    /**
     * 编码 Email 地址
     *
     * @param string $email
     *
     * @return string
     */
    proteced function getEmail($email)
    {
        // 把 liaoyulei@qeeyuan.com 类似的邮件地址转换为 liaoyulei (at) qeeyuan (dot) com 这样的字符串
        return str_replace(array('@', '.'), array(' (at) ', ' (dot) '), $email);
    }

    /**
     * 返回加密后的密码
     *
     * @param string $cleartext
     *
     * @return string
     */
    protected function setPassword($cleartext)
    {
        return crypt($cleartext);
    }
}
?>
```