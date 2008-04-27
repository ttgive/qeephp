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
class Behavior_Acluser extends QDB_ActiveRecord_Behavior_Abstract
{
    /**
     * 插件的设置信息
     *
     * @var array
     */
    protected $_settings = array(
        // 密码加密方式
        'encode_type'       => 'crypt',
        // 用户名属性名
        'username_prop'     => 'username',
        // 密码属性名
        'password_prop'     => 'password',
        // 电子邮件属性名
        'email_prop'        => 'email',
        // 账户注册 IP 属性名
        'register_ip_prop'  => 'register_ip',
        // 角色属性名
        'roles_prop'        => 'roles',
        // 角色名属性名
        'rolename_prop'     => 'name',
        // getAclData() 方法要获取的属性值
        'acldata_props'     => 'username',
        // 累计登录次数属性名，例如 login_count
        'login_count_prop'  => null,
        // 最后登录日期属性名，例如 login_at
        'login_at_prop'     => null,
        // 最后登录 IP 属性名，例如 login_ip
        'login_ip_prop'     => null,

        // 是否检查用户名的唯一性
        'unique_username'   => true,
        // 是否检查电子邮件的唯一性
        'unique_email'      => false,
        // 用户名重复时的错误信息
        'err_duplicate_username' => 'Duplicate username "%s".',
        // 电子邮件重复时的错误信息
        'err_duplicate_email'    => 'Duplicate email "%s".',
    );

    /**
     * 绑定行为插件
     */
    function bind()
    {
        $this->meta->addEventHandler(self::before_create, array($this, '_before_create'));
        $this->meta->addDynamicMethod('encodePassword',   array($this, 'encodePassword'));
        $this->meta->addDynamicMethod('checkPassword',    array($this, 'checkPassword'));
        $this->meta->addDynamicMethod('changePassword',   array($this, 'changePassword'));
        $this->meta->addDynamicMethod('updateLogin',      array($this, 'updateLogin'));
        $this->meta->addDynamicMethod('getAclData',       array($this, 'getAclData'));
        $this->meta->addDynamicMethod('getAclRoles',      array($this, 'getAclRoles'));
        $this->meta->update_reject[$this->_settings['password_prop']] = true;
    }

    /**
     * 在新建的 ActiveRecord 保存到数据库前，加密密码并填充 register_ip 属性
     *
     * @param QDB_ActiveRecord_Abstract $obj
     * @param array $props
     */
    function _before_create(QDB_ActiveRecord_Abstract $obj, array & $props)
    {
        if ($this->_settings['unique_username']) {
            $pn = $this->_settings['username_prop'];
            $username = $obj->{$pn};
            $row = $obj->getMeta()->table
                       ->find(array($pn => $username))
                       ->recursion(0)
                       ->count()
                       ->query();
            if (!empty($row) && $row['row_count'] > 0) {
                // 找到同名用户
                throw new QACL_User_Exception(sprintf($this->_settings['err_duplicate_username'], $username));
            }
        }

        if ($this->_settings['unique_email']) {
            $pn = $this->_settings['email_prop'];
            $email = $obj->{$pn};
            $row = $obj->getMeta()->table->find(array($pn => $email))->count()->query();
            if (!empty($row) && $row['row_count'] > 0) {
                // 找到相同的 EMAIL
                throw new QACL_User_Exception(sprintf($this->_settings['err_duplicate_email'], $email));
            }
        }

        $pn = $this->_settings['password_prop'];
        $obj->{$pn} = $this->encodePassword($obj->{$pn});

        $pn = $this->_settings['register_ip_prop'];
        if (isset($obj->{$pn})) {
            $obj->{$pn} = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'none';
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
        switch ($this->_settings['encode_type']) {
        case 'md5':
            return md5($password);
        case 'crypt':
        default:
        }
        return crypt($password);
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
    function checkPassword(QDB_ActiveRecord_Abstract $obj, array $props, $password)
    {
        $p = $this->_settings['password_prop'];
        switch ($this->_settings['encode_type']) {
        case 'md5':
            return md5($password) == $props[$p];
        case 'crypt':
        default:
        }
        return crypt($password, $props[$p]) == rtrim($props[$p]);
    }

    /**
     * 修改用户的密码
     *
     * @param QDB_ActiveRecord_Abstract $obj
     * @param array $props
     * @param string $old_password
     * @param string $new_password
     */
    function changePassword(QDB_ActiveRecord_Abstract $obj, $old_password, $new_password)
    {
        if ($obj->checkPassword($old_password)) {
            $p = $this->_settings['password_prop'];
            $new_password = $this->encodePassword($new_password);
            $row = array($obj->idname() => $obj->id(), $this->field_name($p) => $new_password);
            $obj->getMeta()->table->update($row, 0);
            $props[$p] = $new_password;
        } else {
            // LC_MSG: Change user password failed.
            throw new QACL_User_Exception(__('Change user password failed.'));
        }
    }

    /**
     * 更新用户登录信息
     *
     * @param QDB_ActiveRecord_Abstract $obj
     * @param array $props
     */
    function updateLogin(QDB_ActiveRecord_Abstract $obj)
    {
        $row = array();
        $p = $this->_settings['login_count_prop'];
        if (!empty($p)) {
            $obj->{$p}++;
            $row[$this->meta->prop2fields[$p]] = $obj->{$p};
        }

        $p = $this->_settings['login_at_prop'];
        if (!empty($p)) {
            $f = $this->meta->prop2fields[$p];
            if (!empty($this->ref['meta'][$f])) {
                if ($this->ref['meta'][$f]['ptype'] == 'i') {
                    $time = time();
                } else {
                    $time = date('Y/m/d H:i:s');
                }
                $props[$p] = $time;
                $row[$f] = $time;
            }
        }

        $p = $this->_settings['login_ip_prop'];
        if (!empty($p)) {
            $ip = !empty($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
            $props[$p] = $ip;
            $row[$this->meta->prop2fields[$p]] = $ip;
        }

        if (!empty($row)) {
            $obj->getMeta()->table->updateWhere($row, array($obj->idname() => $obj->id()));
        }
    }

    /**
     * 获得用户的 ACL 数据
     *
     * @param QDB_ActiveRecord_Abstract $obj
     * @param array $props
     * @param string $acldata_props
     *
     * @return array
     */
    function getAclData(QDB_ActiveRecord_Abstract $obj, array $props, $acldata_props = null)
    {
        if (is_null($acldata_props)) {
            $acldata_props = $this->_settings['acldata_props'];
        }
        $acldata_props = Q::normalize($acldata_props);
        $data = array();
        foreach ($acldata_props as $p) {
            if (isset($props[$p])) {
                $data[$p] = $props[$p];
            }
        }
        $data['id'] = $props[$obj->idname()];
        return $data;
    }

    /**
     * 获得包含用户所有角色名的数组
     *
     * @param QDB_ActiveRecord_Abstract $obj
     * @param array $props
     * @param string $roles_prop
     * @param string $rolename_prop
     *
     * @return array
     */
    function getAclRoles(QDB_ActiveRecord_Abstract $obj, array $props, $roles_prop = null, $rolename_prop = null)
    {
        if (is_null($roles_prop)) {
            $roles_prop = $this->_settings['roles_prop'];
        }
        if (is_null($rolename_prop)){
            $rolename_prop = $this->_settings['rolename_prop'];
        }
        $roles = array();
        if (empty($props[$roles_prop])) {
            return array();
        }
        foreach ($props[$roles_prop] as $role) {
            $roles[] = $role->{$rolename_prop};
        }
        return $roles;
    }

}
