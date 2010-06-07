<?php

Class extension_multilingual_field extends Extension {
	protected $addedPublishHeaders = false;
  
  // Simply outputs information to Symphony about the extension
  public function about() {
    $info = array(
      'author' => array(
        'email' => 'guillem@bajoelcocotero.com',
        'name' => 'Guillem Lorman',
        'website' => 'http://bajoelcocotero.com/'
      ),
      'name' => 'Field: Multilingual Text',
      'release-date' => '2010-06-01',
      'version' => '1.0'
    );
    
    return $info;
  }
  	
 
  // Creates the database to store all address fields
  public function install() {
		return $this->_Parent->Database->query("CREATE TABLE `tbl_fields_multilingual` (
      `id` int(11) unsigned NOT NULL auto_increment,
      `field_id` int(11) unsigned NOT NULL,
			`column_length` INT(11) UNSIGNED DEFAULT 75,
      `text_size` ENUM('single', 'small', 'medium', 'large', 'huge') DEFAULT 'medium',
      `formatter` VARCHAR(255) DEFAULT NULL,
      `text_validator` VARCHAR(255) DEFAULT NULL,
      `text_length` INT(11) UNSIGNED DEFAULT 0,
      PRIMARY KEY(`id`),
      KEY `field_id` (`field_id`)
    ) TYPE=MyISAM;");
  }
  
  // Removes the database
  public function uninstall() {
		$this->_Parent->Database->query("DROP TABLE `tbl_fields_multilingual`");
  }
	
	public function getSubscribedDelegates(){
		return array(
				array(
					'page' => '/system/preferences/',
					'delegate' => 'AddCustomPreferenceFieldsets',
					'callback' => 'appendPreferences'
				),

				array(
					'page' => '/system/preferences/',
					'delegate' => 'Save',
					'callback' => '__SavePreferences'
				),
		);
	}

	/*-------------------------------------------------------------------------*/
	/* !Preferences: */
	/*-------------------------------------------------------------------------*/	
	public function appendPreferences($context){

		$group = new XMLElement('fieldset');
		$group->setAttribute('class', 'settings');
		$group->appendChild(new XMLElement('legend', __('Multilingual Field')));


		$label = Widget::Label(__('Consolidate entry data'));
		$label->appendChild(Widget::Input('settings[multilingual][consolidate]', 'yes', 'checkbox'));

		$group->appendChild($label);

		$group->appendChild(new XMLElement('p', __('Check this field if you want to consolidate database removing entry values of removed/old Language Redirect language codes. Entry values of current language codes will not be affected.'), array('class' => 'help')));


		$context['wrapper']->appendChild($group);

	}

	public function __SavePreferences($context){
		
		$language_codes = explode(',', $_POST['settings']['language_redirect']['languages']);
		$language_codes = array_map('trim', $language_codes);
		$language_codes = array_filter($language_codes);
		
		$languages = $language_codes;

		foreach ($languages as $language) {
			$language = substr($language,0,2);
		}
		$languages = array_unique($languages);

		$fields = $this->_Parent->Database->fetch('SELECT field_id FROM tbl_fields_multilingual');

		if ($fields) {
			// Foreach field check multilanguage values foreach language
			foreach ($fields as $field) {
				$entries_table = 'tbl_entries_data_'.$field["field_id"];
	
				$show_columns = $this->_Parent->Database->fetch("SHOW COLUMNS FROM `{$entries_table}` LIKE 'value-%'");
				if ($show_columns) {
					$columns = array();
					
					foreach ($show_columns as $column) {
						$language = substr($column['Field'], strlen($column['Field'])-2);

						// If consolidate option AND column language not in supported languages codes -> Drop Column
						if ($_POST['settings']['multilingual']['consolidate'] && !in_array($language, $languages)) {
								$this->_Parent->Database->query("ALTER TABLE  `{$entries_table}` DROP COLUMN `value-{$language}`");
								$this->_Parent->Database->query("ALTER TABLE  `{$entries_table}` DROP COLUMN `word_count-{$language}`");
								$this->_Parent->Database->query("ALTER TABLE  `{$entries_table}` DROP COLUMN `value_format-{$language}`");
						} else {
							$columns[] = $column['Field'];
						}
					}

					// Add new fields
					foreach ($languages as $language) {
						// If columna language dosen't exist in the laguange drop columns						

						if (!in_array('value-'.$language, $columns)) {
							$this->_Parent->Database->query("ALTER TABLE  `{$entries_table}` ADD COLUMN `value-{$language}` TEXT DEFAULT NULL");
							$this->_Parent->Database->query("ALTER TABLE  `{$entries_table}` ADD COLUMN `word_count-{$language}` INT(11) UNSIGNED DEFAULT NULL");
							$this->_Parent->Database->query("ALTER TABLE  `{$entries_table}` ADD COLUMN `value_format-{$language}` TEXT DEFAULT NULL");
						} 
					}

				}
			}
		}

	}

	/*-------------------------------------------------------------------------*/
	/* Utilites: */
	/*-------------------------------------------------------------------------*/	
	public function addPublishHeaders($page) {
		if ($page and !$this->addedPublishHeaders) {
			$page->addStylesheetToHead(URL . '/extensions/multilingual_field/assets/publish.css', 'screen', 10251840);
			$page->addScriptToHead(URL . '/extensions/multilingual_field/assets/publish.js', 10251840);
			
			$this->addedPublishHeaders = true;
		}
	}
}