<?php

namespace Tabulate\Controllers;

use \Tabulate\DB\Database;
use \Tabulate\DB\Table;
use \Tabulate\Template;

class SchemaController extends ControllerBase
{

    public function index($args)
    {
        $db = new Database($this->wpdb);
        $tables = $db->getTables();
        $template = new Template('schema.html');
        $template->tables = $tables;
        if (isset($args['schema'])) {
            $template->schema = $db->getTable($args['schema']);
        }
        $template->types = array(
            'varchar' => 'Text (short)',
            'text' => 'Text (long)',
            'int' => 'Number',
            'date' => 'Date',
            'fk' => 'Cross reference',
        );
        return $template->render();
    }

    public function save($args)
    {
        if (!isset($args['schema'])) {
            $url = admin_url('admin.php?page=tabulate_schema');
            wp_redirect($url);
        }
        $db = new Database($this->wpdb);
        $schema = $db->getTable($args['schema']);
        $new_name = $args['schema'];
        if ($schema instanceof Table && !empty($args['new_name'])) {
            $schema->rename($args['new_name']);
            $new_name = $schema->getName();
        }
        $template = new Template('schema.html');
        $template->addNotice('updated', 'Schema updated.');
        $url = admin_url('admin.php?page=tabulate_schema&schema=' . $new_name);
        wp_redirect($url);
    }
}
