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
        $template->tables = $table->getDatabase()->getTables();
        return $template;
    }

    public function index($args)
    {
        // Get database and table.
        //$db = new \Tabulate\DB\Database();
        $table = $this->getTable($args['table']);

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
            $template->record = $table->getDefaultRecord();
            // Check permission.
//            if (!Grants::current_user_can(Grants::CREATE, $table->getName())) {
//                $template->add_notice('error', 'You do not have permission to create records in this table.');
//            }
            // Add query-string values.
            if (isset($args['defaults'])) {
                $template->record->setMultiple($args['defaults']);
            }
        }
        // Don't save to non-updatable views.
        if (!$table->isUpdatable()) {
            $template->addNotice('error', "This table can not be updated.");
        }

        // Return to URL.
        if (isset($_GET['return_to'])) {
            $template->return_to = $_GET['return_to'];
        } else {
            $template->return_to = $table->getUrl();
        }

        echo $template->render();
    }

    /**
     * Save the submitted data to this table.
     *
     * @param string[] $args
     */
    public function save($args)
    {
        // Note that getTable will fail with a 404 if the table's not found.
        $table = $this->getTable($args['table']);

        $recordIdent = isset($args['ident']) ? $args['ident'] : false;
        $template = $this->getTemplate($table);

        // Make sure we're not saving over an already-existing record.
        $pk_name = $table->getPkColumn()->getName();
        $pk = (isset($_POST[$pk_name])) ? $_POST[$pk_name] : null;
        if (!$recordIdent && $pk) {
            $existing = $table->getRecord($pk);
            $template->addNotice('updated', "The record identified by '$pk' already exists.");
            $_REQUEST['return_to'] = $existing->getUrl();
        } else {
            // Otherwise, create a new one.
            //try {
            $this->db->query('BEGIN');
            $template->record = $table->saveRecord($_POST, $recordIdent);
            $this->db->query('COMMIT');
            $template->addNotice('updated', 'Record saved.');
//            } catch (\Exception $e) {
//                echo $e->getMessage();
//                $template->add_notice('error', $e->getMessage());
//                $template->record = new \Tabulate\DB\Record($table, $_POST);
//            }
        }
        // Redirect back to the edit form.
        $return_to = (!empty($_REQUEST['return_to']) ) ? $_REQUEST['return_to'] : 'table/' . $table->getName() . '/' . $template->record->getPrimaryKey();
        $this->redirect($return_to);
    }

    public function delete($args)
    {
        $db = new Database();
        $table = $db->getTable($args['table']);
        $recordIdent = isset($args['ident']) ? $args['ident'] : false;
        if (!$recordIdent) {
            $this->redirect($table->getUrl());
        }

        // Ask for confirmation.
        if (!isset($_POST['confirm_deletion'])) {
            $template = new \Tabulate\Template('record/delete.html');
            $template->table = $table;
            $template->record = $table->getRecord($recordIdent);
            return $template->render();
        }

        // Delete the record.
        try {
            $this->wpdb->query('BEGIN');
            $table->deleteRecord($recordIdent);
            $this->wpdb->query('COMMIT');
        } catch (\Exception $e) {
            $template = $this->getTemplate($table);
            $template->record = $table->getRecord($recordIdent);
            $template->addNotice('error', $e->getMessage());
            return $template->render();
        }

        wp_redirect($table->getUrl());
        exit;
    }
}
