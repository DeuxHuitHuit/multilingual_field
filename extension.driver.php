<?php

	if( !defined('__IN_SYMPHONY__') ) die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');



	require_once(EXTENSIONS.'/textboxfield/extension.driver.php');



	define_safe(MTB_NAME, 'Field: Multilingual Text Box');
	define_safe(MTB_GROUP, 'multilingual_field');



	Class Extension_Multilingual_Field extends Extension_TextBoxField
	{

		const FIELD_TABLE = 'tbl_fields_multilingual_textbox';



		/*------------------------------------------------------------------------------------------------*/
		/*  Installation  */
		/*------------------------------------------------------------------------------------------------*/

		public function install(){
			Symphony::Database()->query(sprintf("
				CREATE TABLE IF NOT EXISTS `%s` (
					`id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
					`field_id` INT(11) UNSIGNED NOT NULL,
					`column_length` INT(11) UNSIGNED DEFAULT 75,
					`text_size` ENUM('single', 'small', 'medium', 'large', 'huge') DEFAULT 'medium',
					`text_formatter` VARCHAR(255) DEFAULT NULL,
					`text_validator` VARCHAR(255) DEFAULT NULL,
					`text_length` INT(11) UNSIGNED DEFAULT 0,
					`text_cdata` ENUM('yes', 'no') DEFAULT 'no',
					`text_handle` ENUM('yes', 'no') DEFAULT 'no',
					`def_ref_lang` ENUM('yes','no') DEFAULT 'no',
					PRIMARY KEY (`id`),
					KEY `field_id` (`field_id`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;",
				self::FIELD_TABLE
			));

			return true;
		}

		public function update($prev_version){

			if( version_compare($prev_version, '2.0', '<') ){

				$v1x_table = 'tbl_fields_multilingual';

				$fields = Symphony::Database()->fetch(sprintf("SELECT field_id FROM `%s`", $v1x_table));
				
				if( version_compare($prev_version, '1.1', '<') ){
					foreach( $fields as $field ){
						$entries_table = 'tbl_entries_data_'.$field["field_id"];

						if( !$this->updateHasColumn('value', $entries_table) )
							Symphony::Database()->query("ALTER TABLE `{$entries_table}` ADD COLUMN `value` TEXT DEFAULT NULL");

					}

				}

				if( version_compare($prev_version, '1.2', '<') ){
					foreach( $fields as $field ){
						$entries_table = 'tbl_entries_data_'.$field["field_id"];

						foreach( FLang::getLangs() as $lc ){
							if( !$this->updateHasColumn('handle-'.$lc, $entries_table) ){
								Symphony::Database()->query("ALTER TABLE `{$entries_table}` ADD COLUMN `handle-{$lc}` TEXT DEFAULT NULL");

								$values = Symphony::Database()->fetch("SELECT `id`, `entry_id`, `value-{$lc}` FROM `{$entries_table}` WHERE `handle` IS NOT NULL");
								foreach( $values as $value ){
									Symphony::Database()->query("UPDATE  `{$entries_table}` SET `handle-{$lc}` = '".$this->__createHandle($value["value-".$lc], $value["entry_id"], $lc, $entries_table)."' WHERE id = ".$value["id"]);
								}
							}

						}

					}
				}

				if( version_compare($prev_version, '1.4', '<') ){
					Symphony::Database()->query(sprintf("ALTER TABLE `%s` ADD COLUMN `unique_handle` ENUM('yes','no') DEFAULT 'yes'", $v1x_table));
					Symphony::Database()->query(sprintf("UPDATE `%s` SET `unique_handle` = 'yes'", $v1x_table));
				}

				if( version_compare($prev_version, '1.4.1', '<') ){
					Symphony::Database()->query(sprintf("ALTER TABLE `%s` ADD COLUMN `use_def_lang_vals` ENUM('yes','no') DEFAULT 'yes'", $v1x_table));
					Symphony::Database()->query(sprintf("UPDATE `%s` SET `use_def_lang_vals` = 'yes'", $v1x_table));
				}
				
				if( version_compare($prev_version, '2.0', '<') ){
				
					Symphony::Database()->query(sprintf(
						"RENAME TABLE `%s` TO `%s`;",
						$v1x_table, self::FIELD_TABLE
					));

					Symphony::Database()->query(sprintf(
						"UPDATE `tbl_fields` SET `type` = '%s' WHERE `type` = '%s'",
						'multilingual_textbox', 'multilingual'
					));

					Symphony::Database()->query(sprintf(
						"ALTER TABLE `%s`
							CHANGE `formatter` `text_formatter` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
							CHANGE `unique_handle` `text_handle` ENUM('yes', 'no') CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT 'yes',
							CHANGE `use_def_lang_vals` `def_ref_lang`  ENUM('yes', 'no') CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT 'no',
							MODIFY `text_validator` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
							MODIFY `text_size` ENUM('single', 'small', 'medium', 'large', 'huge') CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT 'medium',
							ADD `text_cdata` ENUM('yes', 'no') CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT 'no';",
						self::FIELD_TABLE
					));

					Symphony::Database()->query(sprintf(
						"UPDATE  `%s` SET `text_cdata` = 'no'",
						self::FIELD_TABLE
					));

					foreach( $fields as $field ){
						$entries_table = 'tbl_entries_data_'.$field["field_id"];

						Symphony::Database()->query(sprintf(
							'ALTER TABLE `%1$s`
								MODIFY `handle` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
								MODIFY `value` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;',
							$entries_table
						));

						foreach( FLang::getLangs() as $lc ){
							if( !$this->updateHasColumn('value_formatted-'.$lc, $entries_table) ){
								Symphony::Database()->query(sprintf(
									'ALTER TABLE `%1$s`
										CHANGE COLUMN `value_format-%2$s` `value_formatted-%2$s` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
										MODIFY `handle-%2$s` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
										MODIFY `value-%2$s` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
										MODIFY `word_count-%2$s` INT(11) UNSIGNED DEFAULT NULL,
										ADD FULLTEXT KEY `value-%2$s` (`value-%2$s`),
										ADD FULLTEXT KEY `value_formatted-%2$s` (`value_formatted-%2$s`);',
									$entries_table, $lc
								));
							}

						}

					}

				}
			}

			return true;
		}

		public function uninstall(){
			Symphony::Database()->query(sprintf(
				"DROP TABLE `%s`",
				self::FIELD_TABLE
			));

			return true;
		}

		private function __createHandle($value, $entry_id, $lang, $tbl){

			$handle = Lang::createHandle(strip_tags(html_entity_decode($value)));

			if( $this->__isHandleLocked($handle, $entry_id, $lang, $tbl) ){
				$count = 2;

				while( $this->__isHandleLocked("{$handle}-{$count}", $entry_id, $lang, $tbl) ) $count++;

				return "{$handle}-{$count}";
			}

			return $handle;
		}

		private function __isHandleLocked($handle, $entry_id, $lang, $tbl){
			return (boolean)Symphony::Database()->fetchVar('id', 0, sprintf(
				"
				SELECT
					f.id
				FROM
					`{$tbl}` AS f
				WHERE
					f.`handle-{$lang}` = '%s'
					%s
				LIMIT 1
			",
				$handle,
				(!is_null($entry_id) ? "AND f.entry_id != '{$entry_id}'" : '')
			));
		}



		/*------------------------------------------------------------------------------------------------*/
		/*  Public utilities  */
		/*------------------------------------------------------------------------------------------------*/

		/**
		 * Add headers to the page.
		 *
		 * @param $type
		 */
		static public function appendHeaders($type){
			if(
				(self::$appendedHeaders & $type) !== $type
				&& class_exists('Administration')
				&& Administration::instance() instanceof Administration
				&& Administration::instance()->Page instanceof HTMLPage
			){
				$page = Administration::instance()->Page;

				if( $type === self::PUBLISH_HEADERS ){
					$page->addStylesheetToHead(URL.'/extensions/'.MTB_GROUP.'/assets/'.MTB_GROUP.'.publish.css', 'screen');
					$page->addScriptToHead(URL.'/extensions/'.MTB_GROUP.'/assets/'.MTB_GROUP.'.publish.js');
				}

				if( $type === self::SETTING_HEADERS ){
					$page->addStylesheetToHead(URL.'/extensions/textboxfield/assets/textboxfield.settings.css', 'screen');
				}

				self::$appendedHeaders &= $type;
			}
		}



		/*------------------------------------------------------------------------------------------------*/
		/*  Delegates  */
		/*------------------------------------------------------------------------------------------------*/

		public function getSubscribedDelegates(){
			return array(
				array(
					'page' => '/system/preferences/',
					'delegate' => 'AddCustomPreferenceFieldsets',
					'callback' => 'dAddCustomPreferenceFieldsets'
				),
				array(
					'page' => '/extensions/frontend_localisation/',
					'delegate' => 'FLSavePreferences',
					'callback' => 'dFLSavePreferences'
				),
			);
		}



		/*------------------------------------------------------------------------------------------------*/
		/*  System preferences  */
		/*------------------------------------------------------------------------------------------------*/

		/**
		 * Display options on Preferences page.
		 *
		 * @param array $context
		 */
		public function dAddCustomPreferenceFieldsets($context){
			$group = new XMLElement('fieldset');
			$group->setAttribute('class', 'settings');
			$group->appendChild(new XMLElement('legend', __(MTB_NAME)));

			$label = Widget::Label(__('Consolidate entry data'));
			$label->appendChild(Widget::Input('settings['.MTB_GROUP.'][consolidate]', 'yes', 'checkbox', array('checked' => 'checked')));
			$group->appendChild($label);
			$group->appendChild(new XMLElement('p', __('Check this field if you want to consolidate database by <b>keeping</b> entry values of removed/old Language Driver language codes. Entry values of current language codes will not be affected.'), array('class' => 'help')));

			$context['wrapper']->appendChild($group);
		}

		/**
		 * Save options from Preferences page
		 *
		 * @param array $context
		 */
		public function dFLSavePreferences($context){
			$fields = Symphony::Database()->fetch(sprintf('SELECT `field_id` FROM `%s`', self::FIELD_TABLE));

			if( $fields ){
				// Foreach field check multilanguage values foreach language
				foreach( $fields as $field ){
					$entries_table = 'tbl_entries_data_'.$field["field_id"];

					try{
						$show_columns = Symphony::Database()->fetch("SHOW COLUMNS FROM `{$entries_table}` LIKE 'handle-%';");
					}
					catch( DatabaseException $dbe ){
						// Field doesn't exist. Better remove it's settings
						Symphony::Database()->query(sprintf(
								"DELETE FROM `%s` WHERE `field_id` = %s;",
								self::FIELD_TABLE, $field["field_id"])
						);
						continue;
					}

					$columns = array();

					// Remove obsolete fields
					if( $show_columns ){
						foreach( $show_columns as $column ){
							$lc = substr($column['Field'], strlen($column['Field']) - 2);

							// If not consolidate option AND column lang_code not in supported languages codes -> Drop Column
							if( ($_POST['settings'][MTB_GROUP]['consolidate'] !== 'yes') && !in_array($lc, $context['new_langs']) ){
								Symphony::Database()->query(sprintf("
									ALTER TABLE `%s`
										DROP COLUMN `handle-{$lc}`,
										DROP COLUMN `value-{$lc}`,
										DROP COLUMN `value_formatted-{$lc}`,
										DROP COLUMN `word_count-{$lc}`;",
									$entries_table));
							} else{
								$columns[] = $column['Field'];
							}
						}
					}

					// Add new fields
					foreach( $context['new_langs'] as $lc ){
						// If column lang_code dosen't exist in the laguange drop columns

						if( !in_array('handle-'.$lc, $columns) ){
							Symphony::Database()->query(sprintf("
								ALTER TABLE `%s`
									ADD COLUMN `handle-{$lc}` varchar(255) default NULL,
									ADD COLUMN `value-{$lc}` int(11) unsigned NULL,
									ADD COLUMN `value_formatted-{$lc}` varchar(255) default NULL,
									ADD COLUMN `word_count-{$lc}` varchar(50) default NULL;",
								$entries_table));
						}
					}

				}
			}
		}
	}
