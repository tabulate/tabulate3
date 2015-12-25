<?php

namespace Tabulate\Controllers;

use \Tabulate\DB\Grants;
use \Tabulate\DB\Database;
use \Tabulate\Template;

class TableController extends ControllerBase
{

    private function getTable($tableName)
    {
        $db = new Database();
        $table = $db->getTable($tableName);
        if (!$table) {
            http_response_code(404);
            throw new \Exception("Table '" . $tableName . "' not found.", 404);
        }
        return $table;
    }

    public function index($args)
    {
        $table = $this->getTable($args['table']);
        if (!$table instanceof \Tabulate\DB\Table) {
            return $table;
        }

        // Pagination.
        $page_num = (isset($args['p']) && is_numeric($args['p']) ) ? abs($args['p']) : 1;
        $table->set_current_page_num($page_num);
        if (isset($args['psize'])) {
            $table->set_records_per_page($args['psize']);
        }

        // Ordering.
        if (isset($args['order_by'])) {
            $table->set_order_by($args['order_by']);
        }
        if (isset($args['order_dir'])) {
            $table->set_order_dir($args['order_dir']);
        }

        // Filters.
        $filter_param = (isset($args['filter'])) ? $args['filter'] : array();
        $table->addFilters($filter_param);
        $filters = $table->get_filters();
        $title_col = $table->getTitleColumn();
        $first_filter = ( $title_col ) ? $title_col->getName() : '';
        $filters[] = array(
            'column' => $first_filter,
            'operator' => 'like',
            'value' => ''
        );

        // Give it all to the template.
        $template = new \Tabulate\Template('table.twig');
        $template->controller = 'table';
        $template->table = $table;
        $template->tables = $table->getDatabase()->getTables();
        $template->title = $table->getTitle();
        $template->columns = $table->getColumns();
        $template->operators = $table->get_operators();
        $template->filters = $filters;
        $template->filter_count = count($filters);
        $template->sortable = true;
        $template->record = $table->getDefaultRecord();
        $template->records = $table->getRecords();
        $template->record_count = $table->getRecordCount();
        echo $template->render();
    }

    /**
     * This action is for importing a single CSV file into a single database table.
     * It guides the user through the four stages of importing:
     * uploading, field matching, previewing, and doing the actual import.
     * All of the actual work is done in the CSV class.
     *
     * 1. In the first stage, a CSV file is **uploaded**, validated, and moved to a temporary directory.
     *    The file is then accessed from this location in the subsequent stages of importing,
     *    and only deleted upon either successful import or the user cancelling the process.
     *    (The 'id' parameter of this action is the identifier for the uploaded file.)
     * 2. Once a valid CSV file has been uploaded,
     *    its colums are presented to the user to be **matched** to those in the database table.
     *    The columns from the database are presented first and the CSV columns are matched to these,
     *    rather than vice versa,
     *    because this way the user sees immediately what columns are available to be imported into.
     * 3. The column matches are then used to produce a **preview** of what will be added to and/or changed in the database.
     *    All columns from the database are shown (regardless of whether they were in the import) and all rows of the import.
     *    If a column is not present in the import the database will (obviously) use the default value if there is one;
     *    this will be shown in the preview.
     * 4. When the user accepts the preview, the actual **import** of data is carried out.
     *    Rows are saved to the database using the usual `Table::save()` method
     *    and a message presented to the user to indicate successful completion.
     *
     * @return void
     */
    public function import($args)
    {
        $template = new Template('import.html');
        // Set up the progress bar.
        $template->stages = array(
            'choose_file',
            'match_fields',
            'preview',
            'complete_import',
        );
        $template->stage = 'choose_file';

        // First make sure the user is allowed to import data into this table.
        $table = $this->getTable($args['table']);
        $template->record = $table->getDefaultRecord();
        $template->action = 'import';
        $template->table = $table;
        $template->maxsize = \Tabulate\File::maxUploadSize();
        if (!$table->getDatabase()->checkGrant(Grants::IMPORT, $table->getName())) {
            $template->addNotice('error', 'You do not have permission to import data into this table.');
            return $template->render();
        }

        /*
         * Stage 1 of 4: Uploading.
         */
        $template->form_action = $table->getUrl('import');
        try {
            $hash = isset($_GET['hash']) ? $_GET['hash'] : false;
            $uploaded = isset($_FILES['file']) ? $_FILES['file'] : false;
            $csvFile = new \Tabulate\CSV($hash, $uploaded);
        } catch (\Exception $e) {
            $template->addNotice('error', $e->getMessage());
            return $template->render();
        }

        /*
         * Stage 2 of 4: Matching fields
         */
        if ($csvFile->loaded()) {
            $template->file = $csvFile;
            $template->stage = $template->stages[1];
            $template->form_action .= "&hash=" . $csvFile->hash;
        }

        /*
         * Stage 3 of 4: Previewing
         */
        if ($csvFile->loaded() AND isset($_POST['preview'])) {
            $template->stage = $template->stages[2];
            $template->columns = serialize($_POST['columns']);
            $errors = array();
            // Make sure all required columns are selected
            foreach ($table->getColumns() as $col) {
                // Handle missing columns separately; other column errors are
                // done in the CSV class. Missing columns don't matter if importing
                // existing records.
                $missing = empty($_POST['columns'][$col->getName()]);
                $pkPresent = isset($_POST['columns'][$table->getPkColumn()->getName()]);
                if (!$pkPresent && $col->isRequired() && $missing) {
                    $errors[] = array(
                        'column_name' => '',
                        'column_number' => '',
                        'field_name' => $col->getName(),
                        'row_number' => 'N/A',
                        'messages' => array('Column required, but not found in CSV'),
                    );
                }
            }
            $template->errors = empty($errors) ? $csvFile->matchFields($table, wp_unslash($_POST['columns'])) : $errors;
        }

        /*
         * Stage 4 of 4: Import
         */
        if ($csvFile->loaded() && isset($_POST['import'])) {
            $template->stage = $template->stages[3];
            $this->wpdb->query('BEGIN');
            $result = $csvFile->importData($table, unserialize($_POST['columns']));
            $this->wpdb->query('COMMIT');
            $template->addNotice('updated', 'Import complete; ' . $result . ' rows imported.');
        }

        return $template->render();
    }

    public function calendar($args)
    {
        // @todo Validate args.
        $yearNum = (isset($args['year'])) ? $args['year'] : date('Y');
        $monthNum = (isset($args['month'])) ? $args['month'] : date('m');

        $template = new \Tabulate\Template('calendar.html');
        $table = $this->getTable($args['table']);

        $template->table = $table;
        $template->action = 'calendar';
        $template->record = $table->getDefaultRecord();

        $factory = new \CalendR\Calendar();
        $template->weekdays = $factory->getWeek(new \DateTime('Monday this week'));
        $month = $factory->getMonth(new \DateTime($yearNum . '-' . $monthNum . '-01'));
        $template->month = $month;
        $records = array();
        foreach ($table->getColumns('date') as $dateCol) {
            $dateColName = $dateCol->getName();
            // Filter to the just the requested month.
            $table->addFilter($dateColName, '>=', $month->getBegin()->format('Y-m-d'));
            $table->addFilter($dateColName, '<=', $month->getEnd()->format('Y-m-d'));
            foreach ($table->getRecords() as $rec) {
                $dateVal = $rec->$dateColName();
                // Initialise the day's list of records.
                if (!isset($records[$dateVal])) {
                    $records[$dateVal] = array();
                }
                // Add this record to the day's list.
                $records[$dateVal][] = $rec;
            }
        }
        // $records is grouped by date, with each item in a single date being
        // an array like: ['record'=>Record, 'column'=>$name_of_date_column]
        $template->records = $records;

        return $template->render();
    }

    /**
     * Export the current table with the current filters applied.
     * Filters are passed as request parameters, just as for the index action.
     *
     * @return void
     */
    public function export($args)
    {
        // Get database and table.
        $table = $this->getTable($args['table']);

        // Filter and export.
        $filter_param = (isset($args['filter'])) ? $args['filter'] : array();
        $table->addFilters($filter_param);
        $filename = $table->export();

        // Send CSV to client.
        $download_name = date('Y-m-d') . '_' . $table->getName() . '.csv';
        header('Content-Encoding: UTF-8');
        header('Content-type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $download_name . '"');
        echo "\xEF\xBB\xBF";
        readfile($filename);
        exit(0);
    }

    public function timeline($args)
    {
        $table = $this->getTable($args['table']);
        $template = new \Tabulate\Template('timeline.html');
        $template->action = 'timeline';
        $template->table = $table;
        $start_date_arg = (isset($args['start_date'])) ? $args['start_date'] : date('Y-m-d');
        $end_date_arg = (isset($args['end_date'])) ? $args['end_date'] : date('Y-m-d');
        $start_date = new \DateTime($start_date_arg);
        $end_date = new \DateTime($end_date_arg);
        if ($start_date->diff($end_date, true)->d < 7) {
            // Add two weeks to the end date.
            $end_date->add(new \DateInterval('P14D'));
        }
        $date_period = new \DatePeriod($start_date, new \DateInterval('P1D'), $end_date);
        $template->start_date = $start_date->format('Y-m-d');
        $template->end_date = $end_date->format('Y-m-d');
        $template->date_period = $date_period;
        $data = array();
        foreach ($table->getRecords(false) as $record) {
            if (!isset($data[$record->getTitle()])) {
                $data[$record->getTitle()] = array();
            }
            $data[$record->getTitle()][] = $record;
        }
        $template->data = $data;
        return $template->render();
    }
}
