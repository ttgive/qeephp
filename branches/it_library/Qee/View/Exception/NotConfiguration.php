<?php
/////////////////////////////////////////////////////////////////////////////
// 这个文件是 QeePHP 项目的一部分
//
// Copyright (c) 2007 - 2008 QeePHP.org (www.qeephp.org)
//
// 要查看完整的版权信息和许可信息，请查看源代码中附带的 COPYRIGHT 文件，
// 或者访问 http://www.qeephp.org/ 获得详细信息。
/////////////////////////////////////////////////////////////////////////////

/**
 * 定义 Qee_View_Exception_NotConfiguration 类
 *
 * @copyright Copyright (c) 2007 - 2008 QeePHP.org (www.qeephp.org)
 * @author 起源科技(www.qeeyuan.com)
 * @package Exception
 * @version $Id$
 */

/**
 * Qee_View_Exception_NotConfiguration 表示开发者没有提供初始化模版引擎需要的设置
 *
 * @package Exception
 * @author 起源科技(www.qeeyuan.com)
 * @version 1.0
 */
class Qee_View_Exception_NotConfiguration extends Qee_Exception
{
    public $engineName;

    public function __construct($engineName)
    {
        parent::__construct(self::t('Template engine "%s" config not set.', $engineName));
    }
}