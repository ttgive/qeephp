<?php
/////////////////////////////////////////////////////////////////////////////
// QeePHP Framework
//
// Copyright (c) 2005 - 2008 QeeYuan China Inc. (http://www.qeeyuan.com)
//
// 许可协议，请查看源代码中附带的 LICENSE.TXT 文件，
// 或者访问 http://www.qeephp.org/ 获得详细信息。
/////////////////////////////////////////////////////////////////////////////

/**
 * 定义 Behavior_Acluser 类
 *
 * @package database
 * @version $Id$
 */

/**
 * Behavior_Acluser 类实现了基于 ACL 的用户访问控制
 *
 * @package database
 */
class Behavior_Acluser implements QDB_ActiveRecord_Behavior_Interface
{
    /**
     * 插件的设置信息
     *
     * @var array
     */
    public $settings = array(
        'encode_type'       => 'crypt',
        'password_field'    => 'password',
        'register_ip_field' => 'register_ip',
        'roles_field'       => 'roles',
        'rolename_field'    => 'name',
        'acldata_fields'    => 'id, username',
    );

    /**
     * 该方法返回行为插件定义的回调事件以及扩展的方法
     *
     * @return array
     */
    function __callbacks()
    {
        return array(
            // 新对象保存到数据库前调用
            array(self::before_create,   array($this, 'beforeCreate')),
            // 为 ActiveRecord 对象增加一个 checkPassword() 方法
            array(self::custom_callback, array($this, 'checkPassword')),
            // 为 ActiveRecord 对象增加一个 getAclData() 方法
            array(self::custom_callback, array($this, 'getAclData')),
            // 为 ActiveRecord 对象增加一个 getAclRoles() 方法
            array(self::custom_callback, array($this, 'getAclRoles')),
        );
    }

    /**
     * 构造函数
     *
     * @param string $class
     * @param array $settings
     */
    function __construct($class, array $settings)
    {
        if (!empty($settings['encode_type'])) {
            $this->settings['encode_type'] = $settings['encode_type'];
        }
        if (!empty($settings['password_field'])) {
            $this->settings['password_field'] = $settings['password_field'];
        }
    }

    /**
     * 对密码字段加密
     *
     * @param string $password
     *
     * @return string;
     */
    function encodePassword($password)
    {
        switch ($this->settings['encode_type']) {
        case 'md5':
            return md5($password);
        case 'crypt':
        default:
            return crypt($password);
        }
    }

    /**
     * 在 ActiveRecord 保存到数据库前，填充 register_ip 属性
     *
     * @param QDB_ActiveRecord_Abstract $obj
     * @param array $props
     */
    function beforeCreate(QDB_ActiveRecord_Abstract $obj, array & $props)
    {
        $f = $this->settings['password_field'];
        $props[$f] = $this->encodePassword($props[$f]);
        $f = $this->settings['register_ip_field'];
        if (array_key_exists($f, $props)) {
            $props[$f] = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'none';
        }
    }

    /**
     * 获得用户的 ACL 数据
     *
     * @param QDB_ActiveRecord_Abstract $obj
     * @param array $props
     * @param string $acldata_fields
     *
     * @return array
     */
    function getAclData(QDB_ActiveRecord_Abstract $obj, array & $props, $acldata_fields = null)
    {
        if (is_null($acldata_fields)) {
            $acldata_fields = $this->settings['acldata_fields'];
        }
        $acldata_fields = Q::normalize($acldata_fields);
        $data = array();
        foreach ($acldata_fields as $f) {
            $data[$f] = $props[$f];
        }
        return $data;
    }

    /**
     * 获得包含用户所有角色名的数组
     *
     * @param QDB_ActiveRecord_Abstract $obj
     * @param array $props
     * @param string $roles_field
     * @param string $rolename_field
     *
     * @return array
     */
    function getAclRoles(QDB_ActiveRecord_Abstract $obj, array & $props, $roles_field = null, $rolename_field = null)
    {
        if (is_null($roles_field)) {
            $roles_field = $this->settings['roles_field'];
        }
        if (is_null($rolename_field)){
            $rolename_field = $this->settings['rolename_field'];
        }
        $roles = array();
        foreach ($props[$roles_field] as $role) {
            $roles[] = $role->{$rolename_field};
        }
        return $roles;
    }

    /**
     * 检查密码是否符合要求
     *
     * @param QDB_ActiveRecord_Abstract $obj
     * @param array $props
     * @param string $password
     *
     * @return boolean
     */
    function checkPassword(QDB_ActiveRecord_Abstract $obj, array & $props, $password)
    {
        switch ($this->settings['encode_type']) {
        case 'md5':
            return md5($password) == $props['password'];
        case 'crypt':
        default:
            return crypt($password) == $props['password'];
        }
    }
}