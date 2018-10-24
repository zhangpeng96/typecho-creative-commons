<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
/**
 * Creative Commons 4.0 for Typecho，共享你的知识
 *
 * @package CreativeCommons
 * @author zhangpeng96
 * @version 0.6.2
 */
class CreativeCommons_Plugin implements Typecho_Plugin_Interface
{
	
	/**
	 * 激活插件方法,如果激活失败,直接抛出异常
	 * 
	 * @access public
	 * @return void
	 * @throws Typecho_Plugin_Exception
	 */
	public static function activate()
	{
		$info = CreativeCommons_Plugin::sqlInstall();
		Typecho_Plugin::factory('admin/write-post.php')->option = array(__CLASS__, 'setTemplate');
        Typecho_Plugin::factory('Widget_Abstract_Contents')->contentEx = array('CreativeCommons_Plugin', 'render');
		Typecho_Plugin::factory('Widget_Contents_Post_Edit')->finishPublish = array(__CLASS__, "updateTemplate");
		Typecho_Plugin::factory('Widget_Archive')->singleHandle = array('CreativeCommons_Plugin', 'singleHandle');
		Typecho_Plugin::factory('Widget_Archive')->select = array('CreativeCommons_Plugin', 'selectHandle');
		return _t($info);
	}

	//SQL创建
	public static function sqlInstall()
	{
		$db = Typecho_Db::get();
		$type = explode('_', $db->getAdapterName());
		$type = array_pop($type);
		$prefix = $db->getPrefix();
		try {
			$select = $db->select('table.contents.cc')->from('table.contents');
			$db->query($select, Typecho_Db::READ);
			return '检测到字段，插件启用成功';
		} catch (Typecho_Db_Exception $e) {
			$code = $e->getCode();
			if(('Mysql' == $type && 1054 == $code) ||
					('SQLite' == $type && ('HY000' == $code || 1 == $code))) {
				try {
					if ('Mysql' == $type) {
						$db->query("ALTER TABLE `".$prefix."contents` ADD `cc` VARCHAR(32) NOT NULL;");
					} else if ('SQLite' == $type) {
						$db->query("ALTER TABLE `".$prefix."contents` ADD `cc` VARCHAR(255) NOT NULL");
					} else {
						throw new Typecho_Plugin_Exception('不支持的数据库类型：'.$type);
					}
					return '建立字段，插件启用成功';
				} catch (Typecho_Db_Exception $e) {
					$code = $e->getCode();
					if(('Mysql' == $type && 1060 == $code) ) {
						return '字段已经存在，插件启用成功';
					}
					throw new Typecho_Plugin_Exception('插件启用失败。错误号：'.$code);
				}
			}
			throw new Typecho_Plugin_Exception('数据表检测失败，插件启用失败。错误号：'.$code);
		}
	}

    /**
     * 禁用插件方法,如果禁用失败,直接抛出异常
     * 
     * @static
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function deactivate(){}
    
    /**
     * 获取插件配置面板
     * 
     * @access public
     * @param Typecho_Widget_Helper_Form $form 配置面板
     * @return void
     */
    public static function config(Typecho_Widget_Helper_Form $form){
        $types = array(
            'cc0'       => 'CC0 (公共领域)',
            'by'        => 'BY (署名)',
            'by-sa'     => 'BY-SA (署名-相同方式共享)',
            'by-nc'     => 'BY-NC (署名-非商业性)',
            'by-nc-sa'  => 'BY-NC-SA (署名-非商业性-相同方式共享)',
            'by-nd'     => 'BY-ND (署名-禁止演绎)',
            'by-nc-nd'  => 'BY-NC-ND (署名-非商业性-禁止演绎)'
        );
        $defaultCC = new Typecho_Widget_Helper_Form_Element_Select('defaultCC', $types, 'cc0', _t('默认协议'));
        $form->addInput($defaultCC);
    }
 
    public static function personalConfig(Typecho_Widget_Helper_Form $form) { }
    
    /**
     * 插件实现方法
     * 
     * @access public
     * @return void
     */

	public static function selectHandle($archive)
	{
		$db = Typecho_Db::get();
		$options = Typecho_Widget::widget('Widget_Options');
		return $db->select('*')->from('table.contents')->where('table.contents.status = ?', 'publish')
                ->where('table.contents.created < ?', $options->gmtTime);
	}

    public static function singleHandle($select, $archive) { }

	public static function setTemplate($post) {
		$db = Typecho_Db::get();
		$row = $db->fetchRow($db->select('cc')->from('table.contents')->where('cid = ?', $post->cid));
		$cc = implode('', $row);
		$settings = Helper::options()->plugin('CreativeCommons');

		$selectedValue = ['cc0','by','by-sa','by-nc','by-nc-sa','by-nd','by-nc-nd'];

		if ($row == NULL) {
			$cc = $settings->defaultCC;
		}

		for ($i=0; $i<7; $i++) {
			if ($cc == $selectedValue[$i]) {
				$selected[$i] = 'selected';
			} else {
				$selected[$i] = '';
			}
		}

		$html_select = '<section class="typecho-post-option">
	<label for="template" class="typecho-label">知识共享 Creative Commons</label>
	<p><select id="creativecommons" name="creativecommons" class="text-l w-100">
			<option value="cc0" '.$selected[0].'>CC0 (公共领域)</option>
			<option value="by" '.$selected[1].'>BY (署名)</option>
			<option value="by-sa" '.$selected[2].'>BY-SA (署名-相同方式共享)</option>
			<option value="by-nc" '.$selected[3].'>BY-NC (署名-非商业性)</option>
			<option value="by-nc-sa" '.$selected[4].'>BY-NC-SA (署名-非商业性-相同方式共享)</option>
			<option value="by-nd" '.$selected[5].'>BY-ND (署名-禁止演绎)</option>
			<option value="by-nc-nd" '.$selected[6].'>BY-NC-ND (署名-非商业性-禁止演绎)</option>
	</select></p>
</section>';
		$html = $html_select;

		_e($html);
	}
	public static function updateTemplate($contents, $post){
		$CreativeCommons = $post->request->get('creativecommons', NULL);
		$db = Typecho_Db::get();
		$sql = $db->update('table.contents')->rows(array('cc' => $CreativeCommons))->where('cid = ?', $post->cid);
		$db->query($sql);
	}


    /**
     * 插件实现方法
     * 
     * @access public
     * @return void
     */
    public static function render($text)
    {
    	$content = $text;

    	$content .= '<hr />';
		$content .= '<div class="CreativeCommons">';
        $content .=     '<blockquote>';
        $content .= 	   '<div>本作品采用 <a href="http://creativecommons.org/licenses/by-nc-sa/4.0/">知识共享-署名-相同方式分享 4.0 国际许可协议</a> 授权，转载时请注明出处</div>';
        $content .=     '</blockquote>';
		$content .= '</div>';
		return $content;
    }
    
}
