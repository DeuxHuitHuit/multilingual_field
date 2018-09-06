<?php

if (!defined('__IN_SYMPHONY__')) {
    die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');
}

require_once(EXTENSIONS . '/frontend_localisation/lib/class.FLang.php');
require_once(EXTENSIONS . '/textboxfield/fields/field.textbox.php');

class fieldMultilingual_TextBox extends FieldTextBox
{
    /*------------------------------------------------------------------------------------------------*/
    /*  Definition  */
    /*------------------------------------------------------------------------------------------------*/

    public function __construct()
    {
        parent::__construct();

        $this->_name = 'Multilingual Text Box';
    }

    public static function generateTableColumns()
    {
        $cols = array();
        foreach (FLang::getLangs() as $lc) {
            $cols[] = "`handle-{$lc}` VARCHAR(1024) DEFAULT NULL,";
            $cols[] = "`value-{$lc}` TEXT DEFAULT NULL,";
            $cols[] = "`value_formatted-{$lc}` TEXT DEFAULT NULL,";
            $cols[] = "`word_count-{$lc}` INT(11) UNSIGNED DEFAULT NULL,";
        }
        return $cols;
    }

    public static function generateTableKeys()
    {
        $keys = array();
        foreach (FLang::getLangs() as $lc) {
            $keys[] = "KEY `handle-{$lc}` (`handle-{$lc}`),";
            $keys[] = "FULLTEXT KEY `value-{$lc}` (`value-{$lc}`),";
            $keys[] = "FULLTEXT KEY `value_formatted-{$lc}` (`value_formatted-{$lc}`),";
        }
        return $keys;
    }

    public function createTable()
    {
        $field_id = $this->get('id');

        $query = "
            CREATE TABLE IF NOT EXISTS `tbl_entries_data_{$field_id}` (
                `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                `entry_id` INT(11) UNSIGNED NOT NULL,
                `handle` VARCHAR(1024) DEFAULT NULL,
                `value` TEXT DEFAULT NULL,
                `value_formatted` TEXT DEFAULT NULL,
                `word_count` INT(11) UNSIGNED DEFAULT NULL,";

        $query .= implode('', self::generateTableColumns());

        $query .= "
                PRIMARY KEY (`id`),
                UNIQUE KEY `entry_id` (`entry_id`),";

        $query .= implode('', self::generateTableKeys());

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

    public function createHandle($value, $entry_id, $lc = null)
    {
        if (!FLang::validateLangCode($lc)) {
            $lc = FLang::getLangCode();
        }

        $max_length = 1023;
        $handle = Lang::createHandle(strip_tags(html_entity_decode($value)), $max_length);

        if ($this->get('handle_unique') === 'yes' && $this->isHandleLocked($handle, $entry_id, $lc)) {
            if ($this->isHandleFresh($handle, $value, $entry_id, $lc)) {
                return $this->getCurrentHandle($entry_id, $lc);
            } else {
                $count = 1;

                do {
                    $count++;
                    $countString = "-{$count}";
                    $subHandle = trim(General::substr($handle, 0, $max_length - General::strlen($countString)), '-');
                } while ($this->isHandleLocked("{$subHandle}-{$count}", $entry_id, $lc));

                return "{$subHandle}-{$count}";
            }
        }

        return $handle;
    }

    public function getCurrentHandle($entry_id, $lc = null)
    {
        return Symphony::Database()->fetchVar('handle', 0, sprintf(
            "
                SELECT
                    f.`handle-%s`
                FROM
                    `tbl_entries_data_%s` AS f
                WHERE
                    f.entry_id = '%s'
                LIMIT 1
            ",
            $lc,
            $this->get('id'),
            $entry_id
        ));
    }

    public function isHandleLocked($handle, $entry_id, $lc = null)
    {
        return (boolean) Symphony::Database()->fetchVar('id', 0, sprintf(
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
            $this->get('id'), $lc, $handle,
            (!is_null($entry_id) ? "AND f.entry_id != '{$entry_id}'" : '')
        ));
    }

    public function isHandleFresh($handle, $value, $entry_id, $lc = null)
    {
        return (boolean) Symphony::Database()->fetchVar('id', 0, sprintf(
            "
                SELECT
                    f.id
                FROM
                    `tbl_entries_data_%s` AS f
                WHERE
                    f.entry_id = '%s'
                    AND f.`value-%s` = '%s'
                    AND f.`handle-%s` = '%s'
                LIMIT 1
            ",
            $this->get('id'), $entry_id,
            $lc, $this->cleanValue(General::sanitize($value)),
            $lc, $this->cleanValue(General::sanitize($handle))
        ));
    }

    /**
     * Returns required languages for this field.
     */
    public function getRequiredLanguages()
    {
        $required = $this->get('required_languages');

        $languages = FLang::getLangs();

        if (in_array('all', $required)) {
            return $languages;
        }

        if (($key = array_search('main', $required)) !== false) {
            unset($required[$key]);

            $required[] = FLang::getMainLang();
            $required   = array_unique($required);
        }

        return $required;
    }



    /*------------------------------------------------------------------------------------------------*/
    /*  Settings  */
    /*------------------------------------------------------------------------------------------------*/

    public function findDefaults(array &$settings)
    {
        parent::findDefaults($settings);

        $settings['default_main_lang'] = 'no';
    }

    public function set($field, $value)
    {
        if ($field == 'required_languages' && !is_array($value)) {
            $value = array_filter(explode(',', $value));
        }

        $this->_settings[$field] = $value;
    }

    public function get($field = null)
    {
        if ($field == 'required_languages') {
            return (array) parent::get($field);
        }

        return parent::get($field);
    }

    public function displaySettingsPanel(XMLElement &$wrapper, $errors = null)
    {
        parent::displaySettingsPanel($wrapper, $errors);

        Extension_Multilingual_Field::appendHeaders(
            Extension_Multilingual_Field::SETTINGS_HEADERS
        );

        /*
         * UI is like this:
         *
         * ................................................................................
         * [Checkbox] Required                        [Checkbox] Display in Entries table
         *
         *
         * It must become like this:
         *
         * ................................................................................
         * [Checkbox] Require all languages           [Select]   Require only these languages
         * [Checkbox] Default to main lang            [Checkbox] Display in entries table
         */

        // this is the div with current required checkbox. Remove current required checkbox.
        $last_div_pos       = $wrapper->getNumberOfChildren() - 1;
        $last_div           = $wrapper->getChild($last_div_pos);

        // Default to main lang && Display in entries table
        $two_columns = new XMLELement('div', null, array('class' => 'two columns'));
        $this->settingsDefaultMainLang($two_columns);
        $this->appendShowColumnCheckbox($two_columns);
        $wrapper->replaceChildAt($last_div_pos, $two_columns);

        // Require all languages && Require custom languages
        $two_columns = new XMLELement('div', null, array('class' => 'two columns'));
        $this->settingsRequiredLanguages($two_columns);
        $wrapper->appendChild($two_columns);
    }

    private function settingsDefaultMainLang(XMLElement &$wrapper)
    {
        $name = "fields[{$this->get('sortorder')}][default_main_lang]";

        $wrapper->appendChild(Widget::Input($name, 'no', 'hidden'));

        $label = Widget::Label();
        $label->setAttribute('class', 'column');
        $input = Widget::Input($name, 'yes', 'checkbox');

        if ($this->get('default_main_lang') == 'yes') {
            $input->setAttribute('checked', 'checked');
        }

        $label->setValue(__('%s Use value from main language if selected language has empty value.', array($input->generate())));

        $wrapper->appendChild($label);
    }

    private function settingsRequiredLanguages(XMLElement &$wrapper)
    {
        $name = "fields[{$this->get('sortorder')}][required_languages][]";

        $required_languages = $this->get('required_languages');

        $displayed_languages = FLang::getLangs();

        if (($key = array_search(FLang::getMainLang(), $displayed_languages)) !== false) {
            unset($displayed_languages[$key]);
        }

        $options = Extension_Languages::findOptions($required_languages, $displayed_languages);

        array_unshift(
            $options,
            array('all', $this->get('required') == 'yes', __('All')),
            array('main', in_array('main', $required_languages), __('Main language'))
        );

        $label = Widget::Label(__('Required languages'));
        $label->setAttribute('class', 'column');
        $label->appendChild(
            Widget::Select($name, $options, array('multiple' => 'multiple'))
        );

        $wrapper->appendChild($label);
    }

    public function commit()
    {
        $required_languages = $this->get('required_languages');

        // all are required
        if (in_array('all', $required_languages)) {
            $this->set('required', 'yes');
            $required_languages = array('all');
        }
        else {
            $this->set('required', 'no');
        }

        // if main is required, remove the actual language code
        if (in_array('main', $required_languages)) {
            if (($key = array_search(FLang::getMainLang(), $required_languages)) !== false) {
                unset($required_languages[$key]);
            }
        }

        $this->set('required_languages', $required_languages);

        if (!parent::commit()) {
            return false;
        }

        return Symphony::Database()->query(sprintf("
            UPDATE
                `tbl_fields_%s`
            SET
                `default_main_lang` = '%s',
                `required_languages` = '%s'
            WHERE
                `field_id` = '%s';",
            $this->handle(),
            $this->get('default_main_lang') === 'yes' ? 'yes' : 'no',
            implode(',', $this->get('required_languages')),
            $this->get('id')
        ));
    }



    /*------------------------------------------------------------------------------------------------*/
    /*  Publish  */
    /*------------------------------------------------------------------------------------------------*/

    public function displayPublishPanel(XMLElement &$wrapper, $data = null, $flagWithError = null, $fieldnamePrefix = null, $fieldnamePostfix = null, $entry_id = null)
    {
        // We've been called out of context: Publish Filter
        $callback = Administration::instance()->getPageCallback();
        if (!in_array($callback['context']['page'], array('edit', 'new'))) {
            return;
        }

        Extension_Frontend_Localisation::appendAssets();
        Extension_Multilingual_Field::appendHeaders(
            Extension_Multilingual_Field::PUBLISH_HEADERS
        );

        $main_lang = FLang::getMainLang();
        $all_langs = FLang::getAllLangs();
        $langs     = FLang::getLangs();

        $wrapper->setAttribute('class', $wrapper->getAttribute('class') . ' field-multilingual');
        $container = new XMLElement('div', null, array('class' => 'container'));

        /*------------------------------------------------------------------------------------------------*/
        /*  Label  */
        /*------------------------------------------------------------------------------------------------*/

        $label    = Widget::Label($this->get('label'));
        $title = '';
        $optional = '';
        $required_languages = $this->getRequiredLanguages();
        $required = in_array('all', $required_languages) || count($langs) == count($required_languages);

        $append_dash = false;
        if ((integer) $this->get('text_length') > 0) {
            $optional = __('$1 of $2 remaining');
            $append_dash = true;
        }

        if (!$required) {
            if ($append_dash) {
                $optional .= ' &ndash; ';
            }
            
            if (empty($required_languages)) {
                $optional .= __('All languages are optional');
            } else {
                $optional_langs = array();
                foreach ($langs as $lang) {
                    if (!in_array($lang, $required_languages)) {
                        $optional_langs[] = $all_langs[$lang];
                    }
                }
                
                foreach ($optional_langs as $idx => $lang) {
                    $optional .= ' ' . __($lang);
                    if ($idx < count($optional_langs) - 2) {
                        $optional .= ',';
                    } else if ($idx < count($optional_langs) - 1) {
                        $optional .= ' ' . __('and');
                    }
                }
                if (count($optional_langs) > 1) {
                    $optional .= __(' are optional');
                } else {
                    $optional .= __(' is optional');
                }
            }
            if ($this->get('default_main_lang') == 'yes') {
                $title .= __('Empty values defaults to %s', array($all_langs[$main_lang]));
            }
        }

        if ($optional !== '') {
            foreach ($langs as $lc) {
                $label->appendChild(new XMLElement('i', $optional, array(
                    'class'          => "tab-element tab-$lc",
                    'data-lang_code' => $lc,
                    'title' => $title
                )));
            }
        }

        $container->appendChild($label);

        /*------------------------------------------------------------------------------------------------*/
        /*  Tabs  */
        /*------------------------------------------------------------------------------------------------*/

        $ul = new XMLElement('ul', null, array('class' => 'tabs'));
        foreach ($langs as $lc) {
            $li = new XMLElement('li', $all_langs[$lc], array('class' => $lc));
            $lc === $main_lang ? $ul->prependChild($li) : $ul->appendChild($li);
        }

        $container->appendChild($ul);

        /*------------------------------------------------------------------------------------------------*/
        /*  Panels  */
        /*------------------------------------------------------------------------------------------------*/

        foreach ($langs as $lc) {
            $div = new XMLElement('div', null, array(
                'class'          => 'tab-panel tab-' . $lc,
                'data-lang_code' => $lc
            ));

            $element_name = $this->get('element_name');

            // Input box:
            if ($this->get('text_size') === 'single') {
                $input = Widget::Input(
                    "fields{$fieldnamePrefix}[$element_name]{$fieldnamePostfix}[{$lc}]", General::sanitize($data["value-$lc"])
                );

                ###
                # Delegate: ModifyTextBoxInlineFieldPublishWidget
                # Description: Allows developers modify the textbox before it is rendered in the publish forms
                $delegate = 'ModifyTextBoxInlineFieldPublishWidget';
            }

            // Text Box:
            else {
                $input = Widget::Textarea(
                    "fields{$fieldnamePrefix}[$element_name]{$fieldnamePostfix}[{$lc}]", 20, 50, General::sanitize($data["value-$lc"])
                );

                ###
                # Delegate: ModifyTextBoxFullFieldPublishWidget
                # Description: Allows developers modify the textbox before it is rendered in the publish forms
                $delegate = 'ModifyTextBoxFullFieldPublishWidget';
            }

            // Add classes:
            $classes = array('size-' . $this->get('text_size'));

            if ($this->get('text_formatter') != 'none') {
                $classes[] = $this->get('text_formatter');
            }

            $input->setAttributeArray(array(
                'class'  => implode(' ', $classes),
                'length' => (integer) $this->get('text_length')
            ));

            Symphony::ExtensionManager()->notifyMembers(
                $delegate, '/backend/',
                array(
                    'field'    => $this,
                    'label'    => $div,
                    'input'    => $input,
                    'textarea' => $input
                )
            );

            $div->appendChild($input);

            $container->appendChild($div);
        }

        /*------------------------------------------------------------------------------------------------*/
        /*  Errors  */
        /*------------------------------------------------------------------------------------------------*/

        if ($flagWithError != null) {
            $wrapper->appendChild(Widget::Error($container, $flagWithError));
        }
        else {
            $wrapper->appendChild($container);
        }
    }



    /*------------------------------------------------------------------------------------------------*/
    /*  Input  */
    /*------------------------------------------------------------------------------------------------*/

    public function checkPostFieldData($data, &$message, $entry_id = null)
    {
        $error              = self::__OK__;
        $all_langs          = FLang::getAllLangs();
        $main_lang          = FLang::getMainLang();
        $required_languages = $this->getRequiredLanguages();
        $original_required  = $this->get('required');

        foreach (FLang::getLangs() as $lc) {
            $this->set('required', in_array($lc, $required_languages) ? 'yes' : 'no');

            // ignore missing languages
            if (!isset($data[$lc]) && $entry_id) {
                continue;
            }

            // if one language fails, all fail
            if (self::__OK__ != parent::checkPostFieldData($data[$lc], $file_message, $entry_id)) {

                $local_msg = "<br />[$lc] {$all_langs[$lc]}: {$file_message}";

                if ($lc === $main_lang) {
                    $message = $local_msg . $message;
                }
                else {
                    $message = $message . $local_msg;
                }

                $error = self::__ERROR__;
            }
        }

        $this->set('required', $original_required);

        return $error;
    }

    public function processRawFieldData($data, &$status, &$message = null, $simulate = false, $entry_id = null)
    {
        if (!is_array($data)) {
            $data = array();
        }

        $status     = self::__OK__;
        $result     = array();
        $field_data = $data;

        $missing_langs = array();

        foreach (FLang::getLangs() as $lc) {

            if (!isset($field_data[$lc])) {
                $missing_langs[] = $lc;
                continue;
            }

            $data = $field_data[$lc];

            $formatted = $this->applyFormatting($data);

            $result = array_merge($result, array(
                "handle-$lc"          => $this->createHandle($formatted, $entry_id, $lc),
                "value-$lc"           => (string) $data,
                "value_formatted-$lc" => $formatted,
                "word_count-$lc"      => General::countWords($data)
            ));

            // Insert values of default language as default values of the field for compatibility with other extensions 
            // that watch the values without lang code.
            if (FLang::getMainLang() == $lc) {
                $result = array_merge($result, array(
                    'handle'          => $this->createHandle($formatted, $entry_id, $lc),
                    'value'           => (string) $data,
                    'value_formatted' => $formatted,
                    'word_count'      => General::countWords($data)
                ));
            }
        }

        if (!empty($missing_langs) && $entry_id) {
            $crt_data = $this->getCurrentData($entry_id);

            foreach ($missing_langs as $lc) {
                $result = array_merge($result, array(
                    "handle-$lc"          => $crt_data["handle-$lc"],
                    "value-$lc"           => $crt_data["value-$lc"],
                    "value_formatted-$lc" => $crt_data["value_formatted-$lc"],
                    "word_count-$lc"      => $crt_data["word_count-$lc"]
                ));
            }
        }

        return $result;
    }

    private function getCurrentData($entry_id)
    {
        $query = sprintf(
            'SELECT * FROM `tbl_entries_data_%d`
            WHERE `entry_id` = %d',
            $this->get('id'),
            $entry_id
        );

        return Symphony::Database()->fetchRow(0, $query);
    }



    /*------------------------------------------------------------------------------------------------*/
    /*  Output  */
    /*------------------------------------------------------------------------------------------------*/

    public function fetchIncludableElements()
    {
        $parent_elements     = parent::fetchIncludableElements();
        $includable_elements = $parent_elements;

        $name        = $this->get('element_name');
        $name_length = strlen($name);

        foreach ($parent_elements as $element) {
            $includable_elements[] = $name . ': all-languages' . substr($element, $name_length);
        }

        return $includable_elements;
    }

    public function appendFormattedElement(XMLElement &$wrapper, $data, $encode = false, $mode = null, $entry_id = null)
    {
        // all-languages
        $all_languages = strpos($mode, 'all-languages');

        if ($all_languages !== false) {
            $submode = substr($mode, $all_languages + 15);

            if (empty($submode)) {
                $submode = 'formatted';
            }

            $all = new XMLElement($this->get('element_name'), null, array('mode' => $mode));

            foreach (FLang::getLangs() as $lc) {
                $data['handle']          = $data["handle-$lc"];
                $data['value']           = $data["value-$lc"];
                $data['value_formatted'] = $data["value_formatted-$lc"];
                $data['word_count']      = $data["word_count-$lc"];

                $item = new XMLElement('item', null, array('lang' => $lc));

                parent::appendFormattedElement($item, $data, $encode, $submode);

                // Reformat generated XML
                $elem = $item->getChild(0);
                if (!is_null($elem)) {
                    $attributes = $elem->getAttributes();
                    unset($attributes['mode']);
                    $value = $elem->getValue();
                    $item->setAttributeArray($attributes);
                    $item->setValue($value);
                    $item->removeChildAt(0);
                }

                $all->appendChild($item);
            }

            $wrapper->appendChild($all);
        }

        // current-language
        else {
            $lc = FLang::getLangCode();

            // If value is empty for this language, load value from main language
            if ($this->get('default_main_lang') == 'yes' && empty($data["value-$lc"])) {
                $lc = FLang::getMainLang();
            }

            $data['handle']          = $data["handle-$lc"];
            $data['value']           = $data["value-$lc"];
            $data['value_formatted'] = $data["value_formatted-$lc"];
            $data['word_count']      = $data["word_count-$lc"];

            parent::appendFormattedElement($wrapper, $data, $encode, $mode);

            $elem = $wrapper->getChildByName($this->get('element_name'), 0);

            if (!is_null($elem)) {
                $elem->setAttribute('lang', $lc);

                if ($this->get('text_handle') === 'yes') {
                    foreach (FLang::getLangs() as $lc) {
                        $elem->setAttribute("handle-$lc", $data["handle-$lc"]);
                    }
                }
            }
        }
    }

    // @todo: remove and fallback to default (Symphony 2.5 only?)
    public function prepareTableValue($data, XMLElement $link = null, $entry_id = null)
    {
        $lc = $this->getLang($data);

        $data['value']           = $data["value-$lc"];
        $data['value_formatted'] = $data["value_formatted-$lc"];

        return parent::prepareTableValue($data, $link);
    }

    public function prepareTextValue($data, $entry_id = null)
    {
        $lc = $this->getLang($data);
        return strip_tags($data["value-$lc"]);
    }
    
    protected function getLang($data = null)
    {
        $required_languages = $this->getRequiredLanguages();
        $lc = Lang::get();

        if (!FLang::validateLangCode($lc)) {
            $lc = FLang::getLangCode();
        }

        // If value is empty for this language, load value from main language
        if (is_array($data) && $this->get('default_main_lang') == 'yes' && empty($data["value-$lc"])) {
            $lc = FLang::getMainLang();
        }

        // If value if still empty try to use the value from the first
        // required language
        if (is_array($data) && empty($data["value-$lc"]) && count($required_languages) > 0) {
            $lc = $required_languages[0];
        }

        return $lc;
    }

    public function getParameterPoolValue(array $data, $entry_id = null)
    {
        $lc = $this->getLang();
        return $data["value-$lc"];
    }

    public function getExampleFormMarkup()
    {
        $label = Widget::Label($this->get('label'));

        if ($this->get('text_size') === 'single') {
            foreach (FLang::getLangs() as $lc) {
                $label->appendChild(Widget::Input("fields[{$this->get('element_name')}][{$lc}]"));
            }
        }
        else {
            foreach (FLang::getLangs() as $lc) {
                $label->appendChild(Widget::Textarea("fields[{$this->get('element_name')}][{$lc}]", 20, 50));
            }
        }

        return $label;
    }

    public function prepareExportValue($data, $mode, $entry_id = null)
    {
        $modes = (object)$this->getExportModes();
        $lc = $this->getLang();

        // Export handles:
        if ($mode === $modes->getHandle) {
            if (isset($data["handle-$lc"])) {
                return $data["handle-$lc"];
            }
            else if (isset($data['handle'])) {
                return $data['handle'];
            }
            else if (isset($data["value-$lc"])) {
                return Lang::createHandle($data["value-$lc"]);
            }
            else if (isset($data['value'])) {
                return Lang::createHandle($data['value']);
            }
        }

        // Export unformatted:
        else if ($mode === $modes->getUnformatted || $mode === $modes->getPostdata) {
            if (isset($data["value-$lc"])) {
                return $data["value-$lc"];
            }
            return isset($data['value'])
                ? $data['value']
                : null;
        }

        // Export formatted:
        else if ($mode === $modes->getFormatted) {
            if (isset($data["value_formatted-$lc"])) {
                return $data["value_formatted-$lc"];
            }
            if (isset($data['value_formatted'])) {
                return $data['value_formatted'];
            }
            else if (isset($data["value-$lc"])) {
                return General::sanitize($data["value-$lc"]);
            }
            else if (isset($data['value'])) {
                return General::sanitize($data['value']);
            }
        }

        return null;
    }



    /*------------------------------------------------------------------------------------------------*/
    /*  Filtering  */
    /*------------------------------------------------------------------------------------------------*/

    public function buildDSRetrievalSQL($data, &$joins, &$where, $andOperation = false)
    {
        $multi_where = '';

        parent::buildDSRetrievalSQL($data, $joins, $multi_where, $andOperation);

        $lc = FLang::getLangCode();
        
        if ($lc) {
            $multi_where = str_replace('.value', ".`value-$lc`", $multi_where);
            $multi_where = str_replace('.handle', ".`handle-$lc`", $multi_where);
        }

        $where .= $multi_where;

        return true;
    }



    /*-------------------------------------------------------------------------
        Sorting:
    -------------------------------------------------------------------------*/

    public function buildSortingSQL(&$joins, &$where, &$sort, $order = 'ASC')
    {
        $lc = FLang::getLangCode();
        
        if (in_array(strtolower($order), array('random', 'rand'))) {
            $sort = 'ORDER BY RAND()';
        }

        else if ($lc != null) {
            $sort = sprintf('
                ORDER BY(
                    SELECT `%s`
                    FROM tbl_entries_data_%d
                    WHERE entry_id = e.id
                ) %s',
                "handle-$lc",
                $this->get('id'),
                $order
            );
        }
        else {
            parent::buildSortingSQL($joins, $where, $sort, $order);
        }
    }

    /*------------------------------------------------------------------------------------------------*/
    /*  Grouping  */
    /*------------------------------------------------------------------------------------------------*/

    public function groupRecords($records)
    {
        $lc = FLang::getLangCode();

        $groups = array(
            $this->get('element_name') => array()
        );

        foreach ($records as $record) {
            $data = $record->getData($this->get('id'));

            $handle  = $data["handle-$lc"];
            $element = $this->get('element_name');

            if (!isset($groups[$element][$handle])) {
                $groups[$element][$handle] = array(
                    'attr'    => array(
                        'handle' => $handle
                    ),
                    'records' => array(),
                    'groups'  => array()
                );
            }

            $groups[$element][$handle]['records'][] = $record;
        }

        return $groups;
    }



    /*------------------------------------------------------------------------------------------------*/
    /*  Field schema  */
    /*------------------------------------------------------------------------------------------------*/

    public function appendFieldSchema(XMLElement $f)
    {
        $required_languages = $this->getRequiredLanguages();

        $required = new XMLElement('required-languages');

        foreach ($required_languages as $lc) {
            $required->appendChild(new XMLElement('item', $lc));
        }

        $f->appendChild($required);
    }
}
