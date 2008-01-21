<?php
/////////////////////////////////////////////////////////////////////////////
// QeePHP Framework
//
// Copyright (c) 2005 - 2008 QeeYuan China Inc. (http://www.qeeyuan.com)
//
// 许可协议，请查看源代码中附带的 LICENSE.txt 文件，
// 或者访问 http://www.qeephp.org/ 获得详细信息。
/////////////////////////////////////////////////////////////////////////////

/**
 * QActiveRecord_Abstract 类实现了 Active Record 模式
 *
 * @copyright Copyright (c) 2005 - 2008 QeeYuan China Inc. (http://www.qeeyuan.com)
 * @author 起源科技(www.qeeyuan.com)
 * @package core
 * @version $Id$
 */
abstract class QActiveRecord_Abstract implements QActiveRecord_Callbacks_Interface, QActiveRecord_Interface
{
    /**
     * 聚合关系
     */
    const has_one       = 0x02;
    const has_many      = 0x04;
    const belongs_to    = 0x08;
    const many_to_many  = 0x10;

    /**
     * 属性的读写权限
     */
    const writable      = 0x20;
    const readonly      = 0x40;
    const hidden        = 0x80;

    /**
     * 要自动载入的对象行为插件
     *
     * @var array|string
     */
    protected $_behaviors;

    /**
     * 当前对象的类名
     *
     * @var string
     */
    private $__class;

    /**
     * 对象不允许直接访问、不允许修改（只读）的属性的值
     *
     * @var array
     */
    private $__protected_props;

    /**
     * 对象所有属性的引用
     *
     * @var array
     */
    private $__all_props;

    /**
     * 事件钩子
     *
     * @var array
     */
    private static $__callbacks = array();

    /**
     * 扩展的方法
     *
     * @var array
     */
    private static $__methods = array();

    /**
     * 所有用到的 ActiveRecord 对象的定义
     *
     * @var array
     */
    private static $__defines;

    /**
     * 构造函数
     *
     * @param array $data
     */
    function __construct(array $data = null)
    {
        $this->__class = get_class($this);
        self::__init_define($this->__class);

        if (is_array($data)) {
            $this->attach($data);
        } else {
            $this->attach(array());
        }
        $this->__bind_behaviors();
        $this->__do_callbacks(self::after_initialize);
    }

    /**
     * 开启一个查询
     *
     * @param string $class
     * @param array $where
     *
     * @return ActiveRecord_Select
     */
    protected static function __find($class, array $where = null)
    {
        self::__init_define($class);
        $select = new QActiveRecord_Select($class, self::$__defines[$class]['table'], self::$__defines[$class]['attribs']);
        if (!empty($where)) {
            call_user_func_array(array($select, 'where'), $where);
        }
        // $select->set_callbacks(self::$__callbacks[$this->__class][self::after_find]);
        return $select;
    }

    /**
     * 强制创建新记录
     */
    function force_create()
    {
        $table = self::$__defines[$this->__class]['table'];
        /* @var $table Table */
        $this->validate('create');
        $this->__do_callbacks(self::before_create);
        $table->create($this->to_array());
        $this->__do_callbacks(self::after_create);
    }

    /**
     * 保存对象到数据库
     */
    function save()
    {
        $this->__do_callbacks(self::before_save);
        $table = self::$__defines[$this->__class]['table'];
        /* @var $table Table */
        $id = $this->id();
        if (empty($id)) {
            $this->force_create();
        } else {
            $this->validate('update');
            $this->__do_callbacks(self::before_update);
            $table->update($this->to_array());
            $this->__do_callbacks(self::after_update);
        }
        $this->__do_callbacks(self::after_save);
    }

    /**
     * 验证对象属性
     */
    function validate($mode = 'general')
    {
        $this->__do_callbacks(self::before_validation);
        if ($mode == 'create') {
            $this->__do_callbacks(self::before_validation_on_create);
        } elseif ($mode == 'update') {
            $this->__do_callbacks(self::before_validation_on_update);
        }
        // ...
        if ($mode == 'create') {
            $this->__do_callbacks(self::after_validation_on_create);
        } elseif ($mode == 'update') {
            $this->__do_callbacks(self::after_validation_on_update);
        }
        $this->__do_callbacks(self::after_validation);
    }

    /**
     * 销毁对象对应的数据库记录
     */
    function destroy()
    {
        $table = self::$__defines[$this->__class]['table'];
        /* @var $table Table */
        $this->__do_callbacks(self::before_destroy);
        $table->remove($this->id());
        $this->__do_callbacks(self::after_destroy);
    }

    /**
     * 调用指定类型的 callback 方法
     */
    protected function __do_callbacks($type)
    {
        if (!isset(self::$__callbacks[$this->__class][$type])) { return; }
        foreach (self::$__callbacks[$this->__class][$type] as $callback) {
            $ret = call_user_func_array($callback, array($this, $this->__all_props));
            if ($ret === false) { break; }
        }
    }

    /**
     * 获得对象ID（对象在数据库中的主键值）
     *
     * @return mixed
     */
    function id()
    {
        $pk = self::$__defines[$this->__class]['pk'];
        return $this->{$pk};
    }

    /**
     * 获得包含对象所有属性的数组
     *
     * @return array
     */
    function to_array()
    {
        return $this->__all_props;
    }

    /**
     * 将包含记录值的数组复制到对象
     *
     * @param array $row
     */
    function attach($row)
    {
        foreach (self::$__defines[$this->__class]['attribs'] as $field => $define) {
            if (!isset($row[$field])) {
                $row[$field] = self::$__defines[$this->__class]['attribs'][$field]['default'];
            }

            if ($define['readonly'] || !$define['public']) {
                $this->__protected_props[$field] = $row[$field];
                $this->__all_props[$field] =& $this->__protected_props[$field];
            } else {
                $this->{$field} = $row[$field];
                $this->__all_props[$field]= & $this->{$field};
            }
        }
    }

    /**
     * 返回该对象使用的表数据入口
     *
     * @return Table
     */
    function get_table()
    {
        return self::$__defines[$this->__class]['table'];
    }

    /**
     * 魔法方法，实现只读属性的支持
     *
     * @param string $varname
     *
     * @return mixed
     */
    function __get($varname)
    {
        if (!isset(self::$__defines[$this->__class]['attribs'][$varname])) {
            throw new QException(__('Property "%s" not defined.', $varname));
        }
        return $this->__protected_props[$varname];
    }

    /**
     * 魔法方法，实现只读属性的支持
     *
     * @param string $varname
     * @param mixed $value
     */
    function __set($varname, $value)
    {
        if (!isset(self::$__defines[$this->__class][$varname])) {
            $this->{$varname} = $value;
            return;
        }

        $define = self::$__defines[$this->__class][$varname];
        if ($define['readonly']) {
            throw new QException(__('Property "%s" is readonly.', $varname));
        }

        if (isset($define['model'])) {
            if ($define['relation'] & (self::has_many | self::many_to_many)) {
                // 聚合的对象，要求 $value 必须是一个包含特定类型对象的数组
                if (!is_array($value)) {
                    $msg = 'Property "%s" type mismatch. expected is "array", actual is "%s".';
                    throw new QActiveRecord_Exception(__($msg, $varname, gettype($value)));
                }
                foreach (array_kesy($value) as $key) {
                    if (!is_object($value[$key]) || !($value[$key] instanceof $define['model'])) {
                        $msg = 'Property "%s[]" type mismatch. expected is "%s", actual is "%s".';
                        throw new QActiveRecord_Exception(__($msg, $varname, $define['model'], gettype($value[$key])));
                    }
                }
                $this->__protected_props[$varname] = $value;
            } else {
                if (!is_object($value) || !($value instanceof $define['model'])) {
                    $msg = 'Property "%s" type mismatch. expected is "%s", actual is "%s".';
                    throw new QActiveRecord_Exception(__($msg, $varname, $define['model'], gettype($value)));
                }
                $this->__protected_props[$varname] = $value;
            }
        } else {
            $this->__protected_props[$varname] = $value;
        }
    }

    /**
     * 魔法方法，用于调用行为插件为对象添加的方法
     *
     * @param string $method
     * @param array $args
     *
     * @return mixed
     */
    function __call($method, $args)
    {
        if (isset(self::$__methods[$this->__class][$method])) {
            array_unshift($args, $this->__all_props);
            array_unshift($args, $this);
            return call_user_func_array(self::$__methods[$this->__class][$method], $args);
        } else {
            throw new QActiveRecord_Exception(__('Call to undefined method "%s::%s()"', $this->__class, $method));
        }
    }

    /**
     * 初始化指定 ActiveRecord 继承类的定义
     *
     * @param string $class
     */
    private static function __init_define($class)
    {
        if (isset(self::$__defines[$class])) { return; }
        $class_define = call_user_func(array($class, 'define'));

        if (!isset($class_define['table_class'])) {
            if (!isset($class_define['table_name'])) {
                $arr = explode('_', $class);
                $class_define['table_name'] = strtolower($arr[count($arr) - 1]);
            }
            $class_define['table'] = new QTable_Base(array('table_name' => $class_define['table_name']));
        } else {
            $class_define['table'] = Q::getSingleton($class_define['table_class']);
        }
        $class_define['pk'] = $class_define['table']->pk;

        $meta = $class_define['table']->columns();
        $attribs = array();
        if (isset($class_define['fields'])) {
            foreach ($class_define['fields'] as $field => $flags) {
                if (is_array($flags)) {
                    if (isset($flags[1])) {
                        list($mapping, $flags) = $flags;
                    } else {
                        die('invlid');
                    }
                } else {
                    $mapping = null;
                }

                $define = array(
                    'public'    => !($flags & self::hidden),
                    'readonly'  => $flags & self::readonly,
                );

                $flags = $flags & (self::has_one | self::has_many | self::belongs_to | self::many_to_many);
                if ($flags) {
                    $define['model'] = $mapping;
                    $define['relation'] = $flags;
                }

                if (isset($meta[$field]) && $meta[$field]['has_default']) {
                    $define['default'] = $meta[$field]['default'];
                } else {
                    $define['default'] = null;
                }
                $attribs[$field] = $define;
            }
        }

        foreach ($meta as $key => $field) {
            if (!isset($attribs[$key])) {
                $attribs[$key] = array(
                    'public'    => true,
                    'readonly'  => false,
                    'default'   => ($field['has_default']) ? $field['default'] : null,
                );
            }
        }
        $class_define['attribs'] = $attribs;
        unset($class_define['fields']);

        self::$__defines[$class] = $class_define;
    }

    /**
     * 为指定的 ActiveRecord 类绑定行为插件
     */
    private function __bind_behaviors()
    {
        if (isset(self::$__callbacks[$this->__class])) { return; }

        self::$__callbacks[$this->__class] = array();
        self::$__methods[$this->__class] = array();

        $behaviors = normalize($this->_behaviors);
        foreach ($behaviors as $behavior) {
            $class = 'Behavior_' . ucfirst(strtolower($behavior));
            Q::loadClass($class, 'behavior');
            $hooks = call_user_func(array($class, 'hooks'));

            foreach ($hooks as $hook) {
                list($type, $method) = $hook;
                if ($type == QActiveRecord_Behavior_Interface::custom_method) {
                    self::$__methods[$this->__class][$method[0]] = $method;
                } else {
                    if (!isset(self::$__callbacks[$this->__class][$type])) {
                        self::$__callbacks[$this->__class][$type] = array();
                    }
                    self::$__callbacks[$this->__class][$type][] = $method;
                }
            }
        }
    }
}

