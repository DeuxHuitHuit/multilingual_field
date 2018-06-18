<?php
/*
Copyright: Deux Huit Huit 2015
LICENCE: MIT http://deuxhuithuit.mit-license.org;
*/

if(!defined("__IN_SYMPHONY__")) die("<h2>Error</h2><p>You cannot directly access this file</p>");

require_once(TOOLKIT . '/class.jsonpage.php');
require_once(EXTENSIONS . '/multilingual_field/fields/field.multilingual_textbox.php');

class contentExtensionMultilingual_FieldConvert extends JSONPage
{
    /**
     *
     * Builds the content view
     */
    public function view()
    {
        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            $this->_Result['status'] = Page::HTTP_STATUS_BAD_REQUEST;
            $this->_Result['error'] = 'This page accepts posts only';
            $this->setHttpStatus($this->_Result['status']);
            return;
        }

        if (!is_array($this->_context) || empty($this->_context)) {
            $this->_Result['error'] = 'Parameters not found';
            return;
        }

        $id = $this->_context[0];
        $this->_Result['ok'] = true;

        $field = (new FieldManager)->select()->field($id)->execute()->next();

        if ($field == null || !($field instanceof FieldTextBox)) {
            $this->_Result['error'] = "Field $id not found.";
            $this->_Result['ok'] = false;
            return;
        }

        try {
            // Check for languages
            $langs = FLang::getLangs();
            if (empty($langs)) {
                throw new Exception('No language found. Please check that you have at least one.');
            }

            $column_length = $field->get('column_length');
            $text_size = $field->get('text_size');
            $text_formatter = $field->get('text_formatter');
            $text_validator = $field->get('text_validator');
            $text_length = $field->get('text_length');
            $text_cdata = $field->get('text_cdata');
            $text_handle = $field->get('text_handle');

            // ALTER data table SQL: add new cols
            $entries_table = "tbl_entries_data_$id";
            $cols = fieldMultilingual_TextBox::generateTableColumns();
            $keys = fieldMultilingual_TextBox::generateTableKeys();
            Symphony::Database()
                ->alter($entries_table)
                ->add($cols)
                ->addKey($keys)
                ->execute()
                ->success();

            // Copy values
            $values = array();
            foreach ($langs as $lc) {
                $values["handle-$lc"] = '$handle';
                $values["value-$lc"] = '$value';
                $values["value_formatted-$lc"] = '$value_formatted';
                $values["word_count-$lc"] = '$word_count';
            }
            Symphony::Database()
                ->update($entries_table)
                ->set($values)
                ->execute()
                ->success();

            // Insert into multilingual
            Symphony::Database()
                ->insert('tbl_fields_multilingual_textbox')
                ->values([
                    'field_id' => $id,
                    'column_length' => $column_length,
                    'text_size' => $text_size,
                    'text_formatter' => $text_formatter,
                    'text_validator' => $text_validator,
                    'text_length' => $text_length,
                    'text_cdata' => $text_cdata,
                    'text_handle' => $text_handle,
                ])
                ->execute()
                ->success();

            // remove from textbox
            Symphony::Database()
                ->delete('tbl_fields_textbox')
                ->where(['field_id' => $id])
                ->execute()
                ->success();

            // update type
            Symphony::Database()
                ->update('tbl_fields')
                ->set([
                    'type' => 'multilingual_textbox',
                ])
                ->where(['id' => $id])
                ->execute()
                ->success();
        } catch (Exception $ex) {
            $this->_Result['ok'] = false;
            $this->_Result['error'] = $ex->getMessage();
        }
    }
}
