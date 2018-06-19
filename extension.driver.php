<?php

if (!defined('__IN_SYMPHONY__')) {
    die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');
}

class Extension_Multilingual_Field extends Extension
{
    const FIELD_TABLE = 'tbl_fields_multilingual_textbox';
    const PUBLISH_HEADERS = 1;
    const SETTINGS_HEADERS = 2;
    private static $appendedHeaders = 0;

    /*------------------------------------------------------------------------------------------------*/
    /*  Installation  */
    /*------------------------------------------------------------------------------------------------*/

    public function install()
    {
        return $this->createFieldTable();
    }

    public function update($previousVersion = false)
    {
        $textboxExt = new Extension_TextBoxField();

        if (version_compare($previousVersion, '2.0', '<')) {
            $v1x_table = 'tbl_fields_multilingual';

            $fields = Symphony::Database()
                ->select(['field_id'])
                ->from($v1x_table)
                ->execute()
                ->rows();

            if (version_compare($previousVersion, '1.1', '<')) {
                foreach ($fields as $field) {
                    $entries_table = 'tbl_entries_data_' . $field["field_id"];

                    if (!$textboxExt->updateHasColumn('value', $entries_table)) {
                        Symphony::Database()
                            ->alter($entries_table)
                            ->add([
                                'value' => [
                                    'type' => 'text',
                                    'null' => true,
                                ],
                            ])
                            ->execute()
                            ->success();
                    }
                }
            }

            if (version_compare($previousVersion, '1.2', '<')) {
                foreach ($fields as $field) {
                    $entries_table = 'tbl_entries_data_' . $field["field_id"];

                    foreach (FLang::getLangs() as $lc) {
                        if (!$textboxExt->updateHasColumn('handle-' . $lc, $entries_table)) {
                            Symphony::Database()
                                ->alter($entries_table)
                                ->add([
                                    "handle-$lc" => [
                                        'type' => 'text',
                                        'null' => true,
                                    ],
                                ])
                                ->execute()
                                ->success();

                            $values = Symphony::Database()
                                ->select(['id', 'entry_id', "value-$lc"])
                                ->from($entries_table)
                                ->where(['handle' => ['!=' => null]])
                                ->execute()
                                ->rows();

                            foreach ($values as $value) {
                                Symphony::Database()
                                    ->alter($entries_table)
                                    ->set("handle-$lc")
                                    ->value($this->createHandle($value["value-$lc"], $value["entry_id"], $lc, $entries_table))
                                    ->where(['id' => $value['id']])
                                    ->execute()
                                    ->success();
                            }
                        }
                    }
                }
            }

            if (version_compare($previousVersion, '1.4', '<')) {
                Symphony::database()
                    ->alter($v1x_table)
                    ->add([
                        'unique_handle' => [
                            'type' => 'enum',
                            'values' => ['yes','no'],
                            'default' => 'yes',
                        ],
                    ])
                    ->execute()
                    ->success();

                Symphony::database()
                    ->update($v1x_table)
                    ->set([
                        'unique_handle' => 'yes',
                    ])
                    ->execute()
                    ->success();
            }

            if (version_compare($previousVersion, '1.4.1', '<')) {
                Symphony::database()
                    ->alter($v1x_table)
                    ->add([
                        'use_def_lang_vals' => [
                            'type' => 'enum',
                            'values' => ['yes','no'],
                            'default' => 'yes',
                        ],
                    ])
                    ->execute()
                    ->success();

                Symphony::database()
                    ->update($v1x_table)
                    ->set([
                        'use_def_lang_vals' => 'yes'
                    ])
                    ->value('yes')
                    ->execute()
                    ->success();
            }

            if (version_compare($previousVersion, '2.0', '<')) {
                Symphony::database()
                    ->rename($v1x_table)
                    ->to(self::FIELD_TABLE)
                    ->execute()
                    ->success();

                Symphony::Database()
                    ->update('tbl_fields')
                    ->set(['type' => 'multilingual_textbox'])
                    ->where(['type' => 'multilingual'])
                    ->execute()
                    ->success();

                Symphony::Database()
                    ->alter(self::FIELD_TABLE)
                    ->change(['formatter', 'unique_handle', 'use_def_lang_vals'], [
                        'text_formatter' => [
                            'type' => 'varchar(255)',
                            'charset' => 'utf8',
                            'collate' => 'utf8_unicode_ci',
                            'null' => true,
                        ],
                        'text_handle' => [
                            'type' => 'enum',
                            'values' => ['yes', 'no'],
                            'charset' => 'utf8',
                            'collate' => 'utf8_unicode_ci',
                            'default' => 'yes',
                        ],
                        'def_ref_lang' => [
                            'type' => 'enum',
                            'values' => ['yes', 'no'],
                            'charset' => 'utf8',
                            'collate' => 'utf8_unicode_ci',
                            'default' => 'no',
                        ],
                    ])
                    ->modify([
                        'text_validator' => [
                            'type' => 'varchar(255)',
                            'charset' => 'utf8',
                            'collate' => 'utf8_unicode_ci',
                            'null' => true,
                        ],
                        'text_size' => [
                            'type' => 'enum',
                            'values' => ['single', 'small', 'medium', 'large', 'huge'],
                            'charset' => 'utf8',
                            'collate' => 'utf8_unicode_ci',
                            'default' => 'medium',
                        ],
                    ])
                    ->add([
                        'text_cdata' => [
                            'type' => 'enum',
                            'values' => ['yes', 'no'],
                            'charset' => 'utf8',
                            'collate' => 'utf8_unicode_ci',
                            'default' => 'no',
                        ],
                    ])
                    ->execute()
                    ->success();

                Symphony::Database()
                    ->update(self::FIELD_TABLE)
                    ->set([
                        'text_cdata' => 'no',
                    ])
                    ->execute()
                    ->success();

                foreach ($fields as $field) {
                    $entries_table = 'tbl_entries_data_' . $field["field_id"];

                    Symphony::Database()
                        ->alter($entries_table)
                        ->modify([
                            'handle' => [
                                'type' => 'varchar(255)',
                                'charset' => 'utf8',
                                'collate' => 'utf8_unicode_ci',
                                'null' => true,
                            ],
                            'value' => [
                                'type' => 'text',
                                'charset' => 'utf8',
                                'collate' => 'utf8_unicode_ci',
                                'null' => true,
                            ],
                        ])
                        ->execute()
                        ->success();

                    foreach (FLang::getLangs() as $lc) {
                        if (!$textboxExt->updateHasColumn('value_formatted-' . $lc, $entries_table)) {
                            Symphony::Database()
                                ->alter($entries_table)
                                ->change("value_format-$lc", [
                                    "value_formatted-$lc" => [
                                        'type' => 'text',
                                        'charset' => 'utf8',
                                        'collate' => 'utf8_unicode_ci',
                                        'null' => true,
                                    ],
                                ])
                                ->modify([
                                    "handle-$lc" => [
                                        'type' => 'varchar(255)',
                                        'charset' => 'utf8',
                                        'collate' => 'utf8_unicode_ci',
                                        'null' => true,
                                    ],
                                    "value-$lc" => [
                                        'type' => 'text',
                                        'charset' => 'utf8',
                                        'collate' => 'utf8_unicode_ci',
                                        'null' => true,
                                    ],
                                    "word_count-$lc" => [
                                        'type' => 'int(11)',
                                        'null' => true,
                                    ],
                                ])
                                ->addKey([
                                    "value-$lc" => 'fulltext',
                                    "value_formatted-$lc" => 'fulltext',
                                ])
                                ->execute()
                                ->success();
                        }
                    }
                }
            }
        }

        if (version_compare($previousVersion, '3.0', '<')) {
            Symphony::Database()
                ->alter(self::FIELD_TABLE)
                ->change('def_ref_lang', [
                    'default_main_lang' => [
                        'type' => 'enum',
                        'values' => ['yes', 'no'],
                        'charset' => 'utf8',
                        'collate' => 'utf8_unicode_ci',
                        'default' => 'no',
                    ],
                ])
                ->add([
                    'required_languages' => [
                        'type' => 'varchar(255)',
                        'charset' => 'utf8',
                        'collate' => 'utf8_unicode_ci',
                        'null' => true,
                    ],
                ])
                ->execute()
                ->success();
        }

        // is handle unique:
        if (!$textboxExt->updateHasColumn('handle_unique', self::FIELD_TABLE)) {
            $textboxExt->updateAddColumn('handle_unique', ['type' => 'enum', 'values' => ['yes', 'no'], 'default' => 'yes'], self::FIELD_TABLE, 'text_handle');
        }

        // add field_id unique key
        if ($textboxExt->updateHasColumn('field_id', self::FIELD_TABLE) && !$textboxExt->updateHasUniqueKey('field_id', self::FIELD_TABLE)) {
            $textboxExt->updateAddUniqueKey('field_id', self::FIELD_TABLE);
        }

        // add entry_id unique key
        $textbox_fields = (new FieldManager)
            ->select()
            ->sort('sortorder', 'asc')
            ->type('multilingual_textbox')
            ->execute()
            ->rows();

        foreach($textbox_fields as $field) {
            $table = 'tbl_entries_data_' . $field->get('id');
            try {
                Symphony::Database()
                    ->alter($table)
                    ->dropKey('handle')
                    ->execute()
                    ->success();
            } catch (Exception $ex) {
                // ignore
            }
            // Handle length
            $textboxExt->updateModifyColumn('handle', 'varchar(1024)', $table);
            foreach (FLang::getLangs() as $lc) {
                if ($textboxExt->updateHasColumn("handle-$lc", $table)) {
                    $textboxExt->updateModifyColumn("handle-$lc", 'varchar(1024)', $table);
                }
            }

            // Make sure we have an index on the handle
            if ($textboxExt->updateHasColumn('text_handle') && !$textboxExt->updateHasIndex('handle', $table)) {
                $textboxExt->updateAddIndex('handle', $table, 333);
            }

            // Make sure we have a unique key on `entry_id`
            if ($textboxExt->updateHasColumn('entry_id', $table) && !$textboxExt->updateHasUniqueKey('entry_id', $table)) {
                $textboxExt->updateAddUniqueKey('entry_id', $table);
            }
        }

        return true;
    }

    public function uninstall()
    {
        return $this->dropFieldTable();
    }

    private function createFieldTable()
    {
        return Symphony::Database()
            ->create(self::FIELD_TABLE)
            ->ifNotExists()
            ->charset('utf8')
            ->collate('utf8_unicode_ci')
            ->fields([
                'id' => [
                    'type' => 'int(11)',
                    'auto' => true,
                ],
                'field_id' => 'int(11)',
                'column_length' => [
                    'type' => 'int(11)',
                    'default' => 75,
                ],
                'text_size' => [
                    'type' => 'enum',
                    'values' => ['single', 'small', 'medium', 'large', 'huge'],
                    'default' => 'medium',
                ],
                'text_formatter' => [
                    'type' => 'varchar(255)',
                    'null' => true,
                ],
                'text_validator' => [
                    'type' => 'varchar(255)',
                    'null' => true,
                ],
                'text_length' => [
                    'type' => 'int(11)',
                    'default' => 0,
                ],
                'text_cdata' => [
                    'type' => 'enum',
                    'values' => ['yes', 'no'],
                    'default' => 'no',
                ],
                'text_handle' => [
                    'type' => 'enum',
                    'values' => ['yes', 'no'],
                    'default' => 'no',
                ],
                'handle_unique' => [
                    'type' => 'enum',
                    'values' => ['yes', 'no'],
                    'default' => 'yes',
                ],
                'default_main_lang' => [
                    'type' => 'enum',
                    'values' => ['yes', 'no'],
                    'default' => 'no',
                ],
                'required_languages' => [
                    'type' => 'varchar(255)',
                    'null' => true,
                ],
            ])
            ->keys([
                'id' => 'primary',
                'field_id' => 'unique',
            ])
            ->execute()
            ->success();
    }

    private function dropFieldTable()
    {
        return Symphony::Database()
            ->drop(self::FIELD_TABLE)
            ->ifExists()
            ->execute()
            ->success();
    }

    private function createHandle($value, $entry_id, $lang, $tbl)
    {
        $handle = Lang::createHandle(strip_tags(html_entity_decode($value)));

        if ($this->isHandleLocked($handle, $entry_id, $lang, $tbl)) {
            $count = 2;

            while ($this->isHandleLocked("{$handle}-{$count}", $entry_id, $lang, $tbl)) {
                $count++;
            }

            return "{$handle}-{$count}";
        }

        return $handle;
    }

    private function isHandleLocked($handle, $entry_id, $lang, $tbl)
    {
        $q = Symphony::Database()
            ->select(['f.id'])
            ->from($tbl, 'f')
            ->where(["f.handle-$lang" => $handle]);

        if(!is_null($entry_id)) {
            $q->where(['f.entry_id' => ['!=' => $entry_id]]);
        }

        return (boolean) $q->limit(1)->execute()->variable('id');
    }


    /*------------------------------------------------------------------------------------------------*/
    /*  Public utilities  */
    /*------------------------------------------------------------------------------------------------*/

    /**
     * Add headers to the page.
     *
     * @param $type
     */
    static public function appendHeaders($type)
    {
        if (
            (self::$appendedHeaders & $type) !== $type
            && class_exists('Administration')
            && Administration::instance() instanceof Administration
            && Administration::instance()->Page instanceof HTMLPage
        ) {
            $page = Administration::instance()->Page;

            if ($type === self::PUBLISH_HEADERS) {
                $page->addStylesheetToHead(URL . '/extensions/multilingual_field/assets/multilingual_field.publish.css', 'screen');
                $page->addScriptToHead(URL . '/extensions/multilingual_field/assets/multilingual_field.publish.js');
            }

            if ($type === self::SETTINGS_HEADERS) {
                $page->addScriptToHead(URL . '/extensions/multilingual_field/assets/multilingual_field.settings.js');
            }

            self::$appendedHeaders |= $type;
        }
    }



    /*------------------------------------------------------------------------------------------------*/
    /*  Delegates  */
    /*------------------------------------------------------------------------------------------------*/

    public function getSubscribedDelegates()
    {
        return array(
            array(
                'page'     => '/system/preferences/',
                'delegate' => 'AddCustomPreferenceFieldsets',
                'callback' => 'dAddCustomPreferenceFieldsets'
            ),
            array(
                'page'     => '/system/preferences/',
                'delegate' => 'Save',
                'callback' => 'dSave'
            ),
            array(
                'page'     => '/extensions/frontend_localisation/',
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
    public function dAddCustomPreferenceFieldsets($context)
    {
        $group = new XMLElement('fieldset');
        $group->setAttribute('class', 'settings');
        $group->appendChild(new XMLElement('legend', __('Multilingual Text Box')));

        $label = Widget::Label(__('Consolidate entry data'));
        $label->prependChild(Widget::Input('settings[multilingual_field][consolidate]', 'yes', 'checkbox', array('checked' => 'checked')));
        $group->appendChild($label);
        $group->appendChild(new XMLElement('p', __('Check this field if you want to consolidate database by <b>keeping</b> entry values of removed/old Language Driver language codes. Entry values of current language codes will not be affected.'), array('class' => 'help')));

        $context['wrapper']->appendChild($group);
    }

    /**
     * Edits the preferences to be saved
     *
     * @param array $context
     */
    public function dSave($context) {
        // prevent the saving of the values
        unset($context['settings']['multilingual_field']);
    }

    /**
     * Save options from Preferences page
     *
     * @param array $context
     */
    public function dFLSavePreferences($context)
    {
        if ($fields = Symphony::Database()->fetch(sprintf("SELECT `field_id` FROM `%s`", self::FIELD_TABLE))) {
            $new_languages = $context['new_langs'];

            // Foreach field check multilanguage values foreach language
            foreach ($fields as $field) {
                $entries_table = "tbl_entries_data_{$field["field_id"]}";

                try {
                    $current_columns = Symphony::Database()
                        ->showColumns()
                        ->from($entries_table)
                        ->like('handle-%')
                        ->execute()
                        ->rows();
                } catch (DatabaseException $dbe) {
                    // Field doesn't exist. Better remove it's settings
                    Symphony::Database()
                        ->delete(self::FIELD_TABLE)
                        ->where(['field_id' => $field["field_id"]])
                        ->execute()
                        ->success();

                    continue;
                }

                $valid_columns = array();

                // Remove obsolete fields
                if ($current_columns) {
                    $consolidate = $_POST['settings']['multilingual_field']['consolidate'] === 'yes';

                    foreach ($current_columns as $column) {
                        $column_name = $column['Field'];

                        $lc = str_replace('handle-', '', $column_name);

                        // If not consolidate option AND column lang_code not in supported languages codes -> drop Column
                        if (!$consolidate && !in_array($lc, $new_languages)) {
                            Symphony::Database()
                                ->alter($entries_table)
                                ->drop(["handle-$lc", "value-$lc", "value_formatted-$lc", "word_count-$lc"])
                                ->execute()
                                ->success();
                        }
                        else {
                            $valid_columns[] = $column_name;
                        }
                    }
                }

                // Add new fields
                foreach ($new_languages as $lc) {
                    // if columns for language don't exist, create them
                    if (!in_array("handle-$lc", $valid_columns)) {
                        Symphony::Database()
                            ->alter($entries_table)
                            ->add([
                                "handle-$lc" => [
                                    'type' => 'varchar(255)',
                                    'null' => true,
                                ],
                                "value-$lc" => [
                                    'type' => 'text',
                                    'null' => true,
                                ],
                                "value_formatted-$lc" => [
                                    'type' => 'text',
                                    'null' => true,
                                ],
                                "word_count-$lc" => [
                                    'type' => 'int(11)',
                                    'null' => true,
                                ],
                            ])
                            ->execute()
                            ->success();
                    }
                }
            }
        }
    }
}
