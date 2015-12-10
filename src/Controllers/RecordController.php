<?php

namespace Tabulate\Controllers;

use Tabulate\Template;
use Tabulate\DB\Database;

class RecordController extends ControllerBase
{

    /**
     * @return \Tabulate\Template
     */
    private function getTemplate(\Tabulate\DB\Table $table)
    {
        $template = new Template('record/edit.twig');
        $customTemplate = 'record/' . $table->getName() . '/edit.twig';
        if ($template->getLoader()->exists($customTemplate)) {
            $template->setTemplateName($customTemplate);
        }
        $template->table = $table;
        $template->controller = 'record';
        $template->title = $table->getTitle();
        $template->tables = $table->getDatabase()->get_tables();
        return $template;
    }

    public function index($args)
    {
        // Get database and table.
        $db = new \Tabulate\DB\Database();
        $table = $db->get_table($args['table']);

        // Give it all to the template.
        $template = $this->getTemplate($table);
        if (isset($args['ident'])) {
            $template->record = $table->getRecord($args['ident']);
            // Check permission.
//            if (!Grants::current_user_can(Grants::UPDATE, $table->getName())) {
//                $template->add_notice('error', 'You do not have permission to update data in this table.');
//            }
        }
        if (!isset($template->record) || $template->record === false) {
            $template->record = $table->get_default_record();
            // Check permission.
//            if (!Grants::current_user_can(Grants::CREATE, $table->getName())) {
//                $template->add_notice('error', 'You do not have permission to create records in this table.');
//            }
            // Add query-string values.
            if (isset($args['defaults'])) {
                $template->record->set_multiple($args['defaults']);
            }
        }
        // Don't save to non-updatable views.
        if (!$table->is_updatable()) {
            $template->add_notice('error', "This table can not be updated.");
        }

        // Return to URL.
        if (isset($args['return_to'])) {
            $template->return_to = $args['return_to'];
        }

        echo $template->render();
    }

    public function save($args)
    {
        $db = new \Tabulate\DB\Database();
        $table = $db->get_table($args['table']);
        if (!$table) {
            // It shouldn't be possible to get here via the UI, so no message.
            return false;
        }

        $record_ident = isset($args['ident']) ? $args['ident'] : false;
        $template = $this->getTemplate($table);

        // Make sure we're not saving over an already-existing record.
        $pk_name = $table->get_pk_column()->getName();
        $pk = (isset($_POST[$pk_name])) ? $_POST[$pk_name] : null;
        if (!$record_ident && $pk) {
            $existing = $table->getRecord($pk);
            $template->add_notice('updated', "The record identified by '$pk' already exists.");
            $_REQUEST['return_to'] = $existing->get_url();
        } else {
            // Otherwise, create a new one.
            //try {
                $db->query('BEGIN');
                $template->record = $table->save_record($_POST, $record_ident);
                $db->query('COMMIT');
                $template->add_notice('updated', 'Record saved.');
//            } catch (\Exception $e) {
//                echo $e->getMessage();
//                $template->add_notice('error', $e->getMessage());
//                $template->record = new \Tabulate\DB\Record($table, $_POST);
//            }
        }
        // Redirect back to the edit form.
        $return_to = (!empty($_REQUEST['return_to']) ) ? $_REQUEST['return_to'] : 'table/'.$table->getName().'/'.$template->record->getPrimaryKey();
        //$this->redirect($return_to);
    }

    public function delete($args)
    {
        $db = new Database($this->wpdb);
        $table = $db->get_table($args['table']);
        $record_ident = isset($args['ident']) ? $args['ident'] : false;
        if (!$record_ident) {
            wp_redirect($table->get_url());
            exit;
        }

        // Ask for confirmation.
        if (!isset($_POST['confirm_deletion'])) {
            $template = new \Tabulate\Template('record/delete.html');
            $template->table = $table;
            $template->record = $table->getRecord($record_ident);
            return $template->render();
        }

        // Delete the record.
        try {
            $this->wpdb->query('BEGIN');
            $table->delete_record($record_ident);
            $this->wpdb->query('COMMIT');
        } catch (\Exception $e) {
            $template = $this->getTemplate($table);
            $template->record = $table->getRecord($record_ident);
            $template->add_notice('error', $e->getMessage());
            return $template->render();
        }

        wp_redirect($table->get_url());
        exit;
    }
}
