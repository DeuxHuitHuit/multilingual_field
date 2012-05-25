<?php
	if( !defined('__IN_SYMPHONY__') ) die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');



	require_once(TOOLKIT.'/class.xsltprocess.php');
	require_once(EXTENSIONS.'/frontend_localisation/lib/class.FLang.php');
	require_once(EXTENSIONS.'/textboxfield/fields/field.textbox.php');



	Class fieldMultilingual_TextBox extends FieldTextBox
	{

		/*------------------------------------------------------------------------------------------------*/
		/*  Definition  */
		/*------------------------------------------------------------------------------------------------*/

		public function __construct(){
			parent::__construct();

			$this->_name = __('Multilingual Text Box');
		}

		public function createTable(){
			$field_id = $this->get('id');

			$query = "
				CREATE TABLE IF NOT EXISTS `tbl_entries_data_{$field_id}` (
					`id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
					`entry_id` INT(11) UNSIGNED NOT NULL,
					`handle` VARCHAR(255) DEFAULT NULL,
					`value` TEXT DEFAULT NULL,
					`value_formatted` TEXT DEFAULT NULL,
					`word_count` INT(11) UNSIGNED DEFAULT NULL,";

			foreach( FLang::getLangs() as $lc )
				$query .= "
					`handle-{$lc}` VARCHAR(255) DEFAULT NULL,
				    `value-{$lc}` TEXT default NULL,
				    `value_formatted-{$lc}` TEXT default NULL,
				    `word_count-{$lc}` INT(11) UNSIGNED DEFAULT NULL,";

			$query .= "
					PRIMARY KEY (`id`),
					KEY `entry_id` (`entry_id`),";

			foreach( FLang::getLangs() as $lc )
				$query .= "
					KEY `handle-{$lc}` (`handle-{$lc}`),
					FULLTEXT KEY `value-{$lc}` (`value-{$lc}`),
					FULLTEXT KEY `value_formatted-{$lc}` (`value_formatted-{$lc}`),";

			$query .= "
					KEY `handle` (`handle`),
					FULLTEXT KEY `value` (`value`),
					FULLTEXT KEY `value_formatted` (`value_formatted`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";

			return Symphony::Database()->query($query);
		}



		/*------------------------------------------------------------------------------------------------*/
		/*  Utilities  */
		/*------------------------------------------------------------------------------------------------*/

		public function createHandle($value, $entry_id, $lang_code = null){
			if( FLang::validateLangCode($lang_code) ) $lang_code = FLang::getLangCode();

			$handle = Lang::createHandle(strip_tags(html_entity_decode($value)));

			if( $this->isHandleLocked($handle, $entry_id, $lang_code) ){
				if( $this->isHandleFresh($handle, $value, $entry_id, $lang_code) ){
					return $this->getCurrentHandle($entry_id);
				}

				else{
					$count = 2;

					while( $this->isHandleLocked("{$handle}-{$count}", $entry_id, $lang_code) ) $count++;

					return "{$handle}-{$count}";
				}
			}

			return $handle;
		}

		public function isHandleLocked($handle, $entry_id, $lang_code){
			return (boolean)Symphony::Database()->fetchVar('id', 0, sprintf(
				"
					SELECT
						f.id
					FROM
						`tbl_entries_data_%s` AS f
					WHERE
						f.`handle-%s` = '%s'
						%s
					LIMIT 1
				",
				$this->get('id'), $lang_code, $handle,
				(!is_null($entry_id) ? "AND f.entry_id != '{$entry_id}'" : '')
			));
		}

		public function isHandleFresh($handle, $value, $entry_id, $lang_code){
			return (boolean)Symphony::Database()->fetchVar('id', 0, sprintf(
				"
					SELECT
						f.id
					FROM
						`tbl_entries_data_%s` AS f
					WHERE
						f.entry_id = '%s'
						AND f.`value-%s` = '%s'
					LIMIT 1
				",
				$this->get('id'), $entry_id, $lang_code,
				$this->cleanValue(General::sanitize($value))
			));
		}



		/*------------------------------------------------------------------------------------------------*/
		/*  Settings  */
		/*------------------------------------------------------------------------------------------------*/

		public function findDefaults(&$fields){
			parent::findDefaults($fields);

			$fields['def_ref_lang'] = 'no';
		}

		public function displaySettingsPanel(XMLElement &$wrapper, $errors = null){
			parent::displaySettingsPanel($wrapper, $errors);

			foreach( $wrapper->getChildrenByName('ul') as /* @var XMLElement $list*/
			         $list ){

				if( $list->getAttribute('class') === 'options-list' ){
					$item = new XMLElement('li');

					$input = Widget::Input("fields[{$this->get('sortorder')}][def_ref_lang]", 'yes', 'checkbox');
					if( $this->get('def_ref_lang') == 'yes' ) $input->setAttribute('checked', 'checked');

					$item->appendChild(Widget::Label(
						__('%s Use value from main language if selected language has empty value.', array($input->generate()))
					));

					$list->appendChild($item);
				}
			}
		}

		public function commit($propogate = null){
			if( !parent::commit($propogate) ) return false;

			return Symphony::Database()->query(sprintf("
				UPDATE
					`tbl_fields_%s`
				SET
					`def_ref_lang` = '%s'
				WHERE
					`field_id` = '%s';",
				$this->handle(), $this->get('def_ref_lang') === 'yes' ? 'yes': 'no', $this->get('id')
			));
		}



		/*------------------------------------------------------------------------------------------------*/
		/*  Publish  */
		/*------------------------------------------------------------------------------------------------*/

		public function displayPublishPanel(&$wrapper, $data = null, $error = null, $prefix = null, $postfix = null){
			Extension_Frontend_Localisation::appendAssets();
			Extension_Multilingual_Field::appendHeaders(
				Extension_Multilingual_Field::PUBLISH_HEADERS
			);

			$main_lang = FLang::getMainLang();
			$all_langs = FLang::getAllLangs();
			$langs = FLang::getLangs();

			$wrapper->setAttribute('class', $wrapper->getAttribute('class').' field-multilingual');
			$container = new XMLElement('div', null, array('class' => 'container'));


			/*------------------------------------------------------------------------------------------------*/
			/*  Label  */
			/*------------------------------------------------------------------------------------------------*/

			$label = Widget::Label($this->get('label'));
			$optional = '';

			if( $this->get('required') != 'yes' ){
				if( (integer)$this->get('text_length') > 0 ){
					$optional = __('$1 of $2 remaining').' &ndash; '.__('Optional');
				}
				else{
					$optional = __('Optional');
				}
			}
			elseif( (integer)$this->get('text_length') > 0 ){
				$optional = __('$1 of $2 remaining');
			}

			if( $optional !== '' )
				foreach( $langs as $lc )
					$label->appendChild(new XMLElement('i', $optional, array('class' => 'tab-element tab-'.$lc, 'data-lang_code' => $lc)));

			$container->appendChild($label);


			/*------------------------------------------------------------------------------------------------*/
			/*  Tabs  */
			/*------------------------------------------------------------------------------------------------*/

			$ul = new XMLElement('ul', null, array('class' => 'tabs'));
			foreach( $langs as $lc ){
				$li = new XMLElement('li', $all_langs[$lc], array('class' => $lc));
				$lc === $main_lang ? $ul->prependChild($li) : $ul->appendChild($li);
			}

			$container->appendChild($ul);


			/*------------------------------------------------------------------------------------------------*/
			/*  Panels  */
			/*------------------------------------------------------------------------------------------------*/

			foreach( $langs as $lc ){
				$div = new XMLElement('div', null, array('class' => 'file tab-panel tab-'.$lc, 'data-lang_code' => $lc));

				$element_name = $this->get('element_name');

				// Input box:
				if( $this->get('text_size') === 'single' ){
					$input = Widget::Input(
						"fields{$prefix}[$element_name]{$postfix}[{$lc}]", General::sanitize($data['value-'.$lc])
					);

					###
					# Delegate: ModifyTextBoxInlineFieldPublishWidget
					# Description: Allows developers modify the textbox before it is rendered in the publish forms
					$delegate = 'ModifyTextBoxInlineFieldPublishWidget';
				}

				// Text Box:
				else{
					$input = Widget::Textarea(
						"fields{$prefix}[$element_name]{$postfix}[{$lc}]", 20, 50, General::sanitize($data['value-'.$lc])
					);

					###
					# Delegate: ModifyTextBoxFullFieldPublishWidget
					# Description: Allows developers modify the textbox before it is rendered in the publish forms
					$delegate = 'ModifyTextBoxFullFieldPublishWidget';
				}

				// Add classes:
				$classes = array('size-'.$this->get('text_size'));

				if( $this->get('text_formatter') != 'none' ){
					$classes[] = $this->get('text_formatter');
				}

				$input->setAttributeArray(array(
					'class' => implode(' ', $classes),
					'length' => (integer)$this->get('text_length')
				));

				Symphony::ExtensionManager()->notifyMembers(
					$delegate, '/backend/',
					array(
						'field' => $this,
						'label' => $div,
						'input' => $input,
						'textarea' => $input
					)
				);

				$div->appendChild($input);

				$container->appendChild($div);
			}


			/*------------------------------------------------------------------------------------------------*/
			/*  Errors  */
			/*------------------------------------------------------------------------------------------------*/

			if( $error != null ){
				$wrapper->appendChild(Widget::Error($container, $error));
			}
			else{
				$wrapper->appendChild($container);
			}
		}



		/*------------------------------------------------------------------------------------------------*/
		/*  Input  */
		/*------------------------------------------------------------------------------------------------*/

		public function checkPostFieldData($data, &$message, $entry_id = null){
			$error = self::__OK__;
			$field_data = $data;
			$all_langs = FLang::getAllLangs();
			$main_lang = FLang::getMainLang();

			foreach( FLang::getLangs() as $lc ){

				$file_message = '';
				$data = $field_data[$lc];

				$status = parent::checkPostFieldData($data, $file_message, $entry_id);

				// if one language fails, all fail
				if( $status != self::__OK__ ){

					if( $lc === $main_lang ){
						$message = "<br />{$all_langs[$lc]}: {$file_message}" . $message;
					}
					else{
						$message .= "<br />{$all_langs[$lc]}: {$file_message}";
					}

					$error = self::__ERROR__;
				}
			}

			return $error;
		}

		public function processRawFieldData($data, &$status, &$message = null, $simulate = false, $entry_id = null){
			if( !is_array($data) || empty($data) ) return parent::processRawFieldData($data, $status, $message, $simulate, $entry_id);

			$status = self::__OK__;
			$result = array();
			$field_data = $data;

			foreach( FLang::getLangs() as $lc ){

				$data = $field_data[$lc];

				$formatted = $this->applyFormatting($data);

				$result = array_merge($result, array(
					'handle-'.$lc => $this->createHandle($formatted, $entry_id, $lc),
					'value-'.$lc => (string)$data,
					'value_formatted-'.$lc => $formatted,
					'word_count-'.$lc => General::countWords($data)
				));
			}

			return $result;
		}



		/*------------------------------------------------------------------------------------------------*/
		/*  Output  */
		/*------------------------------------------------------------------------------------------------*/

		public function appendFormattedElement(XMLElement &$wrapper, $data, $encode = false, $mode = null){
			$lang_code = FLang::getLangCode();

			// If value is empty for this language, load value from main language
			if( $this->get('def_ref_lang') == 'yes' && $data['handle-'.$lang_code] === '' ){
				$lang_code = FLang::getMainLang();
			}

			$data['handle'] = $data['handle-'.$lang_code];
			$data['value'] = $data['value-'.$lang_code];
			$data['value_formatted'] = $data['value_formatted-'.$lang_code];
			$data['word_count'] = $data['word_count-'.$lang_code];

			parent::appendFormattedElement($wrapper, $data);

			$elem = $wrapper->getChildByName($this->get('element_name'), 0);

			if( !is_null($elem) )
				if( $this->get('text_handle') === 'yes' )
					foreach( FLang::getLangs() as $lc ){
						$elem->setAttribute("handle-{$lc}", $data["handle-{$lc}"]);
					}
		}

		public function prepareTableValue($data, XMLElement $link = null){
			$lang_code = FLang::getLangCode();

			// If value is empty for this language, load value from main language
			if( $this->get('def_ref_lang') == 'yes' && $data['handle-'.$lang_code] === '' ){
				$lang_code = FLang::getMainLang();
			}

			$data['value'] = $data['value-'.$lang_code];
			$data['value_formatted'] = $data['value_formatted-'.$lang_code];

			return parent::prepareTableValue($data, $link);
		}

		public function getParameterPoolValue($data){
			$lang_code = FLang::getLangCode();

			// If value is empty for this language, load value from main language
			if( $this->get('def_ref_lang') === 'yes' && $data['value-'.$lang_code] === '' ){
				$lang_code = FLang::getMainLang();
			}

			return $data['value-'.$lang_code];
		}

		public function getExampleFormMarkup(){
			$label = Widget::Label($this->get('label').'
					<!-- '.__('Modify just current language value').' -->
					<input name="fields['.$this->get('element_name').'][value-{$url-fl-language}]" type="text" />

					<!-- '.__('Modify all values').' -->');

			if( $this->get('text_size') === 'single' )
				foreach( FLang::getLangs() as $lc )
					$label->appendChild(Widget::Input("fields[{$this->get('element_name')}][value-{$lc}]"));
			else
				foreach( FLang::getLangs() as $lc )
					$label->appendChild(Widget::Textarea("fields[{$this->get('element_name')}][value-{$lc}]", 20, 50));

			return $label;
		}



		/*------------------------------------------------------------------------------------------------*/
		/*  Filtering  */
		/*------------------------------------------------------------------------------------------------*/

		public function buildDSRetrivalSQL($data, &$joins, &$where, $andOperation = false){
			parent::buildDSRetrivalSQL($data, $joins, $where, $andOperation);

			$lc = FLang::getLangCode();

			$where = str_replace('.value', ".`value-{$lc}`", $where);
			$where = str_replace('.handle', ".`handle-{$lc}`", $where);

			return true;
		}



		/*------------------------------------------------------------------------------------------------*/
		/*  Grouping  */
		/*------------------------------------------------------------------------------------------------*/

		public function groupRecords($records){
			$lc = FLang::getLangCode();

			$groups = array(
				$this->get('element_name') => array()
			);

			foreach( $records as $record ){
				$data = $record->getData($this->get('id'));

				$handle = $data['handle-'.$lc];
				$element = $this->get('element_name');

				if( !isset($groups[$element][$handle]) ){
					$groups[$element][$handle] = array(
						'attr' => array(
							'handle' => $handle
						),
						'records' => array(),
						'groups' => array()
					);
				}

				$groups[$element][$handle]['records'][] = $record;
			}

			return $groups;
		}



		/*------------------------------------------------------------------------------------------------*/
		/*  Field schema  */
		/*------------------------------------------------------------------------------------------------*/

		public function appendFieldSchema($f){}

	}
