<?php if (!defined('__IN_SYMPHONY__')) die('No direct script access.');
	
require_once(TOOLKIT . '/class.xsltprocess.php');

Class fieldMultilingual extends Field {
  
	protected $_sizes = array();
	protected $_supported_language_codes = array();
	protected $_current_language = array();
	protected $_driver = null;

	// I don't know those languages, so if You know for sure that browser uses different code,
	// or that native name should be different, please let me know about that :).
	// It would also be great, if whole string could be in native form, including name of country.
	public $_lang = array(						// [English name]
		'ab' => 'аҧсуа бызшәа',					// Abkhazian
		'af' => 'Afrikaans',					// Afrikaans
		'sq' => 'Shqip',						// Albanian
		'am' => 'አማርኛ',							// Amharic
		'ar' => 'العربية',			// Arabic
		'hy' => 'Հայերեն',						// Armenian
		'as' => 'অসমীয়া',							// Assamese
		'az' => 'Azərbaycan',					// Azeri
		'eu' => 'Euskera',						// Basque
		'be' => 'Беларуская',					// Belarusian
		'bn' => 'বাংলা',							// Bengali
		'bg' => 'Български',					// Bulgarian
		'ca' => 'Català',						// Catalan
		'zh' => '中文',							// Chinese
		'hr' => 'Hrvatski',						// Croatian
		'cs' => 'čeština',						// Czech
		'da' => 'Dansk',						// Danish
		'dv' => 'ދިވެހި',							// Divehi
		'nl' => 'Nederlands (Netherlands)',		// Dutch
		'en' => 'English',						// English
		'ee' => 'Ɛʋɛ',							// Ewe
		'et' => 'Eesti',						// Estonian
		'fo' => 'føroyskt',						// Faeroese
		'fa' => 'فارسی',						// Farsi
		'fi' => 'Suomi',						// Finnish
		'fr' => 'Français',						// French
		'ff' => 'Fulfulde, Pulaar, Pular',		// Fula, Fulah, Fulani
		'gl' => 'Galego',						// Galician
		'gd' => 'Gàidhlig',						// Gaelic (Scottish)
		'ga' => 'Gaeilge',						// Gaelic (Irish)
		'gv' => 'Gaelg',						// Gaelic (Manx) (Isle of Man)
		'ka' => 'ქართული ენა',					// Georgian
		'de' => 'Deutsch',						// German
		'el' => 'Ελληνικά',						// Greek
		'gu' => 'ગુજરાતી',							// Gujarati
		'ha' => 'هَوْسَ',							// Hausa
		'he' => 'עברית',						// Hebrew
		'hi' => 'हिंदी',							// Hindi
		'hu' => 'Magyar',						// Hungarian
		'is' => 'Íslenska',						// Icelandic
		'id' => 'Bahasa Indonesia',				// Indonesian
		'it' => 'Italiano',						// Italian
		'ja' => '日本語',							// Japanese
		'kn' => 'ಕನ್ನಡ',						// Kannada
		'kk' => 'Қазақ',						// Kazakh
		'rw' => 'Kinyarwanda',					// Kinyarwanda
		'kok' => 'कोंकणी',							// Konkani
		'ko' => '한국어/조선말',					// Korean
		'kz' => 'Кыргыз',						// Kyrgyz
		'lv' => 'Latviešu',						// Latvian
		'lt' => 'Lietuviškai',					// Lithuanian
		'luo'=> 'Dholuo',						// Luo
		'ms' => 'Bahasa Melayu',				// Malay
		'mk' => 'Македонски',					// Macedonian
		'ml' => 'മലയാളം',							// Malayalam
		'mt' => 'Malti',						// Maltese
		'mr' => 'मराठी',							// Marathi
		'mn' => 'Монгол',						// Mongolian  (Cyrillic)
		'ne' => 'नेपाली',							// Nepali
		'nb' => 'Norsk bokmål',					// Norwegian Bokmål
		'nn' => 'Norsk nynorsk',				// Norwegian Nynorsk
		'no' => 'Norsk',						// Norwegian
		'or' => 'ଓଡ଼ିଆ',							// Oriya
		'ps' => 'پښتو',						// Pashto
		'pl' => 'polski',						// Polish
		'pt-br' => 'português brasileiro',		// Portuguese (Brasil)
		'pt' => 'português',					// Portuguese
		'pa' => 'پنجابی/ਪੰਜਾਬੀ',					// Punjabi
		'qu' => 'Runa Simi/Kichwa',				// Quechua
		'rm' => 'Romansch',						// Rhaeto-Romanic
		'ro' => 'Română',						// Romanian
		'rn' => 'kiRundi', 						// Rundi
		'ru' => 'Pyccĸий',						// Russian
		'sg' => 'yângâ tî sängö',				// Sango
		'sa' => 'संस्कृतम्',							// Sanskrit
		'sc' => 'sardu',						// Sardinian
		'sr' => 'Srpski/српски',				// Serbian
		'sn' => 'chiShona',						// Shona
		'ii' => 'ꆇꉙ',							// Sichuan Yi
		'si' => 'සිංහල',						// Sinhalese, Sinhala
		'sk' => 'Slovenčina',					// Slovak
		'ls' => 'Slovenščina',					// Slovenian
		'so' => 'Soomaaliga/af Soomaali',		// Somali
		'st' => 'Sesotho',						// Sotho, Sutu
		'es' => 'Español',						// Spanish
		'sw' => 'Kiswahili',					// Swahili
		'sv' => 'Svenska',						// Swedish
		'syr' => 'ܣܘܪܝܝܐ',						// Syriac
		'ta' => 'தமிழ்',							// Tamil
		'tt' => 'татарча/تاتارچا',				// Tatar
		'te' => 'తెలుగు',							// Telugu
		'th' => 'ภาษาไทย',						// Thai
		'ti' => 'ትግርኛ',							// Tigrinya
		'ts' => 'Xitsonga',						// Tsonga
		'tn' => 'Setswana',						// Tswana
		'tr' => 'Türkçe',						// Turkish
		'tk' => 'Түркмен',						// Turkmen
		'ug' => 'ئۇيغۇرچە‎/Uyƣurqə/Уйғурчә',	// Uighur, Uyghur
		'uk' => 'Українська',					// Ukrainian
		'ur' => 'اردو',							// Urdu
		'uz' => 'o\'zbek',						// Uzbek
		've' => 'Tshivenḓa',					// Venda
		'vi' => 'Tiếng Việt',					// Vietnamese
		'wa' => 'walon',						// Waloon
		'cy' => 'Cymraeg',						// Welsh
		'wo' => 'Wolof',						// Wolof
		'xh' => 'isiXhosa',						// Xhosa
		'yi' => 'ייִדיש',						// Yiddish
		'yo' => 'Yorùbá',						// Yoruba
		'zu' => 'isiZulu',						// Zulu
	);

	/*------------------------------------------------------------------------- */
	/* !Definition: */
	/*------------------------------------------------------------------------- */

  public function __construct(&$parent) {
    parent::__construct($parent);
    $this->_name = 'Multilingual Text';
    $this->_required = true;
		$this->_driver = $this->_engine->ExtensionManager->create('multilingual_field');


		// Get supported languages
		$this->_supported_language_codes = $this->getSupportedLanguageCodes();
		$this->_current_language = $this->getCurrentLanguage();

		// Set defaults:
		$this->set('show_column', 'yes');
		$this->set('size', 'medium');
		$this->set('required', 'yes');		

		$this->_sizes = array(
			array('single', false, __('Single Line')),
			array('small', false, __('Small Box')),
			array('medium', false, __('Medium Box')),
			array('large', false, __('Large Box')),
			array('huge', false, __('Huge Box'))
		);
  }
  
  public function commit() {

    if (!parent::commit()) { return false; }
    
    $id = $this->get('id');
    $handle = $this->handle();
    if ($id === false) { return false; }

		$fields = array(
			'field_id'			=> $id,
			'column_length'		=> (
				(integer)$this->get('column_length') > 25
				? $this->get('column_length')
				: 25
			),
			'text_size'			=> $this->get('text_size'),
			'formatter'	=> $this->get('formatter'),
			'text_validator'	=> $this->get('text_validator'),
			'text_length'		=> (
				(integer)$this->get('text_length') > 0
				? $this->get('text_length')
				: 0
			)
		);
		
		$this->Database->query("
			DELETE FROM
				`tbl_fields_{$handle}`
			WHERE
				`field_id` = '{$id}'
			LIMIT 1
		");
				
		return $this->Database->insert($fields, "tbl_fields_{$handle}");
							
  }
  
  // Create the table for storing the field's data
  public function createTable() {
  
    $id = $this->get('id');

    $query = "CREATE TABLE IF NOT EXISTS `tbl_entries_data_$id` (
      `id` int(11) unsigned NOT NULL auto_increment,
    	`entry_id` int(11) unsigned NOT NULL,
    	`handle` VARCHAR(255) DEFAULT NULL,";

    	foreach($this->_supported_language_codes as $language) {
    		$query .= "`value-".$language."` TEXT DEFAULT NULL,
				`word_count-".$language."` INT(11) UNSIGNED DEFAULT NULL,
				`value_format-".$language."` TEXT DEFAULT NULL,";
    	}
    	
			$query .= "PRIMARY KEY (`id`),
			KEY `entry_id` (`entry_id`)
    )";

		return $this->_engine->Database->query($query);
		
  }
  
	public function allowDatasourceOutputGrouping() {
		return true;
	}
	
	public function allowDatasourceParamOutput() {
		return true;
	}
	
	public function canFilter() {
		return true;
	}
	
	public function canPrePopulate() {
		return true;
	}
	
	public function isSortable() {
		return true;
	}
	
	/*------------------------------------------------------------------------- */
	/* !Utilities: */
	/* -------------------------------------------------------------------------*/
		
	public function createHandle($value, $entry_id) {
		$handle = Lang::createHandle(strip_tags(html_entity_decode($value)));
		
		if ($this->isHandleLocked($handle, $entry_id)) {
			if ($this->isHandleFresh($handle, $value, $entry_id)) {
				return $this->getCurrentHandle($entry_id);
			}
			
			else {
				$count = 2;
					
					while ($this->isHandleLocked("{$handle}-{$count}", $entry_id)) $count++;
					
				return "{$handle}-{$count}";
			}
		}
		
		return $handle;
	}
	
	public function getCurrentHandle($entry_id) {
		return $this->_engine->Database->fetchVar('handle', 0, sprintf(
			"
				SELECT
					f.handle
				FROM
					`tbl_entries_data_%s` AS f
				WHERE
					f.entry_id = '%s'
				LIMIT 1
			",
			$this->get('id'), $entry_id
		));
	}
	
	public function isHandleLocked($handle, $entry_id) {
		return (boolean)$this->_engine->Database->fetchVar('id', 0, sprintf(
			"
				SELECT
					f.id
				FROM
					`tbl_entries_data_%s` AS f
				WHERE
					f.handle = '%s'
					%s
				LIMIT 1
			",
			$this->get('id'), $handle,
			(!is_null($entry_id) ? "AND f.entry_id != '{$entry_id}'" : '')
		));
	}
	
	public function isHandleFresh($handle, $value, $entry_id) {
		return (boolean)$this->_engine->Database->fetchVar('id', 0, sprintf(
			"
				SELECT
					f.id
				FROM
					`tbl_entries_data_%s` AS f
				WHERE
					f.entry_id = '%s'
					AND f.value = '%s'
				LIMIT 1
			",
			$this->get('id'), $entry_id,
			$this->cleanValue(General::sanitize($value))
		));
	}

	public function getSupportedLanguageCodes() {
		$supported_language_codes = explode(',', General::Sanitize($this->_engine->Configuration->get('languages', 'language_redirect')));
		$supported_language_codes = array_map('trim', $supported_language_codes);
		$supported_language_codes = array_filter($supported_language_codes);
		
		return $supported_language_codes;
	}
	
	public function getCurrentLanguage() {
		return (isset($_REQUEST['language']) && !empty($_REQUEST['language']) ? $_REQUEST['language'] : $this->_supported_language_codes[0]);
	}
	
	public function getMarkup() {
		$supported = array(
			'pb_markdown' => 'markdown', // Markdown extension
			'pb_markdownextra' => 'markdown', // Markdown extension
			'pb_markdownextrasmartypants' => 'markdown', // Markdown extension
			'ta_typogrifymarkdown' => 'markdown', // Typogrify extension
			'ta_typogrifymarkdownextra' => 'markdown', // Typogrify extension
			'ta_typogrifytextile' => 'textile', // Typogrify extension
			'textile' => 'textile', // Textile extension
			'pb_html_complete' => 'html', // HTML extension
			'pb_html_restricted' => 'html', // HTML extension
			'pb_bbcode_modern' => 'bbcode', // BBCode extension
			'pb_bbcode_traditional' => 'bbcode', // BBCode extension
		);
		
		return $supported[$this->get('formatter')];
	}
		
	/*-------------------------------------------------------------------------*/
	/* !Settings: */
	/*-------------------------------------------------------------------------*/
	
	public function displaySettingsPanel(&$wrapper, $errors = null) {
		
		parent::displaySettingsPanel($wrapper, $errors);
		
		$order = $this->get('sortorder');
		
		/* Expression */
			
		$group = new XMLElement('div');
		$group->setAttribute('class', 'group');
		
		$values = $this->_sizes;
		
		foreach ($values as &$value) {
			$value[1] = $value[0] == $this->get('text_size');
		}
		
		$label = Widget::Label('Size');
		$label->appendChild(Widget::Select(
			"fields[{$order}][text_size]", $values
		));
		
		$group->appendChild($label);
		
		/* Text Formatter */		
		
		$group->appendChild($this->buildFormatterSelect(
			$this->get('formatter'),
			"fields[{$order}][formatter]",
			'Text Formatter'
		));
		$wrapper->appendChild($group);
		
		/* Validator */		
		
		$div = new XMLElement('div');
		$this->buildValidationSelect(
			$div, $this->get('text_validator'), "fields[{$order}][text_validator]"
		);
		$wrapper->appendChild($div);
		
		/* Limiting */		
		
		$group = new XMLElement('div');
		$group->setAttribute('class', 'group');
		
		$input = Widget::Input(
			"fields[{$order}][text_length]",
			(integer)$this->get('text_length')
		);
		$input->setAttribute('size', '3');
		
		$group->appendChild(Widget::Label(
			__('Limit input to %s characters', array(
				$input->generate()
			))
		));
		
		/* Show characters */
		
		$input = Widget::Input(
			"fields[{$order}][column_length]",
			$this->get('column_length')
		);
		$input->setAttribute('size', '3');
		
		$group->appendChild(Widget::Label(
			__('Show %s characters in preview', array(
				$input->generate()
			))
		));

		$wrapper->appendChild($group);
		
		$group = new XMLElement('div');
		$group->setAttribute('class', 'group');

		$group->appendChild(Widget::Label(
			__('Current supported languages: '.implode(',',$this->_supported_language_codes))
		));

		$wrapper->appendChild($group);
		/* Defaults */
		
		$this->appendRequiredCheckbox($wrapper);
		$this->appendShowColumnCheckbox($wrapper);
	}

	/*-------------------------------------------------------------------------*/
	/* !Publish: */
	/*-------------------------------------------------------------------------*/

	public function displayPublishPanel(&$wrapper, $data = null, $error = null, $prefix = null, $postfix = null) {
		$this->_driver->addPublishHeaders($this->_engine->Page);
				
		$sortorder = $this->get('sortorder');
		$element_name = $this->get('element_name');
		$classes = array();
		
		/* Label */
		
		$label = Widget::Label($this->get('label'));
		$optional = '';
		
		if ($this->get('required') != 'yes') {
			if ((integer)$this->get('text_length') > 0) {
				$optional = __('$1 of $2 remaining &ndash; Optional');
			}
			
			else {
				$optional = __('Optional');
			}
		}
		
		else if ((integer)$this->get('text_length') > 0) {
			$optional = __('$1 of $2 remaining');
		}
		
		if ($optional) {
			$label->appendChild(new XMLElement('i', $optional));
		}
		
		
		/* Tabs */
		$ul = new XMLElement('ul');	
		$ul->setAttribute('class', 'tabs');
		
		foreach($this->_supported_language_codes as $language) {
			$li = new XMLElement('li',($this->_lang[$language] ? $this->_lang[$language] : $lang));	
			$li->setAttribute('class', $language);
			
			$ul->appendChild($li);
		}
		
		$label->appendChild($ul);				

		/* Inputs */
				
		foreach($this->_supported_language_codes as $language) {
			$panel = Widget::Label();

			// Textfield
			if ($this->get('text_size') == 'single') {
				$input = Widget::Input(
					"fields{$prefix}[$element_name]{$postfix}[value-".$language."]", General::sanitize($data['value-'.$language])
				);
				
				###
				# Delegate: ModifyTextBoxInlineFieldPublishWidget
				# Description: Allows developers modify the textbox before it is rendered in the publish forms
				$delegate = 'ModifyTextBoxInlineFieldPublishWidget';
			}
			
			// Textarea:
			else {
				$input = Widget::Textarea(
					"fields{$prefix}[$element_name]{$postfix}[value-".$language."]", '20', '50', General::sanitize($data['value-'.$language])
				);
				
				###
				# Delegate: ModifyTextBoxFullFieldPublishWidget
				# Description: Allows developers modify the textbox before it is rendered in the publish forms
				$delegate = 'ModifyTextBoxFullFieldPublishWidget';
			}
			
			// Add classes:
			$classes = array();
			$classes[] = 'size-' . $this->get('text_size');
			
			
			if ($this->get('formatter') != 'none') {
				$classes[] = $this->get('formatter');

				// Add form MarkItUp extension support.
				$classes[] = 'markItUp';
				$classes[] = $this->getMarkup();
			}
			
			$input->setAttribute('class', implode(' ', $classes));
			$input->setAttribute('length', (integer)$this->get('text_length'));
			
			$this->_engine->ExtensionManager->notifyMembers(
				$delegate, '/backend/',
				array(
					'field'		=> &$this,
					'label'		=> &$label,
					'input'		=> &$input
				)
			);
			
			if (is_null($label)) return;
			
			$panel->appendChild($input);
			
			$panel->setAttribute('class', 'tab-panel tab-'.$language);
			
			$label->appendChild($panel);
		}

		if ($error != null) {
			$label = Widget::wrapFormElementWithError($label, $error);
		}
			
		$wrapper->appendChild($label);
	}
 
	/*-------------------------------------------------------------------------*/
	/* !Input: */
	/*-------------------------------------------------------------------------*/
		
	public function applyFormatting($data) {
		if ($this->get('formatter') != 'none') {
			if (isset($this->_ParentCatalogue['entrymanager'])) {
				$tfm = $this->_ParentCatalogue['entrymanager']->formatterManager;
			}
			
			else {
				$tfm = new TextformatterManager($this->_engine);
			}
			
			$formatter = $tfm->create($this->get('formatter'));
			$formatted = $formatter->run($data);
		 	$formatted = preg_replace('/&(?![a-z]{0,4}\w{2,3};|#[x0-9a-f]{2,6};)/i', '&amp;', $formatted);
		 	
		 	return $formatted;
		}
		
		return General::sanitize($data);	
	}
	
	public function applyValidationRules($data) {			
		$rule = $this->get('text_validator');
		
		return ($rule ? General::validateString($data, $rule) : true);
	}
	
	public function checkPostFieldData($data, &$message, $entry_id = null) {
		$length = (integer)$this->get('text_length');
		$message = null;
		
		foreach ($this->_supported_language_codes as $language) {
			$value = $data['value-'.$language];
	
			if ($this->get('required') == 'yes' and strlen(trim($value)) == 0) {
				$message = __(
					"'%s' is a required field.", array(
						$this->get('label')
					)
				);
				
				return self::__MISSING_FIELDS__;
			}
			
			if (empty($data)) self::__OK__;
			
			if (!$this->applyValidationRules($data)) {
				$message = __(
					"'%s' contains invalid data. Please check the contents.", array(
						$this->get('label')
					)
				);
				
				return self::__INVALID_FIELDS__;	
			}
			
			if ($length > 0 and $length < strlen($data)) {
				$message = __(
					"'%s' must be no longer than %s characters.", array(
						$this->get('label'),
						$length
					)
				);
				
				return self::__INVALID_FIELDS__;	
			}
			
			if (!General::validateXML($this->applyFormatting($data), $errors, false, new XsltProcess)) {
				$message = __(
					"'%1\$s' contains invalid XML. The following error was returned: <code>%2\$s</code>", array(
						$this->get('label'),
						$errors[0]['message']
					)
				);
				
				return self::__INVALID_FIELDS__;
			}
		}
		
		return self::__OK__;
	}
	
	public function processRawFieldData($data, &$status, $simulate = false, $entry_id = null) {
		$status = self::__OK__;

		$result = array();
		$result['handle'] = $this->createHandle($data['value-'.$this->_supported_language_codes[0]], $entry_id);
		
		foreach ($this->_supported_language_codes as $language) {
			$result['value-'.$language] = $data['value-'.$language];
			$result['word_count-'.$language] = General::countWords($data['value-'.$language]);
			$result['value_format-'.$language] = $this->applyFormatting($data['value-'.$language]);
		}
		
		return $result;
	}
	

	/*-------------------------------------------------------------------------*/
	/*	!Output: */
	/*-------------------------------------------------------------------------*/
		
		public function fetchIncludableElements() {
			return array(
				$this->get('element_name'),
				$this->get('element_name') . ': raw'
			);
		}
		
		public function appendFormattedElement(&$wrapper, $data, $encode = false, $mode = null) {
			$data['value'] = $data['value-'.$this->_current_language];
			$data['value_formatted'] = $data['value_format-'.$this->_current_language];
			$data['word_count'] = $data['word_count-'.$this->_current_language];
			
			if ($mode == 'raw') {
				$value = trim($data['value']);
			}
			
			else {
				$mode = 'normal';
				$value = trim($data['value_formatted']);
			}
			
			$attributes = array(
				'mode'			=> $mode,
				'handle'		=> $data['handle'],
				'word-count'	=> $data['word_count']
			);
			
			$wrapper->appendChild(
				new XMLElement(
					$this->get('element_name'), (
						$encode ? General::sanitize($value) : $value
					), $attributes
				)
			);
		}
		
		public function prepareTableValue($data, XMLElement $link = null) {
			$data['value'] = $data["value-".$this->_supported_language_codes[0]];
			
			if (empty($data) or strlen(trim($data['value'])) == 0) return;
			
			@header('content-type: text/html');
			
			$max_length = (integer)$this->get('column_length');
			$max_length = ($max_length ? $max_length : 75);
			
			$value = strip_tags($data['value']);
			
			if ($max_length < strlen($value)) {
				$lines = explode("\n", wordwrap($value, $max_length - 1, "\n"));
				$value = array_shift($lines);
				$value = rtrim($value, "\n\t !?.,:;");
				$value .= '...';
			}
			
			$value = str_replace('...', '&#x2026;', $value);
			
			if ($max_length > 75) {
				$value = wordwrap($value, 75, '<br />');
			}
			
			if ($link) {
				$link->setValue($value);
				
				return $link->generate();
			}
			
			return $value;
		}
		
		public function getParameterPoolValue($data) {
			return $data['handle'];
		}
}