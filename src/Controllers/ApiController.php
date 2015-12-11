<?php

namespace Tabulate\Controllers;

use Tabulate\DB\Table;
use Tabulate\DB\Database;
use Tabulate\DB\Grants;

/**
 * This controller is different from the others in that it is not called via the
 * usual Menu dispatch system, but rather from a hook in `tabulate.php`.
 */
class ApiController extends ControllerBase
{

    /**
     * Register the API routes for Tabulate.
     * @link http://v2.wp-api.org/extending/adding/
     * @return void
     */
    public function register_routes()
    {
        register_rest_route(TABULATE_SLUG, '/tables', array(
            'methods' => 'GET',
            'callback' => array($this, 'table_names'),
        ));
        register_rest_route(TABULATE_SLUG, '/app/schema', array(
            'methods' => 'GET',
            'callback' => array($this, 'app_schema'),
        ));
        register_rest_route(TABULATE_SLUG, '/fk/(?P<table_name>.*)', array(
            'methods' => 'GET',
            'callback' => array($this, 'foreign_key_values'),
        ));
    }

    /**
     * Get a list of table names for use in the quick-jump menu.
     *
     * @return array
     */
    public function table_names()
    {
        $db = new Database($this->wpdb);
        $tables = $db->getTables(false);
        $out = array();
        foreach ($tables as $table) {
            $out[] = array(
                'value' => $table->getName(),
                'label' => $table->getTitle(),
            );
        }
        return $out;
    }

    /**
     * Privide details of the relevant parts of the database schema, for use by
     * TabulateApp.
     */
    public function app_schema()
    {
        $db = new Database($this->wpdb);
        $tables = $db->getTables();
        $out = array();
        foreach ($tables as $table) {
            if (Grants::current_user_can(Grants::CREATE, $table->getName())) {
                $out[] = $table->getName();
            }
        }
        return $out;
    }

    /**
     * Get a list of a table's records' IDs and titles, filtered by
     * `$_GET['term']`, for foreign-key fields. Only used when there are more
     * than N records in a foreign table (otherwise the options are presented in
     * a select list).
     * @param \WP_REST_Request $request The request, with a 'table_name' parameter.
     * @return array
     */
    public function foreign_key_values(\WP_REST_Request $request)
    {
        if (!isset($this->get['term'])) {
            return array();
        }
        $db = new Database($this->wpdb);
        $table = $db->getTable($request->get_param('table_name'));
        if (!$table instanceof Table) {
            return array();
        }
        // First get any exact matches.
        $out = $this->foreign_key_values_build($table, '=', $this->get['term']);
        // Then get any 'contains' matches.
        $out += $this->foreign_key_values_build($table, 'like', '%' . $this->get['term'] . '%');
        return $out;
    }

    /**
     * Get a set of results for Foreign Key lookups.
     * @param \Tabulate\DB\Table $table    The table to search.
     * @param string                       $operator One of the permitted filter operators.
     * @param string                       $term     The search term.
     * @return string[]
     */
    protected function foreign_key_values_build($table, $operator, $term)
    {
        $table->reset_filters();
        $table->addFilter($table->getTitleColumn(), $operator, $term);
        $out = array();
        foreach ($table->get_records() as $record) {
            $out[$record->getPrimaryKey()] = array(
                'value' => $record->getPrimaryKey(),
                'label' => $record->getTitle(),
            );
        }
        return $out;
    }
}
