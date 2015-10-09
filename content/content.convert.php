<?php
	/*
	Copyright: Deux Huit Huit 2015
	LICENCE: MIT http://deuxhuithuit.mit-license.org;
	*/

	if(!defined("__IN_SYMPHONY__")) die("<h2>Error</h2><p>You cannot directly access this file</p>");

	require_once(TOOLKIT . '/class.jsonpage.php');
	require_once(EXTENSIONS . '/multilingual_field/fields/field.multilingual_textbox.php');

	class contentExtensionMultilingual_FieldConvert extends JSONPage {
		
		/**
		 *
		 * Builds the content view
		 */
		public function view() {
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
			
			$id = MySQL::cleanValue($this->_context[0]);
			$this->_Result['ok'] = true;
			
			$field = FieldManager::fetch($id);
			
			if ($field == null || !($field instanceof FieldTextBox)) {
				$this->_Result['error'] = "Field $id not found.";
				$this->_Result['ok'] = false;
				return;
			}
			
			try {
				$column_length = MySQL::cleanValue($field->get('column_length'));
				$text_size = MySQL::cleanValue($field->get('text_size'));
				$text_formatter = MySQL::cleanValue($field->get('text_formatter'));
				$text_validator = MySQL::cleanValue($field->get('text_validator'));
				$text_length = MySQL::cleanValue($field->get('text_length'));
				$text_cdata = MySQL::cleanValue($field->get('text_cdata'));
				$text_handle = MySQL::cleanValue($field->get('text_handle'));
				
				// ALTER data table SQL: add new cols
				$entries_table = "tbl_entries_data_$id";
				$query = "ALTER TABLE `$entries_table` ";
				$cols = fieldMultilingual_TextBox::generateTableColumns();
				foreach ($cols as $col) {
					$query .= ' ADD COLUMN ' . $col;
				}
				$keys = fieldMultilingual_TextBox::generateTableKeys();
				foreach ($keys as $key) {
					$query .= ' ADD ' . $key;
				}
				$query = trim($query, ',');
				Symphony::Database()->query($query);
				
				// Check for languages
				$langs = FLang::getLangs();
				if (empty($langs)) {
					throw new Exception('No language found. Please check that you have at least one.');
				}
				
				// Copy values
				$query = "UPDATE `$entries_table` SET ";
				foreach ($langs as $lc) {
					$query .= " `handle-$lc` = `handle`,
						 `value-$lc` = `value`,
						 `value_formatted-$lc` = `value_formatted`,
						 `word_count-$lc` = `word_count`,";
				}
				$query = trim($query, ',');
				Symphony::Database()->query($query);
				
				// Insert into multilingual
				Symphony::Database()->query("
					INSERT INTO `tbl_fields_multilingual_textbox`
						(`field_id`, `column_length`, `text_size`, `text_formatter`, `text_validator`,
						 `text_length`, `text_cdata`, `text_handle`)
					VALUES
						($id, $column_length, '$text_size', '$text_formatter', '$text_validator',
						 $text_length, '$text_cdata', '$text_handle')
				");
				
				// remove from textbox
				Symphony::Database()->query("
					DELETE FROM `tbl_fields_textbox`
						WHERE `field_id` = $id
				");
				
				// update type
				Symphony::Database()->query("
					UPDATE `tbl_fields` SET `type` = 'multilingual_textbox'
						WHERE `id` = $id
				");
				
			} catch (Exception $ex) {
				$this->_Result['ok'] = false;
				$this->_Result['error'] = $ex->getMessage();
			}
		}
	}