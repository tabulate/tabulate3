<?php

namespace Tabulate\DB;

class Reports
{

    /** @const The ID of the primary report. */
    const DEFAULT_REPORT_ID = 1;

    /** @var \Tabulate\DB\Database */
    protected $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public static function reportsTableName()
    {
        return 'reports';
    }

    public static function reportSourcesTableName()
    {
        return 'report_sources';
    }

    /**
     * Get a Template instance based on a given report's template string and
     * populated with all of the report's source queries.
     * @param int $report_id
     * @return \Tabulate\Template
     */
    public function get_template($report_id)
    {
        // Find the report.
        $reports = $this->db->getTable(self::reportsTableName());
        $report = $reports->getRecord($report_id);
        if (!$report) {
            throw new \Exception("Report $report_id not found.");
        }
        $template = new \Tabulate\Template(false, $report->template());
        $template->title = $report->title();
        $template->file_extension = $report->file_extension();
        $template->mime_type = $report->mime_type();

        // Populate with source data.
        $sql = "SELECT * FROM `" . self::reportSourcesTableName() . "` WHERE report = " . $report_id;
        $sources = $this->db->query($sql)->fetchAll();
        foreach ($sources as $source) {
            $data = $this->db->query($source->query)->fetchAll();
            $template->{$source->name} = $data;
        }

        // Return the template.
        return $template;
    }
}
